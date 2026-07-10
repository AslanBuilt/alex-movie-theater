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
    'duration_minutes' => '',
    'poster_path'  => '',
    'description'  => '',
    'status'       => 'now_showing',
    'online_only'  => 0,
    'sort_order'   => 0,
];
$errors = [];

// Raw showtime-block values from a failed submission, kept so the "quick-add
// showtimes" section can redisplay exactly what the admin typed instead of
// silently dropping it when an unrelated field (e.g. title) fails validation.
$submittedBlocks = [];

$allowedScreens  = ['large', 'small', 'either'];
$allowedStatuses = ['now_showing', 'coming_soon', 'archived'];
const MOVIE_EDIT_DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/**
 * Renders one showtime-block <div>, matching the markup/classes JS's
 * addBlock() produces client-side for a freshly-added block, but filled with
 * already-submitted values — used to redisplay blocks after a validation
 * error round-trip.
 *
 * @param array{days?:int[],start_time?:string,tickets?:int,date_from?:string,date_to?:string} $values
 */
function movie_edit_render_showtime_block(int $index, array $values): void
{
    $days      = array_map('intval', (array)($values['days'] ?? []));
    $startTime = (string)($values['start_time'] ?? '');
    $tickets   = (int)($values['tickets'] ?? 50);
    $dateFrom  = (string)($values['date_from'] ?? '');
    $dateTo    = (string)($values['date_to'] ?? '');
    ?>
    <div class="showtime-block" data-index="<?= $index ?>" style="border:1px solid var(--border); border-radius:6px; padding:1rem; margin-bottom:1rem;">
        <div class="form-group">
            <label>Days of the week</label>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                <?php foreach (MOVIE_EDIT_DOW as $i => $name) : ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="showtime_blocks[<?= $index ?>][days][]" value="<?= $i ?>" <?= in_array($i, $days, true) ? 'checked' : '' ?>>
                        <?= e($name) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Start time</label>
                <input type="time" name="showtime_blocks[<?= $index ?>][start_time]" class="st-start-time" value="<?= e($startTime) ?>">
            </div>
            <div class="form-group">
                <label>Available tickets</label>
                <input type="number" name="showtime_blocks[<?= $index ?>][tickets]" min="0" value="<?= $tickets ?>" class="st-tickets">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>From date</label>
                <input type="date" name="showtime_blocks[<?= $index ?>][date_from]" class="st-date-from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="form-group">
                <label>To date</label>
                <input type="date" name="showtime_blocks[<?= $index ?>][date_to]" class="st-date-to" value="<?= e($dateTo) ?>">
            </div>
        </div>
        <p class="st-preview" style="font-size:0.85rem; color:var(--text-secondary); margin:0.5rem 0 0;"></p>
        <button type="button" class="btn btn-outline btn-sm st-remove-block" style="margin-top:0.6rem;">Remove this block</button>
    </div>
    <?php
}

/**
 * Builds the YYYY-MM-DD list for every date in [start,end] (inclusive) whose
 * weekday is in $days (0=Sun..6=Sat). Same algorithm as
 * showtime-scheduler.php's sched_generate_dates() — duplicated locally
 * because that file renders its own full admin page and isn't a reusable
 * library, but the date-range/weekday-filter logic is deliberately kept in
 * sync with it so both features generate identical dates for identical input.
 *
 * @param int[] $days
 * @return string[]
 */
function movie_edit_generate_dates(string $start, string $end, array $days): array
{
    try {
        $cur  = new DateTime($start);
        $stop = new DateTime($end);
    } catch (\Throwable $e) {
        return [];
    }
    if ($cur > $stop || $cur->diff($stop)->days > 366) {
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

/**
 * True when GD on this server can both read the given source MIME type and
 * write WebP output. Checked before attempting a conversion so a missing GD
 * build/format support surfaces as a specific inline error instead of a
 * generic "conversion failed" message or a fatal call to an undefined
 * function.
 */
function movie_edit_webp_conversion_supported(string $mime): bool
{
    if (!function_exists('imagewebp')) {
        return false;
    }
    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg'),
        'image/png'  => function_exists('imagecreatefrompng'),
        'image/webp' => function_exists('imagecreatefromwebp'),
        'image/gif'  => function_exists('imagecreatefromgif'),
        default      => false,
    };
}

/**
 * Converts an uploaded jpeg/png/webp/gif image to WebP via GD and writes it
 * to $destPath. Returns false (never throws) on any failure — an unreadable
 * or corrupt source, or imagewebp() itself failing — and logs the specific
 * reason. Callers must not update poster_path when this returns false, so a
 * failed conversion never clobbers an existing good poster.
 */
function convertToWebP(string $tmpPath, string $destPath, string $mime): bool
{
    // move_uploaded_file() implicitly guarded against reading an arbitrary
    // path via is_uploaded_file(); GD's imagecreatefrom*() has no such
    // guard, so it's checked explicitly before ever touching $tmpPath.
    if (!is_uploaded_file($tmpPath)) {
        error_log("convertToWebP: refused non-upload path $tmpPath");
        return false;
    }
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmpPath),
        'image/png'  => @imagecreatefrompng($tmpPath),
        'image/webp' => @imagecreatefromwebp($tmpPath),
        'image/gif'  => @imagecreatefromgif($tmpPath),
        default      => false,
    };
    if ($img === false) {
        error_log("convertToWebP: unsupported or unreadable mime $mime for $tmpPath");
        return false;
    }
    if (in_array($mime, ['image/png', 'image/gif'], true)) {
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
    }
    $ok = imagewebp($img, $destPath, 85);
    imagedestroy($img);
    if (!$ok) {
        error_log("convertToWebP: imagewebp() write failed for $destPath (source mime $mime)");
    }
    return $ok;
}

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
            'duration_minutes' => $row['duration_minutes'] !== null ? (int)$row['duration_minutes'] : '',
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
    // Outer safety net: anything unexpected anywhere in this block (a bad
    // regex, a DateTime exception while generating showtime dates, a file
    // I/O surprise, etc.) is caught here, logged, and turned into a generic
    // inline error instead of an uncaught exception hitting the browser.
    try {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!$auth->validateCsrf($token)) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $old['title']       = trim((string)($_POST['title'] ?? ''));
            $old['rating']      = trim((string)($_POST['rating'] ?? ''));
            $old['screen']      = (string)($_POST['screen'] ?? 'either');

            $durHours = max(0, (int)($_POST['duration_hours'] ?? 0));
            $durMins  = max(0, min(59, (int)($_POST['duration_minutes_part'] ?? 0)));
            $totalDur = $durHours * 60 + $durMins;
            $old['duration_minutes'] = $totalDur > 0 ? $totalDur : '';

            $old['poster_path'] = trim((string)($_POST['poster_path'] ?? ''));
            $old['description'] = (string)($_POST['description'] ?? '');
            $old['status']      = (string)($_POST['status'] ?? 'now_showing');
            $old['online_only'] = isset($_POST['online_only']) ? 1 : 0;
            // sort_order is no longer a form field — it's set automatically on
            // create and otherwise left untouched by drag-to-reorder on
            // movies.php (see api/movies-reorder.php). $old['sort_order']
            // keeps whatever value was loaded from the DB (edit) or the 0
            // default (create); neither is used in the SQL below directly.

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
                } elseif (!movie_edit_webp_conversion_supported($mime)) {
                    // Missing GD / missing WebP support in this server's GD build —
                    // a specific, actionable message rather than a generic failure
                    // or a fatal call to an undefined image function.
                    $errors[] = "This server's image library can't convert this image to WebP right now. Please contact the site administrator.";
                    error_log("movie-edit poster upload: WebP conversion unsupported for mime $mime (imagewebp available: " . (function_exists('imagewebp') ? 'yes' : 'no') . ')');
                } else {
                    // Destination filename is always .webp, regardless of the
                    // uploaded file's original extension — the file is converted,
                    // not just renamed.
                    $safeName = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(' ', '-', $old['title'])));
                    $safeName = $safeName ?: 'poster';
                    $filename = $safeName . '-' . time() . '.webp';
                    $destDir  = dirname(__DIR__) . '/assets/images/posters/';
                    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                        $errors[] = 'Could not create the posters directory. Check server permissions.';
                    } else {
                        $dest = $destDir . $filename;
                        if (convertToWebP($file['tmp_name'], $dest, $mime)) {
                            // Only overwrite poster_path on success — a failed
                            // conversion must never clobber an existing good poster.
                            $old['poster_path'] = 'images/posters/' . $filename;
                        } else {
                            $errors[] = 'Could not convert the uploaded image to WebP. Please try a different file.';
                        }
                    }
                }
            }

            if ($old['title'] === '') {
                $errors[] = 'Title is required.';
            } elseif (mb_strlen($old['title']) > 255) {
                $errors[] = 'Title must be 255 characters or fewer.';
            }
            if (mb_strlen($old['rating']) > 10) {
                // Matches the movies.rating column, which is VARCHAR(10) — a
                // longer value would throw a PDOException on save under
                // strict SQL mode instead of failing this friendlier check.
                $errors[] = 'Rating must be 10 characters or fewer.';
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

            // Optional "quick-add showtimes" blocks — only meaningful on the
            // create path (the section isn't rendered at all when editing).
            $showtimeRows = [];
            if (!$isEdit && isset($_POST['showtime_blocks']) && is_array($_POST['showtime_blocks'])) {
                foreach ($_POST['showtime_blocks'] as $block) {
                    if (!is_array($block)) {
                        continue;
                    }
                    $days = array_values(array_unique(array_intersect(
                        array_map('intval', (array)($block['days'] ?? [])),
                        range(0, 6)
                    )));
                    $startTime = trim((string)($block['start_time'] ?? ''));
                    $dateFrom  = trim((string)($block['date_from'] ?? ''));
                    $dateTo    = trim((string)($block['date_to'] ?? ''));
                    $tickets   = max(0, (int)($block['tickets'] ?? 50));

                    $blockIsEmpty = empty($days) && $startTime === '' && $dateFrom === '' && $dateTo === '';
                    if ($blockIsEmpty) {
                        continue; // an unused leftover block — nothing to generate
                    }

                    // Keep the raw values regardless of validation outcome so
                    // the form can redisplay this block if saving fails for
                    // any reason (e.g. an unrelated field like title).
                    $submittedBlocks[] = [
                        'days'       => $days,
                        'start_time' => $startTime,
                        'tickets'    => $tickets,
                        'date_from'  => $dateFrom,
                        'date_to'    => $dateTo,
                    ];

                    if (empty($days)) {
                        $errors[] = 'Pick at least one day of the week for each showtime block.';
                        continue;
                    }
                    if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
                        $errors[] = 'Each showtime block needs a start time.';
                        continue;
                    }
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                        $errors[] = 'Each showtime block needs a valid date range.';
                        continue;
                    }
                    if ($dateFrom > $dateTo) {
                        $errors[] = "A showtime block's end date must be on or after its start date.";
                        continue;
                    }

                    $dates = movie_edit_generate_dates($dateFrom, $dateTo, $days);
                    if (empty($dates)) {
                        $errors[] = "One of the showtime blocks' date range and day selection produced no showtimes.";
                        continue;
                    }

                    $timeDisplay = date('g:i A', strtotime($startTime));
                    foreach ($dates as $d) {
                        $showtimeRows[] = [
                            'date'    => $d,
                            'time'    => $startTime,
                            'label'   => (new DateTime($d))->format('D, M j') . ' ' . $timeDisplay,
                            'times'   => $timeDisplay,
                            'tickets' => $tickets,
                        ];
                    }
                }
            }

            if (count($errors) === 0) {
                try {
                    $db->beginTransaction();

                    if ($isEdit) {
                        // sort_order intentionally omitted — it's now only
                        // ever changed via drag-to-reorder (api/movies-reorder.php).
                        $sql = 'UPDATE movies SET
                                    title = :title,
                                    rating = :rating,
                                    screen = :screen,
                                    duration_minutes = :duration_minutes,
                                    poster_path = :poster_path,
                                    description = :description,
                                    status = :status,
                                    online_only = :online_only,
                                    updated_at = NOW()
                                WHERE id = :id';
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':title'            => $old['title'],
                            // rating/poster_path are NOT NULL DEFAULT '' columns —
                            // store '' (not NULL) when empty so this insert/update
                            // can't throw under strict SQL modes.
                            ':rating'           => $old['rating'],
                            ':screen'           => $old['screen'],
                            ':duration_minutes' => $old['duration_minutes'] !== '' ? $old['duration_minutes'] : null,
                            ':poster_path'      => $old['poster_path'],
                            ':description'      => $old['description'] !== '' ? $old['description'] : null,
                            ':status'           => $old['status'],
                            ':online_only'      => $old['online_only'],
                            ':id'               => $id,
                        ]);

                        $db->commit();
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Movie updated.'];
                    } else {
                        // Computed as a separate SELECT (not a subquery inside the
                        // INSERT's VALUES list) — some MySQL versions reject a
                        // statement that reads from the same table it targets via
                        // error 1093 ("can't specify target table for update in
                        // FROM clause"), and this codebase has no way to test
                        // against the real server before shipping. A plain SELECT
                        // then a bound parameter has identical semantics (no
                        // unique constraint on sort_order, so the tiny race with a
                        // concurrent create is harmless) without that risk.
                        $nextSortRow = $db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM movies')->fetch(PDO::FETCH_ASSOC);
                        $nextSort    = $nextSortRow ? (int)$nextSortRow['n'] : 1;

                        $sql = 'INSERT INTO movies
                                    (title, rating, screen, duration_minutes, poster_path, description, status, online_only, sort_order, created_at, updated_at)
                                VALUES
                                    (:title, :rating, :screen, :duration_minutes, :poster_path, :description, :status, :online_only, :sort_order, NOW(), NOW())';
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':title'            => $old['title'],
                            ':rating'           => $old['rating'],
                            ':screen'           => $old['screen'],
                            ':duration_minutes' => $old['duration_minutes'] !== '' ? $old['duration_minutes'] : null,
                            ':poster_path'      => $old['poster_path'],
                            ':description'      => $old['description'] !== '' ? $old['description'] : null,
                            ':status'           => $old['status'],
                            ':online_only'      => $old['online_only'],
                            ':sort_order'       => $nextSort,
                        ]);
                        $newMovieId = (int)$db->lastInsertId();

                        if (!empty($showtimeRows)) {
                            $insShow = $db->prepare(
                                'INSERT INTO showtimes
                                    (movie_id, label, times, showtime_date, showtime_time, available_tickets, tickets_sold, is_active, sort_order)
                                 VALUES
                                    (:movie_id, :label, :times, :date, :time, :avail, 0, 1, 0)'
                            );
                            foreach ($showtimeRows as $st) {
                                $insShow->execute([
                                    ':movie_id' => $newMovieId,
                                    ':label'    => $st['label'],
                                    ':times'    => $st['times'],
                                    ':date'     => $st['date'],
                                    ':time'     => $st['time'],
                                    ':avail'    => $st['tickets'],
                                ]);
                            }
                        }

                        $db->commit();
                        $count = count($showtimeRows);
                        $_SESSION['flash'] = [
                            'type'    => 'success',
                            'message' => $count > 0 ? "Movie created with $count showtime(s)." : 'Movie created.',
                        ];
                    }
                    header('Location: movies.php');
                    exit;
                } catch (\Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('movie-edit save failed: ' . $e->getMessage());
                    $errors[] = 'Could not save the movie. Please try again.';
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('movie-edit POST handling failed: ' . $e->getMessage());
        $errors[] = 'An unexpected error occurred while processing your submission. Please try again.';
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
            <?php foreach (array_unique($errors) as $err) : ?>
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
            <input type="text" name="rating" id="rating" maxlength="10" value="<?= e($old['rating']) ?>">
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
        <label for="duration_hours">Duration</label>
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <input type="number" name="duration_hours" id="duration_hours" min="0" max="9" style="width:5rem;"
                   value="<?= $old['duration_minutes'] !== '' ? intdiv((int)$old['duration_minutes'], 60) : '' ?>"> <span>hr</span>
            <input type="number" name="duration_minutes_part" id="duration_minutes_part" min="0" max="59" style="width:5rem;"
                   value="<?= $old['duration_minutes'] !== '' ? ((int)$old['duration_minutes'] % 60) : '' ?>"> <span>min</span>
        </div>
        <small class="form-help">Set a duration to enable automatic end-time calculation for showtimes.</small>
    </div>

    <?php $hasPoster = $old['poster_path'] !== ''; ?>
    <div class="form-group">
        <label for="poster_file">Upload Poster Image</label>
        <div class="admin-upload-zone" id="poster-image-zone" data-upload-zone
             data-input-id="poster_file" data-preview-id="poster-preview"
             data-placeholder-id="poster-placeholder" data-label-id="poster-current-label"
             tabindex="0" role="button" aria-label="Upload poster image — drag and drop or click to choose a file">
            <input type="file" id="poster_file" name="poster_file"
                   accept="image/jpeg,image/png,image/gif,image/webp" hidden>
            <img id="poster-preview" src="<?= $hasPoster ? e(posterUrl($old['poster_path'])) : '' ?>"
                 alt="Current poster" class="admin-upload-preview" style="<?= $hasPoster ? '' : 'display:none;' ?>">
            <div id="poster-placeholder" class="admin-upload-placeholder" style="<?= $hasPoster ? 'display:none;' : '' ?>">
                <strong>Drag &amp; drop a poster image here</strong><br>
                <span>or click to browse — JPG, PNG, GIF, or WebP, max 8&nbsp;MB. Converted to WebP automatically.</span>
            </div>
        </div>
        <div id="poster-current-label" class="form-help" style="<?= $hasPoster ? '' : 'display:none;' ?>">
            Current poster — click above to change it.
        </div>
        <small class="form-help">Overwrites the path below if provided.</small>
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

    <div class="form-group">
        <label class="form-label">Online only</label>
        <div class="checkbox-row">
            <input type="checkbox" name="online_only" id="online_only" value="1" <?= (int)$old['online_only'] === 1 ? 'checked' : '' ?>>
            <label for="online_only" style="margin-bottom:0;">Show only in online listings</label>
        </div>
    </div>

    <?php if (!$isEdit) : ?>
    <div class="form-group" style="border-top:1px solid var(--border); padding-top:1.25rem; margin-top:0.5rem;">
        <label class="form-label" style="font-size:1.05rem;">Showtimes (optional)</label>
        <p class="form-help" style="margin-top:0;">Add one or more repeating schedules and they'll be created together with this movie.</p>

        <div id="showtime-blocks" data-initial-count="<?= count($submittedBlocks) ?>">
            <?php foreach ($submittedBlocks as $idx => $vals) : movie_edit_render_showtime_block($idx, $vals); endforeach; ?>
        </div>

        <button type="button" class="btn btn-outline btn-sm" id="add-showtime-block">+ Add showtimes</button>

        <template id="showtime-block-template">
            <div class="showtime-block" data-index="__INDEX__" style="border:1px solid var(--border); border-radius:6px; padding:1rem; margin-bottom:1rem;">
                <div class="form-group">
                    <label>Days of the week</label>
                    <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                        <?php foreach (MOVIE_EDIT_DOW as $i => $name) : ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="showtime_blocks[__INDEX__][days][]" value="<?= $i ?>">
                                <?= e($name) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start time</label>
                        <input type="time" name="showtime_blocks[__INDEX__][start_time]" class="st-start-time">
                    </div>
                    <div class="form-group">
                        <label>Available tickets</label>
                        <input type="number" name="showtime_blocks[__INDEX__][tickets]" min="0" value="50" class="st-tickets">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>From date</label>
                        <input type="date" name="showtime_blocks[__INDEX__][date_from]" class="st-date-from">
                    </div>
                    <div class="form-group">
                        <label>To date</label>
                        <input type="date" name="showtime_blocks[__INDEX__][date_to]" class="st-date-to">
                    </div>
                </div>
                <p class="st-preview" style="font-size:0.85rem; color:var(--text-secondary); margin:0.5rem 0 0;"></p>
                <button type="button" class="btn btn-outline btn-sm st-remove-block" style="margin-top:0.6rem;">Remove this block</button>
            </div>
        </template>
    </div>
    <?php endif; ?>

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
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create movie' ?></button>
        <a class="btn btn-outline" href="movies.php">Cancel</a>
    </div>
</form>

<script src="../assets/js/admin-movies.js" defer></script>
<script src="../assets/js/admin-upload.js" defer></script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
