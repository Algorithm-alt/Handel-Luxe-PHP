<?php
$sub = str_replace('cart/', '', $_GET['route'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

if ($sub === '' || $sub === 'cart') {
    if ($method === 'GET') handleCartGet();
} elseif ($sub === 'add' && $method === 'POST') {
    handleCartAdd();
} elseif (preg_match('#^update/(\d+)$#', $sub, $m) && $method === 'PUT') {
    handleCartUpdate($m[1]);
} elseif (preg_match('#^remove/(\d+)$#', $sub, $m) && $method === 'DELETE') {
    handleCartRemove($m[1]);
} elseif ($sub === 'clear' && $method === 'DELETE') {
    handleCartClear();
} else {
    jsonResponse(['error' => 'Not found'], 404);
}

function handleCartGet() {
    $db = getDB();
    $key = getCartKey();
    $column = $key['column'];
    $value = $key['value'];

    $stmt = $db->prepare("SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image
        FROM handel_cart c JOIN handel_products p ON c.product_id = p.id WHERE c.$column = ?");
    $stmt->bind_param($column === 'user_id' ? 'i' : 's', $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $items = [];
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $row['image'] = image_url($row['image']);
        $items[] = $row;
        $total += $row['price'] * $row['quantity'];
    }

    jsonResponse(['items' => $items, 'total' => (float)$total, 'count' => count($items)]);
}

function handleCartAdd() {
    $input = getInput();
    $productId = (int)($input['product_id'] ?? 0);
    $qty = max(1, min(100, (int)($input['quantity'] ?? 1)));

    if (!$productId) jsonResponse(['error' => 'Invalid product'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT id, stock FROM handel_products WHERE id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) jsonResponse(['error' => 'Product not found'], 404);

    $key = getCartKey();
    $column = $key['column'];
    $value = $key['value'];

    $check = $db->prepare("SELECT * FROM handel_cart WHERE $column = ? AND product_id = ?");
    $check->bind_param($column === 'user_id' ? 'ii' : 'si', $value, $productId);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        $newQty = $existing['quantity'] + $qty;
        if ($product['stock'] < $newQty) jsonResponse(['error' => 'Insufficient stock'], 400);
        $stmt = $db->prepare("UPDATE handel_cart SET quantity = ? WHERE id = ?");
        $stmt->bind_param('ii', $newQty, $existing['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        if ($product['stock'] < $qty) jsonResponse(['error' => 'Insufficient stock'], 400);
        $stmt = $db->prepare("INSERT INTO handel_cart ($column, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param($column === 'user_id' ? 'iii' : 'sii', $value, $productId, $qty);
        $stmt->execute();
        $stmt->close();
    }

    jsonResponse(['success' => true]);
}

function handleCartUpdate($id) {
    $input = getInput();
    $qty = (int)($input['quantity'] ?? 0);

    // Negative quantities would pass the stock check below and corrupt the
    // cart total; 0 removes the item (existing behavior).
    if ($qty < 0) jsonResponse(['error' => 'Invalid quantity'], 400);

    $db = getDB();
    $key = getCartKey();
    $column = $key['column'];
    $value = $key['value'];

    if ($qty === 0) {
        $stmt = $db->prepare("DELETE FROM handel_cart WHERE id = ? AND $column = ?");
        $stmt->bind_param($column === 'user_id' ? 'ii' : 'is', $id, $value);
        $stmt->execute();
        $stmt->close();
    } else {
        $check = $db->prepare("SELECT c.id, p.stock FROM handel_cart c JOIN handel_products p ON c.product_id = p.id WHERE c.id = ? AND c.$column = ?");
        $check->bind_param($column === 'user_id' ? 'ii' : 'is', $id, $value);
        $check->execute();
        $item = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$item) jsonResponse(['error' => 'Cart item not found'], 404);
        if ($item['stock'] < $qty) jsonResponse(['error' => 'Insufficient stock'], 400);

        $stmt = $db->prepare("UPDATE handel_cart SET quantity = ? WHERE id = ?");
        $stmt->bind_param('ii', $qty, $id);
        $stmt->execute();
        $stmt->close();
    }

    jsonResponse(['success' => true]);
}

function handleCartRemove($id) {
    $db = getDB();
    $key = getCartKey();
    $column = $key['column'];
    $value = $key['value'];

    $stmt = $db->prepare("DELETE FROM handel_cart WHERE id = ? AND $column = ?");
    $stmt->bind_param($column === 'user_id' ? 'ii' : 'is', $id, $value);
    $stmt->execute();
    $stmt->close();

    jsonResponse(['success' => true]);
}

function handleCartClear() {
    $db = getDB();
    $key = getCartKey();
    $column = $key['column'];
    $value = $key['value'];

    $stmt = $db->prepare("DELETE FROM handel_cart WHERE $column = ?");
    $stmt->bind_param($column === 'user_id' ? 'i' : 's', $value);
    $stmt->execute();
    $stmt->close();

    jsonResponse(['success' => true]);
}
