<?php
require __DIR__ . '/config.php';
require_method('POST');

$data = body();

if (trim((string) field($data, 'company_url', '')) !== '') {
    json_out(['ok' => true, 'message' => 'Application received! Our team will review it and activate your account soon.'], 201);
}

$ip = client_ip();
$rate = db()->prepare('SELECT COUNT(*) FROM applications WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
$rate->execute([$ip]);
if ((int) $rate->fetchColumn() >= 5) {
    json_error('Too many applications from this network. Please try again later.', 429);
}

if (!verify_captcha((string) field($data, 'captcha', ''))) {
    json_error('Incorrect answer to the verification question. Please try again.', 422);
}

$type = field($data, 'type', 'retailer');
$type = in_array($type, ['retailer', 'partner'], true) ? $type : 'retailer';

$storeType = field($data, 'store_type', 'retail');
$storeType = in_array($storeType, ['retail', 'supermarket', 'distributor', 'online'], true) ? $storeType : 'retail';

$businessName = (string) field($data, 'business_name', '');
$contactName = (string) field($data, 'contact_name', '');
$email = strtolower((string) field($data, 'email', ''));
$phone = (string) field($data, 'phone', '');
$password = (string) field($data, 'password', '');

if ($businessName === '' || $contactName === '' || $email === '' || $phone === '' || $password === '') {
    json_error('Business name, contact name, email, phone and password are required', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Please enter a valid email address', 422);
}
if (strlen($password) < 8) {
    json_error('Password must be at least 8 characters', 422);
}

$existsUser = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$existsUser->execute([$email]);
if ($existsUser->fetch()) {
    json_error('An account with this email already exists', 409);
}

$pending = db()->prepare("SELECT id FROM applications WHERE email = ? AND status = 'pending' LIMIT 1");
$pending->execute([$email]);
if ($pending->fetch()) {
    json_error('You already have a pending application with this email', 409);
}

$stmt = db()->prepare(
    'INSERT INTO applications
     (type, business_name, contact_name, email, phone, password, store_type,
      address, village, taluka, district, state, pincode, lat, lng, message, ip)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $type,
    $businessName,
    $contactName,
    $email,
    $phone,
    password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
    $storeType,
    field($data, 'address'),
    field($data, 'village'),
    field($data, 'taluka'),
    field($data, 'district'),
    field($data, 'state'),
    field($data, 'pincode'),
    field($data, 'lat') !== '' ? field($data, 'lat') : null,
    field($data, 'lng') !== '' ? field($data, 'lng') : null,
    field($data, 'message'),
    $ip,
]);

json_out([
    'ok' => true,
    'message' => 'Application received! Our team will review it and activate your account soon.',
    'id' => (int) db()->lastInsertId(),
], 201);
