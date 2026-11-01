<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$products = db()->query(
    'SELECT p.id, p.name, p.sku, p.size, p.image, p.is_active, COALESCE(cs.quantity, 0) AS company_qty
     FROM products p LEFT JOIN company_stock cs ON cs.product_id = p.id
     ORDER BY p.name ASC'
)->fetchAll();

$stores = db()->query(
    "SELECT s.id, s.name, u.role, COALESCE(SUM(ss.quantity), 0) AS units
     FROM stores s JOIN users u ON u.id = s.user_id
     LEFT JOIN store_stock ss ON ss.store_id = s.id
     GROUP BY s.id ORDER BY units DESC LIMIT 200"
)->fetchAll();

$movements = db()->query(
    "SELECT m.change_qty, m.reason, m.order_id, m.created_at,
            p.name AS product, COALESCE(s.name, 'Company Warehouse') AS place
     FROM stock_movements m
     JOIN products p ON p.id = m.product_id
     LEFT JOIN stores s ON s.id = m.store_id
     ORDER BY m.id DESC LIMIT 60"
)->fetchAll();

json_out(['ok' => true, 'products' => $products, 'stores' => $stores, 'movements' => $movements]);
