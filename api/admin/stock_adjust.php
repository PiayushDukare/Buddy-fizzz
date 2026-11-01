<?php
require __DIR__ . '/../config.php';
$admin = require_role('admin');
require_method('POST');
verify_csrf();

$data = body();
$pid = (int) field($data, 'product_id', 0);
$mode = (string) field($data, 'mode', 'add');
$qty = (int) field($data, 'quantity', 0);

if ($pid < 1 || !in_array($mode, ['add', 'set'], true)) {
    json_error('Invalid request', 422);
}

$chk = db()->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
$chk->execute([$pid]);
if (!$chk->fetch()) {
    json_error('Product not found', 404);
}

try {
    db()->beginTransaction();
    db()->prepare('INSERT IGNORE INTO company_stock (product_id, quantity) VALUES (?, 0)')->execute([$pid]);
    $cur = (int) db()->query('SELECT quantity FROM company_stock WHERE product_id = ' . $pid . ' FOR UPDATE')->fetchColumn();

    if ($mode === 'set') {
        if ($qty < 0) {
            db()->rollBack();
            json_error('Quantity cannot be negative', 422);
        }
        $delta = $qty - $cur;
        db()->prepare('UPDATE company_stock SET quantity = ? WHERE product_id = ?')->execute([$qty, $pid]);
    } else {
        $delta = $qty;
        db()->prepare('UPDATE company_stock SET quantity = quantity + ? WHERE product_id = ?')->execute([$qty, $pid]);
    }

    if ($delta !== 0) {
        db()->prepare('INSERT INTO stock_movements (store_id, product_id, change_qty, reason, created_by) VALUES (NULL, ?, ?, ?, ?)')
            ->execute([$pid, $delta, 'production', $admin['id']]);
    }
    db()->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    json_error('Could not update stock', 500);
}

$newQty = (int) db()->query('SELECT quantity FROM company_stock WHERE product_id = ' . $pid)->fetchColumn();
log_activity('company_stock', 'product', $pid, ['mode' => $mode, 'quantity' => $qty, 'new' => $newQty]);
json_out(['ok' => true, 'message' => 'Company stock updated', 'quantity' => $newQty]);
