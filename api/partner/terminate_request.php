<?php
require __DIR__ . '/../config.php';
$user = require_role('partner');
require_method('POST');
verify_csrf();

$d = body();
$storeId = (int) field($d, 'store_id', 0);
$reason = trim((string) field($d, 'reason', ''));

if ($storeId < 1) {
    json_error('Retailer not specified', 422);
}
if ($reason === '') {
    json_error('Please give a reason for the termination request', 422);
}

$s = db()->prepare(
    "SELECT s.id, s.name, s.user_id, u.status
     FROM stores s JOIN users u ON u.id = s.user_id
     WHERE s.id = ? AND s.partner_id = ? AND u.role = 'retailer' LIMIT 1"
);
$s->execute([$storeId, (int) $user['id']]);
$store = $s->fetch();
if (!$store) {
    json_error('That retailer is not assigned to you', 403);
}
if ($store['status'] === 'terminated') {
    json_error('This retailer is already terminated', 409);
}

$dup = db()->prepare("SELECT id FROM termination_requests WHERE store_id = ? AND status = 'pending' LIMIT 1");
$dup->execute([$storeId]);
if ($dup->fetch()) {
    json_error('A termination request for this retailer is already pending review', 409);
}

db()->prepare('INSERT INTO termination_requests (store_id, retailer_user_id, requested_by, reason) VALUES (?, ?, ?, ?)')
    ->execute([$storeId, (int) $store['user_id'], (int) $user['id'], $reason]);
$reqId = (int) db()->lastInsertId();

$admins = db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
$ins = db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)');
foreach ($admins as $a) {
    $ins->execute([(int) $a['id'], 'termination', 'Retailer termination request', $user['name'] . ' requested to terminate ' . $store['name'] . '.', 'admin/']);
}

log_activity('termination_request', 'store', $storeId, ['request_id' => $reqId, 'reason' => $reason]);

json_out(['ok' => true, 'message' => 'Termination request sent to the admin for review.']);
