<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');

$company = [
    'name' => get_setting('company_name', 'Buddy Fizz'),
    'gstin' => get_setting('company_gstin', ''),
    'state' => get_setting('company_state', ''),
];

$stmt = db()->prepare(
    "SELECT o.invoice_no, o.id, o.created_at, o.subtotal, o.discount_amount, o.gst_rate, o.gst_amount, o.cess_amount,
            s.name AS store_name, s.gstin AS store_gstin, s.state AS store_state
     FROM orders o JOIN stores s ON s.id = o.buyer_store_id
     WHERE o.status NOT IN ('cancelled','rejected') AND DATE(o.created_at) BETWEEN ? AND ?
     ORDER BY o.created_at ASC"
);
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

$data = [];
$tot = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'cess' => 0, 'total' => 0];
foreach ($rows as $o) {
    $taxable = round((float) $o['subtotal'] - (float) $o['discount_amount'], 2);
    $gst = (float) $o['gst_amount'];
    $cess = (float) $o['cess_amount'];
    $intra = $company['state'] !== '' && strcasecmp(trim((string) $o['store_state']), trim($company['state'])) === 0;
    $cgst = $intra ? round($gst / 2, 2) : 0;
    $sgst = $intra ? round($gst / 2, 2) : 0;
    $igst = $intra ? 0 : $gst;
    $line = [
        'invoice' => $o['invoice_no'] ?: ('#' . $o['id']),
        'date' => date('d M Y', strtotime($o['created_at'])),
        'store' => $o['store_name'],
        'gstin' => $o['store_gstin'] ?: '—',
        'taxable' => $taxable,
        'cgst' => $cgst,
        'sgst' => $sgst,
        'igst' => $igst,
        'cess' => $cess,
        'total' => round($taxable + $gst + $cess, 2),
    ];
    $data[] = $line;
    $tot['taxable'] += $taxable;
    $tot['cgst'] += $cgst;
    $tot['sgst'] += $sgst;
    $tot['igst'] += $igst;
    $tot['cess'] += $cess;
    $tot['total'] += $line['total'];
}

$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$n = fn ($x) => number_format((float) $x, 2);

if (($_GET['csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gst-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice', 'Date', 'Buyer', 'GSTIN', 'Taxable', 'CGST', 'SGST', 'IGST', 'Cess', 'Total']);
    foreach ($data as $d) {
        fputcsv($out, [$d['invoice'], $d['date'], $d['store'], $d['gstin'], $d['taxable'], $d['cgst'], $d['sgst'], $d['igst'], $d['cess'], $d['total']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['TOTALS', '', '', '', $tot['taxable'], $tot['cgst'], $tot['sgst'], $tot['igst'], $tot['cess'], $tot['total']]);
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GST Report <?= $e($from) ?> to <?= $e($to) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Poppins", sans-serif; background: #eef2ea; color: #1f2a22; padding: 2rem 1rem; }
    .sheet { max-width: 62rem; margin: 0 auto; background: #fff; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(20,58,36,.12); overflow: hidden; }
    .head { padding: 1.8rem 2.2rem; background: linear-gradient(120deg,#143a24,#2e7d32); color: #fff; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
    .logo { font-family: "Baloo 2", cursive; font-weight: 800; font-size: 1.8rem; } .logo span { color: #a4d233; }
    .head .meta { text-align: right; font-size: .85rem; color: #cfe6d0; }
    .head .meta h1 { font-family: "Baloo 2", cursive; color: #fff; font-size: 1.5rem; }
    .body { padding: 1.4rem 2.2rem; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: .8rem; min-width: 48rem; }
    thead th { background: #f2f6ec; color: #6b7a6e; text-transform: uppercase; font-size: .68rem; letter-spacing: .04em; text-align: right; padding: .5rem .6rem; }
    thead th:first-child, thead th:nth-child(2), thead th:nth-child(3), thead th:nth-child(4) { text-align: left; }
    tbody td { padding: .5rem .6rem; border-bottom: 1px solid #eef2ea; text-align: right; }
    tbody td:first-child, tbody td:nth-child(2), tbody td:nth-child(3), tbody td:nth-child(4) { text-align: left; }
    tfoot td { padding: .7rem .6rem; font-weight: 700; color: #143a24; border-top: 2px solid #143a24; text-align: right; }
    tfoot td:first-child { text-align: left; }
    .bar { text-align: center; margin: 1.4rem auto 0; display: flex; gap: .6rem; justify-content: center; }
    .btn { background: #143a24; color: #fff; border: none; border-radius: .6rem; padding: .7rem 1.3rem; font-family: inherit; font-weight: 600; cursor: pointer; font-size: .88rem; text-decoration: none; }
    .btn--csv { background: #2e7d32; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; border-radius: 0; } .bar { display: none; } .body { overflow: visible; } }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="head">
      <div><div class="logo">Buddy<span>Fizz</span></div><div style="font-size:.82rem;color:#cfe6d0;margin-top:.4rem"><?= $e($company['name']) ?><?php if ($company['gstin']): ?> · GSTIN <?= $e($company['gstin']) ?><?php endif; ?></div></div>
      <div class="meta"><h1>GST REPORT</h1><div><?= date('d M Y', strtotime($from)) ?> — <?= date('d M Y', strtotime($to)) ?></div><div><?= count($data) ?> invoices</div></div>
    </div>
    <div class="body">
      <table>
        <thead><tr><th>Invoice</th><th>Date</th><th>Buyer</th><th>GSTIN</th><th>Taxable</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Cess</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($data as $d): ?>
          <tr><td><?= $e($d['invoice']) ?></td><td><?= $e($d['date']) ?></td><td><?= $e($d['store']) ?></td><td><?= $e($d['gstin']) ?></td>
            <td><?= $n($d['taxable']) ?></td><td><?= $n($d['cgst']) ?></td><td><?= $n($d['sgst']) ?></td><td><?= $n($d['igst']) ?></td><td><?= $n($d['cess']) ?></td><td><?= $n($d['total']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$data): ?><tr><td colspan="10" style="text-align:center;color:#6b7a6e;padding:1.5rem">No invoices in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="4">TOTALS</td><td><?= $n($tot['taxable']) ?></td><td><?= $n($tot['cgst']) ?></td><td><?= $n($tot['sgst']) ?></td><td><?= $n($tot['igst']) ?></td><td><?= $n($tot['cess']) ?></td><td><?= $n($tot['total']) ?></td></tr></tfoot>
      </table>
    </div>
  </div>
  <div class="bar">
    <button class="btn" onclick="window.print()">🖨️ Print / Save PDF</button>
    <a class="btn btn--csv" href="?from=<?= $e($from) ?>&to=<?= $e($to) ?>&csv=1">⬇ Download CSV</a>
  </div>
</body>
</html>
