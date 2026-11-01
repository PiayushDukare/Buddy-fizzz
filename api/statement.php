<?php
require __DIR__ . '/config.php';
$user = require_login();
require_method('GET');

$storeId = (int) ($_GET['store_id'] ?? 0);
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01', strtotime('-2 months'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');

if ($user['role'] === 'retailer') {
    $store = my_store((int) $user['id']);
} else {
    $s = db()->prepare('SELECT * FROM stores WHERE id = ? LIMIT 1');
    $s->execute([$storeId]);
    $store = $s->fetch();
}
if (!$store) {
    http_response_code(404);
    exit('Store not found');
}
if ($user['role'] === 'partner' && (int) $store['partner_id'] !== (int) $user['id'] && (int) $store['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    exit('Forbidden');
}

$stmt = db()->prepare(
    "SELECT id, invoice_no, status, total, amount_paid, payment_status, created_at
     FROM orders WHERE buyer_store_id = ? AND status NOT IN ('cancelled','rejected')
     AND DATE(created_at) BETWEEN ? AND ? ORDER BY created_at ASC"
);
$stmt->execute([(int) $store['id'], $from, $to]);
$orders = $stmt->fetchAll();

$company = [
    'name' => get_setting('company_name', 'Buddy Fizz'),
    'address' => get_setting('company_address', ''),
    'email' => get_setting('company_email', ''),
    'gstin' => get_setting('company_gstin', ''),
];
$billed = 0.0;
$paid = 0.0;
foreach ($orders as $o) {
    $billed += (float) $o['total'];
    $paid += (float) $o['amount_paid'];
}
$due = $billed - $paid;

$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$rupee = fn ($n) => '&#8377; ' . number_format((float) $n, 2);

if (($_GET['csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="statement-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $store['name']) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice', 'Date', 'Status', 'Total', 'Paid', 'Balance', 'Payment']);
    foreach ($orders as $o) {
        fputcsv($out, [$o['invoice_no'] ?: ('#' . $o['id']), date('Y-m-d', strtotime($o['created_at'])), $o['status'], $o['total'], $o['amount_paid'], (float) $o['total'] - (float) $o['amount_paid'], $o['payment_status']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Totals', '', '', $billed, $paid, $due, '']);
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Statement — <?= $e($store['name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Poppins", sans-serif; background: #eef2ea; color: #1f2a22; padding: 2rem 1rem; }
    .sheet { max-width: 52rem; margin: 0 auto; background: #fff; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(20,58,36,.12); overflow: hidden; }
    .head { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 2rem 2.2rem; background: linear-gradient(120deg,#143a24,#2e7d32); color: #fff; }
    .logo { font-family: "Baloo 2", cursive; font-weight: 800; font-size: 2rem; line-height: .85; }
    .logo span { color: #a4d233; }
    .head p { font-size: .82rem; color: #cfe6d0; margin-top: .5rem; }
    .meta { text-align: right; }
    .meta h1 { font-family: "Baloo 2", cursive; font-size: 1.7rem; letter-spacing: .05em; }
    .meta div { font-size: .82rem; color: #cfe6d0; margin-top: .3rem; }
    .body { padding: 1.6rem 2.2rem; }
    .party b { color: #143a24; font-size: 1rem; }
    .party p { font-size: .84rem; color: #4a5a4e; margin-top: .2rem; }
    table { width: 100%; border-collapse: collapse; margin-top: 1.2rem; }
    thead th { background: #f2f6ec; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7a6e; text-align: left; padding: .6rem .8rem; }
    th.r, td.r { text-align: right; }
    tbody td { padding: .6rem .8rem; border-bottom: 1px solid #eef2ea; font-size: .86rem; }
    .cards { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.4rem; }
    .kpi { flex: 1; min-width: 9rem; background: #f7faf2; border-radius: .7rem; padding: .9rem 1rem; }
    .kpi span { font-size: .74rem; color: #6b7a6e; text-transform: uppercase; letter-spacing: .05em; }
    .kpi b { display: block; font-size: 1.3rem; color: #143a24; margin-top: .2rem; }
    .kpi.due b { color: #b02a2a; }
    .paid { font-size: .72rem; font-weight: 700; padding: .12rem .5rem; border-radius: 999px; text-transform: capitalize; }
    .paid-paid { background: #dff3df; color: #1f7a34; } .paid-partial { background: #fff3d6; color: #a86800; } .paid-unpaid { background: #fbe0e0; color: #b02a2a; }
    .bar { text-align: center; margin: 1.4rem auto 0; max-width: 52rem; display: flex; gap: .6rem; justify-content: center; }
    .btn { background: #143a24; color: #fff; border: none; border-radius: .6rem; padding: .7rem 1.3rem; font-family: inherit; font-weight: 600; cursor: pointer; font-size: .88rem; text-decoration: none; }
    .btn--csv { background: #2e7d32; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; border-radius: 0; } .bar { display: none; } }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="head">
      <div><div class="logo">Buddy<span>Fizz</span></div><p><?= $e($company['address']) ?><?php if ($company['gstin']): ?><br>GSTIN: <?= $e($company['gstin']) ?><?php endif; ?></p></div>
      <div class="meta"><h1>STATEMENT</h1><div><?= date('d M Y', strtotime($from)) ?> — <?= date('d M Y', strtotime($to)) ?></div></div>
    </div>
    <div class="body">
      <div class="party"><b><?= $e($store['name']) ?></b><p><?php if ($store['owner_name']): ?><?= $e($store['owner_name']) ?> · <?php endif; ?><?= $e(implode(', ', array_filter([$store['taluka'], $store['district'], $store['state'], $store['pincode']]))) ?><?php if (!empty($store['gstin'])): ?><br>GSTIN: <?= $e($store['gstin']) ?><?php endif; ?></p></div>
      <table>
        <thead><tr><th>Invoice</th><th>Date</th><th>Status</th><th class="r">Total</th><th class="r">Paid</th><th class="r">Balance</th></tr></thead>
        <tbody>
          <?php if (!$orders): ?><tr><td colspan="6" style="text-align:center;color:#6b7a6e;padding:1.5rem">No orders in this period.</td></tr><?php endif; ?>
          <?php foreach ($orders as $o): $bal = (float) $o['total'] - (float) $o['amount_paid']; ?>
          <tr>
            <td><b><?= $e($o['invoice_no'] ?: ('#' . $o['id'])) ?></b></td>
            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            <td><span class="paid paid-<?= $e($o['payment_status']) ?>"><?= $e($o['payment_status']) ?></span></td>
            <td class="r"><?= $rupee($o['total']) ?></td>
            <td class="r"><?= $rupee($o['amount_paid']) ?></td>
            <td class="r"><?= $rupee($bal) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="cards">
        <div class="kpi"><span>Total Billed</span><b><?= $rupee($billed) ?></b></div>
        <div class="kpi"><span>Total Paid</span><b><?= $rupee($paid) ?></b></div>
        <div class="kpi due"><span>Outstanding</span><b><?= $rupee($due) ?></b></div>
      </div>
    </div>
  </div>
  <div class="bar">
    <button class="btn" onclick="window.print()">🖨️ Print / Save PDF</button>
    <a class="btn btn--csv" href="?store_id=<?= (int) $store['id'] ?>&from=<?= $e($from) ?>&to=<?= $e($to) ?>&csv=1">⬇ Download CSV</a>
  </div>
</body>
</html>
