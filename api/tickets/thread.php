<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('GET');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Invalid ticket', 422);
}

$stmt = db()->prepare(
    'SELECT t.*, u.name AS opener, s.name AS store_name, s.partner_id
     FROM tickets t JOIN users u ON u.id = t.user_id
     LEFT JOIN stores s ON s.id = t.store_id WHERE t.id = ? LIMIT 1'
);
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

$msgs = db()->prepare('SELECT m.message, m.attachment, m.created_at, u.name, u.role FROM ticket_messages m JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? ORDER BY m.created_at ASC');
$msgs->execute([$id]);

json_out(['ok' => true, 'ticket' => $ticket, 'messages' => $msgs->fetchAll()]);
