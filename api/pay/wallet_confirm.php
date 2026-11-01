<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/razorpay.php';
require_once __DIR__ . '/../lib/wallet.php';
$user = require_role('retailer');
require_method('POST');
verify_csrf();

if (!RAZORPAY_ENABLED || RAZORPAY_KEY_SECRET === '') {
    json_error('Online top-up is not enabled', 400);
}

$d = body();
$rzpPaymentId = (string) field($d, 'razorpay_payment_id', '');
$rzpOrderId = (string) field($d, 'razorpay_order_id', '');
$signature = (string) field($d, 'razorpay_signature', '');
if ($rzpPaymentId === '' || $rzpOrderId === '' || $signature === '') {
    json_error('Missing payment details', 422);
}

$store = my_store((int) $user['id']);
if (!$store) {
    json_error('No store linked to your account', 400);
}

if (!razorpay_verify_signature($rzpOrderId, $rzpPaymentId, $signature)) {
    log_error('Wallet topup signature mismatch: ' . $rzpOrderId);
    json_error('Payment could not be verified.', 400);
}

$t = db()->prepare('SELECT * FROM wallet_topups WHERE razorpay_order_id = ? AND store_id = ? LIMIT 1');
$t->execute([$rzpOrderId, (int) $store['id']]);
$topup = $t->fetch();
if (!$topup) {
    json_out(['ok' => true, 'message' => 'Top-up already credited', 'balance' => (float) $store['wallet_balance']]);
}

try {
    db()->beginTransaction();
    $balance = wallet_apply((int) $store['id'], (float) $topup['amount'], 'topup', null, 'Wallet top-up (online)', (int) $user['id']);
    db()->prepare('DELETE FROM wallet_topups WHERE id = ?')->execute([(int) $topup['id']]);
    db()->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    log_error('Wallet topup credit failed: ' . $e->getMessage());
    json_error('Top-up could not be completed. Contact support if money was deducted.', 500);
}

log_activity('wallet_topup', 'store', (int) $store['id'], ['amount' => $topup['amount']]);
json_out(['ok' => true, 'message' => '₹' . number_format((float) $topup['amount'], 2) . ' added to your wallet.', 'balance' => $balance]);
