<?php
declare(strict_types=1);

$pageTitle = 'Senior Showings';
require_once __DIR__ . '/includes/admin-header.php';

$rows = [];

try {
    $stmt = $db->prepare(
        'SELECT id, movie_title, showing_date, showing_time, notes, status
         FROM senior_showings
         ORDER BY showing_date DESC, showing_time DESC, id DESC'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('senior-showings.php query failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not load senior showings.'];
}

function admin_senior_badge(string $status): string
{
    switch ($status) {
        case 'upcoming': return 'badge badge-success';
        case 'past':     return 'badge badge-muted';
        case 'tba':      return 'badge badge-warning';
    }
    return 'badge';
}

function admin_truncate(string $text, int $max = 80): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max - 1) . '…';
}
?>
<div class="admin-page-header">
    <h1>Senior Showings</h1>
    <div class="admin-page-actions">
        <a class="btn btn-primary" href="senior-showing-edit.php">New showing</a>
    </div>
</div>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Movie title</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Notes</th>
                <th class="actions">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($rows) === 0) : ?>
            <tr><td colspan="7" class="empty-state">No senior showings yet.</td></tr>
        <?php else : foreach ($rows as $row) : ?>
            <tr>
                <td class="muted"><?= (int)$row['id'] ?></td>
                <td><a href="senior-showing-edit.php?id=<?= (int)$row['id'] ?>"><?= e((string)$row['movie_title']) ?></a></td>
                <td class="muted"><?= e((string)$row['showing_date']) ?></td>
                <td class="muted"><?= e((string)$row['showing_time']) ?></td>
                <td><span class="<?= e(admin_senior_badge((string)$row['status'])) ?>"><?= e((string)$row['status']) ?></span></td>
                <td class="muted"><?= e(admin_truncate((string)($row['notes'] ?? ''))) ?></td>
                <td class="actions">
                    <a class="btn btn-outline btn-sm" href="senior-showing-edit.php?id=<?= (int)$row['id'] ?>">Edit</a>
                    <button type="button" class="btn btn-danger btn-sm"
                            onclick="confirmDelete(<?= (int)$row['id'] ?>, <?= e(json_encode((string)$row['movie_title'])) ?>, 'senior-showing-delete.php')">
                        Delete
                    </button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
