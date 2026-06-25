<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';
require_once INCLUDES_PATH . '/PaymentGateway.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Parse + validate input ────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// CSRF: clients embed the session token in the request body
session_name('ALEX_ADMIN_SESS');
session_start();

$csrfToken = (string)($body['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid session token']);
    exit;
}

$items    = $body['items']    ?? [];
$customer = $body['customer'] ?? [];

if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No items in order']);
    exit;
}

// ── Validate each item & build line items ─────────────────────────────────────
$pdo  = Database::getInstance();
$repo = new ConcessionRepo($pdo);
$lineItems   = [];
$totalAmount = 0.0;
$hasTicket   = false;
$hasConcession = false;

foreach ($items as $item) {
    $type = (string)($item['type'] ?? '');
    $id   = (int)($item['id'] ?? 0);
    $qty  = max(1, (int)($item['qty'] ?? 1));
    $opt  = isset($item['option']) ? trim((string)$item['option']) : null;

    if ($type === 'ticket') {
        $showtime = ShowtimeRepo::getById($id);
        if (!$showtime || !$showtime['is_active']) {
            echo json_encode(['success' => false, 'error' => 'Showtime not found or inactive']);
            exit;
        }
        $available = (int)$showtime['available_tickets'] - (int)$showtime['tickets_sold'];
        if ($available < $qty) {
            echo json_encode(['success' => false, 'error' => "Only $available ticket(s) remaining for this showtime"]);
            exit;
        }
        $unitPrice = 5.00; // $5 per ticket
        $lineItems[] = [
            'item_type'       => 'ticket',
            'item_id'         => $id,
            'item_name'       => 'Ticket: ' . $showtime['label'],
            'quantity'        => $qty,
            'unit_price'      => $unitPrice,
            'selected_option' => null,
            'subtotal'        => round($unitPrice * $qty, 2),
            '_showtime_id'    => $id,
        ];
        $hasTicket    = true;
        $totalAmount += $unitPrice * $qty;

    } elseif ($type === 'concession') {
        $product = $repo->getById($id);
        if (!$product || !$product['is_available']) {
            echo json_encode(['success' => false, 'error' => 'Product not found or unavailable']);
            exit;
        }
        // Stock check (only if stock tracking is on)
        if ((int)$product['stock_quantity'] > 0 && $qty > (int)$product['stock_quantity']) {
            echo json_encode(['success' => false, 'error' => 'Item out of stock: ' . $product['name']]);
            exit;
        }
        $unitPrice = (float)$product['price'];
        $lineItems[] = [
            'item_type'       => 'concession',
            'item_id'         => $id,
            'item_name'       => $product['name'],
            'quantity'        => $qty,
            'unit_price'      => $unitPrice,
            'selected_option' => $opt,
            'subtotal'        => round($unitPrice * $qty, 2),
            '_stock_qty'      => (int)$product['stock_quantity'],
        ];
        $hasConcession = true;
        $totalAmount  += $unitPrice * $qty;

    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown item type: ' . $type]);
        exit;
    }
}

$totalAmount = round($totalAmount, 2);

// Determine transaction type
$txnType = 'concession';
if ($hasTicket && $hasConcession) $txnType = 'combo';
elseif ($hasTicket)               $txnType = 'ticket';

// ── Generate transaction ref ──────────────────────────────────────────────────
$txnRef = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));

// ── Insert pending transaction ────────────────────────────────────────────────
$txnId = TransactionRepo::create([
    'transaction_ref'  => $txnRef,
    'type'             => $txnType,
    'source_channel'   => 'website',
    'total_amount'     => $totalAmount,
    'payment_status'   => 'pending',
    'payment_method'   => 'mock',
    'customer_name'    => isset($customer['name'])  && $customer['name']  !== '' ? $customer['name']  : null,
    'customer_email'   => isset($customer['email']) && $customer['email'] !== '' ? $customer['email'] : null,
]);

if ($txnId === 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create transaction record']);
    exit;
}

// Insert line items
foreach ($lineItems as $li) {
    TransactionRepo::addItem($txnId, $li);
}

// ── Charge via gateway ────────────────────────────────────────────────────────
$gateway = new MockPaymentGateway();
$result  = $gateway->charge($totalAmount, ['transaction_ref' => $txnRef]);

if (!$result['success']) {
    TransactionRepo::updateStatus($txnId, 'failed');
    echo json_encode(['success' => false, 'error' => $result['error'] ?: 'Payment failed']);
    exit;
}

// ── Payment succeeded — commit side effects ───────────────────────────────────
TransactionRepo::updateStatus($txnId, 'paid', $result['gateway_ref']);

foreach ($lineItems as $li) {
    if ($li['item_type'] === 'ticket') {
        ShowtimeRepo::decrementTickets((int)$li['_showtime_id'], (int)$li['quantity']);
    } elseif ($li['item_type'] === 'concession' && (int)($li['_stock_qty'] ?? 0) > 0) {
        $newStock = (int)$li['_stock_qty'] - (int)$li['quantity'];
        InventoryRepo::logChange(
            (int)$li['item_id'],
            'sale',
            -(int)$li['quantity'],
            $newStock,
            'website'
        );
    }
}

// Empty the session cart now that its concession items have been ordered.
// Ticket-only orders are built from URL params and never use the session cart,
// so leave it untouched to preserve any concessions the user still has pending.
if ($hasConcession) {
    $_SESSION['cart'] = [];
}

echo json_encode([
    'success'         => true,
    'transaction_ref' => $txnRef,
    'total'           => $totalAmount,
    'message'         => 'Payment successful',
]);
