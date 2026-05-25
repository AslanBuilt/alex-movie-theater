<?php
declare(strict_types=1);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin-header.php';

$totalMovies      = 0;
$nowShowingCount  = 0;
$comingSoonCount  = 0;
$archivedCount    = 0;
$totalEvents      = 0;
$recentMovies     = [];
$recentEvents     = [];

try {
    $totalMovies = (int)$db->query('SELECT COUNT(*) FROM movies')->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM movies WHERE status = :s');
    $stmt->execute([':s' => 'now_showing']);
    $nowShowingCount = (int)$stmt->fetchColumn();

    $stmt->execute([':s' => 'coming_soon']);
    $comingSoonCount = (int)$stmt->fetchColumn();

    $stmt->execute([':s' => 'archived']);
    $archivedCount = (int)$stmt->fetchColumn();

    $totalEvents = (int)$db->query('SELECT COUNT(*) FROM events')->fetchColumn();

    $stmt = $db->prepare(
        'SELECT id, title, status, updated_at
         FROM movies
         ORDER BY updated_at DESC
         LIMIT 5'
    );
    $stmt->execute();
    $recentMovies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $db->prepare(
        'SELECT id, title, status, updated_at
         FROM events
         ORDER BY updated_at DESC
         LIMIT 5'
    );
    $stmt->execute();
    $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('Admin dashboard query failed: ' . $e->getMessage());
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Could not load dashboard statistics.',
    ];
}

/** Map a status to a badge class. */
function admin_status_badge(string $status): string
{
    switch ($status) {
        case 'now_showing':
        case 'upcoming':
        case 'active':
            return 'badge badge-success';
        case 'archived':
        case 'past':
            return 'badge badge-muted';
        case 'coming_soon':
        case 'tba':
            return 'badge badge-warning';
    }
    return 'badge';
}

$chartData = [
    'labels' => ['Now Showing', 'Coming Soon', 'Archived'],
    'values' => [$nowShowingCount, $comingSoonCount, $archivedCount],
];
?>
<div class="admin-page-header">
    <h1>Dashboard</h1>
    <div class="admin-page-actions">
        <a class="btn btn-outline btn-sm" href="movies.php">Manage movies</a>
        <a class="btn btn-primary btn-sm" href="movie-edit.php">New movie</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= e((string)$totalMovies) ?></div>
        <div class="stat-label">Total Movies</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= e((string)$nowShowingCount) ?></div>
        <div class="stat-label">Now Showing</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= e((string)$comingSoonCount) ?></div>
        <div class="stat-label">Coming Soon</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= e((string)$totalEvents) ?></div>
        <div class="stat-label">Total Events</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h2>Recently updated movies</h2>
        <?php if (count($recentMovies) === 0) : ?>
            <p class="muted">No movies yet.</p>
        <?php else : ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentMovies as $row) : ?>
                        <tr>
                            <td><a href="movie-edit.php?id=<?= (int)$row['id'] ?>"><?= e((string)$row['title']) ?></a></td>
                            <td><span class="<?= e(admin_status_badge((string)$row['status'])) ?>"><?= e(str_replace('_', ' ', (string)$row['status'])) ?></span></td>
                            <td class="muted"><?= e((string)$row['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-card">
        <h2>Recently updated events</h2>
        <?php if (count($recentEvents) === 0) : ?>
            <p class="muted">No events yet.</p>
        <?php else : ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentEvents as $row) : ?>
                        <tr>
                            <td><a href="event-edit.php?id=<?= (int)$row['id'] ?>"><?= e((string)$row['title']) ?></a></td>
                            <td><span class="<?= e(admin_status_badge((string)$row['status'])) ?>"><?= e(str_replace('_', ' ', (string)$row['status'])) ?></span></td>
                            <td class="muted"><?= e((string)$row['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="chart-wrap">
    <h2>Movies by status</h2>
    <canvas id="moviesByStatus" height="120"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
<script>
window.addEventListener('load', function () {
    if (typeof Chart === 'undefined') return;
    var ctx = document.getElementById('moviesByStatus');
    if (!ctx) return;
    var data = <?= json_encode($chartData, JSON_THROW_ON_ERROR) ?>;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Movies',
                data: data.values,
                backgroundColor: ['#8B1D33', '#A8253F', '#584035'],
                borderColor: '#F2E8DC',
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { ticks: { color: '#C8B8A8' }, grid: { color: '#2A1A12' } },
                y: { ticks: { color: '#C8B8A8' }, grid: { color: '#2A1A12' }, beginAtZero: true, precision: 0 }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
