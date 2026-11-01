<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('POST');
verify_csrf();

$data = body();
$name = (string) field($data, 'name', '');
$phone = (string) field($data, 'phone', '');

if ($name === '') {
    json_error('Name is required', 422);
}

db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone ?: null, $user['id']]);
log_activity('update_profile', 'user', (int) $user['id']);

json_out(['ok' => true, 'message' => 'Profile updated', 'name' => $name]);
