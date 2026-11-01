<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$files = ['app' => 'app_error.log', 'php' => 'php_error.log'];
$key = (string) field(body(), 'file', 'app');
if (!isset($files[$key])) {
    json_error('Unknown log', 422);
}

$path = LOG_DIR . '/' . $files[$key];
if (is_file($path)) {
    @file_put_contents($path, '');
}

log_activity('clear_log', 'log', null, ['file' => $key]);
json_out(['ok' => true, 'message' => ucfirst($key) . ' log cleared']);
