<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
$name = (string) field($data, 'name', '');
$flavour = (string) field($data, 'flavour', '');
$sku = strtoupper((string) field($data, 'sku', ''));
$size = (string) field($data, 'size', '');
$cost = (float) field($data, 'cost', 0);
$price = (float) field($data, 'price', 0);
$partnerProfit = (float) field($data, 'partner_profit', 0);
$mrp = field($data, 'mrp', '') !== '' ? (float) field($data, 'mrp', 0) : null;
$image = (string) field($data, 'image', '');
$uploaded = handle_upload('image_file', 'products', true, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
if ($uploaded) {
    $image = $uploaded;
}
$active = (int) !!field($data, 'is_active', 1);

if ($name === '' || $sku === '') {
    json_error('Name and SKU are required', 422);
}
if ($price < 0) {
    json_error('Price cannot be negative', 422);
}

$skuCheck = db()->prepare('SELECT id FROM products WHERE sku = ? AND id <> ? LIMIT 1');
$skuCheck->execute([$sku, $id]);
if ($skuCheck->fetch()) {
    json_error('Another product already uses this SKU', 409);
}

if ($id > 0) {
    db()->prepare('UPDATE products SET name=?, flavour=?, sku=?, size=?, cost=?, price=?, partner_profit=?, mrp=?, image=?, is_active=? WHERE id=?')
        ->execute([$name, $flavour ?: null, $sku, $size ?: null, $cost, $price, $partnerProfit, $mrp, $image ?: null, $active, $id]);
    log_activity('update_product', 'product', $id);
    json_out(['ok' => true, 'message' => 'Product updated', 'id' => $id]);
}

db()->prepare('INSERT INTO products (name, flavour, sku, size, cost, price, partner_profit, mrp, image, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$name, $flavour ?: null, $sku, $size ?: null, $cost, $price, $partnerProfit, $mrp, $image ?: null, $active]);
$newId = (int) db()->lastInsertId();
log_activity('create_product', 'product', $newId);

json_out(['ok' => true, 'message' => 'Product created', 'id' => $newId], 201);
