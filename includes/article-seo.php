<?php
declare(strict_types=1);
require_once __DIR__ . '/articles.php';

function cloudsys_article_schema(array $article): array
{
    $url = 'https://cloudsysllc.com' . cloudsys_article_url($article['slug']);
    $author = ['@type' => $article['author_name'] === 'CloudSys' ? 'Organization' : 'Person', 'name' => $article['author_name']];
    $posting = [
        '@type' => 'BlogPosting', '@id' => $url . '#article', 'url' => $url,
        'mainEntityOfPage' => $url, 'headline' => $article['title'], 'description' => $article['summary'],
        'datePublished' => str_replace(' ', 'T', $article['published_at']) . 'Z',
        'dateModified' => str_replace(' ', 'T', max($article['published_at'], $article['updated_at'])) . 'Z',
        'author' => $author, 'publisher' => ['@type' => 'Organization', 'name' => 'CloudSys', 'url' => 'https://cloudsysllc.com/'],
        'articleSection' => $article['category_name'], 'inLanguage' => 'en',
    ];
    if (!empty($article['cover_image_path'])) $posting['image'] = 'https://cloudsysllc.com/article-media.php?id=' . (int) $article['id'];
    return ['@context' => 'https://schema.org', '@graph' => [$posting, [
        '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://cloudsysllc.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Insights', 'item' => 'https://cloudsysllc.com/insights'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title'], 'item' => $url],
        ],
    ]]];
}

function cloudsys_schema_json(array $schema): string
{
    return json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
