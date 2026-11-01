<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'ticket_id', 0);
$message = (string) field($data, 'message', '');
$attachment = handle_upload('attachment');

if ($id <= 0 || ($message === '' && !$attachment)) {
    json_error('Message or attachment is required', 422);
}

$stmt = db()->prepare('SELECT t.*, s.partner_id FROM tickets t LEFT JOIN stores s ON s.id = t.store_id WHERE t.id = ? LIMIT 1');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) {
    json_error('Ticket not found', 404);
}

$allowed = $user['role'] === 'admin'
    || (int) $ticket['user_id'] === (int) $user['id']
    || ($user['role'] === 'partner' && (int) $ticket['partner_id'] === (int) $user['id']);
if (!$allowed) {
    json_error('Forbidden', 403);
}

$newStatus = in_array($ticket['status'], ['resolved', 'closed'], true) ? 'open' : $ticket['status'];

db()->prepare('INSERT INTO ticket_messages (ticket_id, user_id, message, attachment) VALUES (?, ?, ?, ?)')->execute([$id, $user['id'], $message !== '' ? $message : '(attachment)', $attachment]);
db()->prepare('UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$newStatus, $id]);

if ((int) $ticket['user_id'] !== (int) $user['id']) {
    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([$ticket['user_id'], 'ticket', 'Reply on your ticket', 'Support replied to "' . $ticket['subject'] . '"', 'retailer/']);
}

log_activity('reply_ticket', 'ticket', $id);
json_out(['ok' => true, 'message' => 'Reply sent']);
