<?php
require __DIR__ . '/../config.php';
$user = require_role('partner');
require_method('GET');

$stmt = db()->prepare(
    "SELECT s.id, s.name, s.owner_name, s.phone, s.email, s.gstin, s.address, s.type,
            s.village, s.taluka, s.district, s.state, s.pincode, s.status,
            s.wallet_balance, s.credit_allowed, s.credit_limit,
            u.status AS account_status,
            (SELECT COUNT(*) FROM orders o WHERE o.buyer_store_id = s.id) AS order_count,
            (SELECT MAX(created_at) FROM orders o WHERE o.buyer_store_id = s.id) AS last_order,
            (SELECT COUNT(*) FROM termination_requests tr WHERE tr.store_id = s.id AND tr.status = 'pending') AS term_pending
     FROM stores s JOIN users u ON u.id = s.user_id WHERE s.partner_id = ? ORDER BY s.name ASC"
);
$stmt->execute([$user['id']]);

json_out(['ok' => true, 'retailers' => $stmt->fetchAll()]);
