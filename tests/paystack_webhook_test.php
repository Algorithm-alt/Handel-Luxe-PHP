<?php
/**
 * TASK 5 — Paystack webhook processing, offline.
 *
 * Signs real webhook bodies with the configured secret (loaded from
 * secrets.php, never printed) and posts them to the local server. Only
 * failure/abandoned and amount-mismatch events are used, because a valid
 * charge.success would trigger an admin notification email and this audit
 * must not send customer or admin mail.
 */
echo "[paystack] webhook signature, amount matching and duplicate protection\n";

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/orders.php';
require_once __DIR__ . '/../config/paystack.php';

if (PAYSTACK_SECRET_KEY === '') {
    t_skip('PAYSTACK_SECRET_KEY is not configured');
    return;
}

try {
    $db = getDB();
} catch (Throwable $e) {
    t_skip('database unavailable: ' . $e->getMessage());
    return;
}

function webhook_post($url, $body, $secret) {
    $sig = hash_hmac('sha512', $body, $secret);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-paystack-signature: ' . $sig],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, (string)$resp, $err];
}

function webhook_probe($url, $secret) {
    $body = '{"event":"ping"}';
    [$code] = webhook_post($url, $body, $secret);
    return $code > 0;
}

$webhookUrl = rtrim(BASE_URL, '/') . '/api/webhook/paystack';
if (!webhook_probe($webhookUrl, PAYSTACK_SECRET_KEY)) {
    t_skip('local web server not reachable');
    return;
}

$created = [];
$refs = [];

function qa_create_order($db, &$created, &$refs, $qty, $total) {
    $stockBeforeReserve = 5;
    $stmt = $db->prepare("INSERT INTO handel_products (name, description, price, category_id, stock, image, featured) VALUES (?, '', 50.00, NULL, ?, '/images/placeholder.png', 0)");
    $pname = 'QA Webhook Product';
    $stmt->bind_param('si', $pname, $stockBeforeReserve);
    $stmt->execute();
    $productId = (int)$db->insert_id;
    $stmt->close();
    $created['products'][] = $productId;

    $ref = 'QA_WH_' . bin2hex(random_bytes(6));
    $refs[] = $ref;
    $guestName = 'QA Webhook';
    $guestEmail = 'qa-webhook@example.test';
    $address = '1 QA Street';
    $phone = '0200000000';
    $zone = 'swedru';
    $stmt = $db->prepare("INSERT INTO handel_orders
        (user_id, guest_name, guest_email, total, shipping_fee, delivery_zone, shipping_address, phone, status, payment_method, payment_ref, payment_status)
        VALUES (NULL, ?, ?, ?, 0, ?, ?, ?, 'pending', 'paystack', ?, 'pending')");
    $stmt->bind_param('ssdssss', $guestName, $guestEmail, $total, $zone, $address, $phone, $ref);
    $stmt->execute();
    $orderId = (int)$db->insert_id;
    $stmt->close();
    $created['orders'][] = $orderId;

    $stmt = $db->prepare("INSERT INTO handel_order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, 'QA Webhook Product', ?, 50.00)");
    $stmt->bind_param('iii', $orderId, $productId, $qty);
    $stmt->execute();
    $stmt->close();

    // Mimic the reservation the checkout performs when the order is created.
    $stmt = $db->prepare("UPDATE handel_products SET stock = stock - ? WHERE id = ?");
    $stmt->bind_param('ii', $qty, $productId);
    $stmt->execute();
    $stmt->close();

    return [$orderId, $productId, $ref, $qty];
}

function qa_webhook_event($ref, $event, $amount, $currency = 'GHS') {
    return json_encode([
        'event' => $event,
        'data' => ['reference' => $ref, 'amount' => $amount, 'currency' => $currency, 'status' => $event === 'charge.success' ? 'success' : 'failed'],
    ]);
}

function qa_order_status($db, $orderId) {
    $stmt = $db->prepare("SELECT payment_status FROM handel_orders WHERE id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['payment_status'] ?? null;
}

function qa_product_stock($db, $productId) {
    $stmt = $db->prepare("SELECT stock FROM handel_products WHERE id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['stock'] ?? -1);
}

try {
    // 1. charge.failed restores stock exactly once, even on duplicate delivery.
    [$orderId, $productId, $ref, ] = qa_create_order($db, $created, $refs, 2, 100.00);
    [$code1, $resp1] = webhook_post($webhookUrl, qa_webhook_event($ref, 'charge.failed', 10000), PAYSTACK_SECRET_KEY);
    t_eq($code1, 200, 'charge.failed webhook is accepted');
    t_eq(qa_order_status($db, $orderId), 'failed', 'charge.failed marks the order failed');
    t_eq(qa_product_stock($db, $productId), 5, 'charge.failed restores the reserved stock');

    [$code2] = webhook_post($webhookUrl, qa_webhook_event($ref, 'charge.failed', 10000), PAYSTACK_SECRET_KEY);
    t_eq($code2, 200, 'duplicate charge.failed webhook is accepted');
    t_eq(qa_product_stock($db, $productId), 5, 'duplicate failure does not restore stock twice');

    // 2. A success event whose amount does not match the order is not trusted.
    [$orderId2, $productId2, $ref2, ] = qa_create_order($db, $created, $refs, 2, 100.00);
    [$code3, $resp3] = webhook_post($webhookUrl, qa_webhook_event($ref2, 'charge.success', 1), PAYSTACK_SECRET_KEY);
    t_eq($code3, 200, 'amount-mismatch success webhook is acknowledged');
    t_eq(qa_order_status($db, $orderId2), 'pending', 'amount mismatch leaves the order pending');
    t_eq(qa_product_stock($db, $productId2), 3, 'amount mismatch does not reserve or release stock');

    $audit = $db->query("SELECT COUNT(*) AS n FROM handel_audit_logs WHERE action = 'payment.amount_mismatch' AND details LIKE '%" . $db->real_escape_string($ref2) . "%'");
    t_assert($audit && (int)$audit->fetch_assoc()['n'] >= 1, 'amount mismatch is recorded in the audit log');
} finally {
    foreach ($created['orders'] ?? [] as $id) {
        $db->query("DELETE FROM handel_order_items WHERE order_id = " . (int)$id);
        $db->query("DELETE FROM handel_orders WHERE id = " . (int)$id);
    }
    foreach ($created['products'] ?? [] as $id) {
        $db->query("DELETE FROM handel_products WHERE id = " . (int)$id);
    }
    foreach ($refs as $ref) {
        $db->query("DELETE FROM handel_audit_logs WHERE action = 'payment.amount_mismatch' AND details LIKE '%" . $db->real_escape_string($ref) . "%'");
    }
}
