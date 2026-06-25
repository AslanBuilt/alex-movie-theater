<?php
declare(strict_types=1);

/**
 * TEMPORARY checkout-start diagnostic. Token-gated and SELF-DELETING:
 * it unlink()s itself after one authenticated run. Remove from the repo
 * after use so a later deploy never re-uploads it.
 *
 * Reports (without ever printing a full secret):
 *  - stripe.php presence + key prefixes/lengths/mode
 *  - a live `GET /v1/balance` Stripe call (discriminates a bad/empty key)
 *  - the exact `transactions` INSERT used by checkout, rolled back (no row left)
 */

$TOKEN = 'diag-7Kq2mZ9xPv4tLn8';

// Backstop: always remove this file when the script ends, however it ends.
register_shutdown_function(static function (): void {
    @unlink(__FILE__);
});

header('Content-Type: application/json');

if (($_GET['token'] ?? '') !== $TOKEN) {
    http_response_code(404);
    echo '{"error":"not found"}';
    return;
}

$out = ['generated_at' => date('c'), 'checks' => []];

// ── 1. stripe.php presence + key shape ───────────────────────────────────────
$stripePath = __DIR__ . '/config/stripe.php';
$sk = '';
if (!is_file($stripePath)) {
    $out['checks']['stripe_config'] = ['present' => false];
} else {
    $cfg = require $stripePath;
    $sk  = (string)($cfg['secret_key'] ?? '');
    $pk  = (string)($cfg['publishable_key'] ?? '');
    $wh  = (string)($cfg['webhook_secret'] ?? '');
    $out['checks']['stripe_config'] = [
        'present'        => true,
        'secret_prefix'  => substr($sk, 0, 8),
        'secret_len'     => strlen($sk),
        'pub_prefix'     => substr($pk, 0, 8),
        'pub_len'        => strlen($pk),
        'webhook_prefix' => substr($wh, 0, 6),
        'webhook_len'    => strlen($wh),
        'mode'           => (string)($cfg['mode'] ?? ''),
    ];
}

// ── 2. Live Stripe call: GET /v1/balance (cheap, read-only) ───────────────────
if ($sk !== '') {
    $ch = curl_init('https://api.stripe.com/v1/balance');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $sk],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    $decoded = json_decode((string)$resp, true);
    $out['checks']['stripe_balance_call'] = [
        'http_code'    => $code,
        'curl_error'   => $cerr !== '' ? $cerr : null,
        'stripe_error' => is_array($decoded) ? ($decoded['error']['message'] ?? null) : null,
        'looks_ok'     => $code === 200 && $cerr === '',
    ];
} else {
    $out['checks']['stripe_balance_call'] = ['skipped' => 'no secret_key'];
}

// ── 3. transactions INSERT path (exact columns from TransactionRepo::create) ──
try {
    require_once __DIR__ . '/config/config.php';
    require_once INCLUDES_PATH . '/Database.php';
    $pdo = Database::getInstance();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO transactions
            (transaction_ref, type, source_channel, total_amount, payment_status, payment_method, customer_name, customer_email)
         VALUES
            (:ref, :type, :channel, :total, :status, :method, :name, :email)'
    );
    $stmt->execute([
        ':ref'     => 'DIAG-PROBE',
        ':type'    => 'ticket',
        ':channel' => 'website',
        ':total'   => 5.00,
        ':status'  => 'pending',
        ':method'  => 'stripe',
        ':name'    => null,
        ':email'   => null,
    ]);
    $wouldId = (int)$pdo->lastInsertId();
    $pdo->rollBack(); // leave no test row behind
    $out['checks']['txn_insert'] = ['ok' => true, 'would_be_id' => $wouldId];
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $out['checks']['txn_insert'] = ['ok' => false, 'error' => $e->getMessage()];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
