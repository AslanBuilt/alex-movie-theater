<?php
declare(strict_types=1);
$pageTitle = 'Concession Orders';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

$repo = new ConcessionRepo($db);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    if ($auth->validateCsrf($_POST['_csrf'] ?? '')) {
        $repo->updateOrderStatus((int)$_POST['order_id'], $_POST['status']);
    }
    header('Location: orders.php');
    exit;
}

$orders = $repo->getOrders(100);
$csrf   = $auth->generateCsrfToken();

$statusLabels = [
    'pending'   => 'Pending',
    'ready'     => 'Ready',
    'picked_up' => 'Picked Up',
    'cancelled' => 'Cancelled',
];
$statusClasses = [
    'pending'   => 'badge-warning',
    'ready'     => 'badge-success',
    'picked_up' => 'badge-muted',
    'cancelled' => 'badge-error',
];
?>

<div class="admin-page-header">
  <h1 class="admin-page-title">Legacy Concession Orders</h1>
</div>

<p class="text-muted" style="margin-bottom:1rem;">
  Archive of the retired pay-at-theatre pre-order form. New concession sales now
  go through the cart &rarr; checkout (online) and the in-venue register, and
  appear under <a href="transactions.php">Transactions</a>. Existing rows below
  are read-only history; you can still update their status to close them out.
</p>

<?php if (empty($orders)): ?>
  <p class="text-muted">No legacy orders on record.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Show Info</th>
          <th>Items</th>
          <th>Total</th>
          <th>Status</th>
          <th>Placed</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order):
          $lineItems = json_decode($order['items_json'], true) ?: [];
        ?>
          <tr>
            <td><strong><?= e($order['order_number']) ?></strong></td>
            <td>
              <?= e($order['customer_name']) ?><br>
              <small class="text-muted"><?= e($order['customer_email']) ?></small>
              <?php if ($order['customer_phone']): ?>
                <br><small><?= e($order['customer_phone']) ?></small>
              <?php endif; ?>
            </td>
            <td><?= $order['show_info'] ? e($order['show_info']) : '<span class="text-muted">—</span>' ?></td>
            <td>
              <?php foreach ($lineItems as $li): ?>
                <div><?= (int)$li['qty'] ?> &times; <?= e($li['name']) ?></div>
              <?php endforeach; ?>
            </td>
            <td>$<?= number_format((float)$order['total_amount'], 2) ?></td>
            <td>
              <span class="badge <?= $statusClasses[$order['status']] ?? 'badge-muted' ?>">
                <?= $statusLabels[$order['status']] ?? e($order['status']) ?>
              </span>
            </td>
            <td><small><?= date('M j g:ia', strtotime($order['created_at'])) ?></small></td>
            <td>
              <form method="POST" action="orders.php" style="display:flex; gap:0.4rem; align-items:center;">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <select name="status" class="admin-select-sm">
                  <?php foreach ($statusLabels as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $order['status'] === $val ? 'selected' : '' ?>>
                      <?= e($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-xs btn-primary">Set</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
