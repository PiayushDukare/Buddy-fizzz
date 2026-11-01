<?php
require __DIR__ . '/../config.php';
$admin = require_role('admin');
require_method('POST');
verify_csrf();

$d = body();
$id = (int) field($d, 'id', 0);
$action = (string) field($d, 'action', '');
$notes = trim((string) field($d, 'notes', ''));

if ($id < 1 || !in_array($action, ['approve', 'reject'], true)) {
    json_error('Invalid request', 422);
}

$tr = db()->prepare('SELECT * FROM termination_requests WHERE id = ? LIMIT 1');
$tr->execute([$id]);
$req = $tr->fetch();
if (!$req) {
    json_error('Request not found', 404);
}
if ($req['status'] !== 'pending') {
    json_error('This request has already been reviewed', 409);
}

$store = db()->prepare('SELECT s.name, s.user_id FROM stores s WHERE s.id = ? LIMIT 1');
$store->execute([(int) $req['store_id']]);
$store = $store->fetch();

db()->beginTransaction();
try {
    if ($action === 'approve') {
        db()->prepare("UPDATE users SET status = 'terminated' WHERE id = ?")->execute([(int) $req['retailer_user_id']]);
        db()->prepare("UPDATE stores SET status = 'suspended' WHERE id = ?")->execute([(int) $req['store_id']]);
    }
    db()->prepare('UPDATE termination_requests SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
        ->execute([$action === 'approve' ? 'approved' : 'rejected', $notes !== '' ? $notes : null, (int) $admin['id'], $id]);
    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    log_error('termination_action failed', $e);
    json_error('Could not process the request', 500);
}

$ins = db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)');
if ($action === 'approve') {
    $ins->execute([(int) $req['requested_by'], 'termination', 'Termination approved', 'Your request to terminate ' . ($store['name'] ?? 'the retailer') . ' was approved.', 'partner/']);
    if ($store && $store['user_id']) {
        $ins->execute([(int) $store['user_id'], 'account', 'Account terminated', 'Your Buddy Fizz account has been terminated' . ($notes !== '' ? ': ' . $notes : '.') . ' Contact support for details.', 'retailer/']);
        $e = db()->prepare('SELECT email, name FROM users WHERE id = ? LIMIT 1');
        $e->execute([(int) $store['user_id']]);
        $ru = $e->fetch();
        if ($ru && $ru['email']) {
            send_mail(
                [[$ru['email'], (string) $ru['name']]],
                'Account terminated — Buddy Fizz',
                mail_template('Account terminated', '<p>Hi ' . htmlspecialchars($ru['name'], ENT_QUOTES) . ',</p><p>Your Buddy Fizz account has been terminated' . ($notes !== '' ? ': ' . htmlspecialchars($notes, ENT_QUOTES) : '.') . '</p><p>If you believe this is a mistake, please contact support.</p>'),
                ['context' => 'account_terminated', 'sent_by' => $admin['id']]
            );
        }
    }
} else {
    $ins->execute([(int) $req['requested_by'], 'termination', 'Termination declined', 'Your request to terminate ' . ($store['name'] ?? 'the retailer') . ' was declined' . ($notes !== '' ? ': ' . $notes : '.'), 'partner/']);
}

log_activity('termination_action', 'termination_request', $id, ['action' => $action, 'notes' => $notes]);

json_out(['ok' => true, 'message' => $action === 'approve' ? 'Retailer terminated.' : 'Request declined.']);
