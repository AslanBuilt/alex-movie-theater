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
    'event_time'  => '',
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
            'event_time'  => (string)($row['event_time'] ?? ''),
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
        $old['description'] = trim((string)($_POST['description'] ?? ''));
        $old['event_date']  = trim((string)($_POST['event_date'] ?? ''));
        $old['event_time']  = trim((string)($_POST['event_time'] ?? ''));
        $old['badge']       = trim((string)($_POST['badge'] ?? ''));
        $old['image_path']  = trim((string)($_POST['image_path'] ?? ''));
        $old['status']      = (string)($_POST['status'] ?? 'upcoming');
        $old['sort_order']  = (int)($_POST['sort_order'] ?? 0);

        // Handle image upload — takes precedence over the manual path field.
        // Never trust $_FILES[...]['type'] (client-controlled); the real MIME
        // type below comes from finfo reading the file's actual bytes. Also
        // check the PHP-reported upload error first — a file rejected by
        // upload_max_filesize/post_max_size arrives with an empty tmp_name,
        // which would otherwise be silently ignored instead of surfacing a
        // real inline error to the admin.
        $uploadError = (int)($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = 'Image is too large. Maximum size is 2 MB.';
        } elseif ($uploadError !== UPLOAD_ERR_OK && $uploadError !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Image upload failed. Please try again.';
        } elseif (!empty($_FILES['image_file']['tmp_name'])) {
            $file       = $_FILES['image_file'];
            $allowedExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $maxBytes   = 2 * 1024 * 1024;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowedExt[$mime])) {
                $errors[] = 'Image must be a JPG, PNG, GIF, or WebP file.';
            } elseif ($file['size'] > $maxBytes) {
                $errors[] = 'Image must be under 2 MB.';
            } else {
                // Filename derived from a random token, not the client-supplied
                // name or its extension — avoids any path/extension trickery.
                $filename = bin2hex(random_bytes(8)) . '.' . $allowedExt[$mime];
                $destDir  = dirname(__DIR__) . '/uploads/events/';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                if (move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                    $old['image_path'] = 'uploads/events/' . $filename;
                } else {
                    $errors[] = 'Could not save the uploaded image. Check server permissions.';
                }
            }
        }

        if ($old['title'] === '' || mb_strlen($old['title']) > 255) {
            $errors[] = 'Title is required (max 255 chars).';
        }
        if ($old['description'] === '') {
            $errors[] = 'Description is required.';
        }
        if (!in_array($old['status'], $allowedStatuses, true)) {
            $errors[] = 'Invalid status value.';
        }
        if ($old['event_date'] === '') {
            $errors[] = 'Event date is required.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['event_date'])) {
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
                                event_time = :event_time,
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
                        ':event_time'  => $old['event_time'] !== '' ? $old['event_time'] : null,
                        ':badge'       => $old['badge'] !== '' ? $old['badge'] : null,
                        ':image_path'  => $old['image_path'] !== '' ? $old['image_path'] : null,
                        ':status'      => $old['status'],
                        ':sort_order'  => $old['sort_order'],
                        ':id'          => $id,
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Event updated.'];
                } else {
                    $sql = 'INSERT INTO events
                                (title, description, event_date, event_time, badge, image_path, status, sort_order, created_at, updated_at)
                            VALUES
                                (:title, :description, :event_date, :event_time, :badge, :image_path, :status, :sort_order, NOW(), NOW())';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':title'       => $old['title'],
                        ':description' => $old['description'] !== '' ? $old['description'] : null,
                        ':event_date'  => $old['event_date'] !== '' ? $old['event_date'] : null,
                        ':event_time'  => $old['event_time'] !== '' ? $old['event_time'] : null,
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

/**
 * Resolve a stored event image_path to a displayable <img src>. New uploads
 * live under uploads/events/ (outside assets/), so they need url() rather
 * than posterUrl()'s assets/-prefixing behavior. Pasted absolute URLs still
 * resolve correctly via posterUrl().
 */
function resolveEventImageUrl(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path) === 1) {
        return $path;
    }
    if (strncasecmp($path, 'uploads/', 8) === 0) {
        return url($path);
    }
    return posterUrl($path);
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

<form method="post" class="admin-form" data-prevent-double="1" novalidate enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="form-group">
        <label for="title">Title *</label>
        <input type="text" name="title" id="title" maxlength="255" value="<?= e($old['title']) ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Description *</label>
        <textarea name="description" id="description" required><?= e($old['description']) ?></textarea>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="event_date">Event date *</label>
            <input type="date" name="event_date" id="event_date" value="<?= e($old['event_date']) ?>" required>
        </div>
        <div class="form-group">
            <label for="event_time">Event time</label>
            <input type="time" name="event_time" id="event_time" value="<?= e($old['event_time']) ?>">
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

    <?php $hasImage = (string)$old['image_path'] !== ''; ?>
    <div class="form-group">
        <label for="image_file">Image</label>
        <div class="admin-upload-zone" id="event-image-zone" data-upload-zone
             data-input-id="image_file" data-preview-id="image-preview"
             data-placeholder-id="image-placeholder" data-label-id="image-current-label"
             tabindex="0" role="button" aria-label="Upload image — drag and drop or click to choose a file">
            <input type="file" id="image_file" name="image_file"
                   accept="image/jpeg,image/png,image/gif,image/webp" hidden>
            <img id="image-preview" src="<?= $hasImage ? e(resolveEventImageUrl($old['image_path'])) : '' ?>"
                 alt="Current image" class="admin-upload-preview" style="<?= $hasImage ? '' : 'display:none;' ?>">
            <div id="image-placeholder" class="admin-upload-placeholder" style="<?= $hasImage ? 'display:none;' : '' ?>">
                <strong>Drag &amp; drop an image here</strong><br>
                <span>or click to browse — JPG, PNG, GIF, or WebP, max 2&nbsp;MB</span>
            </div>
        </div>
        <div id="image-current-label" class="form-help" style="<?= $hasImage ? '' : 'display:none;' ?>">
            Current image — click above to change it.
        </div>
    </div>

    <div class="form-group">
        <label for="image_path">Image path (advanced / manual override)</label>
        <input type="text" name="image_path" id="image_path" maxlength="500" value="<?= e($old['image_path']) ?>">
        <small class="form-help">Auto-filled when you upload above. Only edit this directly if you need to point at an existing file or external URL.</small>
    </div>

    <style>
        .admin-upload-zone {
            border: 2px dashed var(--border);
            border-radius: 6px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }
        .admin-upload-zone:focus-visible,
        .admin-upload-zone.is-dragover {
            border-color: var(--crimson, #a3123a);
            background: rgba(163, 18, 58, 0.06);
        }
        .admin-upload-zone .admin-upload-preview {
            max-height: 140px;
            max-width: 100%;
            border-radius: 4px;
            display: block;
            margin: 0 auto 0.5rem;
            object-fit: cover;
        }
        .admin-upload-zone .admin-upload-placeholder {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.5;
        }
    </style>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create event' ?></button>
        <a class="btn btn-outline" href="events.php">Cancel</a>
    </div>
</form>

<script src="../assets/js/admin-upload.js" defer></script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
