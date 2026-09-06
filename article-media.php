<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/article-format.php';
header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff'); header('X-Robots-Tag: noindex');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD'); http_response_code(405); exit; }
try {
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) { http_response_code(404); exit; }
    $query = cloudsys_db()->prepare("SELECT cover_image_path, (status='published' AND published_at IS NOT NULL AND published_at <= UTC_TIMESTAMP()) AS is_public FROM articles WHERE id=?");
    $query->execute([$id]); $row = $query->fetch();
    if (!$row) { http_response_code(404); exit; }
    if (!(int) $row['is_public']) {
        $admin = cloudsys_current_admin();
        if (!$admin || (int) $admin['must_change_password'] === 1) { http_response_code(404); exit; }
    }
    $name = (string) ($row['cover_image_path'] ?? '');
    if (!preg_match('/\A[a-f0-9]{48}\.jpg\z/D', $name)) { http_response_code(404); exit; }
    $path = cloudsys_article_media_directory() . DIRECTORY_SEPARATOR . $name;
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg'); header('Content-Length: ' . filesize($path));
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($path);
} catch (Throwable $error) { error_log('Article image: ' . $error->getMessage()); http_response_code(503); }
