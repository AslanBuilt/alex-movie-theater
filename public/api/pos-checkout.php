<?php
declare(strict_types=1);

/**
 * Employee POS checkout endpoint (/api/pos-checkout.php).
 *
 * Records a walk-up register sale in ONE PDO transaction:
 *   beginTransaction()
 *     → SELECT ... FOR UPDATE every concession row in the cart
 *     → SELECT ... FOR UPDATE every showtime row referenced by a ticket line
 *     → verify stock / seat availability for each line
 *     → if any short: rollBack() and return a clear, item-named error
 *     → else: INSERT transaction + transaction_items,
 *             UPDATE concessions.stock_quantity, INSERT inventory_log rows,
 *             UPDATE showtimes.tickets_sold for ticket lines
 *   commit()
 *
 * All SQL is inlined here ON PURPOSE: InventoryRepo::logChange() and the cart
 * helpers open their OWN beginTransaction(), which PDO cannot nest. Reusing them
 * inside this transaction would throw. So we write the rows directly.
 *
 * Sales are tagged source_channel = 'staff_register' (transactions) and
 * source = 'staff_register' (inventory_log) so Task 3 reports can separate
 * walk-up register sales from online orders.
 *
 * Auth: requires a valid POS session (employee PIN OR admin). CSRF via the
 * X-CSRF-Token header (mirrors api/cart.php). Rate-limited per IP.
 */

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/PosAuth.php';
require_once INCLUDES_PATH . '/RateLimiter.php';
require_once INCLUDES_PATH . '/TicketTokenRepo.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

/** Emit a JSON error and exit. */
function posFail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

PosAuth::bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    posFail(405, 'Method not allowed.');
}

// Rate-limit the checkout path (mirrors website checkout: 10 / 60s per IP).
if (!RateLimiter::allow('pos-checkout:' . RateLimiter::clientIp(), 20, 60)) {
    RateLimiter::reject429();
}

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    posFail(503, 'The register is temporarily unavailable. Please try again.');
}

$auth = new PosAuth($db);
if (!$auth->isLoggedIn()) {
    posFail(401, 'Your session expired. Please sign in again.');
}

// CSRF — header preferred, POST field fallback.
$csrfHeader = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$csrfToken  = $csrfHeader !== '' ? $csrfHeader : (string)($_POST['csrf_token'] ?? '');
if (!$auth->validateCsrf($csrfToken)) {
    posFail(403, 'Invalid session token. Please reload the register.');
}

// Parse JSON body.
$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    posFail(400, 'Malformed request.');
}

$method = ($data['method'] ?? '') === 'card' ? 'card' : 'cash';
$rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
if (!$rawItems) {
    posFail(400, 'The cart is empty.');
}

// Ticket prices come from ticketPrice() (includes/helpers.php), sourced from the
// TICKET_PRICE_ADULT/TICKET_PRICE_CHILD constants — the single source of truth.

// ── Normalize the incoming cart into concession + ticket request maps ─────────
// Concessions keyed by id (qty summed, options kept per id+option line).
$concLines   = []; // list of ['id'=>int,'option'=>?string,'qty'=>int]
$concQtyById = []; // id => total qty needed (for the FOR UPDATE stock check)
$ticketLines = []; // list of ['showtime_id'=>int,'age'=>string,'qty'=>int]
$ticketQtyById = []; // showtime_id => total qty needed

foreach ($rawItems as $it) {
    if (!is_array($it)) continue;
    $kind = (string)($it['kind'] ?? '');
    $qty  = (int)($it['qty'] ?? 0);
    if ($qty < 1) continue;

    if ($kind === 'concession') {
        $id = (int)($it['id'] ?? 0);
        if ($id < 1) continue;
        $option = isset($it['option']) && $it['option'] !== '' && $it['option'] !== null
            ? substr((string)$it['option'], 0, 100)
            : null;
        $concLines[] = ['id' => $id, 'option' => $option, 'qty' => $qty];
        $concQtyById[$id] = ($concQtyById[$id] ?? 0) + $qty;
    } elseif ($kind === 'ticket') {
        $sid = (int)($it['showtime_id'] ?? 0);
        $age = (string)($it['age'] ?? '');
        if ($sid < 1 || !in_array($age, ['Adult', 'Child'], true)) continue;
        $ticketLines[] = ['showtime_id' => $sid, 'age' => $age, 'qty' => $qty];
        $ticketQtyById[$sid] = ($ticketQtyById[$sid] ?? 0) + $qty;
    }
}

if (!$concLines && !$ticketLines) {
    posFail(400, 'No valid items in the cart.');
}

$txnRef = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));

try {
    $db->beginTransaction();

    // Lock rows in a deterministic (ascending id) order across all terminals so
    // two registers ringing the same items in opposite cart order can't deadlock.
    ksort($concQtyById);
    ksort($ticketQtyById);

    // ── Lock + verify concessions ────────────────────────────────────────────
    $concRows = []; // id => row (name, price, stock_quantity)
    foreach ($concQtyById as $id => $needed) {
        $stmt = $db->prepare(
            'SELECT id, name, price, stock_quantity, is_available
             FROM concessions WHERE id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['is_available'] !== 1) {
            $db->rollBack();
            posFail(409, 'An item in the order is no longer available. Please remove it and try again.');
        }
        if ((int)$row['stock_quantity'] < $needed) {
            $name = (string)$row['name'];
            $db->rollBack();
            posFail(409, 'Sorry — ' . $name . ' just sold out. Remove it or reduce the quantity.');
        }
        $concRows[$id] = $row;
    }

    // ── Lock + verify showtimes (tickets) ──────────────────────────────────────
    $stRows = []; // showtime_id => ['remaining'=>int,'title'=>string,'label'=>string]
    foreach ($ticketQtyById as $sid => $needed) {
        $stmt = $db->prepare(
            'SELECT s.id, s.movie_id, s.available_tickets, s.tickets_sold, s.is_active,
                    s.showtime_date, s.showtime_time, s.label, m.title
             FROM showtimes s
             LEFT JOIN movies m ON m.id = s.movie_id
             WHERE s.id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $sid]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['is_active'] !== 1) {
            $db->rollBack();
            posFail(409, 'A showtime in the order is no longer available. Please remove it and try again.');
        }
        $remaining = (int)$row['available_tickets'] - (int)$row['tickets_sold'];
        if ($remaining < $needed) {
            $title = (string)($row['title'] ?? 'that movie');
            $db->rollBack();
            posFail(409, 'Sorry — tickets for ' . $title . ' just sold out. Reduce the quantity.');
        }

        // Build a human label for the line item.
        $when = '';
        if (!empty($row['showtime_date'])) {
            try {
                $when = (new DateTime((string)$row['showtime_date']))->format('D, M j');
            } catch (\Throwable $e) {
                $when = '';
            }
            if (!empty($row['showtime_time'])) {
                $when .= ' ' . date('g:i A', strtotime((string)$row['showtime_time']));
            }
        } elseif (!empty($row['label'])) {
            $when = (string)$row['label'];
        }

        $stRows[$sid] = [
            'remaining' => $remaining,
            'title'     => (string)($row['title'] ?? 'Movie'),
            'when'      => $when,
        ];
    }

    // ── Compute totals + build the line items ──────────────────────────────────
    $lineItems = []; // for transaction_items
    $total     = 0.0;
    $hasTicket = false;
    $hasConc   = false;

    foreach ($concLines as $cl) {
        $row   = $concRows[$cl['id']];
        $price = round((float)$row['price'], 2);
        $sub   = round($price * $cl['qty'], 2);
        $total += $sub;
        $hasConc = true;
        $lineItems[] = [
            'item_type'       => 'concession',
            'item_id'         => $cl['id'],
            'item_name'       => (string)$row['name'],
            'quantity'        => $cl['qty'],
            'unit_price'      => $price,
            'selected_option' => $cl['option'],
            'subtotal'        => $sub,
        ];
    }

    foreach ($ticketLines as $tl) {
        $st    = $stRows[$tl['showtime_id']];
        $price = round(ticketPrice($tl['age']), 2);
        $sub   = round($price * $tl['qty'], 2);
        $total += $sub;
        $hasTicket = true;
        // Age lives in selected_option below, not baked into item_name — matches
        // the website checkout convention so admin/transaction-view.php and the
        // confirmation/email templates (which append selected_option themselves)
        // don't show the age twice.
        $name = 'Ticket: ' . $st['title']
            . ($st['when'] !== '' ? ' — ' . $st['when'] : '');
        $lineItems[] = [
            'item_type'       => 'ticket',
            'item_id'         => $tl['showtime_id'],
            'item_name'       => substr($name, 0, 200),
            'quantity'        => $tl['qty'],
            'unit_price'      => $price,
            'selected_option' => $tl['age'],
            'subtotal'        => $sub,
        ];
    }

    $total   = round($total, 2);
    $txnType = $hasTicket && $hasConc ? 'combo' : ($hasTicket ? 'ticket' : 'concession');

    // ── Assign the shout-able daily order number ('Order 47') inside the same
    // transaction so rolled-back sales do not consume a number.
    $dailyOrderNumber = TransactionRepo::nextDailyOrderNumber();

    // ── INSERT transaction ─────────────────────────────────────────────────────
    $ins = $db->prepare(
        'INSERT INTO transactions
            (transaction_ref, daily_order_number, type, source_channel, total_amount, payment_status, payment_method)
         VALUES
            (:ref, :daily_num, :type, :channel, :total, :status, :method)'
    );
    $ins->execute([
        ':ref'       => $txnRef,
        ':daily_num' => $dailyOrderNumber,
        ':type'      => $txnType,
        ':channel'   => 'staff_register',
        ':total'     => $total,
        ':status'    => 'paid', // POS payment is taken in person at confirm time
        ':method'    => $method === 'card' ? 'card_mock' : 'cash',
    ]);
    $txnId = (int)$db->lastInsertId();
    if ($txnId < 1) {
        throw new RuntimeException('Failed to create transaction');
    }

    // ── INSERT transaction_items ────────────────────────────────────────────────
    $insItem = $db->prepare(
        'INSERT INTO transaction_items
            (transaction_id, item_type, item_id, item_name, quantity, unit_price, selected_option, subtotal)
         VALUES
            (:txn, :type, :item_id, :name, :qty, :price, :option, :sub)'
    );
    foreach ($lineItems as $li) {
        $insItem->execute([
            ':txn'     => $txnId,
            ':type'    => $li['item_type'],
            ':item_id' => $li['item_id'],
            ':name'    => $li['item_name'],
            ':qty'     => $li['quantity'],
            ':price'   => $li['unit_price'],
            ':option'  => $li['selected_option'],
            ':sub'     => $li['subtotal'],
        ]);
    }

    // ── Decrement concession stock + write inventory_log ────────────────────────
    $updStock = $db->prepare(
        'UPDATE concessions SET stock_quantity = stock_quantity - :qty WHERE id = :id'
    );
    $insLog = $db->prepare(
        'INSERT INTO inventory_log
            (concession_id, change_type, qty_change, new_quantity, source, note)
         VALUES
            (:cid, :type, :chg, :new, :src, :note)'
    );
    foreach ($concQtyById as $id => $needed) {
        $newQty = (int)$concRows[$id]['stock_quantity'] - $needed;
        $updStock->execute([':qty' => $needed, ':id' => $id]);
        $insLog->execute([
            ':cid'  => $id,
            ':type' => 'sale',
            ':chg'  => -$needed,
            ':new'  => $newQty,
            ':src'  => 'staff_register',
            ':note' => $txnRef,
        ]);
    }

    // ── Increment showtime tickets_sold ─────────────────────────────────────────
    $updSeats = $db->prepare(
        'UPDATE showtimes SET tickets_sold = tickets_sold + :qty WHERE id = :id'
    );
    foreach ($ticketQtyById as $sid => $needed) {
        $updSeats->execute([':qty' => $needed, ':id' => $sid]);
    }

    $db->commit();
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[pos-checkout] ' . $e->getMessage());
    posFail(500, 'We could not complete the sale. Please try again.');
}

// Walk-up ticket sales are paid immediately (no webhook), so mint check-in
// tokens right here — same as the Stripe webhook does for online orders.
// Best-effort: a token-generation hiccup must not undo an already-committed sale.
// Tokens are returned to the register so it can offer "Check In Now?" —
// the customer is standing at the counter, so staff can check them in on the
// spot via the same /api/checkin.php the kiosk uses, instead of a kiosk walk.
$ticketTokens = [];
if ($hasTicket) {
    try {
        $ticketTokens = array_column(TicketTokenRepo::generateForTransaction($txnId), 'ticket_token');
    } catch (\Throwable $e) {
        error_log('[pos-checkout] ticket token generation failed for ' . $txnRef . ': ' . $e->getMessage());
    }
}

echo json_encode([
    'ok'              => true,
    'transaction_ref' => $txnRef,
    'total'           => $total,
    'ticket_tokens'   => $ticketTokens,
]);
