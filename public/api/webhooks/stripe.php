<?php
declare(strict_types=1);

/**
 * Stripe webhook endpoint — confirms orders created 'pending' at checkout-start.
 * Never creates an order here. Idempotent via the webhook_events ledger and a
 * pending-guarded status transition, so replays never double-fulfil.
 * No session, no CSRF — authenticity comes from the Stripe signature.
 */

require_once __DIR__ . '/../../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/StripeService.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/TicketTokenRepo.php';
require_once INCLUDES_PATH . '/Mailer.php';
require_once INCLUDES_PATH . '/OrderEmail.php';

header('Content-Type: application/json');

$stripeConfigPath = __DIR__ . '/../../config/stripe.php';
if (!is_file($stripeConfigPath)) {
    http_response_code(500);
    echo '{"error":"stripe not configured"}';
    exit;
}

try {
    $pdo    = Database::getInstance();
    $stripe = new StripeService(require $stripeConfigPath);
} catch (Throwable $e) {
    http_response_code(500);
    echo '{"error":"bootstrap failed"}';
    exit;
}

/**
 * Resolve the transaction for a PaymentIntent: by stored PI id, falling back to
 * the transaction_id we stamped in metadata (covers the rare case where the
 * PI id failed to persist after the charge).
 */
function resolveTxn(array $pi): ?array
{
    $txn = TransactionRepo::getByPaymentIntent((string)($pi['id'] ?? ''));
    if ($txn) {
        return $txn;
    }
    $metaId = (int)($pi['metadata']['transaction_id'] ?? 0);
    return $metaId > 0 ? TransactionRepo::getById($metaId) : null;
}

$payload   = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = $stripe->verifyWebhook($payload, $sigHeader);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Idempotency: record the event id; a duplicate delivery is a no-op.
try {
    $ins = $pdo->prepare('INSERT IGNORE INTO webhook_events (event_id, type) VALUES (?, ?)');
    $ins->execute([(string)($event['id'] ?? ''), (string)($event['type'] ?? '')]);
    if ($ins->rowCount() === 0) {
        http_response_code(200);
        echo '{"received":true,"replay":true}';
        exit;
    }
} catch (Throwable $e) {
    error_log('[stripe-webhook] idempotency insert failed: ' . $e->getMessage());
    http_response_code(500);
    echo '{"error":"ledger error"}';
    exit;
}

switch ($event['type'] ?? '') {
    case 'payment_intent.succeeded':
        $pi  = $event['data']['object'] ?? [];
        $txn = resolveTxn($pi);
        if (!$txn) {
            error_log('[stripe-webhook] succeeded for unknown PI ' . ($pi['id'] ?? '?'));
            break;
        }

        // Verify the amount paid matches what we recorded before fulfilling.
        $expectedCents = (int) round((float)$txn['total_amount'] * 100);
        $paidCents     = (int) ($pi['amount_received'] ?? 0);
        if ($paidCents !== $expectedCents) {
            error_log("[stripe-webhook] amount mismatch txn {$txn['transaction_ref']}: paid $paidCents expected $expectedCents");
            break;
        }

        // Guarded transition — only the first delivery flips pending→paid and
        // therefore only it runs the inventory side effects.
        if (!TransactionRepo::transitionFromPending((int)$txn['id'], 'paid')) {
            break; // already handled
        }

        // ShowtimeRepo::decrementTickets() is the atomic capacity claim (single
        // UPDATE...WHERE, per backend-commerce-concurrency) — its bool return is
        // the only thing standing between "sold out" and a phantom double-sale.
        // Policy: full-reject. If ANY line item in this order can't be claimed,
        // the whole order is refunded and voided — no partial fulfillment, so we
        // never have to explain "2 of your 3 tickets are valid" to a customer.
        //
        // To reproduce the race this guards against: open two terminals, find a
        // showtime with `available_tickets - tickets_sold = 1` (or set one via
        // admin/showtime-edit.php), then fire two concurrent capacity claims at it:
        //   php -r '$p=new PDO(...); $s=$p->prepare("UPDATE showtimes SET tickets_sold=tickets_sold+1 WHERE id=? AND (available_tickets-tickets_sold)>=1"); $s->execute([ID]); var_dump($s->rowCount());'
        // run twice in parallel (`&` in bash, or two terminal tabs at once). Fixed:
        // exactly one rowCount()===1, the other rowCount()===0. Broken (read-then-
        // write instead of this conditional UPDATE): both can return success and
        // tickets_sold exceeds available_tickets.
        $concessionRepo = new ConcessionRepo($pdo);
        $claimed = []; // what we've successfully committed so far, to reverse on reject
        $oversold = false;

        foreach ($txn['items'] as $li) {
            $itemId  = (int)$li['item_id'];
            $qtySold = (int)$li['quantity'];
            if ($qtySold < 1) continue;

            if ($li['item_type'] === 'ticket') {
                if (ShowtimeRepo::decrementTickets($itemId, $qtySold)) {
                    $claimed[] = ['type' => 'ticket', 'item_id' => $itemId, 'qty' => $qtySold];
                } else {
                    error_log("[stripe-webhook] SOLD OUT: showtime $itemId short by qty $qtySold on txn {$txn['transaction_ref']} — rejecting whole order");
                    $oversold = true;
                    break;
                }
            } elseif ($li['item_type'] === 'concession') {
                $product = $concessionRepo->getById($itemId);
                if ($product && (int)$product['stock_quantity'] > 0) {
                    $newStock = max(0, (int)$product['stock_quantity'] - $qtySold);
                    InventoryRepo::logChange($itemId, 'sale', -$qtySold, $newStock, 'website', $txn['transaction_ref']);
                    $claimed[] = ['type' => 'concession', 'item_id' => $itemId, 'qty' => $qtySold];
                }
            }
        }

        if ($oversold) {
            // Reverse every claim already committed for this order, then void +
            // refund. No tokens have been minted yet at this point in the flow.
            foreach ($claimed as $c) {
                if ($c['type'] === 'ticket') {
                    ShowtimeRepo::restoreTickets($c['item_id'], $c['qty']);
                } else {
                    $product = $concessionRepo->getById($c['item_id']);
                    if ($product) {
                        $restored = (int)$product['stock_quantity'] + $c['qty'];
                        InventoryRepo::logChange($c['item_id'], 'restock', $c['qty'], $restored, 'website', "Oversell auto-refund {$txn['transaction_ref']}");
                    }
                }
            }

            TransactionRepo::voidTransaction((int)$txn['id']);

            try {
                $stripe->refund((string)($pi['id'] ?? ''));
            } catch (Throwable $e) {
                error_log('[stripe-webhook] refund failed for oversold txn ' . ($txn['transaction_ref'] ?? '?') . ': ' . $e->getMessage());
            }

            error_log('[stripe-webhook] oversold order fully refunded+voided: ' . ($txn['transaction_ref'] ?? '?'));
            break;
        }

        // Mint one QR check-in token per ticket unit — only here, inside the
        // guarded pending→paid transition, so it fires exactly once per order.
        $tickets = [];
        try {
            $tickets = TicketTokenRepo::generateForTransaction((int)$txn['id']);
        } catch (Throwable $e) {
            error_log('[stripe-webhook] ticket token generation failed for ' . ($txn['transaction_ref'] ?? '?') . ': ' . $e->getMessage());
        }

        // Send the order-confirmation receipt — a mail failure (or no SendGrid
        // key yet) must never fail the webhook, so this is best-effort and
        // fully isolated.
        try {
            $custEmail = trim((string)($txn['customer_email'] ?? ''));
            if ($custEmail !== '') {
                $mail = OrderEmail::build($txn, $tickets);
                Mailer::send($custEmail, (string)($txn['customer_name'] ?? ''), $mail['subject'], $mail['html'], $mail['text'], $mail['inlineImages']);
            }
        } catch (Throwable $e) {
            error_log('[stripe-webhook] receipt email failed for ' . ($txn['transaction_ref'] ?? '?') . ': ' . $e->getMessage());
        }
        break;

    case 'payment_intent.payment_failed':
        $pi  = $event['data']['object'] ?? [];
        $txn = resolveTxn($pi);
        if ($txn) {
            TransactionRepo::transitionFromPending((int)$txn['id'], 'failed');
        }
        break;

    case 'charge.refunded':
        // Refunds are reconciled through the admin Void flow today; log for now.
        error_log('[stripe-webhook] charge.refunded received: ' . ($event['id'] ?? '?'));
        break;

    default:
        // Unhandled event type — acknowledged so Stripe stops retrying.
        break;
}

http_response_code(200);
echo '{"received":true}';
