<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$status = $_GET['status'] ?? 'pending';
$status = in_array($status, ['pending', 'approved', 'rejected', 'all'], true) ? $status : 'pending';
$type = $_GET['type'] ?? 'all';
$q = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT id, type, business_name, contact_name, email, phone, store_type,
        address, village, taluka, district, state, pincode, lat, lng, message,
        status, review_notes, reviewed_at, created_at
        FROM applications WHERE 1=1';
$params = [];

if ($status !== 'all') {
    $sql .= ' AND status = ?';
    $params[] = $status;
}
if (in_array($type, ['retailer', 'partner'], true)) {
    $sql .= ' AND type = ?';
    $params[] = $type;
}
if ($q !== '') {
    $sql .= ' AND (business_name LIKE ? OR contact_name LIKE ? OR email LIKE ? OR phone LIKE ? OR district LIKE ? OR taluka LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$sql .= ' ORDER BY created_at DESC LIMIT 200';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = db()->query(
    "SELECT
        SUM(status='pending') AS pending,
        SUM(status='approved') AS approved,
        SUM(status='rejected') AS rejected
     FROM applications"
)->fetch();

json_out(['ok' => true, 'applications' => $rows, 'counts' => $counts]);
