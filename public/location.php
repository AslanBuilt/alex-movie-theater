<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Location & Parking | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Find Alex Movie Theatre at 407 N. Harrison Street, Alexandria, Indiana 46001. Directions, parking options, and contact information.';
$pageKeywords = 'Alex Movie Theatre location, Alexandria Indiana movie theatre directions, 407 Harrison Street Alexandria IN parking';
$canonical = SITE_URL . 'location.php';

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Location &amp; Parking</p>
        <h1>Location &amp; Parking</h1>
        <p class="subtitle">407 N. Harrison Street &bull; Alexandria, Indiana 46001</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="two-col">
            <div>
                <div class="section-header">
                    <p class="section-label">Find Us</p>
                    <h2 class="section-title">Address &amp; Directions</h2>
                    <div class="section-divider"></div>
                </div>

                <div class="info-card mb-3">
                    <h3>&#x1F4CD; Address</h3>
                    <p>407 N. Harrison Street<br>Alexandria, Indiana 46001<br><br>
                    <a href="https://maps.google.com/?q=407+N+Harrison+Street+Alexandria+Indiana+46001" target="_blank" rel="noopener" class="btn btn-outline" style="display:inline-block; margin-top:0.75rem; font-size:0.8rem; padding:0.45rem 1rem;">Open in Google Maps</a>
                    </p>
                </div>

                <div class="info-card mb-3">
                    <h3>&#x1F9ED; How to Get Here</h3>
                    <ul>
                        <li>5 blocks north of W. Washington Street (1100N)</li>
                        <li>6 blocks west of N. State Road 9</li>
                        <li>Located in downtown Alexandria on N. Harrison Street</li>
                    </ul>
                </div>

                <div class="info-card mb-3">
                    <h3>&#x1F4DE; Contact</h3>
                    <p>Phone: <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a><br><br>
                    Call ahead to confirm showtimes or ask any questions before your visit.</p>
                </div>

                <div class="section-header" style="margin-top:2.5rem;">
                    <p class="section-label">Where to Park</p>
                    <h2 class="section-title">Parking Options</h2>
                    <div class="section-divider"></div>
                </div>

                <ul class="parking-list">
                    <li>Adjacent gravel lot next to the theatre</li>
                    <li>Street parking along N. Harrison Street</li>
                    <li>Horners Grocery store parking lot</li>
                    <li>Empty lot across the street from the theatre</li>
                </ul>

                <div class="policy-box mt-3">
                    <h3>Parking Note</h3>
                    <p>Please park along the theatre or on the other side of the parking lot to ensure space for all visitors. Parking is free.</p>
                </div>
            </div>

            <div>
                <div class="map-wrapper">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3054.4!2d-85.6789!3d40.2623!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2s407+N+Harrison+St%2C+Alexandria%2C+IN+46001!5e0!3m2!1sen!2sus!4v1"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Alex Movie Theatre map">
                    </iframe>
                </div>

                <div class="info-card mt-3">
                    <h3>&#x1F3D9; About Alexandria</h3>
                    <p>Alexandria is a small, friendly town in east-central Indiana, located approximately 50 miles northeast of Indianapolis. The Alex Theatre has been a part of this community &mdash; bringing affordable, quality entertainment to families, seniors, and film fans of all ages.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
