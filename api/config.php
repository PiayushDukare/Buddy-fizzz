<?php
declare(strict_types=1);


function load_env(string $file): void
{
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $len = strlen($v);
        if ($len >= 2 && (($v[0] === '"' && $v[$len - 1] === '"') || ($v[0] === "'" && $v[$len - 1] === "'"))) {
            $v = substr($v, 1, -1);
        }
        if ($k !== '' && getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

function env(string $key, $default = null)
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

function env_bool(string $key, bool $default = false): bool
{
    $v = env($key);
    if ($v === null) {
        return $default;
    }
    return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
}


foreach ([dirname(__DIR__, 2) . '/.env', dirname(__DIR__) . '/.env'] as $__envFile) {
    if (is_readable($__envFile)) {
        load_env($__envFile);
        break;
    }
}

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'buddyfizz'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

define('GOOGLE_MAPS_KEY', env('GOOGLE_MAPS_KEY', ''));

define('MAIL_ENABLED', env_bool('MAIL_ENABLED', false));
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) env('SMTP_PORT', 587));
define('SMTP_SECURE', env('SMTP_SECURE', 'tls'));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_FROM', env('SMTP_FROM', env('SMTP_USER', '')));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Buddy Fizz'));
define('MAIL_BCC', env('MAIL_BCC', ''));
define('MAIL_VERIFY_PEER', env_bool('MAIL_VERIFY_PEER', true));


define('RAZORPAY_ENABLED', env_bool('RAZORPAY_ENABLED', false));
define('RAZORPAY_KEY_ID', env('RAZORPAY_KEY_ID', ''));
define('RAZORPAY_KEY_SECRET', env('RAZORPAY_KEY_SECRET', ''));
define('RAZORPAY_WEBHOOK_SECRET', env('RAZORPAY_WEBHOOK_SECRET', ''));

date_default_timezone_set('Asia/Kolkata');


define('DEBUG', env_bool('APP_DEBUG', false));
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

define('LOG_DIR', dirname(__DIR__) . '/logs');
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}
ini_set('error_log', LOG_DIR . '/php_error.log');

function log_error(string $message, ?Throwable $e = null): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($e) {
        $line .= ' | ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
    $line .= ' | ' . ($_SERVER['REQUEST_METHOD'] ?? '-') . ' ' . ($_SERVER['REQUEST_URI'] ?? '-');
    if (!empty($_SESSION['user_id'])) {
        $line .= ' | uid=' . $_SESSION['user_id'];
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $line .= ' | ip=' . $_SERVER['REMOTE_ADDR'];
    }
    @file_put_contents(LOG_DIR . '/app_error.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

set_exception_handler(function (Throwable $e) {
    log_error('Uncaught exception', $e);
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => DEBUG ? $e->getMessage() : 'A server error occurred']);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        log_error('Fatal ' . $err['type'] . ': ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
    }
});

$__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://checkout.razorpay.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob: https://*.tile.openstreetmap.org https://cdnjs.cloudflare.com https://*.razorpay.com; connect-src 'self' https://nominatim.openstreetmap.org https://*.razorpay.com https://lumberjack.razorpay.com; frame-src https://api.razorpay.com https://checkout.razorpay.com https://*.razorpay.com; child-src https://api.razorpay.com https://checkout.razorpay.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if ($__https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $__https,
    ]);
    session_name('bfsid');
    session_start();
}

$__now = time();
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && ($__now - $_SESSION['last_activity']) > 3600) {
        $_SESSION = [];
        session_destroy();
        session_start();
    } else {
        $_SESSION['last_activity'] = $__now;
    }
}

$__docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
$__appDir = str_replace('\\', '/', dirname(__DIR__));
$__base = ($__docRoot !== '' && strpos($__appDir, $__docRoot) === 0) ? substr($__appDir, strlen($__docRoot)) : '';
define('APP_URL', ($__base === '' ? '' : $__base) . '/');

function url(string $path = ''): string
{
    return APP_URL . ltrim($path, '/');
}

function page_guard(string ...$roles): array
{
    $user = current_user();
    if (!$user || $user['status'] !== 'active') {
        header('Location: ' . url('login.html'));
        exit;
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        header('Location: ' . url($user['role'] . '/'));
        exit;
    }
    return $user;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }
    return $pdo;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($data);
    exit;
}

function json_error(string $message, int $code = 400): void
{
    json_out(['ok' => false, 'error' => $message], $code);
}

function body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST ?: [];
}

function field(array $data, string $key, $default = null)
{
    $value = $data[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_error('Method not allowed', 405);
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, name, email, phone, role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user || $user['status'] !== 'active') {
        json_error('Not authenticated', 401);
    }
    return $user;
}

function my_store(int $userId): ?array
{
    $s = db()->prepare('SELECT * FROM stores WHERE user_id = ? LIMIT 1');
    $s->execute([$userId]);
    return $s->fetch() ?: null;
}

function get_setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings')->fetchAll() as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function handle_upload(string $field, string $subdir = 'tickets', bool $public = false, ?array $onlyExt = null): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        json_error('File upload failed', 400);
    }
    if ($f['size'] > 5 * 1024 * 1024) {
        json_error('File must be under 5 MB', 413);
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = $onlyExt ?? ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'];
    $blocked = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'htm', 'html', 'svg', 'pl', 'py'];
    if (in_array($ext, $blocked, true) || !in_array($ext, $allowed, true)) {
        json_error('This file type is not allowed', 415);
    }
    $baseDir = $public ? 'media' : 'uploads';
    $dir = dirname(__DIR__) . '/' . $baseDir . '/' . $subdir;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        json_error('Could not save file', 500);
    }
    return $baseDir . '/' . $subdir . '/' . $name;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        json_error('Forbidden', 403);
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!$sent || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        json_error('Invalid CSRF token', 419);
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function log_activity(string $action, ?string $entity = null, ?int $entityId = null, ?array $meta = null): void
{
    $stmt = db()->prepare('INSERT INTO activity_log (user_id, action, entity, entity_id, meta, ip) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $entity,
        $entityId,
        $meta !== null ? json_encode($meta) : null,
        client_ip(),
    ]);
}

function record_login_attempt(string $email, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)');
    $stmt->execute([$email, client_ip(), $success ? 1 : 0]);
}

function login_blocked(string $email): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)
         AND (email = ? OR ip = ?)"
    );
    $stmt->execute([$email, client_ip()]);
    return (int) $stmt->fetchColumn() >= 6;
}

function new_captcha(): array
{
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['captcha'] = ['sum' => $a + $b, 'ts' => time()];
    return ['question' => "What is {$a} + {$b}?"];
}

function verify_captcha(string $answer): bool
{
    $c = $_SESSION['captcha'] ?? null;
    unset($_SESSION['captcha']);
    if (!$c || (time() - (int) $c['ts']) > 600) {
        return false;
    }
    return trim($answer) !== '' && (int) $answer === (int) $c['sum'];
}

require_once __DIR__ . '/lib/mail.php';
