<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/article-format.php';
require_once __DIR__ . '/includes/site-pages.php';
header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff'); header('X-Robots-Tag: noindex');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD'); http_response_code(405); exit; }
try {
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) { http_response_code(404); exit; }
    $query = cloudsys_db()->prepare("SELECT m.file_path, (a.status='published' AND a.published_at IS NOT NULL AND a.published_at <= UTC_TIMESTAMP()) AS is_public FROM article_media m INNER JOIN articles a ON a.id=m.article_id WHERE m.id=?");
    $query->execute([$id]); $row = $query->fetch();
    if (!$row) { http_response_code(404); exit; }
    $admin = cloudsys_current_admin();
    $adminAllowed = $admin && (int) $admin['must_change_password'] !== 1;
    if (!(int) $row['is_public'] || !cloudsys_page_is_visible('insights')) { if (!$adminAllowed) { http_response_code(404); exit; } }
    $name = (string) $row['file_path'];
    if (!preg_match('/\A[a-f0-9]{48}\.jpg\z/D', $name)) { http_response_code(404); exit; }
    $path = cloudsys_article_media_directory() . DIRECTORY_SEPARATOR . $name;
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg'); header('Content-Length: ' . filesize($path));
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($path);
} catch (Throwable $error) { error_log('Article inline image: ' . $error->getMessage()); http_response_code(503); }
