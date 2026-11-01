<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/razorpay.php';
$user = require_role('retailer');
require_method('POST');
verify_csrf();

if (!RAZORPAY_ENABLED || RAZORPAY_KEY_SECRET === '') {
    json_error('Online payment is not enabled', 400);
}

$d = body();
$rzpPaymentId = (string) field($d, 'razorpay_payment_id', '');
$rzpOrderId = (string) field($d, 'razorpay_order_id', '');
$signature = (string) field($d, 'razorpay_signature', '');
if ($rzpPaymentId === '' || $rzpOrderId === '' || $signature === '') {
    json_error('Missing payment details', 422);
}


$exists = db()->prepare('SELECT id FROM orders WHERE razorpay_order_id = ? LIMIT 1');
$exists->execute([$rzpOrderId]);
if ($row = $exists->fetch()) {
    json_out(['ok' => true, 'message' => 'Payment successful! Your order is placed.', 'order_id' => (int) $row['id']]);
}

if (!razorpay_verify_signature($rzpOrderId, $rzpPaymentId, $signature)) {
    log_error('Razorpay confirm signature mismatch: ' . $rzpOrderId);
    json_error('Payment could not be verified. If money was deducted it will be auto-refunded.', 400);
}

$p = db()->prepare('SELECT * FROM pending_orders WHERE razorpay_order_id = ? AND user_id = ? LIMIT 1');
$p->execute([$rzpOrderId, (int) $user['id']]);
$pending = $p->fetch();
if (!$pending) {
    razorpay_refund($rzpPaymentId);
    json_error('Your checkout session expired, so the payment was refunded. Please try again.', 409);
}

$res = finalize_pending_order($pending, $rzpPaymentId, (int) $user['id']);
if (!$res['ok']) {
    json_out(['ok' => false, 'error' => $res['error'], 'shortfalls' => $res['shortfalls'] ?? null], 409);
}

log_activity('create_order_paid', 'order', $res['order_id'], ['total' => $res['total'], 'rzp_payment' => $rzpPaymentId]);
json_out(['ok' => true, 'message' => 'Payment successful! Your order is placed.', 'order_id' => $res['order_id'], 'total' => $res['total']], 201);
