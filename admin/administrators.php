<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-ui.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
$admin = cloudsys_require_admin();
$error = '';
$message = isset($_GET['invited']) ? 'Administrator invitation sent.' : '';
$email = '';
$displayName = '';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    exit('Method not allowed.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    try {
        if (!cloudsys_verify_csrf((string) ($_POST['csrf'] ?? ''))) throw new DomainException('This page expired. Refresh and try again.');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !str_ends_with($email, '@cloudsysllc.com')) throw new DomainException('Use a valid @cloudsysllc.com email address.');
        if ($displayName === '' || strlen($displayName) > 100) throw new DomainException('Enter a display name of 100 characters or fewer.');
        $database = cloudsys_db();
        $database->beginTransaction();
        $existing = $database->prepare('SELECT id, is_active FROM admins WHERE email = ? LIMIT 1 FOR UPDATE');
        $existing->execute([$email]);
        $row = $existing->fetch();
        if (is_array($row) && (int) $row['is_active'] === 1) throw new DomainException('That administrator already exists.');
        $temporaryHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        if (is_array($row)) {
            $adminId = (int) $row['id'];
            $database->prepare('UPDATE admins SET display_name = ?, password_hash = ?, is_active = 1, must_change_password = 1, session_version = session_version + 1 WHERE id = ?')->execute([$displayName, $temporaryHash, $adminId]);
        } else {
            $database->prepare('INSERT INTO admins (email, password_hash, display_name, is_active, must_change_password, session_version) VALUES (?, ?, ?, 1, 1, 1)')->execute([$email, $temporaryHash, $displayName]);
            $adminId = (int) $database->lastInsertId();
        }
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $database->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL')->execute([$adminId]);
        $database->prepare('INSERT INTO admin_password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([$adminId, $tokenHash]);
        $siteUrl = rtrim(cloudsys_config_value('SITE_URL') ?: 'https://cloudsysllc.com', '/');
        $link = $siteUrl . '/reset-password?token=' . rawurlencode($token);
        cloudsys_send_email($email, 'Set up your CloudSys administrator account', "Hello {$displayName},\n\nYou have been invited to administer the CloudSys website. Use this one-time link within 30 minutes to choose your password:\n{$link}\n\nIf you were not expecting this invitation, do not use the link.", 'admin-invite-' . $tokenHash);
        $database->commit();
        header('Location: /admin/administrators.php?invited=1', true, 303); exit;
    } catch (Throwable $exception) {
        if (isset($database) && $database->inTransaction()) $database->rollBack();
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'The invitation could not be sent. Please try again.';
        if (!($exception instanceof DomainException)) error_log('CloudSys administrator invitation error: ' . $exception->getMessage());
    }
}
$admins = cloudsys_db()->query('SELECT email, display_name, is_active, last_login_at FROM admins ORDER BY is_active DESC, display_name, email')->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administrators | CloudSys</title><meta name="robots" content="noindex,nofollow"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"><link rel="stylesheet" href="/style.css?v=20260906-3"><link rel="stylesheet" href="/admin-styles.css?v=20260906-3"></head>
<body class="admin-dashboard-shell"><?php cloudsys_admin_header($admin, 'administrators'); ?><main class="admin-dashboard"><p class="admin-eyebrow">ACCESS MANAGEMENT</p><div class="admin-title-row"><div><h1>Administrators</h1><p>Invite trusted CloudSys team members. Invitations expire after 30 minutes and require the recipient to create a strong password.</p></div></div><?php if ($message !== ''): ?><p class="admin-success" role="status"><?= cloudsys_admin_escape($message) ?></p><?php endif; ?><?php if ($error !== ''): ?><p class="admin-alert" role="alert"><?= cloudsys_admin_escape($error) ?></p><?php endif; ?><section class="admin-panel"><h2>Invite an administrator</h2><form class="admin-form admin-invite-form" method="post" action="/admin/administrators.php"><input type="hidden" name="csrf" value="<?= cloudsys_admin_escape(cloudsys_csrf_token()) ?>"><label>Display name<input name="display_name" required maxlength="100" value="<?= cloudsys_admin_escape($displayName) ?>"></label><label>CloudSys email address<input type="email" name="email" required maxlength="254" pattern=".+@cloudsysllc\.com" value="<?= cloudsys_admin_escape($email) ?>"></label><button type="submit">Send invitation <span>&rarr;</span></button></form></section><section class="admin-panel"><h2>Current administrators</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th scope="col">Administrator</th><th scope="col">Status</th><th scope="col">Last sign-in</th></tr></thead><tbody><?php foreach ($admins as $row): ?><tr><td><strong><?= cloudsys_admin_escape((string) $row['display_name']) ?></strong><small><?= cloudsys_admin_escape((string) $row['email']) ?></small></td><td><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></td><td><?= $row['last_login_at'] ? cloudsys_admin_escape((string) $row['last_login_at']) . ' UTC' : 'Not yet' ?></td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
