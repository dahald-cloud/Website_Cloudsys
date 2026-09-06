<?php
declare(strict_types=1);
require_once __DIR__ . '/articles.php';

// A deliberately small Markdown subset. Raw HTML is never interpreted.
function cloudsys_article_inline(string $text): string
{
    $parts = preg_split('~(\[[^\]\r\n]{1,200}\]\(https?://[^\s)<>"\x27]{1,2000}\))~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts ?: [] as $part) {
        if (preg_match('~\A\[([^\]\r\n]+)\]\((https?://[^\s)<>"\x27]+)\)\z~i', $part, $link)
            && filter_var($link[2], FILTER_VALIDATE_URL)) {
            $html .= '<a rel="noopener noreferrer" href="' . cloudsys_article_escape($link[2]) . '">' . cloudsys_article_escape($link[1]) . '</a>';
        } else {
            $safe = cloudsys_article_escape($part);
            $html .= preg_replace('/\*\*([^*\r\n]+)\*\*/', '<strong>$1</strong>', $safe);
        }
    }
    return $html;
}

function cloudsys_article_body(string $source): string
{
    $html = ''; $paragraph = []; $list = '';
    $flush = static function () use (&$html, &$paragraph, &$list): void {
        if ($paragraph) { $html .= '<p>' . cloudsys_article_inline(implode("\n", $paragraph)) . '</p>'; $paragraph = []; }
        if ($list !== '') { $html .= '</' . $list . '>'; $list = ''; }
    };
    foreach (preg_split('/\R/', $source) ?: [] as $line) {
        if (trim($line) === '') { $flush(); continue; }
        if (preg_match('/^(#{2,3})\s+(.+)$/', $line, $heading)) {
            $flush(); $tag = strlen($heading[1]) === 2 ? 'h2' : 'h3';
            $html .= '<' . $tag . '>' . cloudsys_article_inline($heading[2]) . '</' . $tag . '>'; continue;
        }
        if (preg_match('/^(?:([-*])|\d+\.)\s+(.+)$/', $line, $item)) {
            $type = $item[1] !== '' ? 'ul' : 'ol';
            if ($list !== $type) { $flush(); $html .= '<' . $type . '>'; $list = $type; }
            $html .= '<li>' . cloudsys_article_inline($item[2]) . '</li>'; continue;
        }
        if ($list !== '') $flush();
        $paragraph[] = $line;
    }
    $flush();
    return $html;
}

function cloudsys_article_media_directory(): string
{
    // Outside public_html. Never serve uploads directly from the filesystem.
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'cloudsys-article-media';
}
