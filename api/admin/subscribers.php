<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$subs = db()->query('SELECT id, email, created_at FROM subscribers ORDER BY created_at DESC LIMIT 500')->fetchAll();
$count = db()->query('SELECT COUNT(*) FROM subscribers')->fetchColumn();

json_out(['ok' => true, 'subscribers' => $subs, 'total' => (int) $count]);
