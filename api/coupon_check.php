<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/orders.php';
require_role('retailer');
require_method('POST');
verify_csrf();

$data = body();
$code = (string) field($data, 'code', '');
$items = $data['items'] ?? [];

$qtyByPid = [];
foreach ((array) $items as $it) {
    $qtyByPid[(int) ($it['product_id'] ?? 0)] = (int) ($it['qty'] ?? 0);
}
[$lines, $subtotal] = priced_lines($qtyByPid);
if (!$lines) {
    json_error('Your cart is empty', 422);
}

$cp = validate_coupon($code, $subtotal);
if (!$cp['ok']) {
    json_error($cp['error'], 422);
}

json_out([
    'ok' => true,
    'code' => $cp['code'],
    'discount' => $cp['discount'],
    'subtotal' => $subtotal,
]);
