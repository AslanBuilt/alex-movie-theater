<?php
declare(strict_types=1);

$pageTitle = 'Edit Showtime';
require_once __DIR__ . '/includes/admin-header.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$old = [
    'movie_id'          => (int)($_GET['movie_id'] ?? 0),
    'showtime_date'     => '',
    'showtime_time'     => '',
    'available_tickets' => 50,
    'is_active'         => 1,
    'sort_order'        => 0,
    // legacy fields kept for display
    'label'             => '',
    'times'             => '',
];
$errors  = [];
$movies  = [];
$isLegacy = false;

try {
    $movies = $db->query('SELECT id, title, duration_minutes FROM movies ORDER BY title ASC')
                 ->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('showtime-edit movies load: ' . $e->getMessage());
}

$movieDurations = [];
foreach ($movies as $m) {
    if ($m['duration_minutes'] !== null) {
        $movieDurations[(int)$m['id']] = (int)$m['duration_minutes'];
    }
}

if ($isEdit) {
    $row = ShowtimeRepo::getById($id);
    if (!$row) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Showtime not found.'];
        header('Location: showtimes.php');
        exit;
    }
    $isLegacy = empty($row['showtime_time']);
    $old = [
        'movie_id'          => (int)$row['movie_id'],
        'showtime_date'     => (string)($row['showtime_date'] ?? ''),
        'showtime_time'     => isset($row['showtime_time']) ? date('H:i', strtotime((string)$row['showtime_time'])) : '',
        'available_tickets' => (int)($row['available_tickets'] ?? 50),
        'is_active'         => (int)($row['is_active'] ?? 1),
        'sort_order'        => (int)$row['sort_order'],
        'label'             => (string)$row['label'],
        'times'             => (string)$row['times'],
    ];
    $pageTitle = 'Edit Showtime';
} else {
    $pageTitle = 'New Showtime';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['movie_id']          = (int)($_POST['movie_id'] ?? 0);
        $old['showtime_date']     = trim((string)($_POST['showtime_date'] ?? ''));
        $old['showtime_time']     = trim((string)($_POST['showtime_time'] ?? ''));
        $old['available_tickets'] = max(0, (int)($_POST['available_tickets'] ?? 50));
        $old['is_active']         = isset($_POST['is_active']) ? 1 : 0;
        $old['sort_order']        = (int)($_POST['sort_order'] ?? 0);

        if ($old['movie_id'] <= 0) $errors[] = 'Please select a movie.';
        if ($old['showtime_date'] === '') $errors[] = 'Date is required.';
        if ($old['showtime_time'] === '') $errors[] = 'Time is required.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['showtime_date'])) $errors[] = 'Invalid date format.';
        if (!preg_match('/^\d{2}:\d{2}$/', $old['showtime_time']))        $errors[] = 'Invalid time format.';

        if (empty($errors)) {
            $timeDisplay = date('g:i A', strtotime($old['showtime_time']));
            if ($isEdit) {
                $ok = ShowtimeRepo::updateTransactional(
                    $id,
                    $old['showtime_date'],
                    $timeDisplay,
                    $old['available_tickets'],
                    (bool)$old['is_active'],
                    $old['sort_order']
                );
                if ($ok) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Showtime updated.'];
                    header('Location: showtimes.php?movie_id=' . $old['movie_id']);
                    exit;
                }
                $errors[] = 'Failed to update showtime.';
            } else {
                $newId = ShowtimeRepo::createTransactional(
                    $old['movie_id'],
                    $old['showtime_date'],
                    $timeDisplay,
                    $old['available_tickets'],
                    $old['sort_order']
                );
                if ($newId) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Showtime created.'];
                    header('Location: showtimes.php?movie_id=' . $old['movie_id']);
                    exit;
                }
                $errors[] = 'Failed to create showtime.';
            }
        }
    }
}

$csrf = $auth->generateCsrfToken();
?>
<div class="admin-page-header">
  <h1><?= $isEdit ? 'Edit Showtime' : 'New Showtime' ?></h1>
  <a class="btn btn-sm btn-secondary" href="showtimes.php?movie_id=<?= $old['movie_id'] ?>">&#8592; Back</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <ul style="margin:0;padding-left:1.25rem;">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($isLegacy): ?>
  <div class="alert alert-info" role="alert">
    This is a legacy showtime (label/times format). Editing here will convert it to the new date+time format.
  </div>
<?php endif; ?>

<form method="post" class="admin-form" data-prevent-double="1" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

  <div class="form-group">
    <label for="movie_id">Movie <span class="required">*</span></label>
    <select name="movie_id" id="movie_id" required>
      <option value="">-- Select a movie --</option>
      <?php foreach ($movies as $m): ?>
        <option value="<?= (int)$m['id'] ?>" <?= (int)$old['movie_id'] === (int)$m['id'] ? 'selected' : '' ?>>
          <?= e((string)$m['title']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="showtime_date">Date <span class="required">*</span></label>
      <input type="date" name="showtime_date" id="showtime_date"
             value="<?= e($old['showtime_date']) ?>" required>
    </div>
    <div class="form-group">
      <label for="showtime_time">Time <span class="required">*</span></label>
      <input type="time" name="showtime_time" id="showtime_time"
             value="<?= e($old['showtime_time']) ?>" required>
    </div>
    <div class="form-group" id="end-time-group">
      <label for="manual_end_time">Ends (manual)</label>
      <input type="time" id="manual_end_time">
      <small class="form-help">Set a duration on this movie to calculate this automatically.</small>
    </div>
    <div class="form-group" id="end-time-calculated" style="display:none;">
      <label>Ends</label>
      <div class="end-time-readout" id="end-time-readout" style="padding:0.55rem 0; font-weight:600;"></div>
      <small class="form-help">Calculated from the movie's duration.</small>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="available_tickets">Available Tickets</label>
      <input type="number" name="available_tickets" id="available_tickets"
             min="0" value="<?= (int)$old['available_tickets'] ?>">
    </div>
    <div class="form-group">
      <label for="sort_order">Sort Order</label>
      <input type="number" name="sort_order" id="sort_order"
             step="1" value="<?= (int)$old['sort_order'] ?>">
    </div>
  </div>

  <div class="form-group">
    <label class="checkbox-label">
      <input type="checkbox" name="is_active" value="1" <?= $old['is_active'] ? 'checked' : '' ?>>
      Active (visible to customers)
    </label>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Showtime' ?></button>
    <a class="btn btn-outline" href="showtimes.php?movie_id=<?= $old['movie_id'] ?>">Cancel</a>
  </div>
</form>

<script>
(function () {
    var durations = <?= json_encode($movieDurations, JSON_FORCE_OBJECT) ?>;
    var movieSel  = document.getElementById('movie_id');
    var timeInput = document.getElementById('showtime_time');
    var manualGrp = document.getElementById('end-time-group');
    var calcGrp   = document.getElementById('end-time-calculated');
    var readout   = document.getElementById('end-time-readout');

    function formatTime(totalMinutes) {
        totalMinutes = ((totalMinutes % 1440) + 1440) % 1440;
        var h = Math.floor(totalMinutes / 60);
        var m = totalMinutes % 60;
        var suffix = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12;
        if (h12 === 0) h12 = 12;
        return h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + suffix;
    }

    function update() {
        var duration = durations[movieSel.value];
        if (!duration || !timeInput.value) {
            manualGrp.style.display = '';
            calcGrp.style.display   = 'none';
            return;
        }
        var parts = timeInput.value.split(':');
        var startMinutes = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
        readout.textContent = formatTime(startMinutes) + ' → Ends ' + formatTime(startMinutes + duration);
        manualGrp.style.display = 'none';
        calcGrp.style.display   = '';
    }

    movieSel.addEventListener('change', update);
    timeInput.addEventListener('input', update);
    update();
})();
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
