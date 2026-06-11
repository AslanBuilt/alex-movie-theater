<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/MovieRepo.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$movie = $id > 0 ? tryDb(fn() => MovieRepo::getById($id), null) : null;

// Unknown movie (or DB down) — send people back to the listing rather than a dead page.
if ($movie === null) {
    header('Location: ' . url());
    exit;
}

$title       = (string) ($movie['title'] ?? '');
$rating      = (string) ($movie['rating'] ?? '');
$screen      = (string) ($movie['screen'] ?? 'either');
$posterPath  = (string) ($movie['poster_path'] ?? '');
$description = (string) ($movie['description'] ?? '');
$onlineOnly  = !empty($movie['online_only']);
$showtimes   = $movie['showtimes'] ?? [];
$status      = (string) ($movie['status'] ?? 'now_showing');

$screenLabel = $screen === 'large'
    ? 'Large Screen'
    : ($screen === 'small' ? 'Small Screen' : '');

$pageTitle = $title . ' — Showtimes & Tickets | Alex Movie Theatre, Alexandria IN';
$pageDescription = $description !== ''
    ? $description . ' Showtimes and tickets at Alex Movie Theatre in Alexandria, Indiana. Adults $5, Children $3.'
    : $title . ' is playing at Alex Movie Theatre in Alexandria, Indiana. See showtimes and buy tickets — adults $5, children $3.';
$pageKeywords = $title . ' Alexandria Indiana, ' . $title . ' showtimes, ' . $title . ' tickets Alex Theatre';
$canonical = SITE_URL . 'movie.php?id=' . $id;

require TEMPLATES_PATH . '/header.php';
?>

<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Movie",
    "name": <?= json_encode($title) ?>,
    <?php if ($rating !== ''): ?>"contentRating": <?= json_encode($rating) ?>,<?php endif; ?>
    <?php if ($description !== ''): ?>"description": <?= json_encode($description) ?>,<?php endif; ?>
    "url": <?= json_encode($canonical) ?>
}
</script>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Now Showing</a><span class="sep">/</span><?= e($title) ?></p>
        <h1><?= e($title) ?></h1>
        <p class="subtitle">
            <?php if ($rating !== ''): ?><?= e($rating) ?> &middot; <?php endif; ?>
            <?php if ($screenLabel !== ''): ?><?= e($screenLabel) ?> &middot; <?php endif; ?>
            Alexandria, Indiana
        </p>
    </div>
</section>

<section>
    <div class="container">
        <div class="movie-detail">
            <div class="movie-detail-poster">
                <?php if ($posterPath !== ''): ?>
                    <img src="<?= e(asset($posterPath)) ?>" alt="<?= e($title) ?> movie poster" loading="eager">
                <?php else: ?>
                    <div class="movie-poster-placeholder"><?= e($title) ?></div>
                <?php endif; ?>
            </div>

            <div class="movie-detail-body">
                <?php if ($description !== ''): ?>
                    <p class="movie-detail-desc"><?= e($description) ?></p>
                <?php endif; ?>

                <?php if (!empty($showtimes)): ?>
                    <div class="section-header" style="margin-top:0.5rem;">
                        <p class="section-label">This Week</p>
                        <h2 class="section-title">Showtimes</h2>
                        <div class="section-divider"></div>
                    </div>
                    <div class="showtime-list">
                        <?php foreach ($showtimes as $st): ?>
                            <div class="showtime-row">
                                <span class="showtime-day"><?= e((string) ($st['label'] ?? '')) ?></span>
                                <span class="showtime-times"><?= e((string) ($st['times'] ?? '')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-secondary">Showtimes for this film will be posted soon — call us at
                        <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a> for the latest schedule.</p>
                <?php endif; ?>

                <div class="movie-cta" style="margin-top:1.75rem;">
                    <a href="<?= e(TICKETS_URL) ?>" class="btn btn-crimson">Buy Tickets</a>
                    <a href="<?= url() ?>" class="btn btn-outline" style="margin-left:0.75rem;">All Showtimes</a>
                    <?php if ($onlineOnly): ?>
                        <p class="online-required">Small-screen seats are limited — reserve ahead by phone or online.</p>
                    <?php endif; ?>
                </div>

                <div class="policy-box mt-3">
                    <h3>Good to Know</h3>
                    <p>Adults $5 &bull; Kids 12 &amp; under $3. Doors open about 30 minutes before showtime. No outside food or drinks — our concession stand keeps prices low so tickets stay affordable.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
