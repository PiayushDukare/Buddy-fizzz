<?php
require __DIR__ . '/../config.php';
require_method('POST');

$email = strtolower((string) field(body(), 'email', ''));
$generic = ['ok' => true, 'message' => 'If an account exists for that email, a reset link has been sent.'];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out($generic);
}

$recent = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
$recent->execute([$email]);
if ((int) $recent->fetchColumn() >= 3) {
    json_out($generic);
}

$stmt = db()->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active' LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $token = bin2hex(random_bytes(32));
    db()->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
    db()->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, (NOW() + INTERVAL 1 HOUR))')
        ->execute([$email, hash('sha256', $token)]);

    $link = abs_url('reset.html?token=' . $token);
    $body = '<p>Hi ' . htmlspecialchars($user['name'], ENT_QUOTES) . ',</p>'
        . '<p>We received a request to reset your Buddy Fizz password. This link is valid for <b>1 hour</b>.</p>'
        . '<p style="margin:22px 0">' . mail_button('Reset my password', $link) . '</p>'
        . '<p>If you didn\'t request this, you can safely ignore this email — your password stays the same.</p>';
    send_mail(
        [[$email, $user['name']]],
        'Reset your Buddy Fizz password',
        mail_template('Password reset', $body),
        ['context' => 'password_reset', 'no_business_bcc' => true, 'sent_by' => (int) $user['id']]
    );
    log_activity('password_reset_request', 'user', (int) $user['id']);
}

json_out($generic);
