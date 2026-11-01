<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$files = ['app' => 'app_error.log', 'php' => 'php_error.log'];

$key = $_GET['file'] ?? 'app';
if (!isset($files[$key])) {
    $key = 'app';
}
$max = max(50, min((int) ($_GET['lines'] ?? 300), 2000));
$path = LOG_DIR . '/' . $files[$key];

$lines = [];
if (is_file($path)) {
    $f = @fopen($path, 'rb');
    if ($f) {
        $buffer = '';
        $pos = filesize($path);
        $chunk = 8192;
        while ($pos > 0 && substr_count($buffer, "\n") <= $max + 1) {
            $read = (int) min($chunk, $pos);
            $pos -= $read;
            fseek($f, $pos);
            $buffer = fread($f, $read) . $buffer;
        }
        fclose($f);
        $all = preg_split('/\r?\n/', rtrim($buffer, "\r\n"));
        $lines = array_slice(array_filter($all, fn ($l) => trim($l) !== ''), -$max);
        $lines = array_values(array_reverse($lines));
    }
}

$meta = [];
foreach ($files as $k => $fname) {
    $p = LOG_DIR . '/' . $fname;
    $meta[$k] = [
        'exists' => is_file($p),
        'size' => is_file($p) ? filesize($p) : 0,
        'modified' => is_file($p) ? date('c', filemtime($p)) : null,
    ];
}

json_out(['ok' => true, 'file' => $key, 'lines' => $lines, 'meta' => $meta]);
