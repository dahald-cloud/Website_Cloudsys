<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-ui.php';
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
try {
    $admin = cloudsys_require_admin();
} catch (Throwable $startupError) {
    error_log('CloudSys admin dashboard startup error: ' . $startupError->getMessage());
    http_response_code(503);
    exit('Admin dashboard is temporarily unavailable.');
}
$message = ($_GET['password'] ?? '') === 'changed' ? 'Your administrator password was changed successfully.' : '';
$error = '';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!cloudsys_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            throw new DomainException('This settings page expired. Refresh and try again.');
        }
        $requestedMode = (string) ($_POST['chat_access_mode'] ?? '');
        if (!in_array($requestedMode, ['admins', 'everyone', 'disabled'], true)) throw new DomainException('Choose a valid chatbot access mode.');
        $statement = cloudsys_db()->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES ('chat_access_mode', ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP");
        $statement->execute([$requestedMode, (int) $admin['id']]);
        $message = 'Chatbot access updated successfully.';
    }
    $currentMode = cloudsys_chat_mode();
} catch (Throwable $exception) {
    error_log('CloudSys admin settings error: ' . $exception->getMessage());
    $error = $exception instanceof DomainException ? $exception->getMessage() : 'Settings are temporarily unavailable.';
    $currentMode = 'admins';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Website controls | CloudSys</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="icon" type="image/png" href="../assets/cloudsys-logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/style.css?v=20260906-3" />
  <link rel="stylesheet" href="/admin-styles.css?v=20260906-3" />
</head>
<body class="admin-dashboard-shell">
  <?php cloudsys_admin_header($admin, 'controls'); ?>
  <main class="admin-dashboard">
    <p class="admin-eyebrow">WEBSITE CONTROLS</p>
    <div class="admin-title-row"><div><h1>Chatbot access</h1><p>Choose who can see and use the CloudSys guide. The API enforces the same setting.</p></div><a href="../" target="_blank" rel="noopener">View website &nearr;</a></div>
    <?php if ($message !== ''): ?><p class="admin-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="admin-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form class="access-settings" method="post" action="/admin/">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(cloudsys_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
      <fieldset>
        <legend>Who can use the chatbot?</legend>
        <label class="access-option"><input type="radio" name="chat_access_mode" value="admins" <?= $currentMode === 'admins' ? 'checked' : '' ?> /><span><strong>Admins only</strong><small>Only an administrator with an active session can see or use chat. This is the default.</small></span></label>
        <label class="access-option"><input type="radio" name="chat_access_mode" value="everyone" <?= $currentMode === 'everyone' ? 'checked' : '' ?> /><span><strong>Everyone</strong><small>All website visitors can use chat after completing Turnstile verification.</small></span></label>
        <label class="access-option"><input type="radio" name="chat_access_mode" value="disabled" <?= $currentMode === 'disabled' ? 'checked' : '' ?> /><span><strong>Disabled</strong><small>The chatbot is hidden and every chat API request is rejected.</small></span></label>
      </fieldset>
      <button class="admin-save" type="submit">Save access setting <span>&rarr;</span></button>
    </form>
    <aside class="admin-security-note"><strong>Server enforced</strong><p>Changing this setting updates the database immediately. Hiding the launcher is only the visual layer; the PHP endpoint also checks access before every response.</p></aside>
  </main>
</body>
</html>
