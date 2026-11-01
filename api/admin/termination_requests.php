<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$status = (string) ($_GET['status'] ?? 'pending');
$valid = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $valid, true)) {
    $status = 'pending';
}

$sql = "SELECT tr.id, tr.store_id, tr.reason, tr.status, tr.review_notes, tr.created_at, tr.reviewed_at,
               s.name AS store_name, s.district, s.taluka,
               ru.name AS retailer_name, ru.email AS retailer_email, ru.status AS retailer_status,
               pu.name AS partner_name
        FROM termination_requests tr
        JOIN stores s ON s.id = tr.store_id
        JOIN users ru ON ru.id = tr.retailer_user_id
        JOIN users pu ON pu.id = tr.requested_by";
$params = [];
if ($status !== 'all') {
    $sql .= ' WHERE tr.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY tr.created_at DESC LIMIT 200';

$st = db()->prepare($sql);
$st->execute($params);

json_out(['ok' => true, 'requests' => $st->fetchAll()]);
