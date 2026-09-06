<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/article-editor.php';
header('Cache-Control: no-store, private'); header('X-Robots-Tag: noindex, nofollow');
try {
    $admin = cloudsys_require_admin();
    $id = filter_var($_GET['id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($id === false) { http_response_code(404); exit('Article not found.'); }
    $article = $id ? cloudsys_editor_article($id) : null;
    if ($id && !$article) { http_response_code(404); exit('Article not found.'); }
    $categories = cloudsys_db()->query('SELECT id, name FROM article_categories ORDER BY sort_order, id')->fetchAll();
} catch (Throwable $error) {
    error_log('Article editor startup: ' . $error->getMessage()); http_response_code(503); exit('The editor is temporarily unavailable. Check the Insights database installation.');
}
$values = $article ?? ['id' => 0, 'title' => '', 'slug' => '', 'category_id' => '', 'author_name' => $admin['display_name'], 'summary' => '', 'body_text' => '', 'cover_image_alt' => '', 'seo_title' => '', 'seo_description' => '', 'status' => 'draft'];
$revision = $article ? cloudsys_article_revision($article) : '';
$errorMessage = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if ((string) ($_POST['id'] ?? '') !== (string) $id) throw new DomainException('Article identity changed. Reload the editor.');
        $savedId = cloudsys_save_article($_POST, is_array($_FILES['cover'] ?? null) ? $_FILES['cover'] : []);
        header('Location: /admin/edit-article.php?id=' . $savedId . '&saved=1', true, 303); exit;
    } catch (Throwable $error) {
        http_response_code($error instanceof DomainException ? 400 : 503);
        $errorMessage = $error instanceof DomainException ? $error->getMessage() : 'The article could not be saved. Your text is still below; please try again.';
        if (!$error instanceof DomainException) error_log('Article save: ' . $error->getMessage());
        foreach (['title','slug','category_id','author_name','summary','body_text','cover_image_alt','seo_title','seo_description'] as $field) {
            if (is_string($_POST[$field] ?? null)) $values[$field] = $_POST[$field];
        }
        // Preserve the submitted version on conflict; never silently overwrite a newer save.
        if (is_string($_POST['revision'] ?? null)) $revision = $_POST['revision'];
    }
} elseif (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD, POST'); http_response_code(405); exit; }
$e = 'cloudsys_article_escape';
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $id ? 'Edit article' : 'New article' ?> | CloudSys</title><meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/admin-styles.css"><link rel="stylesheet" href="/article-admin.css"><script src="/article-editor.js" defer></script></head>
<body class="admin-dashboard-shell"><header class="admin-header"><a href="/admin/articles.php">← Articles</a><div class="admin-account"><a href="/change-password">Change password</a><form method="post" action="/logout.php"><input type="hidden" name="csrf" value="<?= $e(cloudsys_csrf_token()) ?>"><button>Sign out</button></form></div></header>
<main class="admin-dashboard article-workspace"><p class="admin-eyebrow">PUBLISHING</p><h1><?= $id ? 'Edit article' : 'New article' ?></h1>
<p>Status: <strong><?= $e($article['status'] ?? 'draft') ?></strong>. Saving a published article updates the live page. Unpublish it first to work privately.</p>
<?php if (!empty($article['published_at'])): ?><p>Publication date (UTC): <?= $e($article['published_at']) ?></p><?php endif; ?>
<?php if ($errorMessage): ?><p class="admin-alert" role="alert"><?= $e($errorMessage) ?> If you selected an image, choose it again before retrying.</p><?php elseif (isset($_GET['saved'])): ?><p class="admin-success" role="status">Article saved.</p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="article-editor" id="article-editor">
<input type="hidden" name="csrf" value="<?= $e(cloudsys_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="revision" value="<?= $e($revision) ?>"><input type="hidden" name="MAX_FILE_SIZE" value="4194304">
<div class="editor-main">
<label>Title<input id="article-title" name="title" required maxlength="200" value="<?= $e($values['title']) ?>"></label>
<label>Summary<textarea name="summary" rows="3" maxlength="500"><?= $e($values['summary']) ?></textarea></label>
<label for="article-body">Article text</label>
<div class="editor-toolbar" role="group" aria-label="Insert formatting"><button type="button" data-prefix="## ">Heading</button><button type="button" data-prefix="### ">Subheading</button><button type="button" data-wrap="**">Bold</button><button type="button" data-prefix="- ">Bullet list</button><button type="button" data-prefix="1. ">Numbered list</button><button type="button" data-link>Link</button></div>
<textarea id="article-body" name="body_text" rows="24" maxlength="50000" aria-describedby="format-help"><?= $e($values['body_text']) ?></textarea>
<p id="format-help" class="editor-help">Use blank lines between paragraphs. Formatting: ## heading, **bold**, - list item, [link text](https://example.com). HTML is displayed as text. Save before opening the preview.</p>
</div><aside class="editor-settings">
<label>URL slug<input id="article-slug" name="slug" required maxlength="160" pattern="[a-z0-9]+(-[a-z0-9]+)*" <?= $id ? 'readonly' : '' ?> value="<?= $e($values['slug']) ?>"></label><p class="editor-help">/insights/your-slug — fixed after the first save.</p>
<label>Category<select name="category_id" required><option value="">Choose a category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) $values['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= $e($category['name']) ?></option><?php endforeach; ?></select></label>
<label>Author display name<input name="author_name" required maxlength="100" value="<?= $e($values['author_name']) ?>"></label>
<?php if (!empty($article['cover_image_path'])): ?><img class="editor-cover" src="/article-media.php?id=<?= $id ?>" alt="<?= $e($article['cover_image_alt']) ?>"><label class="editor-checkbox"><input type="checkbox" name="remove_cover" value="1">Remove current cover</label><?php endif; ?>
<label>Cover image<input type="file" name="cover" accept="image/jpeg,image/png"></label><p class="editor-help">JPEG or PNG, up to 4 MB and 6 megapixels. Re-encoded to JPEG, maximum 1920 pixels.</p>
<label>Image description<input name="cover_image_alt" maxlength="255" value="<?= $e($values['cover_image_alt']) ?>"></label>
<label>SEO title<input name="seo_title" maxlength="200" value="<?= $e($values['seo_title']) ?>"></label>
<label>SEO description<textarea name="seo_description" maxlength="500" rows="3"><?= $e($values['seo_description']) ?></textarea></label><p class="editor-help">Leave SEO fields blank to use the article title and summary.</p>
<div class="editor-actions"><button class="admin-save" name="action" value="save">Save <?= ($article['status'] ?? '') === 'published' ? 'live changes' : 'draft' ?></button>
<?php if (($article['status'] ?? '') === 'published'): ?><button name="action" value="unpublish" class="editor-secondary">Unpublish and save privately</button><?php else: ?><button name="action" value="publish" class="editor-publish" data-publish>Publish now</button><?php endif; ?>
<?php if ($id): ?><a href="/admin/preview-article.php?id=<?= $id ?>" target="_blank" rel="noopener">Preview saved article ↗</a><?php endif; ?></div>
</aside></form></main></body></html>
