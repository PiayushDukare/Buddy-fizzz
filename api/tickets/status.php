<?php
require __DIR__ . '/../config.php';
$user = require_role('admin', 'partner');
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'ticket_id', 0);
$status = (string) field($data, 'status', '');
if ($id <= 0 || !in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
    json_error('Invalid request', 422);
}

$stmt = db()->prepare('SELECT t.*, s.partner_id FROM tickets t LEFT JOIN stores s ON s.id = t.store_id WHERE t.id = ? LIMIT 1');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) {
    json_error('Ticket not found', 404);
}
if ($user['role'] === 'partner' && (int) $ticket['partner_id'] !== (int) $user['id']) {
    json_error('Forbidden', 403);
}

db()->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$status, $id]);
log_activity('ticket_status', 'ticket', $id, ['status' => $status]);
json_out(['ok' => true, 'message' => 'Ticket marked ' . $status]);
