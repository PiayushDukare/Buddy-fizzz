<?php
require __DIR__ . '/config.php';

$adminEmail = 'admin@buddyfizz.com';
$adminPass = 'Admin@123';

$existing = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$existing->execute([$adminEmail]);

$created = [];

if (!$existing->fetch()) {
    $stmt = db()->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Buddy Fizz Admin', $adminEmail, '+917774067890', password_hash($adminPass, PASSWORD_DEFAULT), 'admin']);
    $created['admin'] = ['email' => $adminEmail, 'password' => $adminPass];
} else {
    $created['admin'] = 'already exists';
}

$products = [
    ['Lemon Lime Soda', 'Lemon', 'BF-LEM-250', '250 ml', 20.00, 'frames/img/lemon.webp'],
    ['Ginger Lemon Soda', 'Ginger Lemon', 'BF-GIN-250', '250 ml', 20.00, 'frames/img/ginger.webp'],
    ['Orange Soda', 'Orange', 'BF-ORA-250', '250 ml', 20.00, 'frames/img/orange.webp'],
    ['Masala Soda', 'Masala', 'BF-MAS-250', '250 ml', 20.00, 'frames/img/masala.webp'],
    ['Grape Soda', 'Grape', 'BF-GRA-250', '250 ml', 20.00, 'frames/img/grape.webp'],
];

$ins = db()->prepare('INSERT IGNORE INTO products (name, flavour, sku, size, price, image) VALUES (?, ?, ?, ?, ?, ?)');
foreach ($products as $p) {
    $ins->execute($p);
}
$created['products'] = db()->query('SELECT COUNT(*) FROM products')->fetchColumn() . ' total';

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'setup' => $created, 'note' => 'Delete api/setup.php after running this once.'], JSON_PRETTY_PRINT);
