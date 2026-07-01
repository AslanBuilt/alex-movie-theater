<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated E2E test harness for the QR ticket check-in feature.
 * Creates a $5 test-mode ticket purchase, confirms it via Stripe's pm_card_visa
 * test payment method (exercising the real webhook + token generation path),
 * reports results, then voids the transaction to restore inventory and
 * self-deletes. Not linked from anywhere; token-gated so it only runs once.
 */

$TOKEN = 'qrtest-8f3a1c9e2b6d4517';
if (($_GET['token'] ?? '') !== $TOKEN) {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/TicketTokenRepo.php';

header('Content-Type: application/json');
$out = [];

function stripeCurl(string $method, string $path, array $params, string $secretKey): array
{
    $ch = curl_init('https://api.stripe.com/v1' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secretKey, 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$resp, true) ?: [];
    return ['code' => $code, 'data' => $data];
}

try {
    $pdo = Database::getInstance();

    $stripeConfigPath = __DIR__ . '/config/stripe.php';
    if (!is_file($stripeConfigPath)) {
        throw new RuntimeException('stripe not configured on server');
    }
    $stripeConfig = require $stripeConfigPath;
    $secretKey = (string)($stripeConfig['secret_key'] ?? '');
    if ($secretKey === '') {
        throw new RuntimeException('no stripe secret key configured');
    }

    // 1. Find an active, future, purchasable showtime with spare capacity.
    $stmt = $pdo->query(
        "SELECT s.id, s.available_tickets, s.tickets_sold, m.title
         FROM showtimes s JOIN movies m ON m.id = s.movie_id
         WHERE s.is_active = 1 AND s.showtime_date >= CURDATE()
           AND (s.available_tickets - s.tickets_sold) > 0
         ORDER BY s.showtime_date ASC, s.showtime_time ASC LIMIT 1"
    );
    $show = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$show) {
        throw new RuntimeException('no active showtime with spare capacity found');
    }
    $out['showtime'] = $show;

    // 2. Create a pending transaction, mirroring checkout.php.
    $ref = 'TXN-QRTEST-' . strtoupper(bin2hex(random_bytes(3)));
    $txnId = TransactionRepo::create([
        'transaction_ref' => $ref,
        'type'            => 'ticket',
        'source_channel'  => 'website',
        'total_amount'    => 5.00,
        'payment_status'  => 'pending',
        'payment_method'  => 'card',
        'customer_name'   => 'QR Test Harness',
        'customer_email'  => 'taemoor.h@aslanadvisors.com',
    ]);
    if ($txnId < 1) {
        throw new RuntimeException('failed to create transaction');
    }
    TransactionRepo::addItem($txnId, [
        'item_type'       => 'ticket',
        'item_id'         => $show['id'],
        'item_name'       => 'Ticket: ' . $show['title'] . ' (Adult) [QR TEST - auto-voided]',
        'quantity'        => 1,
        'unit_price'      => 5.00,
        'selected_option' => 'Adult',
        'subtotal'        => 5.00,
    ]);
    $out['txn_id'] = $txnId;
    $out['ref']    = $ref;

    // 3. Create + confirm a PaymentIntent using Stripe's standard test payment method.
    $create = stripeCurl('POST', '/payment_intents', [
        'amount'                 => 500,
        'currency'               => 'usd',
        'payment_method_types[]' => 'card',
        'metadata[transaction_id]'  => (string)$txnId,
        'metadata[transaction_ref]' => $ref,
    ], $secretKey);
    if ($create['code'] >= 400) {
        throw new RuntimeException('create PI failed: ' . json_encode($create['data']));
    }
    $piId = (string)$create['data']['id'];
    TransactionRepo::setStripePaymentIntent($txnId, $piId);
    $out['payment_intent_id'] = $piId;

    $confirm = stripeCurl('POST', '/payment_intents/' . rawurlencode($piId) . '/confirm', [
        'payment_method' => 'pm_card_visa',
    ], $secretKey);
    $out['confirm_http_code'] = $confirm['code'];
    $out['pi_status_after_confirm'] = $confirm['data']['status'] ?? null;

    // 4. Poll for the webhook to flip payment_status to 'paid' (async delivery).
    $txn = null;
    for ($i = 0; $i < 10; $i++) {
        $txn = TransactionRepo::getById($txnId);
        if ($txn && $txn['payment_status'] === 'paid') break;
        sleep(1);
    }
    $out['payment_status'] = $txn['payment_status'] ?? 'unknown';
    $out['poll_attempts']  = $i + 1;

    // 5. Check ticket tokens generated by the webhook.
    $tokens = TicketTokenRepo::getByTransaction($txnId);
    $out['tokens_generated'] = count($tokens);
    $out['tokens'] = array_map(fn($t) => [
        'movie_title'  => $t['movie_title'],
        'when'         => $t['when'],
        'seq'          => $t['seq'],
        'seq_total'    => $t['seq_total'],
        'token_status' => $t['token_status'],
        'token_prefix' => substr($t['ticket_token'], 0, 12) . '...',
    ], $tokens);

    // 6. Clean up — void the transaction (restores the ticket + voids tokens),
    // regardless of whether it reached 'paid' (idempotent no-op if still pending).
    $cleanup = ['voided' => false, 'restored_tickets' => 0, 'tokens_voided' => 0];
    $finalTxn = TransactionRepo::getById($txnId);
    if ($finalTxn && $finalTxn['payment_status'] === 'paid') {
        if (TransactionRepo::voidTransaction($txnId)) {
            $cleanup['voided'] = true;
            foreach ($finalTxn['items'] as $li) {
                if ($li['item_type'] === 'ticket' && ShowtimeRepo::restoreTickets((int)$li['item_id'], (int)$li['quantity'])) {
                    $cleanup['restored_tickets'] += (int)$li['quantity'];
                }
            }
            $cleanup['tokens_voided'] = TicketTokenRepo::voidForTransaction($txnId);
        }
    } elseif ($finalTxn && $finalTxn['payment_status'] === 'pending') {
        TransactionRepo::updateStatus($txnId, 'failed', 'qrtest-cleanup');
        $cleanup['marked_failed_no_restock_needed'] = true;
    }
    $out['cleanup'] = $cleanup;

} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT);
@unlink(__FILE__);
