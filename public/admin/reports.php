<?php
declare(strict_types=1);
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';

$tab = isset($_GET['tab']) && $_GET['tab'] === 'inventory' ? 'inventory' : 'sales';

// Inventory tab tables are still rendered server-side (they're not date-range
// scoped, and the print stylesheet needs the Reorder-Now list present in the
// initial HTML — not something that only shows up after a client-side fetch).
$inventory   = InventoryRepo::getFullInventory();
$lowStock    = InventoryRepo::getLowStock();
$lowStockIds = array_column($lowStock, 'id');
?>
<link rel="stylesheet" href="../assets/css/admin-print.css" media="print">

<div class="admin-page-header">
  <h1>Reports</h1>
  <div class="admin-page-actions">
    <a class="btn btn-outline btn-sm" href="occupancy.php">Occupancy Report</a>
  </div>
</div>

<!-- Tabs -->
<div class="reports-tabs" style="display:flex; gap:0; margin-bottom:2rem; border-bottom:2px solid var(--border-light);">
  <a href="?tab=sales" style="padding:0.6rem 1.25rem; font-weight:700; text-decoration:none;
     border-bottom:3px solid <?= $tab === 'sales' ? 'var(--crimson)' : 'transparent' ?>;
     color:<?= $tab === 'sales' ? 'var(--crimson)' : 'var(--text-primary)' ?>; margin-bottom:-2px;">
    Sales Report
  </a>
  <a href="?tab=inventory" style="padding:0.6rem 1.25rem; font-weight:700; text-decoration:none;
     border-bottom:3px solid <?= $tab === 'inventory' ? 'var(--crimson)' : 'transparent' ?>;
     color:<?= $tab === 'inventory' ? 'var(--crimson)' : 'var(--text-primary)' ?>; margin-bottom:-2px;">
    Inventory / Reorder
    <?php if (!empty($lowStock)): ?>
      <span style="background:var(--crimson); color:#fff; border-radius:999px;
                   font-size:0.7rem; padding:0.1rem 0.45rem; margin-left:0.35rem;">
        <?= count($lowStock) ?>
      </span>
    <?php endif; ?>
  </a>
</div>

<div id="reportsError" class="alert alert-error no-print" role="alert" style="display:none;"></div>

<?php if ($tab === 'sales'): ?>

  <!-- ── SALES REPORT ── -->

  <!-- Date range control — scopes the category / top-movies / top-concessions
       charts only. The KPI strip, week-vs-last-week, this-month trend, and
       inventory chart are always their own fixed intrinsic period. -->
  <form id="rangeForm" class="no-print" style="display:flex; align-items:end; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.5rem;">
    <div>
      <label for="rangeSelect" style="display:block; font-size:0.78rem; color:var(--text-muted); margin-bottom:0.25rem;">
        Top-sellers &amp; category range
      </label>
      <select id="rangeSelect" name="range" class="form-input" style="min-height:44px;">
        <option value="today">Today</option>
        <option value="week" selected>This Week</option>
        <option value="month">This Month</option>
        <option value="custom">Custom…</option>
      </select>
    </div>
    <div id="customRangeFields" style="display:none; gap:0.75rem; align-items:end;">
      <div>
        <label for="rangeStart" style="display:block; font-size:0.78rem; color:var(--text-muted); margin-bottom:0.25rem;">Start</label>
        <input type="date" id="rangeStart" name="start" class="form-input" style="min-height:44px;">
      </div>
      <div>
        <label for="rangeEnd" style="display:block; font-size:0.78rem; color:var(--text-muted); margin-bottom:0.25rem;">End</label>
        <input type="date" id="rangeEnd" name="end" class="form-input" style="min-height:44px;">
      </div>
      <button type="submit" class="btn btn-secondary btn-sm" style="min-height:44px;">Apply</button>
    </div>
    <span id="rangeLoading" style="display:none; color:var(--text-muted); font-size:0.85rem;" role="status" aria-live="polite">Loading…</span>
    <button type="button" class="btn btn-secondary" style="min-height:44px; margin-left:auto;" onclick="(window.printAdminReport || window.print)()">Print Report</button>
  </form>

  <!-- KPI strip -->
  <div id="kpiStrip" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:1rem; margin-bottom:2.5rem;"></div>

  <!-- Charts -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:1.5rem; margin-bottom:2.5rem;">

    <section class="policy-box report-chart-section">
      <h3 style="margin:0 0 0.25rem; font-size:0.95rem;">Revenue by Day — This Week vs. Last Week</h3>
      <p id="chartWeekSummary" class="report-chart-summary" style="margin:0 0 0.75rem; font-size:0.85rem; color:var(--cream-dim);"></p>
      <canvas id="chartWeek" height="320" role="img" aria-label="Bar chart comparing daily revenue this week to last week"></canvas>
      <details class="report-data-table"><summary>View data table</summary><div id="chartWeekTable"></div></details>
    </section>

    <section class="policy-box report-chart-section">
      <h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Revenue by Day — This Month</h3>
      <canvas id="chartMonth" height="320" role="img" aria-label="Line chart of daily revenue this month, with an average reference line"></canvas>
      <details class="report-data-table"><summary>View data table</summary><div id="chartMonthTable"></div></details>
    </section>

    <section class="policy-box report-chart-section">
      <h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Revenue by Category</h3>
      <canvas id="chartCategory" height="320" role="img" aria-label="Doughnut chart of revenue split between tickets, concessions, and combos"></canvas>
      <details class="report-data-table"><summary>View data table</summary><div id="chartCategoryTable"></div></details>
    </section>

    <section class="policy-box report-chart-section">
      <h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Top 5 Movies (Tickets Sold)</h3>
      <div id="chartMoviesWrap" style="display:flex; gap:0.5rem; align-items:stretch;">
        <div id="chartMoviesPosters" class="report-poster-col"></div>
        <div style="flex:1; min-width:0;">
          <canvas id="chartMovies" role="img" aria-label="Horizontal bar chart of top 5 movies by tickets sold, split by adult and child"></canvas>
        </div>
      </div>
      <details class="report-data-table"><summary>View data table</summary><div id="chartMoviesTable"></div></details>
    </section>

    <section class="policy-box report-chart-section">
      <h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Top 5 Concessions (Units Sold)</h3>
      <canvas id="chartConcessions" height="320" role="img" aria-label="Horizontal bar chart of top 5 concessions by units sold, with per-unit margin"></canvas>
      <details class="report-data-table"><summary>View data table</summary><div id="chartConcessionsTable"></div></details>
    </section>

  </div>

<?php else: ?>

  <!-- ── INVENTORY REPORT ── -->

  <?php if (!empty($lowStock)): ?>
    <div class="report-reorder-now" style="background:rgba(139,29,51,0.12); border:2px solid var(--crimson); border-radius:6px;
                padding:1rem 1.25rem; margin-bottom:2rem;">
      <h3 style="color:var(--crimson); margin-bottom:0.75rem;">
        &#9888; Reorder Now (<?= count($lowStock) ?> item<?= count($lowStock) !== 1 ? 's' : '' ?>)
      </h3>
      <table class="admin-table" style="background:transparent;">
        <thead><tr><th>Item</th><th>Category</th><th>Stock</th><th>Reorder Point</th></tr></thead>
        <tbody>
          <?php foreach ($lowStock as $ls): ?>
            <tr>
              <td><a href="concession-stock.php?id=<?= (int)$ls['id'] ?>"><?= e((string)$ls['name']) ?></a></td>
              <td><?= e((string)$ls['category']) ?></td>
              <td style="color:var(--crimson); font-weight:700;"><?= (int)$ls['stock_quantity'] ?></td>
              <td><?= (int)$ls['reorder_point'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <section class="policy-box report-chart-section" style="margin-bottom:2rem;">
    <h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Stock Level vs. Reorder Point — Active Products</h3>
    <div style="overflow-x:auto;">
      <div id="chartInventoryWrap" style="min-width:480px;">
        <canvas id="chartInventory" height="400" role="img" aria-label="Bar chart of current stock per product, color-coded by reorder threshold, with a reorder-point reference line"></canvas>
      </div>
    </div>
    <details class="report-data-table"><summary>View data table</summary><div id="chartInventoryTable"></div></details>
    <?php if (!empty($inventory) && !array_filter($inventory, static fn($i) => $i['reorder_point'] !== null)): ?>
      <p style="color:var(--cream-dim); font-size:0.8rem; margin-top:0.5rem;">
        &#9888; Bars show grey because no reorder points are set yet. Go to
        <a href="concessions.php" style="color:var(--crimson-light);">Concessions</a>
        &rarr; edit each item &rarr; set a Reorder Point to enable red/yellow/green stock alerts.
      </p>
    <?php endif; ?>
  </section>

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
            <th class="no-print"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inventory as $inv):
            $isLow      = in_array((int)$inv['id'], $lowStockIds, true);
            $incomplete = ($inv['cost'] === null || $inv['reorder_point'] === null);
          ?>
            <tr style="<?= $isLow ? 'background:rgba(180,0,0,0.1);' : '' ?>">
              <td>
                <?= e((string)$inv['name']) ?>
                <?php if ($incomplete): ?>
                  <span title="Cost or reorder point not set" style="color:#f0c674; font-size:0.75rem;">&#9888; incomplete</span>
                <?php endif; ?>
              </td>
              <td><?= e((string)$inv['category']) ?></td>
              <td style="font-weight:700; color:<?= $isLow ? 'var(--crimson)' : 'inherit' ?>;">
                <?= (int)$inv['stock_quantity'] ?>
              </td>
              <td><?= $inv['reorder_point'] !== null ? (int)$inv['reorder_point'] : '<em style="color:var(--text-muted);">—</em>' ?></td>
              <td><?= $inv['cost'] !== null ? '$' . number_format((float)$inv['cost'], 2) : '<em style="color:var(--text-muted);">—</em>' ?></td>
              <td>$<?= number_format((float)$inv['price'], 2) ?></td>
              <td><?= $inv['is_available'] ? 'Yes' : 'No' ?></td>
              <td class="no-print">
                <a href="concession-stock.php?id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-secondary">Stock</a>
                <a href="concession-edit.php?id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p style="color:var(--text-muted);">No concession items found.</p>
  <?php endif; ?>

<?php endif; ?>

<script>window.ADMIN_REPORTS_TAB = <?= json_encode($tab) ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js" integrity="sha512-Y51n9mtKTVBh3Jbx5pZSJNDDMyY+yGe77DGtBPzRlgsf/YLCh13kSZ3JmfHGzYFCmOndraf0sQgfM654b7dJ3w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/2.2.0/chartjs-plugin-datalabels.min.js" integrity="sha512-JPcRR8yFa8mmCsfrw4TNte1ZvF1e3+1SdGMslZvmrzDYxS69J7J49vkFL8u6u8PlPJK+H3voElBtUCzaXj+6ig==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="../assets/js/admin-charts.js?v=<?= @filemtime(__DIR__ . '/../assets/js/admin-charts.js') ?>" defer></script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
