<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('GET');

$status = $_GET['status'] ?? 'all';
$sql = 'SELECT o.id, o.status, o.total, o.amount_paid, o.payment_status, o.supplier_type, o.supplier_id, o.created_at,
        s.name AS store_name, s.district, s.state,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE 1=1';
$params = [];

if ($user['role'] === 'retailer') {
    $store = my_store((int) $user['id']);
    $sql .= ' AND o.buyer_store_id = ?';
    $params[] = $store['id'] ?? 0;
} elseif ($user['role'] === 'partner') {
    $store = my_store((int) $user['id']);
    $sql .= ' AND (o.supplier_id = ? OR o.buyer_store_id = ?)';
    $params[] = $user['id'];
    $params[] = $store['id'] ?? 0;
}

if (in_array($status, ['pending', 'approved', 'rejected', 'processing', 'dispatched', 'delivered', 'cancelled'], true)) {
    $sql .= ' AND o.status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY o.created_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);

json_out(['ok' => true, 'orders' => $stmt->fetchAll()]);
