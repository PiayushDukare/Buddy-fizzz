<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/wallet.php';
$user = require_role('retailer');
require_method('POST');
verify_csrf();

if (!RAZORPAY_ENABLED || RAZORPAY_KEY_SECRET === '') {
    json_error('Online payment is not enabled', 400);
}

$store = my_store((int) $user['id']);
if (!$store) {
    json_error('No store found for your account', 404);
}

$d = body();
$rzpPaymentId = (string) field($d, 'razorpay_payment_id', '');
$rzpOrderId = (string) field($d, 'razorpay_order_id', '');
$signature = (string) field($d, 'razorpay_signature', '');

if ($rzpPaymentId === '' || $rzpOrderId === '' || $signature === '') {
    json_error('Missing payment details', 422);
}

$due = db()->prepare('SELECT * FROM due_payments WHERE razorpay_order_id = ? AND store_id = ? LIMIT 1');
$due->execute([$rzpOrderId, (int) $store['id']]);
$due = $due->fetch();
if (!$due) {
    json_error('Payment reference not found', 404);
}
if ($due['status'] === 'paid') {
    json_out(['ok' => true, 'message' => 'Payment already recorded']);
}

$expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $signature)) {
    log_error('Razorpay dues signature mismatch for store ' . $store['id']);
    json_error('Payment could not be verified. If money was deducted it will be refunded.', 400);
}

$remaining = (float) $due['amount'];
$paidOrders = [];

db()->beginTransaction();
try {
    $rows = db()->prepare(
        "SELECT id, total, amount_paid, supplier_type, supplier_id
         FROM orders
         WHERE buyer_store_id = ? AND status NOT IN ('cancelled','rejected') AND (total - amount_paid) > 0.001
         ORDER BY created_at ASC, id ASC FOR UPDATE"
    );
    $rows->execute([(int) $store['id']]);

    $upd = db()->prepare('UPDATE orders SET amount_paid = ?, payment_status = ?, payment_method = ?, razorpay_payment_id = ? WHERE id = ?');
    foreach ($rows->fetchAll() as $o) {
        if ($remaining <= 0.001) {
            break;
        }
        $balance = round((float) $o['total'] - (float) $o['amount_paid'], 2);
        $pay = min($remaining, $balance);
        $newPaid = round((float) $o['amount_paid'] + $pay, 2);
        $status = ($newPaid + 0.001) >= (float) $o['total'] ? 'paid' : 'partial';
        $upd->execute([$newPaid, $status, 'razorpay', $rzpPaymentId, (int) $o['id']]);
        $remaining = round($remaining - $pay, 2);
        $paidOrders[] = (int) $o['id'];
    }

    if ($remaining > 0.001) {
        wallet_apply((int) $store['id'], $remaining, 'topup', null, 'Overpayment from dues settlement', (int) $user['id']);
    }

    db()->prepare("UPDATE due_payments SET status = 'paid', razorpay_payment_id = ? WHERE id = ?")
        ->execute([$rzpPaymentId, (int) $due['id']]);

    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    log_error('Dues settlement failed for store ' . $store['id'], $e);
    json_error('Payment verified but settling your orders failed. Our team will reconcile it shortly.', 500);
}

db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
    ->execute([(int) $user['id'], 'payment', 'Dues paid', 'Your payment of ' . money_txt($due['amount']) . ' cleared ' . count($paidOrders) . ' order(s).', 'retailer/']);

log_activity('dues_confirm', 'store', (int) $store['id'], ['rzp_payment' => $rzpPaymentId, 'amount' => $due['amount'], 'orders' => $paidOrders]);

json_out([
    'ok' => true,
    'message' => 'Payment successful — ' . count($paidOrders) . ' order(s) settled.',
    'orders_paid' => count($paidOrders),
]);
