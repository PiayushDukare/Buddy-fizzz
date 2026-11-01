<?php


function compute_totals(float $subtotal, float $discount = 0.0): array
{
    $discount = round(max(0, min($discount, $subtotal)), 2);
    $net = round($subtotal - $discount, 2);
    $gstRate = (float) get_setting('gst_percent', 0);
    $cessRate = (float) get_setting('cess_percent', 0);
    $gstAmount = round($net * $gstRate / 100, 2);
    $cessAmount = round($net * $cessRate / 100, 2);
    $total = round($net + $gstAmount + $cessAmount, 2);
    return compact('gstRate', 'gstAmount', 'cessRate', 'cessAmount', 'total', 'discount');
}

function validate_coupon(?string $code, float $subtotal): array
{
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        return ['ok' => true, 'discount' => 0.0, 'code' => null];
    }
    $c = db()->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $c->execute([$code]);
    $c = $c->fetch();
    if (!$c || !(int) $c['is_active']) {
        return ['ok' => false, 'error' => 'This coupon code is not valid.', 'discount' => 0.0];
    }
    if ($c['expires_at'] !== null && $c['expires_at'] < date('Y-m-d')) {
        return ['ok' => false, 'error' => 'This coupon has expired.', 'discount' => 0.0];
    }
    if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
        return ['ok' => false, 'error' => 'This coupon is no longer available.', 'discount' => 0.0];
    }
    if ($subtotal < (float) $c['min_order']) {
        return ['ok' => false, 'error' => 'Add items worth ₹' . number_format((float) $c['min_order'], 0) . '+ to use this coupon.', 'discount' => 0.0];
    }
    $discount = $c['type'] === 'percent' ? $subtotal * (float) $c['value'] / 100 : (float) $c['value'];
    if ($c['max_discount'] !== null) {
        $discount = min($discount, (float) $c['max_discount']);
    }
    $discount = round(min($discount, $subtotal), 2);
    return ['ok' => true, 'discount' => $discount, 'code' => $c['code'], 'coupon_id' => (int) $c['id']];
}


function priced_lines(array $qtyByPid): array
{
    $clean = [];
    foreach ($qtyByPid as $pid => $qty) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        if ($pid > 0 && $qty > 0) {
            $clean[$pid] = ($clean[$pid] ?? 0) + $qty;
        }
    }
    if (!$clean) {
        return [[], 0.0];
    }
    $ids = implode(',', array_map('intval', array_keys($clean)));
    $rows = db()->query("SELECT id, name, price FROM products WHERE is_active = 1 AND id IN ($ids)")->fetchAll(PDO::FETCH_UNIQUE);
    $lines = [];
    $subtotal = 0.0;
    foreach ($clean as $pid => $qty) {
        if (!isset($rows[$pid])) {
            continue;
        }
        $price = (float) $rows[$pid]['price'];
        $lt = round($price * $qty, 2);
        $subtotal += $lt;
        $lines[] = ['product_id' => (int) $pid, 'qty' => (int) $qty, 'name' => $rows[$pid]['name'], 'price' => $price, 'line_total' => $lt];
    }
    return [$lines, round($subtotal, 2)];
}


function supplier_store_id(string $type, ?int $supplierId): ?int
{
    if ($type !== 'partner' || !$supplierId) {
        return null;
    }
    $s = db()->prepare('SELECT id FROM stores WHERE user_id = ? LIMIT 1');
    $s->execute([$supplierId]);
    $id = $s->fetchColumn();
    return $id ? (int) $id : null;
}


function stock_available(string $type, ?int $supplierStoreId, int $productId): int
{
    if ($type === 'partner' && $supplierStoreId) {
        $q = db()->prepare('SELECT quantity FROM store_stock WHERE store_id = ? AND product_id = ?');
        $q->execute([$supplierStoreId, $productId]);
    } elseif ($type === 'company') {
        $q = db()->prepare('SELECT quantity FROM company_stock WHERE product_id = ?');
        $q->execute([$productId]);
    } else {
        return 0;
    }
    $v = $q->fetchColumn();
    return $v === false ? 0 : (int) $v;
}


function availability_map(string $type, ?int $supplierStoreId): array
{
    $out = [];
    if ($type === 'partner' && $supplierStoreId) {
        $rows = db()->prepare('SELECT p.id, COALESCE(ss.quantity,0) AS q FROM products p LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = ? WHERE p.is_active = 1');
        $rows->execute([$supplierStoreId]);
    } else {
        $rows = db()->query('SELECT p.id, COALESCE(cs.quantity,0) AS q FROM products p LEFT JOIN company_stock cs ON cs.product_id = p.id WHERE p.is_active = 1');
    }
    foreach ($rows->fetchAll() as $r) {
        $out[(int) $r['id']] = (int) $r['q'];
    }
    return $out;
}

function stock_shortfalls(string $type, ?int $supplierStoreId, array $lines): array
{
    $short = [];
    foreach ($lines as $l) {
        $have = stock_available($type, $supplierStoreId, (int) $l['product_id']);
        if ($have < (int) $l['qty']) {
            $short[] = ['name' => $l['name'], 'need' => (int) $l['qty'], 'have' => $have];
        }
    }
    return $short;
}

function shortfall_text(array $short): string
{
    return implode('; ', array_map(fn ($s) => $s['name'] . ' (need ' . $s['need'] . ', available ' . $s['have'] . ')', $short));
}

function apply_supplier_stock(int $sign, string $type, ?int $supplierStoreId, array $lines, ?int $orderId, int $actorId, string $reason = 'order'): void
{
    $mov = db()->prepare('INSERT INTO stock_movements (store_id, product_id, change_qty, reason, order_id, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    if ($type === 'partner' && $supplierStoreId) {
        $ensure = db()->prepare('INSERT IGNORE INTO store_stock (store_id, product_id, quantity) VALUES (?, ?, 0)');
        $upd = db()->prepare('UPDATE store_stock SET quantity = quantity + ? WHERE store_id = ? AND product_id = ?');
        foreach ($lines as $l) {
            $delta = $sign * (int) $l['qty'];
            $ensure->execute([$supplierStoreId, (int) $l['product_id']]);
            $upd->execute([$delta, $supplierStoreId, (int) $l['product_id']]);
            $mov->execute([$supplierStoreId, (int) $l['product_id'], $delta, $reason, $orderId, $actorId]);
        }
    } elseif ($type === 'company') {
        $ensure = db()->prepare('INSERT IGNORE INTO company_stock (product_id, quantity) VALUES (?, 0)');
        $upd = db()->prepare('UPDATE company_stock SET quantity = quantity + ? WHERE product_id = ?');
        foreach ($lines as $l) {
            $delta = $sign * (int) $l['qty'];
            $ensure->execute([(int) $l['product_id']]);
            $upd->execute([$delta, (int) $l['product_id']]);
            $mov->execute([null, (int) $l['product_id'], $delta, $reason, $orderId, $actorId]);
        }
    }
}

function credit_store_stock(int $storeId, array $lines, ?int $orderId, int $actorId): void
{
    $upsert = db()->prepare('INSERT INTO store_stock (store_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)');
    $mov = db()->prepare('INSERT INTO stock_movements (store_id, product_id, change_qty, reason, order_id, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($lines as $l) {
        $upsert->execute([$storeId, (int) $l['product_id'], (int) $l['qty']]);
        $mov->execute([$storeId, (int) $l['product_id'], (int) $l['qty'], 'order', $orderId, $actorId]);
    }
}

function notify_low_stock(string $type, ?int $supplierStoreId, array $lines): void
{
    $threshold = (int) get_setting('low_stock_threshold', 500);
    if ($threshold <= 0) {
        return;
    }
    foreach ($lines as $l) {
        $pid = (int) $l['product_id'];
        $qty = (int) $l['qty'];
        $now = stock_available($type, $supplierStoreId, $pid);
        if ($now >= $threshold || ($now + $qty) < $threshold) {
            continue;
        }
        $pname = (string) db()->query('SELECT name FROM products WHERE id = ' . $pid)->fetchColumn();
        if ($type === 'company') {
            $admins = db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
            $ins = db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)');
            foreach ($admins as $a) {
                $ins->execute([(int) $a['id'], 'stock', 'Low company stock', $pname . ' is running low — only ' . $now . ' left in the warehouse.', 'admin/']);
            }
        } elseif ($type === 'partner' && $supplierStoreId) {
            $ownerId = (int) db()->query('SELECT user_id FROM stores WHERE id = ' . $supplierStoreId)->fetchColumn();
            if ($ownerId) {
                db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$ownerId, 'stock', 'Low warehouse stock', $pname . ' is low — only ' . $now . ' left. Reorder from the company soon.', 'partner/']);
            }
        }
    }
}

function place_order(array $c): array
{
    $paid = !empty($c['paid']);

    db()->beginTransaction();
    try {
        $short = stock_shortfalls($c['supplierType'], $c['supplierStoreId'], $c['lines']);
        if ($short) {
            db()->rollBack();
            return ['ok' => false, 'shortfalls' => $short];
        }

        $cp = validate_coupon($c['couponCode'] ?? null, (float) $c['subtotal']);
        if (!$cp['ok']) {
            db()->rollBack();
            return ['ok' => false, 'coupon_error' => $cp['error']];
        }
        $totals = compute_totals((float) $c['subtotal'], (float) $cp['discount']);

        $deliveryDate = (!empty($c['deliveryDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $c['deliveryDate'])) ? $c['deliveryDate'] : null;
        db()->prepare(
            'INSERT INTO orders (buyer_store_id, buyer_user_id, supplier_type, supplier_id, status,
             subtotal, gst_rate, gst_amount, cess_rate, cess_amount, discount_amount, coupon_code, delivery_date, total, amount_paid, payment_status,
             payment_method, razorpay_order_id, razorpay_payment_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $c['store']['id'], $c['buyerUserId'], $c['supplierType'], $c['supplierId'], 'pending',
            $c['subtotal'], $totals['gstRate'], $totals['gstAmount'], $totals['cessRate'], $totals['cessAmount'], $totals['discount'], $cp['code'], $deliveryDate, $totals['total'],
            $paid ? $totals['total'] : 0, $paid ? 'paid' : 'unpaid',
            $c['method'] ?? 'manual', $c['rzpOrderId'] ?? null, $c['rzpPaymentId'] ?? null, ($c['notes'] ?? '') ?: null,
        ]);
        $orderId = (int) db()->lastInsertId();

        if (!empty($cp['code'])) {
            db()->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')->execute([$cp['coupon_id']]);
        }

        $items = db()->prepare('INSERT INTO order_items (order_id, product_id, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?)');
        foreach ($c['lines'] as $l) {
            $items->execute([$orderId, $l['product_id'], $l['qty'], $l['price'], $l['line_total']]);
        }

        apply_supplier_stock(-1, $c['supplierType'], $c['supplierStoreId'], $c['lines'], $orderId, (int) $c['actorId']);
        notify_low_stock($c['supplierType'], $c['supplierStoreId'], $c['lines']);

        $prefix = (string) get_setting('invoice_prefix', 'BF');
        $seq = (int) db()->query("SELECT svalue FROM settings WHERE skey = 'invoice_seq' FOR UPDATE")->fetchColumn();
        $next = $seq + 1;
        db()->prepare("INSERT INTO settings (skey, svalue) VALUES ('invoice_seq', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)")->execute([(string) $next]);
        $mon = (int) date('n');
        $yr = (int) date('Y');
        $fyStart = $mon >= 4 ? $yr : $yr - 1;
        $fy = $fyStart . '-' . substr((string) ($fyStart + 1), -2);
        $invoiceNo = $prefix . '/' . $fy . '/' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        db()->prepare('UPDATE orders SET invoice_no = ? WHERE id = ?')->execute([$invoiceNo, $orderId]);

        if (($c['method'] ?? '') === 'wallet') {
            try {
                wallet_apply((int) $c['store']['id'], -1 * (float) $totals['total'], 'order', $orderId, 'Order ' . $invoiceNo, (int) $c['actorId']);
            } catch (Throwable $we) {
                db()->rollBack();
                return ['ok' => false, 'wallet_error' => 'Your wallet balance is not enough for this order.'];
            }
        }

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }

    return ['ok' => true, 'order_id' => $orderId, 'invoice_no' => $invoiceNo, 'total' => $totals['total']];
}

function finalize_pending_order(array $pending, string $rzpPaymentId, int $actorId): array
{
    $rzpOrderId = (string) $pending['razorpay_order_id'];
    $qtyByPid = json_decode((string) $pending['cart'], true) ?: [];
    [$lines, $subtotal] = priced_lines($qtyByPid);

    $store = db()->prepare('SELECT * FROM stores WHERE id = ? LIMIT 1');
    $store->execute([(int) $pending['buyer_store_id']]);
    $store = $store->fetch();

    if (!$lines || !$store) {
        razorpay_refund($rzpPaymentId);
        db()->prepare('DELETE FROM pending_orders WHERE id = ?')->execute([(int) $pending['id']]);
        return ['ok' => false, 'error' => 'The products are no longer available, so your payment was refunded.', 'refunded' => true];
    }

    $supplierType = (string) $pending['supplier_type'];
    $supplierId = $pending['supplier_id'] ? (int) $pending['supplier_id'] : null;
    $supplierStoreId = supplier_store_id($supplierType, $supplierId);
    $buyerUserId = (int) ($store['user_id'] ?? $pending['user_id']);

    $res = place_order([
        'store' => $store,
        'buyerUserId' => $buyerUserId,
        'supplierType' => $supplierType,
        'supplierId' => $supplierId,
        'supplierStoreId' => $supplierStoreId,
        'lines' => $lines,
        'subtotal' => $subtotal,
        'couponCode' => $pending['coupon_code'] ?? null,
        'deliveryDate' => $pending['delivery_date'] ?? null,
        'notes' => $pending['notes'],
        'paid' => true,
        'method' => 'razorpay',
        'rzpOrderId' => $rzpOrderId,
        'rzpPaymentId' => $rzpPaymentId,
        'actorId' => $actorId,
    ]);

    db()->prepare('DELETE FROM pending_orders WHERE id = ?')->execute([(int) $pending['id']]);

    if (!$res['ok']) {
        razorpay_refund($rzpPaymentId);
        $why = isset($res['shortfalls'])
            ? 'an item went out of stock (' . shortfall_text($res['shortfalls']) . ')'
            : ($res['coupon_error'] ?? 'the order could not be completed');
        return ['ok' => false, 'error' => 'Sorry, ' . $why . ' — your payment was refunded.', 'shortfalls' => $res['shortfalls'] ?? null, 'refunded' => true];
    }

    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([$buyerUserId, 'payment', 'Payment received', 'Your payment for order #' . $res['order_id'] . ' was successful.', 'retailer/']);
    order_placed_side_effects($res['order_id'], $store, $buyerUserId, $supplierType, $supplierId, false, $actorId, true);

    return ['ok' => true, 'order_id' => $res['order_id'], 'total' => $res['total']];
}

function order_placed_side_effects(int $orderId, array $store, int $buyerUserId, string $supplierType, ?int $supplierId, bool $onBehalf, int $actorId, bool $paid = false): void
{
    if ($onBehalf) {
        db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
            ->execute([$buyerUserId, 'order', 'Order placed for your store', 'Your distributor placed an order for ' . $store['name'], 'retailer/']);
    } elseif ($supplierType === 'partner' && $supplierId) {
        db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
            ->execute([$supplierId, 'order', 'New order received', $store['name'] . ' placed an order', 'partner/']);
    }

    $invoiceNo = (string) db()->query('SELECT invoice_no FROM orders WHERE id = ' . $orderId)->fetchColumn();
    $inlineImgs = [];
    $summary = order_summary_html($orderId, $inlineImgs);
    $buyer = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $buyer->execute([$buyerUserId]);
    $buyer = $buyer->fetch();
    if ($buyer && $buyer['email']) {
        $intro = $onBehalf
            ? '<p>Hi ' . htmlspecialchars($buyer['name'], ENT_QUOTES) . ',</p><p>Your distributor placed the following order for <b>' . htmlspecialchars($store['name'], ENT_QUOTES) . '</b>.</p>'
            : '<p>Hi ' . htmlspecialchars($buyer['name'], ENT_QUOTES) . ',</p><p>' . ($paid ? 'Thanks for your order and payment! It\'s confirmed and being processed.' : 'Thanks for your order! We\'ve received it.') . '</p>';
        send_mail(
            [[$buyer['email'], $buyer['name']]],
            'Order received — ' . $invoiceNo,
            mail_template('Order received', $intro . $summary . '<p style="margin-top:20px">' . mail_button('View in your portal', abs_url('login.html')) . '</p>'),
            ['context' => 'order_placed', 'sent_by' => $actorId, 'inline' => $inlineImgs]
        );
    }
    if ($supplierType === 'partner' && !$onBehalf && $supplierId) {
        $sup = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        $sup->execute([$supplierId]);
        $sup = $sup->fetch();
        if ($sup && $sup['email']) {
            $pintro = '<p>Hi ' . htmlspecialchars($sup['name'], ENT_QUOTES) . ',</p><p><b>' . htmlspecialchars($store['name'], ENT_QUOTES) . '</b> just placed a new order for you to fulfil.</p>';
            send_mail(
                [[$sup['email'], $sup['name']]],
                'New order to fulfil — ' . $invoiceNo,
                mail_template('New order received', $pintro . $summary . '<p style="margin-top:20px">' . mail_button('Open your portal', abs_url('partner/')) . '</p>'),
                ['context' => 'order_new_partner', 'sent_by' => $actorId, 'inline' => $inlineImgs]
            );
        }
    }
}
