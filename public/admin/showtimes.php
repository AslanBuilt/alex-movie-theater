<?php
declare(strict_types=1);

$pageTitle = 'Showtimes';
require_once __DIR__ . '/includes/admin-header.php';

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$movieId = (int)($_GET['movie_id'] ?? 0);

$where  = [];
$params = [];

if ($movieId > 0) {
    $where[]              = 's.movie_id = :movie_id';
    $params[':movie_id']  = $movieId;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total      = 0;
$rows       = [];
$movies     = [];
$totalPages = 1;

try {
    $countSql = "SELECT COUNT(*) FROM showtimes s $whereSql";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT s.id, s.movie_id, s.label, s.times, s.showtime_date, s.sort_order, m.title
                FROM showtimes s
                LEFT JOIN movies m ON m.id = s.movie_id
                $whereSql
                ORDER BY s.showtime_date DESC, s.sort_order ASC, s.id DESC
                LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($listSql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $movies = $db->query('SELECT id, title FROM movies ORDER BY title ASC')
                 ->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('showtimes.php query failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load showtimes.'];
}

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
?>
<div class="admin-page-header">
    <h1>Showtimes</h1>
    <div class="admin-page-actions">
        <a class="btn btn-primary" href="showtime-edit.php">New showtime</a>
    </div>
</div>

<form method="get" class="filter-bar">
    <div class="form-group grow">
        <label for="movie_id">Movie</label>
        <select name="movie_id" id="movie_id">
            <option value="">All movies</option>
            <?php foreach ($movies as $m) : ?>
                <option value="<?= (int)$m['id'] ?>" <?= $movieId === (int)$m['id'] ? 'selected' : '' ?>>
                    <?= e((string)$m['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
        <a class="btn btn-outline btn-sm" href="showtimes.php">Reset</a>
    </div>
</form>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Movie</th>
                <th>Label</th>
                <th>Times</th>
                <th>Date</th>
                <th>Sort</th>
                <th class="actions">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($rows) === 0) : ?>
            <tr><td colspan="7" class="empty-state">No showtimes yet.</td></tr>
        <?php else : foreach ($rows as $row) : ?>
            <tr>
                <td class="muted"><?= (int)$row['id'] ?></td>
                <td><?= e((string)($row['title'] ?? '— (deleted) —')) ?></td>
                <td><a href="showtime-edit.php?id=<?= (int)$row['id'] ?>"><?= e((string)$row['label']) ?></a></td>
                <td><?= e((string)$row['times']) ?></td>
                <td class="muted"><?= e((string)($row['showtime_date'] ?? '')) ?></td>
                <td><?= (int)$row['sort_order'] ?></td>
                <td class="actions">
                    <a class="btn btn-outline btn-sm" href="showtime-edit.php?id=<?= (int)$row['id'] ?>">Edit</a>
                    <button type="button" class="btn btn-danger btn-sm"
                            onclick="confirmDelete(<?= (int)$row['id'] ?>, <?= json_encode((string)$row['label']) ?>, 'showtime-delete.php')">
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
       href="<?= e(admin_page_url(['page' => $prevPage], 'showtimes.php')) ?>">Prev</a>

    <?php for ($p = 1; $p <= $totalPages; $p++) : ?>
        <a class="page-link <?= $p === $page ? 'current' : '' ?>"
           href="<?= e(admin_page_url(['page' => $p], 'showtimes.php')) ?>"><?= $p ?></a>
    <?php endfor; ?>

    <a class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>"
       href="<?= e(admin_page_url(['page' => $nextPage], 'showtimes.php')) ?>">Next</a>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
