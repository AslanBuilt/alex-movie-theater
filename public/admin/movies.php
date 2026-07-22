<?php
declare(strict_types=1);

$pageTitle = 'Movies';
require_once __DIR__ . '/includes/admin-header.php';

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$q       = trim((string)($_GET['q'] ?? ''));
$status  = (string)($_GET['status'] ?? '');

$allowedStatus = ['now_showing', 'coming_soon', 'archived'];
if ($status !== '' && !in_array($status, $allowedStatus, true)) {
    $status = '';
}

$where  = [];
$params = [];

if ($q !== '') {
    $where[]       = 'title LIKE :q';
    $params[':q']  = '%' . $q . '%';
}
if ($status !== '') {
    $where[]          = 'status = :status';
    $params[':status'] = $status;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
$rows  = [];

try {
    $countSql = "SELECT COUNT(*) FROM movies $whereSql";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT id, title, rating, screen, status, online_only, sort_order, updated_at, poster_path
                FROM movies
                $whereSql
                ORDER BY sort_order ASC, title ASC
                LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($listSql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('movies.php query failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load movies.'];
    $totalPages = 1;
}

function admin_status_badge_movies(string $status): string
{
    switch ($status) {
        case 'now_showing': return 'badge badge-success';
        case 'archived':    return 'badge badge-muted';
        case 'coming_soon': return 'badge badge-warning';
    }
    return 'badge';
}

/**
 * Build a URL preserving current query string with overrides.
 *
 * @param array<string,string|int> $overrides
 */
function admin_page_url(array $overrides, string $base): string
{
    $current = $_GET;
    foreach ($overrides as $k => $v) {
        $current[$k] = $v;
    }
    $current = array_filter($current, static function ($v) {
        return $v !== '' && $v !== null;
    });
    return $base . (empty($current) ? '' : ('?' . http_build_query($current)));
}
// Drag-to-reorder only makes sense against the complete, unfiltered movie
// list — reordering a filtered/paginated subset would silently collide with
// sort_order values of movies not currently on screen.
$canArrange = $totalPages <= 1 && $q === '' && $status === '';
?>
<div class="admin-page-header">
    <h1>Movies</h1>
    <div class="admin-page-actions">
        <?php if ($canArrange) : ?>
            <button type="button" class="btn btn-outline btn-sm" id="arrange-toggle">Arrange Order &#8597;</button>
            <button type="button" class="btn btn-primary btn-sm" id="save-order" hidden>Save Order</button>
            <button type="button" class="btn btn-outline btn-sm" id="cancel-order" hidden>Cancel</button>
        <?php endif; ?>
        <a class="btn btn-primary" href="movie-edit.php">New movie</a>
    </div>
</div>

<div id="reorder-status" aria-live="polite"></div>

<form method="get" class="filter-bar">
    <div class="form-group grow">
        <label for="q">Search</label>
        <input type="text" name="q" id="q" value="<?= e($q) ?>" placeholder="Title contains…">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select name="status" id="status">
            <option value="">All</option>
            <option value="now_showing" <?= $status === 'now_showing' ? 'selected' : '' ?>>Now Showing</option>
            <option value="coming_soon" <?= $status === 'coming_soon' ? 'selected' : '' ?>>Coming Soon</option>
            <option value="archived"    <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
        <a class="btn btn-outline btn-sm" href="movies.php">Reset</a>
    </div>
</form>

<div class="admin-table-wrap" id="movies-table-wrap" data-csrf="<?= e($auth->generateCsrfToken()) ?>">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Poster</th>
                <th>Title</th>
                <th>Rating</th>
                <th>Screen</th>
                <th>Status</th>
                <th>Sort</th>
                <th>Updated</th>
                <th class="actions">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($rows) === 0) : ?>
            <tr><td colspan="8" class="empty-state">No movies found. <a href="movie-edit.php">Add the first one.</a></td></tr>
        <?php else : foreach ($rows as $row) : ?>
            <?php
                $posterUrl  = posterUrl((string)($row['poster_path'] ?? ''));
            ?>
            <tr class="movie-row" data-movie-id="<?= (int)$row['id'] ?>">
                <td style="width:52px; padding:0.5rem 0.75rem;">
                    <span class="drag-handle" aria-hidden="true" style="display:none; cursor:grab; margin-right:0.4rem; user-select:none;">&#8942;&#8942;</span>
                    <?php if ($posterUrl !== ''): ?>
                        <img src="<?= e($posterUrl) ?>" alt="" style="width:40px; height:56px; object-fit:cover; border-radius:3px; display:block;">
                    <?php else: ?>
                        <div style="width:40px; height:56px; background:var(--bg-card-hover); border-radius:3px; display:flex; align-items:center; justify-content:center; font-size:0.6rem; color:var(--text-secondary); text-align:center; line-height:1.2;">No<br>img</div>
                    <?php endif; ?>
                </td>
                <td><a href="movie-edit.php?id=<?= (int)$row['id'] ?>"><?= e((string)$row['title']) ?></a></td>
                <td><?= e((string)($row['rating'] ?? '')) ?></td>
                <td><?= e((string)$row['screen']) ?></td>
                <td><span class="<?= e(admin_status_badge_movies((string)$row['status'])) ?>"><?= e(str_replace('_', ' ', (string)$row['status'])) ?></span></td>
                <td><?= (int)$row['sort_order'] ?></td>
                <td class="muted"><?= e((string)$row['updated_at']) ?></td>
                <td class="actions">
                    <a class="btn btn-outline btn-sm" href="movie-edit.php?id=<?= (int)$row['id'] ?>">Edit</a>
                    <button type="button" class="btn btn-danger btn-sm"
                            onclick="confirmDelete(<?= (int)$row['id'] ?>, <?= e(json_encode((string)$row['title'])) ?>, 'movie-delete.php')">
                        Delete
                    </button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1) : ?>
<nav class="pagination" aria-label="Pagination">
    <?php
    $prevPage = max(1, $page - 1);
    $nextPage = min($totalPages, $page + 1);
    ?>
    <a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>"
       href="<?= e(admin_page_url(['page' => $prevPage], 'movies.php')) ?>">Prev</a>

    <?php for ($p = 1; $p <= $totalPages; $p++) : ?>
        <a class="page-link <?= $p === $page ? 'current' : '' ?>"
           href="<?= e(admin_page_url(['page' => $p], 'movies.php')) ?>"><?= $p ?></a>
    <?php endfor; ?>

    <a class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>"
       href="<?= e(admin_page_url(['page' => $nextPage], 'movies.php')) ?>">Next</a>
</nav>
<?php endif; ?>

<script src="../assets/js/admin-movies.js" defer></script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
