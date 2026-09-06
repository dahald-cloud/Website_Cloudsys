<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$configPath = getenv('CLOUDSYS_CONFIG') ?: __DIR__ . DIRECTORY_SEPARATOR . 'cloudsys-config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Place this script beside cloudsys-config.php or set CLOUDSYS_CONFIG.\n");
    exit(1);
}
$config = require $configPath;
if (!is_array($config)) exit("Invalid configuration.\n");
$value = static function (string $name) use ($config): string {
    $environment = getenv($name);
    if ($environment !== false && $environment !== '') return trim($environment);
    return trim((string) ($config[$name] ?? ''));
};
$email = strtolower(trim((string) readline('Admin email: ')));
$name = trim((string) readline('Display name: '));
$approvedEmails = ['dahald@cloudsysllc.com', 'adhakal@cloudsysllc.com'];
if (!in_array($email, $approvedEmails, true) || $name === '') exit("Use one of the two approved CloudSys administrator emails and provide a display name.\n");
$canHide = PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec');
if ($canHide) @shell_exec('stty -echo');
fwrite(STDOUT, 'Password (minimum 14 characters): ');
$password = trim((string) fgets(STDIN));
if ($canHide) { @shell_exec('stty echo'); fwrite(STDOUT, PHP_EOL); }
if (strlen($password) < 14 || strlen($password) > 72) exit("Password must be between 14 and 72 characters.\n");
$dsn = 'mysql:host=' . $value('DB_HOST') . ';port=' . ($value('DB_PORT') ?: '3306') . ';dbname=' . $value('DB_NAME') . ';charset=utf8mb4';
$database = new PDO($dsn, $value('DB_USER'), $value('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$statement = $database->prepare('INSERT INTO admins (email, password_hash, display_name, is_active, must_change_password, session_version) VALUES (?, ?, ?, 1, 1, 1) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name), is_active = 1, must_change_password = 1, session_version = session_version + 1');
$statement->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name]);
fwrite(STDOUT, "Administrator created. Delete this helper from the server now.\n");
