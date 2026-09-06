<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function cloudsys_article_valid_slug(string $slug): bool
{
    return strlen($slug) <= 160
        && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug) === 1;
}

function cloudsys_article_url(string $slug): string
{
    if (!cloudsys_article_valid_slug($slug)) throw new InvalidArgumentException('Invalid article slug.');
    return '/insights/' . $slug;
}

function cloudsys_article_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Public reads never accept a status override, preview flag, or admin identifier. */
function cloudsys_published_article(string $slug): ?array
{
    if (!cloudsys_article_valid_slug($slug)) return null;
    $statement = cloudsys_db()->prepare(
        "SELECT a.id, a.cover_image_path, a.cover_image_alt, a.slug, a.title, a.summary, a.body_text, a.author_name,
                a.seo_title, a.seo_description, a.published_at, a.updated_at,
                c.name AS category_name, c.slug AS category_slug
         FROM articles a INNER JOIN article_categories c ON c.id = a.category_id
         WHERE a.slug = ? AND a.status = 'published'
           AND a.published_at IS NOT NULL AND a.published_at <= UTC_TIMESTAMP()
         LIMIT 1"
    );
    $statement->execute([$slug]);
    $article = $statement->fetch();
    return is_array($article) ? $article : null;
}

/** Guard belongs inside the admin data helper as well as the page controller. */
function cloudsys_admin_article_counts(): array
{
    cloudsys_require_admin();
    $statement = cloudsys_db()->query(
        "SELECT COUNT(*) AS total,
         COALESCE(SUM(status = 'draft'), 0) AS drafts,
         COALESCE(SUM(status = 'published' AND published_at <= UTC_TIMESTAMP()), 0) AS published
         FROM articles"
    );
    return $statement->fetch() ?: ['total' => 0, 'drafts' => 0, 'published' => 0];
}

/** Validate public filter inputs before they reach a query or template. */
function cloudsys_insights_filters(array $input): array
{
    $q = $input['q'] ?? '';
    $category = $input['category'] ?? '';
    $page = $input['page'] ?? '1';
    if (!is_string($q) || strlen($q) > 400 || preg_match('//u', $q) !== 1 || preg_match_all('/./us', $q) > 100
        || preg_match('/[\x00-\x1f\x7f]/', $q)
        || !is_string($category) || ($category !== '' && !cloudsys_article_valid_slug($category))
        || !is_scalar($page) || filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) === false) {
        throw new InvalidArgumentException('Please use a shorter search and a valid page number.');
    }
    return ['q' => trim($q), 'category' => $category, 'page' => (int) $page];
}

function cloudsys_insights_url(string $q = '', string $category = '', int $page = 1): string
{
    $params = [];
    if ($q !== '') $params['q'] = $q;
    if ($category !== '') $params['category'] = $category;
    if ($page > 1) $params['page'] = $page;
    return '/insights' . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
}

function cloudsys_insights_like(string $query): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
}

function cloudsys_public_categories(): array
{
    return cloudsys_db()->query("SELECT c.slug, c.name, COUNT(a.id) AS article_count
        FROM article_categories c LEFT JOIN articles a ON a.category_id = c.id
        AND a.status = 'published' AND a.published_at <= UTC_TIMESTAMP()
        GROUP BY c.id, c.slug, c.name, c.sort_order ORDER BY c.sort_order, c.id")->fetchAll();
}

/** Every public listing uses the same visibility boundary as the detail page. */
function cloudsys_public_articles(array $filters): array
{
    $filters = cloudsys_insights_filters($filters);
    $where = "a.status = 'published' AND a.published_at <= UTC_TIMESTAMP()";
    $params = [];
    if ($filters['category'] !== '') {
        $where .= ' AND c.slug = ?';
        $params[] = $filters['category'];
    }
    if ($filters['q'] !== '') {
        $where .= " AND (a.title LIKE ? ESCAPE '!' OR a.summary LIKE ? ESCAPE '!' OR a.body_text LIKE ? ESCAPE '!')";
        $like = cloudsys_insights_like($filters['q']);
        array_push($params, $like, $like, $like);
    }
    $from = ' FROM articles a INNER JOIN article_categories c ON c.id = a.category_id WHERE ' . $where;
    $statement = cloudsys_db()->prepare('SELECT COUNT(*)' . $from);
    $statement->execute($params);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / 9));
    $page = min($filters['page'], $pages);
    $statement = cloudsys_db()->prepare('SELECT a.id, a.slug, a.title, a.summary, a.author_name,
        a.published_at, a.cover_image_path, a.cover_image_alt, c.slug AS category_slug, c.name AS category_name'
        . $from . ' ORDER BY a.published_at DESC, a.id DESC LIMIT ? OFFSET ?');
    foreach ($params as $index => $value) $statement->bindValue($index + 1, $value, PDO::PARAM_STR);
    $statement->bindValue(count($params) + 1, 9, PDO::PARAM_INT);
    $statement->bindValue(count($params) + 2, ($page - 1) * 9, PDO::PARAM_INT);
    $statement->execute();
    return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function cloudsys_related_articles(int $id, string $category): array
{
    $statement = cloudsys_db()->prepare("SELECT a.id, a.slug, a.title, a.summary, a.author_name,
        a.published_at, a.cover_image_path, a.cover_image_alt, c.slug AS category_slug, c.name AS category_name
        FROM articles a INNER JOIN article_categories c ON c.id = a.category_id
        WHERE a.id <> ? AND c.slug = ? AND a.status = 'published' AND a.published_at <= UTC_TIMESTAMP()
        ORDER BY a.published_at DESC, a.id DESC LIMIT 3");
    $statement->execute([$id, $category]);
    return $statement->fetchAll();
}
