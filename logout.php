<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
cloudsys_start_session();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !cloudsys_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('Invalid request.');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $parameters = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
}
session_destroy();
header('Location: /login', true, 302);
exit;