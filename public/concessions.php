<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Concessions | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Enjoy concessions at Alex Movie Theatre. Popcorn, drinks, candy and more at prices cheaper than big chain theatres.';
$pageKeywords = 'movie theatre concessions Alexandria Indiana, cheap movie snacks, Alex Theatre food';
$canonical = SITE_URL . 'concessions.php';

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Concessions</p>
        <h1>Concession Stand</h1>
        <p class="subtitle">Cheaper than other theaters &mdash; classic movie snacks done right.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="highlight-box">
            <p><strong>Our prices are cheaper than other theaters</strong> &mdash; but not as cheap as fast food! Enjoy your favorites without breaking the bank. Prices are consistent with what you'd expect from a quality local cinema.</p>
        </div>

        <div class="section-header">
            <p class="section-label">Classic Movie Favorites</p>
            <h2 class="section-title">What We Offer</h2>
            <div class="section-divider"></div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>Popcorn</h3>
                <ul>
                    <li>Fresh-popped buttered popcorn</li>
                    <li>Available in multiple sizes</li>
                    <li>The classic theatre experience</li>
                </ul>
            </div>
            <div class="info-card">
                <h3>Drinks</h3>
                <ul>
                    <li>Fountain sodas</li>
                    <li>Water</li>
                    <li>Various sizes available</li>
                </ul>
            </div>
            <div class="info-card">
                <h3>Candy &amp; Snacks</h3>
                <ul>
                    <li>A variety of classic movie candies</li>
                    <li>Packaged snacks</li>
                    <li>Great for the kids</li>
                </ul>
            </div>
            <div class="info-card">
                <h3>Hot Items</h3>
                <ul>
                    <li>Nachos</li>
                    <li>Hot dogs</li>
                    <li>Check with staff for current offerings</li>
                </ul>
            </div>
        </div>

        <div class="policy-box mt-3">
            <h3>Concession Policies</h3>
            <p>No outside food or beverages are permitted inside the theatre &mdash; this helps us keep ticket prices low for everyone. Exception: birthday cakes are allowed for private rental events. For current menu items and pricing, please call us at <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a> or check with staff at the stand.</p>
        </div>

        <div style="text-align:center; margin-top:3rem;">
            <p class="text-secondary mb-2">Ready to catch a show?</p>
            <a href="<?= e(TICKETS_URL) ?>" class="btn btn-crimson">Buy Tickets Online</a>
            <a href="<?= url() ?>" class="btn btn-outline" style="margin-left:1rem;">View Showtimes</a>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
