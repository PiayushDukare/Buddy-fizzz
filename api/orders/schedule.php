<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
$date = (string) field($data, 'delivery_date', '');

$stmt = db()->prepare('SELECT o.*, s.user_id AS store_user_id FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE o.id = ? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    json_error('Order not found', 404);
}

$isSupplier = $user['role'] === 'admin'
    || ($user['role'] === 'partner' && (int) $order['supplier_id'] === (int) $user['id']);
if (!$isSupplier) {
    json_error('Only the supplier can schedule delivery', 403);
}

$deliveryDate = $date === '' ? null : ($date);
if ($deliveryDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryDate)) {
    json_error('Invalid date', 422);
}

db()->prepare('UPDATE orders SET delivery_date = ? WHERE id = ?')->execute([$deliveryDate, $id]);

if ($deliveryDate) {
    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([(int) $order['store_user_id'], 'order', 'Delivery scheduled', 'Order #' . $id . ' is scheduled for delivery on ' . date('d M Y', strtotime($deliveryDate)) . '.', 'retailer/']);
}

log_activity('schedule_order', 'order', $id, ['delivery_date' => $deliveryDate]);
json_out(['ok' => true, 'message' => $deliveryDate ? 'Delivery scheduled for ' . date('d M Y', strtotime($deliveryDate)) : 'Delivery date cleared']);
