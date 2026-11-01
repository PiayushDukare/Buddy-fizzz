<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$rows = db()->query('SELECT skey, svalue FROM settings')->fetchAll();
$settings = [];
foreach ($rows as $r) {
    $settings[$r['skey']] = $r['svalue'];
}

json_out(['ok' => true, 'settings' => $settings]);
