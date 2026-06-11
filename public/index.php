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

// Fall back to static demo data when the DB is unavailable (e.g. static preview).
$hasDbMovies = !empty($nowShowing);
if (!$hasDbMovies) {
    $nowShowing = [
        [
            'id' => 1,
            'title' => 'Star Wars: The Mandalorian & Grogu',
            'rating' => 'PG-13',
            'screen' => 'large',
            'poster_path' => 'images/starwars.jpg',
            'online_only' => false,
            'showtimes' => [
                ['label' => 'Thu, May 22', 'times' => '4:00 · 7:15 PM'],
                ['label' => 'Fri–Sun, May 23–25', 'times' => '1:00 · 4:00 · 7:15 PM'],
                ['label' => 'Memorial Day', 'times' => '1:00 · 4:00 PM'],
            ],
        ],
        [
            'id' => 2,
            'title' => 'The Sheep Detectives',
            'rating' => 'PG',
            'screen' => 'small',
            'poster_path' => 'images/sheep.jpg',
            'online_only' => true,
            'showtimes' => [
                ['label' => 'Thu, May 22', 'times' => '4:30 · 7:30 PM'],
                ['label' => 'Fri–Sun, May 23–25', 'times' => '1:30 · 4:30 · 7:30 PM'],
                ['label' => 'Memorial Day', 'times' => '1:30 · 4:30 PM'],
            ],
        ],
    ];
}

require TEMPLATES_PATH . '/header.php';
?>

<!-- Hero — the white Chevelle out front of the marquee -->
<section class="hero-cinematic">
    <div class="hero-bg">
        <img src="<?= asset('images/hero-4.png') ?>" alt="The Alex marquee with a classic white Chevelle parked out front in downtown Alexandria, Indiana" loading="eager" fetchpriority="high">
    </div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <p class="hero-eyebrow-split">Alexandria, Indiana &bull; Independent Cinema</p>
        <h1 class="hero-headline">Real Movies.<br>Five Dollar<br>Tickets.</h1>
        <p class="hero-sub">Your neighborhood two-screen theater since the marquee was new.</p>
        <div class="hero-actions">
            <a href="#now-showing" class="btn btn-crimson">See This Week's Showtimes</a>
            <a href="<?= url('private-screenings.php') ?>" class="btn btn-outline-hero">Book the Theatre</a>
        </div>
    </div>
</section>

<!-- Info Bar -->
<div class="info-bar">
    <div class="info-bar-items">
        <span>Adults $5</span>
        <span class="info-bar-sep">&bull;</span>
        <span>Kids 12 &amp; Under $3</span>
        <span class="info-bar-sep info-bar-address-sep">&bull;</span>
        <span class="info-bar-address">407 N. Harrison St, Alexandria IN</span>
        <span class="info-bar-sep">&bull;</span>
        <span><a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a></span>
    </div>
</div>

<!-- Now Showing -->
<section id="now-showing" class="now-showing-section">
    <div class="container">
        <div class="section-header">
            <p class="section-label">On the Screen This Week</p>
            <h2 class="section-title">Now Showing</h2>
            <div class="section-divider"></div>
        </div>
    </div>

    <div class="container">
        <div class="movie-carousel" data-carousel>
            <button type="button" class="carousel-arrow carousel-arrow-prev" aria-label="Scroll to previous movies" data-carousel-prev>&#8249;</button>
            <div class="carousel-track" data-carousel-track>
                <?php foreach ($nowShowing as $movie): ?>
                    <?php
                        $id = (int) ($movie['id'] ?? 0);
                        $screen = (string) ($movie['screen'] ?? 'either');
                        $screenLabel = $screen === 'large'
                            ? 'Large Screen'
                            : ($screen === 'small' ? 'Small Screen' : '');
                        $posterPath = (string) ($movie['poster_path'] ?? '');
                        $title = (string) ($movie['title'] ?? '');
                        $rating = (string) ($movie['rating'] ?? '');
                        $showtimes = $movie['showtimes'] ?? [];
                        $detailUrl = $hasDbMovies ? url('movie.php?id=' . $id) : TICKETS_URL;
                    ?>
                    <a class="poster-card" href="<?= e($detailUrl) ?>">
                        <div class="poster-card-media">
                            <?php if ($screenLabel !== ''): ?>
                                <span class="screen-badge"><?= e($screenLabel) ?></span>
                            <?php endif; ?>
                            <?php if ($posterPath !== ''): ?>
                                <img src="<?= e(asset($posterPath)) ?>" alt="<?= e($title) ?> movie poster" loading="eager">
                            <?php else: ?>
                                <div class="movie-poster-placeholder"><?= e($title) ?></div>
                            <?php endif; ?>
                            <span class="poster-card-overlay"><span class="poster-card-cta">Showtimes &amp; Tickets &rarr;</span></span>
                        </div>
                        <div class="poster-card-info">
                            <div class="poster-card-head">
                                <?php if ($rating !== ''): ?>
                                    <span class="movie-rating"><?= e($rating) ?></span>
                                <?php endif; ?>
                                <h3 class="poster-card-title"><?= e($title) ?></h3>
                            </div>
                            <?php if (!empty($showtimes)): ?>
                                <ul class="poster-card-times">
                                    <?php foreach (array_slice($showtimes, 0, 3) as $st): ?>
                                        <li>
                                            <span class="st-day"><?= e((string) ($st['label'] ?? '')) ?></span>
                                            <span class="st-times"><?= e((string) ($st['times'] ?? '')) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="carousel-arrow carousel-arrow-next" aria-label="Scroll to more movies" data-carousel-next>&#8250;</button>
        </div>

        <div class="policy-box mt-3">
            <h3>Showtime Policy</h3>
            <p>The theatre reserves the right to adjust showtimes, screens, or auditoriums based on equipment issues or when ticket sales for a particular film exceed 20 seats. We appreciate your flexibility and understanding.</p>
        </div>
    </div>
</section>

<!-- Google Reviews -->
<section class="home-reviews-section">
    <div class="container">
        <div class="section-header centered">
            <p class="section-label">What Our Neighbors Say</p>
            <h2 class="section-title">Loved by Alexandria</h2>
            <div class="section-divider centered"></div>
        </div>

        <div class="reviews-grid">
            <div class="review-card">
                <div class="review-stars">★★★★★</div>
                <p class="review-text">What a great budget friendly place to take the family to see a movie! Even the concessions are budget friendly.</p>
                <div class="review-meta"><span class="review-name">Jackie</span><span class="review-time">2 months ago</span></div>
            </div>
            <div class="review-card">
                <div class="review-stars">★★★★★</div>
                <p class="review-text">We love this historical movie theater. Staff and owners are super nice. Great hospitality.</p>
                <div class="review-meta"><span class="review-name">Robin</span><span class="review-time">3 months ago</span></div>
            </div>
            <div class="review-card">
                <div class="review-stars">★★★★★</div>
                <p class="review-text">I love the vintage feel of this place. The prices are amazing and it's never too busy.</p>
                <div class="review-meta"><span class="review-name">Brian</span><span class="review-time">7 months ago</span></div>
            </div>
            <div class="review-card">
                <div class="review-stars">★★★★★</div>
                <p class="review-text">Really neat small town mom and pop operation. First run movies at second run pricing — worth the trip from surrounding counties.</p>
                <div class="review-meta"><span class="review-name">Jim</span><span class="review-time">a year ago</span></div>
            </div>
        </div>
        <p class="reviews-attribution">Reviews from Google</p>
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

<!-- Senior Movie Teaser -->
<section class="senior-teaser-section">
    <div class="container">
        <div class="senior-teaser">
            <div class="senior-teaser-text">
                <p class="section-label">A Gift to the Community</p>
                <h2 class="section-title">Free Movies for Seniors 55+</h2>
                <p>Every month, the Alex partners with Senior Essential Connections to host a free screening for seniors. No ticket, no reservation — just show up and enjoy.</p>
                <a href="<?= url('senior-movie.php') ?>" class="btn btn-crimson">Senior Movie Details</a>
            </div>
        </div>
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
