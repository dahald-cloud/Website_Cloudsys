<?php
declare(strict_types=1);

function cloudsys_config(): array
{
    static $config;
    if (is_array($config)) return $config;
    $path = getenv('CLOUDSYS_CONFIG');
    if ($path === false || $path === '') $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'cloudsys-config.php';
    $loaded = is_file($path) ? require $path : [];
    if (!is_array($loaded)) throw new RuntimeException('Invalid CloudSys configuration.');
    $config = $loaded;
    return $config;
}

function cloudsys_config_value(string $name): string
{
    $environment = getenv($name);
    if ($environment !== false && $environment !== '') return trim($environment);
    $config = cloudsys_config();
    return isset($config[$name]) ? trim((string) $config[$name]) : '';
}

function cloudsys_db(): PDO
{
    static $database;
    if ($database instanceof PDO) return $database;
    $host = cloudsys_config_value('DB_HOST');
    $port = cloudsys_config_value('DB_PORT') ?: '3306';
    $name = cloudsys_config_value('DB_NAME');
    $user = cloudsys_config_value('DB_USER');
    $password = cloudsys_config_value('DB_PASSWORD');
    if ($host === '' || $name === '' || $user === '') throw new RuntimeException('Database is not configured.');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $database = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $database;
}

function cloudsys_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('cloudsys_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && $lastActivity < time() - 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

function cloudsys_current_admin(): ?array
{
    cloudsys_start_session();
    $adminId = filter_var($_SESSION['admin_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$adminId) return null;
    $statement = cloudsys_db()->prepare('SELECT id, email, display_name, must_change_password, session_version FROM admins WHERE id = ? AND is_active = 1 LIMIT 1');
    $statement->execute([(int) $adminId]);
    $admin = $statement->fetch();
    if (!is_array($admin) || !isset($_SESSION['admin_session_version']) || (int) $_SESSION['admin_session_version'] !== (int) $admin['session_version']) {
        unset($_SESSION['admin_id'], $_SESSION['admin_session_version']);
        return null;
    }
    return $admin;
}

function cloudsys_require_admin(): array
{
    $admin = cloudsys_current_admin();
    if ($admin !== null) {
        if ((int) ($admin['must_change_password'] ?? 0) === 1 && !str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/change-password')) {
            header('Location: /change-password', true, 302);
            exit;
        }
        return $admin;
    }
    $returnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/');
    if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) $returnTo = '/admin/';
    header('Location: /login?return=' . rawurlencode($returnTo), true, 302);
    exit;
}

function cloudsys_csrf_token(): string
{
    cloudsys_start_session();
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function cloudsys_verify_csrf(string $token): bool
{
    cloudsys_start_session();
    return isset($_SESSION['csrf']) && is_string($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function cloudsys_chat_mode(): string
{
    $statement = cloudsys_db()->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'chat_access_mode' LIMIT 1");
    $statement->execute();
    $mode = (string) ($statement->fetchColumn() ?: 'admins');
    return in_array($mode, ['admins', 'everyone', 'disabled'], true) ? $mode : 'admins';
}

function cloudsys_chat_allowed(): bool
{
    $mode = cloudsys_chat_mode();
    if ($mode === 'disabled') return false;
    if ($mode === 'everyone') return true;
    return cloudsys_current_admin() !== null;
}
function cloudsys_valid_password(string $password): bool
{
    return strlen($password) >= 14 && strlen($password) <= 72
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

function cloudsys_send_email(string $to, string $subject, string $text, string $idempotencyKey): void
{
    $apiKey = cloudsys_config_value('RESEND_API_KEY');
    $from = cloudsys_config_value('RESEND_FROM');
    if ($apiKey === '' || $from === '' || !function_exists('curl_init')) throw new RuntimeException('Email delivery is not configured.');
    $body = json_encode(['from' => $from, 'to' => [$to], 'subject' => $subject, 'text' => $text], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $handle = curl_init('https://api.resend.com/emails');
    curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json', 'Idempotency-Key: ' . $idempotencyKey], CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($response === false || $status < 200 || $status >= 300) throw new RuntimeException('Password email failed: ' . ($error !== '' ? $error : 'HTTP ' . $status));
}
