<?php
declare(strict_types=1);

$pageTitle = 'Events';
require_once __DIR__ . '/includes/admin-header.php';

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$status  = (string)($_GET['status'] ?? '');

$allowedStatus = ['upcoming', 'past', 'tba'];
if ($status !== '' && !in_array($status, $allowedStatus, true)) {
    $status = '';
}

$where  = [];
$params = [];

if ($status !== '') {
    $where[]            = 'status = :status';
    $params[':status']  = $status;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total      = 0;
$rows       = [];
$totalPages = 1;

try {
    $countSql = "SELECT COUNT(*) FROM events $whereSql";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT id, title, status, badge, event_date, sort_order, updated_at
                FROM events
                $whereSql
                ORDER BY event_date DESC, sort_order ASC, id DESC
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
    error_log('events.php query failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load events.'];
}

function admin_event_badge(string $status): string
{
    switch ($status) {
        case 'upcoming': return 'badge badge-success';
        case 'past':     return 'badge badge-muted';
        case 'tba':      return 'badge badge-muted';
    }
    return 'badge';
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
    <h1>Events</h1>
    <div class="admin-page-actions">
        <a class="btn btn-primary" href="event-edit.php">New event</a>
    </div>
</div>

<form method="get" class="filter-bar">
    <div class="form-group grow">
        <label for="status">Status</label>
        <select name="status" id="status">
            <option value="">All</option>
            <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
            <option value="past"     <?= $status === 'past' ? 'selected' : '' ?>>Past</option>
            <option value="tba"      <?= $status === 'tba' ? 'selected' : '' ?>>TBA</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
        <a class="btn btn-outline btn-sm" href="events.php">Reset</a>
    </div>
</form>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Status</th>
                <th>Badge</th>
                <th>Date</th>
                <th>Sort</th>
                <th>Updated</th>
                <th class="actions">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($rows) === 0) : ?>
            <tr><td colspan="8" class="empty-state">No events found.</td></tr>
        <?php else : foreach ($rows as $row) : ?>
            <tr>
                <td class="muted"><?= (int)$row['id'] ?></td>
                <td><a href="event-edit.php?id=<?= (int)$row['id'] ?>"><?= e((string)$row['title']) ?></a></td>
                <td><span class="<?= e(admin_event_badge((string)$row['status'])) ?>"><?= e((string)$row['status']) ?></span></td>
                <td><?= e((string)($row['badge'] ?? '')) ?></td>
                <td class="muted"><?= e((string)($row['event_date'] ?? '')) ?></td>
                <td><?= (int)$row['sort_order'] ?></td>
                <td class="muted"><?= e((string)$row['updated_at']) ?></td>
                <td class="actions">
                    <a class="btn btn-outline btn-sm" href="event-edit.php?id=<?= (int)$row['id'] ?>">Edit</a>
                    <button type="button" class="btn btn-danger btn-sm"
                            onclick="confirmDelete(<?= (int)$row['id'] ?>, <?= json_encode((string)$row['title']) ?>, 'event-delete.php')">
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
       href="<?= e(admin_page_url(['page' => $prevPage], 'events.php')) ?>">Prev</a>

    <?php for ($p = 1; $p <= $totalPages; $p++) : ?>
        <a class="page-link <?= $p === $page ? 'current' : '' ?>"
           href="<?= e(admin_page_url(['page' => $p], 'events.php')) ?>"><?= $p ?></a>
    <?php endfor; ?>

    <a class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>"
       href="<?= e(admin_page_url(['page' => $nextPage], 'events.php')) ?>">Next</a>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
