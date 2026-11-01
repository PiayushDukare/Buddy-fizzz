<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/orders.php';
$user = require_login();
require_method('GET');

$supplierType = 'company';
$supplierId = null;

if ($user['role'] === 'retailer') {
    $store = my_store((int) $user['id']);
    if ($store && !empty($store['partner_id'])) {
        $supplierType = 'partner';
        $supplierId = (int) $store['partner_id'];
    }
} elseif ($user['role'] === 'partner') {
    $forStore = (int) ($_GET['store_id'] ?? 0);
    if ($forStore > 0) {
        
        $chk = db()->prepare('SELECT id FROM stores WHERE id = ? AND partner_id = ? LIMIT 1');
        $chk->execute([$forStore, (int) $user['id']]);
        if ($chk->fetch()) {
            $supplierType = 'partner';
            $supplierId = (int) $user['id'];
        }
    }
    
}

$supplierStoreId = supplier_store_id($supplierType, $supplierId);

json_out([
    'ok' => true,
    'source' => $supplierType,
    'availability' => availability_map($supplierType, $supplierStoreId),
]);
