<?php
require_once __DIR__ . '/db.php';

/* Shared cart fetch used by both order paths (Pay on Delivery in
   api/orders.php and Paystack in api/payments.php). Previously defined
   in api/orders.php only, so Paystack checkout fatal-errored. */
function getCartItemsForOrder($db, $isGuest) {
    if ($isGuest) {
        $token = $_SESSION['guest_token'] ?? '';
        /* FOR UPDATE: checkout already runs in a transaction. Without the
           lock two simultaneous submits can both read the same cart rows
           before either deletes them, creating duplicate orders and
           reserving the same stock twice. The locking read makes the second
           request wait, then see the cart has been consumed. */
        $stmt = $db->prepare("SELECT c.*, p.price, p.stock, p.name FROM handel_cart c JOIN handel_products p ON c.product_id = p.id WHERE c.guest_token = ? FOR UPDATE");
        $stmt->bind_param('s', $token);
    } else {
        $stmt = $db->prepare("SELECT c.*, p.price, p.stock, p.name FROM handel_cart c JOIN handel_products p ON c.product_id = p.id WHERE c.user_id = ? FOR UPDATE");
        $stmt->bind_param('i', $_SESSION['user']['id']);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    return $items;
}
