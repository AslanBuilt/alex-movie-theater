<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/EventRepo.php';

$pageTitle = 'Events | The Alex — Alexandria, Indiana';
$pageDescription = 'Special events at The Alex in Alexandria, Indiana. Escape room experiences, special screenings, and more.';
$currentPage = 'events';

$events = tryDb(fn () => EventRepo::getUpcoming());

/**
 * Resolve a stored event image_path to a displayable <img src>. Uploads
 * from event-edit.php live under uploads/events/ (outside assets/), so they
 * need url() rather than posterUrl()'s assets/-prefixing behavior. Pasted
 * absolute URLs still resolve correctly via posterUrl().
 */
function eventsPageImageUrl(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path) === 1) {
        return $path;
    }
    if (strncasecmp($path, 'uploads/', 8) === 0) {
        return url($path);
    }
    return posterUrl($path);
}

require __DIR__ . '/templates/header.php';
?>

<section class="page-hero page-hero--photo" style="background-image: linear-gradient(rgba(250,245,235,0.87),rgba(250,245,235,0.87)), url('assets/images/hero-2.webp')">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Events</p>
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

    <?php if (empty($events)) : ?>
      <div class="info-card" style="max-width:640px; margin:0 auto 2.5rem; text-align:center;">
        <h3>No events scheduled right now</h3>
        <p>Check back soon &mdash; or follow us on Facebook and Instagram, where we post new events first.</p>
      </div>
    <?php else : foreach ($events as $ev) :
        $imgUrl  = eventsPageImageUrl((string)($ev['image_path'] ?? ''));
        $badge   = trim((string)($ev['badge'] ?? ''));
        $dateStr = '';
        if (!empty($ev['event_date'])) {
            $ts = strtotime((string)$ev['event_date']);
            if ($ts !== false) {
                $dateStr = date('F j, Y', $ts);
            }
        }
    ?>
    <div class="event-feature-card">
      <div class="event-feature-img">
        <?php if ($badge !== '') : ?>
          <span class="screen-badge" style="background:var(--crimson-dark); z-index:2; top:0.75rem; left:0.75rem; right:auto;"><?= e($badge) ?></span>
        <?php endif; ?>
        <?php if ($imgUrl !== '') : ?>
          <img src="<?= e($imgUrl) ?>" alt="<?= e((string)$ev['title']) ?>" loading="lazy">
        <?php else : ?>
          <div class="movie-poster-placeholder"><?= e((string)$ev['title']) ?></div>
        <?php endif; ?>
      </div>
      <div class="event-feature-body">
        <h2><?= e((string)$ev['title']) ?></h2>
        <p class="event-meta"><?= $dateStr !== '' ? e($dateStr) : 'Date &amp; details to be announced' ?> &middot; Follow our socials for updates</p>
        <?php if (!empty($ev['description'])) : ?>
          <p><?= nl2br(e((string)$ev['description'])) ?></p>
        <?php endif; ?>
        <div class="contact-social" style="margin-top:1.25rem;">
          <div class="social-links">
            <a href="https://www.facebook.com/TheAlexandriaTheatre" target="_blank" rel="noopener">Facebook</a>
            <a href="https://www.instagram.com/the.alextheatre" target="_blank" rel="noopener">Instagram</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <div class="section-header">
      <p class="section-label">Every Month</p>
      <h2 class="section-title">Recurring Programs</h2>
      <div class="section-divider"></div>
    </div>

    <div class="info-grid">
      <div class="info-card">
        <h3>Free Senior Movie</h3>
        <p>Monthly free screenings for seniors 55 and up, sponsored by Senior Essential Connections. No ticket purchase required · just show up.</p>
        <a href="senior-movie.php" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Learn More</a>
      </div>
      <div class="info-card">
        <h3>Private Screenings</h3>
        <p>Book the theatre for birthdays, group outings, corporate events, or any private occasion. Choose a current film or an alternative title.</p>
        <a href="private-screenings.php" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Book a Screening</a>
      </div>
    </div>

    <div style="text-align:center; margin-top:3rem;">
      <p class="text-secondary mb-2">Want to be the first to know about new events?</p>
      <a href="https://www.facebook.com/TheAlexandriaTheatre" class="btn btn-crimson" target="_blank" rel="noopener">Follow Us on Facebook</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
