<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/article-editor.php';
require_once dirname(__DIR__) . '/includes/insights-view.php';
require_once dirname(__DIR__) . '/includes/sitemap.php';
function insights_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
foreach (['netsuite-tip', 'ai', '2026-workflows', str_repeat('a', 160)] as $slug) {
    insights_check(cloudsys_article_valid_slug($slug), 'Valid slug rejected.');
}
foreach (['', 'Uppercase', '../draft', 'two--hyphens', '-leading', 'trailing-', 'a/b', 'a?preview=1', str_repeat('a', 161)] as $slug) {
    insights_check(!cloudsys_article_valid_slug($slug), 'Unsafe slug accepted.');
}
insights_check(cloudsys_article_url('netsuite-tip') === '/insights/netsuite-tip', 'Wrong URL.');
insights_check(cloudsys_article_escape('<script>alert("x")</script>') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', 'Unsafe HTML escaping.');
echo "Slug, URL, and escaping checks passed.\n";
insights_check(str_contains(cloudsys_article_body("## Heading\n\n- One\n- Two"), '<h2>Heading</h2>'), 'Heading rendering failed.');
insights_check(str_contains(cloudsys_article_body('- One'), '<ul><li>One</li></ul>'), 'List rendering failed.');
insights_check(str_contains(cloudsys_article_body('**Bold**'), '<strong>Bold</strong>'), 'Bold rendering failed.');
insights_check(str_contains(cloudsys_article_body('[Help](https://example.com)'), 'href="https://example.com"'), 'Safe link rejected.');
foreach (['<script>alert(1)</script>', '<img src=x onerror=alert(1)>', '[bad](javascript:alert(1))', '[bad](data:text/html,test)'] as $payload) {
    $rendered = cloudsys_article_body($payload);
    insights_check(!str_contains($rendered, '<script') && !str_contains($rendered, '<img') && !str_contains($rendered, 'href='), 'Unsafe content interpreted.');
}
echo "Formatting and HTML-injection checks passed.\n";
insights_check(cloudsys_editor_field(['title' => '  Test  '], 'title', 10, true) === 'Test', 'Field trimming failed.');
foreach ([['title' => []], ['title' => ''], ['title' => str_repeat('x', 11)], ['title' => "bad\0text"]] as $badField) {
    $rejected = false;
    try { cloudsys_editor_field($badField, 'title', 10, true); } catch (DomainException $error) { $rejected = true; }
    insights_check($rejected, 'Invalid editor input accepted.');
}
insights_check(cloudsys_article_upload([]) === null, 'No-file upload should be optional.');
insights_check(cloudsys_article_cover_crop(2000, 1000) === [200, 0, 1600, 1000], 'Wide cover crop is incorrect.');
insights_check(cloudsys_article_cover_crop(1000, 1000) === [0, 187, 1000, 625], 'Tall cover crop is incorrect.');
$rejected = false;
try { cloudsys_article_upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__]); } catch (DomainException $error) { $rejected = true; }
insights_check($rejected, 'Non-uploaded local file accepted.');
echo "Editor field and upload boundary checks passed.\n";

insights_check(cloudsys_insights_filters([]) === ['q' => '', 'category' => '', 'page' => 1], 'Default public filters failed.');
insights_check(cloudsys_insights_filters(['q' => '  NetSuite  ', 'page' => '2'])['q'] === 'NetSuite', 'Search trimming failed.');
foreach ([['q' => []], ['q' => str_repeat('x', 101)], ['q' => "bad\0query"], ['q' => "\xff"], ['category' => '../draft'], ['category' => []], ['page' => []], ['page' => '0'], ['page' => '10001'], ['page' => '1 OR 1=1']] as $input) {
    $rejected = false;
    try { cloudsys_insights_filters($input); } catch (InvalidArgumentException $error) { $rejected = true; }
    insights_check($rejected, 'Invalid public filters accepted.');
}
insights_check(cloudsys_insights_like('50%_!') === '%50!%!_!!%', 'Search wildcards not escaped.');
insights_check(cloudsys_insights_url('AI & ERP', 'netsuite', 2) === '/insights?q=AI%20%26%20ERP&category=netsuite&page=2', 'Filter URL encoding failed.');
insights_check(cloudsys_insights_url() === '/insights', 'Default listing URL failed.');
insights_check(!str_contains(cloudsys_insights_badge('bad\" onclick=\"evil', '<script>'), '<script>'), 'Badge escaping failed.');
ob_start();
cloudsys_insights_cards([['id' => 1, 'slug' => 'test', 'title' => '<script>title</script>', 'summary' => '<img src=x>', 'author_name' => 'Test', 'published_at' => '2026-01-01 00:00:00', 'cover_image_path' => 'test.jpg', 'cover_image_alt' => '\" onerror=\"alert(1)', 'category_slug' => 'netsuite', 'category_name' => 'NetSuite']]);
$cards = ob_get_clean();
insights_check(!str_contains($cards, '<script>') && !str_contains($cards, 'alt="" onerror='), 'Card HTML injection.');
insights_check(str_contains($cards, '/insights/test') && str_contains($cards, '&lt;img src=x&gt;'), 'Card rendering failed.');
echo "Public filter, wildcard, URL, badge, and card-rendering checks passed.\n";

$seoArticle = ['id' => 7, 'slug' => 'safe-title', 'title' => '</script><script>alert(1)</script>', 'summary' => 'A & B', 'author_name' => 'CloudSys', 'published_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-02 10:00:00', 'category_name' => 'NetSuite', 'cover_image_path' => null];
$schema = cloudsys_article_schema($seoArticle);
$json = cloudsys_schema_json($schema);
insights_check(!str_contains($json, '<script') && !str_contains($json, '</script>'), 'Structured data can terminate script block.');
insights_check(json_decode($json, true, 512, JSON_THROW_ON_ERROR)['@graph'][0]['headline'] === $seoArticle['title'], 'Schema round trip failed.');
insights_check($schema['@graph'][0]['datePublished'] === '2026-01-01T10:00:00Z', 'Schema UTC date failed.');
insights_check($schema['@graph'][0]['author']['@type'] === 'Organization', 'Organization author failed.');
insights_check(!isset($schema['@graph'][0]['image']), 'Invented schema image.');
$seoArticle['author_name'] = 'Example Author'; $seoArticle['cover_image_path'] = 'image.jpg';
insights_check(cloudsys_article_schema($seoArticle)['@graph'][0]['author']['@type'] === 'Person', 'Person author failed.');
insights_check(cloudsys_article_schema($seoArticle)['@graph'][0]['image'] === 'https://cloudsysllc.com/article-media.php?id=7', 'Article image URL failed.');
$xml = cloudsys_sitemap_xml([['loc' => 'https://cloudsysllc.com/insights?q=a&b=c']]);
insights_check(str_contains($xml, 'a&amp;b=c') && str_contains($xml, '<urlset'), 'Sitemap escaping failed.');
insights_check(str_contains(cloudsys_sitemap_xml([], true), '<sitemapindex'), 'Sitemap index rendering failed.');
echo "Article schema and sitemap rendering checks passed.\n";

// Optional integration test: explicitly point CLOUDSYS_CONFIG at a test database.
if (getenv('CLOUDSYS_INSIGHTS_TEST_DB') !== '1') {
    echo "Database checks skipped. Enable only with a dedicated test database.\n";
    exit(0);
}
$db = cloudsys_db();
$db->beginTransaction();
try {
    $sitemapCount = cloudsys_sitemap_article_count();
    $categoryId = (int) $db->query("SELECT id FROM article_categories WHERE slug = 'netsuite'")->fetchColumn();
    insights_check($categoryId > 0, 'Install migrate-insights.sql in the test database first.');
    $slug = 'test-' . bin2hex(random_bytes(12));
    $statement = $db->prepare('INSERT INTO articles (category_id, slug, title, body_text) VALUES (?, ?, ?, ?)');
    $statement->execute([$categoryId, $slug, $slug, '<script>not executable</script>']);
    insights_check(cloudsys_published_article($slug) === null, 'Draft leaked.');
    insights_check(cloudsys_sitemap_article_count() === $sitemapCount, 'Draft leaked into sitemap count.');
    insights_check(cloudsys_public_articles(['q' => $slug])['total'] === 0, 'Draft leaked through search.');
    $statement = $db->prepare("UPDATE articles SET status = 'published', published_at = NULL WHERE slug = ?");
    $statement->execute([$slug]);
    insights_check(cloudsys_published_article($slug) === null, 'Undated article leaked.');
    $statement = $db->prepare('UPDATE articles SET published_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE slug = ?');
    $statement->execute([$slug]);
    insights_check(cloudsys_published_article($slug) === null, 'Future article leaked.');
    insights_check(cloudsys_sitemap_article_count() === $sitemapCount, 'Future article leaked into sitemap count.');
    insights_check(cloudsys_public_articles(['q' => $slug])['total'] === 0, 'Future article leaked through search.');
    $statement = $db->prepare('UPDATE articles SET published_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE slug = ?');
    $statement->execute([$slug]);
    insights_check(cloudsys_published_article($slug) !== null, 'Published article unavailable.');
    insights_check(cloudsys_sitemap_article_count() === $sitemapCount + 1, 'Published article missing from sitemap count.');
    $sitemapRows = cloudsys_sitemap_articles((int) ceil(($sitemapCount + 1) / 1000));
    insights_check(in_array($slug, array_column($sitemapRows, 'slug'), true), 'Published article missing from sitemap page.');
    $statement = $db->prepare('UPDATE articles SET title = ? WHERE slug = ?');
    $statement->execute([$slug, $slug]);
    $listing = cloudsys_public_articles(['q' => $slug, 'category' => 'netsuite', 'page' => 2]);
    insights_check($listing['total'] === 1 && $listing['page'] === 1 && $listing['items'][0]['slug'] === $slug, 'Published search/category/pagination failed.');
    $related = cloudsys_related_articles((int) $listing['items'][0]['id'], 'netsuite');
    insights_check(!in_array($slug, array_column($related, 'slug'), true), 'Related articles included self.');
    $statement = $db->prepare("UPDATE articles SET status = 'draft' WHERE slug = ?");
    $statement->execute([$slug]);
    insights_check(cloudsys_published_article($slug) === null, 'Unpublished article leaked.');
    insights_check(cloudsys_sitemap_article_count() === $sitemapCount, 'Unpublished article remains in sitemap count.');
    insights_check(cloudsys_public_articles(['q' => $slug])['total'] === 0, 'Unpublished article leaked through search.');
    insights_check(cloudsys_published_article($slug . '-missing') === null, 'Missing article returned data.');
    echo "Database visibility checks passed. Test rows rolled back.\n";
} finally {
    $db->rollBack();
}
