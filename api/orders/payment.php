<?php
require __DIR__ . '/../config.php';
$user = require_login();
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
$status = (string) field($data, 'status', '');
if ($id <= 0 || !in_array($status, ['unpaid', 'partial', 'paid'], true)) {
    json_error('Invalid request', 422);
}

$stmt = db()->prepare('SELECT o.*, s.user_id AS store_user_id FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE o.id = ? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    json_error('Order not found', 404);
}

$allowed = $user['role'] === 'admin' || ($user['role'] === 'partner' && (int) $order['supplier_id'] === (int) $user['id']);
if (!$allowed) {
    json_error('Forbidden', 403);
}

$total = (float) $order['total'];
if ($status === 'paid') {
    $paid = $total;
} elseif ($status === 'unpaid') {
    $paid = 0;
} else {
    $paid = min(max((float) field($data, 'amount', 0), 0), $total);
    $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
}

db()->prepare('UPDATE orders SET amount_paid = ?, payment_status = ? WHERE id = ?')->execute([$paid, $status, $id]);

db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
    ->execute([$order['store_user_id'], 'payment', 'Payment updated for Order #' . $id, 'Payment marked ' . $status, 'retailer/']);

log_activity('order_payment', 'order', $id, ['status' => $status, 'amount_paid' => $paid]);

$buyer = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
$buyer->execute([(int) $order['store_user_id']]);
$buyer = $buyer->fetch();
if ($buyer && $buyer['email']) {
    $ref = $order['invoice_no'] ?: ('#' . $id);
    $balance = $total - $paid;
    $blurb = $status === 'paid'
        ? '<p>We\'ve received your payment in full for order <b>' . htmlspecialchars($ref, ENT_QUOTES) . '</b>. Thank you! 🎉</p>'
        : ($status === 'partial'
            ? '<p>A partial payment of <b>' . money_txt($paid) . '</b> has been recorded for order <b>' . htmlspecialchars($ref, ENT_QUOTES) . '</b>. Outstanding balance: <b>' . money_txt($balance) . '</b>.</p>'
            : '<p>Your order <b>' . htmlspecialchars($ref, ENT_QUOTES) . '</b> is currently marked <b>unpaid</b>.</p>');
    $intro = '<p>Hi ' . htmlspecialchars($buyer['name'], ENT_QUOTES) . ',</p>' . $blurb;
    $inlineImgs = [];
    $summary = order_summary_html($id, $inlineImgs);
    send_mail(
        [[$buyer['email'], $buyer['name']]],
        ($status === 'paid' ? 'Payment received' : 'Payment update') . ' — ' . $ref,
        mail_template($status === 'paid' ? 'Payment received' : 'Payment update', $intro . $summary . '<p style="margin-top:20px">' . mail_button('View invoice & orders', abs_url('login.html')) . '</p>'),
        ['context' => 'payment', 'sent_by' => $user['id'], 'inline' => $inlineImgs]
    );
}

json_out(['ok' => true, 'message' => 'Payment marked ' . $status, 'payment_status' => $status]);
