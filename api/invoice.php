<?php
require __DIR__ . '/config.php';
$user = require_login();
require_method('GET');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid invoice');
}

$stmt = db()->prepare(
    'SELECT o.*, s.name AS store_name, s.owner_name, s.phone AS store_phone, s.email AS store_email,
            s.gstin AS store_gstin, s.address, s.village, s.taluka, s.district, s.state, s.pincode, s.user_id AS store_user_id
     FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$o = $stmt->fetch();
if (!$o) {
    http_response_code(404);
    exit('Order not found');
}

$allowed = $user['role'] === 'admin'
    || ($user['role'] === 'partner' && ((int) $o['supplier_id'] === (int) $user['id'] || (int) $o['store_user_id'] === (int) $user['id']))
    || ($user['role'] === 'retailer' && (int) $o['store_user_id'] === (int) $user['id']);
if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$items = db()->prepare('SELECT oi.qty, oi.unit_price, oi.line_total, p.name, p.sku, p.size, p.image FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$items->execute([$id]);
$items = $items->fetchAll();

$company = [
    'name' => get_setting('company_name', 'Buddy Fizz'),
    'address' => get_setting('company_address', ''),
    'phone' => get_setting('company_phone', ''),
    'email' => get_setting('company_email', ''),
    'gstin' => get_setting('company_gstin', ''),
    'state' => get_setting('company_state', ''),
];
$prefix = get_setting('invoice_prefix', 'BF');
$invNo = $o['invoice_no'] ?: ($prefix . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT));

$gst = (float) $o['gst_amount'];
$cess = (float) $o['cess_amount'];
$intra = $company['state'] !== '' && strcasecmp(trim((string) $o['state']), trim($company['state'])) === 0;
$due = (float) $o['total'] - (float) $o['amount_paid'];
$rupee = fn($n) => '&#8377; ' . number_format((float) $n, 2);
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$billLoc = implode(', ', array_filter([$o['address'], $o['village'], $o['taluka'], $o['district'], $o['state'], $o['pincode']]));
$appBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$appBase = preg_replace('#/api$#', '', $appBase);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Invoice <?= $e($invNo) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Poppins", sans-serif; background: #eef2ea; color: #1f2a22; padding: 2rem 1rem; }
    .sheet { max-width: 48rem; margin: 0 auto; background: #fff; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(20,58,36,.12); overflow: hidden; }
    .inv-head { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 2rem 2.2rem; background: linear-gradient(120deg, #143a24, #2e7d32); color: #fff; }
    .inv-logo { font-family: "Baloo 2", cursive; font-weight: 800; font-size: 2rem; line-height: .85; }
    .inv-logo span { color: #a4d233; }
    .inv-head p { font-size: .82rem; color: #cfe6d0; margin-top: .5rem; max-width: 20rem; line-height: 1.5; }
    .inv-meta { text-align: right; }
    .inv-meta h1 { font-family: "Baloo 2", cursive; font-size: 1.8rem; letter-spacing: .05em; }
    .inv-meta div { font-size: .82rem; color: #cfe6d0; margin-top: .3rem; }
    .inv-body { padding: 2rem 2.2rem; }
    .parties { display: flex; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.6rem; }
    .party h3 { font-size: .72rem; text-transform: uppercase; letter-spacing: .1em; color: #6b7a6e; margin-bottom: .4rem; }
    .party b { font-size: 1rem; color: #143a24; }
    .party p { font-size: .84rem; color: #4a5a4e; line-height: 1.5; max-width: 18rem; margin-top: .2rem; }
    table { width: 100%; border-collapse: collapse; margin-top: .5rem; }
    thead th { background: #f2f6ec; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7a6e; text-align: left; padding: .7rem .8rem; }
    thead th.r, tbody td.r { text-align: right; }
    tbody td { padding: .8rem .8rem; border-bottom: 1px solid #eef2ea; font-size: .88rem; vertical-align: middle; }
    .prod { display: flex; align-items: center; gap: .7rem; }
    .prod img { width: 2.4rem; height: 3rem; object-fit: contain; background: #f7faf2; border-radius: .4rem; }
    .prod small { color: #6b7a6e; display: block; font-size: .74rem; }
    .totals { margin-left: auto; margin-top: 1.2rem; width: min(100%, 20rem); }
    .totals div { display: flex; justify-content: space-between; padding: .35rem 0; font-size: .88rem; }
    .totals .grand { border-top: 2px solid #143a24; margin-top: .4rem; padding-top: .6rem; font-weight: 700; font-size: 1.1rem; color: #143a24; }
    .totals .muted { color: #6b7a6e; }
    .paid { display: inline-flex; align-items: center; gap: .3rem; font-size: .74rem; font-weight: 700; padding: .2rem .6rem; border-radius: 999px; text-transform: capitalize; }
    .paid-paid { background: #dff3df; color: #1f7a34; } .paid-partial { background: #fff3d6; color: #a86800; } .paid-unpaid { background: #fbe0e0; color: #b02a2a; }
    .inv-foot { padding: 1.4rem 2.2rem; border-top: 1px solid #eef2ea; font-size: .78rem; color: #6b7a6e; display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .bar { text-align: center; margin: 1.5rem auto 0; max-width: 48rem; }
    .btn { background: #143a24; color: #fff; border: none; border-radius: .6rem; padding: .7rem 1.4rem; font-family: inherit; font-weight: 600; cursor: pointer; font-size: .9rem; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; border-radius: 0; max-width: 100%; } .bar { display: none; } }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="inv-head">
      <div>
        <div class="inv-logo">Buddy<span>Fizz</span></div>
        <p><?= $e($company['address']) ?><?php if ($company['phone']): ?><br><?= $e($company['phone']) ?><?php endif; ?><?php if ($company['gstin']): ?><br>GSTIN: <?= $e($company['gstin']) ?><?php endif; ?></p>
      </div>
      <div class="inv-meta">
        <h1>INVOICE</h1>
        <div>No: <?= $e($invNo) ?></div>
        <div>Date: <?= date('d M Y', strtotime($o['created_at'])) ?></div>
      </div>
    </div>
    <div class="inv-body">
      <div class="parties">
        <div class="party">
          <h3>Billed To</h3>
          <b><?= $e($o['store_name']) ?></b>
          <p><?php if ($o['owner_name']): ?><?= $e($o['owner_name']) ?><br><?php endif; ?><?= $e($billLoc) ?><?php if ($o['store_phone']): ?><br><?= $e($o['store_phone']) ?><?php endif; ?><?php if (!empty($o['store_gstin'])): ?><br>GSTIN: <?= $e($o['store_gstin']) ?><?php endif; ?></p>
        </div>
        <div class="party" style="text-align:right">
          <h3>Payment</h3>
          <span class="paid paid-<?= $e($o['payment_status']) ?>"><?= $e($o['payment_status']) ?></span>
          <p>Paid: <?= $rupee($o['amount_paid']) ?><br>Balance: <?= $rupee($due) ?></p>
        </div>
      </div>
      <table>
        <thead><tr><th>Product</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
          <tr>
            <td><div class="prod"><?php if ($it['image']): ?><img src="<?= $e($appBase . '/' . $it['image']) ?>" alt=""><?php endif; ?><div><b><?= $e($it['name']) ?></b><small><?= $e($it['sku']) ?> · <?= $e($it['size']) ?></small></div></div></td>
            <td class="r"><?= (int) $it['qty'] ?></td>
            <td class="r"><?= $rupee($it['unit_price']) ?></td>
            <td class="r"><?= $rupee($it['line_total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="totals">
        <div><span class="muted">Subtotal</span><span><?= $rupee($o['subtotal']) ?></span></div>
        <?php if ((float) ($o['discount_amount'] ?? 0) > 0): ?>
          <div><span class="muted">Discount<?= $o['coupon_code'] ? ' (' . $e($o['coupon_code']) . ')' : '' ?></span><span>− <?= $rupee($o['discount_amount']) ?></span></div>
        <?php endif; ?>
        <?php if ($gst > 0): ?>
          <?php if ($intra): ?>
            <div><span class="muted">CGST @ <?= rtrim(rtrim(number_format($o['gst_rate'] / 2, 2), '0'), '.') ?>%</span><span><?= $rupee($gst / 2) ?></span></div>
            <div><span class="muted">SGST @ <?= rtrim(rtrim(number_format($o['gst_rate'] / 2, 2), '0'), '.') ?>%</span><span><?= $rupee($gst / 2) ?></span></div>
          <?php else: ?>
            <div><span class="muted">IGST @ <?= rtrim(rtrim(number_format($o['gst_rate'], 2), '0'), '.') ?>%</span><span><?= $rupee($gst) ?></span></div>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($cess > 0): ?>
          <div><span class="muted">Cess @ <?= rtrim(rtrim(number_format($o['cess_rate'], 2), '0'), '.') ?>%</span><span><?= $rupee($cess) ?></span></div>
        <?php endif; ?>
        <div class="grand"><span>Total</span><span><?= $rupee($o['total']) ?></span></div>
      </div>
    </div>
    <div class="inv-foot">
      <span>Thank you for your business! · <?= $e($company['email']) ?></span>
      <span>This is a computer-generated invoice.</span>
    </div>
  </div>
  <div class="bar"><button class="btn" onclick="window.print()">🖨️ Print / Save as PDF</button></div>
</body>
</html>
