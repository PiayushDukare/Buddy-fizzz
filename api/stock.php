<?php
require __DIR__ . '/config.php';
$user = require_login();
require_method('GET');

$storeId = (int) ($_GET['store_id'] ?? 0);

if ($user['role'] === 'retailer' || $storeId === 0) {
    $store = my_store((int) $user['id']);
    if (!$store) {
        json_out(['ok' => true, 'stock' => []]);
    }
    $storeId = (int) $store['id'];
} else {
    $s = db()->prepare('SELECT id, partner_id, user_id FROM stores WHERE id = ? LIMIT 1');
    $s->execute([$storeId]);
    $store = $s->fetch();
    if (!$store) {
        json_error('Store not found', 404);
    }
    if ($user['role'] === 'partner' && (int) $store['partner_id'] !== (int) $user['id']) {
        json_error('Forbidden', 403);
    }
}

$stmt = db()->prepare(
    'SELECT p.name, p.sku, p.size, COALESCE(ss.quantity,0) AS quantity, ss.updated_at
     FROM products p
     LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = ?
     WHERE p.is_active = 1 ORDER BY p.name ASC'
);
$stmt->execute([$storeId]);

json_out(['ok' => true, 'stock' => $stmt->fetchAll()]);
