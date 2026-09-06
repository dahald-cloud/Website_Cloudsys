<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/articles.php';
require_once dirname(__DIR__) . '/includes/admin-ui.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
try {
    $admin = cloudsys_require_admin();
    $counts = cloudsys_admin_article_counts();
    $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
    $page = min($page, max(1, (int) ceil((int) $counts['total'] / 20)));
    $query = cloudsys_db()->prepare('SELECT a.id, a.title, a.status, a.updated_at, c.name AS category_name FROM articles a JOIN article_categories c ON c.id=a.category_id ORDER BY a.updated_at DESC, a.id DESC LIMIT 20 OFFSET ?');
    $query->bindValue(1, ($page - 1) * 20, PDO::PARAM_INT);
    $query->execute(); $rows = $query->fetchAll();
} catch (Throwable $error) {
    error_log('CloudSys articles admin unavailable: ' . $error->getMessage());
    http_response_code(503);
    exit('Article administration is unavailable. Check that the Insights database migration has been installed.');
}
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Articles | CloudSys admin</title><meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="/style.css?v=20260906-3"><link rel="stylesheet" href="/admin-styles.css?v=20260906-3">
<link rel="stylesheet" href="/article-admin.css?v=20260906-3">
</head><body class="admin-dashboard-shell"><?php cloudsys_admin_header($admin, 'articles'); ?>
<main class="admin-dashboard article-workspace"><p class="admin-eyebrow">ARTICLES</p><h1>Your articles</h1>
<p>Total: <?= (int) $counts['total'] ?> · Drafts: <?= (int) $counts['drafts'] ?> · Public: <?= (int) $counts['published'] ?></p>
<p><a class="admin-primary-link article-create-link" href="/admin/edit-article.php">Create an article <span>→</span></a></p>
<?php if (!$rows): ?><section class="admin-empty-state"><p class="admin-eyebrow">NO ARTICLES YET</p><h2>Start with your first useful insight.</h2><p>Create a draft, preview it privately, and publish only when it is ready.</p><a href="/admin/edit-article.php">Create your first draft →</a></section><?php else: ?>
<div class="article-table-wrap"><table class="article-table"><thead><tr><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= cloudsys_article_escape($row['title']) ?></td><td><?= cloudsys_article_escape($row['category_name']) ?></td><td><?= cloudsys_article_escape($row['status']) ?></td><td><div class="article-list-actions"><a href="/admin/edit-article.php?id=<?= (int) $row['id'] ?>">Edit</a><a href="/admin/preview-article.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">Preview</a></div></td></tr><?php endforeach; ?>
</tbody></table></div><nav class="article-pagination" aria-label="Article pages"><?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>">Previous</a><?php endif; ?><span>Page <?= $page ?></span><?php if ($page * 20 < (int) $counts['total']): ?><a href="?page=<?= $page+1 ?>">Next</a><?php endif; ?></nav>
<?php endif; ?>
</main></body></html>
