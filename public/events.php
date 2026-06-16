<?php
$pageTitle = 'Events | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Special events at Alex Movie Theatre in Alexandria, Indiana. Escape room experiences, special screenings, and more.';
$currentPage = 'events';
require __DIR__ . '/templates/header.php';
?>

<section class="page-hero">
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

    <div class="movie-card" style="max-width:600px; margin-bottom:3rem;">
      <div class="movie-poster">
        <span class="screen-badge" style="background:var(--crimson-dark);">Coming Soon</span>
        <img src="assets/images/escape-room.png" alt="Escape From The Lockdown Theatre" loading="lazy">
      </div>
      <div class="movie-card-body">
        <h2 class="movie-title">Escape From The &ldquo;Lockdown Theatre&rdquo;</h2>
        <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1rem; line-height:1.7;">
          An immersive escape room experience set inside the Alex Theatre itself. Details coming soon · follow our social media for the announcement.
        </p>
        <p style="color:var(--text-muted); font-size:0.8rem; margin-bottom:1.25rem;">Date &amp; details to be announced</p>
        <div class="social-links">
          <a href="https://www.facebook.com/TheAlexandriaTheatre" target="_blank" rel="noopener">Follow on Facebook</a>
          <a href="https://www.instagram.com/the.alextheatre" target="_blank" rel="noopener">Follow on Instagram</a>
        </div>
      </div>
    </div>

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
