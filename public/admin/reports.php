<?php
declare(strict_types=1);
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';

$tab = isset($_GET['tab']) && $_GET['tab'] === 'inventory' ? 'inventory' : 'sales';

$sales          = TransactionRepo::getSalesReport();
$topMovies      = TransactionRepo::getTopItems('ticket', 5);
$topConcessions = TransactionRepo::getTopItems('concession', 5);
$byType         = TransactionRepo::getRevenueByType();
$revenueMap     = [];
foreach ($byType as $r) {
    $revenueMap[$r['type']] = ['revenue' => (float)$r['revenue'], 'cnt' => (int)$r['cnt']];
}

$inventory   = InventoryRepo::getFullInventory();
$lowStock    = InventoryRepo::getLowStock();
$lowStockIds = array_column($lowStock, 'id');

// ── Chart data (sales tab) ───────────────────────────────────────────────────
// Revenue by day this week (Mon-start, matching getSalesReport()'s week boundary).
$todayDow = (int)date('N'); // 1=Mon..7=Sun
$monday   = (new DateTime())->modify('-' . ($todayDow - 1) . ' days');
$weekRevenueByDate = [];
foreach (TransactionRepo::getRevenueByDayThisWeek() as $r) {
    $weekRevenueByDate[(string)$r['day']] = (float)$r['revenue'];
}
$weekChartLabels = [];
$weekChartData   = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $monday)->modify("+$i days");
    $weekChartLabels[] = $d->format('D');
    $weekChartData[]   = round($weekRevenueByDate[$d->format('Y-m-d')] ?? 0, 2);
}

// Revenue by day this month, 1st through today.
$monthStart = new DateTime('first day of this month');
$daysSoFar  = (int)date('j');
$monthRevenueByDate = [];
foreach (TransactionRepo::getRevenueByDayThisMonth() as $r) {
    $monthRevenueByDate[(string)$r['day']] = (float)$r['revenue'];
}
$monthChartLabels = [];
$monthChartData   = [];
for ($i = 0; $i < $daysSoFar; $i++) {
    $d = (clone $monthStart)->modify("+$i days");
    $monthChartLabels[] = $d->format('j');
    $monthChartData[]   = round($monthRevenueByDate[$d->format('Y-m-d')] ?? 0, 2);
}

// Revenue by category (Tickets / Concessions / Combos) — doughnut.
$catChartLabels = [];
$catChartData   = [];
foreach (['ticket' => 'Tickets', 'concession' => 'Concessions', 'combo' => 'Combos'] as $key => $label) {
    if (!empty($revenueMap[$key])) {
        $catChartLabels[] = $label;
        $catChartData[]   = round($revenueMap[$key]['revenue'], 2);
    }
}

// Top 5 movies / concessions — horizontal bars.
$movieChartLabels = array_map(static fn ($r) => (string)$r['item_name'], $topMovies);
$movieChartData   = array_map(static fn ($r) => (int)$r['total_qty'], $topMovies);
$concChartLabels  = array_map(static fn ($r) => (string)$r['item_name'], $topConcessions);
$concChartData    = array_map(static fn ($r) => (int)$r['total_qty'], $topConcessions);

// ── Chart data (inventory tab) ───────────────────────────────────────────────
$activeInventory = array_values(array_filter($inventory, static fn ($i) => (int)$i['is_available'] === 1));
$invChartLabels    = array_map(static fn ($i) => (string)$i['name'], $activeInventory);
$invChartStock      = array_map(static fn ($i) => (int)$i['stock_quantity'], $activeInventory);
$invChartReorder    = array_map(static fn ($i) => $i['reorder_point'] !== null ? (int)$i['reorder_point'] : null, $activeInventory);
$invChartBarColors  = array_map(static function ($i) {
    $stock   = (int)$i['stock_quantity'];
    $reorder = $i['reorder_point'] !== null ? (int)$i['reorder_point'] : null;
    if ($stock <= 0) return '#E07A8A';
    if ($reorder !== null && $stock <= $reorder) return '#f0c674';
    return '#9bd9b4';
}, $activeInventory);

// Brand-coherent multi-category palette (same family used on the showtime calendar).
const REPORT_PALETTE = ['#8B1D33', '#3a5a7a', '#6a4a8a', '#4a7a5a', '#a8632a', '#7a4a4a', '#4a7a7a', '#8a7a3a'];
?>

<div class="admin-page-header">
  <h1>Reports</h1>
</div>

<!-- Tabs -->
<div style="display:flex; gap:0; margin-bottom:2rem; border-bottom:2px solid var(--border-light);">
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
        <p style="font-size:0.78rem; color:var(--text-muted); margin:0 0 0.25rem;"><?= $label ?></p>
        <p style="font-size:1.6rem; font-weight:700; color:var(--crimson); margin:0;">
          $<?= number_format($d['total'], 2) ?>
        </p>
        <p style="font-size:0.78rem; color:var(--text-muted); margin:0.25rem 0 0;">
          <?= $d['count'] ?> transaction<?= $d['count'] !== 1 ? 's' : '' ?>
        </p>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:1.5rem; margin-bottom:2.5rem;">
    <div class="policy-box"><h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Revenue by Day — This Week</h3><canvas id="chartWeek" height="220"></canvas></div>
    <div class="policy-box"><h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Revenue by Day — This Month</h3><canvas id="chartMonth" height="220"></canvas></div>
    <div class="policy-box"><h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Revenue by Category</h3><canvas id="chartCategory" height="220"></canvas></div>
    <div class="policy-box"><h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Top 5 Movies (Tickets Sold)</h3><canvas id="chartMovies" height="220"></canvas></div>
    <div class="policy-box"><h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Top 5 Concessions (Units Sold)</h3><canvas id="chartConcessions" height="220"></canvas></div>
  </div>

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
        <p style="color:var(--text-muted);">No ticket sales yet.</p>
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
        <p style="color:var(--text-muted);">No concession sales yet.</p>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>

  <!-- ── INVENTORY REPORT ── -->

  <?php if (!empty($lowStock)): ?>
    <div style="background:rgba(139,29,51,0.12); border:2px solid var(--crimson); border-radius:6px;
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

  <div class="policy-box" style="margin-bottom:2rem;">
    <h3 style="margin:0 0 0.75rem; font-size:0.95rem;">Stock Level vs. Reorder Point — Active Products</h3>
    <div style="overflow-x:auto;">
      <div style="min-width:<?= max(480, count($activeInventory) * 70) ?>px;">
        <canvas id="chartInventory" height="260"></canvas>
      </div>
    </div>
  </div>

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
    <p style="color:var(--text-muted);">No concession items found.</p>
  <?php endif; ?>

<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js" integrity="sha512-n/G+dROKbKL3GVngGWmWfwK0yPctjZQM752diVYnXZtD/48agpUKLIn0xDQL9ydZ91x6BiOmTIFwWjjFi2kEFg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;
  var PALETTE = <?= json_encode(REPORT_PALETTE) ?>;
  Chart.defaults.color = '#9A8070';
  Chart.defaults.borderColor = '#2A1A12';
  Chart.defaults.font.family = "'Lato', 'Helvetica Neue', Arial, sans-serif";

  function money(v) { return '$' + Number(v).toFixed(2); }

  <?php if ($tab === 'sales'): ?>
  new Chart(document.getElementById('chartWeek'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($weekChartLabels) ?>,
      datasets: [{ label: 'Revenue', data: <?= json_encode($weekChartData) ?>, backgroundColor: '#8B1D33', borderRadius: 4 }]
    },
    options: {
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return money(c.parsed.y); } } } },
      scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return money(v); } } } }
    }
  });

  new Chart(document.getElementById('chartMonth'), {
    type: 'line',
    data: {
      labels: <?= json_encode($monthChartLabels) ?>,
      datasets: [{
        label: 'Revenue', data: <?= json_encode($monthChartData) ?>,
        borderColor: '#8B1D33', backgroundColor: 'rgba(139,29,51,0.18)', fill: true, tension: 0.25, pointRadius: 3
      }]
    },
    options: {
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return money(c.parsed.y); } } } },
      scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return money(v); } } } }
    }
  });

  <?php $catTotal = array_sum($catChartData) ?: 1; ?>
  new Chart(document.getElementById('chartCategory'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($catChartLabels) ?>,
      datasets: [{ data: <?= json_encode($catChartData) ?>, backgroundColor: PALETTE, borderColor: '#1C1410', borderWidth: 2 }]
    },
    options: {
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: function (c) {
          var pct = (c.parsed / <?= $catTotal ?> * 100).toFixed(1);
          return c.label + ': ' + money(c.parsed) + ' (' + pct + '%)';
        } } }
      }
    }
  });

  new Chart(document.getElementById('chartMovies'), {
    type: 'bar',
    data: { labels: <?= json_encode($movieChartLabels) ?>, datasets: [{ label: 'Tickets', data: <?= json_encode($movieChartData) ?>, backgroundColor: PALETTE, borderRadius: 4 }] },
    options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
  });

  new Chart(document.getElementById('chartConcessions'), {
    type: 'bar',
    data: { labels: <?= json_encode($concChartLabels) ?>, datasets: [{ label: 'Units', data: <?= json_encode($concChartData) ?>, backgroundColor: PALETTE, borderRadius: 4 }] },
    options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
  });
  <?php else: ?>
  new Chart(document.getElementById('chartInventory'), {
    data: {
      labels: <?= json_encode($invChartLabels) ?>,
      datasets: [
        { type: 'bar', label: 'Stock', data: <?= json_encode($invChartStock) ?>, backgroundColor: <?= json_encode($invChartBarColors) ?>, borderRadius: 4, order: 2 },
        { type: 'line', label: 'Reorder Point', data: <?= json_encode($invChartReorder) ?>, borderColor: '#C8B8A8', borderDash: [6, 4], borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#C8B8A8', fill: false, spanGaps: false, order: 1 }
      ]
    },
    options: {
      plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });
  <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
