<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
$code = strtoupper(preg_replace('/\s+/', '', (string) field($data, 'code', '')));
$description = (string) field($data, 'description', '');
$type = (string) field($data, 'type', 'percent');
$value = (float) field($data, 'value', 0);
$minOrder = (float) field($data, 'min_order', 0);
$maxDiscount = field($data, 'max_discount', '') !== '' ? (float) field($data, 'max_discount', 0) : null;
$usageLimit = field($data, 'usage_limit', '') !== '' ? (int) field($data, 'usage_limit', 0) : null;
$expiresAt = (string) field($data, 'expires_at', '');
$active = (int) !!field($data, 'is_active', 1);

if ($code === '') {
    json_error('Coupon code is required', 422);
}
if (!in_array($type, ['percent', 'flat'], true)) {
    json_error('Invalid discount type', 422);
}
if ($value <= 0) {
    json_error('Discount value must be greater than zero', 422);
}
if ($type === 'percent' && $value > 100) {
    json_error('Percentage cannot exceed 100', 422);
}

$dup = db()->prepare('SELECT id FROM coupons WHERE code = ? AND id <> ? LIMIT 1');
$dup->execute([$code, $id]);
if ($dup->fetch()) {
    json_error('Another coupon already uses this code', 409);
}

$exp = $expiresAt !== '' ? $expiresAt : null;

if ($id > 0) {
    db()->prepare('UPDATE coupons SET code=?, description=?, type=?, value=?, min_order=?, max_discount=?, usage_limit=?, expires_at=?, is_active=? WHERE id=?')
        ->execute([$code, $description ?: null, $type, $value, $minOrder, $maxDiscount, $usageLimit, $exp, $active, $id]);
    log_activity('update_coupon', 'coupon', $id);
    json_out(['ok' => true, 'message' => 'Coupon updated']);
}

db()->prepare('INSERT INTO coupons (code, description, type, value, min_order, max_discount, usage_limit, expires_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$code, $description ?: null, $type, $value, $minOrder, $maxDiscount, $usageLimit, $exp, $active]);
log_activity('create_coupon', 'coupon', (int) db()->lastInsertId());
json_out(['ok' => true, 'message' => 'Coupon created'], 201);
