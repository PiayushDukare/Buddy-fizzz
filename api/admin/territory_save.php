<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$data = body();
$partnerId = (int) field($data, 'partner_id', 0);
$level = (string) field($data, 'level', '');
$value = trim((string) field($data, 'value', ''));

if (!in_array($level, ['state', 'district', 'taluka'], true)) {
    json_error('Invalid territory level', 422);
}
if ($value === '' || mb_strlen($value) > 120) {
    json_error('Enter a valid area name', 422);
}

$chk = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'partner' AND status = 'active' LIMIT 1");
$chk->execute([$partnerId]);
if (!$chk->fetch()) {
    json_error('Partner not found or inactive', 404);
}

$dup = db()->prepare('SELECT id FROM partner_territories WHERE partner_user_id = ? AND level = ? AND value = ? LIMIT 1');
$dup->execute([$partnerId, $level, $value]);
if ($dup->fetch()) {
    json_error('This area is already assigned to the partner', 409);
}

db()->prepare('INSERT INTO partner_territories (partner_user_id, level, value) VALUES (?, ?, ?)')
    ->execute([$partnerId, $level, $value]);

log_activity('add_territory', 'partner', $partnerId, ['level' => $level, 'value' => $value]);
json_out(['ok' => true, 'message' => 'Territory added']);
