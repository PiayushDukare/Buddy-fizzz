<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$counts = db()->query(
    "SELECT
       SUM(role = 'retailer' AND status = 'active' AND email <> '') AS retailers,
       SUM(role = 'partner'  AND status = 'active' AND email <> '') AS partners
     FROM users"
)->fetch();
$subscribers = (int) db()->query('SELECT COUNT(*) FROM subscribers')->fetchColumn();

$stores = db()->query(
    "SELECT s.id, s.name, u.role, u.email
     FROM stores s JOIN users u ON u.id = s.user_id
     WHERE u.status = 'active' AND u.email <> ''
     ORDER BY s.name ASC LIMIT 500"
)->fetchAll();

$people = db()->query(
    "SELECT id, name, role, email
     FROM users
     WHERE role IN ('retailer','partner') AND status = 'active' AND email <> ''
     ORDER BY role ASC, name ASC LIMIT 1000"
)->fetchAll();

json_out([
    'ok' => true,
    'counts' => [
        'retailers' => (int) ($counts['retailers'] ?? 0),
        'partners' => (int) ($counts['partners'] ?? 0),
        'subscribers' => $subscribers,
    ],
    'stores' => $stores,
    'people' => $people,
]);
