<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Concessions | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Enjoy concessions at Alex Movie Theatre. Popcorn, drinks, candy and more at prices cheaper than big chain theatres.';
$pageKeywords = 'movie theatre concessions Alexandria Indiana, cheap movie snacks, Alex Theatre food';
$canonical = SITE_URL . 'concessions.php';

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Concessions</p>
        <h1>Concession Stand</h1>
        <p class="subtitle">Cheaper than the big chains &middot; classic movie snacks done right.</p>
    </div>
</section>

<section>
    <div class="container">

        <div class="highlight-box">
            <p><strong>Our prices are cheaper than other theaters</strong> &mdash; but still great quality. Enjoy your favorites without breaking the bank. No outside food or beverages permitted inside the theatre.</p>
        </div>

        <div class="section-header">
            <p class="section-label">Classic Movie Favorites</p>
            <h2 class="section-title">What We Offer</h2>
            <div class="section-divider"></div>
        </div>

        <div class="concession-grid">

            <!-- Popcorn -->
            <div class="concession-card">
                <div class="concession-visual">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="140" height="140" aria-hidden="true">
                        <defs>
                            <clipPath id="pc-bkt">
                                <path d="M12 54 L24 98 L76 98 L88 54Z"/>
                            </clipPath>
                        </defs>
                        <path d="M12 54 L24 98 L76 98 L88 54Z" fill="#C41E3A"/>
                        <g clip-path="url(#pc-bkt)">
                            <rect x="14" y="44" width="13" height="62" fill="white" opacity="0.25" transform="skewX(6)"/>
                            <rect x="48" y="44" width="13" height="62" fill="white" opacity="0.25" transform="skewX(6)"/>
                            <rect x="82" y="44" width="13" height="62" fill="white" opacity="0.25" transform="skewX(6)"/>
                        </g>
                        <rect x="10" y="51" width="80" height="7" rx="3.5" fill="#8B0000"/>
                        <circle cx="18" cy="48" r="10" fill="#FDE68A"/>
                        <circle cx="36" cy="41" r="12" fill="#FEF9C3"/>
                        <circle cx="55" cy="39" r="12" fill="#FBBF24"/>
                        <circle cx="74" cy="46" r="10" fill="#FDE68A"/>
                        <circle cx="27" cy="52" r="11" fill="#FEF3C7"/>
                        <circle cx="47" cy="48" r="12" fill="#FDE68A"/>
                        <circle cx="66" cy="52" r="10" fill="#FEF9C3"/>
                        <circle cx="18" cy="44" r="5" fill="#FFFDE7" opacity="0.65"/>
                        <circle cx="36" cy="37" r="5" fill="#FFFDE7" opacity="0.65"/>
                        <circle cx="55" cy="35" r="5" fill="#FFFDE7" opacity="0.65"/>
                        <circle cx="47" cy="44" r="5" fill="#FFFDE7" opacity="0.65"/>
                    </svg>
                </div>
                <div class="concession-info">
                    <h3>Popcorn</h3>
                    <ul>
                        <li>Fresh-popped buttered popcorn</li>
                        <li>Multiple sizes available</li>
                        <li>The classic theatre experience</li>
                    </ul>
                </div>
            </div>

            <!-- Drinks -->
            <div class="concession-card">
                <div class="concession-visual">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 100" width="112" height="140" aria-hidden="true">
                        <defs>
                            <clipPath id="soda-cup">
                                <path d="M10 32 L18 96 L62 96 L70 32Z"/>
                            </clipPath>
                        </defs>
                        <rect x="54" y="6" width="6" height="54" rx="3" fill="#9CA3AF"/>
                        <rect x="54" y="6" width="2.5" height="54" rx="1.5" fill="white" opacity="0.45"/>
                        <path d="M10 32 L18 96 L62 96 L70 32Z" fill="#E5E7EB"/>
                        <g clip-path="url(#soda-cup)">
                            <rect x="5" y="44" width="75" height="58" fill="#DC2626"/>
                            <rect x="16" y="52" width="13" height="13" rx="2" fill="white" opacity="0.3"/>
                            <rect x="38" y="57" width="11" height="11" rx="2" fill="white" opacity="0.28"/>
                            <rect x="55" y="50" width="10" height="12" rx="2" fill="white" opacity="0.22"/>
                            <circle cx="26" cy="72" r="2.5" fill="white" opacity="0.2"/>
                            <circle cx="45" cy="78" r="2" fill="white" opacity="0.18"/>
                            <circle cx="35" cy="85" r="1.5" fill="white" opacity="0.18"/>
                        </g>
                        <path d="M8 33 Q9 19 40 19 Q71 19 72 33Z" fill="#D1D5DB"/>
                        <line x1="8" y1="33" x2="72" y2="33" stroke="#9CA3AF" stroke-width="1"/>
                        <path d="M13 42 L19 92" stroke="white" stroke-width="3" stroke-linecap="round" opacity="0.12"/>
                        <circle cx="9" cy="52" r="1.5" fill="#9CA3AF" opacity="0.4"/>
                        <circle cx="7" cy="64" r="1.5" fill="#9CA3AF" opacity="0.35"/>
                        <circle cx="10" cy="75" r="1" fill="#9CA3AF" opacity="0.3"/>
                    </svg>
                </div>
                <div class="concession-info">
                    <h3>Drinks</h3>
                    <ul>
                        <li>Fountain sodas</li>
                        <li>Bottled water</li>
                        <li>Various sizes available</li>
                    </ul>
                </div>
            </div>

            <!-- Candy & Snacks -->
            <div class="concession-card">
                <div class="concession-visual">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 110 95" width="147" height="127" aria-hidden="true">
                        <rect x="6" y="30" width="52" height="55" rx="5" fill="#6D28D9"/>
                        <path d="M6 30 L58 30 L58 44 Q32 52 6 44Z" fill="#5B21B6"/>
                        <rect x="6" y="43" width="52" height="4" fill="#5B21B6"/>
                        <rect x="12" y="33" width="40" height="8" rx="2" fill="white" opacity="0.18"/>
                        <circle cx="22" cy="62" r="7" fill="#F59E0B"/>
                        <circle cx="36" cy="57" r="7" fill="#EF4444"/>
                        <circle cx="50" cy="63" r="6" fill="#10B981"/>
                        <circle cx="28" cy="73" r="6" fill="#3B82F6"/>
                        <circle cx="44" cy="75" r="5" fill="#EC4899"/>
                        <circle cx="20" cy="60" r="2.5" fill="white" opacity="0.35"/>
                        <circle cx="34" cy="55" r="2.5" fill="white" opacity="0.35"/>
                        <circle cx="48" cy="61" r="2" fill="white" opacity="0.35"/>
                        <ellipse cx="82" cy="38" rx="11" ry="7" fill="#F59E0B" transform="rotate(-25 82 38)"/>
                        <path d="M72 34 Q75 29 78 34" stroke="#D97706" stroke-width="2" fill="none" stroke-linecap="round"/>
                        <path d="M86 34 Q89 29 92 34" stroke="#D97706" stroke-width="2" fill="none" stroke-linecap="round"/>
                        <ellipse cx="96" cy="58" rx="11" ry="7" fill="#EC4899" transform="rotate(15 96 58)"/>
                        <path d="M86 55 Q89 50 92 55" stroke="#BE185D" stroke-width="2" fill="none" stroke-linecap="round"/>
                        <path d="M100 55 Q103 50 106 55" stroke="#BE185D" stroke-width="2" fill="none" stroke-linecap="round"/>
                        <ellipse cx="78" cy="76" rx="10" ry="6.5" fill="#10B981" transform="rotate(-10 78 76)"/>
                        <path d="M69 73 Q72 68 75 73" stroke="#059669" stroke-width="2" fill="none" stroke-linecap="round"/>
                        <path d="M82 73 Q85 68 88 73" stroke="#059669" stroke-width="2" fill="none" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="concession-info">
                    <h3>Candy &amp; Snacks</h3>
                    <ul>
                        <li>Classic movie candies</li>
                        <li>Packaged snacks</li>
                        <li>Great for the kids</li>
                    </ul>
                </div>
            </div>

            <!-- Hot Items -->
            <div class="concession-card">
                <div class="concession-visual">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 110 100" width="140" height="127" aria-hidden="true">
                        <path d="M38 42 Q35 33 38 24" stroke="#CBD5E1" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                        <path d="M55 38 Q52 27 55 18" stroke="#CBD5E1" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                        <path d="M72 42 Q69 33 72 24" stroke="#CBD5E1" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                        <path d="M14 72 Q55 88 96 72 L96 82 Q55 98 14 82Z" fill="#D97706"/>
                        <path d="M18 60 Q55 55 92 60 L92 70 Q55 75 18 70Z" fill="#92400E"/>
                        <path d="M25 62 Q30 60 35 62 Q40 64 45 62 Q50 60 55 62 Q60 64 65 62 Q70 60 75 62 Q80 64 85 62" stroke="#EF4444" stroke-width="3" fill="none" stroke-linecap="round" opacity="0.85"/>
                        <path d="M28 66 Q35 62 42 66 Q49 70 56 66 Q63 62 70 66 Q77 70 84 66" stroke="#FCD34D" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                        <path d="M14 60 Q55 44 96 60 L92 60 Q55 55 18 60Z" fill="#FBBF24"/>
                        <path d="M22 58 Q55 48 88 58" stroke="white" stroke-width="2" opacity="0.18" fill="none" stroke-linecap="round"/>
                        <rect x="10" y="82" width="90" height="10" rx="4" fill="#E5E7EB"/>
                        <rect x="10" y="82" width="90" height="4" rx="4" fill="#F3F4F6"/>
                    </svg>
                </div>
                <div class="concession-info">
                    <h3>Hot Items</h3>
                    <ul>
                        <li>Hot dogs</li>
                        <li>Nachos</li>
                        <li>Check with staff for current offerings</li>
                    </ul>
                </div>
            </div>

        </div><!-- /.concession-grid -->

        <div class="policy-box mt-3">
            <h3>Concession Policies</h3>
            <p>No outside food or beverages are permitted inside the theatre &mdash; this helps us keep ticket prices low for everyone. Exception: birthday cakes are allowed for private rental events. For current menu items and pricing, call us at <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a> or check with staff at the stand.</p>
        </div>

        <div style="text-align:center; margin-top:3rem;">
            <p class="text-secondary mb-2">Ready to catch a show?</p>
            <a href="<?= e(TICKETS_URL) ?>" class="btn btn-crimson">Buy Tickets Online</a>
            <a href="<?= url() ?>" class="btn btn-outline" style="margin-left:1rem;">View Showtimes</a>
        </div>

    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
