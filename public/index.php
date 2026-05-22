<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Now Showing | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'See what\'s playing at Alex Movie Theatre in Alexandria, Indiana. Two-screen independent theatre. Adults $5, Children $3. Buy tickets online.';
$pageKeywords = 'now showing Alexandria Indiana, movies playing Alexandria IN, Alex Movie Theatre showtimes, cheap movies Indiana';
$canonical = SITE_URL;

require TEMPLATES_PATH . '/header.php';
?>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <p class="hero-eyebrow">Alexandria, Indiana &bull; Est. Independent Cinema</p>
        <h1>The <span>Alex</span> Theatre</h1>
        <p class="hero-tagline">Your community's two-screen independent movie house &mdash; affordable tickets, real movies.</p>
        <div class="hero-rule"></div>
        <div class="hero-actions">
            <a href="<?= SQUARE_URL ?>" class="btn btn-crimson" target="_blank" rel="noopener">Buy Tickets Online</a>
            <a href="<?= url('location.php') ?>" class="btn btn-outline">Location &amp; Parking</a>
        </div>
    </div>
</section>

<!-- Pricing Strip -->
<div class="info-strip">
    <div class="container">
        <div class="strip-items">
            <span>Adults &mdash; <strong>$5</strong></span>
            <span class="sep">|</span>
            <span>Children &mdash; <strong>$3</strong></span>
            <span class="sep">|</span>
            <span>&#x260E; <a href="tel:<?= SITE_PHONE ?>" style="color:inherit"><?= e(SITE_PHONE) ?></a></span>
            <span class="sep">|</span>
            <span>407 N. Harrison St, Alexandria IN</span>
        </div>
    </div>
</div>

<!-- Now Showing -->
<section>
    <div class="container">
        <div class="section-header">
            <p class="section-label">On the Screen This Week</p>
            <h2 class="section-title">Now Showing</h2>
            <div class="section-divider"></div>
        </div>

        <div class="movies-grid">
            <!-- Movie 1: Large Screen -->
            <div class="movie-card">
                <div class="movie-poster">
                    <span class="screen-badge">Large Screen</span>
                    <img src="<?= asset('images/starwars.jpg') ?>" alt="Star Wars: The Mandalorian &amp; Grogu movie poster" loading="eager">
                </div>
                <div class="movie-card-body">
                    <span class="movie-rating">PG-13</span>
                    <h2 class="movie-title">Star Wars: The Mandalorian &amp; Grogu</h2>

                    <p class="showtimes-label">Showtimes</p>

                    <div class="showtime-row">
                        <span class="showtime-day">Thursday, May 22</span>
                        <span class="showtime-times">4:00 PM &bull; 7:15 PM</span>
                    </div>
                    <div class="showtime-row">
                        <span class="showtime-day">Fri–Sun, May 23–25</span>
                        <span class="showtime-times">1:00 &bull; 4:00 &bull; 7:15 PM</span>
                    </div>
                    <div class="showtime-row">
                        <span class="showtime-day">Memorial Day, May 26</span>
                        <span class="showtime-times">1:00 PM &bull; 4:00 PM</span>
                    </div>

                    <div class="movie-cta">
                        <a href="<?= SQUARE_URL ?>" class="btn btn-crimson" target="_blank" rel="noopener">Buy Tickets</a>
                    </div>
                </div>
            </div>

            <!-- Movie 2: Small Screen -->
            <div class="movie-card">
                <div class="movie-poster">
                    <span class="screen-badge">Small Screen</span>
                    <img src="<?= asset('images/sheep.jpg') ?>" alt="The Sheep Detectives movie poster" loading="eager">
                </div>
                <div class="movie-card-body">
                    <span class="movie-rating">PG</span>
                    <h2 class="movie-title">The Sheep Detectives</h2>

                    <p class="showtimes-label">Showtimes</p>

                    <div class="showtime-row">
                        <span class="showtime-day">Thursday, May 22</span>
                        <span class="showtime-times">4:30 PM &bull; 7:30 PM</span>
                    </div>
                    <div class="showtime-row">
                        <span class="showtime-day">Fri–Sun, May 23–25</span>
                        <span class="showtime-times">1:30 &bull; 4:30 &bull; 7:30 PM</span>
                    </div>
                    <div class="showtime-row">
                        <span class="showtime-day">Memorial Day, May 26</span>
                        <span class="showtime-times">1:30 PM &bull; 4:30 PM</span>
                    </div>

                    <div class="movie-cta">
                        <a href="<?= SQUARE_URL ?>" class="btn btn-crimson" target="_blank" rel="noopener">Buy Tickets</a>
                        <span class="online-required">&#x26A0; Small screen tickets must be purchased online</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="policy-box mt-3">
            <h3>Showtime Policy</h3>
            <p>The theatre reserves the right to adjust showtimes, screens, or auditoriums based on equipment issues or when ticket sales for a particular film exceed 20 seats. We appreciate your flexibility and understanding.</p>
        </div>
    </div>
</section>

<!-- Coming Soon -->
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

<!-- Pricing -->
<section class="pricing-section">
    <div class="container">
        <div class="section-header centered">
            <p class="section-label">Affordable for Everyone</p>
            <h2 class="section-title">Ticket Prices</h2>
            <div class="section-divider centered"></div>
        </div>

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

        <p class="text-secondary" style="text-align:center; font-size:0.875rem;">
            Purchase tickets at the door or online at Square for the small screen.
        </p>
    </div>
</section>

<!-- Quick Info -->
<section style="background:var(--bg-secondary); border-top:1px solid var(--border);">
    <div class="container">
        <div class="section-header centered">
            <p class="section-label">Everything You Need to Know</p>
            <h2 class="section-title">Visit the Alex</h2>
            <div class="section-divider centered"></div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>&#x1F4CD; Location</h3>
                <p>407 N. Harrison Street<br>Alexandria, IN 46001<br><br>5 blocks north of W. Washington St, 6 blocks west of State Rd 9.</p>
            </div>
            <div class="info-card">
                <h3>&#x1F3AA; What We Offer</h3>
                <ul>
                    <li>Two-screen independent theatre</li>
                    <li>Free senior movies (55+)</li>
                    <li>Private screenings &amp; rentals</li>
                    <li>Concession stand</li>
                    <li>Birthday party packages</li>
                </ul>
            </div>
            <div class="info-card">
                <h3>&#x1F4DE; Contact</h3>
                <p>Phone: <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a><br><br>
                Follow us on Facebook and Instagram for the latest updates and announcements.</p>
            </div>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
