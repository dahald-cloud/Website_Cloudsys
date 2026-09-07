<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-pages.php';
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD'); http_response_code(405); exit; }
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$slug = preg_match('~\A/([a-z0-9]+(?:-[a-z0-9]+)*)\.html\z~D', $path, $match) ? $match[1] : '';
cloudsys_render_static_page($slug);
