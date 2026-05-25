<?php
declare(strict_types=1);

$pageTitle = 'Edit Showtime';
require_once __DIR__ . '/includes/admin-header.php';

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$old = [
    'movie_id'      => 0,
    'label'         => '',
    'times'         => '',
    'showtime_date' => '',
    'sort_order'    => 0,
];
$errors = [];
$movies = [];

try {
    $movies = $db->query('SELECT id, title FROM movies ORDER BY title ASC')
                 ->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('showtime-edit movies load failed: ' . $e->getMessage());
}

if ($isEdit) {
    try {
        $stmt = $db->prepare('SELECT * FROM showtimes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Showtime not found.'];
            header('Location: showtimes.php');
            exit;
        }
        $old = [
            'movie_id'      => (int)$row['movie_id'],
            'label'         => (string)$row['label'],
            'times'         => (string)$row['times'],
            'showtime_date' => (string)($row['showtime_date'] ?? ''),
            'sort_order'    => (int)$row['sort_order'],
        ];
        $pageTitle = 'Edit Showtime';
    } catch (PDOException $e) {
        error_log('showtime-edit load failed: ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load showtime.'];
        header('Location: showtimes.php');
        exit;
    }
} else {
    $pageTitle = 'New Showtime';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['movie_id']      = (int)($_POST['movie_id'] ?? 0);
        $old['label']         = trim((string)($_POST['label'] ?? ''));
        $old['times']         = trim((string)($_POST['times'] ?? ''));
        $old['showtime_date'] = trim((string)($_POST['showtime_date'] ?? ''));
        $old['sort_order']    = (int)($_POST['sort_order'] ?? 0);

        if ($old['movie_id'] <= 0) {
            $errors[] = 'Please select a movie.';
        } else {
            try {
                $check = $db->prepare('SELECT 1 FROM movies WHERE id = :id');
                $check->execute([':id' => $old['movie_id']]);
                if (!$check->fetchColumn()) {
                    $errors[] = 'Selected movie does not exist.';
                }
            } catch (PDOException $e) {
                error_log('showtime-edit movie check failed: ' . $e->getMessage());
                $errors[] = 'Could not validate movie selection.';
            }
        }
        if ($old['label'] === '' || mb_strlen($old['label']) > 255) {
            $errors[] = 'Label is required (max 255 chars).';
        }
        if ($old['times'] === '') {
            $errors[] = 'Times is required.';
        }
        if ($old['showtime_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['showtime_date'])) {
            $errors[] = 'Showtime date must be in YYYY-MM-DD format.';
        }

        if (count($errors) === 0) {
            try {
                if ($isEdit) {
                    $sql = 'UPDATE showtimes SET
                                movie_id = :movie_id,
                                label = :label,
                                times = :times,
                                showtime_date = :showtime_date,
                                sort_order = :sort_order
                            WHERE id = :id';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':movie_id'      => $old['movie_id'],
                        ':label'         => $old['label'],
                        ':times'         => $old['times'],
                        ':showtime_date' => $old['showtime_date'] !== '' ? $old['showtime_date'] : null,
                        ':sort_order'    => $old['sort_order'],
                        ':id'            => $id,
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Showtime updated.'];
                } else {
                    $sql = 'INSERT INTO showtimes
                                (movie_id, label, times, showtime_date, sort_order, created_at)
                            VALUES
                                (:movie_id, :label, :times, :showtime_date, :sort_order, NOW())';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':movie_id'      => $old['movie_id'],
                        ':label'         => $old['label'],
                        ':times'         => $old['times'],
                        ':showtime_date' => $old['showtime_date'] !== '' ? $old['showtime_date'] : null,
                        ':sort_order'    => $old['sort_order'],
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Showtime created.'];
                }
                header('Location: showtimes.php');
                exit;
            } catch (PDOException $e) {
                error_log('showtime-edit save failed: ' . $e->getMessage());
                $errors[] = 'Could not save the showtime. Please try again.';
            }
        }
    }
}

$csrf = $auth->generateCsrfToken();
?>
<div class="admin-page-header">
    <h1><?= $isEdit ? 'Edit showtime' : 'New showtime' ?></h1>
    <div class="admin-page-actions">
        <a class="btn btn-outline btn-sm" href="showtimes.php">Back to list</a>
    </div>
</div>

<?php if (count($errors) > 0) : ?>
    <div class="alert alert-error" role="alert">
        <ul style="margin:0;padding-left:1.25rem;">
            <?php foreach ($errors as $err) : ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="admin-form" data-prevent-double="1" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="form-group">
        <label for="movie_id">Movie *</label>
        <select name="movie_id" id="movie_id" required>
            <option value="">-- Select a movie --</option>
            <?php foreach ($movies as $m) : ?>
                <option value="<?= (int)$m['id'] ?>" <?= (int)$old['movie_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                    <?= e((string)$m['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="label">Label *</label>
        <input type="text" name="label" id="label" maxlength="255" value="<?= e($old['label']) ?>" required>
        <small class="form-help">e.g. "Friday", "Weekend Matinee".</small>
    </div>

    <div class="form-group">
        <label for="times">Times *</label>
        <input type="text" name="times" id="times" value="<?= e($old['times']) ?>" required>
        <small class="form-help">e.g. "7:00 PM, 9:30 PM"</small>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="showtime_date">Showtime date</label>
            <input type="date" name="showtime_date" id="showtime_date" value="<?= e($old['showtime_date']) ?>">
            <small class="form-help">Optional. Leave blank for recurring showtimes.</small>
        </div>
        <div class="form-group">
            <label for="sort_order">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" step="1" value="<?= (int)$old['sort_order'] ?>">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create showtime' ?></button>
        <a class="btn btn-outline" href="showtimes.php">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
