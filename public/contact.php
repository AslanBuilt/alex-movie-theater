<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Contact Us | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Contact Alex Movie Theatre in Alexandria, Indiana. Phone: 765-620-9093. Find us on Facebook and Instagram.';
$pageKeywords = 'contact Alex Movie Theatre, Alexandria Indiana movie theatre phone, Alex Theatre Alexandria contact';
$canonical = SITE_URL . 'contact.php';

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Contact</p>
        <h1>Contact Us</h1>
        <p class="subtitle">We'd love to hear from you &mdash; reach out anytime.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="two-col">
            <div>
                <div class="section-header">
                    <p class="section-label">Get in Touch</p>
                    <h2 class="section-title">Reach the Alex</h2>
                    <div class="section-divider"></div>
                </div>

                <div class="contact-grid" style="grid-template-columns:1fr;">
                    <div class="contact-item">
                        <div class="contact-icon">&#x1F4DE;</div>
                        <div class="contact-detail">
                            <div class="label">Phone</div>
                            <div class="value"><a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a></div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">&#x1F4CD;</div>
                        <div class="contact-detail">
                            <div class="label">Address</div>
                            <div class="value"><?= e(SITE_ADDRESS) ?></div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">&#x1F4D8;</div>
                        <div class="contact-detail">
                            <div class="label">Facebook</div>
                            <div class="value"><a href="<?= FACEBOOK_URL ?>" target="_blank" rel="noopener">The Alexandria Theatre</a></div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">&#x1F4F7;</div>
                        <div class="contact-detail">
                            <div class="label">Instagram</div>
                            <div class="value"><a href="<?= INSTAGRAM_URL ?>" target="_blank" rel="noopener">@the.alextheatre</a></div>
                        </div>
                    </div>
                </div>

                <div class="info-card mt-3">
                    <h3>&#x1F4DD; Employment</h3>
                    <p>Interested in working at the Alex? We'd love to have you on the team.</p>
                    <a href="<?= FORM_EMPLOYMENT ?>" class="btn btn-outline mt-2" target="_blank" rel="noopener" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Apply Now</a>
                </div>
            </div>

            <div>
                <div class="section-header">
                    <p class="section-label">Send a Message</p>
                    <h2 class="section-title">General Inquiry</h2>
                    <div class="section-divider"></div>
                </div>

                <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:6px; padding:2rem; border-top:3px solid var(--gold);">
                    <p class="text-secondary" style="margin-bottom:1.5rem; font-size:0.9rem; line-height:1.7;">
                        Have a question about showtimes, events, or the theatre? Fill out our contact form and we'll get back to you as soon as possible.
                    </p>
                    <a href="<?= FORM_PRIVATE_RENTAL ?>" class="btn btn-crimson" target="_blank" rel="noopener" style="width:100%; text-align:center; display:block; margin-bottom:1rem;">Send a Message</a>
                    <p style="font-size:0.75rem; color:var(--text-muted); text-align:center;">Opens our Google Form &mdash; no account required</p>
                </div>

                <div class="info-card mt-3">
                    <h3>&#x1F382; Private Screenings &amp; Rentals</h3>
                    <p>Looking to book the theatre for a private event, birthday, or group outing?</p>
                    <a href="<?= url('private-screenings.php') ?>" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">View Rental Info</a>
                </div>

                <div class="info-card mt-2">
                    <h3>&#x1F4CD; Location &amp; Parking</h3>
                    <p>Need directions or parking info? We've got you covered.</p>
                    <a href="<?= url('location.php') ?>" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Get Directions</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
