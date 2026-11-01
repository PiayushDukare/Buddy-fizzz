<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$stmt = db()->query(
    "SELECT s.id, s.name, s.type, s.status, s.village, s.taluka, s.district, s.state, s.pincode, s.lat, s.lng,
            u.role, p.name AS partner_name
     FROM stores s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN users p ON p.id = s.partner_id
     WHERE s.lat IS NOT NULL AND s.lng IS NOT NULL
     ORDER BY s.created_at DESC LIMIT 1000"
);

json_out(['ok' => true, 'stores' => $stmt->fetchAll()]);
