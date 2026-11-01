<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$allowed = [
    'company_name', 'company_address', 'company_phone', 'company_email',
    'company_gstin', 'company_state', 'gst_percent', 'cess_percent',
    'invoice_prefix', 'order_min_qty',
];

$data = body();
$stmt = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');

foreach ($allowed as $key) {
    if (array_key_exists($key, $data)) {
        $val = (string) field($data, $key, '');
        if (in_array($key, ['gst_percent', 'cess_percent'], true)) {
            $val = (string) max(0, min((float) $val, 100));
        }
        $stmt->execute([$key, $val]);
    }
}

log_activity('update_settings');
json_out(['ok' => true, 'message' => 'Settings saved']);
