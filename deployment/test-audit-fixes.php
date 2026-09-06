<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/request-state.php';
require_once dirname(__DIR__) . '/includes/chat-routing.php';
require_once dirname(__DIR__) . '/includes/insights-article.php';

function audit_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

if (($argv[1] ?? '') === '--worker') {
    $directory = $argv[2]; $mode = $argv[3]; $number = (int) $argv[4];
    $main = $directory . '/' . $mode;
    $paths = $mode === 'contact' ? [$main, $directory . '/ip-' . ($number % 2), $directory . '/pair-' . $number] : [$main];
    try {
        $locks = new CloudsysRequestLocks($paths);
        $state = cloudsys_read_state($main);
        if (($state['count'] ?? 0) >= ($mode === 'contact' ? 1 : 30)) { echo 'limited'; exit; }
        usleep(80000); // Widen the former read/check/write race intentionally.
        cloudsys_write_state($main, ['count' => (int) ($state['count'] ?? 0) + 1]);
        echo 'accepted';
    } catch (CloudsysRequestBusy $error) { echo 'busy'; }
    exit;
}

foreach (['What is ERP?', 'How do saved searches work?', 'Give me a NetSuite tip', 'What can AI agents do?'] as $question) {
    audit_check(!cloudsys_chat_handoff_followup($question, true), 'New topic incorrectly inherited handoff.');
}
audit_check(cloudsys_chat_handoff_followup('Yes please!', true), 'Handoff continuation lost.');
audit_check(!cloudsys_chat_handoff_followup('Yes please!', false), 'Handoff invented.');
audit_check(cloudsys_chat_in_scope("what's a sales order?", false), 'Sales-order question rejected as out of scope.');
audit_check(cloudsys_chat_in_scope('how do you use it?', true), 'Contextual ERP follow-up rejected.');
audit_check(!cloudsys_chat_in_scope('write me a holiday poem', false), 'Unrelated topic accepted as in scope.');

$fixture = ['id' => 1, 'slug' => 'preview', 'title' => '<script>Preview</script>', 'summary' => 'Summary', 'body_text' => "## Heading\n\nBody", 'author_name' => 'CloudSys', 'published_at' => null, 'category_slug' => 'netsuite', 'category_name' => 'NetSuite', 'cover_image_path' => null];
ob_start(); cloudsys_render_insights_article($fixture); $rendered = ob_get_clean();
audit_check(str_contains($rendered, 'insights-prose') && str_contains($rendered, 'Not yet published') && !str_contains($rendered, '<script>'), 'Shared preview renderer failed.');
ob_start(); cloudsys_insights_head('Private preview', '', ''); $head = ob_get_clean();
audit_check(str_contains($head, 'noindex,nofollow,noarchive') && !str_contains($head, 'rel="canonical"') && !str_contains($head, 'application/ld+json'), 'Private preview metadata leaked.');
$rules = file_get_contents(dirname(__DIR__) . '/.htaccess');
preg_match('/<FilesMatch "([^"]+)"/', $rules, $rule);
foreach (['cloudsys-config.php', 'cloudsys-config.php.bak', '.env', '.env.production'] as $name) {
    audit_check(preg_match('~' . $rule[1] . '~', $name) === 1, 'Private configuration not denied: ' . $name);
}
audit_check(!is_file(dirname(__DIR__) . '/cloudsys-config.php'), 'Private config remains in public folder.');
echo "Handoff routing, shared preview, and private config checks passed.\n";

$directory = sys_get_temp_dir() . '/cloudsys-audit-test-' . bin2hex(random_bytes(10));
mkdir($directory, 0700);
try {
    foreach (['contact' => 0, 'chat' => 29] as $mode => $initial) {
        cloudsys_write_state($directory . '/' . $mode, ['count' => $initial]);
        $workers = [];
        for ($i = 0; $i < 12; $i++) {
            $process = proc_open([PHP_BINARY, '-n', __FILE__, '--worker', $directory, $mode, (string) $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Cannot start concurrency worker.');
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $accepted = 0;
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            audit_check(proc_close($process) === 0, 'Concurrency worker failed: ' . $errors);
            audit_check(in_array($output, ['accepted', 'busy', 'limited'], true), 'Unexpected worker result: ' . $output . $errors);
            if ($output === 'accepted') $accepted++;
        }
        audit_check($accepted === 1, 'Concurrent requests exceeded the ' . $mode . ' limit.');
        audit_check(cloudsys_read_state($directory . '/' . $mode)['count'] === $initial + 1, 'Lost counter write.');
        echo ucfirst($mode) . " concurrency passed: 12 simultaneous workers, exactly one accepted.\n";
    }
    // Failure must never silently disable enforcement.
    file_put_contents($directory . '/corrupt', '{invalid');
    $failed = false;
    try { cloudsys_read_state($directory . '/corrupt'); } catch (RuntimeException $error) { $failed = true; }
    audit_check($failed, 'Corrupt state failed open.');
    $failed = false;
    try { cloudsys_write_state($directory . '/missing-parent/state', []); } catch (RuntimeException $error) { $failed = true; }
    audit_check($failed, 'Unwritable state failed open.');
    echo "Fail-closed state checks passed.\n";
} finally {
    // Only remove this test's uniquely-created files, after every worker exits.
    foreach (glob($directory . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    rmdir($directory);
}
