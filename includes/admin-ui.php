<?php
declare(strict_types=1);

function cloudsys_admin_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cloudsys_admin_header(array $admin, string $active): void
{
    $name = cloudsys_admin_escape((string) ($admin['display_name'] ?? 'Administrator'));
    $initial = cloudsys_admin_escape(strtoupper(substr((string) ($admin['display_name'] ?? 'A'), 0, 1)));
    $csrf = cloudsys_admin_escape(cloudsys_csrf_token());
    ?>
    <header class="admin-header">
      <a class="brand" href="/admin/" aria-label="CloudSys website controls"><img src="/assets/cloudsys-logo.png" width="794" height="243" alt="CloudSys"></a>
      <nav class="admin-context-nav" aria-label="Administrator navigation">
        <a href="/admin/"<?= $active === 'controls' ? ' aria-current="page"' : '' ?>>Website controls</a>
        <a href="/admin/articles.php"<?= $active === 'articles' ? ' aria-current="page"' : '' ?>>Articles</a>
        <a href="/admin/administrators.php"<?= $active === 'administrators' ? ' aria-current="page"' : '' ?>>Administrators</a>
      </nav>
      <details class="admin-profile">
        <summary><span class="admin-profile-avatar" aria-hidden="true"><?= $initial ?></span><span><?= $name ?></span></summary>
        <div class="admin-profile-menu">
          <a href="/change-password">Change password</a>
          <form method="post" action="/logout.php"><input type="hidden" name="csrf" value="<?= $csrf ?>"><button type="submit">Sign out</button></form>
        </div>
      </details>
    </header>
    <script src="/admin-nav.js?v=20260906-3" defer></script>
    <?php
}
