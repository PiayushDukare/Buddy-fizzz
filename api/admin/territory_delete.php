<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$id = (int) field(body(), 'id', 0);
if ($id < 1) {
    json_error('Territory not specified', 422);
}

$stmt = db()->prepare('SELECT partner_user_id FROM partner_territories WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('Territory not found', 404);
}

db()->prepare('DELETE FROM partner_territories WHERE id = ?')->execute([$id]);

log_activity('remove_territory', 'partner', (int) $row['partner_user_id'], ['territory_id' => $id]);
json_out(['ok' => true, 'message' => 'Territory removed']);
