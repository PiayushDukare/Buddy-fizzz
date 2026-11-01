<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('GET');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Invalid order', 422);
}

$stmt = db()->prepare(
    'SELECT o.*, s.name AS store_name, s.owner_name, s.phone AS store_phone,
            s.address, s.village, s.taluka, s.district, s.state, s.pincode, s.user_id AS store_user_id
     FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    json_error('Order not found', 404);
}

$allowed = $user['role'] === 'admin'
    || ($user['role'] === 'partner' && ((int) $order['supplier_id'] === (int) $user['id'] || (int) $order['store_user_id'] === (int) $user['id']))
    || ($user['role'] === 'retailer' && (int) $order['store_user_id'] === (int) $user['id']);

if (!$allowed) {
    json_error('Forbidden', 403);
}

$items = db()->prepare('SELECT oi.product_id, oi.qty, oi.unit_price, oi.line_total, p.name, p.sku, p.size, p.image, p.is_active FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$items->execute([$id]);

json_out(['ok' => true, 'order' => $order, 'items' => $items->fetchAll()]);
