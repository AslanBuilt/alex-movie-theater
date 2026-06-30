<?php
declare(strict_types=1);

$pageTitle = 'Edit Movie';
require_once __DIR__ . '/includes/admin-header.php';

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$old = [
    'title'        => '',
    'rating'       => '',
    'screen'       => 'either',
    'poster_path'  => '',
    'description'  => '',
    'status'       => 'now_showing',
    'online_only'  => 0,
    'sort_order'   => 0,
];
$errors = [];

$allowedScreens  = ['large', 'small', 'either'];
$allowedStatuses = ['now_showing', 'coming_soon', 'archived'];

if ($isEdit) {
    try {
        $stmt = $db->prepare('SELECT * FROM movies WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Movie not found.'];
            header('Location: movies.php');
            exit;
        }
        $old = [
            'title'       => (string)$row['title'],
            'rating'      => (string)($row['rating'] ?? ''),
            'screen'      => (string)$row['screen'],
            'poster_path' => (string)($row['poster_path'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'status'      => (string)$row['status'],
            'online_only' => (int)$row['online_only'],
            'sort_order'  => (int)$row['sort_order'],
        ];
        $pageTitle = 'Edit Movie — ' . $old['title'];
    } catch (PDOException $e) {
        error_log('movie-edit load failed: ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load movie.'];
        header('Location: movies.php');
        exit;
    }
} else {
    $pageTitle = 'New Movie';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!$auth->validateCsrf($token)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['title']       = trim((string)($_POST['title'] ?? ''));
        $old['rating']      = trim((string)($_POST['rating'] ?? ''));
        $old['screen']      = (string)($_POST['screen'] ?? 'either');
        $old['poster_path'] = trim((string)($_POST['poster_path'] ?? ''));
        $old['description'] = (string)($_POST['description'] ?? '');
        $old['status']      = (string)($_POST['status'] ?? 'now_showing');
        $old['online_only'] = isset($_POST['online_only']) ? 1 : 0;
        $old['sort_order']  = (int)($_POST['sort_order'] ?? 0);

        // Handle poster image upload
        if (!empty($_FILES['poster_file']['tmp_name'])) {
            $file     = $_FILES['poster_file'];
            $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxBytes = 8 * 1024 * 1024;

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mime     = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed, true)) {
                $errors[] = 'Poster must be a JPG, PNG, GIF, or WebP image.';
            } elseif ($file['size'] > $maxBytes) {
                $errors[] = 'Poster image must be under 8 MB.';
            } else {
                $ext      = pathinfo((string)$file['name'], PATHINFO_EXTENSION);
                $safeName = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(' ', '-', $old['title'])));
                $safeName = $safeName ?: 'poster';
                $filename = $safeName . '-' . time() . '.' . strtolower($ext);
                $destDir  = dirname(__DIR__) . '/assets/images/posters/';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                $dest = $destDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $old['poster_path'] = 'images/posters/' . $filename;
                } else {
                    $errors[] = 'Could not save the uploaded image. Check server permissions.';
                }
            }
        }

        if ($old['title'] === '') {
            $errors[] = 'Title is required.';
        } elseif (mb_strlen($old['title']) > 255) {
            $errors[] = 'Title must be 255 characters or fewer.';
        }
        if (mb_strlen($old['rating']) > 50) {
            $errors[] = 'Rating must be 50 characters or fewer.';
        }
        if (!in_array($old['screen'], $allowedScreens, true)) {
            $errors[] = 'Invalid screen value.';
        }
        if (mb_strlen($old['poster_path']) > 500) {
            $errors[] = 'Poster path must be 500 characters or fewer.';
        }
        if (!in_array($old['status'], $allowedStatuses, true)) {
            $errors[] = 'Invalid status value.';
        }

        if (count($errors) === 0) {
            try {
                if ($isEdit) {
                    $sql = 'UPDATE movies SET
                                title = :title,
                                rating = :rating,
                                screen = :screen,
                                poster_path = :poster_path,
                                description = :description,
                                status = :status,
                                online_only = :online_only,
                                sort_order = :sort_order,
                                updated_at = NOW()
                            WHERE id = :id';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':title'       => $old['title'],
                        ':rating'      => $old['rating'] !== '' ? $old['rating'] : null,
                        ':screen'      => $old['screen'],
                        ':poster_path' => $old['poster_path'] !== '' ? $old['poster_path'] : null,
                        ':description' => $old['description'] !== '' ? $old['description'] : null,
                        ':status'      => $old['status'],
                        ':online_only' => $old['online_only'],
                        ':sort_order'  => $old['sort_order'],
                        ':id'          => $id,
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Movie updated.'];
                } else {
                    $sql = 'INSERT INTO movies
                                (title, rating, screen, poster_path, description, status, online_only, sort_order, created_at, updated_at)
                            VALUES
                                (:title, :rating, :screen, :poster_path, :description, :status, :online_only, :sort_order, NOW(), NOW())';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':title'       => $old['title'],
                        ':rating'      => $old['rating'] !== '' ? $old['rating'] : null,
                        ':screen'      => $old['screen'],
                        ':poster_path' => $old['poster_path'] !== '' ? $old['poster_path'] : null,
                        ':description' => $old['description'] !== '' ? $old['description'] : null,
                        ':status'      => $old['status'],
                        ':online_only' => $old['online_only'],
                        ':sort_order'  => $old['sort_order'],
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Movie created.'];
                }
                header('Location: movies.php');
                exit;
            } catch (PDOException $e) {
                error_log('movie-edit save failed: ' . $e->getMessage());
                $errors[] = 'Could not save the movie. Please try again.';
            }
        }
    }
}

$csrf = $auth->generateCsrfToken();
?>
<div class="admin-page-header">
    <h1><?= $isEdit ? 'Edit movie' : 'New movie' ?></h1>
    <div class="admin-page-actions">
        <a class="btn btn-outline btn-sm" href="movies.php">Back to list</a>
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
        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <input type="text" name="title" id="title" maxlength="255" value="<?= e($old['title']) ?>" required style="flex:1; min-width:200px;">
            <button type="button" class="btn btn-outline btn-sm" id="google-poster-btn" title="Open Google Images to find a poster for this movie" onclick="
                var t = document.getElementById('title').value.trim();
                if (!t) { alert('Enter a movie title first.'); return; }
                window.open('https://www.google.com/search?q=' + encodeURIComponent(t + ' movie poster') + '&tbm=isch', '_blank');
            ">Find Poster on Google</button>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="rating">Rating</label>
            <input type="text" name="rating" id="rating" maxlength="50" value="<?= e($old['rating']) ?>">
            <small class="form-help">e.g. PG-13, R</small>
        </div>
        <div class="form-group">
            <label for="screen">Screen</label>
            <select name="screen" id="screen">
                <?php foreach ($allowedScreens as $val) : ?>
                    <option value="<?= e($val) ?>" <?= $old['screen'] === $val ? 'selected' : '' ?>><?= e(ucfirst($val)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select name="status" id="status">
                <?php foreach ($allowedStatuses as $val) : ?>
                    <option value="<?= e($val) ?>" <?= $old['status'] === $val ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $val)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="poster_file">Upload Poster Image</label>
        <input type="file" name="poster_file" id="poster_file" accept="image/jpeg,image/png,image/gif,image/webp" style="color:var(--text-primary);">
        <small class="form-help">Upload JPG, PNG, or WebP (max 8 MB). Overwrites the path below if provided.</small>
        <?php if ($old['poster_path'] !== ''): ?>
            <div style="margin-top:0.6rem; display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                <img src="<?= e(posterUrl($old['poster_path'])) ?>" alt="Current poster" id="poster-preview"
                     style="height:80px; width:56px; object-fit:cover; border-radius:3px; border:1px solid var(--border);">
                <span style="font-size:0.8rem; color:var(--text-secondary);">Current poster</span>
            </div>
        <?php else: ?>
            <img id="poster-preview" src="" alt="" style="display:none; height:80px; width:56px; object-fit:cover; border-radius:3px; border:1px solid var(--border); margin-top:0.6rem;">
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="poster_path">Poster Path (manual)</label>
        <input type="text" name="poster_path" id="poster_path" maxlength="500" value="<?= e($old['poster_path']) ?>">
        <small class="form-help">Either a relative path (e.g. <code>images/posters/mymovie.jpg</code>) or a full image URL (e.g. from "Find Poster on Google" — right-click the image and copy its address). Leave blank if uploading above.</small>
    </div>

    <div class="form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description"><?= e($old['description']) ?></textarea>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="sort_order">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" step="1" value="<?= (int)$old['sort_order'] ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Online only</label>
            <div class="checkbox-row">
                <input type="checkbox" name="online_only" id="online_only" value="1" <?= (int)$old['online_only'] === 1 ? 'checked' : '' ?>>
                <label for="online_only" style="margin-bottom:0;">Show only in online listings</label>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create movie' ?></button>
        <a class="btn btn-outline" href="movies.php">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
