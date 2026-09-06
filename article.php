<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/insights-article.php';
// No shared caching: unpublished content must not remain served from a cache.
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
// Read the actual path, not a user-overridable query parameter.
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (!is_string($path) || preg_match('~\A/insights/([a-z0-9]+(?:-[a-z0-9]+)*)\z~D', $path, $match) !== 1
    || !cloudsys_article_valid_slug($match[1])) {
    header('X-Robots-Tag: noindex');
    http_response_code(404);
    exit('Article not found.');
}
try {
    $article = cloudsys_published_article($match[1]);
} catch (Throwable $error) {
    error_log('CloudSys article read failed: ' . $error->getMessage());
    header('X-Robots-Tag: noindex');
    header('Retry-After: 300');
    http_response_code(503);
    exit('Articles are temporarily unavailable. Please try again later.');
}
if ($article === null) {
    header('X-Robots-Tag: noindex');
    http_response_code(404);
    exit('Article not found.');
}
$title = $article['seo_title'] !== '' ? $article['seo_title'] : $article['title'];
$description = $article['seo_description'] !== '' ? $article['seo_description'] : $article['summary'];
$canonical = 'https://cloudsysllc.com' . cloudsys_article_url($article['slug']);
// Related content is optional: a failure here must not hide the requested article.
$related = [];
try {
    $related = cloudsys_related_articles((int) $article['id'], $article['category_slug']);
} catch (Throwable $error) {
    error_log('CloudSys related articles failed: ' . $error->getMessage());
}
cloudsys_insights_head($title, $description, $canonical, $article);
?>
<main id="main-content" class="insights-main">
<?php cloudsys_render_insights_article($article); ?>
<?php if ($related): ?><section class="insights-related" aria-labelledby="related-heading"><h2 id="related-heading">More on this topic</h2><?php cloudsys_insights_cards($related); ?></section><?php endif; ?>
</main>
<?php cloudsys_insights_footer(); ?>
