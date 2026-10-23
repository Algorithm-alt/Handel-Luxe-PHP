<?php
/**
 * TASK 4 — delivery fee parity.
 *
 * The checkout UI shows the fee from GET /api/delivery/zones; the server
 * charges lookupZone(). They must agree. This test also compares the offline
 * fallback in config/delivery.php with the live table, because an outage must
 * not quote a different price than a normal request.
 */
echo "[delivery] zone fallback parity and fee consistency\n";

require_once __DIR__ . '/../config/delivery.php';
global $DEFAULT_ZONES;

try {
    getDB();
} catch (Throwable $e) {
    t_skip('database unavailable: ' . $e->getMessage());
    return;
}

$live = getDeliveryZones();
$liveById = [];
foreach ($live as $z) $liveById[$z['id']] = $z;

$parity = true;
foreach ($DEFAULT_ZONES as $id => $fallback) {
    if (!isset($liveById[$id])) { $parity = false; continue; }
    foreach (['label', 'eta'] as $field) {
        if ((string)$fallback[$field] !== (string)$liveById[$id][$field]) $parity = false;
    }
    if ((float)$fallback['fee'] !== (float)$liveById[$id]['fee']) $parity = false;
    if ((string)$fallback['category'] !== (string)$liveById[$id]['category']) $parity = false;
}
t_assert($parity, 'offline fallback zones match the live delivery_zones table');

$consistent = true;
$contradiction = false;
foreach ($live as $z) {
    $charged = lookupZone($z['id']);
    if ((float)$charged['fee'] !== (float)$z['fee']) $consistent = false;
    if ((float)$z['fee'] > 0 && stripos((string)$z['eta'], 'free delivery') !== false) {
        $contradiction = true;
    }
}
t_assert($consistent, 'displayed zone fee equals the server-side lookupZone() fee for every zone');

if ($contradiction) {
    // Deliberate: Task 4 says do not change a price or wording before the
    // owner confirms the intended Swedru policy. Reported, not silently fixed.
    t_skip('a zone has fee > 0 while its ETA says "Free delivery" — awaiting owner decision (see AUDIT-REPORT.md)');
} else {
    t_pass('no fee/ETA price contradiction in live zones');
}

// Displayed total = subtotal + zone fee, the same arithmetic the checkout
// summary and the order total use.
$sampleSubtotal = 100.0;
$totalsOk = true;
foreach ($live as $z) {
    $fee = (float)lookupZone($z['id'])['fee'];
    if (abs(($sampleSubtotal + $fee) - ($sampleSubtotal + (float)$z['fee'])) > 0.0001) $totalsOk = false;
}
t_assert($totalsOk, 'cart total + displayed fee matches cart total + server fee');
