<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/article-editor.php';
require_once dirname(__DIR__) . '/includes/insights-article.php';
header('Cache-Control: no-store, private'); header('X-Robots-Tag: noindex, nofollow, noarchive');
try {
    cloudsys_require_admin();
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $article = $id ? cloudsys_editor_article($id) : null;
    if ($article) {
        $categoryQuery = cloudsys_db()->prepare('SELECT name, slug FROM article_categories WHERE id = ?');
        $categoryQuery->execute([(int) $article['category_id']]);
        $category = $categoryQuery->fetch();
        if (!$category) throw new RuntimeException('Article category not found.');
        $article['category_name'] = $category['name'];
        $article['category_slug'] = $category['slug'];
    }
} catch (Throwable $error) { error_log('Article preview: ' . $error->getMessage()); http_response_code(503); exit('Preview temporarily unavailable.'); }
if (!$article) { http_response_code(404); exit('Article not found.'); }
$e = 'cloudsys_article_escape';
cloudsys_insights_head('Private preview', 'Private saved article preview.', '');
?>
<main id="main-content" class="insights-main">
<aside class="insights-preview-notice"><p>Private preview · Saved version · <?= $e($article['status']) ?>. Related articles are not shown in this preview.</p><a href="/admin/edit-article.php?id=<?= (int) $id ?>">← Back to editor</a></aside>
<?php cloudsys_render_insights_article($article); ?>
</main>
<?php cloudsys_insights_footer(); ?>
