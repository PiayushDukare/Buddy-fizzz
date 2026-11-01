<?php
require __DIR__ . '/../config.php';
$user = require_role('retailer');
require_method('POST');
verify_csrf();

if (!RAZORPAY_ENABLED || RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    json_error('Online payment is not enabled yet', 400);
}

$orderId = (int) field(body(), 'order_id', 0);
if ($orderId < 1) {
    json_error('Order not specified', 422);
}

$stmt = db()->prepare(
    'SELECT o.*, s.user_id AS store_user_id, s.name AS store_name
     FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    json_error('Order not found', 404);
}
if ((int) $order['store_user_id'] !== (int) $user['id']) {
    json_error('You can only pay for your own orders', 403);
}
if ($order['payment_status'] === 'paid') {
    json_error('This order is already paid', 409);
}

$balance = round((float) $order['total'] - (float) $order['amount_paid'], 2);
if ($balance <= 0) {
    json_error('There is nothing to pay on this order', 400);
}
$amountPaise = (int) round($balance * 100);

$payload = json_encode([
    'amount' => $amountPaise,
    'currency' => 'INR',
    'receipt' => (string) ($order['invoice_no'] ?: ('order-' . $orderId)),
    'notes' => ['order_id' => (string) $orderId, 'store' => $order['store_name']],
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
    log_error('Razorpay create unreachable: ' . $curlErr);
    json_error('Payment gateway is unreachable. Please try again.', 502);
}
$rz = json_decode($res, true);
if ($httpCode >= 300 || empty($rz['id'])) {
    log_error('Razorpay create error (' . $httpCode . '): ' . $res);
    json_error($rz['error']['description'] ?? 'Could not start the payment', 502);
}

db()->prepare('UPDATE orders SET razorpay_order_id = ? WHERE id = ?')->execute([$rz['id'], $orderId]);
log_activity('pay_create', 'order', $orderId, ['amount' => $balance, 'rzp_order' => $rz['id']]);

json_out([
    'ok' => true,
    'key_id' => RAZORPAY_KEY_ID,
    'razorpay_order_id' => $rz['id'],
    'amount' => $amountPaise,
    'currency' => 'INR',
    'name' => (string) get_setting('company_name', 'Buddy Fizz'),
    'description' => 'Invoice ' . ($order['invoice_no'] ?: ('#' . $orderId)),
    'prefill' => [
        'name' => $user['name'],
        'email' => $user['email'],
        'contact' => $user['phone'] ?? '',
    ],
]);
