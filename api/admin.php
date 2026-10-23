<?php
$sub = str_replace('admin/', '', $_GET['route'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

switch ($sub) {
    case 'stats':
        requireAdmin();
        handleStats();
        break;
    case 'orders':
        requireAdmin();
        if ($method === 'GET') handleAdminOrders();
        break;
    case 'all-products':
        requireAdmin();
        handleAllProducts();
        break;
    case 'users':
        requireAdmin();
        if ($method === 'GET') handleAdminUsers();
        break;
    case 'messages':
        requireAdmin();
        if ($method === 'GET') handleAdminMessages();
        break;
    case 'categories':
        requireAdmin();
        handleAdminCategories();
        break;
    case 'profile':
        requireAdmin();
        handleAdminProfile();
        break;
    case 'notifications':
        requireAdmin();
        handleNotifications();
        break;
    case 'newsletter':
        requireAdmin();
        if ($method === 'POST') handleNewsletter();
        break;
    case 'delivery-zones':
        requireAdmin();
        if ($method === 'GET') handleDeliveryZones();
        elseif ($method === 'POST') handleAddDeliveryZone();
        else jsonResponse(['error' => 'Invalid method'], 405);
        break;
    default:
        if (preg_match('#^orders/(\d+)/status$#', $sub, $m)) {
            requireAdmin();
            // Mutations must pin their method — GETs bypass the CSRF check.
            if ($method === 'PUT' || $method === 'POST') handleUpdateOrderStatus($m[1]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^orders/(\d+)/payment$#', $sub, $m)) {
            requireAdmin();
            if ($method === 'PUT' || $method === 'POST') handleUpdatePaymentStatus($m[1]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^orders/(\d+)/invoice$#', $sub, $m)) {
            requireAdmin();
            handleInvoice($m[1]);
        } elseif (preg_match('#^orders/(\d+)$#', $sub, $m)) {
            requireAdmin();
            handleAdminOrderDetail($m[1]);
        } elseif (preg_match('#^products$#', $sub) && $method === 'POST') {
            requireAdmin();
            handleAddProduct();
        } elseif (preg_match('#^products/(\d+)$#', $sub, $m)) {
            requireAdmin();
            // POST is accepted alongside PUT because PHP does not parse
            // multipart/form-data bodies on PUT requests — the admin edit
            // form (which may carry an image file) submits via POST.
            if ($method === 'PUT' || $method === 'POST') handleUpdateProduct($m[1]);
            elseif ($method === 'DELETE') handleDeleteProduct($m[1]);
        } elseif (preg_match('#^products/(\d+)/stock$#', $sub, $m)) {
            requireAdmin();
            // State-mutating routes must pin their method: a GET would fall
            // through the CSRF check (GETs are exempt) and still hit this,
            // and an empty body parses to stock = 0 — wiping the product.
            if ($method === 'PUT' || $method === 'POST') handleUpdateStock($m[1]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^products/(\d+)/images/(\d+)$#', $sub, $m)) {
            requireAdmin();
            // Deletion is a mutation — never GET-reachable (GETs skip CSRF).
            if ($method === 'DELETE' || $method === 'POST') handleDeleteProductImage($m[1], $m[2]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^products/(\d+)/images/(\d+)/cover$#', $sub, $m)) {
            requireAdmin();
            if ($method === 'PUT' || $method === 'POST') handleSetCoverImage($m[1], $m[2]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^categories/(\d+)$#', $sub, $m)) {
            requireAdmin();
            if ($method === 'PUT') handleUpdateCategory($m[1]);
            elseif ($method === 'DELETE') handleDeleteCategory($m[1]);
        } elseif (preg_match('#^users/(\d+)/role$#', $sub, $m)) {
            requireAdmin();
            // State-mutating routes must pin their method: a GET would fall
            // through the CSRF check (GETs are exempt) and still hit this.
            if ($method === 'PUT' || $method === 'POST') handleUpdateUserRole($m[1]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^users/(\d+)/orders$#', $sub, $m)) {
            requireAdmin();
            handleUserOrders($m[1]);
        } elseif (preg_match('#^users/(\d+)$#', $sub, $m)) {
            requireAdmin();
            if ($method === 'DELETE') handleDeleteUser($m[1]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^messages/(\d+)/read$#', $sub, $m)) {
            requireAdmin();
            // Same as the role route — mutations must never be GET-reachable.
            if ($method === 'PUT' || $method === 'POST') handleMarkRead($m[1]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^messages/(\d+)/unread$#', $sub, $m)) {
            requireAdmin();
            if ($method === 'PUT' || $method === 'POST') handleMarkUnread($m[1]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } elseif (preg_match('#^messages/(\d+)$#', $sub, $m)) {
            requireAdmin();
            if ($method === 'DELETE') handleDeleteMessage($m[1]);
        } elseif (preg_match('#^delivery-zones/([a-z0-9\-_]+)$#i', $sub, $m)) {
            // Zone ids are slugs (swedru, collect...), NOT numeric — a \d+
            // pattern here made every save/delete 404 with "Not found".
            requireAdmin();
            if ($method === 'PUT') handleUpdateDeliveryZone($m[1]);
            elseif ($method === 'DELETE') handleDeleteDeliveryZone($m[1]);
        } elseif (preg_match('#^export/(\w+)$#', $sub, $m)) {
            requireAdmin();
            if ($method === 'GET') handleExport($m[1]);
            else jsonResponse(['error' => 'Invalid method'], 405);
        } else {
            jsonResponse(['error' => 'Not found'], 404);
        }
}

function handleStats() {
    $db = getDB();
    $stats = [];

    $r = $db->query("SELECT COUNT(*) as count FROM handel_users"); $stats['users'] = $r->fetch_assoc()['count'];
    $r = $db->query("SELECT COUNT(*) as count FROM handel_products"); $stats['products'] = $r->fetch_assoc()['count'];
    $r = $db->query("SELECT COUNT(*) as count FROM handel_orders"); $stats['orders'] = $r->fetch_assoc()['count'];
    $r = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM handel_orders WHERE status != 'cancelled'"); $stats['revenue'] = $r->fetch_assoc()['total'];
    $r = $db->query("SELECT COUNT(*) as count FROM handel_orders WHERE payment_method = 'paystack' AND payment_status = 'pending'"); $stats['pending_payments'] = $r->fetch_assoc()['count'];
    $r = $db->query("SELECT COALESCE(SUM(stock), 0) as total FROM handel_products"); $stats['total_stock'] = $r->fetch_assoc()['total'];
    $r = $db->query("SELECT COUNT(*) as count FROM handel_orders WHERE DATE(created_at) = CURDATE()"); $stats['orders_today'] = $r->fetch_assoc()['count'];
    $r = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM handel_orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'"); $stats['revenue_today'] = $r->fetch_assoc()['total'];
    $r = $db->query("SELECT COUNT(*) as count FROM handel_products WHERE stock <= 5"); $stats['low_stock'] = $r->fetch_assoc()['count'];

    $r = $db->query("SELECT COALESCE(p.name, oi.product_name, 'Deleted product') AS name, p.image, SUM(oi.quantity) as qty, SUM(oi.quantity * oi.price) as total
        FROM handel_order_items oi LEFT JOIN handel_products p ON oi.product_id = p.id
        JOIN handel_orders o ON o.id = oi.order_id WHERE o.status != 'cancelled'
        GROUP BY COALESCE(p.name, oi.product_name, 'Deleted product'), p.image ORDER BY qty DESC LIMIT 5");
    $stats['top_sellers'] = [];
    while ($row = $r->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $stats['top_sellers'][] = $row;
    }

    $r = $db->query("SELECT o.id, o.total, o.payment_method, o.status, o.created_at, COALESCE(u.name, o.guest_name) as customer_name
        FROM handel_orders o LEFT JOIN handel_users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 8");
    $stats['recent_orders'] = [];
    while ($row = $r->fetch_assoc()) $stats['recent_orders'][] = $row;

    $r = $db->query("SELECT DATE(created_at) as day, COALESCE(SUM(total), 0) as total FROM handel_orders
        WHERE status != 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at) ORDER BY day ASC");
    $stats['daily_revenue'] = [];
    while ($row = $r->fetch_assoc()) $stats['daily_revenue'][] = $row;

    jsonResponse($stats);
}

function handleAdminOrders() {
    $db = getDB();
    $r = $db->query("SELECT o.*, COALESCE(u.name, o.guest_name) as customer_name, COALESCE(u.email, o.guest_email) as customer_email
        FROM handel_orders o LEFT JOIN handel_users u ON o.user_id = u.id ORDER BY o.created_at DESC");
    $orders = [];
    while ($row = $r->fetch_assoc()) $orders[] = $row;
    jsonResponse($orders);
}

function handleAdminOrderDetail($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT o.*, COALESCE(u.name, o.guest_name) as customer_name, COALESCE(u.email, o.guest_email) as customer_email
        FROM handel_orders o LEFT JOIN handel_users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result();
    $stmt->close();
    if ($r->num_rows === 0) jsonResponse(['error' => 'Order not found'], 404);
    $order = $r->fetch_assoc();

    $stmt = $db->prepare("SELECT oi.*, COALESCE(p.name, oi.product_name, 'Deleted product') AS name, p.image
        FROM handel_order_items oi LEFT JOIN handel_products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $itemsR = $stmt->get_result();
    $stmt->close();
    $items = [];
    while ($row = $itemsR->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $items[] = $row;
    }
    $order['items'] = $items;
    jsonResponse($order);
}

function handleUpdateOrderStatus($id) {
    $input = getInput();
    $status = $input['status'] ?? '';
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) jsonResponse(['error' => 'Invalid status'], 400);
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM handel_orders WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) jsonResponse(['error' => 'Order not found'], 404);
    if ($order['status'] === $status) jsonResponse(['success' => true, 'stock_restored' => false, 'stock_reserved' => false]);

    // A failed Paystack payment already returned its stock. Cancelling that
    // order must not return it a second time.
    $stockAlreadyReleased = $order['payment_method'] === 'paystack' && $order['payment_status'] === 'failed';

    if ($status === 'cancelled') {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("UPDATE handel_orders SET status = 'cancelled' WHERE id = ? AND status != 'cancelled'");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed > 0 && !$stockAlreadyReleased) {
                restoreOrderStock($db, (int)$id);
            }
            $db->commit();
            jsonResponse(['success' => true, 'stock_restored' => $changed > 0 && !$stockAlreadyReleased]);
        } catch (Throwable $e) {
            $db->rollback();
            jsonResponse(['error' => 'Could not cancel order'], 500);
        }
    }

    // Re-opening a cancelled order has to reserve its items again, or the
    // catalogue would show stock that this order is about to consume.
    if ($order['status'] === 'cancelled') {
        $db->begin_transaction();
        try {
            // A cancelled Paystack order whose payment had failed carries no
            // reserved stock. Reopening reserves it, so reset the payment to
            // pending in the same update; otherwise a later failed->success
            // transition would reserve the same items a second time.
            if ($order['payment_method'] === 'paystack' && $order['payment_status'] === 'failed') {
                $stmt = $db->prepare("UPDATE handel_orders SET status = ?, payment_status = 'pending' WHERE id = ? AND status = 'cancelled'");
                $stmt->bind_param('si', $status, $id);
            } else {
                $stmt = $db->prepare("UPDATE handel_orders SET status = ? WHERE id = ? AND status = 'cancelled'");
                $stmt->bind_param('si', $status, $id);
            }
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed > 0 && !reserveOrderStock($db, (int)$id)) {
                $db->rollback();
                jsonResponse(['error' => 'Not enough stock to reopen this order'], 409);
            }
            $db->commit();
            jsonResponse(['success' => true, 'stock_reserved' => $changed > 0]);
        } catch (Throwable $e) {
            $db->rollback();
            jsonResponse(['error' => 'Could not reopen order'], 500);
        }
    }

    $stmt = $db->prepare("UPDATE handel_orders SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true, 'stock_restored' => false, 'stock_reserved' => false]);
}

function handleUpdatePaymentStatus($id) {
    $input = getInput();
    $ps = $input['payment_status'] ?? '';
    $allowed = ['pending', 'success', 'failed'];
    if (!in_array($ps, $allowed)) jsonResponse(['error' => 'Invalid payment status'], 400);
    $db = getDB();

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT * FROM handel_orders WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) {
            $db->rollback();
            jsonResponse(['error' => 'Order not found'], 404);
        }
        if ($order['payment_status'] === $ps) {
            $db->commit();
            jsonResponse(['success' => true]);
        }

        // Paystack stock was reserved at initialization. Moving to failed
        // releases it; moving back out of failed reserves it again. POD
        // payment status is informational and does not change stock.
        if ($order['payment_method'] === 'paystack' && $order['status'] !== 'cancelled') {
            if ($ps === 'failed') {
                restoreOrderStock($db, (int)$id);
            } elseif ($order['payment_status'] === 'failed') {
                if (!reserveOrderStock($db, (int)$id)) {
                    $db->rollback();
                    jsonResponse(['error' => 'Not enough stock to reopen this payment'], 409);
                }
            }
        }

        $stmt = $db->prepare("UPDATE handel_orders SET payment_status = ? WHERE id = ?");
        $stmt->bind_param('si', $ps, $id);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        jsonResponse(['error' => 'Could not update payment status'], 500);
    }
    jsonResponse(['success' => true]);
}

function handleAllProducts() {
    $db = getDB();
    ensureImagesTable();
    $r = $db->query("SELECT p.*, c.name as category_name FROM handel_products p LEFT JOIN handel_categories c ON p.category_id = c.id ORDER BY p.created_at DESC");
    $products = [];
    while ($row = $r->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $products[] = $row;
    }

    // Attach galleries in one query rather than N — the admin table shows an
    // image count per product and the edit form needs the full list.
    if ($products) {
        $ids = array_map('intval', array_column($products, 'id'));
        $in = implode(',', $ids);
        $imgRows = [];
        $res = $db->query("SELECT id, product_id, image, sort_order FROM handel_product_images WHERE product_id IN ($in) ORDER BY sort_order ASC, id ASC");
        while ($img = $res->fetch_assoc()) {
            $img['id'] = (int)$img['id'];
            $img['sort_order'] = (int)$img['sort_order'];
            $img['image'] = image_url($img['image']);
            $imgRows[(int)$img['product_id']][] = $img;
        }
        foreach ($products as &$p) {
            $sid = (int)$p['id'];
            // Legacy products with no gallery rows get a virtual single-image
            // gallery so the edit form always has at least one entry.
            $p['images'] = $imgRows[$sid] ?? [[
                'id' => 0,
                'product_id' => $sid,
                'image' => $p['image'],
                'sort_order' => 0,
            ]];
            $p['image_count'] = count($p['images']);
        }
        unset($p);
    }

    jsonResponse($products);
}

function validProductCategoryId($db, $categoryId) {
    if ($categoryId <= 0) return null;
    $stmt = $db->prepare("SELECT id FROM handel_categories WHERE id = ?");
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$found) jsonResponse(['error' => 'Selected category does not exist'], 400);
    return $categoryId;
}

function handleAddProduct() {
    if (oversizedPostDetected()) jsonResponse(['error' => oversizedPostError()], 400);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $featured = (!empty($_POST['featured']) && $_POST['featured'] !== 'false') ? 1 : 0;

    if (!$name || $price <= 0) jsonResponse(['error' => 'Name and price are required'], 400);
    if (strlen($name) > 200) jsonResponse(['error' => 'Name must be 200 characters or fewer'], 400);
    if ($stock < 0) jsonResponse(['error' => 'Stock cannot be negative'], 400);

    $files = normaliseUploadedFiles('images');
    $imageUrl = trim($_POST['image_url'] ?? '');

    // A product needs at least one image. Gallery uploads are the normal
    // route; an explicit image URL (or the placeholder) is still accepted
    // so the API stays usable for scripted imports.
    if (count($files) === 0 && $imageUrl === '') {
        jsonResponse(['error' => 'Please add at least 1 image for this product.'], 400);
    }
    if (count($files) > GALLERY_MAX_IMAGES) {
        jsonResponse(['error' => 'You can upload at most ' . GALLERY_MAX_IMAGES . ' images per product.'], 400);
    }

    $db = getDB();
    $catId = validProductCategoryId($db, $categoryId);
    $image = $imageUrl !== '' ? $imageUrl : '/images/placeholder.png';

    $stmt = $db->prepare("INSERT INTO handel_products (name, description, price, category_id, stock, image, featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssdiisi', $name, $description, $price, $catId, $stock, $image, $featured);
    $stmt->execute();
    $productId = $stmt->insert_id;
    $stmt->close();

    // Seed the gallery: either the uploaded files or the single image URL.
    try {
        if ($files) {
            applyProductImages($productId, $files, [], true);
            // Re-sync the cover now the gallery exists (the placeholder
            // written above is replaced by the first uploaded image).
            syncProductCover($productId);
        } else {
            insertProductImage($productId, $image, 0);
        }
    } catch (Throwable $e) {
        // Roll the half-made product back so a failed upload never leaves
        // an image-less product behind.
        removeProductAndFiles($productId);
        jsonResponse(['error' => $e->getMessage()], 400);
    }

    jsonResponse(['success' => true, 'id' => $productId]);
}

/* Delete a product plus any gallery files it owns. The gallery rows
   themselves go with the FK cascade. Used both to undo a partial create and
   to service an admin delete, so removing a product never leaves orphaned
   uploads behind in public/uploads. removeUploadedImageFiles() only unlinks
   paths under /uploads/, so the seeded /images/products/* static files that
   most catalogue entries reference are left untouched. */
function removeProductAndFiles($productId) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT image FROM handel_product_images WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) removeUploadedImageFiles($r['image']);

    $stmt = $db->prepare("DELETE FROM handel_products WHERE id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $stmt->close();
}

function handleUpdateProduct($id) {
    if (oversizedPostDetected()) jsonResponse(['error' => oversizedPostError()], 400);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $featured = (!empty($_POST['featured']) && $_POST['featured'] !== 'false') ? 1 : 0;

    if (!$name || $price <= 0) jsonResponse(['error' => 'Name and price are required'], 400);
    if (strlen($name) > 200) jsonResponse(['error' => 'Name must be 200 characters or fewer'], 400);
    if ($stock < 0) jsonResponse(['error' => 'Stock cannot be negative'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM handel_products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$exists) jsonResponse(['error' => 'Product not found'], 404);

    // Existing products may predate the gallery — seed it before editing.
    ensureProductGallery($id);

    $files = normaliseUploadedFiles('images');
    $removeIds = $_POST['remove_images'] ?? [];
    if (is_string($removeIds)) $removeIds = array_filter(explode(',', $removeIds));
    if (!is_array($removeIds)) $removeIds = [];

    if (count($files) > GALLERY_MAX_IMAGES) {
        jsonResponse(['error' => 'You can upload at most ' . GALLERY_MAX_IMAGES . ' images per product.'], 400);
    }

    try {
        applyProductImages($id, $files, $removeIds, true);
    } catch (Throwable $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }

    // The cover is owned by the gallery order; text columns update separately.
    // Deliberately after the gallery: applyProductImages() changes nothing when
    // it refuses a batch, so a rejected edit leaves the whole product — text
    // included — exactly as it was. Moving this above the call would apply
    // half an edit whenever an image was refused.
    $catId = validProductCategoryId($db, $categoryId);
    $stmt = $db->prepare("UPDATE handel_products SET name=?, description=?, price=?, category_id=?, stock=?, featured=? WHERE id=?");
    $stmt->bind_param('ssdiisi', $name, $description, $price, $catId, $stock, $featured, $id);
    $stmt->execute();
    $stmt->close();

    jsonResponse(['success' => true]);
}

/* Remove one gallery image. Refuses to empty the gallery — a product must
   always keep at least one image. */
function handleDeleteProductImage($productId, $imageId) {
    if (countProductImages($productId) <= GALLERY_MIN_IMAGES) {
        jsonResponse(['error' => 'A product must keep at least 1 image. Upload a replacement first.'], 400);
    }
    if (!deleteProductImage($productId, $imageId)) {
        jsonResponse(['error' => 'Image not found'], 404);
    }
    syncProductCover($productId);
    jsonResponse(['success' => true]);
}

/* Promote a gallery image to the cover shown in listings and carts. */
function handleSetCoverImage($productId, $imageId) {
    ensureImagesTable();
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM handel_product_images WHERE id = ? AND product_id = ?");
    $stmt->bind_param('ii', $imageId, $productId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$found) jsonResponse(['error' => 'Image not found'], 404);

    // Cover = lowest sort_order, so shifting this image above the current
    // minimum is what actually reorders it.
    $stmt = $db->prepare("SELECT MIN(sort_order) AS m FROM handel_product_images WHERE product_id = ? AND id != ?");
    $stmt->bind_param('ii', $productId, $imageId);
    $stmt->execute();
    $min = $stmt->get_result()->fetch_assoc()['m'];
    $stmt->close();

    $newOrder = ($min === null) ? 0 : ((int)$min - 1);
    $stmt = $db->prepare("UPDATE handel_product_images SET sort_order = ? WHERE id = ? AND product_id = ?");
    $stmt->bind_param('iii', $newOrder, $imageId, $productId);
    $stmt->execute();
    $stmt->close();

    syncProductCover($productId);
    jsonResponse(['success' => true]);
}

function handleUpdateStock($id) {
    $input = getInput();
    $stock = (int)($input['stock'] ?? 0);
    if ($stock < 0) jsonResponse(['error' => 'Invalid stock value'], 400);
    $db = getDB();
    $stmt = $db->prepare("UPDATE handel_products SET stock = ? WHERE id = ?");
    $stmt->bind_param('ii', $stock, $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleDeleteProduct($id) {
    /* Resolve the gallery before the row goes: the FK cascade drops the
       handel_product_images rows, and with them the only record of which
       files on disk belonged to this product. removeProductAndFiles()
       unlinks those uploads first, then deletes the product. */
    removeProductAndFiles($id);
    jsonResponse(['success' => true]);
}

function handleAdminUsers() {
    $db = getDB();
    $r = $db->query("SELECT id, name, email, role, created_at FROM handel_users ORDER BY created_at DESC");
    $users = [];
    while ($row = $r->fetch_assoc()) $users[] = $row;
    jsonResponse($users);
}

function handleAdminMessages() {
    $db = getDB();

    // Counts for the filter tabs — always from the full table, not the page.
    $counts = ['all' => 0, 'unread' => 0, 'read' => 0];
    $r = $db->query("SELECT COUNT(*) AS total, SUM(is_read = 0) AS unread, SUM(is_read = 1) AS `read` FROM handel_messages");
    if ($row = $r->fetch_assoc()) {
        $counts['all'] = (int)$row['total'];
        $counts['unread'] = (int)($row['unread'] ?? 0);
        $counts['read'] = (int)($row['read'] ?? 0);
    }

    $filter = $_GET['filter'] ?? 'all';
    if (!in_array($filter, ['all', 'unread', 'read'], true)) $filter = 'all';

    $where = [];
    $params = [];
    $types = '';
    if ($filter === 'unread') $where[] = 'is_read = 0';
    elseif ($filter === 'read') $where[] = 'is_read = 1';

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        // Escape LIKE wildcards so a customer typing % or _ searches literally.
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
        $params = [$like, $like, $like, $like];
        $types = 'ssss';
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    // Total matching rows, for pagination.
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM handel_messages$whereSql");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $perPage = 20;
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)($_GET['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET are interpolated as cast ints — never user strings.
    $stmt = $db->prepare("SELECT id, name, email, subject, message, is_read, created_at FROM handel_messages$whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_read'] = (int)$row['is_read'];
        $messages[] = $row;
    }
    $stmt->close();

    jsonResponse([
        'messages' => $messages,
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'counts' => $counts,
    ]);
}

function handleAdminCategories() {
    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDB();
    if ($method === 'GET') {
        $r = $db->query("SELECT c.*, (SELECT COUNT(*) FROM handel_products WHERE category_id = c.id) as product_count FROM handel_categories c ORDER BY c.name");
        $cats = [];
        while ($row = $r->fetch_assoc()) $cats[] = $row;
        jsonResponse($cats);
    } elseif ($method === 'POST') {
        $input = getInput();
        $name = trim($input['name'] ?? '');
        $desc = trim($input['description'] ?? '');
        if (!$name) jsonResponse(['error' => 'Name is required'], 400);
        $stmt = $db->prepare("INSERT INTO handel_categories (name, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $name, $desc);
        $stmt->execute();
        $stmt->close();
        jsonResponse(['success' => true, 'id' => $db->insert_id]);
    }
}

function handleUpdateCategory($id) {
    $input = getInput();
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $db = getDB();
    $stmt = $db->prepare("UPDATE handel_categories SET name=?, description=? WHERE id=?");
    $stmt->bind_param('ssi', $name, $desc, $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleDeleteCategory($id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE handel_products SET category_id = NULL WHERE category_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare("DELETE FROM handel_categories WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleAdminProfile() {
    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDB();
    if ($method === 'GET') {
        $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM handel_users WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user']['id']);
        $stmt->execute();
        $r = $stmt->get_result();
        $stmt->close();
        if ($r->num_rows === 0) jsonResponse(['error' => 'User not found'], 404);
        jsonResponse($r->fetch_assoc());
    } elseif ($method === 'PUT') {
        $input = getInput();
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $currentPw = $input['current_password'] ?? '';
        $newPw = $input['new_password'] ?? '';

        if (!$name) jsonResponse(['error' => 'Name is required'], 400);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'A valid email is required'], 400);
        // Reject an email already used by another account BEFORE updating,
        // otherwise the UPDATE fails on the unique index but the session
        // below would still switch to the new (never-saved) email.
        $stmt = $db->prepare("SELECT id FROM handel_users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $email, $_SESSION['user']['id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            jsonResponse(['error' => 'That email is already in use by another account'], 400);
        }
        $stmt->close();

        $stmt = $db->prepare("SELECT * FROM handel_users WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user']['id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($newPw) {
            if (strlen($newPw) < 8) jsonResponse(['error' => 'New password must be at least 8 characters'], 400);
            if (!$currentPw) jsonResponse(['error' => 'Current password required to set new password'], 400);
            if (!password_verify($currentPw, $user['password'])) jsonResponse(['error' => 'Current password is incorrect'], 400);
            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE handel_users SET name=?, email=?, password=? WHERE id=?");
            $stmt->bind_param('sssi', $name, $email, $hash, $_SESSION['user']['id']);
        } else {
            $stmt = $db->prepare("UPDATE handel_users SET name=?, email=? WHERE id=?");
            $stmt->bind_param('ssi', $name, $email, $_SESSION['user']['id']);
        }
        $stmt->execute();
        $stmt->close();
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        jsonResponse(['success' => true]);
    }
}

function handleNotifications() {
    $db = getDB();
    $r = $db->query("SELECT COUNT(*) as count FROM handel_messages WHERE is_read = 0");
    $unread = $r->fetch_assoc()['count'];
    $r = $db->query("SELECT COUNT(*) as count FROM handel_orders WHERE payment_status = 'pending'");
    $pendingPay = $r->fetch_assoc()['count'];
    $r = $db->query("SELECT COUNT(*) as count FROM handel_orders WHERE status = 'pending'");
    $pendingOrders = $r->fetch_assoc()['count'];
    jsonResponse([
        'unread_messages' => (int)$unread,
        'pending_payments' => (int)$pendingPay,
        'pending_orders' => (int)$pendingOrders
    ]);
}

function handleNewsletter() {
    $input = getInput();
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');
    if (!$subject || !$message) jsonResponse(['error' => 'Subject and message are required'], 400);

    // Marketing sends only to people who explicitly opted in through the
    // newsletter form and have not unsubscribed. Registered customers are
    // not added implicitly: registration consent covers account/orders, not
    // promotional email, and the privacy policy says so.
    $recipients = newsletterRecipientRows();

    $sent = 0;
    $failed = 0;

    foreach ($recipients as $row) {
        $email = strtolower(trim((string)$row['email']));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        $token = (string)($row['unsubscribe_token'] ?? '');
        $unsubUrl = base_url('/unsubscribe?token=' . rawurlencode($token));
        $html = '<div style="font-family:Arial;max-width:600px;margin:auto;color:#1e293b;">
            <div style="background:#1e293b;color:#fff;padding:20px;text-align:center;border-radius:8px 8px 0 0;"><h2 style="margin:0;">Handel Luxe GH</h2></div>
            <div style="padding:20px;border:1px solid #e2e8f0;">' . nl2br(htmlspecialchars($message)) . '</div>
            <div style="text-align:center;padding:16px;color:#64748b;font-size:0.85rem;">
                <a href="' . base_url('/products') . '" style="color:#2563eb;font-weight:600;text-decoration:none;">Shop Fragrances</a><br>
                You received this because you subscribed to Handel Luxe GH marketing emails.<br>
                <a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#64748b;">Unsubscribe</a><br>
                Handel Luxe GH &copy; ' . date('Y') . '</div></div>';
        if (sendMail($email, $subject, $html)) $sent++;
        else $failed++;
    }

    jsonResponse(['success' => true, 'sent' => $sent, 'failed' => $failed]);
}

function handleDeliveryZones() {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        jsonResponse(getDeliveryZones());
    }
}

function handleAddDeliveryZone() {
    $input = getInput();
    $id = trim($input['id'] ?? '');
    $label = trim($input['label'] ?? '');
    $fee = (float)($input['fee'] ?? 0);
    $eta = trim($input['eta'] ?? '');
    $category = ($input['category'] ?? 'delivery') === 'pickup' ? 'pickup' : 'delivery';

    if (!$id || !$label) jsonResponse(['error' => 'Zone ID and label are required'], 400);
    if (!preg_match('/^[a-z0-9\-_]+$/i', $id)) jsonResponse(['error' => 'Zone ID may only contain letters, numbers, dashes and underscores'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM handel_delivery_zones WHERE id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        jsonResponse(['error' => 'A zone with this ID already exists'], 400);
    }
    $stmt->close();

    $stmt = $db->prepare("INSERT INTO handel_delivery_zones (id, label, fee, eta, category) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssdss', $id, $label, $fee, $eta, $category);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true, 'id' => $id]);
}

function handleUpdateDeliveryZone($id) {
    $input = getInput();
    $label = trim($input['label'] ?? '');
    $fee = (float)($input['fee'] ?? 0);
    $eta = trim($input['eta'] ?? '');
    $category = ($input['category'] ?? '') === 'pickup' ? 'pickup' : 'delivery';
    $db = getDB();
    // A zone id that matches nothing would otherwise report success
    // while updating zero rows — surface it as a real 404 instead.
    $stmt = $db->prepare("SELECT id FROM handel_delivery_zones WHERE id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) jsonResponse(['error' => 'Zone not found'], 404);
    $stmt->close();
    $stmt = $db->prepare("UPDATE handel_delivery_zones SET label=?, fee=?, eta=?, category=? WHERE id=?");
    $stmt->bind_param('sdsss', $label, $fee, $eta, $category, $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleDeleteDeliveryZone($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM handel_delivery_zones WHERE id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) jsonResponse(['error' => 'Zone not found'], 404);
    $stmt->close();

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM handel_orders WHERE delivery_zone = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r['count'] > 0) jsonResponse(['error' => "This zone is used by {$r['count']} order(s) and cannot be deleted."], 400);

    $stmt = $db->prepare("DELETE FROM handel_delivery_zones WHERE id = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleMarkRead($id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE handel_messages SET is_read = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleMarkUnread($id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE handel_messages SET is_read = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleDeleteMessage($id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM handel_messages WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleUpdateUserRole($id) {
    $input = getInput();
    $role = $input['role'] ?? '';
    if (!in_array($role, ['customer', 'admin'])) jsonResponse(['error' => 'Invalid role'], 400);
    $db = getDB();

    // Demoting yourself when you are the only admin would lock everyone
    // out of the admin panel.
    if ($role !== 'admin' && (int)$id === (int)$_SESSION['user']['id']) {
        $cr = $db->query("SELECT COUNT(*) as count FROM handel_users WHERE role = 'admin'");
        if ($cr->fetch_assoc()['count'] <= 1) {
            jsonResponse(['error' => 'You are the only admin — promote another admin first'], 400);
        }
    }

    $stmt = $db->prepare("UPDATE handel_users SET role = ? WHERE id = ?");
    $stmt->bind_param('si', $role, $id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

function handleUserOrders($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email FROM handel_users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) jsonResponse(['error' => 'User not found'], 404);

    $stmt = $db->prepare("SELECT id, total, shipping_fee, delivery_zone, status, payment_status, payment_method, created_at FROM handel_orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $r = $stmt->get_result();
    $stmt->close();
    $orders = [];
    $totalSpent = 0;
    while ($row = $r->fetch_assoc()) {
        $orders[] = $row;
        $totalSpent += (float)$row['total'];
    }
    jsonResponse(['user' => $user, 'orders' => $orders, 'total_orders' => count($orders), 'total_spent' => $totalSpent]);
}

function handleDeleteUser($id) {
    $id = (int)$id;
    if ($id === $_SESSION['user']['id']) jsonResponse(['error' => 'Cannot delete your own account'], 400);
    $db = getDB();
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT role FROM handel_users WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result();
        $stmt->close();
        if ($r->num_rows === 0) {
            $db->rollback();
            jsonResponse(['error' => 'User not found'], 404);
        }
        $target = $r->fetch_assoc();

        if ($target['role'] === 'admin') {
            $cr = $db->query("SELECT COUNT(*) as count FROM handel_users WHERE role = 'admin'");
            if ($cr->fetch_assoc()['count'] <= 1) {
                $db->rollback();
                jsonResponse(['error' => 'Cannot delete the last admin account'], 400);
            }
        }

        $stmt = $db->prepare("SELECT COUNT(*) as count FROM handel_orders
            WHERE user_id = ? AND status <> 'cancelled'
            AND (status <> 'delivered' OR payment_status <> 'success')");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $incompleteOrders = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        if ($incompleteOrders > 0) {
            $db->rollback();
            jsonResponse([
                'error' => "Cannot delete this account while it has {$incompleteOrders} order(s) that are not completed and paid. Complete or cancel those orders first."
            ], 400);
        }

        $stmt = $db->prepare("DELETE FROM handel_users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $db->commit();
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['error' => 'Failed to delete user'], 500);
    }
}

function handleExport($type) {
    $db = getDB();
    $headers = [];
    $filename = '';

    if ($type === 'orders') {
        $r = $db->query("SELECT o.id, COALESCE(u.name, o.guest_name) as customer, COALESCE(u.email, o.guest_email) as email, o.total, o.shipping_fee, o.delivery_zone, o.phone, o.payment_method, o.payment_status, o.status, o.created_at
            FROM handel_orders o LEFT JOIN handel_users u ON o.user_id = u.id ORDER BY o.created_at DESC");
        $headers = ['ID', 'Customer', 'Email', 'Total', 'Shipping Fee', 'Delivery Zone', 'Phone', 'Payment Method', 'Payment Status', 'Status', 'Date'];
        $filename = 'orders';
    } elseif ($type === 'products') {
        $r = $db->query("SELECT p.id, p.name, p.price, p.stock, p.featured, c.name as category, p.created_at FROM handel_products p LEFT JOIN handel_categories c ON p.category_id = c.id ORDER BY p.id");
        $headers = ['ID', 'Name', 'Price', 'Stock', 'Featured', 'Category', 'Created'];
        $filename = 'products';
    } elseif ($type === 'messages') {
        $r = $db->query("SELECT id, name, email, subject, message, is_read, created_at FROM handel_messages ORDER BY created_at DESC");
        $headers = ['ID', 'Name', 'Email', 'Subject', 'Message', 'Read', 'Created'];
        $filename = 'messages';
    } else {
        jsonResponse(['error' => 'Invalid export type'], 400);
    }

    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;

    if (($_GET['format'] ?? '') === 'print') {
        renderExportPrint($type, $headers, $rows);
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '-' . time() . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    /* CSV formula injection: a cell starting with =, +, - or @ runs as a
       formula in Excel/Sheets. Prefix those cells with an apostrophe. */
    $csvSafe = function ($v) {
        $v = (string)$v;
        if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) $v = "'" . $v;
        return $v;
    };
    foreach ($rows as $row) {
        fputcsv($output, array_map($csvSafe, array_values($row)));
    }
    fclose($output);
    exit;
}

function renderExportPrint($type, $headers, $rows) {
    /* api/index.php sends application/json for every API route. This
       endpoint renders a print-ready HTML document opened in a new tab,
       so replace that header before any output is flushed. */
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');

    $titles = [
        'orders' => 'Orders report',
        'products' => 'Products report',
        'messages' => 'Messages report'
    ];
    $keysByType = [
        'orders' => ['id', 'customer', 'email', 'total', 'shipping_fee', 'delivery_zone', 'phone', 'payment_method', 'payment_status', 'status', 'created_at'],
        'products' => ['id', 'name', 'price', 'stock', 'featured', 'category', 'created_at'],
        'messages' => ['id', 'name', 'email', 'subject', 'message', 'is_read', 'created_at']
    ];
    $title = $titles[$type] ?? 'Export report';
    $keys = $keysByType[$type] ?? [];
    $zoneLabels = [];
    if ($type === 'orders') {
        foreach (getDeliveryZones() as $zone) $zoneLabels[$zone['id']] = $zone['label'];
    }
    $autoprint = (($_GET['autoprint'] ?? '1') !== '0');
    $e = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $count = count($rows);
    $recordLabel = $count . ' record' . ($count === 1 ? '' : 's');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<meta name="robots" content="noindex, nofollow">' .
        '<title>' . $e($title) . ' - Handel Luxe GH</title>' .
        '<style>' .
        '*{box-sizing:border-box}body{margin:0;padding:24px;background:#fff;color:#0f172a;font:12px/1.45 Arial,Helvetica,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}' .
        '.report-head{display:flex;justify-content:space-between;gap:24px;padding-bottom:14px;border-bottom:2px solid #2563eb;margin-bottom:18px}' .
        '.brand{margin:0;color:#2563eb;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}' .
        'h1{margin:5px 0 0;font-size:24px;letter-spacing:-.02em}.report-meta{text-align:right;color:#475569;font-size:11px;line-height:1.7;white-space:nowrap}' .
        'table{width:100%;border-collapse:collapse}thead{display:table-header-group}th{background:#eff6ff;color:#1e3a8a;border:1px solid #dbeafe;padding:8px;font-size:10px;text-align:left;text-transform:uppercase;letter-spacing:.04em}' .
        'td{border:1px solid #e2e8f0;padding:8px;vertical-align:top}tbody tr:nth-child(even){background:#f8fafc}tbody tr{break-inside:avoid;page-break-inside:avoid}' .
        'td.cell-wrap{min-width:170px;overflow-wrap:anywhere}td.cell-message{min-width:240px;white-space:normal;overflow-wrap:anywhere}' .
        '.report-foot{display:flex;justify-content:space-between;gap:20px;margin-top:16px;color:#64748b;font-size:10px}' .
        '.no-print{display:flex;justify-content:flex-end;gap:8px;margin-bottom:16px}.no-print a,.no-print button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 14px;border:1px solid #2563eb;border-radius:6px;background:#2563eb;color:#fff;font:600 12px Arial,Helvetica,sans-serif;text-decoration:none;cursor:pointer}.no-print a{background:#fff;color:#2563eb}' .
        '@media print{@page{size:A4 landscape;margin:10mm}body{padding:0}.no-print{display:none}}' .
        '</style></head><body>' .
        '<div class="no-print"><a href="' . $e(base_url('/admin')) . '">Back to admin</a><button type="button" onclick="window.print()">Print / Save as PDF</button></div>' .
        '<header class="report-head"><div><p class="brand">Handel Luxe GH</p><h1>' . $e($title) . '</h1></div>' .
        '<div class="report-meta">Generated ' . $e(date('d M Y, H:i')) . '<br>' . $e($recordLabel) . '</div></header>' .
        '<table><thead><tr>';
    foreach ($headers as $header) echo '<th scope="col">' . $e($header) . '</th>';
    echo '</tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;color:#64748b;padding:24px">No records found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($keys as $key) {
                $value = $row[$key] ?? '';
                $class = '';
                if ($key === 'total' || $key === 'shipping_fee' || $key === 'price') {
                    $value = 'GHS ' . number_format((float)$value, 2);
                } elseif ($key === 'payment_method') {
                    $value = $value === 'delivery' ? 'Pay on Delivery' : ($value === 'paystack' ? 'Paystack' : ucfirst((string)$value));
                } elseif ($key === 'payment_status' || $key === 'status') {
                    $value = ucfirst(str_replace('_', ' ', (string)$value));
                } elseif ($key === 'delivery_zone') {
                    $zoneId = (string)$value;
                    $value = $zoneLabels[$zoneId] ?? ucwords(str_replace(['_', '-'], ' ', $zoneId));
                } elseif ($key === 'featured' || $key === 'is_read') {
                    $value = $value ? 'Yes' : 'No';
                } elseif ($key === 'created_at') {
                    $time = $value ? strtotime((string)$value) : false;
                    $value = $time ? date('d M Y, H:i', $time) : (string)$value;
                } elseif ($key === 'id') {
                    $value = '#' . $value;
                } elseif ($key === 'message') {
                    $class = ' class="cell-message"';
                } elseif ($key === 'subject') {
                    $class = ' class="cell-wrap"';
                }
                if ($value === '' || $value === null) $value = '—';
                echo '<td' . $class . '>' . ($key === 'message' ? nl2br($e($value)) : $e($value)) . '</td>';
            }
            echo '</tr>';
        }
    }
    echo '</tbody></table><footer class="report-foot"><span>Handel Luxe GH · 0542332244 · 0549986486 · drdehandel@gmail.com</span><span>' . $e($recordLabel) . '</span></footer>';
    echo '<script>window.addEventListener("load", function () {';
    if ($autoprint) echo 'window.setTimeout(function () { window.print(); }, 350);';
    echo '});</script></body></html>';
}


function handleInvoice($id) {
    /* api/index.php sets Content-Type: application/json for every API route.
       This endpoint renders a full HTML document (opened in a new tab from
       the admin panel), and browsers do not render HTML served as
       application/json — they show the markup as text. Override it before
       any output; the header is not flushed until the first echo below. */
    header('Content-Type: text/html; charset=utf-8');

    $db = getDB();
    $stmt = $db->prepare("SELECT o.*, COALESCE(u.name, o.guest_name) as customer_name, COALESCE(u.email, o.guest_email) as customer_email
        FROM handel_orders o LEFT JOIN handel_users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result();
    $stmt->close();
    if ($r->num_rows === 0) { echo 'Order not found'; exit; }
    $o = $r->fetch_assoc();

    $stmt = $db->prepare("SELECT oi.*, COALESCE(p.name, oi.product_name, 'Deleted product') AS name
        FROM handel_order_items oi LEFT JOIN handel_products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $itemsR = $stmt->get_result();
    $stmt->close();
    $items = [];
    while ($row = $itemsR->fetch_assoc()) $items[] = $row;

    $formatInvoice = fn($v) => number_format((float)$v, 2);
    $subtotal = array_reduce($items, fn($s, $i) => $s + (float)$i['price'] * $i['quantity'], 0);

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Invoice #' . $o['id'] . ' - Handel Luxe GH</title>
    <style>body{font-family:Arial;margin:40px;color:#1e293b;}h1{color:#2563eb;}table{width:100%;border-collapse:collapse;margin:20px 0;}th,td{border:1px solid #e2e8f0;padding:10px;text-align:left;}th{background:#f8fafc;}hr{border:none;border-top:2px solid #2563eb;margin:20px 0;}.row{display:flex;justify-content:space-between;}@media print{.no-print{display:none;}}button{padding:10px 20px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:1rem;}</style></head>
    <body><div class="no-print" style="text-align:right;"><button onclick="window.print()">Print / Save as PDF</button> <a href="' . base_url('/admin') . '"><button style="background:#64748b;">Back</button></a></div>
    <h1>Handel Luxe GH</h1><p>Invoice</p><hr>
    <div class="row"><div><strong>Order #' . $o['id'] . '</strong><br>Date: ' . date('Y-m-d H:i', strtotime($o['created_at'])) . '<br>Payment: ' . ($o['payment_method'] === 'delivery' ? 'Pay on Delivery' : 'Paystack') . ' (' . $o['payment_status'] . ')</div><div><strong>Billed To</strong><br>' . htmlspecialchars($o['customer_name']) . '<br>' . htmlspecialchars($o['customer_email'] ?? '') . '<br>Phone: ' . htmlspecialchars($o['phone'] ?? 'N/A') . '</div></div><hr>
    <h3>Items</h3><table><thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr></thead><tbody>';
    foreach ($items as $i) {
        echo '<tr><td>' . htmlspecialchars($i['name']) . '</td><td>' . $i['quantity'] . '</td><td>GHS ' . $formatInvoice($i['price']) . '</td><td>GHS ' . $formatInvoice($i['price'] * $i['quantity']) . '</td></tr>';
    }
    echo '</tbody></table>
    <p><strong>Subtotal:</strong> GHS ' . $formatInvoice($subtotal) . '</p>
    <p><strong>Delivery(' . htmlspecialchars($o['delivery_zone'] ?? 'N/A') . '):</strong> GHS ' . $formatInvoice($o['shipping_fee'] ?? 0) . '</p>
    <p style="font-size:1.3rem;"><strong>Total Due:</strong> GHS ' . $formatInvoice($o['total']) . '</p>
    <hr><p style="text-align:center;color:#64748b;">Handel Luxe GH - Your premier destination for authentic perfumes in Ghana.<br>0542332244 | 0549986486 | drdehandel@gmail.com</p></body></html>';
    exit;
}
