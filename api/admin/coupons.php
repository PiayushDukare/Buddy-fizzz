<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$rows = db()->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();
json_out(['ok' => true, 'coupons' => $rows]);
