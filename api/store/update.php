<?php
require __DIR__ . '/../config.php';
$user = require_role('retailer', 'partner');
require_method('POST');
verify_csrf();

$store = my_store((int) $user['id']);
if (!$store) {
    json_error('No store linked to your account', 400);
}

$data = body();
$gstin = strtoupper((string) field($data, 'gstin', ''));
$phone = (string) field($data, 'phone', '');
$address = (string) field($data, 'address', '');

if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
    json_error('GSTIN must be 15 characters (letters & numbers)', 422);
}

db()->prepare('UPDATE stores SET gstin = ?, phone = ?, address = ? WHERE id = ?')
    ->execute([$gstin ?: null, $phone ?: null, $address ?: null, $store['id']]);

log_activity('update_store', 'store', (int) $store['id']);
json_out(['ok' => true, 'message' => 'Business details updated', 'gstin' => $gstin]);
