<?php
declare(strict_types=1);
$pageTitle = 'Transaction Detail';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';

$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$txn = $id > 0 ? TransactionRepo::getById($id) : null;

if (!$txn) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Transaction not found.'];
    header('Location: transactions.php');
    exit;
}

$csrf      = $auth->generateCsrfToken();
$isVoided  = $txn['payment_status'] === 'voided';
$hasPaid   = $txn['payment_status'] === 'paid';
?>

<div class="admin-page-header">
  <h1>Transaction: <code><?= e((string)$txn['transaction_ref']) ?></code></h1>
  <a href="transactions.php" class="btn btn-sm btn-secondary">&#8592; Back</a>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:2rem;">
  <div class="policy-box" style="text-align:center;">
    <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0;">Status</p>
    <p style="font-size:1.4rem; font-weight:700; margin:0.25rem 0;
       color:<?= $txn['payment_status'] === 'paid' ? 'green' : 'var(--color-crimson)' ?>;">
      <?= strtoupper(e((string)$txn['payment_status'])) ?>
    </p>
  </div>
  <div class="policy-box" style="text-align:center;">
    <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0;">Total</p>
    <p style="font-size:1.4rem; font-weight:700; margin:0.25rem 0;">$<?= number_format((float)$txn['total_amount'], 2) ?></p>
  </div>
  <div class="policy-box" style="text-align:center;">
    <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0;">Type</p>
    <p style="font-size:1.1rem; font-weight:700; margin:0.25rem 0; text-transform:capitalize;"><?= e((string)$txn['type']) ?></p>
  </div>
  <div class="policy-box" style="text-align:center;">
    <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0;">Channel</p>
    <p style="font-size:1.1rem; font-weight:700; margin:0.25rem 0; text-transform:capitalize;"><?= e((string)$txn['source_channel']) ?></p>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
  <div>
    <h3>Customer</h3>
    <p><?= $txn['customer_name']  ? e((string)$txn['customer_name'])  : '<em>Not provided</em>' ?></p>
    <p><?= $txn['customer_email'] ? e((string)$txn['customer_email']) : '<em>Not provided</em>' ?></p>
  </div>
  <div>
    <h3>Payment</h3>
    <p><?= e((string)$txn['payment_method']) ?></p>
    <p style="font-size:0.85rem; color:var(--color-text-muted);"><?= e(date('M j, Y g:i A', strtotime((string)$txn['created_at']))) ?></p>
  </div>
</div>

<h3 style="margin-bottom:0.75rem;">Line Items</h3>
<?php if (!empty($txn['items'])): ?>
  <table class="admin-table">
    <thead>
      <tr><th>Item</th><th>Type</th><th>Option</th><th>Qty</th><th>Unit</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
      <?php foreach ($txn['items'] as $li): ?>
        <tr>
          <td><?= e((string)$li['item_name']) ?></td>
          <td><?= e((string)$li['item_type']) ?></td>
          <td><?= $li['selected_option'] ? e((string)$li['selected_option']) : '—' ?></td>
          <td><?= (int)$li['quantity'] ?></td>
          <td>$<?= number_format((float)$li['unit_price'], 2) ?></td>
          <td>$<?= number_format((float)$li['subtotal'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" style="text-align:right; font-weight:700;">Total</td>
        <td style="font-weight:700;">$<?= number_format((float)$txn['total_amount'], 2) ?></td>
      </tr>
    </tfoot>
  </table>
<?php else: ?>
  <p style="color:var(--color-text-muted);">No line items recorded.</p>
<?php endif; ?>

<div style="margin-top:2.5rem; padding-top:1.5rem; border-top:1px solid rgba(0,0,0,0.1);">
  <?php if ($isVoided): ?>
    <p style="color:var(--color-text-muted);">This transaction has been voided.</p>
  <?php else: ?>
    <form method="POST" action="transaction-void.php"
          onsubmit="return confirm('Void <?= e((string)$txn['transaction_ref']) ?>?<?= $hasPaid ? ' Concession stock and ticket counts from this order will be restored.' : '' ?> This cannot be undone.');">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$txn['id'] ?>">
      <button type="submit" class="btn btn-void btn-sm">Void Transaction</button>
      <span style="margin-left:0.75rem; font-size:0.82rem; color:var(--color-text-muted);">
        <?= $hasPaid
            ? 'Marks the transaction voided (drops it from reports) and restores inventory.'
            : 'Marks the transaction voided so it no longer counts in reports.' ?>
      </span>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
