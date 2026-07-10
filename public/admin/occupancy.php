<?php
declare(strict_types=1);

$pageTitle = 'Occupancy Report';
require_once __DIR__ . '/includes/admin-header.php';

$totalInBuilding = 0;
$showtimes = [];

try {
    $totalInBuilding = (int)$db->query(
        "SELECT COUNT(*) FROM ticket_tokens WHERE token_status = 'used' AND DATE(checked_in_at) = CURDATE()"
    )->fetchColumn();

    $stmt = $db->prepare(
        "SELECT s.id AS showtime_id, m.title, s.showtime_date, s.showtime_time, s.label,
                s.available_tickets, s.tickets_sold
         FROM showtimes s
         LEFT JOIN movies m ON m.id = s.movie_id
         WHERE s.showtime_date = CURDATE() AND s.is_active = 1
         ORDER BY s.showtime_time ASC, s.sort_order ASC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $attStmt = $db->prepare(
        "SELECT tt.token_status, tt.checked_in_at, t.customer_name, t.customer_email, ti.selected_option
         FROM ticket_tokens tt
         JOIN transactions t ON t.id = tt.transaction_id
         LEFT JOIN transaction_items ti ON ti.id = tt.transaction_item_id
         WHERE tt.showtime_id = :sid AND tt.token_status != 'voided'
         ORDER BY t.customer_name ASC"
    );

    foreach ($rows as $row) {
        $when = '';
        if (!empty($row['showtime_time'])) {
            $when = date('g:i A', strtotime((string)$row['showtime_time']));
        } elseif (!empty($row['label'])) {
            $when = (string)$row['label'];
        }

        $attStmt->execute([':sid' => $row['showtime_id']]);
        $attendees = $attStmt->fetchAll(PDO::FETCH_ASSOC);
        $checkedIn = 0;
        foreach ($attendees as $a) {
            if ($a['token_status'] === 'used') {
                $checkedIn++;
            }
        }

        $showtimes[] = [
            'title'      => (string)($row['title'] ?? '— (deleted) —'),
            'when'       => $when,
            'capacity'   => (int)$row['available_tickets'],
            'sold'       => (int)$row['tickets_sold'],
            'checkedIn'  => $checkedIn,
            'attendees'  => $attendees,
        ];
    }
} catch (PDOException $e) {
    error_log('occupancy.php failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load the occupancy report.'];
}

$now = (new DateTime())->format('M j, Y g:i A');
?>
<style>
@media print {
    .admin-sidebar, .sidebar-toggle, .admin-page-actions, .occ-no-print, .admin-page-header h1 { display: none !important; }
    .admin-layout, .admin-content { display: block !important; width: 100% !important; max-width: none !important; padding: 0 !important; }
    body, .admin-body { background: #fff !important; color: #000 !important; }
    .occ-print-title { display: block !important; }
    .occ-total, .occ-show { border: 1px solid #999 !important; background: #fff !important; color: #000 !important; }
    .occ-total .num, .occ-show-head .title, h1 { color: #000 !important; }
    a { color: #000 !important; text-decoration: none !important; }
}
.occ-print-title { display: none; margin-bottom: 1rem; }
.occ-total {
    background: var(--bg-card-hover); border: 1px solid var(--crimson-dark); border-radius: 10px;
    padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;
}
.occ-total .num { font-family: var(--font-display); font-size: 3.5rem; line-height: 1; color: var(--text-primary); }
.occ-total .lbl { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-secondary); margin-top: 0.4rem; }
.occ-show { border: 1px solid var(--border-light); border-radius: 8px; margin-bottom: 1rem; overflow: hidden; }
.occ-show-head { display: flex; justify-content: space-between; align-items: center; background: var(--bg-card-hover); padding: 0.8rem 1.1rem; flex-wrap: wrap; gap: 0.5rem; }
.occ-show-head .title { font-weight: 700; color: var(--text-primary); }
.occ-show-head .when { color: var(--text-secondary); font-size: 0.85rem; }
.occ-bar-row { padding: 0.7rem 1.1rem 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
.occ-bar-track { flex: 1; height: 8px; background: var(--bg-primary); border-radius: 4px; overflow: hidden; }
.occ-bar-fill { height: 100%; background: var(--crimson); }
.occ-bar-label { font-size: 0.78rem; color: var(--text-secondary); white-space: nowrap; font-variant-numeric: tabular-nums; }
.occ-attendees { padding: 0 1.1rem 1rem; }
.occ-attendees table { width: 100%; font-size: 0.85rem; }
.occ-attendees th { text-align: left; color: var(--text-secondary); font-size: 0.72rem; text-transform: uppercase; padding: 0.3rem 0.5rem; }
.occ-attendees td { padding: 0.3rem 0.5rem; border-top: 1px solid var(--border); }
</style>

<div class="admin-page-header">
    <h1>Occupancy Report</h1>
    <div class="admin-page-actions occ-no-print">
        <button type="button" class="btn btn-outline btn-sm" onclick="location.reload()">&#8635; Refresh</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">&#128424; Print Report</button>
    </div>
</div>

<div class="occ-print-title">
    <h2 style="font-family:var(--font-display);"><?= e(SITE_NAME) ?> — Occupancy Report</h2>
    <p>Generated <?= e($now) ?></p>
</div>

<p class="occ-no-print" style="color:var(--text-secondary); font-size:0.85rem; margin-top:-0.5rem;">Last updated <?= e($now) ?></p>

<div class="occ-total">
    <div class="num tnum"><?= $totalInBuilding ?></div>
    <div class="lbl">People currently in the building</div>
</div>

<?php if (empty($showtimes)): ?>
    <p style="color:var(--text-secondary);">No active showtimes scheduled today.</p>
<?php else: foreach ($showtimes as $st): ?>
    <div class="occ-show">
        <div class="occ-show-head">
            <span class="title"><?= e($st['title']) ?><?= $st['when'] !== '' ? ' — ' . e($st['when']) : '' ?></span>
        </div>
        <div class="occ-bar-row">
            <div class="occ-bar-track">
                <div class="occ-bar-fill" style="width:<?= $st['capacity'] > 0 ? min(100, round($st['checkedIn'] / $st['capacity'] * 100)) : 0 ?>%"></div>
            </div>
            <div class="occ-bar-label tnum"><?= $st['checkedIn'] ?> checked in / <?= $st['sold'] ?> sold / <?= $st['capacity'] ?> cap</div>
        </div>
        <?php if (!empty($st['attendees'])): ?>
        <div class="occ-attendees">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Age Tier</th><th>Status</th><th>Checked In At</th></tr></thead>
                <tbody>
                <?php foreach ($st['attendees'] as $a): ?>
                    <tr>
                        <td><?= e((string)($a['customer_name'] ?: 'Walk-up guest')) ?></td>
                        <td><?= e((string)($a['customer_email'] ?: '—')) ?></td>
                        <td><?= e((string)($a['selected_option'] ?: '—')) ?></td>
                        <td><?= $a['token_status'] === 'used' ? 'Checked in' : 'Not yet arrived' ?></td>
                        <td><?= $a['checked_in_at'] ? e(date('g:i A', strtotime((string)$a['checked_in_at']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="occ-attendees" style="color:var(--text-secondary); font-size:0.85rem;">No tickets sold yet for this showtime.</div>
        <?php endif; ?>
    </div>
<?php endforeach; endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
