<?php
require __DIR__ . '/../config.php';
$user = require_role('retailer');
require_method('POST');
verify_csrf();

if (!RAZORPAY_ENABLED || RAZORPAY_KEY_SECRET === '') {
    json_error('Online payment is not enabled', 400);
}

$d = body();
$orderId = (int) field($d, 'order_id', 0);
$rzpPaymentId = (string) field($d, 'razorpay_payment_id', '');
$rzpOrderId = (string) field($d, 'razorpay_order_id', '');
$signature = (string) field($d, 'razorpay_signature', '');

if ($orderId < 1 || $rzpPaymentId === '' || $rzpOrderId === '' || $signature === '') {
    json_error('Missing payment details', 422);
}

$stmt = db()->prepare(
    'SELECT o.*, s.user_id AS store_user_id FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE o.id = ? LIMIT 1'
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
    json_out(['ok' => true, 'message' => 'Payment already recorded', 'payment_status' => 'paid']);
}
if ((string) $order['razorpay_order_id'] !== $rzpOrderId) {
    json_error('Payment reference mismatch', 422);
}

$expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $signature)) {
    log_error('Razorpay signature mismatch on order ' . $orderId);
    json_error('Payment could not be verified. If money was deducted it will be refunded.', 400);
}

$total = (float) $order['total'];
db()->prepare('UPDATE orders SET amount_paid = ?, payment_status = ?, payment_method = ?, razorpay_payment_id = ? WHERE id = ?')
    ->execute([$total, 'paid', 'razorpay', $rzpPaymentId, $orderId]);

db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
    ->execute([(int) $order['store_user_id'], 'payment', 'Payment received', 'Your online payment for order #' . $orderId . ' was successful.', 'retailer/']);

if ($order['supplier_type'] === 'partner' && $order['supplier_id']) {
    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([(int) $order['supplier_id'], 'payment', 'Order paid online', 'Order #' . $orderId . ' has been paid in full.', 'partner/']);
}

log_activity('pay_verify', 'order', $orderId, ['rzp_payment' => $rzpPaymentId, 'amount' => $total]);

$buyer = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
$buyer->execute([(int) $order['store_user_id']]);
$buyer = $buyer->fetch();
if ($buyer && $buyer['email']) {
    $ref = $order['invoice_no'] ?: ('#' . $orderId);
    $intro = '<p>Hi ' . htmlspecialchars($buyer['name'], ENT_QUOTES) . ',</p><p>We\'ve received your online payment for order <b>' . htmlspecialchars($ref, ENT_QUOTES) . '</b>. Thank you! 🎉</p>';
    $inlineImgs = [];
    $summary = order_summary_html($orderId, $inlineImgs);
    send_mail(
        [[$buyer['email'], $buyer['name']]],
        'Payment received — ' . $ref,
        mail_template('Payment received', $intro . $summary . '<p style="margin-top:20px">' . mail_button('View invoice & orders', abs_url('login.html')) . '</p>'),
        ['context' => 'payment', 'sent_by' => $user['id'], 'inline' => $inlineImgs]
    );
}

json_out(['ok' => true, 'message' => 'Payment successful! Your order is now paid.', 'payment_status' => 'paid']);
