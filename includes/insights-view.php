<?php
declare(strict_types=1);
require_once __DIR__ . '/article-format.php';
require_once __DIR__ . '/article-seo.php';
require_once __DIR__ . '/site-pages.php';

function cloudsys_insights_badge(string $slug, string $name): string
{
    $known = ['netsuite', 'ai-automation', 'problems-solved'];
    $class = in_array($slug, $known, true) ? $slug : 'other';
    if ($slug === 'problems-solved') $name = 'Problems We’ve Solved';
    return '<span class="insights-badge badge-' . $class . '">' . cloudsys_article_escape($name) . '</span>';
}

function cloudsys_insights_head(string $title, string $description, string $canonical, ?array $article = null): void
{
    ?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= cloudsys_article_escape($title) ?> | CloudSys</title>
<meta name="description" content="<?= cloudsys_article_escape($description) ?>">
<?php if ($canonical !== ''): ?>
<link rel="canonical" href="<?= cloudsys_article_escape($canonical) ?>">
<meta property="og:type" content="<?= $article ? 'article' : 'website' ?>">
<meta property="og:title" content="<?= cloudsys_article_escape($title) ?> | CloudSys">
<meta property="og:description" content="<?= cloudsys_article_escape($description) ?>">
<meta property="og:url" content="<?= cloudsys_article_escape($canonical) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= cloudsys_article_escape($title) ?> | CloudSys">
<meta name="twitter:description" content="<?= cloudsys_article_escape($description) ?>">
<?php else: ?><meta name="robots" content="noindex,nofollow,noarchive"><?php endif; ?>
<?php if ($article): ?><script type="application/ld+json"><?= cloudsys_schema_json(cloudsys_article_schema($article)) ?></script><?php endif; ?>
<link rel="icon" href="/assets/cloudsys-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&family=Newsreader:ital,wght@1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/form-styles.css"><link rel="stylesheet" href="/accessibility.css"><link rel="stylesheet" href="/insights.css?v=20260907-1">
<script src="/cookie-consent.js" defer></script>
<script src="/form.js" defer></script><link rel="stylesheet" href="/navigation.css">
</head><body class="insights-page">
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header"><a class="brand" href="/" aria-label="CloudSys home"><img src="/assets/cloudsys-logo.png" width="794" height="243" alt="CloudSys"></a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Open navigation"><span></span><span></span><span></span></button><nav id="primary-nav" aria-label="Primary navigation"><?php cloudsys_public_navigation('insights'); ?></nav><a class="header-contact" href="/#contact">Talk to an expert <span aria-hidden="true">↗</span></a></header>
<?php
}

function cloudsys_insights_footer(): void
{
    ?>
<footer><a href="/">CloudSys home</a><nav class="footer-links" aria-label="Explore and legal"><?php $visible = cloudsys_page_visibility(); if ($visible['insights'] ?? false): ?><a href="/insights">Insights</a><?php endif; ?><?php if ($visible['integrations-development'] ?? false): ?><a href="/integrations-development.html">Development</a><?php endif; ?><a href="/privacy.html">Privacy Policy</a><a href="/cookies.html">Cookie Policy</a><button type="button" data-manage-cookies>Cookie Preferences</button></nav></footer>
</body></html>
<?php
}

function cloudsys_insights_cards(array $items): void
{
    ?><div class="insights-grid"><?php foreach ($items as $item): ?>
<article class="insight-card">
<?php if ($item['cover_image_path']): ?><img class="insight-card-image" src="/article-media.php?id=<?= (int) $item['id'] ?>" alt="<?= cloudsys_article_escape($item['cover_image_alt']) ?>" loading="lazy" decoding="async" width="640" height="400"><?php endif; ?>
<div class="insight-card-content">
<?= cloudsys_insights_badge($item['category_slug'], $item['category_name']) ?>
<h2><a href="<?= cloudsys_article_escape(cloudsys_article_url($item['slug'])) ?>"><?= cloudsys_article_escape($item['title']) ?></a></h2>
<p><?= cloudsys_article_escape($item['summary']) ?></p>
<div class="insight-card-meta"><time datetime="<?= cloudsys_article_escape(substr($item['published_at'], 0, 10)) ?>"><?= cloudsys_article_escape(gmdate('j M Y', strtotime($item['published_at'] . ' UTC'))) ?></time><span aria-hidden="true">↗</span></div>
</div></article>
<?php endforeach; ?></div><?php
}
