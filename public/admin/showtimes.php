<?php
declare(strict_types=1);

$pageTitle = 'Showtimes';
require_once __DIR__ . '/includes/admin-header.php';

// Deterministic, brand-coherent color per movie — distinct from the crimson
// chrome color (reserved for the "today" outline) so legend swatches never
// get confused with UI accents.
const CAL_PALETTE = ['#3a5a7a', '#6a4a8a', '#4a7a5a', '#a8632a', '#7a4a4a', '#4a7a7a', '#8a7a3a', '#5a4a8a'];
function cal_color(int $movieId): string
{
    return CAL_PALETTE[$movieId % count(CAL_PALETTE)];
}

$view = ($_GET['view'] ?? 'month') === 'week' ? 'week' : 'month';
$today = new DateTime('today');

if ($view === 'month') {
    $year  = (int)($_GET['year'] ?? $today->format('Y'));
    $month = (int)($_GET['month'] ?? $today->format('n'));
    if ($month < 1) { $month = 12; $year--; }
    if ($month > 12) { $month = 1; $year++; }
    $first = DateTime::createFromFormat('Y-n-j', "$year-$month-1");
    $gridStart = (clone $first)->modify('-' . ((int)$first->format('w')) . ' days');
    $gridEnd   = (clone $gridStart)->modify('+41 days');
    $title     = $first->format('F Y');

    $prevM = $month - 1; $prevY = $year; if ($prevM < 1) { $prevM = 12; $prevY--; }
    $nextM = $month + 1; $nextY = $year; if ($nextM > 12) { $nextM = 1; $nextY++; }
    $prevUrl = "showtimes.php?view=month&year=$prevY&month=$prevM";
    $nextUrl = "showtimes.php?view=month&year=$nextY&month=$nextM";
    $todayUrl = 'showtimes.php?view=month&year=' . $today->format('Y') . '&month=' . $today->format('n');
} else {
    $dateParam = (string)($_GET['date'] ?? $today->format('Y-m-d'));
    try {
        $anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam) ? new DateTime($dateParam) : new DateTime();
    } catch (\Throwable $e) {
        $anchor = new DateTime();
    }
    $gridStart = (clone $anchor)->modify('-' . ((int)$anchor->format('w')) . ' days');
    $gridEnd   = (clone $gridStart)->modify('+6 days');
    $title     = $gridStart->format('M j') . ' – ' . $gridEnd->format('M j, Y');

    $prevUrl  = 'showtimes.php?view=week&date=' . (clone $gridStart)->modify('-7 days')->format('Y-m-d');
    $nextUrl  = 'showtimes.php?view=week&date=' . (clone $gridStart)->modify('+7 days')->format('Y-m-d');
    $todayUrl = 'showtimes.php?view=week&date=' . $today->format('Y-m-d');
}

$rangeStart = $gridStart->format('Y-m-d');
$rangeEnd   = $gridEnd->format('Y-m-d');

$eventsByDate = [];
try {
    $stmt = $db->prepare(
        'SELECT s.id, s.movie_id, s.showtime_date, s.showtime_time, s.label, m.title, m.screen
         FROM showtimes s
         LEFT JOIN movies m ON m.id = s.movie_id
         WHERE s.is_active = 1 AND s.showtime_date BETWEEN :start AND :end
         ORDER BY s.showtime_date ASC, s.showtime_time ASC, s.sort_order ASC'
    );
    $stmt->execute([':start' => $rangeStart, ':end' => $rangeEnd]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eventsByDate[(string)$row['showtime_date']][] = $row;
    }
} catch (PDOException $e) {
    error_log('showtimes.php calendar query failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load showtimes.'];
}

$legendMovies = [];
foreach ($eventsByDate as $dayEvents) {
    foreach ($dayEvents as $ev) {
        $mid = (int)$ev['movie_id'];
        if (!isset($legendMovies[$mid])) {
            $legendMovies[$mid] = (string)($ev['title'] ?? '— (deleted) —');
        }
    }
}

$cellCount  = $view === 'month' ? 42 : 7;
$curMonth   = $view === 'month' ? $month : null;
$todayStr   = $today->format('Y-m-d');
?>
<div class="admin-page-header">
    <h1>Showtimes</h1>
    <div class="admin-page-actions">
        <a class="btn btn-outline btn-sm" href="showtime-scheduler.php">Weekly Schedule</a>
        <a class="btn btn-primary" href="showtime-edit.php">New Showtime</a>
    </div>
</div>

<div class="cal-toolbar">
    <div class="cal-nav">
        <a class="btn btn-outline btn-sm" href="<?= e($prevUrl) ?>" aria-label="Previous">&#8249;</a>
        <h2><?= e($title) ?></h2>
        <a class="btn btn-outline btn-sm" href="<?= e($nextUrl) ?>" aria-label="Next">&#8250;</a>
        <a class="btn btn-outline btn-sm" href="<?= e($todayUrl) ?>">Today</a>
    </div>
    <div class="cal-view-toggle">
        <a class="btn btn-outline btn-sm <?= $view === 'month' ? 'active' : '' ?>" href="showtimes.php?view=month">Month</a>
        <a class="btn btn-outline btn-sm <?= $view === 'week' ? 'active' : '' ?>" href="showtimes.php?view=week&date=<?= e($gridStart->format('Y-m-d')) ?>">Week</a>
    </div>
</div>

<div class="cal-grid <?= $view === 'week' ? 'is-week' : '' ?>" id="calGrid">
    <div class="cal-dow">Sun</div><div class="cal-dow">Mon</div><div class="cal-dow">Tue</div>
    <div class="cal-dow">Wed</div><div class="cal-dow">Thu</div><div class="cal-dow">Fri</div><div class="cal-dow">Sat</div>

    <?php
    $cellDate = clone $gridStart;
    for ($i = 0; $i < $cellCount; $i++):
        $dateStr = $cellDate->format('Y-m-d');
        $isDim   = $view === 'month' && (int)$cellDate->format('n') !== $curMonth;
        $isToday = $dateStr === $todayStr;
        $dayEvents = $eventsByDate[$dateStr] ?? [];
    ?>
        <div class="cal-cell <?= $isDim ? 'is-dim' : '' ?> <?= $isToday ? 'is-today' : '' ?>">
            <span class="cal-daynum"><?= (int)$cellDate->format('j') ?></span>
            <?php foreach ($dayEvents as $ev) :
                $color = cal_color((int)$ev['movie_id']);
                $evTitle = (string)($ev['title'] ?? '— (deleted) —');
                $evWhen = '';
                if (!empty($ev['showtime_time'])) {
                    $evWhen = date('g:i A', strtotime((string)$ev['showtime_time']));
                } elseif (!empty($ev['label'])) {
                    $evWhen = (string)$ev['label'];
                }
                $abbrev = mb_strlen($evTitle) > 16 ? mb_substr($evTitle, 0, 15) . '…' : $evTitle;
            ?>
                <button type="button" class="cal-event" style="background:<?= e($color) ?>;"
                        data-id="<?= (int)$ev['id'] ?>"
                        data-title="<?= e($evTitle) ?>"
                        data-when="<?= e($cellDate->format('D, M j') . ($evWhen !== '' ? ' — ' . $evWhen : '')) ?>"
                        data-screen="<?= e($ev['screen'] !== null ? ucfirst((string)$ev['screen']) : '') ?>">
                    <?= e($abbrev) ?><?= $evWhen !== '' ? ' ' . e($evWhen) : '' ?>
                </button>
            <?php endforeach; ?>
            <a class="cal-add" href="showtime-edit.php?date=<?= e($dateStr) ?>" title="Add a showtime on <?= e($cellDate->format('M j')) ?>" aria-label="Add a showtime on <?= e($cellDate->format('M j')) ?>">+</a>
        </div>
    <?php
        $cellDate->modify('+1 day');
    endfor;
    ?>
</div>

<?php if (!empty($legendMovies)) : ?>
<div class="cal-legend">
    <?php foreach ($legendMovies as $mid => $mtitle) : ?>
        <span class="cal-legend-item"><span class="cal-legend-swatch" style="background:<?= e(cal_color($mid)) ?>;"></span><?= e($mtitle) ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Event detail / edit-or-delete overlay -->
<div class="modal-backdrop" id="eventModal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="eventModalTitle">
        <h2 id="eventModalTitle" class="modal-title">Showtime</h2>
        <dl>
            <dt>Movie</dt><dd id="eventModalMovie"></dd>
            <dt>When</dt><dd id="eventModalWhen"></dd>
            <dt>Screen</dt><dd id="eventModalScreen"></dd>
        </dl>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" data-modal-close>Close</button>
            <a class="btn btn-outline" id="eventModalEdit" href="#">Edit</a>
            <button type="button" class="btn btn-danger" id="eventModalDelete">Delete</button>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('eventModal');
    var grid  = document.getElementById('calGrid');
    if (!modal || !grid) return;

    function openEventModal(btn) {
        document.getElementById('eventModalMovie').textContent  = btn.getAttribute('data-title');
        document.getElementById('eventModalWhen').textContent   = btn.getAttribute('data-when');
        document.getElementById('eventModalScreen').textContent = btn.getAttribute('data-screen') || '—';
        var id = btn.getAttribute('data-id');
        document.getElementById('eventModalEdit').setAttribute('href', 'showtime-edit.php?id=' + id);
        var delBtn = document.getElementById('eventModalDelete');
        delBtn.onclick = function () {
            modal.setAttribute('hidden', '');
            confirmDelete(id, btn.getAttribute('data-title'), 'showtime-delete.php');
        };
        modal.removeAttribute('hidden');
    }

    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.cal-event');
        if (btn) openEventModal(btn);
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.setAttribute('hidden', ''); });
    modal.querySelectorAll('[data-modal-close]').forEach(function (b) {
        b.addEventListener('click', function () { modal.setAttribute('hidden', ''); });
    });

    // iPad portrait and similarly narrow-tall viewports default to week view,
    // unless the admin already picked a view explicitly.
    if (!/view=/.test(location.search)) {
        var mq = window.matchMedia('(max-width: 834px) and (orientation: portrait)');
        if (mq.matches) {
            location.replace('showtimes.php?view=week&date=<?= e($todayStr) ?>');
        }
    }
})();
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
