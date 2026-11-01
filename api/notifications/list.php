<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('GET');

$rows = db()->prepare('SELECT id, type, title, body, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30');
$rows->execute([$user['id']]);

$unread = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unread->execute([$user['id']]);

json_out(['ok' => true, 'notifications' => $rows->fetchAll(), 'unread' => (int) $unread->fetchColumn()]);
