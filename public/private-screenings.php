<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Private Screenings & Birthday Rentals | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Book a private movie screening or birthday party at Alex Movie Theatre in Alexandria, Indiana. Affordable rental packages starting at $75.';
$pageKeywords = 'private movie screening Alexandria Indiana, birthday party movie theatre Indiana, theatre rental Alexandria IN';
$canonical = SITE_URL . 'private-screenings.php';

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Private Screenings</p>
        <h1>Private Screenings &amp; Rentals</h1>
        <p class="subtitle">Book the Alex for your birthday, group outing, or special occasion.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="highlight-box">
            <p><strong>Make it memorable.</strong> Rent out the Alex Theatre for a private experience your group will never forget &mdash; whether it's a birthday, a reunion, a corporate outing, or just a fun night out with friends.</p>
        </div>

        <div class="section-header">
            <p class="section-label">Choose Your Package</p>
            <h2 class="section-title">Rental Options</h2>
            <div class="section-divider"></div>
        </div>

        <div class="rental-grid">
            <div class="rental-card featured">
                <h3>New Release Screening</h3>
                <div class="rental-price">$5 <span>/ adult &bull; $3 / child</span></div>
                <ul class="rental-features">
                    <li>See a currently playing film</li>
                    <li>Minimum $75 group spend</li>
                    <li>Host can cover all costs or guests pay individually</li>
                    <li>Full concession stand available</li>
                    <li>Entire group must meet the minimum</li>
                </ul>
                <a href="<?= FORM_PRIVATE_RENTAL ?>" class="btn btn-gold" target="_blank" rel="noopener">Request This Package</a>
            </div>

            <div class="rental-card">
                <h3>Alternative / Classic Film</h3>
                <div class="rental-price">$75 <span>flat fee</span></div>
                <ul class="rental-features">
                    <li>Choose a film outside current listings</li>
                    <li>Flat $75 rental fee</li>
                    <li>Great for themed parties</li>
                    <li>Full concession stand available</li>
                    <li>Contact us to discuss film options</li>
                </ul>
                <a href="<?= FORM_PRIVATE_RENTAL ?>" class="btn btn-outline" target="_blank" rel="noopener">Request This Package</a>
            </div>
        </div>

        <!-- Policies -->
        <div class="section-header" style="margin-top:3rem;">
            <p class="section-label">What You Need to Know</p>
            <h2 class="section-title">Rental Policies</h2>
            <div class="section-divider"></div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>&#x1F4B0; Group Spend Minimum</h3>
                <p>Your entire group must collectively spend at least <strong class="text-gold">$100</strong> to qualify for a private rental booking.</p>
            </div>
            <div class="info-card">
                <h3>&#x1F354; Food &amp; Beverages</h3>
                <p>No outside food or beverages are permitted. Exception: <strong class="text-gold">birthday cakes are welcome</strong> for birthday rental events. Our full concession stand will be open.</p>
            </div>
            <div class="info-card">
                <h3>&#x1F4B3; Payment Options</h3>
                <p>The host can choose to cover all costs for the group, or have each guest pay individually at the door. Flexibility is built in.</p>
            </div>
            <div class="info-card">
                <h3>&#x1F4DE; How to Book</h3>
                <p>Fill out our inquiry form to get started. We'll reach out to confirm availability, discuss your film choice, and finalize details.</p>
            </div>
        </div>

        <!-- CTA -->
        <div style="text-align:center; margin-top:3rem; padding:3rem; background:var(--bg-card); border:1px solid var(--border); border-radius:6px; border-top:3px solid var(--gold);">
            <p class="section-label">Ready to Book?</p>
            <h2 class="section-title" style="margin-bottom:1rem;">Request a Private Screening</h2>
            <p class="text-secondary" style="margin-bottom:2rem; max-width:500px; margin-left:auto; margin-right:auto;">
                Fill out our quick inquiry form and we'll get back to you to confirm your date and details.
            </p>
            <a href="<?= FORM_PRIVATE_RENTAL ?>" class="btn btn-gold" target="_blank" rel="noopener" style="font-size:1rem; padding:1rem 2.5rem;">Submit Rental Inquiry</a>
            <p style="margin-top:1rem; font-size:0.8rem; color:var(--text-muted);">Or call us directly at <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a></p>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
