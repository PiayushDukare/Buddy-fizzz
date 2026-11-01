<?php
require __DIR__ . '/../config.php';
$admin = require_role('admin');
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
$action = field($data, 'action', '');

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    json_error('Invalid request', 422);
}

$stmt = db()->prepare('SELECT * FROM applications WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    json_error('Application not found', 404);
}
if ($app['status'] !== 'pending') {
    json_error('This application has already been ' . $app['status'], 409);
}

if ($action === 'reject') {
    $notes = (string) field($data, 'notes', '');
    db()->prepare('UPDATE applications SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
        ->execute(['rejected', $notes !== '' ? $notes : null, $admin['id'], $id]);
    log_activity('reject_application', 'application', $id);
    json_out(['ok' => true, 'message' => 'Application rejected.']);
}

$assignment = field($data, 'partner_id', 'company');
$partnerId = null;

if ($app['type'] === 'retailer' && $assignment !== 'company' && $assignment !== '' && $assignment !== null) {
    $partnerId = (int) $assignment;
    $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'partner' AND status = 'active' LIMIT 1");
    $check->execute([$partnerId]);
    if (!$check->fetch()) {
        json_error('Selected partner is invalid', 422);
    }
}

$dup = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$dup->execute([$app['email']]);
if ($dup->fetch()) {
    json_error('A user with this email already exists', 409);
}

$storeType = $app['type'] === 'partner' ? 'distributor' : $app['store_type'];

try {
    db()->beginTransaction();

    $u = db()->prepare('INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $u->execute([$app['contact_name'], $app['email'], $app['phone'], $app['password'], $app['type'], 'active']);
    $userId = (int) db()->lastInsertId();

    $s = db()->prepare(
        'INSERT INTO stores
         (user_id, application_id, partner_id, name, owner_name, phone, email, type,
          address, village, taluka, district, state, pincode, lat, lng, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $s->execute([
        $userId, $id, $partnerId,
        $app['business_name'], $app['contact_name'], $app['phone'], $app['email'], $storeType,
        $app['address'], $app['village'], $app['taluka'], $app['district'], $app['state'], $app['pincode'],
        $app['lat'], $app['lng'], 'active',
    ]);
    $storeId = (int) db()->lastInsertId();

    db()->prepare('UPDATE applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
        ->execute(['approved', $admin['id'], $id]);

    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, 'welcome', 'Welcome to Buddy Fizz!', 'Your ' . $app['type'] . ' account is now active.', $app['type'] . '/']);

    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    json_error('Failed to approve application', 500);
}

log_activity('approve_application', 'application', $id, [
    'user_id' => $userId,
    'store_id' => $storeId,
    'role' => $app['type'],
    'partner_id' => $partnerId,
]);

$loginUrl = abs_url('login.html');
$body = '<p>Hi ' . htmlspecialchars($app['contact_name'], ENT_QUOTES) . ',</p>'
    . '<p>Great news — your application for <b>' . htmlspecialchars($app['business_name'], ENT_QUOTES) . '</b> has been <b>approved</b> and your ' . htmlspecialchars($app['type'], ENT_QUOTES) . ' account is now active.</p>'
    . '<p>You can sign in with the email <b>' . htmlspecialchars($app['email'], ENT_QUOTES) . '</b> and the password you chose when applying.</p>'
    . '<p style="margin:22px 0">' . mail_button('Sign in to your portal', $loginUrl) . '</p>'
    . '<p>Welcome to the ' . htmlspecialchars((string) get_setting('company_name', 'Buddy Fizz'), ENT_QUOTES) . ' family! 🥤</p>';
send_mail(
    [[$app['email'], $app['contact_name']]],
    'Your ' . ucfirst($app['type']) . ' account is approved',
    mail_template('Welcome aboard!', $body),
    ['context' => 'approval', 'sent_by' => $admin['id']]
);

json_out([
    'ok' => true,
    'message' => ucfirst($app['type']) . ' account created and activated.',
    'user_id' => $userId,
    'store_id' => $storeId,
    'assigned' => $app['type'] === 'retailer' ? ($partnerId ? 'partner #' . $partnerId : 'company (direct)') : 'n/a',
]);
