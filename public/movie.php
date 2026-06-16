<?php
$currentPage = 'index';
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/MovieRepo.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$movie = null;
$showtimes = [];

if ($id > 0) {
    $movie = tryDb(fn() => (new MovieRepo(Database::getInstance()))->getById($id), null);
}

if ($movie === null) {
    $pageTitle = 'Movie Not Found | Alex Movie Theatre';
    $pageDescription = 'Movie information at Alex Movie Theatre in Alexandria, Indiana.';
} else {
    $pageTitle = htmlspecialchars($movie['title']) . ' | Alex Movie Theatre — Alexandria, Indiana';
    $pageDescription = $movie['description']
        ? htmlspecialchars(substr($movie['description'], 0, 155))
        : 'Now showing at Alex Movie Theatre in Alexandria, Indiana.';
    $showtimes = $movie['showtimes'] ?? [];
}

require __DIR__ . '/templates/header.php';
?>

<?php if ($movie === null): ?>
<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Movie</p>
    <h1>Movie Not Found</h1>
    <p class="subtitle">This film may have ended its run or the link is outdated.</p>
  </div>
</section>
<section>
  <div class="container" style="text-align:center; padding:4rem 0;">
    <p class="text-secondary" style="margin-bottom:2rem;">Check what&rsquo;s playing now:</p>
    <a href="index.php#now-showing" class="btn btn-crimson">See Now Showing</a>
  </div>
</section>

<?php else: ?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><a href="index.php#now-showing">Now Showing</a><span class="sep">/</span><?= e($movie['title']) ?></p>
    <h1><?= e($movie['title']) ?></h1>
    <p class="subtitle">
      <?php if ($movie['screen'] === 'large'): ?>Large Screen<?php elseif ($movie['screen'] === 'small'): ?>Small Screen<?php endif; ?>
      <?php if (!empty($movie['rating'])): ?>&bull; Rated <?= e($movie['rating']) ?><?php endif; ?>
    </p>
  </div>
</section>

<section>
  <div class="container">
    <div class="movie-detail-layout">

      <!-- Poster -->
      <div class="movie-detail-poster">
        <?php if (!empty($movie['poster_path'])): ?>
          <img src="assets/<?= e($movie['poster_path']) ?>" alt="<?= e($movie['title']) ?> movie poster" loading="eager">
        <?php else: ?>
          <div class="movie-poster-placeholder"><?= e($movie['title']) ?></div>
        <?php endif; ?>
        <?php if (!empty($movie['rating'])): ?>
          <span class="movie-rating" style="margin-top:0.75rem; display:inline-block;"><?= e($movie['rating']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="movie-detail-info">
        <?php if (!empty($movie['description'])): ?>
          <p class="movie-detail-desc"><?= e($movie['description']) ?></p>
        <?php endif; ?>

        <?php if ($movie['screen'] === 'small' || $movie['online_only']): ?>
          <div class="highlight-box" style="margin-bottom:1.5rem;">
            <p><strong>Online purchase required</strong> for this film. Tickets for the small screen must be purchased online before your visit.</p>
          </div>
        <?php endif; ?>

        <?php if (!empty($showtimes)): ?>
          <div class="section-header">
            <p class="section-label">This Week</p>
            <h2 class="section-title" style="font-size:1.6rem;">Showtimes</h2>
            <div class="section-divider"></div>
          </div>
          <div class="showtime-list">
            <?php foreach ($showtimes as $st): ?>
              <div class="showtime-row">
                <span class="showtime-day"><?= e($st['label']) ?></span>
                <span class="showtime-times"><?= e($st['times']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="policy-box" style="margin-bottom:1.5rem;">
            <h3>Showtimes</h3>
            <p>Showtimes for this film are not yet listed online. Call us at <a href="tel:765-620-9093">(765) 620-9093</a> to confirm times before your visit.</p>
          </div>
        <?php endif; ?>

        <div class="movie-cta" style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap;">
          <a href="https://the-alexandria-theatre.square.site/" target="_blank" rel="noopener" class="btn btn-crimson">Buy Tickets</a>
          <a href="index.php#now-showing" class="btn btn-outline">All Movies</a>
        </div>

        <div class="policy-box mt-3">
          <h3>Showtime Policy</h3>
          <p>The theatre reserves the right to adjust showtimes, screens, or auditoriums based on equipment issues or when ticket sales exceed 20 seats.</p>
        </div>
      </div>

    </div>
  </div>
</section>

<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
