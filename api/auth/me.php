<?php
require __DIR__ . '/../config.php';

$user = current_user();
if (!$user) {
    json_out(['ok' => true, 'authenticated' => false]);
}

$store = null;
if (in_array($user['role'], ['retailer', 'partner'], true)) {
    $s = my_store((int) $user['id']);
    if ($s) {
        $store = [
            'id' => (int) $s['id'],
            'name' => $s['name'],
            'gstin' => $s['gstin'],
            'phone' => $s['phone'],
            'address' => $s['address'],
        ];
    }
}

json_out([
    'ok' => true,
    'authenticated' => true,
    'user' => $user,
    'store' => $store,
    'pay' => ['razorpay' => RAZORPAY_ENABLED],
    'tax' => ['gst' => (float) get_setting('gst_percent', 0), 'cess' => (float) get_setting('cess_percent', 0)],
    'csrf' => csrf_token(),
]);
