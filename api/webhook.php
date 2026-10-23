<?php
/**
 * Paystack webhook - ported from server.js (/api/webhook/paystack).
 * Verifies the x-paystack-signature header (HMAC-SHA512 of the raw body),
 * then updates order payment status. This route is exempt from CSRF
 * (see api/index.php) since Paystack posts without a session.
 */

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// The signature is an HMAC keyed on the Paystack secret. With an empty key
// anyone could compute a valid one and forge a charge.success event to mark
// their own order paid, so refuse to process anything until it is set.
requireConfig('PAYSTACK_SECRET_KEY', 'Webhook is not configured on this site.');

$computed = hash_hmac('sha512', $rawBody, PAYSTACK_SECRET_KEY);
if (!$signature || !hash_equals($computed, $signature)) {
    jsonResponse(['error' => 'Invalid signature'], 400);
}

$event = json_decode($rawBody, true);
$reference = $event['data']['reference'] ?? null;
if (!$reference) {
    jsonResponse(['ok' => true]);
}

$db = getDB();
$eventName = $event['event'] ?? '';
$payload = is_array($event['data'] ?? null) ? $event['data'] : [];

if ($eventName === 'charge.success') {
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT * FROM handel_orders WHERE payment_ref = ? FOR UPDATE");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            $db->rollback();
            jsonResponse(['ok' => true]);
        }

        // The webhook is unsigned data from Paystack's side of the network;
        // verify the amount/currency against the order before trusting it.
        $expectedMinor = (int)round(((float)$existing['total']) * 100);
        $paidMinor = (int)($payload['amount'] ?? -1);
        $currency = strtoupper((string)($payload['currency'] ?? ''));
        if ($paidMinor !== $expectedMinor || $currency !== 'GHS') {
            $db->rollback();
            try {
                writeAuditLog('payment.amount_mismatch', 'system', null, 'Paystack webhook', [
                    'reference' => $reference,
                    'order_id' => (int)$existing['id'],
                    'expected_minor' => $expectedMinor,
                    'paid_minor' => $paidMinor,
                    'currency' => $currency,
                ]);
            } catch (Throwable $e) {}
            jsonResponse(['ok' => true]);
        }

        $orderId = (int)$existing['id'];
        $becameSuccess = false;
        if ($existing['payment_status'] !== 'success') {
            // A failed payment released its stock; a later success must
            // reserve it again before the order is marked paid.
            if ($existing['payment_status'] === 'failed' && $existing['status'] !== 'cancelled') {
                if (!reserveOrderStock($db, $orderId)) {
                    $db->rollback();
                    try {
                        writeAuditLog('payment.restock_failed', 'system', null, 'Paystack webhook', [
                            'reference' => $reference,
                            'order_id' => $orderId,
                        ]);
                    } catch (Throwable $e) {}
                    jsonResponse(['error' => 'Stock could not be reserved'], 500);
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
        jsonResponse(['error' => 'Could not process payment'], 500);
    }

    if ($becameSuccess) {
        // The webhook has no guest session, but a logged-in order can be
        // cleared here. Guest carts are cleared by /api/payments/verify.
        if (!empty($existing['user_id'])) {
            $stmt = $db->prepare("DELETE FROM handel_cart WHERE user_id = ?");
            $stmt->bind_param('i', $existing['user_id']);
            $stmt->execute();
            $stmt->close();
        }
        notifyAdminOrderEmail($orderId);
    }
} elseif ($eventName === 'charge.failed' || $eventName === 'charge.abandoned') {
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT * FROM handel_orders WHERE payment_ref = ? FOR UPDATE");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && $existing['payment_status'] === 'pending') {
            // Conditional update + row lock makes duplicate webhook deliveries
            // restore stock only once, even when they arrive together.
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
        jsonResponse(['error' => 'Could not process payment failure'], 500);
    }
}

jsonResponse(['ok' => true]);
