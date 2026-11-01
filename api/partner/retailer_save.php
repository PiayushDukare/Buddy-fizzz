<?php
require __DIR__ . '/../config.php';
$user = require_role('partner');
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
if ($id < 1) {
    json_error('Retailer not specified', 422);
}

$stmt = db()->prepare('SELECT id FROM stores WHERE id = ? AND partner_id = ? LIMIT 1');
$stmt->execute([$id, $user['id']]);
if (!$stmt->fetch()) {
    json_error('Retailer not found', 404);
}

$gstin = strtoupper((string) field($data, 'gstin', ''));
$owner = (string) field($data, 'owner_name', '');
$phone = (string) field($data, 'phone', '');
$address = (string) field($data, 'address', '');
$creditAllowed = (int) !!field($data, 'credit_allowed', 0);
$creditLimit = round((float) field($data, 'credit_limit', 0), 2);

if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
    json_error('GSTIN must be 15 characters (letters & numbers)', 422);
}

db()->prepare('UPDATE stores SET gstin = ?, owner_name = ?, phone = ?, address = ?, credit_allowed = ?, credit_limit = ? WHERE id = ? AND partner_id = ?')
    ->execute([$gstin ?: null, $owner ?: null, $phone ?: null, $address ?: null, $creditAllowed, $creditLimit, $id, $user['id']]);

log_activity('update_store', 'store', $id);
json_out(['ok' => true, 'message' => 'Business details saved']);
