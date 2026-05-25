<?php
declare(strict_types=1);

$pageTitle = 'Edit Event';
require_once __DIR__ . '/includes/admin-header.php';

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$old = [
    'title'       => '',
    'description' => '',
    'event_date'  => '',
    'badge'       => '',
    'image_path'  => '',
    'status'      => 'upcoming',
    'sort_order'  => 0,
];
$errors = [];

$allowedStatuses = ['upcoming', 'past', 'tba'];

if ($isEdit) {
    try {
        $stmt = $db->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Event not found.'];
            header('Location: events.php');
            exit;
        }
        $old = [
            'title'       => (string)$row['title'],
            'description' => (string)($row['description'] ?? ''),
            'event_date'  => (string)($row['event_date'] ?? ''),
            'badge'       => (string)($row['badge'] ?? ''),
            'image_path'  => (string)($row['image_path'] ?? ''),
            'status'      => (string)$row['status'],
            'sort_order'  => (int)$row['sort_order'],
        ];
        $pageTitle = 'Edit Event — ' . $old['title'];
    } catch (PDOException $e) {
        error_log('event-edit load failed: ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load event.'];
        header('Location: events.php');
        exit;
    }
} else {
    $pageTitle = 'New Event';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['title']       = trim((string)($_POST['title'] ?? ''));
        $old['description'] = (string)($_POST['description'] ?? '');
        $old['event_date']  = trim((string)($_POST['event_date'] ?? ''));
        $old['badge']       = trim((string)($_POST['badge'] ?? ''));
        $old['image_path']  = trim((string)($_POST['image_path'] ?? ''));
        $old['status']      = (string)($_POST['status'] ?? 'upcoming');
        $old['sort_order']  = (int)($_POST['sort_order'] ?? 0);

        if ($old['title'] === '' || mb_strlen($old['title']) > 255) {
            $errors[] = 'Title is required (max 255 chars).';
        }
        if (!in_array($old['status'], $allowedStatuses, true)) {
            $errors[] = 'Invalid status value.';
        }
        if ($old['event_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['event_date'])) {
            $errors[] = 'Event date must be in YYYY-MM-DD format.';
        }
        if (mb_strlen($old['badge']) > 100) {
            $errors[] = 'Badge must be 100 characters or fewer.';
        }
        if (mb_strlen($old['image_path']) > 500) {
            $errors[] = 'Image path must be 500 characters or fewer.';
        }

        if (count($errors) === 0) {
            try {
                if ($isEdit) {
                    $sql = 'UPDATE events SET
                                title = :title,
                                description = :description,
                                event_date = :event_date,
                                badge = :badge,
                                image_path = :image_path,
                                status = :status,
                                sort_order = :sort_order,
                                updated_at = NOW()
                            WHERE id = :id';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':title'       => $old['title'],
                        ':description' => $old['description'] !== '' ? $old['description'] : null,
                        ':event_date'  => $old['event_date'] !== '' ? $old['event_date'] : null,
                        ':badge'       => $old['badge'] !== '' ? $old['badge'] : null,
                        ':image_path'  => $old['image_path'] !== '' ? $old['image_path'] : null,
                        ':status'      => $old['status'],
                        ':sort_order'  => $old['sort_order'],
                        ':id'          => $id,
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Event updated.'];
                } else {
                    $sql = 'INSERT INTO events
                                (title, description, event_date, badge, image_path, status, sort_order, created_at, updated_at)
                            VALUES
                                (:title, :description, :event_date, :badge, :image_path, :status, :sort_order, NOW(), NOW())';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':title'       => $old['title'],
                        ':description' => $old['description'] !== '' ? $old['description'] : null,
                        ':event_date'  => $old['event_date'] !== '' ? $old['event_date'] : null,
                        ':badge'       => $old['badge'] !== '' ? $old['badge'] : null,
                        ':image_path'  => $old['image_path'] !== '' ? $old['image_path'] : null,
                        ':status'      => $old['status'],
                        ':sort_order'  => $old['sort_order'],
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Event created.'];
                }
                header('Location: events.php');
                exit;
            } catch (PDOException $e) {
                error_log('event-edit save failed: ' . $e->getMessage());
                $errors[] = 'Could not save the event. Please try again.';
            }
        }
    }
}

$csrf = $auth->generateCsrfToken();
?>
<div class="admin-page-header">
    <h1><?= $isEdit ? 'Edit event' : 'New event' ?></h1>
    <div class="admin-page-actions">
        <a class="btn btn-outline btn-sm" href="events.php">Back to list</a>
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
        <label for="title">Title *</label>
        <input type="text" name="title" id="title" maxlength="255" value="<?= e($old['title']) ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description"><?= e($old['description']) ?></textarea>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="event_date">Event date</label>
            <input type="date" name="event_date" id="event_date" value="<?= e($old['event_date']) ?>">
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select name="status" id="status">
                <?php foreach ($allowedStatuses as $val) : ?>
                    <option value="<?= e($val) ?>" <?= $old['status'] === $val ? 'selected' : '' ?>><?= e($val) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="sort_order">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" step="1" value="<?= (int)$old['sort_order'] ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="badge">Badge</label>
        <input type="text" name="badge" id="badge" maxlength="100" value="<?= e($old['badge']) ?>">
        <small class="form-help">Short tag shown on the event card (e.g. "Family Night").</small>
    </div>

    <div class="form-group">
        <label for="image_path">Image path</label>
        <input type="text" name="image_path" id="image_path" maxlength="500" value="<?= e($old['image_path']) ?>">
        <small class="form-help">Relative path or URL to the event image.</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create event' ?></button>
        <a class="btn btn-outline" href="events.php">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
