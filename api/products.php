<?php
require __DIR__ . '/config.php';
$user = require_login();
require_method('GET');

if ($user['role'] === 'admin') {
    $rows = db()->query('SELECT * FROM products ORDER BY name ASC')->fetchAll();
} else {
    $rows = db()->query('SELECT id, name, flavour, sku, size, price, mrp, image FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
}

json_out(['ok' => true, 'products' => $rows]);
