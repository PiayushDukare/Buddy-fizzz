<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$valid = "o.status NOT IN ('cancelled','rejected')";

$from = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['from'])) ? (string) $_GET['from'] : null;
$to = (isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['to'])) ? (string) $_GET['to'] : null;
$days = max(1, min((int) ($_GET['days'] ?? 90), 730));
if ($from && $to) {
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $range = "o.created_at >= '$from 00:00:00' AND o.created_at < DATE_ADD('$to', INTERVAL 1 DAY)";
    $range2 = "f >= '$from 00:00:00' AND f < DATE_ADD('$to', INTERVAL 1 DAY)";
    $rangeLabel = $from . ' to ' . $to;
} else {
    $range = "o.created_at > (NOW() - INTERVAL $days DAY)";
    $range2 = "f > (NOW() - INTERVAL $days DAY)";
    $rangeLabel = 'Last ' . $days . ' days';
}

$partnerCut = "oi.qty * IF(o.supplier_type='partner', p.partner_profit, 0)";

$profit = db()->query("
    SELECT
      IFNULL(SUM(oi.line_total),0) AS revenue,
      IFNULL(SUM(oi.qty * p.cost),0) AS cogs,
      IFNULL(SUM($partnerCut),0) AS partner_payout,
      IFNULL(SUM(oi.qty),0) AS units
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE $valid AND $range
")->fetch();
$profit['revenue'] = (float) $profit['revenue'];
$profit['cogs'] = (float) $profit['cogs'];
$profit['partner_payout'] = (float) $profit['partner_payout'];
$profit['units'] = (int) $profit['units'];
$profit['gross_profit'] = round($profit['revenue'] - $profit['cogs'], 2);
$profit['net_profit'] = round($profit['gross_profit'] - $profit['partner_payout'], 2);
$profit['margin'] = $profit['revenue'] > 0 ? round($profit['net_profit'] / $profit['revenue'] * 100, 1) : 0;
$profit['net_per_bottle'] = $profit['units'] > 0 ? round($profit['net_profit'] / $profit['units'], 2) : 0;
$profit['gross_per_bottle'] = $profit['units'] > 0 ? round($profit['gross_profit'] / $profit['units'], 2) : 0;

$byProduct = db()->query("
    SELECT p.name, p.sku, p.cost, p.price, p.partner_profit,
           SUM(oi.qty) AS units,
           SUM(oi.line_total) AS revenue,
           SUM(oi.qty * p.cost) AS cogs,
           SUM($partnerCut) AS partner_payout
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE $valid AND $range
    GROUP BY p.id ORDER BY revenue DESC
")->fetchAll();
foreach ($byProduct as &$r) {
    $r['units'] = (int) $r['units'];
    $r['revenue'] = (float) $r['revenue'];
    $r['cogs'] = (float) $r['cogs'];
    $r['partner_payout'] = (float) $r['partner_payout'];
    $r['profit'] = round($r['revenue'] - $r['cogs'] - $r['partner_payout'], 2);
    $r['per_bottle'] = $r['units'] > 0 ? round($r['profit'] / $r['units'], 2) : 0;
    $r['margin'] = $r['revenue'] > 0 ? round($r['profit'] / $r['revenue'] * 100, 1) : 0;
}
unset($r);

$trend = db()->query("
    SELECT DATE(o.created_at) AS d,
           IFNULL(SUM(oi.line_total),0) AS revenue,
           IFNULL(SUM(oi.qty * p.cost),0) AS cogs,
           IFNULL(SUM(oi.line_total) - SUM(oi.qty * p.cost) - SUM($partnerCut),0) AS profit
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE $valid AND $range
    GROUP BY DATE(o.created_at) ORDER BY d ASC
")->fetchAll();

$counts = db()->query("
    SELECT buyer_store_id, COUNT(*) AS c
    FROM orders o WHERE $valid AND $range GROUP BY buyer_store_id
")->fetchAll();
$oneTime = 0;
$repeat = 0;
$totalBuyers = count($counts);
foreach ($counts as $c) {
    if ((int) $c['c'] >= 2) {
        $repeat++;
    } else {
        $oneTime++;
    }
}
$repeatRate = $totalBuyers > 0 ? round($repeat / $totalBuyers * 100, 1) : 0;

$newCustomers = db()->query("
    SELECT DATE_FORMAT(f, '%Y-%m') AS m, COUNT(*) AS n FROM (
      SELECT buyer_store_id, MIN(created_at) AS f
      FROM orders o WHERE $valid GROUP BY buyer_store_id
    ) t
    WHERE $range2
    GROUP BY m ORDER BY m ASC
")->fetchAll();

$topRetainers = db()->query("
    SELECT s.name, s.district, COUNT(o.id) AS orders, IFNULL(SUM(o.total),0) AS revenue,
           MAX(o.created_at) AS last_order
    FROM orders o JOIN stores s ON s.id = o.buyer_store_id
    WHERE $valid AND $range GROUP BY s.id HAVING orders >= 2 ORDER BY orders DESC, revenue DESC LIMIT 10
")->fetchAll();

$inv = db()->query('
    SELECT
      IFNULL(SUM(cs.quantity * p.cost),0) AS cost_value,
      IFNULL(SUM(cs.quantity * p.price),0) AS retail_value,
      IFNULL(SUM(cs.quantity),0) AS units
    FROM company_stock cs JOIN products p ON p.id = cs.product_id
')->fetch();

$storeInv = db()->query('
    SELECT IFNULL(SUM(ss.quantity * p.price),0) AS retail_value, IFNULL(SUM(ss.quantity),0) AS units
    FROM store_stock ss JOIN products p ON p.id = ss.product_id
')->fetch();

$lowStock = db()->query('
    SELECT p.name, p.sku, COALESCE(cs.quantity,0) AS qty
    FROM products p LEFT JOIN company_stock cs ON cs.product_id = p.id
    WHERE p.is_active = 1 AND COALESCE(cs.quantity,0) < 500
    ORDER BY qty ASC LIMIT 20
')->fetchAll();

$sold90 = (int) db()->query("
    SELECT IFNULL(SUM(oi.qty),0)
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE $valid AND o.created_at > (NOW() - INTERVAL 90 DAY)
")->fetchColumn();
$turnover = (int) $inv['units'] > 0 ? round($sold90 / (int) $inv['units'], 2) : 0;

$recent = array_slice($trend, -14);
$avgDaily = count($recent) ? array_sum(array_map(fn ($r) => (float) $r['revenue'], $recent)) / count($recent) : 0;
$forecast30 = round($avgDaily * 30, 2);

json_out([
    'ok' => true,
    'days' => $days,
    'range_label' => $rangeLabel,
    'profit' => $profit,
    'by_product' => $byProduct,
    'trend' => $trend,
    'retention' => [
        'buyers' => $totalBuyers,
        'repeat' => $repeat,
        'one_time' => $oneTime,
        'repeat_rate' => $repeatRate,
    ],
    'new_customers' => $newCustomers,
    'top_retainers' => $topRetainers,
    'inventory' => [
        'company_cost_value' => (float) $inv['cost_value'],
        'company_retail_value' => (float) $inv['retail_value'],
        'company_units' => (int) $inv['units'],
        'store_retail_value' => (float) $storeInv['retail_value'],
        'store_units' => (int) $storeInv['units'],
        'turnover_90d' => $turnover,
    ],
    'low_stock' => $lowStock,
    'forecast_30d' => $forecast30,
]);
