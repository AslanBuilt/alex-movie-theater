<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated diagnostic. Answers: for the most recent test
 * purchase, did it reach 'paid', was a receipt email attempted, and what did
 * the mail transport (SendGrid or PHP mail() fallback) actually do?
 * Usage: /_maildiag2.php?key=THE_TOKEN
 * REMOVE after use.
 */

$TOKEN = 'maildiag2-9f3k7x2m';
if (!hash_equals($TOKEN, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Mailer.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Recent transactions (last 5) ===\n";
$rows = TransactionRepo::getRecent(5);
foreach ($rows as $r) {
    echo sprintf(
        "ref=%s  status=%s  email=%s  created=%s  stripe_pi=%s\n",
        $r['transaction_ref'] ?? '?',
        $r['payment_status'] ?? '?',
        $r['customer_email'] ?? '(blank)',
        $r['created_at'] ?? '?',
        $r['stripe_payment_intent_id'] ?? '(none)'
    );
}

echo "\n=== Mail transport state ===\n";
echo "PHP mail() available: " . (function_exists('mail') ? 'yes' : 'NO (disabled on host)') . "\n";
echo "SendGrid configured:  " . (Mailer::isConfigured() ? 'yes' : 'no (mail() fallback active)') . "\n";

echo "\n=== error_log path ===\n";
$candidates = array_filter([
    ini_get('error_log') ?: null,
    __DIR__ . '/error_log',
    __DIR__ . '/api/webhooks/error_log',
    dirname(__DIR__) . '/error_log',
]);
foreach ($candidates as $path) {
    echo $path . ' — ' . (is_file($path) ? 'exists, ' . filesize($path) . ' bytes' : 'not found') . "\n";
}

echo "\n=== Matching log lines (Mailer / stripe-webhook / QrCode) ===\n";
$found = false;
foreach ($candidates as $path) {
    if (!is_file($path) || !is_readable($path)) {
        continue;
    }
    $lines = file($path);
    if ($lines === false) {
        continue;
    }
    $tail = array_slice($lines, -400);
    foreach ($tail as $line) {
        if (stripos($line, '[Mailer]') !== false
            || stripos($line, 'stripe-webhook') !== false
            || stripos($line, '[QrCode]') !== false) {
            echo $line;
            $found = true;
        }
    }
}
if (!$found) {
    echo "(no matching lines found in any readable log)\n";
}
