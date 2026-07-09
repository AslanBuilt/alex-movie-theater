<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/EventRepo.php';

$pageTitle = 'Free Senior Movie | The Alex — Alexandria, Indiana';
$pageDescription = 'Free monthly movie screenings for seniors 55 and up at The Alex in Alexandria, Indiana. No reservation needed. Sponsored by Senior Essential Connections.';
$currentPage = 'senior-movie';

$nextShowing = tryDb(fn () => EventRepo::getNextSeniorShowing(), null);

require __DIR__ . '/templates/header.php';
?>

<section class="page-hero page-hero--photo" style="background-image: linear-gradient(rgba(250,245,235,0.87),rgba(250,245,235,0.87)), url('assets/images/hero-3.webp')">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Senior Movie</p>
    <h1>Free Senior Movie</h1>
    <p class="subtitle">A community gift for seniors 55 and up &bull; no ticket required.</p>
  </div>
</section>

<!-- ── Free for Seniors 55+ ── -->
<section>
  <div class="container">
    <div class="senior-badge">Free Admission &bull; Ages 55 &amp; Up &bull; Sponsored by Senior Essential Connections</div>

    <div class="senior-banner">
      <img src="assets/images/hero-3.webp" alt="The Alex auditorium interior" loading="lazy">
    </div>

    <div class="section-header">
      <p class="section-label">Next Screening</p>
      <h2 class="section-title">Coming Up</h2>
      <div class="section-divider"></div>
    </div>

    <?php if ($nextShowing !== null) :
        $movieTitle = trim((string)($nextShowing['movie_title'] ?? ''));
        $status     = (string)($nextShowing['status'] ?? 'tba');
        $dateStr    = '';
        if (!empty($nextShowing['showing_date'])) {
            $ts = strtotime((string)$nextShowing['showing_date']);
            if ($ts !== false) {
                $dateStr = date('l, F j, Y', $ts);
            }
        }
        $timeStr = trim((string)($nextShowing['showing_time'] ?? ''));
        $notes   = trim((string)($nextShowing['notes'] ?? ''));
    ?>
      <div class="info-card" style="max-width:700px; margin-bottom:2.5rem;">
        <h3><?= $movieTitle !== '' ? e($movieTitle) : 'Movie to be announced' ?></h3>
        <p>
          <?php if ($status === 'tba' || $dateStr === '') : ?>
            Date &amp; time to be announced &mdash; check back soon or call the theatre.
          <?php else : ?>
            <?= e($dateStr) ?><?= $timeStr !== '' ? ' &middot; ' . e($timeStr) : '' ?>
          <?php endif; ?>
        </p>
        <?php if ($notes !== '') : ?>
          <p style="color:var(--text-secondary);"><?= nl2br(e($notes)) ?></p>
        <?php endif; ?>
      </div>
    <?php else : ?>
      <div class="info-card" style="max-width:700px; margin-bottom:2.5rem;">
        <h3>Check back soon</h3>
        <p>We haven&rsquo;t announced our next free senior screening yet &mdash; call the theatre at <a href="tel:765-620-9093">(765) 620-9093</a> or check back here.</p>
      </div>
    <?php endif; ?>

    <div class="section-header">
      <p class="section-label">About the Program</p>
      <h2 class="section-title">Free for Seniors 55+</h2>
      <div class="section-divider"></div>
    </div>

    <p style="color:var(--text-secondary); margin-bottom:1.75rem; line-height:1.8; max-width:700px;">
      The Alex is proud to partner with <strong class="text-crimson">Senior Essential Connections</strong> to bring free monthly screenings to the community. Every senior 55 or older is welcome at no charge &mdash; no reservations needed, just show up and enjoy.
    </p>

    <div class="info-grid" style="max-width:800px;">
      <div class="info-card">
        <h3>Who Qualifies?</h3>
        <p>Any senior citizen 55 years of age or older. No ID check required &mdash; just arrive and take your seat.</p>
      </div>
      <div class="info-card">
        <h3>Concessions</h3>
        <p>No outside food or drinks. Our concession stand will be open during the screening with affordable snacks and drinks.</p>
      </div>
    </div>

  </div>
</section>

<!-- ── Senior Essential Connections ── -->
<section style="background:var(--cream); border-top:1px solid var(--border);">
  <div class="container">
    <div class="section-header">
      <p class="section-label">Our Sponsor</p>
      <h2 class="section-title">Senior Essential Connections</h2>
      <div class="section-divider"></div>
    </div>

    <p style="color:var(--text-secondary); line-height:1.8; max-width:680px;">
      Senior Essential Connections is an organization dedicated to supporting and enriching the lives of older adults in the Alexandria community. They sponsor this free movie program as part of their mission to keep seniors engaged and connected.
    </p>

    <div class="info-card mt-3" style="max-width:480px;">
      <h3>Contact</h3>
      <p class="senior-contact-phone"><a href="tel:765-620-9093">(765) 620-9093</a></p>
      <p style="margin-top:0.5rem; color:var(--text-secondary);">Call the theatre to confirm the next screening date or reach out to Senior Essential Connections directly.</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
