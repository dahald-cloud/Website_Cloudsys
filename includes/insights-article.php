<?php
declare(strict_types=1);
require_once __DIR__ . '/insights-view.php';

function cloudsys_render_insights_article(array $article): void
{
?>
<article class="insights-article">
<a class="insights-back" href="/insights">← All Insights</a>
<div><a href="<?= cloudsys_article_escape(cloudsys_insights_url('', $article['category_slug'])) ?>" aria-label="<?= cloudsys_article_escape('More articles about ' . $article['category_name']) ?>"><?= cloudsys_insights_badge($article['category_slug'], $article['category_name']) ?></a></div>
<h1><?= cloudsys_article_escape($article['title']) ?></h1>
<p class="insights-article-meta"><span>By <?= cloudsys_article_escape($article['author_name']) ?></span><?php if (!empty($article['published_at'])): ?><time datetime="<?= cloudsys_article_escape(substr($article['published_at'], 0, 10)) ?>"><?= cloudsys_article_escape(gmdate('j F Y', strtotime($article['published_at'] . ' UTC'))) ?></time><?php else: ?><span>Not yet published</span><?php endif; ?></p>
<p class="insights-article-intro"><?= cloudsys_article_escape($article['summary']) ?></p>
<?php if ($article['cover_image_path']): ?><img class="insights-article-cover" src="/article-media.php?id=<?= (int) $article['id'] ?>" alt="<?= cloudsys_article_escape($article['cover_image_alt']) ?>" width="1600" height="1000"><?php endif; ?>
<div class="insights-prose">
<?= cloudsys_article_body($article['body_text']) ?>
</div>
<aside class="insights-article-cta"><h2>What does this mean for your business?</h2><p>Talk through your NetSuite or automation questions with a CloudSys expert.</p><a href="/#contact">Click here to reach out →</a></aside>
</article>
<?php
}
