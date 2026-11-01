<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');

$kpi = db()->prepare("
    SELECT COUNT(*) AS orders, IFNULL(SUM(total),0) AS revenue,
           IFNULL(SUM(gst_amount+cess_amount),0) AS tax, IFNULL(SUM(discount_amount),0) AS discount,
           IFNULL(SUM(amount_paid),0) AS collected, IFNULL(SUM(total-amount_paid),0) AS outstanding
    FROM orders WHERE status NOT IN ('cancelled','rejected') AND DATE(created_at) BETWEEN ? AND ?
");
$kpi->execute([$from, $to]);
$k = $kpi->fetch();

$byProduct = db()->prepare("
    SELECT p.name, SUM(oi.qty) AS units, IFNULL(SUM(oi.line_total),0) AS revenue
    FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
    WHERE o.status NOT IN ('cancelled','rejected') AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id ORDER BY revenue DESC
");
$byProduct->execute([$from, $to]);
$byProduct = $byProduct->fetchAll();

$daily = db()->prepare("
    SELECT DATE(created_at) AS d, COUNT(*) AS orders, IFNULL(SUM(total),0) AS revenue, IFNULL(SUM(gst_amount+cess_amount),0) AS tax, IFNULL(SUM(discount_amount),0) AS discount
    FROM orders WHERE status NOT IN ('cancelled','rejected') AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at) ORDER BY d ASC
");
$daily->execute([$from, $to]);
$daily = $daily->fetchAll();

$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$rupee = fn ($x) => '&#8377; ' . number_format((float) $x, 2);

if (($_GET['csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Sales Report', $from . ' to ' . $to]);
    fputcsv($out, []);
    fputcsv($out, ['Orders', $k['orders'], 'Revenue', $k['revenue'], 'Tax', $k['tax'], 'Discount', $k['discount'], 'Collected', $k['collected'], 'Outstanding', $k['outstanding']]);
    fputcsv($out, []);
    fputcsv($out, ['Date', 'Orders', 'Revenue', 'Tax', 'Discount']);
    foreach ($daily as $d) {
        fputcsv($out, [$d['d'], $d['orders'], $d['revenue'], $d['tax'], $d['discount']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Product', 'Units', 'Revenue']);
    foreach ($byProduct as $p) {
        fputcsv($out, [$p['name'], $p['units'], $p['revenue']]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales Report <?= $e($from) ?> to <?= $e($to) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Poppins", sans-serif; background: #eef2ea; color: #1f2a22; padding: 2rem 1rem; }
    .sheet { max-width: 56rem; margin: 0 auto; background: #fff; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(20,58,36,.12); overflow: hidden; }
    .head { padding: 1.8rem 2.2rem; background: linear-gradient(120deg,#143a24,#2e7d32); color: #fff; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
    .logo { font-family: "Baloo 2", cursive; font-weight: 800; font-size: 1.8rem; } .logo span { color: #a4d233; }
    .head .meta { text-align: right; font-size: .85rem; color: #cfe6d0; } .head .meta h1 { font-family: "Baloo 2", cursive; color: #fff; font-size: 1.5rem; }
    .body { padding: 1.4rem 2.2rem; }
    .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: .8rem; }
    .kpi { background: #f7faf2; border-radius: .7rem; padding: .9rem 1rem; } .kpi span { font-size: .72rem; color: #6b7a6e; text-transform: uppercase; letter-spacing: .04em; } .kpi b { display: block; font-size: 1.25rem; color: #143a24; margin-top: .2rem; }
    h3 { font-family: "Baloo 2", cursive; color: #143a24; margin: 1.6rem 0 .6rem; font-size: 1.15rem; }
    table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    thead th { background: #f2f6ec; color: #6b7a6e; text-transform: uppercase; font-size: .68rem; text-align: right; padding: .5rem .7rem; } thead th:first-child { text-align: left; }
    tbody td { padding: .5rem .7rem; border-bottom: 1px solid #eef2ea; text-align: right; } tbody td:first-child { text-align: left; }
    .bar { text-align: center; margin: 1.4rem auto 0; display: flex; gap: .6rem; justify-content: center; }
    .btn { background: #143a24; color: #fff; border: none; border-radius: .6rem; padding: .7rem 1.3rem; font-family: inherit; font-weight: 600; cursor: pointer; font-size: .88rem; text-decoration: none; } .btn--csv { background: #2e7d32; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; border-radius: 0; } .bar { display: none; } }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="head">
      <div><div class="logo">Buddy<span>Fizz</span></div><div style="font-size:.82rem;color:#cfe6d0;margin-top:.4rem"><?= $e(get_setting('company_name', 'Buddy Fizz')) ?></div></div>
      <div class="meta"><h1>SALES REPORT</h1><div><?= date('d M Y', strtotime($from)) ?> — <?= date('d M Y', strtotime($to)) ?></div></div>
    </div>
    <div class="body">
      <div class="cards">
        <div class="kpi"><span>Orders</span><b><?= (int) $k['orders'] ?></b></div>
        <div class="kpi"><span>Revenue</span><b><?= $rupee($k['revenue']) ?></b></div>
        <div class="kpi"><span>Tax Collected</span><b><?= $rupee($k['tax']) ?></b></div>
        <div class="kpi"><span>Discounts Given</span><b><?= $rupee($k['discount']) ?></b></div>
        <div class="kpi"><span>Collected</span><b><?= $rupee($k['collected']) ?></b></div>
        <div class="kpi"><span>Outstanding</span><b><?= $rupee($k['outstanding']) ?></b></div>
      </div>
      <h3>Sales by Product</h3>
      <table><thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead><tbody>
        <?php foreach ($byProduct as $p): ?><tr><td><?= $e($p['name']) ?></td><td><?= (int) $p['units'] ?></td><td><?= $rupee($p['revenue']) ?></td></tr><?php endforeach; ?>
        <?php if (!$byProduct): ?><tr><td colspan="3" style="text-align:center;color:#6b7a6e;padding:1rem">No sales.</td></tr><?php endif; ?>
      </tbody></table>
      <h3>Daily Breakdown</h3>
      <table><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Tax</th><th>Discount</th></tr></thead><tbody>
        <?php foreach ($daily as $d): ?><tr><td><?= date('d M Y', strtotime($d['d'])) ?></td><td><?= (int) $d['orders'] ?></td><td><?= $rupee($d['revenue']) ?></td><td><?= $rupee($d['tax']) ?></td><td><?= $rupee($d['discount']) ?></td></tr><?php endforeach; ?>
        <?php if (!$daily): ?><tr><td colspan="5" style="text-align:center;color:#6b7a6e;padding:1rem">No sales.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
  <div class="bar">
    <button class="btn" onclick="window.print()">🖨️ Print / Save PDF</button>
    <a class="btn btn--csv" href="?from=<?= $e($from) ?>&to=<?= $e($to) ?>&csv=1">⬇ Download CSV</a>
  </div>
</body>
</html>
