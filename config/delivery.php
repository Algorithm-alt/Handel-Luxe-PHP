<?php
require_once __DIR__ . '/db.php';

/* Delivery areas are grouped under two main categories:
   'pickup' (Pick Up) and 'delivery' (Delivery). Sub-zones hang off each.

   These values seed handel_delivery_zones on a fresh install, and they are also
   what getDeliveryZones() and lookupZone() fall back to when the database
   cannot be reached. They are therefore not free-form defaults: they have to
   mirror the live table, or an outage quotes the customer one fee while the
   server charges another. The owner edits the live values from the admin
   dashboard, and the change belongs here too.

   This is the only copy. pages/checkout.php prints getDeliveryZones() into the
   page as its offline fallback rather than keeping a list of its own, so there
   is nothing else to keep in step. */
$DEFAULT_ZONES = [
    'swedru' => ['id' => 'swedru', 'label' => 'Agona Swedru', 'fee' => 10, 'eta' => 'Free delivery', 'category' => 'delivery'],
    'nearby' => ['id' => 'nearby', 'label' => 'Greater Accra', 'fee' => 35, 'eta' => '1-3 business days', 'category' => 'delivery'],
    'kumasi' => ['id' => 'kumasi', 'label' => 'Ashanti Region', 'fee' => 100, 'eta' => '1-2 business days', 'category' => 'delivery'],
    'countryside' => ['id' => 'countryside', 'label' => 'Other Regions (Ghana)', 'fee' => 50, 'eta' => '2-5 business days', 'category' => 'delivery'],
    'collect' => ['id' => 'collect', 'label' => 'Self-Collection', 'fee' => 0, 'eta' => 'Pick up at our Swedru store', 'category' => 'pickup'],
];

function zoneCategoryLabel($category) {
    return $category === 'pickup' ? 'Pick Up' : 'Delivery';
}

function ensureZones() {
    $db = getDB();
    $db->query("CREATE TABLE IF NOT EXISTS handel_delivery_zones (
        id VARCHAR(50) PRIMARY KEY,
        label VARCHAR(120) NOT NULL,
        fee DECIMAL(10,2) NOT NULL DEFAULT 0,
        eta VARCHAR(120),
        category VARCHAR(20) NOT NULL DEFAULT 'delivery'
    )");
    // Older installs predate the category column — add it and backfill.
    $cols = $db->query("SHOW COLUMNS FROM handel_delivery_zones LIKE 'category'");
    if ($cols->num_rows === 0) {
        $db->query("ALTER TABLE handel_delivery_zones ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'delivery'");
        $db->query("UPDATE handel_delivery_zones SET category = 'pickup' WHERE id = 'collect'");
    }
    $result = $db->query("SELECT COUNT(*) as cnt FROM handel_delivery_zones");
    $row = $result->fetch_assoc();
    if ($row['cnt'] == 0) {
        global $DEFAULT_ZONES;
        $stmt = $db->prepare("INSERT INTO handel_delivery_zones (id, label, fee, eta, category) VALUES (?, ?, ?, ?, ?)");
        foreach ($DEFAULT_ZONES as $z) {
            $stmt->bind_param('ssdss', $z['id'], $z['label'], $z['fee'], $z['eta'], $z['category']);
            $stmt->execute();
        }
        $stmt->close();
    }
}

function getDeliveryZones() {
    global $DEFAULT_ZONES;
    try {
        ensureZones();
        $db = getDB();
        $result = $db->query("SELECT * FROM handel_delivery_zones ORDER BY category DESC, id");
        $zones = [];
        while ($row = $result->fetch_assoc()) {
            $row['fee'] = (float)$row['fee'];
            if (!in_array($row['category'], ['pickup', 'delivery'], true)) $row['category'] = 'delivery';
            $zones[] = $row;
        }
        return $zones;
    } catch (Exception $e) {
        return array_values($DEFAULT_ZONES);
    }
}

function lookupZone($id = null) {
    global $DEFAULT_ZONES;
    $zoneId = $id ?: 'swedru';
    try {
        ensureZones();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM handel_delivery_zones WHERE id = ?");
        $stmt->bind_param('s', $zoneId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $row['fee'] = (float)$row['fee'];
            if (!in_array($row['category'], ['pickup', 'delivery'], true)) $row['category'] = 'delivery';
            return $row;
        }
        $fallback = isset($DEFAULT_ZONES[$zoneId]) ? $DEFAULT_ZONES[$zoneId] : $DEFAULT_ZONES['swedru'];
        return $fallback;
    } catch (Exception $e) {
        return isset($DEFAULT_ZONES[$zoneId]) ? $DEFAULT_ZONES[$zoneId] : $DEFAULT_ZONES['swedru'];
    }
}
