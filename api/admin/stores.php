<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$role = $_GET['role'] ?? 'retailer';
$q = trim((string) ($_GET['q'] ?? ''));

$sql = "SELECT s.id, s.name, s.owner_name, s.phone, s.email, s.gstin, s.address, s.type,
               s.village, s.taluka, s.district, s.state, s.pincode, s.status, s.created_at, s.partner_id,
               s.wallet_balance, s.credit_allowed, s.credit_limit,
               u.id AS user_id, u.role, u.status AS user_status,
               p.name AS partner_name
        FROM stores s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN users p ON p.id = s.partner_id
        WHERE 1=1";
$params = [];

if (in_array($role, ['retailer', 'partner'], true)) {
    $sql .= ' AND u.role = ?';
    $params[] = $role;
}
if ($q !== '') {
    $sql .= ' AND (s.name LIKE ? OR s.owner_name LIKE ? OR u.email LIKE ? OR s.district LIKE ? OR s.taluka LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$sql .= ' ORDER BY s.created_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);

json_out(['ok' => true, 'stores' => $stmt->fetchAll()]);
