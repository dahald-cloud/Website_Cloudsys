<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__) . '/includes/bootstrap.php';
try {
    $mode = cloudsys_chat_mode();
    $admin = cloudsys_current_admin();
    $allowed = $mode === 'everyone' || ($mode === 'admins' && $admin !== null);
    echo json_encode(['allowed' => $allowed, 'admin' => $admin !== null], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('CloudSys chat access error: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['allowed' => false, 'admin' => false, 'error' => 'Chat access is temporarily unavailable.']);
}