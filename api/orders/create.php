<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/wallet.php';
$user = require_role('retailer', 'partner');
require_method('POST');
verify_csrf();

$data = body();
$items = $data['items'] ?? [];
$notes = (string) field($data, 'notes', '');
$targetStoreId = (int) field($data, 'store_id', 0);

if (!is_array($items) || count($items) === 0) {
    json_error('Your order is empty', 422);
}

$onBehalf = false;
if ($user['role'] === 'partner' && $targetStoreId > 0) {
    $st = db()->prepare('SELECT * FROM stores WHERE id = ? AND partner_id = ? LIMIT 1');
    $st->execute([$targetStoreId, (int) $user['id']]);
    $store = $st->fetch();
    if (!$store) {
        json_error('That retailer is not assigned to you', 403);
    }
    $onBehalf = true;
} else {
    $store = my_store((int) $user['id']);
}
if (!$store) {
    json_error('No store is linked to your account', 400);
}
$buyerUserId = (int) ($store['user_id'] ?? $user['id']);

$supplierType = 'company';
$supplierId = null;
if ($onBehalf) {
    $supplierType = 'partner';
    $supplierId = (int) $user['id'];
} elseif ($user['role'] === 'retailer' && !empty($store['partner_id'])) {
    $supplierType = 'partner';
    $supplierId = (int) $store['partner_id'];
}
$supplierStoreId = supplier_store_id($supplierType, $supplierId);

$qtyByPid = [];
foreach ($items as $it) {
    $qtyByPid[(int) ($it['product_id'] ?? 0)] = (int) ($it['qty'] ?? 0);
}
[$lines, $subtotal] = priced_lines($qtyByPid);
if (!$lines) {
    json_error('None of the selected products are available', 422);
}

$payMethod = (string) field($data, 'pay_method', '');
$paid = false;
$method = 'manual';

if ($user['role'] === 'retailer' && !$onBehalf) {
    $cpPrev = validate_coupon((string) field($data, 'coupon_code', ''), $subtotal);
    if (!$cpPrev['ok']) {
        json_error($cpPrev['error'], 422);
    }
    $previewTotal = compute_totals($subtotal, (float) $cpPrev['discount'])['total'];

    if ($payMethod === 'wallet') {
        if ((float) $store['wallet_balance'] + 0.001 < $previewTotal) {
            json_error('Your wallet balance is not enough for this order.', 402);
        }
        $paid = true;
        $method = 'wallet';
    } elseif ($payMethod === 'credit') {
        if (!(int) $store['credit_allowed']) {
            json_error('Pay-later is not enabled for your store.', 403);
        }
        $limit = (float) $store['credit_limit'];
        if ($limit > 0 && (store_outstanding((int) $store['id']) + $previewTotal) > $limit + 0.001) {
            json_error('This order would exceed your credit limit of ₹' . number_format($limit, 0) . '.', 402);
        }
        $paid = false;
        $method = 'credit';
    } elseif (RAZORPAY_ENABLED) {
        json_error('Please choose how to pay for this order.', 402);
    }
}

$res = place_order([
    'store' => $store,
    'buyerUserId' => $buyerUserId,
    'supplierType' => $supplierType,
    'supplierId' => $supplierId,
    'supplierStoreId' => $supplierStoreId,
    'lines' => $lines,
    'subtotal' => $subtotal,
    'couponCode' => field($data, 'coupon_code', ''),
    'deliveryDate' => field($data, 'delivery_date', ''),
    'notes' => $notes,
    'paid' => $paid,
    'method' => $method,
    'actorId' => (int) $user['id'],
]);

if (!$res['ok']) {
    if (isset($res['coupon_error'])) {
        json_error($res['coupon_error'], 422);
    }
    if (isset($res['wallet_error'])) {
        json_error($res['wallet_error'], 402);
    }
    json_out(['ok' => false, 'error' => 'Some items are out of stock: ' . shortfall_text($res['shortfalls']), 'shortfalls' => $res['shortfalls']], 409);
}

order_placed_side_effects($res['order_id'], $store, $buyerUserId, $supplierType, $supplierId, $onBehalf, (int) $user['id'], $paid);
log_activity('create_order', 'order', $res['order_id'], ['total' => $res['total'], 'supplier' => $supplierType, 'method' => $method]);

$msg = $method === 'wallet'
    ? 'Order placed and paid from your wallet.'
    : ($method === 'credit' ? 'Order placed on credit (pay later).' : 'Order placed successfully');
$walletBalance = $method === 'wallet' ? (float) db()->query('SELECT wallet_balance FROM stores WHERE id = ' . (int) $store['id'])->fetchColumn() : null;

json_out(['ok' => true, 'message' => $msg, 'order_id' => $res['order_id'], 'total' => $res['total'], 'wallet_balance' => $walletBalance], 201);
