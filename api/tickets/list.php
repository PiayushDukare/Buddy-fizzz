<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('GET');

$sql = 'SELECT t.id, t.subject, t.category, t.priority, t.status, t.created_at, t.updated_at,
        u.name AS opener, s.name AS store_name
        FROM tickets t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN stores s ON s.id = t.store_id
        WHERE 1=1';
$params = [];

if ($user['role'] === 'retailer') {
    $sql .= ' AND t.user_id = ?';
    $params[] = $user['id'];
} elseif ($user['role'] === 'partner') {
    $sql .= ' AND (t.user_id = ? OR t.store_id IN (SELECT id FROM stores WHERE partner_id = ?))';
    $params[] = $user['id'];
    $params[] = $user['id'];
}

$status = $_GET['status'] ?? 'all';
if (in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
    $sql .= ' AND t.status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY t.updated_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);

json_out(['ok' => true, 'tickets' => $stmt->fetchAll()]);
