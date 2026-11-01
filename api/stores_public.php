<?php
require __DIR__ . '/config.php';
require_method('GET');

$q = trim((string) ($_GET['q'] ?? ''));
$type = $_GET['type'] ?? 'all';

$sql = "SELECT id, name, type, address, village, taluka, district, state, pincode, lat, lng
        FROM stores
        WHERE status = 'active' AND lat IS NOT NULL AND lng IS NOT NULL";
$params = [];

if (in_array($type, ['retail', 'supermarket', 'distributor', 'online'], true)) {
    $sql .= ' AND type = ?';
    $params[] = $type;
}
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR village LIKE ? OR taluka LIKE ? OR district LIKE ? OR state LIKE ? OR pincode LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$sql .= ' ORDER BY name ASC LIMIT 500';
$stmt = db()->prepare($sql);
$stmt->execute($params);

json_out(['ok' => true, 'stores' => $stmt->fetchAll()]);
