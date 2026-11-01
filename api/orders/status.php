<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/razorpay.php';
require_once __DIR__ . '/../lib/wallet.php';
$user = require_login();
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
$status = (string) field($data, 'status', '');
$valid = ['pending', 'approved', 'rejected', 'processing', 'dispatched', 'delivered', 'cancelled'];

if ($id <= 0 || !in_array($status, $valid, true)) {
    json_error('Invalid request', 422);
}

$stmt = db()->prepare('SELECT o.*, s.user_id AS store_user_id FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE o.id = ? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    json_error('Order not found', 404);
}

$isSupplier = $user['role'] === 'admin'
    || ($user['role'] === 'partner' && (int) $order['supplier_id'] === (int) $user['id']);
$isBuyer = (int) $order['store_user_id'] === (int) $user['id'];

$reversal = $status === 'cancelled' || $status === 'rejected';

if ($status === 'cancelled') {
    if (!($isSupplier || ($isBuyer && $order['status'] === 'pending'))) {
        json_error('You cannot cancel this order', 403);
    }
} elseif (!$isSupplier) {
    json_error('Forbidden', 403);
}
if ($reversal && $order['status'] === 'delivered') {
    json_error('A delivered order cannot be ' . $status, 409);
}

if ($order['status'] === $status) {
    json_out(['ok' => true, 'message' => 'No change']);
}

$refunded = false;
$lines = db()->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ?');
$lines->execute([$id]);
$lines = $lines->fetchAll();

try {
    db()->beginTransaction();

    if ($status === 'delivered') {
        credit_store_stock((int) $order['buyer_store_id'], $lines, $id, (int) $user['id']);
    } elseif ($reversal) {
        $supplierStoreId = supplier_store_id((string) $order['supplier_type'], $order['supplier_id'] ? (int) $order['supplier_id'] : null);
        apply_supplier_stock(1, (string) $order['supplier_type'], $supplierStoreId, $lines, $id, (int) $user['id'], 'return');
    }

    db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);

    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([$order['store_user_id'], 'order', 'Order #' . $id . ' ' . $status, 'Your order status is now ' . $status, 'retailer/']);

    db()->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    json_error('Could not update order', 500);
}


if ($reversal && $order['payment_status'] === 'paid' && $order['payment_method'] === 'razorpay' && !empty($order['razorpay_payment_id'])) {
    if (RAZORPAY_ENABLED && razorpay_refund((string) $order['razorpay_payment_id'])) {
        db()->prepare("UPDATE orders SET payment_status = 'unpaid', amount_paid = 0 WHERE id = ?")->execute([$id]);
        db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
            ->execute([$order['store_user_id'], 'payment', 'Refund issued', 'Your payment for the ' . $status . ' order #' . $id . ' has been refunded.', 'retailer/']);
        $refunded = true;
    } else {
        log_error('Refund NOT completed for ' . $status . ' paid order ' . $id . ' (razorpay disabled or API error)');
    }
} elseif ($reversal && $order['payment_status'] === 'paid' && $order['payment_method'] === 'wallet') {
    try {
        db()->beginTransaction();
        wallet_apply((int) $order['buyer_store_id'], (float) $order['total'], 'refund', $id, 'Refund for ' . $status . ' order ' . ($order['invoice_no'] ?: ('#' . $id)), (int) $user['id']);
        db()->prepare("UPDATE orders SET payment_status = 'unpaid', amount_paid = 0 WHERE id = ?")->execute([$id]);
        db()->commit();
        db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
            ->execute([$order['store_user_id'], 'payment', 'Refunded to wallet', 'Your wallet was refunded ₹' . number_format((float) $order['total'], 2) . ' for the ' . $status . ' order #' . $id . '.', 'retailer/']);
        $refunded = true;
    } catch (Throwable $we) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        log_error('Wallet refund failed for order ' . $id . ': ' . $we->getMessage());
    }
}

log_activity('order_status', 'order', $id, ['status' => $status, 'refunded' => $refunded]);

$buyer = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
$buyer->execute([(int) $order['store_user_id']]);
$buyer = $buyer->fetch();
if ($buyer && $buyer['email']) {
    $ref = $order['invoice_no'] ?: ('#' . $id);
    $blurb = [
        'approved' => 'Good news — your order has been <b>approved</b> and is being prepared.',
        'processing' => 'Your order is now being <b>processed</b>.',
        'dispatched' => 'Your order has been <b>dispatched</b> and is on its way. 🚚',
        'delivered' => 'Your order has been <b>delivered</b>. Enjoy the fizz! 🥤',
        'cancelled' => 'Your order has been <b>cancelled</b>.' . ($refunded ? ' Your payment has been <b>refunded</b> in full.' : ' Contact us if this was unexpected.'),
        'rejected' => 'Unfortunately your order was <b>rejected</b>. Please reach out for details.',
    ][$status] ?? ('Your order status is now <b>' . htmlspecialchars($status, ENT_QUOTES) . '</b>.');
    $intro = '<p>Hi ' . htmlspecialchars($buyer['name'], ENT_QUOTES) . ',</p><p>' . $blurb . '</p>';
    $inlineImgs = [];
    $summary = order_summary_html($id, $inlineImgs);
    send_mail(
        [[$buyer['email'], $buyer['name']]],
        'Order ' . $ref . ' — ' . ucfirst($status),
        mail_template('Order ' . ucfirst($status), $intro . $summary . '<p style="margin-top:20px">' . mail_button('View your orders', abs_url('login.html')) . '</p>'),
        ['context' => 'order_status', 'sent_by' => $user['id'], 'inline' => $inlineImgs]
    );
}

json_out(['ok' => true, 'message' => 'Order marked ' . $status . ($refunded ? ' and payment refunded' : '')]);
