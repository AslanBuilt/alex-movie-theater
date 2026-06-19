<?php
declare(strict_types=1);
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';

$tab = isset($_GET['tab']) && $_GET['tab'] === 'inventory' ? 'inventory' : 'sales';

$sales        = TransactionRepo::getSalesReport();
$topMovies    = TransactionRepo::getTopItems('ticket', 5);
$topConcessions = TransactionRepo::getTopItems('concession', 5);
$byType       = TransactionRepo::getRevenueByType();
$revenueMap   = [];
foreach ($byType as $r) {
    $revenueMap[$r['type']] = ['revenue' => (float)$r['revenue'], 'cnt' => (int)$r['cnt']];
}

$inventory   = InventoryRepo::getFullInventory();
$lowStock    = InventoryRepo::getLowStock();
$lowStockIds = array_column($lowStock, 'id');
?>

<div class="admin-page-header">
  <h1>Reports</h1>
</div>

<!-- Tabs -->
<div style="display:flex; gap:0; margin-bottom:2rem; border-bottom:2px solid #e5e5e5;">
  <a href="?tab=sales" style="padding:0.6rem 1.25rem; font-weight:700; text-decoration:none;
     border-bottom:3px solid <?= $tab === 'sales' ? 'var(--color-crimson)' : 'transparent' ?>;
     color:<?= $tab === 'sales' ? 'var(--color-crimson)' : 'inherit' ?>; margin-bottom:-2px;">
    Sales Report
  </a>
  <a href="?tab=inventory" style="padding:0.6rem 1.25rem; font-weight:700; text-decoration:none;
     border-bottom:3px solid <?= $tab === 'inventory' ? 'var(--color-crimson)' : 'transparent' ?>;
     color:<?= $tab === 'inventory' ? 'var(--color-crimson)' : 'inherit' ?>; margin-bottom:-2px;">
    Inventory / Reorder
    <?php if (!empty($lowStock)): ?>
      <span style="background:var(--color-crimson); color:#fff; border-radius:999px;
                   font-size:0.7rem; padding:0.1rem 0.45rem; margin-left:0.35rem;">
        <?= count($lowStock) ?>
      </span>
    <?php endif; ?>
  </a>
</div>

<?php if ($tab === 'sales'): ?>

  <!-- ── SALES REPORT ── -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:1rem; margin-bottom:2.5rem;">
    <?php
      $periods = [
        'tonight'   => 'Tonight',
        'today'     => 'Today',
        'yesterday' => 'Yesterday',
        'week'      => 'This Week',
        'month'     => 'This Month',
      ];
      foreach ($periods as $key => $label):
        $d = $sales[$key] ?? ['total' => 0, 'count' => 0];
    ?>
      <div class="policy-box" style="text-align:center;">
        <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0 0 0.25rem;"><?= $label ?></p>
        <p style="font-size:1.6rem; font-weight:700; color:var(--color-crimson); margin:0;">
          $<?= number_format($d['total'], 2) ?>
        </p>
        <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0.25rem 0 0;">
          <?= $d['count'] ?> transaction<?= $d['count'] !== 1 ? 's' : '' ?>
        </p>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Revenue by category -->
  <h3 style="margin-bottom:0.75rem;">Revenue by Category</h3>
  <?php if (!empty($byType)): ?>
    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:2rem;">
      <?php foreach ($byType as $r): ?>
        <div class="policy-box" style="min-width:140px; text-align:center;">
          <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0; text-transform:capitalize;"><?= e((string)$r['type']) ?></p>
          <p style="font-size:1.2rem; font-weight:700; margin:0.25rem 0;">$<?= number_format((float)$r['revenue'], 2) ?></p>
          <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0;"><?= (int)$r['cnt'] ?> txn<?= (int)$r['cnt'] !== 1 ? 's' : '' ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p style="color:var(--color-text-muted); margin-bottom:2rem;">No sales data yet.</p>
  <?php endif; ?>

  <!-- Top items -->
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; flex-wrap:wrap;">
    <div>
      <h3 style="margin-bottom:0.75rem;">Top Movies (by tickets)</h3>
      <?php if (!empty($topMovies)): ?>
        <table class="admin-table">
          <thead><tr><th>Movie</th><th>Tickets</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($topMovies as $tm): ?>
              <tr>
                <td><?= e((string)$tm['item_name']) ?></td>
                <td><?= (int)$tm['total_qty'] ?></td>
                <td>$<?= number_format((float)$tm['total_revenue'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:var(--color-text-muted);">No ticket sales yet.</p>
      <?php endif; ?>
    </div>
    <div>
      <h3 style="margin-bottom:0.75rem;">Top Concessions (by quantity)</h3>
      <?php if (!empty($topConcessions)): ?>
        <table class="admin-table">
          <thead><tr><th>Item</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($topConcessions as $tc): ?>
              <tr>
                <td><?= e((string)$tc['item_name']) ?></td>
                <td><?= (int)$tc['total_qty'] ?></td>
                <td>$<?= number_format((float)$tc['total_revenue'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:var(--color-text-muted);">No concession sales yet.</p>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>

  <!-- ── INVENTORY REPORT ── -->

  <?php if (!empty($lowStock)): ?>
    <div style="background:#fff3f3; border:2px solid var(--color-crimson); border-radius:6px;
                padding:1rem 1.25rem; margin-bottom:2rem;">
      <h3 style="color:var(--color-crimson); margin-bottom:0.75rem;">
        &#9888; Reorder Now (<?= count($lowStock) ?> item<?= count($lowStock) !== 1 ? 's' : '' ?>)
      </h3>
      <table class="admin-table" style="background:transparent;">
        <thead><tr><th>Item</th><th>Category</th><th>Stock</th><th>Reorder Point</th></tr></thead>
        <tbody>
          <?php foreach ($lowStock as $ls): ?>
            <tr>
              <td><a href="concession-stock.php?id=<?= (int)$ls['id'] ?>"><?= e((string)$ls['name']) ?></a></td>
              <td><?= e((string)$ls['category']) ?></td>
              <td style="color:var(--color-crimson); font-weight:700;"><?= (int)$ls['stock_quantity'] ?></td>
              <td><?= (int)$ls['reorder_point'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h3 style="margin-bottom:0.75rem;">Full Inventory</h3>
  <?php if (!empty($inventory)): ?>
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Category</th>
            <th>Stock</th>
            <th>Reorder Point</th>
            <th>Cost</th>
            <th>Sell Price</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inventory as $inv):
            $isLow      = in_array((int)$inv['id'], $lowStockIds, true);
            $incomplete = ($inv['cost'] === null || $inv['reorder_point'] === null);
          ?>
            <tr style="<?= $isLow ? 'background:rgba(180,0,0,0.06);' : '' ?>">
              <td>
                <?= e((string)$inv['name']) ?>
                <?php if ($incomplete): ?>
                  <span title="Cost or reorder point not set" style="color:orange; font-size:0.75rem;">&#9888; incomplete</span>
                <?php endif; ?>
              </td>
              <td><?= e((string)$inv['category']) ?></td>
              <td style="font-weight:700; color:<?= $isLow ? 'var(--color-crimson)' : 'inherit' ?>;">
                <?= (int)$inv['stock_quantity'] ?>
              </td>
              <td><?= $inv['reorder_point'] !== null ? (int)$inv['reorder_point'] : '<em style="color:#999;">—</em>' ?></td>
              <td><?= $inv['cost'] !== null ? '$' . number_format((float)$inv['cost'], 2) : '<em style="color:#999;">—</em>' ?></td>
              <td>$<?= number_format((float)$inv['price'], 2) ?></td>
              <td><?= $inv['is_available'] ? 'Yes' : 'No' ?></td>
              <td>
                <a href="concession-stock.php?id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-secondary">Stock</a>
                <a href="concession-edit.php?id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p style="color:var(--color-text-muted);">No concession items found.</p>
  <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
