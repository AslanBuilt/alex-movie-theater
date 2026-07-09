<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/PosAuth.php';
require_once INCLUDES_PATH . '/RateLimiter.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/TicketTokenRepo.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/QrCode.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function kioskFail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kioskFail(405, 'Method not allowed.');
}

if (!RateLimiter::allow('kiosk-checkout:' . RateLimiter::clientIp(), 20, 60)) {
    RateLimiter::reject429();
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    kioskFail(400, 'Malformed request.');
}

$method = ($data['method'] ?? '') === 'card' ? 'card' : 'cash';
$rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
if (!$rawItems) {
    kioskFail(400, 'The cart is empty.');
}

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    kioskFail(503, 'The kiosk is temporarily unavailable. Please try again.');
}

if ($method === 'cash') {
    $pin = trim((string)($data['pin'] ?? ''));
    if ($pin === '') {
        kioskFail(401, 'PIN is required for cash checkout.');
    }
    $auth = new PosAuth($db);
    $pinResult = $auth->verifyPinOnly($pin);
    if (!$pinResult['ok']) {
        kioskFail($pinResult['locked'] ? 423 : 401, $pinResult['error']);
    }
}

$concLines   = [];
$concQtyById = [];
$ticketLines = [];
$ticketQtyById = [];

foreach ($rawItems as $item) {
    if (!is_array($item)) {
        continue;
    }
    $kind = (string)($item['kind'] ?? '');
    $qty = max(0, (int)($item['qty'] ?? 0));
    if ($qty < 1) {
        continue;
    }
    if ($kind === 'concession') {
        $id = (int)($item['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $option = isset($item['option']) && $item['option'] !== '' ? (string)$item['option'] : null;
        $concLines[] = ['id' => $id, 'option' => $option, 'qty' => $qty];
        $concQtyById[$id] = ($concQtyById[$id] ?? 0) + $qty;
    } elseif ($kind === 'ticket') {
        $sid = (int)($item['showtime_id'] ?? 0);
        $age = (string)($item['age'] ?? '');
        if ($sid < 1 || !in_array($age, ['Adult', 'Child'], true)) {
            continue;
        }
        $ticketLines[] = ['showtime_id' => $sid, 'age' => $age, 'qty' => $qty];
        $ticketQtyById[$sid] = ($ticketQtyById[$sid] ?? 0) + $qty;
    }
}

if (!$concLines && !$ticketLines) {
    kioskFail(400, 'No valid items in the cart.');
}

$txnRef = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));

try {
    $db->beginTransaction();
    ksort($concQtyById);
    ksort($ticketQtyById);

    $concRows = [];
    foreach ($concQtyById as $id => $needed) {
        $stmt = $db->prepare(
            'SELECT id, name, price, stock_quantity, is_available
             FROM concessions
             WHERE id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['is_available'] !== 1) {
            $db->rollBack();
            kioskFail(409, 'An item in the order is no longer available. Please remove it and try again.');
        }
        if ((int)$row['stock_quantity'] < $needed) {
            $db->rollBack();
            kioskFail(409, 'Sorry — ' . (string)$row['name'] . ' just sold out. Remove it or reduce the quantity.');
        }
        $concRows[$id] = $row;
    }

    $showtimeRows = [];
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
            kioskFail(409, 'A showtime in the order is no longer available. Please remove it and try again.');
        }
        $remaining = (int)$row['available_tickets'] - (int)$row['tickets_sold'];
        if ($remaining < $needed) {
            $db->rollBack();
            kioskFail(409, 'Sorry — tickets for ' . ((string)($row['title'] ?? 'that movie')) . ' just sold out. Reduce the quantity.');
        }
        $showtimeRows[$sid] = $row;
    }

    $lineItems = [];
    $total = 0.0;
    $hasTicket = false;
    $hasConc = false;

    foreach ($concLines as $cl) {
        $row = $concRows[$cl['id']];
        $price = round((float)$row['price'], 2);
        $sub = round($price * $cl['qty'], 2);
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
        $sr = $showtimeRows[$tl['showtime_id']];
        $price = round(ticketPrice($tl['age']), 2);
        $sub = round($price * $tl['qty'], 2);
        $total += $sub;
        $hasTicket = true;
        $name = 'Ticket: ' . ((string)($sr['title'] ?? 'Movie'))
            . (!empty($sr['showtime_date']) ? ' — ' . ((new DateTime((string)$sr['showtime_date']))->format('D, M j')) . (!empty($sr['showtime_time']) ? ' ' . date('g:i A', strtotime((string)$sr['showtime_time'])) : '') : ((string)$sr['label'] ?? ''));
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

    $total = round($total, 2);
    $txnType = $hasTicket && $hasConc ? 'combo' : ($hasTicket ? 'ticket' : 'concession');

    // ── Assign the shout-able daily order number for this kiosk sale.
    $dailyOrderNumber = TransactionRepo::nextDailyOrderNumber();

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
        ':channel'   => 'kiosk',
        ':total'     => $total,
        ':status'    => 'paid',
        ':method'    => $method === 'card' ? 'card_mock' : 'cash',
    ]);
    $txnId = (int)$db->lastInsertId();
    if ($txnId < 1) {
        throw new RuntimeException('Failed to create transaction');
    }

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
            ':src'  => 'kiosk',
            ':note' => $txnRef,
        ]);
    }

    $updSeats = $db->prepare('UPDATE showtimes SET tickets_sold = tickets_sold + :qty WHERE id = :id');
    foreach ($ticketQtyById as $sid => $needed) {
        $updSeats->execute([':qty' => $needed, ':id' => $sid]);
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[kiosk-checkout] ' . $e->getMessage());
    kioskFail(500, 'We could not complete your order. Please try again.');
}

$ticketTokens = [];
if ($hasTicket) {
    try {
        foreach (TicketTokenRepo::generateForTransaction($txnId) as $token) {
            $ticketTokens[] = $token + ['ticket_qr' => QrCode::pngDataUri((string)$token['ticket_token'])];
        }
    } catch (Throwable $e) {
        error_log('[kiosk-checkout] ticket token generation failed: ' . $e->getMessage());
    }
}

$responseItems = [];
foreach ($lineItems as $li) {
    $responseItems[] = [
        'item_type'       => $li['item_type'],
        'item_name'       => $li['item_name'],
        'quantity'        => $li['quantity'],
        'unit_price'      => $li['unit_price'],
        'selected_option' => $li['selected_option'],
        'subtotal'        => $li['subtotal'],
    ];
}

echo json_encode([
    'ok'                 => true,
    'transaction_ref'    => $txnRef,
    'daily_order_number' => $dailyOrderNumber,
    'total'              => $total,
    'items'              => $responseItems,
    'ticket_tokens'      => $ticketTokens,
]);
