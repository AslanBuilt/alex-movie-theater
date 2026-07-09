<?php
declare(strict_types=1);

/**
 * GET /admin/api/reports-data.php?range=today|week|month|custom&start=YYYY-MM-DD&end=YYYY-MM-DD
 *
 * Single JSON endpoint feeding all 6 reports.php charts (admin-charts.js).
 *
 * Auth mirrors every other admin page exactly: Database::getInstance() +
 * new AdminAuth($db) + $auth->requireAuth(). This file deliberately does
 * NOT require admin-header.php — that file ob_start()s and then emits the
 * entire page shell (sidebar, nav, <main>), which would corrupt this
 * response with HTML before/around the JSON. requireAuth() itself still
 * 302-redirects to login.php (not a 401) when the session is missing or
 * expired, same as every other admin page — admin-charts.js's fetch()
 * detects that by checking the response Content-Type rather than the
 * status code, since a redirect-followed-to-login-HTML is still a 200.
 *
 * Only three of the six charts are date-range-scoped: category revenue,
 * top movies, and top concessions. The KPI strip (getSummaryStats), the
 * week-vs-last-week comparison, the this-month trend, and the inventory
 * chart are intrinsically fixed-period/always-current — retrofitting a
 * generic date engine onto them would mean threading a $pdo/date param
 * through methods that are static-no-arg by established convention
 * (getSalesReport, getRevenueByDayThisWeek, etc.), which the brief
 * explicitly prohibits changing. All six chart payloads are still
 * included in every response, so the front end re-renders all six on
 * every range change even though three of them show identical data.
 */

require_once __DIR__ . '/../../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/AdminAuth.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/InventoryRepo.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
    exit;
}

$auth = new AdminAuth($db);
$auth->requireAuth(); // 302 -> login.php + exit if not authenticated/expired

/** Strictly validate a Y-m-d date string (rejects "0000-00-00", overflowed dates, etc). */
function reports_valid_date(?string $v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

$rangeKey = (string)($_GET['range'] ?? 'week');
if (!in_array($rangeKey, ['today', 'week', 'month', 'custom'], true)) {
    $rangeKey = 'week';
}

$today = new DateTime('today');
$start = null;
$end   = null;

if ($rangeKey === 'custom') {
    $customStart = reports_valid_date(is_string($_GET['start'] ?? null) ? $_GET['start'] : null);
    $customEnd   = reports_valid_date(is_string($_GET['end'] ?? null) ? $_GET['end'] : null);
    if ($customStart !== null && $customEnd !== null) {
        $start = new DateTime($customStart);
        $end   = new DateTime($customEnd);
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
    } else {
        $rangeKey = 'week'; // missing/invalid custom bounds — fall back rather than error
    }
}

if ($rangeKey === 'today') {
    $start = clone $today;
    $end   = clone $today;
} elseif ($rangeKey === 'week') {
    $dow   = (int)$today->format('N');
    $start = (clone $today)->modify('-' . ($dow - 1) . ' days');
    $end   = clone $today;
} elseif ($rangeKey === 'month') {
    $start = new DateTime('first day of this month');
    $end   = clone $today;
}

$startStr = $start->format('Y-m-d');
$endStr   = $end->format('Y-m-d');

// ── Fixed-period payloads (not affected by $rangeKey) ───────────────────────
$summary = TransactionRepo::getSummaryStats();

$revenueWeekRaw = TransactionRepo::getRevenueByDayWithComparison();
$thisWeekTotal  = array_sum(array_column($revenueWeekRaw, 'this_week'));
$lastWeekTotal  = array_sum(array_column($revenueWeekRaw, 'last_week'));
if ($lastWeekTotal > 0) {
    $changePct = round((($thisWeekTotal - $lastWeekTotal) / $lastWeekTotal) * 100, 1);
} else {
    $changePct = $thisWeekTotal > 0 ? 100.0 : 0.0;
}

$monthStart  = new DateTime('first day of this month');
$daysSoFar   = (int)$today->format('j');
$monthByDate = [];
foreach (TransactionRepo::getRevenueByDayThisMonth() as $r) {
    $monthByDate[(string)$r['day']] = (float)$r['revenue'];
}
$monthLabels = [];
$monthData   = [];
for ($i = 0; $i < $daysSoFar; $i++) {
    $d             = (clone $monthStart)->modify("+$i days");
    $monthLabels[] = $d->format('j');
    $monthData[]   = round($monthByDate[$d->format('Y-m-d')] ?? 0, 2);
}
$monthAverage = count($monthData) > 0 ? round(array_sum($monthData) / count($monthData), 2) : 0.0;

$inventoryRaw = InventoryRepo::getFullInventory();
$lowStock     = InventoryRepo::getLowStock();
$lowStockIds  = array_column($lowStock, 'id');
$inventoryOut = array_map(static function (array $i) use ($lowStockIds): array {
    return [
        'id'             => (int)$i['id'],
        'category'       => (string)$i['category'],
        'name'           => (string)$i['name'],
        'stock_quantity' => (int)$i['stock_quantity'],
        'reorder_point'  => $i['reorder_point'] !== null ? (int)$i['reorder_point'] : null,
        'cost'           => $i['cost'] !== null ? (float)$i['cost'] : null,
        'price'          => (float)$i['price'],
        'is_available'   => (bool)(int)$i['is_available'],
        'is_low'         => in_array((int)$i['id'], $lowStockIds, true),
    ];
}, $inventoryRaw);

// ── Range-scoped payloads ────────────────────────────────────────────────────
$byTypeRange = TransactionRepo::getRevenueByTypeInRange($startStr, $endStr);
$catLabels   = ['Tickets', 'Concessions', 'Combos'];
$catKeys     = ['ticket', 'concession', 'combo'];
$catData     = [];
$catNoSales  = [];
foreach ($catKeys as $k) {
    $rev          = (float)($byTypeRange[$k]['revenue'] ?? 0);
    $catData[]    = round($rev, 2);
    $catNoSales[] = $rev <= 0;
}

$topMoviesOut = array_map(static function (array $r): array {
    return [
        'movie_id'      => (int)$r['movie_id'],
        'item_name'     => (string)$r['item_name'],
        'poster_path'   => posterUrl($r['poster_path'] ?? ''), // absolute URL — see includes/helpers.php
        'total_qty'     => (int)$r['total_qty'],
        'total_revenue' => round((float)$r['total_revenue'], 2),
        'adult_count'   => (int)$r['adult_count'],
        'child_count'   => (int)$r['child_count'],
    ];
}, TransactionRepo::getTopMoviesWithAgeSplit(5, $startStr, $endStr));

$topConcessionsOut = array_map(static function (array $r): array {
    $cost          = $r['cost'] !== null ? (float)$r['cost'] : null;
    $price         = (float)$r['price'];
    $qty           = (int)$r['total_qty'];
    $marginPerUnit = $cost !== null ? round($price - $cost, 2) : null;
    return [
        'concession_id'   => (int)$r['concession_id'],
        'item_name'       => (string)$r['item_name'],
        'total_qty'       => $qty,
        'total_revenue'   => round((float)$r['total_revenue'], 2),
        'cost'            => $cost,
        'price'           => $price,
        'margin_per_unit' => $marginPerUnit,
        'margin_total'    => $marginPerUnit !== null ? round($marginPerUnit * $qty, 2) : null,
    ];
}, TransactionRepo::getTopConcessionsWithMargin(5, $startStr, $endStr));

echo json_encode([
    'ok'    => true,
    'range' => ['key' => $rangeKey, 'start' => $startStr, 'end' => $endStr],
    'summary' => $summary,
    'revenueWeek' => [
        'labels'        => array_column($revenueWeekRaw, 'day'),
        'thisWeek'      => array_map(static fn (array $r): float => (float)$r['this_week'], $revenueWeekRaw),
        'lastWeek'      => array_map(static fn (array $r): float => (float)$r['last_week'], $revenueWeekRaw),
        'thisWeekTotal' => round($thisWeekTotal, 2),
        'lastWeekTotal' => round($lastWeekTotal, 2),
        'changePct'     => $changePct,
    ],
    'revenueMonth' => [
        'labels'  => $monthLabels,
        'data'    => $monthData,
        'average' => $monthAverage,
    ],
    'byCategory' => [
        'labels'  => $catLabels,
        'data'    => $catData,
        'noSales' => $catNoSales,
    ],
    'topMovies'      => $topMoviesOut,
    'topConcessions' => $topConcessionsOut,
    'inventory'      => $inventoryOut,
    'lowStockCount'  => count($lowStock),
], JSON_THROW_ON_ERROR);
