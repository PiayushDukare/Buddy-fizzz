<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$level = $_GET['level'] ?? 'district';
$cols = ['state' => 'state', 'district' => 'district', 'taluka' => 'taluka', 'village' => 'village', 'pincode' => 'pincode'];
$col = $cols[$level] ?? 'district';

$valid = "o.status NOT IN ('cancelled','rejected')";

$kpi = db()->query("
    SELECT
      (SELECT COUNT(*) FROM orders) AS total_orders,
      (SELECT IFNULL(SUM(total),0) FROM orders o WHERE $valid) AS revenue,
      (SELECT IFNULL(SUM(gst_amount + cess_amount),0) FROM orders o WHERE $valid) AS tax_collected,
      (SELECT IFNULL(SUM(amount_paid),0) FROM orders o WHERE $valid) AS collected,
      (SELECT IFNULL(SUM(total - amount_paid),0) FROM orders o WHERE $valid) AS outstanding,
      (SELECT COUNT(*) FROM orders WHERE status='pending') AS pending_orders,
      (SELECT COUNT(*) FROM users WHERE role='retailer' AND status='active') AS retailers,
      (SELECT COUNT(*) FROM users WHERE role='partner' AND status='active') AS partners,
      (SELECT COUNT(*) FROM applications WHERE status='pending') AS pending_apps,
      (SELECT COUNT(*) FROM tickets WHERE status IN ('open','pending')) AS open_tickets,
      (SELECT COUNT(*) FROM leads WHERE status='new') AS new_leads,
      (SELECT COUNT(*) FROM termination_requests WHERE status='pending') AS pending_terminations
")->fetch();

$region = db()->prepare("
    SELECT COALESCE(NULLIF(s.$col,''),'—') AS area,
           COUNT(o.id) AS orders,
           IFNULL(SUM(o.total),0) AS value
    FROM orders o JOIN stores s ON s.id = o.buyer_store_id
    WHERE $valid
    GROUP BY area ORDER BY value DESC LIMIT 20
");
$region->execute();

$topProducts = db()->query("
    SELECT p.name, SUM(oi.qty) AS units, SUM(oi.line_total) AS value
    FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
    WHERE o.status NOT IN ('cancelled','rejected')
    GROUP BY p.id ORDER BY units DESC LIMIT 8
")->fetchAll();

$byStatus = db()->query("SELECT status, COUNT(*) AS n FROM orders GROUP BY status")->fetchAll();

$heat = db()->query("
    SELECT COALESCE(NULLIF(s.district,''),'—') AS area, p.name AS product, SUM(oi.qty) AS units
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN stores s ON s.id = o.buyer_store_id
    JOIN products p ON p.id = oi.product_id
    WHERE o.status NOT IN ('cancelled','rejected')
    GROUP BY area, p.id ORDER BY area ASC
")->fetchAll();

$scatter = db()->query("
    SELECT s.name AS store, COUNT(o.id) AS orders, IFNULL(SUM(o.total),0) AS value
    FROM stores s LEFT JOIN orders o ON o.buyer_store_id = s.id AND o.status NOT IN ('cancelled','rejected')
    WHERE s.status = 'active'
    GROUP BY s.id HAVING orders > 0 ORDER BY value DESC LIMIT 60
")->fetchAll();

$trend = db()->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS orders, IFNULL(SUM(total),0) AS value
    FROM orders WHERE created_at > (NOW() - INTERVAL 14 DAY)
    GROUP BY DATE(created_at) ORDER BY d ASC
")->fetchAll();

json_out([
    'ok' => true,
    'kpi' => $kpi,
    'level' => $level,
    'region' => $region->fetchAll(),
    'top_products' => $topProducts,
    'by_status' => $byStatus,
    'trend' => $trend,
    'heat' => $heat,
    'scatter' => $scatter,
]);
