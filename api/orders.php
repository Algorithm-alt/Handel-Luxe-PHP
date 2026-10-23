<?php
// Route arrives as 'orders', 'orders/', 'orders/place', etc. — strip the
// leading segment AND trailing slash so a bare 'orders' (the My Orders
// page calls /api/orders with no trailing slash) lists orders.
$sub = str_replace('orders/', '', $_GET['route'] ?? '');
if ($sub === 'orders') $sub = '';
$method = $_SERVER['REQUEST_METHOD'];

if ($sub === 'place' && $method === 'POST') {
    // Order placement writes to many tables and emails the admin.
    if (!checkRateLimit('orderplace_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 20, 900)) {
        jsonResponse(['error' => 'Too many attempts, please try again later'], 429);
    }
    handlePlaceOrder();
} elseif ($sub === '' && $method === 'GET') {
    handleListOrders();
} elseif (preg_match('#^guest/lookup$#', $sub) && $method === 'GET') {
    handleGuestLookup();
} elseif (preg_match('#^(\d+)$#', $sub, $m) && $method === 'GET') {
    handleOrderDetail($m[1]);
} else {
    jsonResponse(['error' => 'Not found'], 404);
}

function handlePlaceOrder() {
    $input = getInput();
    $shippingAddress = trim($input['shipping_address'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $paymentMethod = $input['payment_method'] ?? 'delivery';
    $guestName = trim($input['guest_name'] ?? '');
    $guestEmail = trim($input['guest_email'] ?? '');
    $deliveryZone = $input['delivery_zone'] ?? '';

    $db = getDB();
    $isGuest = empty($_SESSION['user']);

    if (strlen($shippingAddress) < 8) jsonResponse(['error' => 'Please provide a full shipping address'], 400);
    if (!$phone) jsonResponse(['error' => 'Phone number is required'], 400);
    if (!preg_match('/^[+\d][\d\s\-()]{7,17}$/', $phone)) jsonResponse(['error' => 'Please provide a valid phone number'], 400);
    if ($paymentMethod !== 'delivery') jsonResponse(['error' => 'Invalid payment method'], 400);

    $zone = lookupZone($deliveryZone);
    if ($deliveryZone === '' || (string)$zone['id'] !== $deliveryZone) {
        jsonResponse(['error' => 'Please select a valid delivery area'], 400);
    }

    if ($isGuest) {
        if (!$guestName) jsonResponse(['error' => 'Your name is required'], 400);
        if (strlen($guestName) > 100) jsonResponse(['error' => 'Name must be 100 characters or fewer'], 400);
        if (strlen($guestEmail) > 100 || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'A valid email is required'], 400);
    }

    $db->begin_transaction();
    try {
        $cartItems = getCartItemsForOrder($db, $isGuest);
        if (empty($cartItems)) {
            $db->rollback();
            jsonResponse(['error' => 'Cart is empty'], 400);
        }

        foreach ($cartItems as $item) {
            if ($item['quantity'] <= 0 || $item['quantity'] > 999) {
                $db->rollback();
                jsonResponse(['error' => 'Invalid quantity in cart'], 400);
            }
            if ($item['stock'] < $item['quantity']) {
                $db->rollback();
                jsonResponse(['error' => "Insufficient stock for {$item['name']}"], 400);
            }
        }

        $subtotal = array_reduce($cartItems, fn($sum, $i) => $sum + $i['price'] * $i['quantity'], 0);
        $shippingFee = $zone['fee'];
        $grandTotal = $subtotal + $shippingFee;
        $paymentStatus = 'pending';

        $userId = $isGuest ? null : $_SESSION['user']['id'];
        $gName = $isGuest ? $guestName : null;
        $gEmail = $isGuest ? $guestEmail : null;

        $stmt = $db->prepare("INSERT INTO handel_orders (user_id, guest_name, guest_email, total, shipping_fee, delivery_zone, shipping_address, phone, status, payment_method, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
        $stmt->bind_param('isssdsssss', $userId, $gName, $gEmail, $grandTotal, $shippingFee, $zone['id'], $shippingAddress, $phone, $paymentMethod, $paymentStatus);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        foreach ($cartItems as $item) {
            $stmt = $db->prepare("INSERT INTO handel_order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('iisid', $orderId, $item['product_id'], $item['name'], $item['quantity'], $item['price']);
            $stmt->execute();
            $stmt->close();

            // Decrement stock atomically — the row lock + `stock >= ?` guard
            // makes concurrent checkouts race-proof. Two requests that both
            // pass the pre-check above can still oversell the last unit; the
            // affected-rows check rolls the whole order back if one of them
            // loses the race, instead of silently selling stock twice.
            $stmt = $db->prepare("UPDATE handel_products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt->bind_param('iii', $item['quantity'], $item['product_id'], $item['quantity']);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                $db->rollback();
                jsonResponse(['error' => "Insufficient stock for {$item['name']}"], 400);
            }
            $stmt->close();
        }

        if ($isGuest && !empty($_SESSION['guest_token'])) {
            $stmt = $db->prepare("DELETE FROM handel_cart WHERE guest_token = ?");
            $stmt->bind_param('s', $_SESSION['guest_token']);
            $stmt->execute();
            $stmt->close();
        } elseif (!$isGuest) {
            $stmt = $db->prepare("DELETE FROM handel_cart WHERE user_id = ?");
            $stmt->bind_param('i', $_SESSION['user']['id']);
            $stmt->execute();
            $stmt->close();
        }

        $customerName = $isGuest ? $guestName : $_SESSION['user']['name'];
        $customerEmail = $isGuest ? $guestEmail : $_SESSION['user']['email'];
        $itemLines = implode(', ', array_map(fn($i) => "{$i['name']} x {$i['quantity']} @ {$i['price']}", $cartItems));

        $stmt = $db->prepare("INSERT INTO handel_messages (name, email, subject, message, is_read) VALUES (?, ?, ?, ?, 0)");
        $subject = "New Order #$orderId";
        $message = "A new order was placed.\n\nCustomer: $customerName\nEmail: $customerEmail\nPhone: $phone\nShipping Address: $shippingAddress\nDelivery: {$zone['label']}\n\nItems: $itemLines\n\nSubtotal: GHS " . number_format($subtotal, 2) . "\nDelivery Fee: GHS " . number_format($shippingFee, 2) . "\nTotal: GHS " . number_format($grandTotal, 2) . "\nPayment: Pay on Delivery\nStatus: pending";
        $stmt->bind_param('ssss', $customerName, $customerEmail, $subject, $message);
        $stmt->execute();
        $stmt->close();

        $db->commit();

        // Admin order notification — email (works out of the box via SMTP);
        // WhatsApp needs a provider configured before it sends anything.
        notifyAdminOrderEmail($orderId);
        notifyAdminsWhatsApp($orderId);

        jsonResponse(['success' => true, 'order_id' => $orderId]);
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['error' => 'Failed to place order'], 500);
    }
}

function handleListOrders() {
    requireAuth();
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM handel_orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $_SESSION['user']['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    jsonResponse($orders);
}

function handleGuestLookup() {
    // Guest lookup matches an id + email pair — rate limit so it can't be
    // scripted to enumerate orders or guess emails.
    if (!checkRateLimit('guestlookup_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 600)) {
        jsonResponse(['error' => 'Too many lookups, please try again later'], 429);
    }

    $email = trim($_GET['email'] ?? '');
    $id = (int)($_GET['id'] ?? 0);

    if (!$email) jsonResponse(['error' => 'Email is required'], 400);
    if (!$id) jsonResponse(['error' => 'Order ID is required'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM handel_orders WHERE id = ? AND guest_email = ? AND user_id IS NULL");
    $stmt->bind_param('is', $id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) jsonResponse(['error' => 'Order not found'], 404);

    $order = $result->fetch_assoc();

    $stmt = $db->prepare("SELECT oi.*, COALESCE(p.name, oi.product_name, 'Deleted product') AS name, p.image
        FROM handel_order_items oi LEFT JOIN handel_products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    $stmt->close();

    $items = [];
    while ($row = $itemsResult->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $items[] = $row;
    }

    $order['items'] = $items;
    jsonResponse($order);
}

function handleOrderDetail($id) {
    requireAuth();
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM handel_orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $_SESSION['user']['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) jsonResponse(['error' => 'Order not found'], 404);
    $order = $result->fetch_assoc();

    $stmt = $db->prepare("SELECT oi.*, COALESCE(p.name, oi.product_name, 'Deleted product') AS name, p.image
        FROM handel_order_items oi LEFT JOIN handel_products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    $stmt->close();

    $items = [];
    while ($row = $itemsResult->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $items[] = $row;
    }

    $order['items'] = $items;
    jsonResponse($order);
}

