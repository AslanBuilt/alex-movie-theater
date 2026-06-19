<?php
declare(strict_types=1);
$pageTitle = 'Adjust Stock';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';

$repo = new ConcessionRepo($db);
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = $id > 0 ? $repo->getById($id) : null;

if (!$item) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Concession item not found.'];
    header('Location: concessions.php');
    exit;
}

$errors  = [];
$csrf    = $auth->generateCsrfToken();
$current = (int)$item['stock_quantity'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCsrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        $changeType = $_POST['change_type'] ?? 'restock';
        $qty        = (int)($_POST['qty'] ?? 0);
        $note       = trim($_POST['note'] ?? '');

        if ($qty <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        } elseif (!in_array($changeType, ['restock', 'adjustment'], true)) {
            $errors[] = 'Invalid adjustment type.';
        } else {
            $qtyChange = $changeType === 'adjustment' ? -$qty : $qty;
            $newQty    = max(0, $current + $qtyChange);

            $ok = InventoryRepo::logChange($id, $changeType, $qtyChange, $newQty, 'admin', $note ?: null);
            if ($ok) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Stock updated from ' . $current . ' → ' . $newQty . '.'];
                header('Location: concession-stock.php?id=' . $id);
                exit;
            }
            $errors[] = 'Failed to update stock.';
        }
    }
}

$log = InventoryRepo::getLogForProduct($id, 10);
?>

<div class="admin-page-header">
  <h1>Stock: <?= e((string)$item['name']) ?></h1>
  <a href="concession-edit.php?id=<?= $id ?>" class="btn btn-sm btn-secondary">&#8592; Back to Item</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="policy-box" style="margin-bottom:2rem; text-align:center;">
  <p style="font-size:0.85rem; color:var(--color-text-muted); margin:0;">Current Stock</p>
  <p style="font-size:2.5rem; font-weight:700; margin:0.25rem 0;
     color:<?= ($item['reorder_point'] !== null && $current <= (int)$item['reorder_point']) ? 'var(--color-crimson)' : 'var(--color-text)' ?>;">
    <?= $current ?>
  </p>
  <?php if ($item['reorder_point'] !== null): ?>
    <p style="font-size:0.8rem; color:var(--color-text-muted); margin:0;">Reorder point: <?= (int)$item['reorder_point'] ?></p>
  <?php endif; ?>
</div>

<form method="POST" class="admin-form" style="margin-bottom:2rem;">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <h3 style="margin-bottom:1rem;">Record Adjustment</h3>

  <div class="form-row">
    <div class="form-group">
      <label for="change_type">Type</label>
      <select name="change_type" id="change_type">
        <option value="restock">Restock (add quantity)</option>
        <option value="adjustment">Adjustment (remove quantity)</option>
      </select>
    </div>
    <div class="form-group">
      <label for="qty">Quantity <span class="required">*</span></label>
      <input type="number" id="qty" name="qty" min="1" required placeholder="e.g. 10">
    </div>
  </div>

  <div class="form-group">
    <label for="note">Reason / Note</label>
    <input type="text" id="note" name="note" maxlength="200" placeholder="e.g. Received shipment, Damaged goods">
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Save Adjustment</button>
  </div>
</form>

<?php if (!empty($log)): ?>
  <h3 style="margin-bottom:0.75rem;">Recent Changes</h3>
  <table class="admin-table">
    <thead><tr><th>Date</th><th>Type</th><th>Change</th><th>New Qty</th><th>Source</th><th>Note</th></tr></thead>
    <tbody>
      <?php foreach ($log as $entry): ?>
        <tr>
          <td><?= e(date('M j, g:i A', strtotime((string)$entry['created_at']))) ?></td>
          <td><?= e((string)$entry['change_type']) ?></td>
          <td style="color:<?= (int)$entry['qty_change'] >= 0 ? 'green' : 'var(--color-crimson)' ?>;">
            <?= (int)$entry['qty_change'] >= 0 ? '+' : '' ?><?= (int)$entry['qty_change'] ?>
          </td>
          <td><?= (int)$entry['new_quantity'] ?></td>
          <td><?= e((string)$entry['source']) ?></td>
          <td><?= e((string)($entry['note'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
