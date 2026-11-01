<?php
require __DIR__ . '/../config.php';
$admin = require_role('admin');
require_method('POST');
verify_csrf();

$d = body();
$userId = (int) field($d, 'user_id', 0);
$action = (string) field($d, 'action', '');
$reason = trim((string) field($d, 'reason', ''));

$map = ['terminate' => 'terminated', 'suspend' => 'suspended', 'activate' => 'active'];
if ($userId < 1 || !isset($map[$action])) {
    json_error('Invalid request', 422);
}
$newStatus = $map[$action];

$u = db()->prepare('SELECT id, name, role, status FROM users WHERE id = ? LIMIT 1');
$u->execute([$userId]);
$target = $u->fetch();
if (!$target) {
    json_error('User not found', 404);
}
if ($target['role'] === 'admin') {
    json_error('Admin accounts cannot be changed here', 403);
}
if ((int) $target['id'] === (int) $admin['id']) {
    json_error('You cannot change your own account', 403);
}
if ($target['status'] === $newStatus) {
    json_error('Account is already ' . $newStatus, 409);
}

db()->beginTransaction();
try {
    db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $userId]);
    $storeStatus = $newStatus === 'active' ? 'active' : 'suspended';
    db()->prepare('UPDATE stores SET status = ? WHERE user_id = ?')->execute([$storeStatus, $userId]);
    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    log_error('user_status update failed', $e);
    json_error('Could not update the account', 500);
}

$titles = [
    'terminated' => 'Account terminated',
    'suspended' => 'Account suspended',
    'active' => 'Account reactivated',
];
$bodies = [
    'terminated' => 'Your Buddy Fizz account has been terminated' . ($reason !== '' ? ': ' . $reason : '.') . ' Contact support if you believe this is a mistake.',
    'suspended' => 'Your Buddy Fizz account has been suspended' . ($reason !== '' ? ': ' . $reason : '.') . ' Please contact support.',
    'active' => 'Good news — your Buddy Fizz account has been reactivated. You can log in again.',
];
db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
    ->execute([$userId, 'account', $titles[$newStatus], $bodies[$newStatus], $target['role'] . '/']);

$email = db()->prepare('SELECT email, name FROM users WHERE id = ? LIMIT 1');
$email->execute([$userId]);
$e = $email->fetch();
if ($e && $e['email']) {
    send_mail(
        [[$e['email'], (string) $e['name']]],
        $titles[$newStatus] . ' — Buddy Fizz',
        mail_template($titles[$newStatus], '<p>Hi ' . htmlspecialchars($e['name'], ENT_QUOTES) . ',</p><p>' . htmlspecialchars($bodies[$newStatus], ENT_QUOTES) . '</p>'),
        ['context' => 'account_status', 'sent_by' => $admin['id']]
    );
}

log_activity('user_status', 'user', $userId, ['status' => $newStatus, 'reason' => $reason]);

json_out(['ok' => true, 'message' => ucfirst($target['role']) . ' ' . $target['name'] . ' is now ' . $newStatus . '.', 'status' => $newStatus]);
