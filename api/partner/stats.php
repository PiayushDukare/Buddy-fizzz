<?php
require __DIR__ . '/../config.php';
$user = require_role('partner');
require_method('GET');
$me = (int) $user['id'];

$level = $_GET['level'] ?? 'taluka';
$cols = ['state' => 'state', 'district' => 'district', 'taluka' => 'taluka', 'village' => 'village', 'pincode' => 'pincode'];
$col = $cols[$level] ?? 'taluka';
$days = max(7, min((int) ($_GET['days'] ?? 30), 365));

$scope = "o.supplier_id = :me AND o.status NOT IN ('cancelled','rejected')";

$kpi = db()->prepare("
    SELECT
      (SELECT COUNT(*) FROM stores WHERE partner_id = :me) AS retailers,
      (SELECT COUNT(*) FROM orders WHERE supplier_id = :me) AS retailer_orders,
      (SELECT COUNT(*) FROM orders WHERE supplier_id = :me AND status='pending') AS pending_orders,
      (SELECT IFNULL(SUM(total),0) FROM orders o WHERE $scope) AS revenue,
      (SELECT IFNULL(SUM(amount_paid),0) FROM orders o WHERE $scope) AS collected,
      (SELECT IFNULL(SUM(total-amount_paid),0) FROM orders o WHERE $scope) AS outstanding
");
$kpi->execute([':me' => $me]);

$trend = db()->prepare("
    SELECT DATE(o.created_at) AS d, IFNULL(SUM(o.total),0) AS revenue, COUNT(*) AS orders
    FROM orders o WHERE $scope AND o.created_at > (NOW() - INTERVAL :days DAY)
    GROUP BY DATE(o.created_at) ORDER BY d ASC
");
$trend->execute([':me' => $me, ':days' => $days]);

$region = db()->prepare("
    SELECT COALESCE(NULLIF(s.$col,''),'—') AS area, COUNT(o.id) AS orders, IFNULL(SUM(o.total),0) AS value
    FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE $scope
    GROUP BY area ORDER BY value DESC LIMIT 20
");
$region->execute([':me' => $me]);

$funnel = [];
foreach (['pending', 'approved', 'processing', 'dispatched', 'delivered'] as $st) {
    $c = db()->prepare('SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status = ?');
    $c->execute([$me, $st]);
    $funnel[] = ['status' => $st, 'n' => (int) $c->fetchColumn()];
}

$topRetailers = db()->prepare("
    SELECT s.name, s.district, COUNT(o.id) AS orders, IFNULL(SUM(o.total),0) AS revenue
    FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE $scope
    GROUP BY s.id ORDER BY revenue DESC LIMIT 10
");
$topRetailers->execute([':me' => $me]);

$topProducts = db()->prepare("
    SELECT p.name, SUM(oi.qty) AS units, SUM(oi.line_total) AS value
    FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
    WHERE $scope GROUP BY p.id ORDER BY units DESC LIMIT 8
");
$topProducts->execute([':me' => $me]);

$heat = db()->prepare("
    SELECT COALESCE(NULLIF(s.district,''),'—') AS area, p.name AS product, SUM(oi.qty) AS units
    FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN stores s ON s.id = o.buyer_store_id JOIN products p ON p.id = oi.product_id
    WHERE $scope GROUP BY area, p.id ORDER BY area ASC
");
$heat->execute([':me' => $me]);

$paymentSplit = db()->prepare("
    SELECT payment_status, COUNT(*) AS n, IFNULL(SUM(total-amount_paid),0) AS due
    FROM orders o WHERE $scope GROUP BY payment_status
");
$paymentSplit->execute([':me' => $me]);

$dues = db()->prepare("
    SELECT o.id, o.total, o.amount_paid, (o.total-o.amount_paid) AS due, o.created_at, s.name AS store_name, s.district
    FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE $scope AND (o.total-o.amount_paid) > 0
    ORDER BY due DESC LIMIT 20
");
$dues->execute([':me' => $me]);

json_out([
    'ok' => true,
    'level' => $level,
    'kpi' => $kpi->fetch(),
    'trend' => $trend->fetchAll(),
    'region' => $region->fetchAll(),
    'funnel' => $funnel,
    'top_retailers' => $topRetailers->fetchAll(),
    'top_products' => $topProducts->fetchAll(),
    'heat' => $heat->fetchAll(),
    'payment_split' => $paymentSplit->fetchAll(),
    'dues' => $dues->fetchAll(),
]);
