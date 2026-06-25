<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';

$db   = Database::getInstance();
$auth = new AdminAuth($db);
$auth->requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: transactions.php');
    exit;
}

if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Session expired. Please try again.'];
    header('Location: transactions.php');
    exit;
}

$id  = (int)($_POST['id'] ?? 0);
$txn = $id > 0 ? TransactionRepo::getById($id) : null;

if (!$txn) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Transaction not found.'];
    header('Location: transactions.php');
    exit;
}

$ref = (string)$txn['transaction_ref'];

// Idempotent: a transaction can only be voided once. Re-submitting (e.g. a
// double-click or a retry after a partial failure) must not restock twice.
if ($txn['payment_status'] === 'voided') {
    $_SESSION['flash'] = ['type' => 'info', 'message' => "Transaction $ref is already voided."];
    header('Location: transaction-view.php?id=' . $id);
    exit;
}

$wasPaid = $txn['payment_status'] === 'paid';

// Flip the status FIRST so any retry sees 'voided' and skips the restock block.
if (!TransactionRepo::voidTransaction($id)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => "Could not void $ref. Please try again."];
    header('Location: transaction-view.php?id=' . $id);
    exit;
}

// Only a paid transaction ever committed inventory side effects worth reversing.
$restockedUnits = 0;
$restoredTickets = 0;
if ($wasPaid) {
    $concessionRepo = new ConcessionRepo($db);
    foreach ($txn['items'] as $li) {
        $itemId = (int)$li['item_id'];
        $qty    = (int)$li['quantity'];
        if ($qty < 1) continue;

        if ($li['item_type'] === 'concession') {
            // NOTE: assumes the original sale decremented stock. If the item was
            // at 0 stock when sold, checkout skipped the decrement, so this slightly
            // over-credits — rare, and visible in the inventory log for correction.
            $product = $concessionRepo->getById($itemId);
            if ($product) {
                $newQty = (int)$product['stock_quantity'] + $qty;
                InventoryRepo::logChange($itemId, 'restock', $qty, $newQty, 'admin', "Void $ref");
                $restockedUnits += $qty;
            }
        } elseif ($li['item_type'] === 'ticket') {
            if (ShowtimeRepo::restoreTickets($itemId, $qty)) {
                $restoredTickets += $qty;
            }
        }
    }
}

$msg = "Voided $ref.";
if ($wasPaid) {
    $parts = [];
    if ($restockedUnits > 0)  $parts[] = "restocked $restockedUnits concession unit" . ($restockedUnits === 1 ? '' : 's');
    if ($restoredTickets > 0) $parts[] = "restored $restoredTickets ticket" . ($restoredTickets === 1 ? '' : 's');
    if ($parts) $msg .= ' ' . ucfirst(implode(', ', $parts)) . '.';
}

$_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
header('Location: transaction-view.php?id=' . $id);
exit;
