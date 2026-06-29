<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated mail diagnostic. Confirms whether the host can send
 * email via the Mailer (SendGrid if configured, else PHP mail()).
 * Usage: /_mailtest.php?key=THE_TOKEN&to=you@example.com
 * REMOVE after testing (retired via deploy.yml retire-file loop + git rm).
 */

$TOKEN = 'mailtest-7h2k9q4z';
if (!hash_equals($TOKEN, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Mailer.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Alex Theater mail diagnostic ===\n";
echo "PHP mail() available: " . (function_exists('mail') ? 'yes' : 'NO (disabled on host)') . "\n";
echo "SendGrid configured:  " . (Mailer::isConfigured() ? 'yes (using SendGrid)' : 'no (will use PHP mail())') . "\n";

$to = trim((string)($_GET['to'] ?? ''));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "\nProvide a recipient: add &to=you@example.com to the URL\n";
    exit;
}

$ok = Mailer::send(
    $to,
    'Test Recipient',
    'The Alex — mail test',
    '<div style="font-family:sans-serif"><h2>It works ✅</h2><p>This is a <strong>test email</strong> from The Alex checkout system. If you received it, order receipts will send too.</p></div>',
    "It works.\n\nThis is a test email from The Alex checkout system. If you received it, order receipts will send too."
);

echo "\nMailer::send() returned: " . ($ok ? 'TRUE — handed to the mail server' : 'FALSE — send failed (check host)') . "\n";
echo "Sent to: " . htmlspecialchars($to) . "\n";
echo "\nNow check that inbox AND its spam/junk folder.\n";
