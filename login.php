<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

cloudsys_start_session();
$error = '';
try {
    if (cloudsys_current_admin() !== null) {
        header('Location: /admin/', true, 302);
        exit;
    }
} catch (Throwable $startupError) {
    error_log('CloudSys admin startup error: ' . $startupError->getMessage());
    $error = 'Admin sign-in is temporarily unavailable.';
}

$email = '';
$returnTo = (string) ($_GET['return'] ?? $_POST['return'] ?? '/admin/');
if (!str_starts_with($returnTo, '/admin') || preg_match('/[\r\n]/', $returnTo)) $returnTo = '/admin/';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $csrf = (string) ($_POST['csrf'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rateFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cloudsys-admin-login-' . hash('sha256', $ip);
    $rate = is_file($rateFile) ? json_decode((string) @file_get_contents($rateFile), true) : [];
    if (!is_array($rate) || (int) ($rate['started'] ?? 0) < time() - 900) $rate = ['started' => time(), 'count' => 0];

    if ((int) ($rate['count'] ?? 0) >= 5) {
        http_response_code(429);
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } elseif (!cloudsys_verify_csrf($csrf)) {
        $error = 'This sign-in page expired. Refresh and try again.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            $statement = cloudsys_db()->prepare('SELECT id, password_hash, must_change_password, session_version FROM admins WHERE email = ? AND is_active = 1 LIMIT 1');
            $statement->execute([$email]);
            $admin = $statement->fetch();
            if (is_array($admin) && password_verify($password, (string) $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_session_version'] = (int) $admin['session_version'];
                $_SESSION['last_activity'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(24));
                $update = cloudsys_db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
                $update->execute([(int) $admin['id']]);
                @unlink($rateFile);
                header('Location: ' . ((int) ($admin['must_change_password'] ?? 0) === 1 ? '/change-password' : $returnTo), true, 302);
                exit;
            }
            $rate['count'] = (int) ($rate['count'] ?? 0) + 1;
            @file_put_contents($rateFile, json_encode($rate), LOCK_EX);
            @chmod($rateFile, 0600);
            usleep(350000);
            $error = 'The email address or password is incorrect.';
        } catch (Throwable $exception) {
            error_log('CloudSys admin login error: ' . $exception->getMessage());
            $error = 'Admin sign-in is temporarily unavailable.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin sign in | CloudSys</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="icon" type="image/png" href="./assets/cloudsys-logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="./style.css" />
  <link rel="stylesheet" href="./admin-styles.css" />
</head>
<body class="admin-shell">
  <main class="admin-auth-card">
    <a class="brand" href="./" aria-label="CloudSys home"><img src="./assets/cloudsys-logo.png" width="794" height="243" alt="CloudSys" /></a>
    <p class="admin-eyebrow">SECURE ADMIN ACCESS</p>
    <h1>Sign in</h1>
    <p class="admin-intro">Access website controls using an authorised administrator account.</p>
    <?php if ($error !== ''): ?><p class="admin-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form class="admin-form" method="post" action="/login" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(cloudsys_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
      <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
      <label>Email address<input type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required maxlength="254" /></label>
      <label>Password<input type="password" name="password" autocomplete="current-password" required maxlength="72" /></label>
      <button type="submit">Sign in <span>&rarr;</span></button>
    </form>
    <div class="admin-auth-links"><a class="admin-back" href="/forgot-password">Forgot password?</a><a class="admin-back" href="./">&larr; Return to website</a></div>
  </main>
</body>
</html>
