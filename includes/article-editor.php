<?php
declare(strict_types=1);
require_once __DIR__ . '/article-format.php';

function cloudsys_editor_article(int $id, bool $lock = false): ?array
{
    cloudsys_require_admin();
    $query = cloudsys_db()->prepare('SELECT * FROM articles WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]);
    $row = $query->fetch();
    return is_array($row) ? $row : null;
}

function cloudsys_article_revision(array $row): string
{
    ksort($row);
    return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
}

function cloudsys_editor_field(array $input, string $key, int $max, bool $required = false): string
{
    if (isset($input[$key]) && !is_string($input[$key])) throw new DomainException('Invalid value for ' . $key . '.');
    $value = trim($input[$key] ?? '');
    if (!preg_match('//u', $value) || str_contains($value, "\0")) throw new DomainException('Please use valid text.');
    $length = preg_match_all('/./us', $value);
    if ($length > $max || ($required && $value === '')) throw new DomainException(str_replace('_', ' ', ucfirst($key)) . ' is required or exceeds ' . $max . ' characters.');
    return $value;
}

function cloudsys_article_upload(array $file): ?string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new DomainException('Image upload failed. Choose the image again.');
    if (filesize($file['tmp_name']) > 4 * 1024 * 1024) throw new DomainException('Cover images must be 4 MB or smaller.');
    if (!extension_loaded('gd') || !class_exists('finfo')) throw new DomainException('Image uploads need the PHP GD and Fileinfo extensions enabled on hosting.');
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($type, ['image/jpeg', 'image/png'], true)) throw new DomainException('Use a JPEG or PNG image. SVG and other files are not accepted.');
    $size = @getimagesize($file['tmp_name']);
    if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] > 6000 || $size[1] > 6000 || $size[0] * $size[1] > 6000000) throw new DomainException('Use an image under 6 megapixels and 6000 pixels per side.');
    $image = $type === 'image/jpeg' ? @imagecreatefromjpeg($file['tmp_name']) : @imagecreatefrompng($file['tmp_name']);
    if (!$image) throw new DomainException('That image could not be decoded.');
    $directory = cloudsys_article_media_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Cannot create private image directory.');
    $name = bin2hex(random_bytes(24)) . '.jpg';
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    $ratio = min(1, 1920 / max($size[0], $size[1]));
    $width = max(1, (int) round($size[0] * $ratio)); $height = max(1, (int) round($size[1] * $ratio));
    $output = imagecreatetruecolor($width, $height);
    try {
        imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        if (!imagejpeg($output, $path, 85) || !chmod($path, 0600)) throw new RuntimeException('Unable to store image securely.');
    } catch (Throwable $failure) {
        if (is_file($path)) unlink($path);
        throw $failure;
    } finally { imagedestroy($image); imagedestroy($output); }
    return $name;
}

function cloudsys_save_article(array $input, array $file): int
{
    $admin = cloudsys_require_admin();
    if (!is_string($input['csrf'] ?? null) || !cloudsys_verify_csrf($input['csrf'])) throw new DomainException('Your session form expired. Refresh the page before saving.');
    $action = $input['action'] ?? '';
    if (!in_array($action, ['draft', 'save', 'publish', 'unpublish'], true)) throw new DomainException('Choose a valid publishing action.');
    $id = filter_var($input['id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($id === false) throw new DomainException('Invalid article.');
    $data = [];
    foreach (['title' => 200, 'slug' => 160, 'summary' => 500, 'body_text' => 50000, 'author_name' => 100, 'cover_image_alt' => 255, 'seo_title' => 200, 'seo_description' => 500] as $field => $max) {
        $data[$field] = cloudsys_editor_field($input, $field, $max, in_array($field, ['title', 'slug', 'author_name'], true));
    }
    if (!cloudsys_article_valid_slug($data['slug'])) throw new DomainException('Use lowercase letters, numbers, and single hyphens in the URL slug.');
    $category = filter_var($input['category_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$category) throw new DomainException('Choose a category.');
    $db = cloudsys_db(); $newImage = null;
    $db->beginTransaction();
    try {
        $existing = $id ? cloudsys_editor_article($id, true) : null;
        if ($id && !$existing) throw new DomainException('Article not found.');
        if ($existing && (!is_string($input['revision'] ?? null) || !hash_equals(cloudsys_article_revision($existing), $input['revision']))) throw new DomainException('Another save changed this article. Copy your edits, then reload before saving.');
        if ($existing && $data['slug'] !== $existing['slug']) throw new DomainException('The URL slug is fixed after the first save so existing links remain valid.');
        $check = $db->prepare('SELECT id FROM article_categories WHERE id = ?'); $check->execute([$category]);
        if (!$check->fetchColumn()) throw new DomainException('Choose an existing category.');
        $status = in_array($action, ['draft', 'unpublish'], true) ? 'draft' : ($action === 'publish' ? 'published' : ($existing['status'] ?? 'draft'));
        if ($status === 'published' && ($data['summary'] === '' || $data['body_text'] === '')) throw new DomainException('Add a summary and article text before publishing.');
        $publishedAt = $existing['published_at'] ?? null;
        if ($action === 'publish' && (!$publishedAt || $publishedAt > gmdate('Y-m-d H:i:s'))) $publishedAt = gmdate('Y-m-d H:i:s');
        $imageName = ($input['remove_cover'] ?? '') === '1' ? null : ($existing['cover_image_path'] ?? null);
        $hasUpload = ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (($imageName || $hasUpload) && $data['cover_image_alt'] === '') throw new DomainException('Describe the cover image for readers using assistive technology.');
        $newImage = cloudsys_article_upload($file);
        if ($newImage) $imageName = $newImage;
        $values = [$category, $data['author_name'], $data['slug'], $data['title'], $data['summary'], $data['body_text'], $status, $imageName, $data['cover_image_alt'], $data['seo_title'], $data['seo_description'], $publishedAt];
        if ($id) {
            $sql = 'UPDATE articles SET category_id=?, author_name=?, slug=?, title=?, summary=?, body_text=?, status=?, cover_image_path=?, cover_image_alt=?, seo_title=?, seo_description=?, published_at=?, updated_at=UTC_TIMESTAMP() WHERE id=?';
            $values[] = $id;
        } else {
            $sql = 'INSERT INTO articles (category_id, author_name, slug, title, summary, body_text, status, cover_image_path, cover_image_alt, seo_title, seo_description, published_at, author_admin_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())';
            $values[] = (int) $admin['id'];
        }
        $db->prepare($sql)->execute($values);
        if (!$id) $id = (int) $db->lastInsertId();
        $db->commit();
        return $id;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        if ($newImage) @unlink(cloudsys_article_media_directory() . DIRECTORY_SEPARATOR . $newImage);
        if ($error instanceof PDOException && ($error->errorInfo[1] ?? 0) === 1062) throw new DomainException('That URL slug is already used. Choose a different one.');
        throw $error;
    }
}
