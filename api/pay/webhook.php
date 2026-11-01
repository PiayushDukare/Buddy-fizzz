<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/razorpay.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (RAZORPAY_WEBHOOK_SECRET === '' || $sig === '') {
    http_response_code(400);
    exit;
}
if (!hash_equals(hash_hmac('sha256', $raw, RAZORPAY_WEBHOOK_SECRET), $sig)) {
    log_error('Razorpay webhook signature mismatch');
    http_response_code(400);
    exit;
}

$event = json_decode($raw, true) ?: [];
$type = $event['event'] ?? '';

if (in_array($type, ['payment.captured', 'order.paid'], true)) {
    $payment = $event['payload']['payment']['entity'] ?? [];
    $rzpOrderId = (string) ($payment['order_id'] ?? ($event['payload']['order']['entity']['id'] ?? ''));
    $rzpPaymentId = (string) ($payment['id'] ?? '');

    if ($rzpOrderId !== '' && $rzpPaymentId !== '') {
        $exists = db()->prepare('SELECT id FROM orders WHERE razorpay_order_id = ? LIMIT 1');
        $exists->execute([$rzpOrderId]);
        if (!$exists->fetch()) {
            $p = db()->prepare('SELECT * FROM pending_orders WHERE razorpay_order_id = ? LIMIT 1');
            $p->execute([$rzpOrderId]);
            $pending = $p->fetch();
            if ($pending) {
                $res = finalize_pending_order($pending, $rzpPaymentId, (int) $pending['user_id']);
                log_activity('webhook_order', 'order', $res['order_id'] ?? null, ['event' => $type, 'ok' => !empty($res['ok'])]);
            }
        }
    }
}

http_response_code(200);
echo json_encode(['ok' => true]);
