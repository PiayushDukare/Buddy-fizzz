<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
if ($id < 1) {
    json_error('Store not specified', 422);
}

$stmt = db()->prepare('SELECT s.id, u.role FROM stores s JOIN users u ON u.id = s.user_id WHERE s.id = ? LIMIT 1');
$stmt->execute([$id]);
$store = $stmt->fetch();
if (!$store) {
    json_error('Store not found', 404);
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

$assign = $data['partner_id'] ?? null;
$partnerId = null;
$reassign = false;
if ($store['role'] === 'retailer' && $assign !== null) {
    $reassign = true;
    if ($assign !== 'company' && $assign !== '' && (int) $assign > 0) {
        $partnerId = (int) $assign;
        $chk = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'partner' AND status = 'active' LIMIT 1");
        $chk->execute([$partnerId]);
        if (!$chk->fetch()) {
            json_error('Selected partner is invalid', 422);
        }
    }
}

if ($reassign) {
    db()->prepare('UPDATE stores SET gstin = ?, owner_name = ?, phone = ?, address = ?, credit_allowed = ?, credit_limit = ?, partner_id = ? WHERE id = ?')
        ->execute([$gstin ?: null, $owner ?: null, $phone ?: null, $address ?: null, $creditAllowed, $creditLimit, $partnerId, $id]);
} else {
    db()->prepare('UPDATE stores SET gstin = ?, owner_name = ?, phone = ?, address = ?, credit_allowed = ?, credit_limit = ? WHERE id = ?')
        ->execute([$gstin ?: null, $owner ?: null, $phone ?: null, $address ?: null, $creditAllowed, $creditLimit, $id]);
}

log_activity('update_store', 'store', $id, $reassign ? ['partner_id' => $partnerId] : null);
json_out(['ok' => true, 'message' => 'Business details saved']);
