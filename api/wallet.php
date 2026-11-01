<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/wallet.php';
$user = require_role('retailer');
require_method('GET');

$store = my_store((int) $user['id']);
if (!$store) {
    json_out([
        'ok' => true,
        'balance' => 0,
        'credit_allowed' => 0,
        'credit_limit' => 0,
        'outstanding' => 0,
        'razorpay' => RAZORPAY_ENABLED,
        'transactions' => [],
    ]);
}

$tx = db()->prepare('SELECT amount, balance_after, type, order_id, note, created_at FROM wallet_transactions WHERE store_id = ? ORDER BY id DESC LIMIT 50');
$tx->execute([(int) $store['id']]);

json_out([
    'ok' => true,
    'balance' => (float) $store['wallet_balance'],
    'credit_allowed' => (int) $store['credit_allowed'],
    'credit_limit' => (float) $store['credit_limit'],
    'outstanding' => store_outstanding((int) $store['id']),
    'razorpay' => RAZORPAY_ENABLED,
    'transactions' => $tx->fetchAll(),
]);
