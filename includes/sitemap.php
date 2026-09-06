<?php
declare(strict_types=1);
require_once __DIR__ . '/articles.php';

function cloudsys_sitemap_article_count(): int
{
    return (int) cloudsys_db()->query("SELECT COUNT(*) FROM articles WHERE status = 'published' AND published_at <= UTC_TIMESTAMP()")->fetchColumn();
}

function cloudsys_sitemap_articles(int $page): array
{
    if ($page < 1 || $page > 50000) throw new InvalidArgumentException('Invalid sitemap page.');
    $statement = cloudsys_db()->prepare("SELECT slug, published_at, updated_at FROM articles
        WHERE status = 'published' AND published_at <= UTC_TIMESTAMP()
        ORDER BY id LIMIT 1000 OFFSET ?");
    $statement->bindValue(1, ($page - 1) * 1000, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function cloudsys_sitemap_xml(array $rows, bool $index = false): string
{
    $root = $index ? 'sitemapindex' : 'urlset';
    $item = $index ? 'sitemap' : 'url';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<" . $root . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($rows as $row) {
        $xml .= '<' . $item . '><loc>' . htmlspecialchars($row['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
        if (!empty($row['lastmod'])) $xml .= '<lastmod>' . htmlspecialchars($row['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</lastmod>';
        $xml .= '</' . $item . '>';
    }
    return $xml . '</' . $root . '>';
}
