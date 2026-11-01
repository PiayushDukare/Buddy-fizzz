<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$type = $_GET['type'] ?? 'orders';
$type = in_array($type, ['orders', 'retailers', 'dues', 'subscribers'], true) ? $type : 'orders';

$queries = [
    'orders' => "SELECT o.id AS order_id, s.name AS store, s.district, s.state, o.status, o.payment_status,
                 o.total, o.amount_paid, (o.total-o.amount_paid) AS due, o.created_at
                 FROM orders o JOIN stores s ON s.id = o.buyer_store_id ORDER BY o.created_at DESC",
    'retailers' => "SELECT s.name AS store, s.owner_name, u.email, s.phone, s.village, s.taluka, s.district, s.state, s.pincode,
                    p.name AS partner, s.status, s.created_at
                    FROM stores s JOIN users u ON u.id = s.user_id LEFT JOIN users p ON p.id = s.partner_id
                    WHERE u.role='retailer' ORDER BY s.created_at DESC",
    'dues' => "SELECT o.id AS order_id, s.name AS store, s.district, o.total, o.amount_paid, (o.total-o.amount_paid) AS due, o.created_at
               FROM orders o JOIN stores s ON s.id = o.buyer_store_id
               WHERE o.status NOT IN ('cancelled','rejected') AND (o.total-o.amount_paid) > 0 ORDER BY due DESC",
    'subscribers' => "SELECT email, created_at FROM subscribers ORDER BY created_at DESC",
];

$rows = db()->query($queries[$type])->fetchAll();

log_activity('export_csv', $type);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="buddyfizz-' . $type . '-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
} else {
    fputcsv($out, ['No data']);
}
fclose($out);
exit;
