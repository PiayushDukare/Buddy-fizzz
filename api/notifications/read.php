<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('POST');
verify_csrf();

$id = (int) field(body(), 'id', 0);
if ($id > 0) {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
} else {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
}

json_out(['ok' => true]);
