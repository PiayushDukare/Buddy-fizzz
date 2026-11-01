<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('POST');
verify_csrf();

$data = body();
$current = (string) field($data, 'current', '');
$new = (string) field($data, 'new', '');

if (strlen($new) < 8) {
    json_error('New password must be at least 8 characters', 422);
}

$row = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
$row->execute([$user['id']]);
$hash = $row->fetchColumn();

if (!$hash || !password_verify($current, $hash)) {
    json_error('Your current password is incorrect', 403);
}

db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
log_activity('change_password', 'user', (int) $user['id']);

json_out(['ok' => true, 'message' => 'Password changed successfully']);
