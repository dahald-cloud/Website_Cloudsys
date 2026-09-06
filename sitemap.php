<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/sitemap.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
try {
    if ($path === '/sitemap-pages.xml') {
        $xml = file_get_contents(__DIR__ . '/sitemap.xml');
        if ($xml === false) throw new RuntimeException('Static sitemap unavailable.');
    } elseif ($path === '/sitemap.xml') {
        $pages = (int) ceil(cloudsys_sitemap_article_count() / 1000);
        if ($pages > 49999) throw new RuntimeException('Sitemap index capacity exceeded.');
        $rows = [['loc' => 'https://cloudsysllc.com/sitemap-pages.xml']];
        for ($i = 1; $i <= $pages; $i++) $rows[] = ['loc' => 'https://cloudsysllc.com/sitemap-articles-' . $i . '.xml'];
        $xml = cloudsys_sitemap_xml($rows, true);
    } elseif (is_string($path) && preg_match('~\A/sitemap-articles-([1-9][0-9]{0,4})\.xml\z~D', $path, $match)) {
        $page = (int) $match[1];
        if ($page > (int) ceil(cloudsys_sitemap_article_count() / 1000)) { http_response_code(404); exit('Sitemap not found.'); }
        $rows = [];
        foreach (cloudsys_sitemap_articles($page) as $article) {
            $rows[] = ['loc' => 'https://cloudsysllc.com' . cloudsys_article_url($article['slug']),
                'lastmod' => str_replace(' ', 'T', max($article['published_at'], $article['updated_at'])) . 'Z'];
        }
        $xml = cloudsys_sitemap_xml($rows);
    } else { http_response_code(404); exit('Sitemap not found.'); }
    header('Content-Type: application/xml; charset=UTF-8');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $xml;
} catch (Throwable $error) {
    error_log('CloudSys sitemap failed: ' . $error->getMessage());
    http_response_code(503); header('Retry-After: 300'); header('Content-Type: text/plain; charset=UTF-8');
    echo 'Sitemap temporarily unavailable.';
}
