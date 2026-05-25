<?php
declare(strict_types=1);

$pageTitle = 'Edit Senior Showing';
require_once __DIR__ . '/includes/admin-header.php';

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$old = [
    'movie_title'  => '',
    'showing_date' => '',
    'showing_time' => '',
    'notes'        => '',
    'status'       => 'upcoming',
];
$errors = [];

$allowedStatuses = ['upcoming', 'past', 'tba'];

if ($isEdit) {
    try {
        $stmt = $db->prepare('SELECT * FROM senior_showings WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Senior showing not found.'];
            header('Location: senior-showings.php');
            exit;
        }
        $old = [
            'movie_title'  => (string)$row['movie_title'],
            'showing_date' => (string)$row['showing_date'],
            'showing_time' => (string)$row['showing_time'],
            'notes'        => (string)($row['notes'] ?? ''),
            'status'       => (string)$row['status'],
        ];
        $pageTitle = 'Edit Senior Showing — ' . $old['movie_title'];
    } catch (PDOException $e) {
        error_log('senior-showing-edit load failed: ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load senior showing.'];
        header('Location: senior-showings.php');
        exit;
    }
} else {
    $pageTitle = 'New Senior Showing';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['movie_title']  = trim((string)($_POST['movie_title'] ?? ''));
        $old['showing_date'] = trim((string)($_POST['showing_date'] ?? ''));
        $old['showing_time'] = trim((string)($_POST['showing_time'] ?? ''));
        $old['notes']        = (string)($_POST['notes'] ?? '');
        $old['status']       = (string)($_POST['status'] ?? 'upcoming');

        if ($old['movie_title'] === '' || mb_strlen($old['movie_title']) > 255) {
            $errors[] = 'Movie title is required (max 255 chars).';
        }
        if ($old['showing_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['showing_date'])) {
            $errors[] = 'Showing date is required (YYYY-MM-DD).';
        }
        if ($old['showing_time'] === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $old['showing_time'])) {
            $errors[] = 'Showing time is required (HH:MM).';
        }
        if (!in_array($old['status'], $allowedStatuses, true)) {
            $errors[] = 'Invalid status value.';
        }

        if (count($errors) === 0) {
            try {
                if ($isEdit) {
                    $sql = 'UPDATE senior_showings SET
                                movie_title = :movie_title,
                                showing_date = :showing_date,
                                showing_time = :showing_time,
                                notes = :notes,
                                status = :status
                            WHERE id = :id';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':movie_title'  => $old['movie_title'],
                        ':showing_date' => $old['showing_date'],
                        ':showing_time' => $old['showing_time'],
                        ':notes'        => $old['notes'] !== '' ? $old['notes'] : null,
                        ':status'       => $old['status'],
                        ':id'           => $id,
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Senior showing updated.'];
                } else {
                    $sql = 'INSERT INTO senior_showings
                                (movie_title, showing_date, showing_time, notes, status, created_at)
                            VALUES
                                (:movie_title, :showing_date, :showing_time, :notes, :status, NOW())';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':movie_title'  => $old['movie_title'],
                        ':showing_date' => $old['showing_date'],
                        ':showing_time' => $old['showing_time'],
                        ':notes'        => $old['notes'] !== '' ? $old['notes'] : null,
                        ':status'       => $old['status'],
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Senior showing created.'];
                }
                header('Location: senior-showings.php');
                exit;
            } catch (PDOException $e) {
                error_log('senior-showing-edit save failed: ' . $e->getMessage());
                $errors[] = 'Could not save the senior showing. Please try again.';
            }
        }
    }
}

$csrf = $auth->generateCsrfToken();
?>
<div class="admin-page-header">
    <h1><?= $isEdit ? 'Edit senior showing' : 'New senior showing' ?></h1>
    <div class="admin-page-actions">
        <a class="btn btn-outline btn-sm" href="senior-showings.php">Back to list</a>
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
        <label for="movie_title">Movie title *</label>
        <input type="text" name="movie_title" id="movie_title" maxlength="255" value="<?= e($old['movie_title']) ?>" required>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="showing_date">Showing date *</label>
            <input type="date" name="showing_date" id="showing_date" value="<?= e($old['showing_date']) ?>" required>
        </div>
        <div class="form-group">
            <label for="showing_time">Showing time *</label>
            <input type="time" name="showing_time" id="showing_time" value="<?= e($old['showing_time']) ?>" required>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select name="status" id="status">
                <?php foreach ($allowedStatuses as $val) : ?>
                    <option value="<?= e($val) ?>" <?= $old['status'] === $val ? 'selected' : '' ?>><?= e($val) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes"><?= e($old['notes']) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create showing' ?></button>
        <a class="btn btn-outline" href="senior-showings.php">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
