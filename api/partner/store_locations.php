<?php
require __DIR__ . '/../config.php';
$user = require_role('partner');
require_method('GET');

$stmt = db()->prepare(
    "SELECT s.id, s.name, s.type, s.village, s.taluka, s.district, s.state, s.pincode, s.lat, s.lng,
            (SELECT COUNT(*) FROM orders o WHERE o.buyer_store_id = s.id) AS orders
     FROM stores s
     WHERE s.partner_id = ? AND s.lat IS NOT NULL AND s.lng IS NOT NULL
     ORDER BY s.name ASC LIMIT 1000"
);
$stmt->execute([$user['id']]);

json_out(['ok' => true, 'stores' => $stmt->fetchAll()]);
