<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/EventRepo.php';

$pageTitle = 'Events | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Special events at Alex Movie Theatre in Alexandria, Indiana. Escape room experiences, special screenings, and more.';
$pageKeywords = 'movie theatre events Alexandria Indiana, special screenings, escape room theatre Indiana';
$canonical = SITE_URL . 'events.php';

$events = tryDb(fn() => EventRepo::getUpcoming());

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Events</p>
        <h1>Events</h1>
        <p class="subtitle">Special screenings, unique experiences, and community events at the Alex.</p>
    </div>
</section>

<section>
    <div class="container">

        <div class="section-header">
            <p class="section-label">Something New is Coming</p>
            <h2 class="section-title">Upcoming Events</h2>
            <div class="section-divider"></div>
        </div>

        <?php if (!empty($events)): ?>
            <?php foreach ($events as $event): ?>
                <?php
                    $eTitle = (string) ($event['title'] ?? '');
                    $eDesc = (string) ($event['description'] ?? '');
                    $eBadge = (string) ($event['badge'] ?? 'Upcoming');
                    $eStatus = (string) ($event['status'] ?? 'upcoming');
                    $eDate = $event['event_date'] ?? null;
                    $eImage = (string) ($event['image_path'] ?? '');

                    if ($eStatus === 'tba' || empty($eDate)) {
                        $dateLine = 'Date &amp; details to be announced';
                    } else {
                        $ts = strtotime((string) $eDate);
                        $dateLine = $ts ? e(date('l, F j, Y', $ts)) : 'Date to be announced';
                    }
                ?>
                <!-- Featured Coming Event -->
                <div class="movie-card" style="max-width:600px; margin-bottom:3rem;">
                    <div class="movie-poster" style="background: linear-gradient(135deg, #1a0a0a, #2a0a0a, #0a0a1a);">
                        <span class="screen-badge" style="background:var(--crimson-dark);"><?= e($eBadge) ?></span>
                        <?php if ($eImage !== ''): ?>
                            <img src="<?= e(asset($eImage)) ?>" alt="<?= e($eTitle) ?>" loading="lazy">
                        <?php else: ?>
                            &#x1F512;
                        <?php endif; ?>
                    </div>
                    <div class="movie-card-body">
                        <h2 class="movie-title"><?= e($eTitle) ?></h2>
                        <?php if ($eDesc !== ''): ?>
                            <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1rem; line-height:1.7;">
                                <?= e($eDesc) ?>
                            </p>
                        <?php endif; ?>
                        <p style="color:var(--text-muted); font-size:0.8rem; margin-bottom:1.25rem;"><?= $dateLine ?></p>
                        <div class="social-links">
                            <a href="<?= FACEBOOK_URL ?>" target="_blank" rel="noopener">&#x1F4D8; Follow on Facebook</a>
                            <a href="<?= INSTAGRAM_URL ?>" target="_blank" rel="noopener">&#x1F4F7; Follow on Instagram</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
        <!-- Featured Coming Event -->
        <div class="movie-card" style="max-width:600px; margin-bottom:3rem;">
            <div class="movie-poster" style="background: linear-gradient(135deg, #1a0a0a, #2a0a0a, #0a0a1a);">
                <span class="screen-badge" style="background:var(--crimson-dark);">Coming Soon</span>
                &#x1F512;
            </div>
            <div class="movie-card-body">
                <h2 class="movie-title">Escape From The "Lockdown Theatre"</h2>
                <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1rem; line-height:1.7;">
                    An immersive escape room experience set inside the Alex Theatre itself. Details coming soon &mdash; follow our social media for the announcement.
                </p>
                <p style="color:var(--text-muted); font-size:0.8rem; margin-bottom:1.25rem;">Date &amp; details to be announced</p>
                <div class="social-links">
                    <a href="<?= FACEBOOK_URL ?>" target="_blank" rel="noopener">&#x1F4D8; Follow on Facebook</a>
                    <a href="<?= INSTAGRAM_URL ?>" target="_blank" rel="noopener">&#x1F4F7; Follow on Instagram</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recurring Events -->
        <div class="section-header">
            <p class="section-label">Every Month</p>
            <h2 class="section-title">Recurring Programs</h2>
            <div class="section-divider"></div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>&#x1F477; Free Senior Movie</h3>
                <p>Monthly free screenings for seniors 55 and up, sponsored by Senior Essential Connections. No ticket purchase required &mdash; just show up.</p>
                <a href="<?= url('senior-movie.php') ?>" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Learn More</a>
            </div>
            <div class="info-card">
                <h3>&#x1F382; Private Screenings</h3>
                <p>Book the theatre for birthdays, group outings, corporate events, or any private occasion. Choose a current film or an alternative title.</p>
                <a href="<?= url('private-screenings.php') ?>" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Book a Screening</a>
            </div>
        </div>

        <div style="text-align:center; margin-top:3rem;">
            <p class="text-secondary mb-2">Want to be the first to know about new events?</p>
            <a href="<?= FACEBOOK_URL ?>" class="btn btn-crimson" target="_blank" rel="noopener">Follow Us on Facebook</a>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
