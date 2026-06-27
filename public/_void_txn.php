<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated single-transaction void harness.
 *
 * Owner-requested 2026-06-27 to void the leftover Stripe test txn TXN-609FF7C8,
 * since voiding is admin-gated and no admin credentials are shared. Mirrors
 * admin/transaction-void.php EXACTLY (voidTransaction + InventoryRepo::logChange
 * restock + ShowtimeRepo::restoreTickets). Token-gated (key checked before any
 * side effect or unlink); SELF-DELETES via ?action=cleanup. git-rm after use.
 *
 * Actions (require ?key=): inspect&ref= | void&ref= | cleanup
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

const VOID_KEY = 'b8e1047fa39c52d6710be4923fd8a6c5';

function out(array $d): void { echo json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); exit; }
function fail(int $code, string $msg): void { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

// ── Token gate FIRST — before any DB work, side effect, or self-delete ──────────
$key = (string)($_GET['key'] ?? '');
if (!hash_equals(VOID_KEY, $key)) { http_response_code(404); echo 'Not found'; exit; }

$action = (string)($_GET['action'] ?? 'inspect');

if ($action === 'cleanup') {
    out(['ok' => true, 'deleted' => @unlink(__FILE__)]);
}

try { $db = Database::getInstance(); } catch (\Throwable $e) { fail(503, 'DB unavailable'); }

$ref = (string)($_GET['ref'] ?? '');
if ($ref === '') fail(400, 'missing ref');
$txn = TransactionRepo::getByRef($ref);
if (!$txn) fail(404, 'transaction not found: ' . $ref);

if ($action === 'inspect') {
    out(['ok' => true, 'txn' => [
        'ref' => $txn['transaction_ref'], 'id' => (int)$txn['id'], 'type' => $txn['type'],
        'status' => $txn['payment_status'], 'total' => $txn['total_amount'],
        'channel' => $txn['source_channel'] ?? null, 'created' => $txn['created_at'] ?? null,
        'items' => array_map(fn($li) => [
            'item_type' => $li['item_type'], 'item_id' => (int)$li['item_id'],
            'name' => $li['item_name'], 'qty' => (int)$li['quantity'],
        ], $txn['items']),
    ]]);
}

if ($action === 'void') {
    $id = (int)$txn['id'];
    if (($txn['payment_status'] ?? '') === 'voided') {
        out(['ok' => true, 'already_voided' => true, 'ref' => $ref]);
    }
    $wasPaid = ($txn['payment_status'] ?? '') === 'paid';
    if (!TransactionRepo::voidTransaction($id)) fail(500, 'voidTransaction failed');

    $conc = new ConcessionRepo($db);
    $restockedUnits = 0; $restoredTickets = 0; $detail = [];
    if ($wasPaid) {
        foreach ($txn['items'] as $li) {
            $itemId = (int)$li['item_id'];
            $qty    = (int)$li['quantity'];
            if ($qty < 1) continue;
            if ($li['item_type'] === 'concession') {
                $p = $conc->getById($itemId);
                if ($p) {
                    $newQty = (int)$p['stock_quantity'] + $qty;
                    InventoryRepo::logChange($itemId, 'restock', $qty, $newQty, 'admin', 'Void ' . $ref);
                    $restockedUnits += $qty;
                    $detail[] = ['concession' => $itemId, 'name' => $li['item_name'], 'added' => $qty, 'new' => $newQty];
                }
            } elseif ($li['item_type'] === 'ticket') {
                if (ShowtimeRepo::restoreTickets($itemId, $qty)) {
                    $restoredTickets += $qty;
                    $detail[] = ['showtime' => $itemId, 'name' => $li['item_name'], 'restored' => $qty];
                }
            }
        }
    }
    out(['ok' => true, 'voided' => $ref, 'txn_id' => $id, 'was_paid' => $wasPaid,
         'restocked_units' => $restockedUnits, 'restored_tickets' => $restoredTickets, 'detail' => $detail]);
}

fail(400, 'unknown action: ' . $action);
