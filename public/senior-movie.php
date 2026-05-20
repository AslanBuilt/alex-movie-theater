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
        <p class="subtitle">A community gift for seniors 55 and up &mdash; no ticket required.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="senior-badge">&#x2605; Free Admission &bull; Ages 55 &amp; Up &bull; Sponsored by Senior Essential Connections</div>

        <div class="two-col">
            <div>
                <div class="section-header">
                    <p class="section-label">About the Program</p>
                    <h2 class="section-title">Free for Seniors 55+</h2>
                    <div class="section-divider"></div>
                </div>

                <p style="color:var(--text-secondary); margin-bottom:1.5rem; line-height:1.8;">
                    The Alex Theatre is proud to partner with <strong class="text-crimson">Senior Essential Connections</strong> to bring free movie screenings to the community. Every eligible senior 55 years of age or older is welcome at no charge.
                </p>

                <p style="color:var(--text-secondary); margin-bottom:2rem; line-height:1.8;">
                    This is a recurring program designed to keep our senior community connected, entertained, and part of the Alex Theatre family. No reservations needed — just show up and enjoy!
                </p>

                <div class="highlight-box">
                    <p><strong>Who qualifies?</strong> Any senior citizen 55 years of age or older. No ID check required &mdash; just arrive and take your seat.</p>
                </div>

                <div class="highlight-box">
                    <p><strong>No outside concessions allowed.</strong> Please enjoy our in-house concession stand for snacks and drinks during your visit.</p>
                </div>

                <div class="info-card mt-3">
                    <h3>&#x1F4DE; Questions?</h3>
                    <p>Contact Senior Essential Connections directly for the latest scheduling information, or call the theatre at <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a>.</p>
                </div>
            </div>

            <div>
                <div class="section-header">
                    <p class="section-label">Upcoming Screening</p>
                    <h2 class="section-title">Next Showing</h2>
                    <div class="section-divider"></div>
                </div>

                <div class="next-showing-card">
                    <div class="film-name">The Devil Wears Prada 2</div>
                    <div class="film-meta">
                        <p style="margin-top:0.5rem;">Friday, May 1st &bull; 1:00 PM</p>
                        <p style="margin-top:0.5rem;">Hosted by Essential Senior Connections</p>
                    </div>
                    <p style="margin-top:1rem; font-size:0.8rem; color:var(--text-muted);">Showtimes subject to change. Contact Senior Essential Connections for current schedule.</p>
                </div>

                <div class="info-card">
                    <h3>&#x1F3AA; About Senior Essential Connections</h3>
                    <p>Senior Essential Connections is an organization dedicated to supporting and enriching the lives of older adults in the Alexandria community. They sponsor this free movie program as part of their mission to keep seniors engaged and connected.</p>
                </div>

                <div class="info-card mt-2">
                    <h3>&#x1F4CD; Theatre Location</h3>
                    <p><?= e(SITE_ADDRESS) ?><br><br>
                    Parking available in the adjacent gravel lot, along the street, and at Horners Grocery.</p>
                    <a href="<?= url('location.php') ?>" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem;">Get Directions</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
