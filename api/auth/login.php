<?php
require __DIR__ . '/../config.php';
require_method('POST');

$data = body();
$email = strtolower((string) field($data, 'email', ''));
$password = (string) field($data, 'password', '');

if ($email === '' || $password === '') {
    json_error('Email and password are required', 422);
}

if (login_blocked($email)) {
    json_error('Too many failed attempts. Please try again in 15 minutes.', 429);
}

$stmt = db()->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    record_login_attempt($email, false);
    json_error('Invalid email or password', 401);
}
if ($user['status'] !== 'active') {
    record_login_attempt($email, false);
    json_error('Your account is suspended. Please contact support.', 403);
}

record_login_attempt($email, true);
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
log_activity('login', 'user', (int) $user['id']);

json_out([
    'ok' => true,
    'user' => [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ],
    'csrf' => csrf_token(),
    'redirect' => $user['role'] . '/',
]);
