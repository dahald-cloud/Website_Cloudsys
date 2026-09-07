<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/insights-view.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit;
}
try {
    if (!cloudsys_page_is_visible('insights')) { http_response_code(404); header('X-Robots-Tag: noindex, nofollow'); require __DIR__ . '/404.php'; exit; }
} catch (Throwable $error) { error_log('Insights visibility failed: ' . $error->getMessage()); http_response_code(503); exit('Insights are temporarily unavailable.'); }
$errorMessage = '';
$filters = ['q' => '', 'category' => '', 'page' => 1];
$categories = [];
$results = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
try {
    $filters = cloudsys_insights_filters($_GET);
    $categories = cloudsys_public_categories();
    if ($filters['category'] !== '' && !in_array($filters['category'], array_column($categories, 'slug'), true)) {
        http_response_code(404);
        $errorMessage = 'That topic could not be found. Browse all Insights instead.';
    } else {
        $results = cloudsys_public_articles($filters);
        if ($filters['page'] !== $results['page']) {
            header('Location: ' . cloudsys_insights_url($filters['q'], $filters['category'], $results['page']), true, 302);
            exit;
        }
    }
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    $errorMessage = $error->getMessage();
} catch (Throwable $error) {
    error_log('CloudSys Insights listing failed: ' . $error->getMessage());
    http_response_code(503);
    header('Retry-After: 300');
    $errorMessage = 'Insights are temporarily unavailable. Please try again shortly.';
}
if ($errorMessage !== '' || $_GET) header('X-Robots-Tag: noindex, follow');
cloudsys_insights_head('Insights', 'Practical perspectives on NetSuite, ERP workflows, AI, and business automation from CloudSys.', 'https://cloudsysllc.com/insights');
?>
<main id="main-content" class="insights-main">
<div class="insights-intro"><p class="insights-eyebrow">THE CLOUDSYS PERSPECTIVE</p><h1>Ideas for a business<br>that <em>works better.</em></h1><p>NetSuite know-how, thoughtful automation, and lessons from solving real business problems.</p></div>
<section class="insights-browser" aria-label="Browse Insights">
<form class="insights-search" method="get" action="/insights" role="search">
<label for="insights-query">Search Insights</label>
<div><input id="insights-query" name="q" type="search" value="<?= cloudsys_article_escape($filters['q']) ?>" placeholder="Search topics, questions, or keywords" maxlength="100"><button type="submit">Search <span aria-hidden="true">→</span></button></div>
<?php if ($filters['category'] !== ''): ?><input type="hidden" name="category" value="<?= cloudsys_article_escape($filters['category']) ?>"><?php endif; ?>
</form>
<nav class="insights-filters" aria-label="Filter by topic">
<a href="<?= cloudsys_article_escape(cloudsys_insights_url($filters['q'])) ?>" <?= $filters['category'] === '' ? 'aria-current="true"' : '' ?>>All topics</a>
<?php foreach ($categories as $category): ?>
<a href="<?= cloudsys_article_escape(cloudsys_insights_url($filters['q'], $category['slug'])) ?>" <?= $filters['category'] === $category['slug'] ? 'aria-current="true"' : '' ?>><?= cloudsys_insights_badge($category['slug'], $category['name']) ?></a>
<?php endforeach; ?></nav>
</section>
<?php if ($errorMessage !== ''): ?>
<section class="insights-empty"><h2>Let’s try that again.</h2><p><?= cloudsys_article_escape($errorMessage) ?></p><a href="/insights">Back to all Insights →</a></section>
<?php else: ?>
<p class="insights-result-count"><?= (int) $results['total'] ?> <?= $results['total'] === 1 ? 'article' : 'articles' ?><?= $filters['q'] !== '' ? ' matching “' . cloudsys_article_escape($filters['q']) . '”' : '' ?><?php if ($filters['q'] !== '' || $filters['category'] !== ''): ?> · <a href="/insights">Clear filters</a><?php endif; ?></p>
<?php if (!$results['items']): ?>
<section class="insights-empty"><h2><?= $filters['q'] !== '' || $filters['category'] !== '' ? 'No articles found.' : 'Fresh perspectives are on the way.' ?></h2><p><?= $filters['q'] !== '' || $filters['category'] !== '' ? 'Try a different keyword or browse all topics.' : 'We’re preparing practical articles on NetSuite and AI. Check back soon.' ?></p><?php if ($filters['q'] !== '' || $filters['category'] !== ''): ?><a href="/insights">Browse all Insights →</a><?php endif; ?></section>
<?php else: cloudsys_insights_cards($results['items']); endif; ?>
<?php if ($results['pages'] > 1): ?><nav class="insights-pagination" aria-label="Article pages">
<?php if ($results['page'] > 1): ?><a rel="prev" href="<?= cloudsys_article_escape(cloudsys_insights_url($filters['q'], $filters['category'], $results['page'] - 1)) ?>">← Previous</a><?php endif; ?>
<span>Page <?= (int) $results['page'] ?> of <?= (int) $results['pages'] ?></span>
<?php if ($results['page'] < $results['pages']): ?><a rel="next" href="<?= cloudsys_article_escape(cloudsys_insights_url($filters['q'], $filters['category'], $results['page'] + 1)) ?>">Next →</a><?php endif; ?>
</nav><?php endif; endif; ?>
</main>
<?php cloudsys_insights_footer(); ?>
