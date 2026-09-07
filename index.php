<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-pages.php';
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD'); http_response_code(405); exit; }
try {
    $html = file_get_contents(__DIR__ . '/index.html');
    if ($html === false) throw new RuntimeException('Homepage source unavailable.');
    header('Cache-Control: no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo cloudsys_filter_hidden_links($html, cloudsys_page_visibility());
} catch (Throwable $error) {
    error_log('CloudSys homepage gate failed: ' . $error->getMessage());
    http_response_code(503); header('Retry-After: 300'); echo 'The website is temporarily unavailable.';
}
