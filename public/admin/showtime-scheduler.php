<?php
declare(strict_types=1);

$pageTitle = 'Weekly Schedule Builder';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';

const SCHED_MAX_DAYS = 366; // sanity cap on the date range — not in the spec, just a backstop
const SCHED_DOW       = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Builds the YYYY-MM-DD list for every date in [start,end] whose weekday is in $days (0=Sun..6=Sat). */
function sched_generate_dates(string $start, string $end, array $days): array
{
    try {
        $cur = new DateTime($start);
        $stop = new DateTime($end);
    } catch (\Throwable $e) {
        return [];
    }
    if ($cur > $stop || $cur->diff($stop)->days > SCHED_MAX_DAYS) {
        return [];
    }
    $out = [];
    while ($cur <= $stop) {
        if (in_array((int)$cur->format('w'), $days, true)) {
            $out[] = $cur->format('Y-m-d');
        }
        $cur->modify('+1 day');
    }
    return $out;
}

function sched_minutes(string $time): int
{
    $ts = strtotime($time);
    return $ts === false ? 0 : ((int)date('G', $ts) * 60 + (int)date('i', $ts));
}

/**
 * Finds existing showtimes (on other movies) that conflict with a generated
 * batch on the same screen. ASSUMPTION (documented, no separate "physical
 * screen" entity exists in this schema): two showtimes conflict if their
 * movies' `screen` values are equal, or either is 'either' (a flexible movie
 * could land on whichever physical screen the other one is using). When both
 * movies have a duration_minutes set, conflict requires actual time-range
 * overlap; otherwise it falls back to "same date + same screen" — coarser,
 * but never silently misses a same-day double-book just because a runtime
 * wasn't entered yet.
 *
 * @param string[] $dates
 * @return array<string, string[]> date => list of human-readable conflict descriptions
 */
function sched_find_conflicts(PDO $db, int $movieId, string $movieScreen, ?int $movieDuration, array $dates, string $time): array
{
    if (!$dates) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $stmt = $db->prepare(
        "SELECT s.showtime_date, s.showtime_time, m.screen, m.duration_minutes, m.title
         FROM showtimes s LEFT JOIN movies m ON m.id = s.movie_id
         WHERE s.is_active = 1 AND s.movie_id != ? AND s.showtime_date IN ($placeholders)"
    );
    $stmt->execute(array_merge([$movieId], $dates));

    $byDate = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byDate[(string)$row['showtime_date']][] = $row;
    }

    $newStart = sched_minutes($time);
    $newEnd   = $movieDuration ? $newStart + $movieDuration : null;

    $conflicts = [];
    foreach ($dates as $d) {
        foreach ($byDate[$d] ?? [] as $ex) {
            $exScreen = (string)($ex['screen'] ?? 'either');
            $screenConflict = $movieScreen === 'either' || $exScreen === 'either' || $movieScreen === $exScreen;
            if (!$screenConflict) {
                continue;
            }
            $exDuration = $ex['duration_minutes'] !== null ? (int)$ex['duration_minutes'] : null;
            $exStart    = sched_minutes((string)$ex['showtime_time']);
            $isOverlap  = true; // coarse default when either side's duration is unknown
            if ($newEnd !== null && $exDuration !== null) {
                $exEnd     = $exStart + $exDuration;
                $isOverlap = $newStart < $exEnd && $exStart < $newEnd;
            }
            if ($isOverlap) {
                $conflicts[$d][] = (string)$ex['title'] . ' at ' . date('g:i A', strtotime((string)$ex['showtime_time']));
            }
        }
    }
    return $conflicts;
}

$movies = [];
try {
    $movies = $db->query('SELECT id, title, screen, duration_minutes FROM movies ORDER BY title ASC')
                 ->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('showtime-scheduler movies load: ' . $e->getMessage());
}
$movieDurations = [];
$movieById = [];
foreach ($movies as $m) {
    $movieById[(int)$m['id']] = $m;
    if ($m['duration_minutes'] !== null) {
        $movieDurations[(int)$m['id']] = (int)$m['duration_minutes'];
    }
}

$old = [
    'movie_id'          => 0,
    'days'              => [],
    'start_time'        => '',
    'date_start'        => '',
    'date_end'          => '',
    'available_tickets' => 50,
];
$errors    = [];
$preview   = null; // ['dates' => [...], 'conflicts' => [...]] once previewed
$createdN  = null;

$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['preview', 'create'], true)) {
    if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['movie_id']          = (int)($_POST['movie_id'] ?? 0);
        $old['days']              = array_values(array_intersect(array_map('intval', (array)($_POST['days'] ?? [])), range(0, 6)));
        $old['start_time']        = trim((string)($_POST['start_time'] ?? ''));
        $old['date_start']        = trim((string)($_POST['date_start'] ?? ''));
        $old['date_end']          = trim((string)($_POST['date_end'] ?? ''));
        $old['available_tickets'] = max(0, (int)($_POST['available_tickets'] ?? 50));

        if ($old['movie_id'] <= 0 || !isset($movieById[$old['movie_id']])) {
            $errors[] = 'Please select a movie.';
        }
        if (empty($old['days'])) {
            $errors[] = 'Pick at least one day of the week.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $old['start_time'])) {
            $errors[] = 'Start time is required.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['date_start']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['date_end'])) {
            $errors[] = 'A valid start and end date are required.';
        } elseif ($old['date_start'] > $old['date_end']) {
            $errors[] = 'The end date must be on or after the start date.';
        }

        if (empty($errors)) {
            $dates = sched_generate_dates($old['date_start'], $old['date_end'], $old['days']);
            if (!$dates) {
                $errors[] = 'That date range and day selection produced no showtimes — check the range.';
            } else {
                $movie     = $movieById[$old['movie_id']];
                $conflicts = sched_find_conflicts(
                    $db,
                    $old['movie_id'],
                    (string)$movie['screen'],
                    $movie['duration_minutes'] !== null ? (int)$movie['duration_minutes'] : null,
                    $dates,
                    $old['start_time']
                );

                if ($action === 'preview') {
                    $preview = ['dates' => $dates, 'conflicts' => $conflicts, 'movie' => $movie];
                } else { // create
                    try {
                        $label = date('g:i A', strtotime($old['start_time']));
                        $db->beginTransaction();
                        $ins = $db->prepare(
                            'INSERT INTO showtimes
                                (movie_id, label, times, showtime_date, showtime_time, available_tickets, tickets_sold, is_active, sort_order)
                             VALUES
                                (:movie_id, :label, :times, :date, :time, :avail, 0, 1, 0)'
                        );
                        foreach ($dates as $d) {
                            $ins->execute([
                                ':movie_id' => $old['movie_id'],
                                ':label'    => (new DateTime($d))->format('D, M j') . ' ' . $label,
                                ':times'    => $label,
                                ':date'     => $d,
                                ':time'     => $old['start_time'],
                                ':avail'    => $old['available_tickets'],
                            ]);
                        }
                        $db->commit();
                        $createdN = count($dates);
                        $_SESSION['flash'] = ['type' => 'success', 'message' => "Created $createdN showtime(s)."];
                        $firstDate = $dates[0];
                        header('Location: showtimes.php?year=' . substr($firstDate, 0, 4) . '&month=' . (int)substr($firstDate, 5, 2));
                        exit;
                    } catch (\Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log('showtime-scheduler create failed: ' . $e->getMessage());
                        $errors[] = 'Could not save the schedule. Please try again.';
                    }
                }
            }
        }
    }
}

$csrf = $auth->generateCsrfToken();
?>
<div class="admin-page-header">
    <h1>Weekly Schedule Builder</h1>
    <div class="admin-page-actions">
        <a class="btn btn-outline btn-sm" href="showtime-edit.php">Add one-off showtime instead</a>
        <a class="btn btn-outline btn-sm" href="showtimes.php">Back to calendar</a>
    </div>
</div>

<?php if (!empty($errors)) : ?>
    <div class="alert alert-error" role="alert">
        <ul style="margin:0;padding-left:1.25rem;">
            <?php foreach ($errors as $err) : ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="admin-form" data-prevent-double="1" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="preview">

    <div class="form-group">
        <label for="movie_id">Movie <span class="required">*</span></label>
        <select name="movie_id" id="movie_id" required>
            <option value="">-- Select a movie --</option>
            <?php foreach ($movies as $m) : ?>
                <option value="<?= (int)$m['id'] ?>" <?= $old['movie_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                    <?= e((string)$m['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Days of the week <span class="required">*</span></label>
        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <?php foreach (SCHED_DOW as $i => $name) : ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="days[]" value="<?= $i ?>" <?= in_array($i, $old['days'], true) ? 'checked' : '' ?>>
                    <?= e($name) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="start_time">Start time <span class="required">*</span></label>
            <input type="time" name="start_time" id="start_time" value="<?= e($old['start_time']) ?>" required>
        </div>
        <div class="form-group" id="end-time-calculated" style="display:none;">
            <label>Ends</label>
            <div id="end-time-readout" style="padding:0.55rem 0; font-weight:600;"></div>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="date_start">Start date <span class="required">*</span></label>
            <input type="date" name="date_start" id="date_start" value="<?= e($old['date_start']) ?>" required>
        </div>
        <div class="form-group">
            <label for="date_end">End date <span class="required">*</span></label>
            <input type="date" name="date_end" id="date_end" value="<?= e($old['date_end']) ?>" required>
        </div>
        <div class="form-group">
            <label for="available_tickets">Available tickets (each showtime)</label>
            <input type="number" name="available_tickets" id="available_tickets" min="0" value="<?= (int)$old['available_tickets'] ?>">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Preview Schedule</button>
    </div>
</form>

<?php if ($preview !== null) : ?>
    <div class="admin-table-wrap" style="margin-top:1.5rem;">
        <h2 style="font-size:1.1rem;">
            Preview — <?= count($preview['dates']) ?> showtime(s) for <?= e((string)$preview['movie']['title']) ?>
        </h2>
        <?php if (!empty($preview['conflicts'])) : ?>
            <div class="alert alert-error" role="alert">
                <?= count($preview['conflicts']) ?> of these dates conflict with an existing showtime on the same screen. Review below — saving will not remove or change the existing ones.
            </div>
        <?php endif; ?>
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Day</th><th>Time</th><th>Conflict</th></tr></thead>
            <tbody>
            <?php foreach ($preview['dates'] as $d) :
                $dt = new DateTime($d);
                $hasConflict = !empty($preview['conflicts'][$d]);
            ?>
                <tr>
                    <td><?= e($dt->format('M j, Y')) ?></td>
                    <td><?= e($dt->format('D')) ?></td>
                    <td><?= e(date('g:i A', strtotime($old['start_time']))) ?></td>
                    <td>
                        <?php if ($hasConflict) : ?>
                            <span class="badge-warning" style="display:inline-block;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.7rem;font-weight:700;">
                                <?= e(implode('; ', $preview['conflicts'][$d])) ?>
                            </span>
                        <?php else : ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="post" class="admin-form" style="margin-top:1rem;" data-prevent-double="1">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="movie_id" value="<?= (int)$old['movie_id'] ?>">
        <?php foreach ($old['days'] as $d) : ?><input type="hidden" name="days[]" value="<?= (int)$d ?>"><?php endforeach; ?>
        <input type="hidden" name="start_time" value="<?= e($old['start_time']) ?>">
        <input type="hidden" name="date_start" value="<?= e($old['date_start']) ?>">
        <input type="hidden" name="date_end" value="<?= e($old['date_end']) ?>">
        <input type="hidden" name="available_tickets" value="<?= (int)$old['available_tickets'] ?>">
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Confirm and Save <?= count($preview['dates']) ?> Showtime(s)</button>
        </div>
    </form>
<?php endif; ?>

<script>
(function () {
    var durations = <?= json_encode($movieDurations, JSON_FORCE_OBJECT) ?>;
    var movieSel  = document.getElementById('movie_id');
    var timeInput = document.getElementById('start_time');
    var calcGrp   = document.getElementById('end-time-calculated');
    var readout   = document.getElementById('end-time-readout');
    if (!movieSel || !timeInput) return;

    function formatTime(totalMinutes) {
        totalMinutes = ((totalMinutes % 1440) + 1440) % 1440;
        var h = Math.floor(totalMinutes / 60), m = totalMinutes % 60;
        var suffix = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12; if (h12 === 0) h12 = 12;
        return h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + suffix;
    }
    function update() {
        var duration = durations[movieSel.value];
        if (!duration || !timeInput.value) { calcGrp.style.display = 'none'; return; }
        var parts = timeInput.value.split(':');
        var startMinutes = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
        readout.textContent = formatTime(startMinutes) + ' → Ends ' + formatTime(startMinutes + duration);
        calcGrp.style.display = '';
    }
    movieSel.addEventListener('change', update);
    timeInput.addEventListener('input', update);
    update();
})();
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
