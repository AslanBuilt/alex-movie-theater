<?php
declare(strict_types=1);
$pageTitle = 'Transactions';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';

$statusFilter = trim($_GET['status'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;

$rows    = TransactionRepo::getPaginated($page, $perPage, $statusFilter);
$total   = TransactionRepo::countAll($statusFilter);
$pages   = (int)ceil($total / $perPage);
$summary = TransactionRepo::getDashboardSummary();

$txnIds         = array_column($rows, 'id');
$checkinByTxnId = TransactionRepo::getCheckinSummaries($txnIds);
$itemsByTxnId   = TransactionRepo::getItemsForTransactions($txnIds);

$csrf = $auth->generateCsrfToken();

/** Render the "Checked In" badge for one transaction's check-in summary row. */
function renderCheckinBadge(?array $summary): string
{
    $totalTickets = (int)($summary['total_tickets'] ?? 0);
    if ($totalTickets === 0) {
        return '—';
    }
    $checkedIn = (int)($summary['checked_in'] ?? 0);
    if ($checkedIn === $totalTickets) {
        return '<span class="badge badge-success">All Checked In</span>';
    }
    if ($checkedIn > 0) {
        return '<span class="badge badge-warning">Partial ' . $checkedIn . '/' . $totalTickets . '</span>';
    }
    return '<span class="badge badge-muted">Not Yet</span>';
}

/**
 * Source-channel badge label/class for a transaction row. Same three
 * channels as FULFILL_CHANNEL_BADGES in api/fulfillment.php (the order
 * fulfillment board) — kept as a page-local twin rather than a shared
 * include, since that file also bootstraps its own DB/RateLimiter setup
 * this admin page doesn't need. Badge *classes* (and their colors — see
 * admin.css) are the exact ones the fulfillment board already uses, so a
 * channel reads as the same color on both screens; labels here are the
 * shorter Online/Walk-Up/Kiosk form asked for on this summary row.
 *
 * @return array{label:string, class:string}
 */
function channelBadge(string $channel): array
{
    return match ($channel) {
        'website'                 => ['label' => 'Online', 'class' => 'badge-online'],
        'staff_register', 'staff' => ['label' => 'Walk-Up', 'class' => 'badge-walkup'],
        'kiosk'                   => ['label' => 'Kiosk', 'class' => 'badge-kiosk'],
        default                   => ['label' => $channel !== '' ? ucfirst($channel) : 'Unknown', 'class' => 'badge-muted'],
    };
}

/**
 * Order number label for a row: the daily counter (assigned at checkout —
 * see TransactionRepo::assignDailyOrderNumber()) if present, else a
 * short-formed transaction ref so pending/failed rows (which never get a
 * daily number) still have a stable at-a-glance id.
 */
function orderNumberLabel(?int $dailyOrderNumber, string $ref): string
{
    if ($dailyOrderNumber !== null && $dailyOrderNumber > 0) {
        return '#' . $dailyOrderNumber;
    }
    return '#' . strtoupper(substr($ref, -6));
}
?>

<div class="admin-page-header">
  <h1>Transactions</h1>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-number"><?= (int)$summary['today_orders'] ?></div>
    <div class="stat-label">Orders Today</div>
  </div>
  <div class="stat-card">
    <div class="stat-number">$<?= number_format((float)$summary['today_revenue'], 2) ?></div>
    <div class="stat-label">Revenue Today</div>
  </div>
  <div class="stat-card">
    <div class="stat-number">$<?= number_format((float)$summary['week_revenue'], 2) ?></div>
    <div class="stat-label">Revenue This Week</div>
  </div>
  <div class="stat-card">
    <div class="stat-number"><?= (int)$summary['pending_count'] ?></div>
    <div class="stat-label">Pending Transactions</div>
  </div>
</div>

<!-- Filters -->
<form method="GET" style="margin-bottom:1.5rem; display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
  <select name="status" onchange="this.form.submit()" style="padding:0.4rem 0.75rem;">
    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All statuses</option>
    <option value="paid"    <?= $statusFilter === 'paid'    ? 'selected' : '' ?>>Paid</option>
    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
    <option value="failed"  <?= $statusFilter === 'failed'  ? 'selected' : '' ?>>Failed</option>
    <option value="voided"  <?= $statusFilter === 'voided'  ? 'selected' : '' ?>>Voided</option>
  </select>
  <span style="color:var(--text-secondary); font-size:0.85rem;"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?></span>
</form>

<?php if (empty($rows)): ?>
  <p style="color:var(--text-secondary);">No transactions found.</p>
<?php else: ?>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th></th>
          <th>Order</th>
          <th>Placed</th>
          <th>Channel</th>
          <th>Status</th>
          <th>Items</th>
          <th>Total</th>
          <th>Checked In</th>
          <th>Customer</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $txn):
            $txnId    = (int)$txn['id'];
            $items    = $itemsByTxnId[$txnId] ?? [];
            $channel  = channelBadge((string)$txn['source_channel']);
            $isPaid   = $txn['payment_status'] === 'paid';
            $isVoided = $txn['payment_status'] === 'voided';
            $detailId = 'txn-detail-' . $txnId;
            $dailyNum = $txn['daily_order_number'] !== null ? (int)$txn['daily_order_number'] : null;
        ?>
          <tr class="txn-row" tabindex="0" role="button" aria-expanded="false" aria-controls="<?= e($detailId) ?>"
              onclick="toggleTxnDetail(this, '<?= e($detailId) ?>')"
              onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleTxnDetail(this,'<?= e($detailId) ?>');}">
            <td><span class="txn-expand-icon" aria-hidden="true">&#9656;</span></td>
            <td>
              <span style="font-weight:700;"><?= e(orderNumberLabel($dailyNum, (string)$txn['transaction_ref'])) ?></span>
              <code class="txn-order-ref"><?= e((string)$txn['transaction_ref']) ?></code>
            </td>
            <td title="<?= e(date('M j, Y g:i A', strtotime((string)$txn['created_at']))) ?>">
              <?= e(timeAgo((string)$txn['created_at'])) ?>
            </td>
            <td><span class="badge <?= e($channel['class']) ?>"><?= e($channel['label']) ?></span></td>
            <td>
              <span class="status-badge status-<?= e((string)$txn['payment_status']) ?>">
                <?= e((string)$txn['payment_status']) ?>
              </span>
            </td>
            <td><?= e(summarizeLineItems($items)) ?></td>
            <td>$<?= number_format((float)$txn['total_amount'], 2) ?></td>
            <td><?= renderCheckinBadge($checkinByTxnId[$txnId] ?? null) ?></td>
            <td><?= $txn['customer_name'] ? e((string)$txn['customer_name']) : '<em style="color:var(--text-secondary);">Guest</em>' ?></td>
            <td onclick="event.stopPropagation();"><a href="transaction-view.php?id=<?= $txnId ?>" class="btn btn-sm btn-secondary">View</a></td>
          </tr>
          <tr class="txn-detail-row" id="<?= e($detailId) ?>" hidden>
            <td colspan="10">
              <div class="txn-detail-grid">
                <div>
                  <h3 style="margin-top:0;">Line Items</h3>
                  <?php if (!empty($items)): ?>
                    <table class="admin-table">
                      <thead>
                        <tr><th>Item</th><th>Option</th><th>Qty</th><th>Unit</th><th>Subtotal</th></tr>
                      </thead>
                      <tbody>
                        <?php foreach ($items as $li): ?>
                          <tr>
                            <td><?= e((string)$li['item_name']) ?></td>
                            <td><?= $li['selected_option'] ? e((string)$li['selected_option']) : '—' ?></td>
                            <td><?= (int)$li['quantity'] ?></td>
                            <td>$<?= number_format((float)$li['unit_price'], 2) ?></td>
                            <td>$<?= number_format((float)$li['subtotal'], 2) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  <?php else: ?>
                    <p style="color:var(--text-secondary);">No line items recorded.</p>
                  <?php endif; ?>
                </div>
                <div>
                  <h3 style="margin-top:0;">Details</h3>
                  <p style="margin:0 0 0.5rem;">
                    <strong>Email:</strong>
                    <?= $txn['customer_email'] ? e((string)$txn['customer_email']) : '<em style="color:var(--text-secondary);">Not provided</em>' ?>
                  </p>
                  <p style="margin:0 0 0.5rem;"><strong>Method:</strong> <?= e((string)$txn['payment_method']) ?></p>
                  <p style="margin:0 0 1rem;"><strong>Placed:</strong> <?= e(date('M j, Y g:i A', strtotime((string)$txn['created_at']))) ?></p>

                  <?php if ($isPaid): ?>
                    <form method="POST" action="transaction-void.php"
                          onsubmit="return confirm('Void <?= e((string)$txn['transaction_ref']) ?>? Concession stock and ticket counts from this order will be restored. This cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= $txnId ?>">
                      <button type="submit" class="btn btn-void btn-sm">Void Transaction</button>
                    </form>
                  <?php elseif ($isVoided): ?>
                    <p style="color:var(--text-secondary); font-size:0.85rem;">This transaction has been voided.</p>
                  <?php endif; ?>

                  <p style="margin-top:1rem;">
                    <a href="transaction-view.php?id=<?= $txnId ?>" class="btn btn-sm btn-outline">Full Detail &rarr;</a>
                  </p>
                </div>
              </div>
            </td>
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

<script>
// Expand/collapse a transaction row's detail panel in place — no reload,
// no extra request; the detail row's contents are already server-rendered
// from the same batch of line items fetched for the list above.
function toggleTxnDetail(rowEl, detailId) {
  var detail = document.getElementById(detailId);
  if (!detail) return;
  var expanded = rowEl.getAttribute('aria-expanded') === 'true';
  detail.hidden = expanded;
  rowEl.setAttribute('aria-expanded', expanded ? 'false' : 'true');
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
