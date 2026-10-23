<?php
$sub = str_replace('payments/', '', $_GET['route'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// Refuse to start or verify a payment when the two keys are from different
// Paystack environments. status/* reads only local rows, so it is exempt.
if (in_array($sub, ['config', 'initialize'], true) || preg_match('#^verify$#', $sub)) {
    $mismatch = paystackEnvironmentMismatch();
    if ($mismatch !== null) {
        jsonResponse(['error' => 'Payments are misconfigured on this site. Please try again later.'], 503);
    }
}

if ($sub === 'config' && $method === 'GET') {
    requireConfig('PAYSTACK_PUBLIC_KEY', 'Payments are not configured on this site.');
    jsonResponse(['publicKey' => PAYSTACK_PUBLIC_KEY]);
} elseif ($sub === 'initialize' && $method === 'POST') {
    requireConfig('PAYSTACK_SECRET_KEY', 'Payments are not configured on this site.');
    handleInitialize();
} elseif (preg_match('#^verify$#', $sub) && $method === 'GET') {
    // Verify is what marks an order paid — never call Paystack keyless.
    requireConfig('PAYSTACK_SECRET_KEY', 'Payments are not configured on this site.');
    handleVerify();
} elseif (preg_match('#^status/(\d+)$#', $sub, $m) && $method === 'GET') {
    // Reads the local order row only; no Paystack call, so no key needed.
    handleStatus($m[1]);
} else {
    jsonResponse(['error' => 'Not found'], 404);
}

function handleInitialize() {
    $input = getInput();
    $shippingAddress = trim($input['shipping_address'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $guestName = trim($input['guest_name'] ?? '');
    $guestEmail = trim($input['guest_email'] ?? '');
    $deliveryZone = $input['delivery_zone'] ?? '';

    $db = getDB();
    $isGuest = empty($_SESSION['user']);

    if (strlen($shippingAddress) < 8) jsonResponse(['error' => 'Please provide a full shipping address'], 400);
    if (!$phone) jsonResponse(['error' => 'Phone number is required'], 400);
    if (!preg_match('/^[+\d][\d\s\-()]{7,17}$/', $phone)) {
        jsonResponse(['error' => 'Please provide a valid phone number'], 400);
    }

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
            if ($item['stock'] < $item['quantity']) {
                $db->rollback();
                jsonResponse(['error' => "Insufficient stock for {$item['name']}"], 400);
            }
        }

        $subtotal = array_reduce($cartItems, fn($sum, $i) => $sum + $i['price'] * $i['quantity'], 0);
        $shippingFee = $zone['fee'];
        $grandTotal = $subtotal + $shippingFee;
        $reference = 'HLX_' . time() . '_' . bin2hex(random_bytes(6));
        $email = $isGuest ? $guestEmail : $_SESSION['user']['email'];
        $paymentStatus = 'pending';

        $userId = $isGuest ? null : $_SESSION['user']['id'];
        $gName = $isGuest ? $guestName : null;
        $gEmail = $isGuest ? $guestEmail : null;

        $stmt = $db->prepare("INSERT INTO handel_orders (user_id, guest_name, guest_email, total, shipping_fee, delivery_zone, shipping_address, phone, status, payment_method, payment_ref, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'paystack', ?, ?)");
        $stmt->bind_param('isssdsssss', $userId, $gName, $gEmail, $grandTotal, $shippingFee, $zone['id'], $shippingAddress, $phone, $reference, $paymentStatus);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        foreach ($cartItems as $item) {
            $stmt = $db->prepare("INSERT INTO handel_order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('iisid', $orderId, $item['product_id'], $item['name'], $item['quantity'], $item['price']);
            $stmt->execute();
            $stmt->close();

            // Atomic stock decrement (same guard as the Pay-on-Delivery
            // path in api/orders.php): the row lock plus `stock >= ?` makes
            // concurrent checkouts race-proof and rolls back on a shortfall
            // instead of double-selling the last unit.
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

        $customerName = $isGuest ? $guestName : $_SESSION['user']['name'];
        $customerEmail = $isGuest ? $guestEmail : $_SESSION['user']['email'];
        $itemLines = implode(', ', array_map(fn($i) => "{$i['name']} x {$i['quantity']} @ {$i['price']}", $cartItems));

        $stmt = $db->prepare("INSERT INTO handel_messages (name, email, subject, message, is_read) VALUES (?, ?, ?, ?, 0)");
        $subject = "New Order #$orderId";
        $message = "A new order was placed.\n\nCustomer: $customerName\nEmail: $customerEmail\nPhone: $phone\nShipping Address: $shippingAddress\nDelivery: {$zone['label']}\n\nItems: $itemLines\n\nSubtotal: GHS " . number_format($subtotal, 2) . "\nDelivery Fee: GHS " . number_format($shippingFee, 2) . "\nTotal: GHS " . number_format($grandTotal, 2) . "\nPayment: Paystack (awaiting payment)\nStatus: pending";
        $stmt->bind_param('ssss', $customerName, $customerEmail, $subject, $message);
        $stmt->execute();
        $stmt->close();

        $db->commit();

        $payload = json_encode([
            'email' => $email,
            // Charge the full order total (items + delivery fee), matching
            // the order record — charging only the subtotal undercharges
            // every delivery order by the fee.
            'amount' => (int)round($grandTotal * 100),
            'reference' => $reference,
            'callback_url' => base_url('/payment/verify'),
            'metadata' => [
                'order_id' => $orderId,
                'user_id' => $isGuest ? null : $_SESSION['user']['id'],
                'custom_fields' => [
                    ['display_name' => 'Order ID', 'variable_name' => 'order_id', 'value' => $orderId]
                ]
            ]
        ]);

        $ch = curl_init(PAYSTACK_BASE_URL . '/transaction/initialize');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            cleanupFailedInitialization($db, $orderId, $cartItems);
            jsonResponse(['error' => 'Payment initialization failed. Please try again.'], 500);
        }

        $paystackResponse = json_decode($response, true);
        if (!$paystackResponse || empty($paystackResponse['data']['access_code'])) {
            // Initialization rejected: remove the committed order and return
            // the reserved stock, so no orphaned pending order remains.
            cleanupFailedInitialization($db, $orderId, $cartItems);
            jsonResponse(['error' => 'Payment initialization failed. Please try again.'], 500);
        }

        // Admin WhatsApp notification — sent only after Paystack
        // initialization succeeded, so the admin is never notified about
        // an order that was rolled back (no-op until a provider is set up)
        notifyAdminsWhatsApp($orderId);

        jsonResponse([
            'success' => true,
            'order_id' => $orderId,
            'reference' => $reference,
            'access_code' => $paystackResponse['data']['access_code'],
            'total' => $grandTotal
        ]);
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        error_log('Paystack: payment initialization failed before the gateway call completed.');
        jsonResponse(['error' => 'Payment initialization failed'], 500);
    }
}

/* Remove an order whose Paystack initialization failed, restoring the
   stock it reserved. Runs after the order transaction was committed. */
function cleanupFailedInitialization($db, $orderId, $cartItems) {
    $db->begin_transaction();
    try {
        // Resolve the customer email first: the admin inbox message created
        // alongside this order must be removed too, otherwise the dashboard
        // keeps advertising an order that no longer exists.
        $stmt = $db->prepare("SELECT COALESCE(u.email, o.guest_email) AS email
            FROM handel_orders o LEFT JOIN handel_users u ON o.user_id = u.id WHERE o.id = ?");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $email = $stmt->get_result()->fetch_assoc()['email'] ?? null;
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM handel_order_items WHERE order_id = ?");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM handel_orders WHERE id = ?");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();

        if ($email !== null && $email !== '') {
            $subject = "New Order #$orderId";
            $stmt = $db->prepare("DELETE FROM handel_messages WHERE subject = ? AND email = ?");
            $stmt->bind_param('ss', $subject, $email);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare("UPDATE handel_products SET stock = stock + ? WHERE id = ?");
        foreach ($cartItems as $item) {
            $stmt->bind_param('ii', $item['quantity'], $item['product_id']);
            $stmt->execute();
        }
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
    }
}

function handleVerify() {
    // Verification is a public GET that calls Paystack; without a limit a
    // script could burn API quota. The webhook covers legitimate paid orders
    // even if a verifier is throttled.
    if (!checkRateLimit('paystack_verify_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 60, 3600)) {
        jsonResponse(['error' => 'Too many verification attempts, please try again later'], 429);
    }

    $reference = trim($_GET['reference'] ?? '');
    if (!$reference || strlen($reference) > 100) jsonResponse(['error' => 'No valid reference provided'], 400);

    $ch = curl_init(PAYSTACK_BASE_URL . '/transaction/verify/' . rawurlencode($reference));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($response === false || $curlError) {
        jsonResponse(['error' => 'Could not reach Paystack. Please try again.'], 502);
    }

    $data = json_decode($response, true);
    $payload = is_array($data['data'] ?? null) ? $data['data'] : [];
    $status = $payload['status'] ?? 'failed';
    $db = getDB();

    if ($status === 'success') {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("SELECT * FROM handel_orders WHERE payment_ref = ? FOR UPDATE");
            $stmt->bind_param('s', $reference);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                $db->rollback();
                jsonResponse(['error' => 'Order not found for this payment reference'], 404);
            }

            // Never trust a success callback alone: the charged amount and
            // currency must match the order we recorded. A mismatch is left
            // pending for the admin to investigate rather than marked paid.
            $expectedMinor = (int)round(((float)$existing['total']) * 100);
            $paidMinor = (int)($payload['amount'] ?? -1);
            $currency = strtoupper((string)($payload['currency'] ?? ''));
            if ($paidMinor !== $expectedMinor || $currency !== 'GHS') {
                $db->rollback();
                try {
                    writeAuditLog('payment.amount_mismatch', 'system', null, 'Paystack', [
                        'reference' => $reference,
                        'order_id' => (int)$existing['id'],
                        'expected_minor' => $expectedMinor,
                        'paid_minor' => $paidMinor,
                        'currency' => $currency,
                    ]);
                } catch (Throwable $e) {}
                jsonResponse([
                    'success' => false,
                    'status' => 'amount_mismatch',
                    'message' => 'The payment amount did not match the order total. Please contact support.',
                ]);
            }

            $orderId = (int)$existing['id'];
            $wasStatus = $existing['payment_status'];
            $becameSuccess = false;

            if ($wasStatus !== 'success') {
                // A previously failed payment had its stock returned. A later
                // success must reserve it again before the order is marked paid.
                if ($wasStatus === 'failed' && $existing['status'] !== 'cancelled') {
                    if (!reserveOrderStock($db, $orderId)) {
                        $db->rollback();
                        try {
                            writeAuditLog('payment.restock_failed', 'system', null, 'Paystack', [
                                'reference' => $reference,
                                'order_id' => $orderId,
                            ]);
                        } catch (Throwable $e) {}
                        jsonResponse([
                            'success' => false,
                            'status' => 'stock_unavailable',
                            'message' => 'Payment succeeded but stock could not be reserved. Please contact support.',
                        ], 409);
                    }
                }

                $stmt = $db->prepare("UPDATE handel_orders SET payment_status = 'success' WHERE id = ?");
                $stmt->bind_param('i', $orderId);
                $stmt->execute();
                $stmt->close();
                $becameSuccess = true;
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            jsonResponse(['error' => 'Could not verify payment'], 500);
        }

        // Clear the cart after every confirmed guest payment, including those
        // already marked paid by the webhook (which has no guest session).
        // Logged-in carts are cleared by whichever path marked success first.
        if ($becameSuccess && $existing['user_id']) {
            $stmt = $db->prepare("DELETE FROM handel_cart WHERE user_id = ?");
            $stmt->bind_param('i', $existing['user_id']);
            $stmt->execute();
            $stmt->close();
        } elseif (!$existing['user_id'] && !empty($_SESSION['guest_token'])) {
            $stmt = $db->prepare("DELETE FROM handel_cart WHERE guest_token = ?");
            $stmt->bind_param('s', $_SESSION['guest_token']);
            $stmt->execute();
            $stmt->close();
        }

        if ($becameSuccess) notifyAdminOrderEmail($orderId);

        jsonResponse(['success' => true, 'status' => 'success', 'order_id' => $orderId]);
    }

    // Failed/abandoned verification: restore stock once, and never demote an
    // already-successful payment or a cancelled order.
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT * FROM handel_orders WHERE payment_ref = ? FOR UPDATE");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && $existing['payment_status'] === 'pending') {
            $stmt = $db->prepare("UPDATE handel_orders SET payment_status = 'failed' WHERE id = ? AND payment_status = 'pending'");
            $stmt->bind_param('i', $existing['id']);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed > 0 && $existing['status'] !== 'cancelled') {
                restoreOrderStock($db, (int)$existing['id']);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        jsonResponse(['error' => 'Could not update payment status'], 500);
    }

    jsonResponse(['success' => false, 'status' => $status]);
}

function handleStatus($orderId) {
    requireAuth();
    $db = getDB();
    $stmt = $db->prepare("SELECT payment_status, payment_ref FROM handel_orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $orderId, $_SESSION['user']['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) jsonResponse(['error' => 'Order not found'], 404);
    jsonResponse($result->fetch_assoc());
}
