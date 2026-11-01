<?php
require __DIR__ . '/../config.php';
require_method('POST');

$data = body();
$token = (string) field($data, 'token', '');
$password = (string) field($data, 'password', '');

if ($token === '') {
    json_error('This reset link is invalid.', 400);
}
if (strlen($password) < 8) {
    json_error('Password must be at least 8 characters.', 422);
}

$stmt = db()->prepare('SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1');
$stmt->execute([hash('sha256', $token)]);
$row = $stmt->fetch();
if (!$row) {
    json_error('This reset link is invalid or has expired. Please request a new one.', 400);
}

$email = $row['email'];
db()->prepare('UPDATE users SET password = ? WHERE email = ?')
    ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $email]);
db()->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);

$uid = (int) db()->query('SELECT id FROM users WHERE email = ' . db()->quote($email))->fetchColumn();
log_activity('password_reset_done', 'user', $uid);

json_out(['ok' => true, 'message' => 'Your password has been reset. You can now sign in.']);
