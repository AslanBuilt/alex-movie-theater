<?php
$pageTitle = 'Now Showing | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'See what\'s playing at Alex Movie Theatre in Alexandria, Indiana. Two-screen independent theatre. Adults $5, Children $3.';
$currentPage = 'index';
require __DIR__ . '/templates/header.php';
?>

<!-- Cinematic Hero Slideshow — Chevelle first -->
<div class="hero-cinematic">
  <div class="photo-slideshow hero-slideshow">
    <div class="slideshow-track">
      <div class="slideshow-slide active">
        <img src="assets/images/hero-4.png" alt="Classic white Chevelle outside the Alex Theatre" loading="eager">
      </div>
      <div class="slideshow-slide">
        <img src="assets/images/hero-1.png" alt="Alex Theatre exterior, daytime" loading="eager">
      </div>
      <div class="slideshow-slide">
        <img src="assets/images/hero-2.png" alt="Alex Theatre at night with neon sign" loading="lazy">
      </div>
      <div class="slideshow-slide">
        <img src="assets/images/hero-3.png" alt="Alex Theatre auditorium interior" loading="lazy">
      </div>
    </div>
    <button class="slideshow-btn slideshow-btn-prev" aria-label="Previous photo">&#8249;</button>
    <button class="slideshow-btn slideshow-btn-next" aria-label="Next photo">&#8250;</button>
    <div class="slideshow-dots">
      <button class="slideshow-dot active" aria-label="Photo 1"></button>
      <button class="slideshow-dot" aria-label="Photo 2"></button>
      <button class="slideshow-dot" aria-label="Photo 3"></button>
      <button class="slideshow-dot" aria-label="Photo 4"></button>
    </div>
  </div>
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <p class="hero-eyebrow-split">Alexandria, Indiana &bull; Independent Cinema</p>
    <h1 class="hero-headline">Real Movies.<br>Five Dollar<br>Tickets.</h1>
    <p class="hero-sub">Your neighborhood two-screen theater since the marquee was new.</p>
    <div class="hero-actions">
      <a href="#now-showing" class="btn btn-crimson">See This Week's Showtimes</a>
      <a href="private-screenings.php" class="btn btn-outline-hero">Book the Theatre</a>
    </div>
  </div>
</div>

<!-- Info Bar -->
<div class="info-bar">
  <div class="info-bar-items">
    <span>Adults $5</span>
    <span class="info-bar-sep">&bull;</span>
    <span>Kids 12 &amp; Under $3</span>
    <span class="info-bar-sep info-bar-address-sep">&bull;</span>
    <span class="info-bar-address">407 N. Harrison St, Alexandria IN</span>
    <span class="info-bar-sep">&bull;</span>
    <span><a href="tel:765-620-9093">(765) 620-9093</a></span>
  </div>
</div>

<!-- ── Now Showing ── -->
<section id="now-showing" class="now-showing-section">
  <div class="container">
    <div class="section-header centered">
      <p class="section-label">On the Screen This Week</p>
      <h2 class="section-title">Now Showing</h2>
      <div class="section-divider centered"></div>
    </div>

    <div class="poster-carousel-wrap">
      <button class="carousel-arrow carousel-prev" aria-label="Previous movies">&#8249;</button>
      <div class="poster-carousel">
        <div class="poster-track">

          <div class="poster-card">
            <a href="movie.php?id=1" class="poster-link">
              <div class="poster-img-wrap">
                <span class="screen-badge">Large Screen</span>
                <img src="assets/images/mandalorian.jpg" alt="Star Wars: The Mandalorian &amp; Grogu" loading="eager">
              </div>
              <div class="poster-card-info">
                <span class="movie-rating">PG-13</span>
                <h3 class="poster-title">Star Wars: The Mandalorian &amp; Grogu</h3>
                <span class="poster-cta">View Showtimes &#8250;</span>
              </div>
            </a>
          </div>

          <div class="poster-card">
            <a href="movie.php?id=2" class="poster-link">
              <div class="poster-img-wrap">
                <span class="screen-badge">Small Screen</span>
                <img src="assets/images/sheep.jpg" alt="The Sheep Detectives" loading="eager">
              </div>
              <div class="poster-card-info">
                <span class="movie-rating">PG</span>
                <h3 class="poster-title">The Sheep Detectives</h3>
                <span class="poster-cta">View Showtimes &#8250;</span>
              </div>
            </a>
          </div>

        </div>
      </div>
      <button class="carousel-arrow carousel-next" aria-label="Next movies">&#8250;</button>
    </div>

    <div class="policy-box mt-3" style="max-width:700px;">
      <h3>Showtime Policy</h3>
      <p>The theatre reserves the right to adjust showtimes, screens, or auditoriums based on equipment issues or when ticket sales for a particular film exceed 20 seats.</p>
    </div>
  </div>
</section>

<!-- ── Google Reviews ── -->
<section class="reviews-home-section">
  <div class="container">
    <div class="section-header centered">
      <p class="section-label">What Guests Are Saying</p>
      <h2 class="section-title">Google Reviews</h2>
      <div class="section-divider centered"></div>
    </div>
    <div class="reviews-grid-home">

      <div class="review-item">
        <div class="review-meta">
          <span class="review-name">Jackie</span>
          <span class="review-time">2 months ago</span>
        </div>
        <div class="review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <p class="review-text">What a great budget friendly place to take the family to see a movie! Even the concessions are budget friendly.</p>
      </div>

      <div class="review-item">
        <div class="review-meta">
          <span class="review-name">Robin</span>
          <span class="review-time">3 months ago</span>
        </div>
        <div class="review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <p class="review-text">We love this historical movie theater. Staff and owners are super nice. Great hospitality.</p>
      </div>

      <div class="review-item">
        <div class="review-meta">
          <span class="review-name">Brian</span>
          <span class="review-time">7 months ago</span>
        </div>
        <div class="review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <p class="review-text">I love the vintage feel of this place. The prices are amazing and it&rsquo;s never too busy.</p>
      </div>

      <div class="review-item">
        <div class="review-meta">
          <span class="review-name">Jim</span>
          <span class="review-time">a year ago</span>
        </div>
        <div class="review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <p class="review-text">Really neat small town mom and pop operation. Prices are super reasonable. First run movies at second run pricing &mdash; worth making the trip from surrounding counties.</p>
      </div>

    </div>
  </div>
</section>

<!-- ── Coming Soon ── -->
<section class="coming-soon-section">
  <div class="container">
    <div class="section-header centered">
      <p class="section-label">Coming to the Alex</p>
      <h2 class="section-title">Coming Soon</h2>
      <div class="section-divider centered"></div>
    </div>
    <div class="coming-soon-grid">
      <div class="coming-soon-card">
        <div class="film-title">The Mummy</div>
        <div class="film-status">Coming Soon</div>
      </div>
      <div class="coming-soon-card">
        <div class="film-title">Toy Story 5</div>
        <div class="film-status">Coming Soon</div>
      </div>
    </div>
  </div>
</section>

<!-- ── Senior Movie Teaser ── -->
<section class="senior-teaser-section">
  <div class="container">
    <div class="senior-teaser-inner">
      <div class="senior-teaser-text">
        <p class="section-label" style="color:rgba(247,243,238,0.7);">Monthly Free Screening</p>
        <h2 class="senior-teaser-heading">Free for Seniors 55+</h2>
        <p class="senior-teaser-sub">Every month, seniors 55 and older attend free &mdash; no reservation, no ticket. Sponsored by Senior Essential Connections.</p>
        <a href="senior-movie.php" class="btn btn-outline-hero" style="margin-top:1.5rem; display:inline-flex;">Learn More</a>
      </div>
      <div class="senior-teaser-badge" aria-hidden="true">
        <span class="badge-free">FREE</span>
        <span class="badge-age">Ages 55+</span>
      </div>
    </div>
  </div>
</section>

<!-- ── Visit the Alex ── -->
<section class="visit-section">
  <div class="container">
    <div class="section-header centered">
      <p class="section-label">Everything You Need to Know</p>
      <h2 class="section-title">Visit the Alex</h2>
      <div class="section-divider centered"></div>
    </div>
    <div class="info-grid">
      <div class="info-card">
        <h3>Location</h3>
        <p>407 N. Harrison Street<br>Alexandria, IN 46001<br><br>5 blocks north of W. Washington St, 6 blocks west of State Rd 9.</p>
        <a href="location.php" class="btn btn-outline" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.45rem 1rem;">Get Directions</a>
      </div>
      <div class="info-card">
        <h3>What We Offer</h3>
        <ul>
          <li>Two-screen independent theatre</li>
          <li>Free senior movies (55+)</li>
          <li>Private screenings &amp; rentals</li>
          <li>Concession stand</li>
          <li>Birthday party packages</li>
        </ul>
      </div>
      <div class="info-card">
        <h3>Contact</h3>
        <p>Phone: <a href="tel:765-620-9093">(765) 620-9093</a><br><br>Follow us on Facebook and Instagram for the latest updates and showtimes.</p>
        <a href="location.php#contact" class="btn btn-outline" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.45rem 1rem;">Send a Message</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
