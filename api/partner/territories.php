<?php
require __DIR__ . '/../config.php';
$user = require_role('partner');
require_method('GET');

$stmt = db()->prepare('SELECT level, value FROM partner_territories WHERE partner_user_id = ? ORDER BY level, value');
$stmt->execute([$user['id']]);

json_out(['ok' => true, 'territories' => $stmt->fetchAll()]);
