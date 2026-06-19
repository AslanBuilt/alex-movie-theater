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
    $movies = $db->query('SELECT id, title FROM movies ORDER BY title ASC')
                 ->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('showtime-edit movies load: ' . $e->getMessage());
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

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
