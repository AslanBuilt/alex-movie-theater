<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/EventRepo.php';

$pageTitle = 'Free Senior Movie | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Free movie screenings for seniors 55 and up at Alex Movie Theatre in Alexandria, Indiana. Sponsored by Senior Essential Connections.';
$pageKeywords = 'free senior movie Alexandria Indiana, senior cinema Indiana, free movies for seniors, Senior Essential Connections';
$canonical = SITE_URL . 'senior-movie.php';

$next = tryDb(fn() => EventRepo::getNextSeniorShowing(), null);

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Senior Movie</p>
        <h1>Free Senior Movie</h1>
        <p class="subtitle">A community gift for seniors 55 and up · no ticket required.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="senior-badge">Free Admission &bull; Ages 55 &amp; Up &bull; Sponsored by Senior Essential Connections</div>

        <div class="senior-banner">
            <img src="<?= asset('images/hero-3.png') ?>" alt="Alex Theatre auditorium interior" loading="lazy">
        </div>

        <div class="two-col">
            <div>
                <div class="section-header">
                    <p class="section-label">About the Program</p>
                    <h2 class="section-title">Free for Seniors 55+</h2>
                    <div class="section-divider"></div>
                </div>

                <p style="color:var(--text-secondary); margin-bottom:1.75rem; line-height:1.8;">
                    The Alex Theatre is proud to partner with <strong class="text-crimson">Senior Essential Connections</strong> to bring free monthly screenings to the community. Every senior 55 or older is welcome at no charge &mdash; no reservations needed, just show up and enjoy.
                </p>

                <div class="highlight-box">
                    <p><strong>Who qualifies?</strong> Any senior citizen 55 years of age or older. No ID check required &mdash; just arrive and take your seat.</p>
                </div>

                <div class="highlight-box" style="margin-top:1rem;">
                    <p><strong>No outside food or drinks.</strong> Our concession stand will be open during the screening.</p>
                </div>

                <div class="info-card mt-3">
                    <h3>Questions?</h3>
                    <p>Contact Senior Essential Connections or call the theatre at <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a>.</p>
                </div>

                <div class="reviews-section">
                    <div class="reviews-header">
                        <span class="reviews-label">Google Reviews</span>
                        <span class="reviews-google-badge">via Google</span>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Jackie</span>
                            <span class="review-time">2 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">What a great budget friendly place to take the family to see a movie! Even the concessions are budget friendly.</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Robin</span>
                            <span class="review-time">3 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">We love this historical movie theater. Staff and owners are super nice. Great hospitality.</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Tony</span>
                            <span class="review-time">5 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">Clean vintage type theatre with more of today's amenities. Off street parking, clean and nice. Level low ground facility and no stairs.</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Devan</span>
                            <span class="review-time">8 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">I just love it here — we bring the kids and have a wonderful time. It's affordable to go more than once every few months with 2 adults and 3 kids.</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Lil Miracles</span>
                            <span class="review-time">10 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">We had such a great time! A bunch of our volunteers went to see a movie and will be going back. You just can't beat the price.</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Kaitlyn</span>
                            <span class="review-time">6 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">I was pleasantly surprised. I really like this little theater and the staff are amazing!</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Melissa</span>
                            <span class="review-time">4 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">Always a good time but definitely bring a blanket because it is freezing in the theater.</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Brian</span>
                            <span class="review-time">7 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">I love the vintage feel of this place. The prices are amazing and it's never too busy.</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Stephanie</span>
                            <span class="review-time">11 months ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">It's the most nostalgic adorable theater!! Really feels like a comfy cozy living room.</p>
                    </div>

                    <div class="review-item">
                        <div class="review-meta">
                            <span class="review-name">Jim</span>
                            <span class="review-time">a year ago</span>
                        </div>
                        <div class="review-stars">★★★★★</div>
                        <p class="review-text">Really neat small town mom and pop operation. Prices are super reasonable. First run movies at second run pricing — worth making the trip from surrounding counties.</p>
                    </div>
                </div>
            </div>

            <div>
                <div class="section-header">
                    <p class="section-label">Upcoming Screening</p>
                    <h2 class="section-title">Next Showing</h2>
                    <div class="section-divider"></div>
                </div>

                <?php if ($next !== null && (string) ($next['status'] ?? '') === 'upcoming'): ?>
                    <?php
                        $title = (string) ($next['movie_title'] ?? '');
                        $rawDate = $next['showing_date'] ?? null;
                        $ts = $rawDate ? strtotime((string) $rawDate) : false;
                        $dateLine = $ts ? date('l, F j, Y', $ts) : 'Date to be announced';
                        $time = (string) ($next['showing_time'] ?? '');
                        $notes = (string) ($next['notes'] ?? '');
                    ?>
                    <div class="next-showing-card">
                        <div class="film-name"><?= e($title !== '' ? $title : 'TBA — Check Back Soon') ?></div>
                        <div class="film-meta">
                            <p style="margin-top:0.5rem;"><?= e($dateLine) ?><?php if ($time !== ''): ?> · <?= e($time) ?><?php endif; ?></p>
                            <?php if ($notes !== ''): ?>
                                <p style="margin-top:0.5rem;"><?= e($notes) ?></p>
                            <?php else: ?>
                                <p style="margin-top:0.5rem;">Hosted by Senior Essential Connections</p>
                            <?php endif; ?>
                        </div>
                        <p style="margin-top:1rem; font-size:0.8rem; color:var(--text-muted);">Contact Senior Essential Connections or call us at <a href="tel:765-620-9093">765-620-9093</a> for the current schedule.</p>
                    </div>
                <?php else: ?>
                    <div class="next-showing-card">
                        <div class="film-name">TBA — Check Back Soon</div>
                        <div class="film-meta">
                            <p style="margin-top:0.5rem;">Date &amp; film to be announced</p>
                            <p style="margin-top:0.5rem;">Hosted by Senior Essential Connections</p>
                        </div>
                        <p style="margin-top:1rem; font-size:0.8rem; color:var(--text-muted);">Contact Senior Essential Connections or call us at <a href="tel:765-620-9093">765-620-9093</a> for the current schedule.</p>
                    </div>
                <?php endif; ?>

                <div class="info-card">
                    <h3>About Senior Essential Connections</h3>
                    <p>Senior Essential Connections is an organization dedicated to supporting and enriching the lives of older adults in the Alexandria community. They sponsor this free movie program as part of their mission to keep seniors engaged and connected.</p>
                </div>

                <div class="location-photo mt-2">
                    <img src="<?= asset('images/hero-1.png') ?>" alt="Alex Theatre exterior" loading="lazy">
                </div>

                <div class="info-card">
                    <h3>Theatre Location</h3>
                    <p><?= e(SITE_ADDRESS) ?><br><br>
                    Parking available in the adjacent gravel lot, along the street, and at Horners Grocery.</p>
                    <a href="<?= url('location.php') ?>" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem;">Get Directions</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
