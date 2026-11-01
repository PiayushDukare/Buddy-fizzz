<?php
require __DIR__ . '/../config.php';
$user = require_role('retailer', 'partner');
require_method('POST');
verify_csrf();

$data = body();
$subject = (string) field($data, 'subject', '');
$category = (string) field($data, 'category', 'general');
$priority = field($data, 'priority', 'medium');
$priority = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';
$message = (string) field($data, 'message', '');

if ($subject === '' || $message === '') {
    json_error('Subject and message are required', 422);
}

$store = my_store((int) $user['id']);

$attachment = handle_upload('attachment');

try {
    db()->beginTransaction();
    db()->prepare('INSERT INTO tickets (store_id, user_id, subject, category, priority, status) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$store['id'] ?? null, $user['id'], $subject, $category ?: 'general', $priority, 'open']);
    $ticketId = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO ticket_messages (ticket_id, user_id, message, attachment) VALUES (?, ?, ?, ?)')
        ->execute([$ticketId, $user['id'], $message, $attachment]);
    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    json_error('Could not create ticket', 500);
}

log_activity('create_ticket', 'ticket', $ticketId);
json_out(['ok' => true, 'message' => 'Support ticket created', 'ticket_id' => $ticketId], 201);
