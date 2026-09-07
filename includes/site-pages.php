<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function cloudsys_managed_pages(): array
{
    return [
        'netsuite-optimization' => ['label' => 'NetSuite Optimization', 'file' => 'netsuite-optimization.html', 'path' => '/netsuite-optimization.html'],
        'ai-agents' => ['label' => 'AI & Automation', 'file' => 'ai-agents.html', 'path' => '/ai-agents.html'],
        'case-studies' => ['label' => 'Client Results', 'file' => 'case-studies.html', 'path' => '/case-studies.html'],
        'managed-support' => ['label' => 'Managed Support', 'file' => 'managed-support.html', 'path' => '/managed-support.html'],
        'insights' => ['label' => 'Insights', 'file' => null, 'path' => '/insights'],
        'about' => ['label' => 'About', 'file' => 'about.html', 'path' => '/about.html'],
        'integrations-development' => ['label' => 'Development', 'file' => 'integrations-development.html', 'path' => '/integrations-development.html'],
        'faq' => ['label' => 'FAQ', 'file' => 'faq.html', 'path' => '/faq.html'],
    ];
}

function cloudsys_page_visibility(): array
{
    $visibility = array_fill_keys(array_keys(cloudsys_managed_pages()), true);
    $rows = cloudsys_db()->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'page_visible_%'")->fetchAll();
    foreach ($rows as $row) {
        $slug = substr((string) $row['setting_key'], 13);
        if (array_key_exists($slug, $visibility)) $visibility[$slug] = (string) $row['setting_value'] === '1';
    }
    return $visibility;
}

function cloudsys_page_is_visible(string $slug): bool
{
    $visibility = cloudsys_page_visibility();
    return $visibility[$slug] ?? false;
}

function cloudsys_public_navigation(string $active = ''): void
{
    $pages = cloudsys_managed_pages();
    $visibility = cloudsys_page_visibility();
    foreach (['netsuite-optimization', 'ai-agents', 'case-studies', 'managed-support', 'insights', 'about'] as $slug) {
        if (!($visibility[$slug] ?? false)) continue;
        $page = $pages[$slug];
        echo '<a href="' . htmlspecialchars($page['path'], ENT_QUOTES, 'UTF-8') . '"' . ($active === $slug ? ' aria-current="page"' : '') . '>' . htmlspecialchars($page['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }
}

function cloudsys_filter_hidden_links(string $html, array $visibility): string
{
    foreach (cloudsys_managed_pages() as $slug => $page) {
        if ($visibility[$slug] ?? true) continue;
        $path = preg_quote(ltrim((string) $page['path'], '/'), '~');
        $html = preg_replace('~<a\b[^>]*href=(["\x27])(?:\./|/)?' . $path . '\1[^>]*>.*?</a>~is', '', $html);
    }
    return $html;
}

function cloudsys_filter_sitemap_pages(string $xml, array $visibility): string
{
    foreach (cloudsys_managed_pages() as $slug => $page) {
        if ($visibility[$slug] ?? true) continue;
        $path = preg_quote('https://cloudsysllc.com' . $page['path'], '~');
        $xml = preg_replace('~\s*<url>\s*<loc>' . $path . '</loc>.*?</url>~is', '', $xml);
    }
    return $xml;
}

function cloudsys_render_static_page(string $slug): never
{
    $pages = cloudsys_managed_pages();
    if (!isset($pages[$slug]) || !is_string($pages[$slug]['file'])) { http_response_code(404); require dirname(__DIR__) . '/404.php'; exit; }
    try {
        $visibility = cloudsys_page_visibility();
        if (!($visibility[$slug] ?? false)) { http_response_code(404); header('X-Robots-Tag: noindex, nofollow'); require dirname(__DIR__) . '/404.php'; exit; }
        $html = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . $pages[$slug]['file']);
        if ($html === false) throw new RuntimeException('Page source is unavailable.');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo cloudsys_filter_hidden_links($html, $visibility);
        exit;
    } catch (Throwable $error) {
        error_log('CloudSys page gate failed: ' . $error->getMessage());
        http_response_code(503); header('Retry-After: 300'); exit('This page is temporarily unavailable.');
    }
}
