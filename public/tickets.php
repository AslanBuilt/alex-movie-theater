<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/MovieRepo.php';

$showCart = true;
$pageTitle = 'Buy Tickets | The Alex — Alexandria, Indiana';
$pageDescription = 'Tickets at The Alex in Alexandria, Indiana. Adults $5, Children $3. Buy at the door or reserve by phone at 765-620-9093.';
$pageKeywords = 'The Alex tickets, movie tickets Alexandria Indiana, cheap movie tickets';
$canonical = SITE_URL . 'tickets';

$nowShowing = tryDb(fn() => MovieRepo::getNowShowing());

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero page-hero--photo" style="background-image: linear-gradient(rgba(250,245,235,0.87),rgba(250,245,235,0.87)), url('assets/images/hero-4.webp')">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Tickets</p>
        <h1>Buy Tickets</h1>
        <p class="subtitle">Affordable seats for everyone · $5 adults, $3 children.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="pricing-grid">
            <div class="price-card">
                <div class="price-amount">$5</div>
                <div class="price-label">Adults</div>
            </div>
            <div class="price-card">
                <div class="price-amount">$3</div>
                <div class="price-label">Children</div>
            </div>
        </div>

        <div class="section-header" style="margin-top:3rem;">
            <p class="section-label">How to Buy</p>
            <h2 class="section-title">Ways to Get Your Seat</h2>
            <div class="section-divider"></div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>At the Door</h3>
                <p>Walk up to the box office before showtime. Cash and card accepted. The doors open about 30 minutes before each show.</p>
                <p style="margin-top:1rem;"><strong><?= e(SITE_ADDRESS) ?></strong></p>
            </div>
            <div class="info-card">
                <h3>Reserve by Phone</h3>
                <p>Call ahead to reserve seats, especially for the small screen or for groups. We hold reservations until 10 minutes before showtime.</p>
                <p style="margin-top:1rem;"><strong><a href="tel:<?= e(SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a></strong></p>
            </div>
            <div class="info-card">
                <h3>Private Rentals</h3>
                <p>Book the whole theatre for a birthday or group event. Choose a current film ($5/adult, $75 minimum) or an alternative title ($75 flat).</p>
                <a href="<?= url('private-screenings') ?>" class="btn btn-outline" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Private Screening Info</a>
            </div>
        </div>

        <div class="policy-box mt-3">
            <h3>Showtime Policy</h3>
            <p>The theatre reserves the right to adjust showtimes, screens, or auditoriums based on equipment issues or when ticket sales exceed capacity. We appreciate your flexibility and understanding.</p>
        </div>

        <div class="section-header" style="margin-top:3rem;">
            <p class="section-label">Now Showing</p>
            <h2 class="section-title">Pick a Movie to See Times &amp; Buy</h2>
            <div class="section-divider"></div>
        </div>

        <?php if (!empty($nowShowing)): ?>
            <div class="info-grid">
                <?php foreach ($nowShowing as $movie): ?>
                    <?php $mid = (int) ($movie['id'] ?? 0); $title = (string) ($movie['title'] ?? 'Movie'); ?>
                    <div class="info-card">
                        <h3><?= e($title) ?></h3>
                        <a href="movie.php?id=<?= $mid ?>" class="btn btn-crimson" style="display:inline-block; margin-top:1rem;">Showtimes &amp; Tickets</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center;">
                <p class="text-secondary mb-2">Nothing listed online right now &mdash; call us to check what's playing.</p>
                <a href="tel:<?= e(SITE_PHONE) ?>" class="btn btn-crimson"><?= e(SITE_PHONE) ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
