<?php
$sub = str_replace('delivery/', '', $_GET['route'] ?? '');

if ($sub === 'zones') {
    jsonResponse(getDeliveryZones());
} else {
    jsonResponse(['error' => 'Not found'], 404);
}
