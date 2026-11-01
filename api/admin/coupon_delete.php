<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$id = (int) field(body(), 'id', 0);
if ($id < 1) {
    json_error('Coupon not specified', 422);
}
db()->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
log_activity('delete_coupon', 'coupon', $id);
json_out(['ok' => true, 'message' => 'Coupon deleted']);
