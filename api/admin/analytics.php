<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$days = (int) ($_GET['days'] ?? 30);
$days = max(7, min($days, 365));
$valid = "status NOT IN ('cancelled','rejected')";

$kpi = db()->query("
    SELECT
      (SELECT IFNULL(SUM(total),0) FROM orders WHERE $valid) AS revenue,
      (SELECT IFNULL(SUM(amount_paid),0) FROM orders WHERE $valid) AS collected,
      (SELECT IFNULL(SUM(total-amount_paid),0) FROM orders WHERE $valid) AS outstanding,
      (SELECT COUNT(*) FROM orders WHERE $valid) AS orders,
      (SELECT IFNULL(AVG(total),0) FROM orders WHERE $valid) AS aov,
      (SELECT COUNT(*) FROM orders WHERE status='delivered') AS delivered,
      (SELECT COUNT(*) FROM orders WHERE status='pending') AS pending
")->fetch();

$revenueTrend = db()->prepare("
    SELECT DATE(created_at) AS d, IFNULL(SUM(total),0) AS revenue, IFNULL(SUM(amount_paid),0) AS collected, COUNT(*) AS orders
    FROM orders WHERE $valid AND created_at > (NOW() - INTERVAL ? DAY)
    GROUP BY DATE(created_at) ORDER BY d ASC
");
$revenueTrend->execute([$days]);

$funnel = [];
foreach (['pending', 'approved', 'processing', 'dispatched', 'delivered'] as $st) {
    $c = db()->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
    $c->execute([$st]);
    $funnel[] = ['status' => $st, 'n' => (int) $c->fetchColumn()];
}

$topRetailers = db()->query("
    SELECT s.name, s.district, COUNT(o.id) AS orders, IFNULL(SUM(o.total),0) AS revenue
    FROM orders o JOIN stores s ON s.id = o.buyer_store_id
    WHERE o.status NOT IN ('cancelled','rejected')
    GROUP BY s.id ORDER BY revenue DESC LIMIT 10
")->fetchAll();

$bySupplier = db()->query("
    SELECT supplier_type, IFNULL(SUM(total),0) AS revenue, COUNT(*) AS orders
    FROM orders WHERE status NOT IN ('cancelled','rejected') GROUP BY supplier_type
")->fetchAll();

$paymentSplit = db()->query("
    SELECT payment_status, COUNT(*) AS n, IFNULL(SUM(total-amount_paid),0) AS due
    FROM orders WHERE status NOT IN ('cancelled','rejected') GROUP BY payment_status
")->fetchAll();

$appConversion = db()->query("
    SELECT status, COUNT(*) AS n FROM applications GROUP BY status
")->fetchAll();

$subGrowth = db()->prepare("
    SELECT DATE(created_at) AS d, COUNT(*) AS n FROM subscribers
    WHERE created_at > (NOW() - INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY d ASC
");
$subGrowth->execute([$days]);

$dues = db()->query("
    SELECT o.id, o.total, o.amount_paid, (o.total-o.amount_paid) AS due, o.created_at, s.name AS store_name, s.district
    FROM orders o JOIN stores s ON s.id = o.buyer_store_id
    WHERE o.status NOT IN ('cancelled','rejected') AND (o.total-o.amount_paid) > 0
    ORDER BY due DESC LIMIT 30
")->fetchAll();

json_out([
    'ok' => true,
    'kpi' => $kpi,
    'revenue_trend' => $revenueTrend->fetchAll(),
    'funnel' => $funnel,
    'top_retailers' => $topRetailers,
    'by_supplier' => $bySupplier,
    'payment_split' => $paymentSplit,
    'app_conversion' => $appConversion,
    'sub_growth' => $subGrowth->fetchAll(),
    'dues' => $dues,
]);
