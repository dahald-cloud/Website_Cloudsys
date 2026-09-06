<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow', true);
cloudsys_start_session();
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = false;
if (!preg_match('/^[a-f0-9]{64}$/', $token)) $error = 'This password reset link is invalid or expired.';
if ($error === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (!cloudsys_verify_csrf((string) ($_POST['csrf'] ?? ''))) $error = 'This page expired. Refresh the reset link and try again.';
    elseif (!cloudsys_valid_password($password)) $error = 'Use 14-72 characters with uppercase, lowercase, and a number.';
    elseif (!hash_equals($password, $confirmation)) $error = 'The new passwords do not match.';
    else {
        try {
            $database = cloudsys_db();
            $database->beginTransaction();
            $query = $database->prepare('SELECT id, admin_id FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE');
            $query->execute([hash('sha256', $token)]);
            $resetRow = $query->fetch();
            if (!is_array($resetRow)) throw new DomainException('This password reset link is invalid or expired.');
            $database->prepare('UPDATE admins SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ? AND is_active = 1')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $resetRow['admin_id']]);
            $database->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL')->execute([(int) $resetRow['admin_id']]);
            $database->commit();
            $success = true;
        } catch (Throwable $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            $error = $exception instanceof DomainException ? $exception->getMessage() : 'Unable to reset the password right now.';
            if (!($exception instanceof DomainException)) error_log('CloudSys password reset error: ' . $exception->getMessage());
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Choose new password | CloudSys</title><meta name="robots" content="noindex,nofollow"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin-styles.css"></head><body class="admin-shell"><main class="admin-auth-card"><a class="brand" href="/" aria-label="CloudSys home"><img src="/assets/cloudsys-logo.png" width="794" height="243" alt="CloudSys"></a><p class="admin-eyebrow">SECURE ADMIN ACCESS</p><h1>New password</h1><?php if ($success): ?><p class="admin-success" role="status">Your password was changed. You can now sign in.</p><a class="admin-primary-link" href="/login">Continue to sign in &rarr;</a><?php else: ?><p class="admin-intro">Use 14-72 characters with uppercase, lowercase, and at least one number.</p><?php if ($error !== ''): ?><p class="admin-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><?php if (preg_match('/^[a-f0-9]{64}$/', $token)): ?><form class="admin-form" method="post" action="/reset-password"><input type="hidden" name="csrf" value="<?= htmlspecialchars(cloudsys_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><label>New password<input type="password" name="password" autocomplete="new-password" required minlength="14" maxlength="72"></label><label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" required minlength="14" maxlength="72"></label><button type="submit">Set new password <span>&rarr;</span></button></form><?php endif; ?><a class="admin-back" href="/forgot-password">Request another link</a><?php endif; ?></main></body></html>
