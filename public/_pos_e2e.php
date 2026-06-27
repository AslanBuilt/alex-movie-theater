<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated POS E2E test + restock harness.
 *
 * Authorized by the site owner on 2026-06-27 to (a) restock concessions and
 * (b) verify the POS sale -> void -> restock/restore loop server-side, because
 * voiding is admin-gated and no admin credentials are being shared. It bypasses
 * admin auth ON PURPOSE via a single-use shared secret, and SELF-DELETES via
 * ?action=cleanup. The token check runs BEFORE any side effect or unlink so a
 * blind crawler can never trigger or delete it.
 *
 * Actions (all require ?key=):
 *   state                      -> snapshot concession stock + active showtime seats
 *   restock&to=50              -> set every concession stock to N (logged as 'restock')
 *   void&ref=TXN-XXXX          -> mirror admin/transaction-void.php: void + restock/restore
 *   cleanup                    -> unlink this file
 *
 * Remove from the repo (git rm) after use.
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

const E2E_KEY = 'a3f9d2c84b7e16059fce8b21d740a96c';

function out(array $d): void { echo json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); exit; }
function fail(int $code, string $msg): void { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

// ── Token gate FIRST — before any DB work, side effect, or self-delete ──────────
$key = (string)($_GET['key'] ?? '');
if (!hash_equals(E2E_KEY, $key)) { http_response_code(404); echo 'Not found'; exit; }

$action = (string)($_GET['action'] ?? 'state');

if ($action === 'cleanup') {
    $deleted = @unlink(__FILE__);
    out(['ok' => true, 'deleted' => $deleted]);
}

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    fail(503, 'DB unavailable');
}
$conc = new ConcessionRepo($db);

/** Snapshot of stock + seat state for before/after comparison. */
function snapshot(\PDO $db): array
{
    $concessions = $db->query(
        'SELECT id, name, stock_quantity FROM concessions ORDER BY id'
    )->fetchAll();
    $showtimes = $db->query(
        'SELECT s.id, m.title, s.available_tickets, s.tickets_sold,
                (s.available_tickets - s.tickets_sold) AS remaining
         FROM showtimes s LEFT JOIN movies m ON m.id = s.movie_id
         WHERE s.is_active = 1 ORDER BY s.id'
    )->fetchAll();
    return ['concessions' => $concessions, 'showtimes' => $showtimes];
}

switch ($action) {
    case 'state':
        out(['ok' => true, 'state' => snapshot($db)]);
        // no break needed (out exits)

    case 'restock': {
        $to = max(0, (int)($_GET['to'] ?? 50));
        $rows = $db->query('SELECT id, name, stock_quantity FROM concessions ORDER BY id')->fetchAll();
        $changed = [];
        foreach ($rows as $r) {
            $id  = (int)$r['id'];
            $old = (int)$r['stock_quantity'];
            if ($old === $to) { $changed[] = ['id' => $id, 'name' => $r['name'], 'old' => $old, 'new' => $to, 'noop' => true]; continue; }
            // logChange updates stock_quantity to $to AND writes an inventory_log row, atomically.
            InventoryRepo::logChange($id, 'restock', $to - $old, $to, 'admin', 'E2E bulk restock to ' . $to);
            $changed[] = ['id' => $id, 'name' => $r['name'], 'old' => $old, 'new' => $to];
        }
        out(['ok' => true, 'restocked_to' => $to, 'count' => count($changed), 'items' => $changed]);
    }

    case 'void': {
        $ref = (string)($_GET['ref'] ?? '');
        if ($ref === '') fail(400, 'missing ref');
        $txn = TransactionRepo::getByRef($ref);
        if (!$txn) fail(404, 'transaction not found: ' . $ref);
        $id = (int)$txn['id'];
        if (($txn['payment_status'] ?? '') === 'voided') {
            out(['ok' => true, 'already_voided' => true, 'ref' => $ref]);
        }
        $wasPaid = ($txn['payment_status'] ?? '') === 'paid';
        // Flip status FIRST (idempotency) then reverse inventory — mirrors admin/transaction-void.php.
        if (!TransactionRepo::voidTransaction($id)) fail(500, 'voidTransaction failed');

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
        out([
            'ok' => true,
            'voided' => $ref,
            'txn_id' => $id,
            'was_paid' => $wasPaid,
            'restocked_units' => $restockedUnits,
            'restored_tickets' => $restoredTickets,
            'detail' => $detail,
        ]);
    }

    default:
        fail(400, 'unknown action: ' . $action);
}
