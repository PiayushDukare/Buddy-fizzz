<?php
require __DIR__ . '/../config.php';
$admin = require_role('admin');
require_method('POST');
verify_csrf();

$data = body();
$pid = (int) field($data, 'partner_id', 0);
$note = (string) field($data, 'note', '');
$amountRaw = field($data, 'amount', '');

$chk = db()->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'partner' AND status = 'active' LIMIT 1");
$chk->execute([$pid]);
$partner = $chk->fetch();
if (!$partner) {
    json_error('Partner not found', 404);
}

$earned = (float) db()->query("
    SELECT IFNULL(SUM(oi.qty * p.partner_profit),0)
    FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id
    WHERE o.supplier_id = " . $pid . " AND o.supplier_type = 'partner' AND o.status = 'delivered'
")->fetchColumn();
$settled = (float) db()->query('SELECT IFNULL(SUM(amount),0) FROM partner_settlements WHERE partner_user_id = ' . $pid)->fetchColumn();
$outstanding = round($earned - $settled, 2);

if ($outstanding <= 0) {
    json_error('This partner has nothing pending to settle.', 409);
}

$amount = $amountRaw === '' ? $outstanding : round((float) $amountRaw, 2);
if ($amount <= 0) {
    json_error('Enter a valid amount', 422);
}
if ($amount > $outstanding + 0.001) {
    json_error('Amount exceeds the pending profit of ' . number_format($outstanding, 2), 422);
}

db()->prepare('INSERT INTO partner_settlements (partner_user_id, amount, note, settled_by) VALUES (?, ?, ?, ?)')
    ->execute([$pid, $amount, $note ?: null, $admin['id']]);

db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
    ->execute([$pid, 'payout', 'Profit payout settled', 'A profit payout of ₹' . number_format($amount, 2) . ' has been recorded by the company.', 'partner/']);

log_activity('settle_partner', 'user', $pid, ['amount' => $amount]);

$newOutstanding = round($outstanding - $amount, 2);
json_out(['ok' => true, 'message' => 'Settled ₹' . number_format($amount, 2) . ' to ' . $partner['name'], 'outstanding' => $newOutstanding]);
