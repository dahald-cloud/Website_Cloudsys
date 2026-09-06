<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/articles.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
try {
    cloudsys_require_admin();
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
<link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin-styles.css">
<link rel="stylesheet" href="/article-admin.css">
</head><body class="admin-dashboard-shell"><header class="admin-header"><a href="/admin/">Back to website controls</a></header>
<main class="admin-dashboard article-workspace"><p class="admin-eyebrow">ARTICLES</p><h1>Your articles</h1>
<p>Total: <?= (int) $counts['total'] ?> · Drafts: <?= (int) $counts['drafts'] ?> · Public: <?= (int) $counts['published'] ?></p>
<p><a href="/admin/edit-article.php">Create an article →</a></p>
<?php if (!$rows): ?><p>No articles yet. Create your first draft to get started.</p><?php else: ?>
<div class="article-table-wrap"><table class="article-table"><thead><tr><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= cloudsys_article_escape($row['title']) ?></td><td><?= cloudsys_article_escape($row['category_name']) ?></td><td><?= cloudsys_article_escape($row['status']) ?></td><td><div class="article-list-actions"><a href="/admin/edit-article.php?id=<?= (int) $row['id'] ?>">Edit</a><a href="/admin/preview-article.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">Preview</a></div></td></tr><?php endforeach; ?>
</tbody></table></div><nav class="article-pagination" aria-label="Article pages"><?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>">Previous</a><?php endif; ?><span>Page <?= $page ?></span><?php if ($page * 20 < (int) $counts['total']): ?><a href="?page=<?= $page+1 ?>">Next</a><?php endif; ?></nav>
<?php endif; ?>
</main></body></html>
