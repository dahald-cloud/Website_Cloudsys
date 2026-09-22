<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/article-ai.php';
require_once dirname(__DIR__) . '/includes/admin-ui.php';
header('Cache-Control: no-store, private'); header('X-Robots-Tag: noindex, nofollow');
$errorMessage = ''; $generation = null; $history = [];
try {
    $admin = cloudsys_require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!cloudsys_verify_csrf((string) ($_POST['csrf'] ?? ''))) throw new DomainException('This page expired. Refresh and try again.');
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'generate') {
            $recent = cloudsys_db()->prepare('SELECT COUNT(*) FROM article_ai_generations WHERE admin_id=? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)');
            $recent->execute([(int) $admin['id']]);
            if ((int) $recent->fetchColumn() >= 10) throw new DomainException('The hourly article-generation limit has been reached. Try again later.');
            $brief = [];
            foreach (['topic' => 300, 'audience' => 300, 'purpose' => 500, 'key_points' => 6000, 'source_material' => 12000, 'avoid' => 3000, 'tone' => 200, 'length' => 30] as $field => $max) {
                $brief[$field] = cloudsys_editor_field($_POST, $field, $max, in_array($field, ['topic','audience','purpose','key_points'], true));
            }
            if (!in_array($brief['length'], ['short','standard','deep'], true)) throw new DomainException('Choose a valid article length.');
            $result = cloudsys_generate_article($brief, is_array($_FILES['reference_image'] ?? null) ? $_FILES['reference_image'] : []);
            $id = cloudsys_store_article_generation((int) $admin['id'], $brief, $result);
            header('Location: /admin/ai-writer.php?id=' . $id, true, 303); exit;
        }
        $id = filter_var($_POST['generation_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) throw new DomainException('Generated article not found.');
        $generation = cloudsys_article_generation((int) $id, (int) $admin['id']);
        if (!$generation) throw new DomainException('Generated article not found.');
        if ($action === 'discard') {
            $statement = cloudsys_db()->prepare("UPDATE article_ai_generations SET status='discarded', reviewed_at=UTC_TIMESTAMP() WHERE id=? AND admin_id=? AND status='generated'");
            $statement->execute([(int) $id, (int) $admin['id']]);
            if ($statement->rowCount() !== 1) throw new DomainException('This generated article has already been used.');
            header('Location: /admin/ai-writer.php?discarded=1', true, 303); exit;
        }
        if (in_array($action, ['draft','publish','schedule'], true)) {
            $articleId = cloudsys_accept_article_generation($generation, $action, trim((string) ($_POST['schedule_at'] ?? '')));
            header('Location: /admin/edit-article.php?id=' . $articleId . '&ai=1', true, 303); exit;
        }
        throw new DomainException('Choose a valid article action.');
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD','POST'], true)) { header('Allow: GET, HEAD, POST'); http_response_code(405); exit; }
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id) $generation = cloudsys_article_generation((int) $id, (int) $admin['id']);
    $history = cloudsys_db()->prepare('SELECT id, model_name, status, created_at FROM article_ai_generations WHERE admin_id=? ORDER BY id DESC LIMIT 8');
    $history->execute([(int) $admin['id']]); $history = $history->fetchAll();
} catch (Throwable $error) {
    $errorMessage = $error instanceof DomainException ? $error->getMessage() : 'The AI writer is temporarily unavailable.';
    if (!$error instanceof DomainException) error_log('CloudSys AI writer: ' . $error->getMessage());
}
$e = 'cloudsys_admin_escape';
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AI article writer | CloudSys</title><meta name="robots" content="noindex,nofollow"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"><link rel="stylesheet" href="/style.css?v=20260922-1"><link rel="stylesheet" href="/admin-styles.css?v=20260922-1"><link rel="stylesheet" href="/article-admin.css?v=20260922-1"><link rel="stylesheet" href="/article-ai.css?v=20260922-2"><script src="/article-ai.js?v=20260922-1" defer></script></head><body class="admin-dashboard-shell"><?php cloudsys_admin_header($admin, 'ai-writer'); ?>
<main class="admin-dashboard ai-writer-workspace"><p class="admin-eyebrow">EDITORIAL STUDIO</p><div class="admin-title-row"><div><h1>Write with AI</h1><p>Turn an approved topic and source material into a reviewable CloudSys article. Nothing is published without a second administrator action.</p></div><a href="/admin/articles.php">View articles →</a></div>
<?php if ($errorMessage): ?><p class="admin-alert" role="alert"><?= $e($errorMessage) ?></p><?php elseif (isset($_GET['discarded'])): ?><p class="admin-success" role="status">Generated article discarded.</p><?php endif; ?>
<?php if (!$generation): ?>
<form class="ai-brief" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= $e(cloudsys_csrf_token()) ?>"><input type="hidden" name="action" value="generate"><input type="hidden" name="MAX_FILE_SIZE" value="4194304">
<section class="ai-brief-main"><h2>Article brief</h2><label>Topic<input name="topic" maxlength="300" required placeholder="Example: Reducing manual order exceptions in NetSuite"></label><label>Who should read this?<input name="audience" maxlength="300" required placeholder="Operations and finance leaders using NetSuite"></label><label>What should the article accomplish?<textarea name="purpose" rows="3" maxlength="500" required placeholder="What should readers understand or do after reading?"></textarea></label><label>Facts and key points<textarea name="key_points" rows="8" maxlength="6000" required placeholder="Provide the specific facts, arguments, examples, and approved CloudSys perspective."></textarea></label><label>Source material and excerpts<textarea name="source_material" rows="7" maxlength="12000" placeholder="Paste the relevant text. URLs may be included for attribution, but the model will not assume it opened them."></textarea></label></section>
<aside class="ai-brief-settings"><h2>Direction</h2><label>Length<select name="length"><option value="standard">Standard · 900–1,300 words</option><option value="short">Short · 500–800 words</option><option value="deep">Deep · 1,400–2,000 words</option></select></label><label>Tone<input name="tone" maxlength="200" value="Practical, concise, expert, and business-first"></label><label>Claims or wording to avoid<textarea name="avoid" rows="5" maxlength="3000" placeholder="Customer names, unsupported statistics, guarantees, confidential details…"></textarea></label><label>Optional reference image<input type="file" name="reference_image" accept="image/jpeg,image/png"></label><p class="editor-help">The image helps the model understand context. It is not automatically published. JPEG or PNG, maximum 4 MB.</p><button class="admin-save" type="submit">Generate review draft <span>→</span></button><p class="ai-privacy-note">The brief and optional image are sent to the configured OpenRouter model with zero-data-retention and data-collection restrictions requested.</p></aside></form>
<?php if ($history): ?><section class="ai-history" aria-labelledby="ai-history-title"><div><p class="admin-eyebrow">RECENT WORK</p><h2 id="ai-history-title">Generated drafts</h2></div><div class="ai-history-list"><?php foreach ($history as $item): ?><a href="/admin/ai-writer.php?id=<?= (int) $item['id'] ?>"><span>#<?= (int) $item['id'] ?> · <?= $e($item['status']) ?></span><strong><?= $e($item['model_name']) ?></strong><time datetime="<?= $e($item['created_at']) ?>Z"><?= $e($item['created_at']) ?> UTC</time></a><?php endforeach; ?></div></section><?php endif; ?>
<?php else: $output = $generation['output']; ?>
<section class="ai-review-head"><div><p class="admin-eyebrow">GENERATED REVIEW</p><h2><?= $e($output['title']) ?></h2><p><?= $e($output['summary']) ?></p></div><dl><div><dt>Model</dt><dd><?= $e($generation['model_name']) ?></dd></div><div><dt>Readiness</dt><dd><?= $e($output['readiness']) ?></dd></div><div><dt>Tokens</dt><dd><?= (int) $generation['prompt_tokens'] ?> in · <?= (int) $generation['completion_tokens'] ?> out</dd></div></dl></section>
<div class="ai-review-grid"><article class="ai-article-preview"><span class="editor-preview-category"><?= $e($output['category_slug']) ?></span><h1><?= $e($output['title']) ?></h1><p class="editor-preview-summary"><?= $e($output['summary']) ?></p><div class="article-prose"><?= cloudsys_article_body($output['body_text']) ?></div></article><aside class="ai-review-sidebar"><section><h3>Human review required</h3><?php if ($output['fact_check_notes']): ?><ul><?php foreach ($output['fact_check_notes'] as $note): ?><li><?= $e($note) ?></li><?php endforeach; ?></ul><?php else: ?><p>No specific fact-check warnings were returned. You should still review every claim.</p><?php endif; ?></section><section><h3>Image suggestions</h3><?php if ($output['image_suggestions']): ?><ul><?php foreach ($output['image_suggestions'] as $note): ?><li><?= $e($note) ?></li><?php endforeach; ?></ul><?php else: ?><p>No additional image suggestions.</p><?php endif; ?></section><section><h3>SEO</h3><p><strong><?= $e($output['seo_title']) ?></strong><br><?= $e($output['seo_description']) ?></p></section></aside></div>
<?php if ($generation['status'] === 'generated'): ?><form class="ai-publish-actions" method="post"><input type="hidden" name="csrf" value="<?= $e(cloudsys_csrf_token()) ?>"><input type="hidden" name="generation_id" value="<?= (int) $generation['id'] ?>"><button name="action" value="draft">Import as editable draft</button><button class="ai-live-action" name="action" value="publish" data-confirm="Publish this generated article now? Confirm that every claim has been reviewed.">Approve and publish now</button><label>Schedule in UTC<input type="datetime-local" name="schedule_at"><button name="action" value="schedule" data-schedule>Approve and schedule</button></label><button class="ai-discard-action" name="action" value="discard" data-confirm="Discard this generated article? It will remain in the audit history but cannot be imported.">Discard generation</button></form><?php else: ?><p class="admin-success">This generation has already been <?= $e($generation['status']) ?>.</p><?php endif; ?>
<p><a href="/admin/ai-writer.php">← Start another article</a></p>
<?php endif; ?></main></body></html>
