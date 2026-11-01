<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$partnerId = (int) ($_GET['partner_id'] ?? 0);
if ($partnerId < 1) {
    json_error('Partner not specified', 422);
}

$chk = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'partner' LIMIT 1");
$chk->execute([$partnerId]);
if (!$chk->fetch()) {
    json_error('Partner not found', 404);
}

$stmt = db()->prepare('SELECT id, level, value FROM partner_territories WHERE partner_user_id = ? ORDER BY level, value');
$stmt->execute([$partnerId]);

$geo = ['state' => [], 'district' => [], 'taluka' => []];
$rows = db()->query(
    'SELECT DISTINCT state, district, taluka FROM stores
     UNION SELECT DISTINCT state, district, taluka FROM applications'
)->fetchAll();
foreach ($rows as $r) {
    foreach (['state', 'district', 'taluka'] as $lvl) {
        $val = trim((string) $r[$lvl]);
        if ($val !== '' && !in_array($val, $geo[$lvl], true)) {
            $geo[$lvl][] = $val;
        }
    }
}
foreach ($geo as &$list) {
    sort($list);
}

json_out(['ok' => true, 'territories' => $stmt->fetchAll(), 'geo' => $geo]);
