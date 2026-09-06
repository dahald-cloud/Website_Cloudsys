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

/** Match general educational ERP topics while preserving short contextual follow-ups. */
function cloudsys_chat_in_scope(string $message, bool $previousInScope): bool
{
    $length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
    if ($previousInScope && $length <= 180) return true;
    $normalized = ' ' . strtolower(trim($message)) . ' ';
    $terms = [
        'netsuite', 'erp', 'suitecloud', 'suitescript', 'suiteanalytics', 'saved search',
        'workflow', 'accounting', 'inventory', 'invoice', 'sales order', 'purchase order',
        'order management', 'order-to-cash', 'procure-to-pay', 'procurement', 'warehouse',
        'crm', 'integration', 'automation', 'artificial intelligence', ' ai ', 'ai agent',
        'business process', 'financial close', 'demand planning',
    ];
    foreach ($terms as $term) if (str_contains($normalized, $term)) return true;
    return false;
}
