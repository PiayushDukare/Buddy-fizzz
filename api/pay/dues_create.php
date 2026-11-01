<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/wallet.php';
$user = require_role('retailer');
require_method('POST');
verify_csrf();

if (!RAZORPAY_ENABLED || RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    json_error('Online payment is not enabled yet', 400);
}

$store = my_store((int) $user['id']);
if (!$store) {
    json_error('No store found for your account', 404);
}

$outstanding = store_outstanding((int) $store['id']);
if ($outstanding <= 0) {
    json_error('You have no outstanding dues 🎉', 400);
}

$amountPaise = (int) round($outstanding * 100);

$payload = json_encode([
    'amount' => $amountPaise,
    'currency' => 'INR',
    'receipt' => 'dues-' . $store['id'] . '-' . time(),
    'notes' => ['store_id' => (string) $store['id'], 'store' => $store['name'], 'type' => 'dues'],
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_TIMEOUT => 20,
]);
$res = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($res === false) {
    log_error('Razorpay dues create unreachable: ' . $curlErr);
    json_error('Payment gateway is unreachable. Please try again.', 502);
}
$rz = json_decode($res, true);
if ($httpCode >= 300 || empty($rz['id'])) {
    log_error('Razorpay dues create error (' . $httpCode . '): ' . $res);
    json_error($rz['error']['description'] ?? 'Could not start the payment', 502);
}

db()->prepare('INSERT INTO due_payments (store_id, razorpay_order_id, amount) VALUES (?, ?, ?)')
    ->execute([(int) $store['id'], $rz['id'], $outstanding]);
log_activity('dues_create', 'store', (int) $store['id'], ['amount' => $outstanding, 'rzp_order' => $rz['id']]);

json_out([
    'ok' => true,
    'key_id' => RAZORPAY_KEY_ID,
    'razorpay_order_id' => $rz['id'],
    'amount' => $amountPaise,
    'currency' => 'INR',
    'name' => (string) get_setting('company_name', 'Buddy Fizz'),
    'description' => 'Outstanding dues settlement',
    'prefill' => [
        'name' => $user['name'],
        'email' => $user['email'],
        'contact' => $user['phone'] ?? '',
    ],
]);
