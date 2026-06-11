<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Free Senior Movie | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Free movie screenings for seniors 55 and up at Alex Movie Theatre in Alexandria, Indiana. Sponsored by Senior Essential Connections.';
$pageKeywords = 'free senior movie Alexandria Indiana, senior cinema Indiana, free movies for seniors, Senior Essential Connections';
$canonical = SITE_URL . 'senior-movie.php';

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Senior Movie</p>
        <h1>Free Senior Movie</h1>
        <p class="subtitle">A community gift for seniors 55 and up · no ticket required.</p>
    </div>
</section>

<section>
    <div class="container container-narrow">
        <div class="senior-badge">Free Admission &bull; Ages 55 &amp; Up &bull; Sponsored by Senior Essential Connections</div>

        <div class="section-header">
            <p class="section-label">About the Program</p>
            <h2 class="section-title">Free for Seniors 55+</h2>
            <div class="section-divider"></div>
        </div>

        <p style="color:var(--text-secondary); margin-bottom:1.75rem; line-height:1.8; font-size:1.1rem;">
            The Alex Theatre is proud to partner with <strong class="text-crimson">Senior Essential Connections</strong> to bring free monthly screenings to the community. Every senior 55 or older is welcome at no charge &mdash; no reservations needed, just show up and enjoy.
        </p>

        <div class="highlight-box">
            <p><strong>Who qualifies?</strong> Any senior citizen 55 years of age or older. No ID check required &mdash; just arrive and take your seat.</p>
        </div>

        <div class="info-card mt-3">
            <h3>Schedule &amp; Questions</h3>
            <p>Screening dates are organized by <strong>Senior Essential Connections</strong>. Contact them for the current movie listings, or call the theatre at <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a>.</p>
        </div>

        <div class="info-card mt-2">
            <h3>About Senior Essential Connections</h3>
            <p>Senior Essential Connections is an organization dedicated to supporting and enriching the lives of older adults in the Alexandria community. They sponsor this free movie program as part of their mission to keep seniors engaged and connected.</p>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
