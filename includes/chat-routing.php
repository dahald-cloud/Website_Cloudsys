<?php
declare(strict_types=1);

/** Only conversational continuations of a handoff inherit it, not new topics. */
function cloudsys_chat_handoff_followup(string $message, bool $previousHandoff): bool
{
    if (!$previousHandoff) return false;
    $normalized = strtolower(trim($message));
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = rtrim($normalized, " .!?\t\r\n");
    return in_array($normalized, [
        'yes', 'yes please', 'please', 'okay', 'ok', 'sure', 'how', 'how do i contact you',
        'where', 'where is the form', 'send me the link', 'can i have the link',
        'how do i reach out', 'who should i contact', 'what happens next',
    ], true);
}
