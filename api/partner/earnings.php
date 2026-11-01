<?php
require __DIR__ . '/../config.php';
$user = require_role('partner');
require_method('GET');
$me = (int) $user['id'];

$earned = (float) db()->query("
    SELECT IFNULL(SUM(oi.qty * p.partner_profit),0)
    FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id
    WHERE o.supplier_id = " . $me . " AND o.supplier_type = 'partner' AND o.status = 'delivered'
")->fetchColumn();

$settled = (float) db()->query('SELECT IFNULL(SUM(amount),0) FROM partner_settlements WHERE partner_user_id = ' . $me)->fetchColumn();

$byProduct = db()->prepare("
    SELECT p.name, SUM(oi.qty) AS bottles, ROUND(SUM(oi.qty * p.partner_profit),2) AS profit
    FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id
    WHERE o.supplier_id = ? AND o.supplier_type = 'partner' AND o.status = 'delivered'
    GROUP BY p.id ORDER BY profit DESC
");
$byProduct->execute([$me]);

$history = db()->prepare('SELECT amount, note, created_at FROM partner_settlements WHERE partner_user_id = ? ORDER BY id DESC LIMIT 20');
$history->execute([$me]);

json_out([
    'ok' => true,
    'earned' => round($earned, 2),
    'settled' => round($settled, 2),
    'outstanding' => round($earned - $settled, 2),
    'by_product' => $byProduct->fetchAll(),
    'history' => $history->fetchAll(),
]);
