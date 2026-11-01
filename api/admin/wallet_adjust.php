<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/wallet.php';
$user = require_role('admin', 'partner');
require_method('POST');
verify_csrf();

$data = body();
$storeId = (int) field($data, 'store_id', 0);
$amount = round((float) field($data, 'amount', 0), 2);
$note = (string) field($data, 'note', '');

$s = db()->prepare('SELECT id, partner_id, user_id, name FROM stores WHERE id = ? LIMIT 1');
$s->execute([$storeId]);
$store = $s->fetch();
if (!$store) {
    json_error('Store not found', 404);
}
if ($user['role'] === 'partner' && (int) $store['partner_id'] !== (int) $user['id']) {
    json_error('That store is not one of your retailers', 403);
}
if ($amount === 0.0) {
    json_error('Enter a non-zero amount (use a minus sign to deduct)', 422);
}

try {
    db()->beginTransaction();
    $balance = wallet_apply($storeId, $amount, 'adjustment', null, $note !== '' ? $note : ($amount > 0 ? 'Credit by ' . $user['name'] : 'Debit by ' . $user['name']), (int) $user['id']);
    db()->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    json_error('That would make the wallet negative.', 422);
}

if ($store['user_id']) {
    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([(int) $store['user_id'], 'wallet', 'Wallet updated', ($amount > 0 ? '₹' . number_format($amount, 2) . ' added to' : '₹' . number_format(abs($amount), 2) . ' deducted from') . ' your wallet.', 'retailer/']);
}

log_activity('wallet_adjust', 'store', $storeId, ['amount' => $amount]);
json_out(['ok' => true, 'message' => 'Wallet updated. New balance ₹' . number_format($balance, 2), 'balance' => $balance]);
