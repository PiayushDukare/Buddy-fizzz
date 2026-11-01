<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/razorpay.php';
$user = require_role('retailer');
require_method('POST');
verify_csrf();

if (!RAZORPAY_ENABLED || RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    json_error('Online top-up is not enabled yet', 400);
}

$amount = round((float) field(body(), 'amount', 0), 2);
if ($amount < 1) {
    json_error('Enter an amount of at least ₹1', 422);
}
if ($amount > 200000) {
    json_error('Maximum top-up is ₹2,00,000', 422);
}

$store = my_store((int) $user['id']);
if (!$store) {
    json_error('No store linked to your account', 400);
}

$amountPaise = (int) round($amount * 100);
[$code, $rz, $err] = razorpay_api('POST', 'orders', [
    'amount' => $amountPaise,
    'currency' => 'INR',
    'receipt' => 'wallet-' . $store['id'] . '-' . time(),
    'notes' => ['type' => 'wallet_topup', 'store_id' => (string) $store['id']],
]);
if ($code >= 300 || empty($rz['id'])) {
    log_error('Razorpay wallet topup create error (' . $code . '): ' . ($err ?: json_encode($rz)));
    json_error($rz['error']['description'] ?? 'Could not start the top-up', 502);
}

db()->prepare('DELETE FROM wallet_topups WHERE created_at < (NOW() - INTERVAL 1 DAY)')->execute();
db()->prepare('INSERT INTO wallet_topups (store_id, razorpay_order_id, amount) VALUES (?, ?, ?)')
    ->execute([(int) $store['id'], $rz['id'], $amount]);

json_out([
    'ok' => true,
    'key_id' => RAZORPAY_KEY_ID,
    'razorpay_order_id' => $rz['id'],
    'amount' => $amountPaise,
    'currency' => 'INR',
    'name' => (string) get_setting('company_name', 'Buddy Fizz'),
    'description' => 'Wallet top-up',
    'prefill' => ['name' => $user['name'], 'email' => $user['email'], 'contact' => $user['phone'] ?? ''],
]);
