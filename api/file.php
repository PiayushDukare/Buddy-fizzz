<?php
require __DIR__ . '/config.php';
$user = require_login();
require_method('GET');

$f = (string) ($_GET['f'] ?? '');

if (!preg_match('#^uploads/tickets/[a-f0-9]{16}\.[a-z0-9]{1,5}$#', $f)) {
    json_error('Invalid file', 400);
}

$stmt = db()->prepare(
    'SELECT t.user_id, s.partner_id
     FROM ticket_messages m
     JOIN tickets t ON t.id = m.ticket_id
     LEFT JOIN stores s ON s.id = t.store_id
     WHERE m.attachment = ? LIMIT 1'
);
$stmt->execute([$f]);
$row = $stmt->fetch();

if (!$row) {
    json_error('File not found', 404);
}

$allowed = $user['role'] === 'admin'
    || (int) $row['user_id'] === (int) $user['id']
    || ($user['role'] === 'partner' && (int) $row['partner_id'] === (int) $user['id']);

if (!$allowed) {
    json_error('Forbidden', 403);
}

$base = realpath(dirname(__DIR__) . '/uploads/tickets');
$path = realpath(dirname(__DIR__) . '/' . $f);

if ($base === false || $path === false || strpos($path, $base) !== 0 || !is_file($path)) {
    json_error('File not found', 404);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
$name = basename($path);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-store');
readfile($path);
exit;
