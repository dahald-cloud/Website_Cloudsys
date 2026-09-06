<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow', true);
cloudsys_start_session();
$message = '';
$error = '';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $csrf = (string) ($_POST['csrf'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rateFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cloudsys-password-reset-' . hash('sha256', $ip);
    if (!cloudsys_verify_csrf($csrf)) {
        $error = 'This page expired. Refresh and try again.';
    } elseif (is_file($rateFile) && filemtime($rateFile) > time() - 900) {
        $message = 'If that address is an active administrator, a reset link has been sent.';
    } else {
        try {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $query = cloudsys_db()->prepare('SELECT id, email, display_name FROM admins WHERE email = ? AND is_active = 1 LIMIT 1');
                $query->execute([$email]);
                $admin = $query->fetch();
                if (is_array($admin)) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    cloudsys_db()->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL')->execute([(int) $admin['id']]);
                    cloudsys_db()->prepare('INSERT INTO admin_password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([(int) $admin['id'], $tokenHash]);
                    $siteUrl = rtrim(cloudsys_config_value('SITE_URL') ?: 'https://cloudsysllc.com', '/');
                    $link = $siteUrl . '/reset-password?token=' . rawurlencode($token);
                    cloudsys_send_email((string) $admin['email'], 'Reset your CloudSys administrator password', "Hello {$admin['display_name']},\n\nUse this one-time link within 30 minutes to reset your CloudSys administrator password:\n{$link}\n\nIf you did not request this, ignore this email.", 'password-reset-' . $tokenHash);
                }
            }
            @touch($rateFile); @chmod($rateFile, 0600);
            $message = 'If that address is an active administrator, a reset link has been sent.';
        } catch (Throwable $exception) {
            error_log('CloudSys password reset request error: ' . $exception->getMessage());
            $message = 'If that address is an active administrator, a reset link has been sent.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Forgot password | CloudSys</title><meta name="robots" content="noindex,nofollow"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin-styles.css"></head><body class="admin-shell"><main class="admin-auth-card"><a class="brand" href="/" aria-label="CloudSys home"><img src="/assets/cloudsys-logo.png" width="794" height="243" alt="CloudSys"></a><p class="admin-eyebrow">SECURE ADMIN ACCESS</p><h1>Reset password</h1><p class="admin-intro">Enter an authorised administrator email. Reset links expire after 30 minutes.</p><?php if ($message !== ''): ?><p class="admin-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><?php if ($error !== ''): ?><p class="admin-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><form class="admin-form" method="post" action="/forgot-password"><input type="hidden" name="csrf" value="<?= htmlspecialchars(cloudsys_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><label>Email address<input type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required maxlength="254"></label><button type="submit">Send reset link <span>&rarr;</span></button></form><a class="admin-back" href="/login">&larr; Return to sign in</a></main></body></html>