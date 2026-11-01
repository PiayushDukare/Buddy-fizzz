<?php
require __DIR__ . '/config.php';
require_method('POST');

$email = strtolower((string) field(body(), 'email', ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Please enter a valid email address', 422);
}

db()->prepare('INSERT IGNORE INTO subscribers (email) VALUES (?)')->execute([$email]);

json_out(['ok' => true, 'message' => 'Thanks for subscribing!']);
