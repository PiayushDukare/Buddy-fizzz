<?php

function wallet_apply(int $storeId, float $amount, string $type, ?int $orderId = null, ?string $note = null, ?int $by = null): float
{
    $cur = (float) db()->query('SELECT wallet_balance FROM stores WHERE id = ' . $storeId . ' FOR UPDATE')->fetchColumn();
    $newBal = round($cur + $amount, 2);
    if ($newBal < -0.001) {
        throw new RuntimeException('Insufficient wallet balance');
    }
    db()->prepare('UPDATE stores SET wallet_balance = ? WHERE id = ?')->execute([$newBal, $storeId]);
    db()->prepare('INSERT INTO wallet_transactions (store_id, amount, balance_after, type, order_id, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$storeId, round($amount, 2), $newBal, $type, $orderId, $note, $by]);
    return $newBal;
}

function store_outstanding(int $storeId): float
{
    return (float) db()->query(
        "SELECT IFNULL(SUM(total - amount_paid),0) FROM orders WHERE buyer_store_id = " . $storeId . " AND status NOT IN ('cancelled','rejected')"
    )->fetchColumn();
}
