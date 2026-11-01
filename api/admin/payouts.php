<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$partners = db()->query("
    SELECT u.id, u.name, u.email,
      (SELECT IFNULL(SUM(oi.qty * p.partner_profit),0)
       FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id
       WHERE o.supplier_id = u.id AND o.supplier_type = 'partner' AND o.status = 'delivered') AS earned,
      (SELECT IFNULL(SUM(amount),0) FROM partner_settlements WHERE partner_user_id = u.id) AS settled,
      (SELECT MAX(created_at) FROM partner_settlements WHERE partner_user_id = u.id) AS last_settled
    FROM users u WHERE u.role = 'partner' AND u.status = 'active'
    ORDER BY u.name ASC
")->fetchAll();

$totEarned = 0;
$totSettled = 0;
foreach ($partners as &$p) {
    $p['earned'] = (float) $p['earned'];
    $p['settled'] = (float) $p['settled'];
    $p['outstanding'] = round($p['earned'] - $p['settled'], 2);
    $totEarned += $p['earned'];
    $totSettled += $p['settled'];
}
unset($p);

$history = db()->query('
    SELECT ps.id, ps.amount, ps.note, ps.created_at, u.name AS partner_name, a.name AS settled_by_name
    FROM partner_settlements ps
    JOIN users u ON u.id = ps.partner_user_id
    LEFT JOIN users a ON a.id = ps.settled_by
    ORDER BY ps.id DESC LIMIT 50
')->fetchAll();

json_out([
    'ok' => true,
    'partners' => $partners,
    'history' => $history,
    'totals' => [
        'earned' => round($totEarned, 2),
        'settled' => round($totSettled, 2),
        'outstanding' => round($totEarned - $totSettled, 2),
    ],
]);
