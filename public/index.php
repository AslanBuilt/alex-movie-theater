<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/MovieRepo.php';

$pageTitle = 'Now Showing | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'See what\'s playing at Alex Movie Theatre in Alexandria, Indiana. Two-screen independent theatre. Adults $5, Children $3. Buy tickets online.';
$pageKeywords = 'now showing Alexandria Indiana, movies playing Alexandria IN, Alex Movie Theatre showtimes, cheap movies Indiana';
$canonical = SITE_URL;

$nowShowing = tryDb(fn() => MovieRepo::getNowShowing());
$comingSoon = tryDb(fn() => MovieRepo::getComingSoon());

require TEMPLATES_PATH . '/header.php';
?>

<!-- Hero -->
<div class="hero-split">
    <div class="hero-text">
        <p class="hero-eyebrow-split">Alexandria, Indiana &bull; Independent Cinema</p>
        <h1 class="hero-headline">Real Movies.<br>Five Dollar<br>Tickets.</h1>
        <p class="hero-sub">Your neighborhood two-screen theater since the marquee was new.</p>
        <div class="hero-actions">
            <a href="#now-showing" class="btn btn-crimson">See This Week's Showtimes</a>
            <a href="<?= url('private-screenings.php') ?>" class="btn btn-outline">Book the Theatre</a>
        </div>
    </div>
    <div class="hero-image">
        <?php
            $heroImgPath = '';
            foreach (['hero-theater.png', 'hero-theater.jpg', 'hero-theater.webp'] as $heroFile) {
                if (file_exists(__DIR__ . '/assets/images/' . $heroFile)) {
                    $heroImgPath = $heroFile;
                    break;
                }
            }
        ?>
        <?php if ($heroImgPath !== ''): ?>
            <img src="<?= asset('images/' . e($heroImgPath)) ?>" alt="Alex Theatre exterior, Alexandria Indiana">
        <?php else: ?>
            <div class="hero-placeholder">
                <div class="hero-placeholder-inner">
                    <div class="hero-placeholder-title">Alex<br>Theatre</div>
                    <div class="hero-placeholder-sub">407 N. Harrison St &bull; Alexandria, IN</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Info Bar -->
<div class="info-bar">
    <div class="info-bar-items">
        <span>Adults · $5</span>
        <span class="info-bar-sep">&bull;</span>
        <span>Kids 12 &amp; Under · $3</span>
        <span class="info-bar-sep">&bull;</span>
        <span>407 N. Harrison St, Alexandria IN</span>
        <span class="info-bar-sep">&bull;</span>
        <span><a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a></span>
    </div>
</div>

<!-- Now Showing -->
<section id="now-showing">
    <div class="container">
        <div class="section-header">
            <p class="section-label">On the Screen This Week</p>
            <h2 class="section-title">Now Showing</h2>
            <div class="section-divider"></div>
        </div>

        <?php if (!empty($nowShowing)): ?>
        <div class="movies-grid">
            <?php foreach ($nowShowing as $movie): ?>
                <?php
                    $screen = (string) ($movie['screen'] ?? 'either');
                    $screenLabel = $screen === 'large'
                        ? 'Large Screen'
                        : ($screen === 'small' ? 'Small Screen' : '');
                    $posterPath = (string) ($movie['poster_path'] ?? '');
                    $title = (string) ($movie['title'] ?? '');
                    $rating = (string) ($movie['rating'] ?? '');
                    $onlineOnly = !empty($movie['online_only']);
                    $showtimes = $movie['showtimes'] ?? [];
                ?>
            <div class="movie-card">
                <div class="movie-poster">
                    <?php if ($screenLabel !== ''): ?>
                        <span class="screen-badge"><?= e($screenLabel) ?></span>
                    <?php endif; ?>
                    <?php if ($posterPath !== ''): ?>
                        <img src="<?= e(asset($posterPath)) ?>" alt="<?= e($title) ?> movie poster" loading="eager">
                    <?php else: ?>
                        <div class="movie-poster-placeholder"><?= e($title) ?></div>
                    <?php endif; ?>
                </div>
                <div class="movie-card-body">
                    <?php if ($rating !== ''): ?>
                        <span class="movie-rating"><?= e($rating) ?></span>
                    <?php endif; ?>
                    <h2 class="movie-title"><?= e($title) ?></h2>

                    <?php if (!empty($showtimes)): ?>
                        <p class="showtimes-label">Showtimes</p>
                        <?php foreach ($showtimes as $st): ?>
                            <div class="showtime-row">
                                <span class="showtime-day"><?= e((string) ($st['label'] ?? '')) ?></span>
                                <span class="showtime-times"><?= e((string) ($st['times'] ?? '')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="movie-cta">
                        <a href="<?= e(TICKETS_URL) ?>" class="btn btn-crimson" target="_blank" rel="noopener">Buy Tickets</a>
                        <?php if ($onlineOnly): ?>
                            <span class="online-required">Small screen tickets must be purchased online</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
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
                        <a href="<?= e(TICKETS_URL) ?>" class="btn btn-crimson" target="_blank" rel="noopener">Buy Tickets</a>
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
                        <a href="<?= e(TICKETS_URL) ?>" class="btn btn-crimson" target="_blank" rel="noopener">Buy Tickets</a>
                        <span class="online-required">Small screen tickets must be purchased online</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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

        <?php if (!empty($comingSoon)): ?>
        <div class="coming-soon-grid">
            <?php foreach ($comingSoon as $movie): ?>
                <div class="coming-soon-card">
                    <div class="film-title"><?= e((string) ($movie['title'] ?? '')) ?></div>
                    <div class="film-status">Coming Soon</div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
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
        <?php endif; ?>
    </div>
</section>

<!-- Photo Gallery -->
<section class="gallery-section">
    <div class="container">
        <div class="section-header centered">
            <p class="section-label">Life at the Alex</p>
            <h2 class="section-title">Inside the Theatre</h2>
            <div class="section-divider centered"></div>
        </div>
    </div>

    <div class="photo-slideshow">
        <div class="slideshow-track">
            <div class="slideshow-slide">
                <img src="<?= asset('images/gallery/gallery-1.png') ?>" alt="Alex Theatre exterior, daytime">
                <div class="slideshow-caption">407 N. Harrison St · Alexandria, Indiana</div>
            </div>
            <div class="slideshow-slide">
                <img src="<?= asset('images/gallery/gallery-2.png') ?>" alt="Alex Theatre exterior with classic car">
                <div class="slideshow-caption">The Alex · A Neighborhood Landmark</div>
            </div>
            <div class="slideshow-slide">
                <img src="<?= asset('images/gallery/gallery-3.png') ?>" alt="Alex Theatre auditorium interior">
                <div class="slideshow-caption">Two Screens · Classic Movie Experience</div>
            </div>
            <div class="slideshow-slide">
                <img src="<?= asset('images/gallery/gallery-4.png') ?>" alt="Alex Theatre at night with neon sign">
                <div class="slideshow-caption">Open Every Weekend · Come See a Show</div>
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
                <p>Phone: <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a><br><br>
                Follow us on Facebook and Instagram for the latest updates and announcements.</p>
            </div>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
