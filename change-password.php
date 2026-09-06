<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow', true);
try {
    $admin = cloudsys_current_admin();
    if ($admin === null) {
        header('Location: /login', true, 302);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(503);
    exit('Password service is temporarily unavailable.');
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $current = (string) ($_POST['current_password'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!cloudsys_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        $error = 'This page expired. Refresh and try again.';
    } elseif (!cloudsys_valid_password($password)) {
        $error = 'Use 14-72 characters with uppercase, lowercase, and a number.';
    } elseif (!hash_equals($password, $confirmation)) {
        $error = 'The new passwords do not match.';
    } else {
        try {
            $database = cloudsys_db();
            $query = $database->prepare('SELECT password_hash FROM admins WHERE id = ? AND is_active = 1 LIMIT 1');
            $query->execute([(int) $admin['id']]);
            $hash = (string) ($query->fetchColumn() ?: '');

            if (!password_verify($current, $hash)) {
                $error = 'Your current password is incorrect.';
            } elseif (password_verify($password, $hash)) {
                $error = 'Choose a password different from the current password.';
            } else {
                $database->beginTransaction();
                $database->prepare('UPDATE admins SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ?')->execute([
                    password_hash($password, PASSWORD_DEFAULT),
                    (int) $admin['id'],
                ]);
                $database->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL')->execute([(int) $admin['id']]);
                $versionQuery = $database->prepare('SELECT session_version FROM admins WHERE id = ? LIMIT 1');
                $versionQuery->execute([(int) $admin['id']]);
                $newSessionVersion = (int) $versionQuery->fetchColumn();
                $database->commit();

                session_regenerate_id(true);
                $_SESSION['admin_session_version'] = $newSessionVersion;
                $_SESSION['csrf'] = bin2hex(random_bytes(24));
                header('Location: /admin/?password=changed', true, 302);
                exit;
            }
        } catch (Throwable $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            error_log('CloudSys password change error: ' . $exception->getMessage());
            $error = 'Unable to change the password right now.';
        }
    }
}
$forced = (int) ($admin['must_change_password'] ?? 0) === 1;
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Change password | CloudSys</title><meta name="robots" content="noindex,nofollow"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin-styles.css"></head><body class="admin-shell"><main class="admin-auth-card"><a class="brand" href="/" aria-label="CloudSys home"><img src="/assets/cloudsys-logo.png" width="794" height="243" alt="CloudSys"></a><p class="admin-eyebrow">SECURE ADMIN ACCESS</p><h1>Change password</h1><p class="admin-intro"><?= $forced ? 'Before continuing, replace your temporary password with a private password.' : 'Update your administrator password.' ?> Use 14-72 characters with uppercase, lowercase, and a number.</p><?php if ($error !== ''): ?><p class="admin-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><form class="admin-form" method="post" action="/change-password"><input type="hidden" name="csrf" value="<?= htmlspecialchars(cloudsys_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required maxlength="72"></label><label>New password<input type="password" name="password" autocomplete="new-password" required minlength="14" maxlength="72"></label><label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" required minlength="14" maxlength="72"></label><button type="submit">Change password <span>&rarr;</span></button></form><?php if (!$forced): ?><a class="admin-back" href="/admin/">&larr; Return to dashboard</a><?php endif; ?></main></body></html>
