<?php
declare(strict_types=1);
$pageTitle = 'Transactions';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';

$statusFilter = trim($_GET['status'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;

$rows  = TransactionRepo::getPaginated($page, $perPage, $statusFilter);
$total = TransactionRepo::countAll($statusFilter);
$pages = (int)ceil($total / $perPage);
?>

<div class="admin-page-header">
  <h1>Transactions</h1>
</div>

<!-- Filters -->
<form method="GET" style="margin-bottom:1.5rem; display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
  <select name="status" onchange="this.form.submit()" style="padding:0.4rem 0.75rem;">
    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All statuses</option>
    <option value="paid"    <?= $statusFilter === 'paid'    ? 'selected' : '' ?>>Paid</option>
    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
    <option value="failed"  <?= $statusFilter === 'failed'  ? 'selected' : '' ?>>Failed</option>
  </select>
  <span style="color:var(--color-text-muted); font-size:0.85rem;"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?></span>
</form>

<?php if (empty($rows)): ?>
  <p style="color:var(--color-text-muted);">No transactions found.</p>
<?php else: ?>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Date</th>
          <th>Type</th>
          <th>Total</th>
          <th>Status</th>
          <th>Customer</th>
          <th>Channel</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $txn): ?>
          <tr>
            <td><code><?= e((string)$txn['transaction_ref']) ?></code></td>
            <td><?= e(date('M j Y, g:i A', strtotime((string)$txn['created_at']))) ?></td>
            <td><?= e((string)$txn['type']) ?></td>
            <td>$<?= number_format((float)$txn['total_amount'], 2) ?></td>
            <td>
              <span class="status-badge status-<?= e((string)$txn['payment_status']) ?>">
                <?= e((string)$txn['payment_status']) ?>
              </span>
            </td>
            <td><?= $txn['customer_name'] ? e((string)$txn['customer_name']) : '<em style="color:var(--color-text-muted);">Guest</em>' ?></td>
            <td><?= e((string)$txn['source_channel']) ?></td>
            <td><a href="transaction-view.php?id=<?= (int)$txn['id'] ?>" class="btn btn-sm btn-secondary">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div style="display:flex; gap:0.5rem; margin-top:1.5rem; flex-wrap:wrap;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?page=<?= $p ?>&status=<?= urlencode($statusFilter) ?>"
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
