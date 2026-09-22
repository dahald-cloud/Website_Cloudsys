<?php
declare(strict_types=1);
require_once __DIR__ . '/article-editor.php';

function cloudsys_article_ai_model(string $key, string $fallback = ''): string
{
    $value = cloudsys_config_value($key);
    if ($value === '') $value = $fallback;
    if (!preg_match('~\A[a-z0-9._-]+/[a-z0-9._:-]+\z~i', $value)) throw new RuntimeException('Invalid article AI model configuration.');
    return $value;
}

function cloudsys_article_ai_post(string $url, array $headers, string $body): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required.');
    $handle = curl_init($url);
    curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 90, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $response = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $error = curl_error($handle); curl_close($handle);
    if ($response === false) throw new RuntimeException('OpenRouter request failed: ' . $error);
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || $status < 200 || $status >= 300) {
        $detail = is_array($decoded) ? (string) ($decoded['error']['message'] ?? 'provider error') : 'invalid provider response';
        throw new RuntimeException('OpenRouter returned HTTP ' . $status . ': ' . $detail);
    }
    return $decoded;
}

function cloudsys_article_ai_image(array $file): ?array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) throw new DomainException('The reference image could not be uploaded.');
    if ((int) ($file['size'] ?? 0) > 4 * 1024 * 1024 || !class_exists('finfo')) throw new DomainException('Reference images must be JPEG or PNG and no larger than 4 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) throw new DomainException('Reference images must be JPEG or PNG.');
    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false) throw new DomainException('The reference image could not be read.');
    return ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode($bytes)]];
}

function cloudsys_article_ai_validate(array $value): array
{
    $limits = ['title' => 200, 'slug' => 160, 'summary' => 500, 'body_text' => 50000, 'seo_title' => 200, 'seo_description' => 500, 'category_slug' => 80, 'readiness' => 20];
    $clean = [];
    foreach ($limits as $field => $limit) $clean[$field] = cloudsys_editor_field($value, $field, $limit, true);
    if (!cloudsys_article_valid_slug($clean['slug']) || !cloudsys_article_valid_slug($clean['category_slug'])) throw new RuntimeException('The AI returned an invalid slug.');
    if (!in_array($clean['readiness'], ['ready', 'needs-review'], true)) throw new RuntimeException('The AI returned an invalid readiness value.');
    foreach (['fact_check_notes', 'image_suggestions'] as $field) {
        if (!isset($value[$field]) || !is_array($value[$field]) || count($value[$field]) > 12) throw new RuntimeException('The AI returned invalid review notes.');
        $clean[$field] = [];
        foreach ($value[$field] as $item) {
            if (!is_string($item) || trim($item) === '' || strlen($item) > 800) throw new RuntimeException('The AI returned invalid review notes.');
            $clean[$field][] = trim($item);
        }
    }
    return $clean;
}

function cloudsys_generate_article(array $brief, array $imageFile): array
{
    $apiKey = cloudsys_config_value('OPENROUTER_API_KEY');
    $baseUrl = rtrim(cloudsys_config_value('OPENROUTER_BASE_URL') ?: 'https://openrouter.ai/api/v1', '/');
    if ($apiKey === '' || $baseUrl !== 'https://openrouter.ai/api/v1') throw new RuntimeException('Article AI is not configured.');
    $primary = cloudsys_article_ai_model('ARTICLE_AI_MODEL', 'anthropic/claude-opus-4.8');
    $fallbackValue = cloudsys_config_value('ARTICLE_AI_FALLBACK_MODEL');
    $models = [$primary];
    if ($fallbackValue !== '') $models[] = cloudsys_article_ai_model('ARTICLE_AI_FALLBACK_MODEL');
    $system = <<<'PROMPT'
You are the senior editorial writer for CloudSys, a business-first NetSuite, ERP, automation, integration, and AI consultancy. Produce clear, useful, credible writing for operational and finance leaders. Use a confident but restrained professional voice. Avoid generic AI phrasing, hype, filler, repetition, and sales-heavy language.

Only use facts supplied in the brief or broadly stable general knowledge. Never invent customer outcomes, quotations, statistics, product capabilities, certifications, or CloudSys experience. Mark any claim requiring human verification in fact_check_notes. If the brief cannot support a responsible finished article, set readiness to needs-review. Raw HTML is forbidden. Body text must use only plain paragraphs, ## headings, ### headings, bullet lists, numbered lists, bold text, safe links, and the site's [video](URL) syntax. The title is the only H1. Return only data matching the required schema.
PROMPT;
    $lengthGuide = ['short' => '500–800 words', 'standard' => '900–1,300 words', 'deep' => '1,400–2,000 words'];
    $targetLength = $lengthGuide[$brief['length'] ?? 'standard'] ?? $lengthGuide['standard'];
    $prompt = "Create a CloudSys article from this editorial brief. Target length: {$targetLength}. URLs are attribution references only: do not claim to have opened them, and rely on pasted excerpts for their factual content.\n" . json_encode($brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $content = [['type' => 'text', 'text' => $prompt]];
    $image = cloudsys_article_ai_image($imageFile); if ($image) $content[] = $image;
    $schema = ['name' => 'cloudsys_article', 'strict' => true, 'schema' => ['type' => 'object', 'additionalProperties' => false,
        'properties' => [
            'title' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'summary' => ['type' => 'string'],
            'body_text' => ['type' => 'string'], 'seo_title' => ['type' => 'string'], 'seo_description' => ['type' => 'string'],
            'category_slug' => ['type' => 'string', 'enum' => ['netsuite', 'ai-automation', 'problems-solved']],
            'readiness' => ['type' => 'string', 'enum' => ['ready', 'needs-review']],
            'fact_check_notes' => ['type' => 'array', 'items' => ['type' => 'string']],
            'image_suggestions' => ['type' => 'array', 'items' => ['type' => 'string']],
        ], 'required' => ['title','slug','summary','body_text','seo_title','seo_description','category_slug','readiness','fact_check_notes','image_suggestions']]];
    $lastError = null;
    foreach (array_values(array_unique($models)) as $model) {
        try {
            $configuredMax = (int) (cloudsys_config_value('ARTICLE_AI_MAX_OUTPUT_TOKENS') ?: 6000);
            $maxTokens = max(2000, min(10000, $configuredMax));
            $payload = ['model' => $model, 'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $content]],
                'temperature' => 0.35, 'max_tokens' => $maxTokens, 'stream' => false,
                'response_format' => ['type' => 'json_schema', 'json_schema' => $schema],
                'provider' => ['zdr' => true, 'data_collection' => 'deny', 'require_parameters' => true]];
            $response = cloudsys_article_ai_post($baseUrl . '/chat/completions', ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json', 'HTTP-Referer: https://cloudsysllc.com', 'X-Title: CloudSys Article Studio'], json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $raw = $response['choices'][0]['message']['content'] ?? '';
            if (!is_string($raw) || $raw === '') throw new RuntimeException('The model returned an empty article.');
            $output = cloudsys_article_ai_validate(json_decode($raw, true, 32, JSON_THROW_ON_ERROR));
            return ['model' => $model, 'output' => $output, 'usage' => is_array($response['usage'] ?? null) ? $response['usage'] : []];
        } catch (Throwable $error) { $lastError = $error; }
    }
    throw new RuntimeException('Article generation failed: ' . ($lastError?->getMessage() ?? 'unknown error'));
}

function cloudsys_store_article_generation(int $adminId, array $brief, array $result): int
{
    $usage = $result['usage'];
    $statement = cloudsys_db()->prepare("INSERT INTO article_ai_generations (admin_id, model_name, brief_json, output_json, prompt_tokens, completion_tokens, status, created_at) VALUES (?,?,?,?,?,?,'generated',UTC_TIMESTAMP())");
    $statement->execute([$adminId, $result['model'], json_encode($brief, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), json_encode($result['output'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), (int) ($usage['prompt_tokens'] ?? 0), (int) ($usage['completion_tokens'] ?? 0)]);
    return (int) cloudsys_db()->lastInsertId();
}

function cloudsys_article_generation(int $id, int $adminId): ?array
{
    $statement = cloudsys_db()->prepare('SELECT * FROM article_ai_generations WHERE id=? AND admin_id=? LIMIT 1');
    $statement->execute([$id, $adminId]); $row = $statement->fetch();
    if (!is_array($row)) return null;
    $row['brief'] = json_decode($row['brief_json'], true, 32, JSON_THROW_ON_ERROR);
    $row['output'] = cloudsys_article_ai_validate(json_decode($row['output_json'], true, 32, JSON_THROW_ON_ERROR));
    return $row;
}

function cloudsys_accept_article_generation(array $generation, string $action, string $scheduleAt): int
{
    if (($generation['status'] ?? '') !== 'generated') throw new DomainException('This generated article has already been used.');
    if (!in_array($action, ['draft', 'publish', 'schedule'], true)) throw new DomainException('Choose a valid publishing action.');
    $output = $generation['output'];
    if ($action !== 'draft' && ($output['readiness'] !== 'ready' || $output['fact_check_notes'])) throw new DomainException('Resolve the model’s review notes before publishing. Import this as a draft first.');
    $publishedAt = null; $status = 'draft';
    if ($action === 'publish') { $status = 'published'; $publishedAt = gmdate('Y-m-d H:i:s'); }
    if ($action === 'schedule') {
        $timestamp = strtotime($scheduleAt . ' UTC');
        if (!$timestamp || $timestamp < time() + 300) throw new DomainException('Schedule publication at least five minutes in the future using UTC.');
        $status = 'published'; $publishedAt = gmdate('Y-m-d H:i:s', $timestamp);
    }
    $db = cloudsys_db(); $db->beginTransaction();
    try {
        $category = $db->prepare('SELECT id FROM article_categories WHERE slug=? LIMIT 1'); $category->execute([$output['category_slug']]);
        $categoryId = (int) $category->fetchColumn(); if (!$categoryId) throw new DomainException('The generated category is unavailable.');
        $slug = $output['slug']; $suffix = 1;
        while (true) {
            $check = $db->prepare('SELECT 1 FROM articles WHERE slug=?'); $check->execute([$slug]); if (!$check->fetchColumn()) break;
            $suffix++; $slug = substr($output['slug'], 0, max(1, 155 - strlen((string) $suffix))) . '-' . $suffix;
        }
        $admin = cloudsys_require_admin();
        $insert = $db->prepare('INSERT INTO articles (category_id,author_admin_id,author_name,slug,title,summary,body_text,status,cover_image_path,cover_image_alt,seo_title,seo_description,published_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?, ?,NULL,\'\',?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $insert->execute([$categoryId, (int) $admin['id'], (string) $admin['display_name'], $slug, $output['title'], $output['summary'], $output['body_text'], $status, $output['seo_title'], $output['seo_description'], $publishedAt]);
        $articleId = (int) $db->lastInsertId();
        $update = $db->prepare("UPDATE article_ai_generations SET status=?, article_id=?, reviewed_at=UTC_TIMESTAMP() WHERE id=? AND status='generated'");
        $update->execute([$action === 'draft' ? 'imported' : 'published', $articleId, (int) $generation['id']]);
        if ($update->rowCount() !== 1) throw new DomainException('This generated article was already used.');
        $db->commit(); return $articleId;
    } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}
