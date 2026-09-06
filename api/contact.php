<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/request-state.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function single_line(mixed $value): string
{
    return trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($value ?? '')));
}

function config_value(string $name, array $config): string
{
    $environment = getenv($name);
    if ($environment !== false && $environment !== '') return $environment;
    return isset($config[$name]) ? trim((string) $config[$name]) : '';
}

function post_request(string $url, array $headers, string $body): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required.');
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $responseBody = curl_exec($handle);
    if ($responseBody === false) {
        $message = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException("External request failed: {$message}");
    }
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    return [$status, $responseBody];
}


function rate_limit_status(string $path, int $limit, int $window): array
{
    if (!is_file($path)) return ['limited' => false, 'retry_after' => 0, 'count' => 0, 'started' => time()];
    $state = cloudsys_read_state($path);
    if (!is_array($state) || (int) ($state['started'] ?? 0) <= time() - $window) {
        return ['limited' => false, 'retry_after' => 0, 'count' => 0, 'started' => time()];
    }
    $count = max(0, (int) ($state['count'] ?? 0));
    $retryAfter = max(1, $window - (time() - (int) $state['started']));
    return ['limited' => $count >= $limit, 'retry_after' => $retryAfter, 'count' => $count, 'started' => (int) $state['started']];
}

function record_rate_limit(string $path, array $state): void
{
    $state['count'] = (int) ($state['count'] ?? 0) + 1;
    cloudsys_write_state($path, $state);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Method not allowed.']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 10000) respond(413, ['error' => 'Request too large.']);

$configPath = getenv('CLOUDSYS_CONFIG');
if ($configPath === false || $configPath === '') $configPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'cloudsys-config.php';
$config = is_file($configPath) ? require $configPath : [];
if (!is_array($config)) {
    error_log('CloudSys contact error: invalid configuration file.');
    respond(500, ['error' => 'Contact service is not configured.']);
}

$turnstileSecret = config_value('TURNSTILE_SECRET_KEY', $config);
$resendApiKey = config_value('RESEND_API_KEY', $config);
$resendFrom = config_value('RESEND_FROM', $config);
$contactTo = config_value('CONTACT_TO', $config);
if ($turnstileSecret === '' || $resendApiKey === '' || $resendFrom === '' || $contactTo === '') {
    error_log('CloudSys contact error: required configuration is missing.');
    respond(500, ['error' => 'Contact service is not configured.']);
}

try {
    $input = json_decode((string) file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) respond(400, ['error' => 'Invalid request.']);
    if (single_line($input['website'] ?? '') !== '') respond(200, ['ok' => true]);

    $name = single_line($input['name'] ?? '');
    $email = single_line($input['email'] ?? '');
    $company = single_line($input['company'] ?? '');
    $phone = single_line($input['phone'] ?? '');
    $message = trim((string) ($input['message'] ?? ''));
    $turnstileToken = single_line($input['cf-turnstile-response'] ?? '');

    if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) respond(400, ['error' => 'Please provide a name and valid email.']);
    $phoneDigits = preg_replace('/\D+/', '', $phone);
    if ($phone === '' || !preg_match('/^\+?[0-9\s().-]+$/', $phone) || strlen((string) $phoneDigits) < 7 || strlen((string) $phoneDigits) > 15) respond(400, ['error' => 'Please provide a valid phone number with country code.']);
    if (strlen($name) > 100 || strlen($email) > 254 || strlen($company) > 150 || strlen($phone) > 30 || strlen($message) > 3000) respond(400, ['error' => 'One or more fields are too long.']);
    if ($turnstileToken === '') respond(400, ['error' => 'Please complete the CAPTCHA.']);

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $normalizedEmail = strtolower($email);
    $rateDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
    $rateChecks = [
        ['path' => $rateDirectory . 'cloudsys-contact-pair-' . hash('sha256', $ip . '|' . $normalizedEmail), 'limit' => 1, 'message' => 'This request was already sent. Please try again in about an hour.'],
        ['path' => $rateDirectory . 'cloudsys-contact-email-' . hash('sha256', $normalizedEmail), 'limit' => 1, 'message' => 'A request using this email address was already sent. Please try again in about an hour.'],
        ['path' => $rateDirectory . 'cloudsys-contact-ip-' . hash('sha256', $ip), 'limit' => 5, 'message' => 'Too many requests were sent from this connection. Please try again in about an hour.'],
    ];
    $requestLocks = new CloudsysRequestLocks(array_column($rateChecks, 'path'));
    foreach ($rateChecks as $index => $check) {
        $rateChecks[$index]['state'] = rate_limit_status($check['path'], $check['limit'], 3600);
        if ($rateChecks[$index]['state']['limited']) {
            header('Retry-After: ' . $rateChecks[$index]['state']['retry_after']);
            respond(429, ['error' => $check['message'], 'retry_after' => $rateChecks[$index]['state']['retry_after']]);
        }
    }
    $verificationBody = http_build_query(['secret' => $turnstileSecret, 'response' => $turnstileToken, 'remoteip' => $ip]);
    [$verificationStatus, $verificationJson] = post_request('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['Content-Type: application/x-www-form-urlencoded'], $verificationBody);
    $verification = json_decode($verificationJson, true, 32, JSON_THROW_ON_ERROR);
    if ($verificationStatus !== 200 || !($verification['success'] ?? false)) respond(400, ['error' => 'CAPTCHA verification failed. Please try again.']);

    $emailText = "Name: {$name}\nWork email: {$email}\nPhone: {$phone}\nCompany: " . ($company !== '' ? $company : 'Not provided') . "\nChallenge: " . ($message !== '' ? $message : 'Not provided') . "\n\nSubmitted through cloudsysllc.com.";
    $resendBody = json_encode([
        'from' => $resendFrom,
        'to' => [$contactTo],
        'reply_to' => $email,
        'subject' => "New NetSuite assessment request from {$name}",
        'text' => $emailText,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    [$resendStatus, $resendJson] = post_request('https://api.resend.com/emails', [
        'Authorization: Bearer ' . $resendApiKey,
        'Content-Type: application/json',
        'Idempotency-Key: contact-' . hash('sha256', $turnstileToken),
    ], $resendBody);
    if ($resendStatus < 200 || $resendStatus >= 300) {
        $resendError = json_decode($resendJson, true);
        $detail = is_array($resendError) ? (string) ($resendError['message'] ?? 'Unknown Resend error') : 'Unknown Resend error';
        throw new RuntimeException("Resend returned {$resendStatus}: {$detail}");
    }

    foreach ($rateChecks as $check) record_rate_limit($check['path'], $check['state']);
    respond(200, ['ok' => true]);
} catch (CloudsysRequestBusy $error) {
    header('Retry-After: 2');
    respond(429, ['error' => $error->getMessage(), 'retry_after' => 2]);
} catch (JsonException $error) {
    respond(400, ['error' => 'Invalid request.']);
} catch (Throwable $error) {
    error_log('CloudSys contact error: ' . $error->getMessage());
    respond(500, ['error' => 'Unable to send message.']);
}
