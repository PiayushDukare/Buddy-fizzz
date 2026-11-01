<?php
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/razorpay.php';
$user = require_role('retailer');
require_method('POST');
verify_csrf();

if (!RAZORPAY_ENABLED || RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    json_error('Online payment is not enabled yet', 400);
}

$data = body();
$items = $data['items'] ?? [];
$notes = (string) field($data, 'notes', '');
if (!is_array($items) || count($items) === 0) {
    json_error('Your order is empty', 422);
}

$store = my_store((int) $user['id']);
if (!$store) {
    json_error('No store is linked to your account', 400);
}
$supplierType = 'company';
$supplierId = null;
if ($user['role'] === 'retailer' && !empty($store['partner_id'])) {
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

$short = stock_shortfalls($supplierType, $supplierStoreId, $lines);
if ($short) {
    json_out(['ok' => false, 'error' => 'Some items are out of stock: ' . shortfall_text($short), 'shortfalls' => $short], 409);
}

$couponCode = (string) field($data, 'coupon_code', '');
$cp = validate_coupon($couponCode, $subtotal);
if (!$cp['ok']) {
    json_error($cp['error'], 422);
}
$totals = compute_totals($subtotal, (float) $cp['discount']);
$amountPaise = (int) round($totals['total'] * 100);

[$code, $rz, $err] = razorpay_api('POST', 'orders', [
    'amount' => $amountPaise,
    'currency' => 'INR',
    'receipt' => 'cart-' . $user['id'] . '-' . time(),
    'notes' => ['user_id' => (string) $user['id'], 'store' => $store['name']],
]);
if ($code >= 300 || empty($rz['id'])) {
    log_error('Razorpay checkout create error (' . $code . '): ' . ($err ?: json_encode($rz)));
    json_error($rz['error']['description'] ?? 'Could not start the payment', 502);
}


$deliveryDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) field($data, 'delivery_date', '')) ? field($data, 'delivery_date', '') : null;
db()->prepare('DELETE FROM pending_orders WHERE created_at < (NOW() - INTERVAL 1 DAY)')->execute();
db()->prepare(
    'INSERT INTO pending_orders (user_id, razorpay_order_id, buyer_store_id, supplier_type, supplier_id, cart, amount, coupon_code, delivery_date, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    (int) $user['id'], $rz['id'], (int) $store['id'], $supplierType, $supplierId,
    json_encode($qtyByPid), $totals['total'], $cp['code'], $deliveryDate, $notes ?: null,
]);

json_out([
    'ok' => true,
    'key_id' => RAZORPAY_KEY_ID,
    'razorpay_order_id' => $rz['id'],
    'amount' => $amountPaise,
    'currency' => 'INR',
    'name' => (string) get_setting('company_name', 'Buddy Fizz'),
    'description' => 'Order payment',
    'prefill' => ['name' => $user['name'], 'email' => $user['email'], 'contact' => $user['phone'] ?? ''],
]);
