<?php
/**
 * Order item schema upkeep.
 *
 * handel_order_items.product_id used to be NOT NULL with ON DELETE CASCADE,
 * so deleting a product silently deleted its line items out of every past
 * order — order history lost rows and the stored totals stopped reconciling
 * against them.
 *
 * Two changes fix that:
 *
 *   1. product_name snapshots the name at order time. price was always
 *      snapshotted, so this completes the record: an order stays readable
 *      after the product behind it is gone.
 *   2. The foreign key becomes ON DELETE SET NULL, so removing a product
 *      leaves its line items in place instead of taking them with it.
 *
 * There is no migration runner in this project, so the upkeep follows the
 * same self-healing pattern as ensureZones() and ensureImagesTable(): a cheap
 * check on the request path, with the ALTERs run only when something is
 * genuinely out of date.
 */
require_once __DIR__ . '/db.php';

function ensureOrderItemsSchema() {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db = getDB();

        /* One round trip answers "is anything out of date?", so the steady
           state costs a single information_schema query per request. */
        $res = $db->query("SELECT
            (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'handel_order_items'
                 AND COLUMN_NAME = 'product_name') AS has_name,
            (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'handel_order_items'
                 AND COLUMN_NAME = 'product_id' AND IS_NULLABLE = 'YES') AS nullable,
            (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'handel_order_items'
                 AND REFERENCED_TABLE_NAME = 'handel_products') AS fk_count,
            (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'handel_order_items'
                 AND REFERENCED_TABLE_NAME = 'handel_products'
                 AND DELETE_RULE = 'SET NULL') AS fk_setnull");
        $s = $res ? $res->fetch_assoc() : null;
        if (!$s) return;
        if ((int)$s['has_name'] === 1 && (int)$s['nullable'] === 1
            && (int)$s['fk_count'] === 1 && (int)$s['fk_setnull'] === 1) {
            return; // already migrated
        }

        // 1. Snapshot column, backfilled from the catalogue while the products
        //    it names still exist.
        if ((int)$s['has_name'] === 0) {
            $db->query("ALTER TABLE handel_order_items ADD COLUMN product_name VARCHAR(200) NULL AFTER product_id");
            $db->query("UPDATE handel_order_items oi JOIN handel_products p ON p.id = oi.product_id
                        SET oi.product_name = p.name WHERE oi.product_name IS NULL");
        }

        // 2. product_id has to be nullable before the FK can be SET NULL.
        if ((int)$s['nullable'] === 0) {
            $db->query("ALTER TABLE handel_order_items MODIFY product_id INT NULL");
        }

        // 3. Swap the constraint. Its name is looked up rather than guessed
        //    (MySQL auto-generates handel_order_items_ibfk_N), and a missing
        //    constraint is re-added — so a run that died between the DROP and
        //    the ADD heals on the next request instead of leaving no FK.
        $fk = $db->query("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'handel_order_items'
              AND REFERENCED_TABLE_NAME = 'handel_products'")->fetch_assoc();
        if ($fk && strtoupper($fk['DELETE_RULE']) === 'CASCADE') {
            $db->query("ALTER TABLE handel_order_items DROP FOREIGN KEY `" . $fk['CONSTRAINT_NAME'] . "`");
            $fk = null;
        }
        if (!$fk) {
            $db->query("ALTER TABLE handel_order_items ADD CONSTRAINT fk_order_items_product
                FOREIGN KEY (product_id) REFERENCES handel_products(id) ON DELETE SET NULL");
        }
    } catch (Throwable $e) {
        /* Schema upkeep must never take a request down. If this fails the
           order paths still work — the next request simply retries. */
    }
}

/* ---------------------------------------------------------------- stock
 * Inventory helpers shared by checkout, cancellation and payment events.
 *
 * Every checkout reserves stock when the order is created. A failed or
 * cancelled order must therefore put it back exactly once, and a failed
 * Paystack payment that later succeeds must reserve it again. These helpers
 * only perform the stock movements; the caller owns the transaction so the
 * status update and the stock change commit together.
 */

function orderItemsForStock($db, $orderId) {
    $stmt = $db->prepare("SELECT product_id, quantity FROM handel_order_items WHERE order_id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function restoreOrderStock($db, $orderId) {
    $items = orderItemsForStock($db, $orderId);
    $stmt = $db->prepare("UPDATE handel_products SET stock = stock + ? WHERE id = ?");
    $restored = 0;
    foreach ($items as $item) {
        if (empty($item['product_id'])) continue;
        $quantity = (int)$item['quantity'];
        $productId = (int)$item['product_id'];
        $stmt->bind_param('ii', $quantity, $productId);
        $stmt->execute();
        $restored++;
    }
    $stmt->close();
    return $restored;
}

function reserveOrderStock($db, $orderId) {
    $items = orderItemsForStock($db, $orderId);
    $stmt = $db->prepare("UPDATE handel_products SET stock = stock - ? WHERE id = ? AND stock >= ?");
    foreach ($items as $item) {
        if (empty($item['product_id'])) continue;
        $quantity = (int)$item['quantity'];
        $productId = (int)$item['product_id'];
        $stmt->bind_param('iii', $quantity, $productId, $quantity);
        $stmt->execute();
        if ($stmt->affected_rows < 1) {
            $stmt->close();
            return false;
        }
    }
    $stmt->close();
    return true;
}

/* True when a failed Paystack order has already had its stock returned.
   Cancelled orders are handled by their status instead. */
function orderStockWasReleased(array $order) {
    return ($order['payment_method'] ?? '') === 'paystack'
        && ($order['payment_status'] ?? '') === 'failed'
        && ($order['status'] ?? '') !== 'cancelled';
}
