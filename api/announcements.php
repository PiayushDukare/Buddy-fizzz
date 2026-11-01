<?php
require __DIR__ . '/config.php';
$user = require_login();
require_method('GET');

if ($user['role'] === 'admin' && isset($_GET['all'])) {
    $rows = db()->query('SELECT * FROM announcements ORDER BY created_at DESC LIMIT 100')->fetchAll();
    json_out(['ok' => true, 'announcements' => $rows]);
}

$stmt = db()->prepare(
    "SELECT id, title, body, audience, starts_at, ends_at, created_at
     FROM announcements
     WHERE is_active = 1
       AND (audience = 'all' OR audience = ?)
       AND (starts_at IS NULL OR starts_at <= CURDATE())
       AND (ends_at IS NULL OR ends_at >= CURDATE())
     ORDER BY created_at DESC LIMIT 10"
);
$stmt->execute([$user['role']]);

json_out(['ok' => true, 'announcements' => $stmt->fetchAll()]);
