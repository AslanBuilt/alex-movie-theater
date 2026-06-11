<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Visit & Contact | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Find Alex Movie Theatre at 407 N. Harrison Street, Alexandria, Indiana 46001. Directions, parking, phone 765-620-9093, and a contact form.';
$pageKeywords = 'Alex Movie Theatre location, Alexandria Indiana movie theatre directions, 407 Harrison Street Alexandria IN parking, contact Alex Theatre';
$canonical = SITE_URL . 'contact.php';

$mapQuery = '407+N+Harrison+Street,+Alexandria,+IN+46001';

require TEMPLATES_PATH . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= url() ?>">Home</a><span class="sep">/</span>Visit &amp; Contact</p>
        <h1>Visit &amp; Contact</h1>
        <p class="subtitle">407 N. Harrison Street &bull; Alexandria, Indiana 46001</p>
    </div>
</section>

<!-- Find Us -->
<section>
    <div class="container">
        <div class="section-header">
            <p class="section-label">Find Us</p>
            <h2 class="section-title">Location &amp; Parking</h2>
            <div class="section-divider"></div>
        </div>

        <div class="two-col">
            <div>
                <div class="contact-grid" style="grid-template-columns:1fr;">
                    <div class="contact-item">
                        <div class="contact-icon"><?= icon('map-pin') ?></div>
                        <div class="contact-detail">
                            <div class="label">Address</div>
                            <div class="value">407 N. Harrison Street<br>Alexandria, Indiana 46001</div>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>" target="_blank" rel="noopener" class="btn btn-outline mt-2" style="display:inline-block; margin-top:0.85rem; font-size:0.8rem; padding:0.45rem 1rem;">Get Directions</a>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon"><?= icon('route') ?></div>
                        <div class="contact-detail">
                            <div class="label">How to Get Here</div>
                            <div class="value">5 blocks north of W. Washington Street (1100N), 6 blocks west of N. State Road 9, in downtown Alexandria.</div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon"><?= icon('car') ?></div>
                        <div class="contact-detail">
                            <div class="label">Parking</div>
                            <div class="value">
                                <ul class="parking-list" style="margin-top:0.5rem;">
                                    <li>Adjacent gravel lot next to the theatre</li>
                                    <li>Street parking along N. Harrison Street</li>
                                    <li>Horners Grocery store parking lot</li>
                                    <li>Empty lot across the street</li>
                                </ul>
                                <p class="text-secondary" style="font-size:0.92rem; margin-top:0.75rem;">Parking is free. Please leave room along the theatre for all visitors.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="map-wrapper">
                    <iframe
                        src="https://www.google.com/maps?q=<?= $mapQuery ?>&z=16&output=embed"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Map to Alex Movie Theatre, 407 N. Harrison Street, Alexandria, Indiana">
                    </iframe>
                </div>
                <div class="info-card mt-3">
                    <h3>About Alexandria</h3>
                    <p>Alexandria is a small, friendly town in east-central Indiana, about 50 miles northeast of Indianapolis. The Alex has been part of this community for years &mdash; bringing affordable, quality entertainment to families, seniors, and film fans of all ages.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Get in Touch -->
<section class="visit-section">
    <div class="container">
        <div class="section-header">
            <p class="section-label">Get in Touch</p>
            <h2 class="section-title">Reach the Alex</h2>
            <div class="section-divider"></div>
        </div>

        <div class="two-col">
            <div>
                <div class="contact-grid" style="grid-template-columns:1fr;">
                    <div class="contact-item">
                        <div class="contact-icon"><?= icon('phone') ?></div>
                        <div class="contact-detail">
                            <div class="label">Phone</div>
                            <div class="value"><a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a></div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon"><?= icon('mail') ?></div>
                        <div class="contact-detail">
                            <div class="label">Email</div>
                            <div class="value"><a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon"><?= icon('brand-facebook') ?></div>
                        <div class="contact-detail">
                            <div class="label">Facebook</div>
                            <div class="value"><a href="<?= FACEBOOK_URL ?>" target="_blank" rel="noopener">The Alexandria Theatre</a></div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon"><?= icon('brand-instagram') ?></div>
                        <div class="contact-detail">
                            <div class="label">Instagram</div>
                            <div class="value"><a href="<?= INSTAGRAM_URL ?>" target="_blank" rel="noopener">@the.alextheatre</a></div>
                        </div>
                    </div>
                </div>

                <div class="info-card mt-3">
                    <h3>Employment</h3>
                    <p>Interested in working at the Alex? We'd love to have you on the team.</p>
                    <a href="<?= FORM_EMPLOYMENT ?>" class="btn btn-outline mt-2" target="_blank" rel="noopener" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Apply Now</a>
                </div>

                <div class="info-card mt-2">
                    <h3>Private Screenings &amp; Rentals</h3>
                    <p>Book the theatre for a birthday, group outing, or private event.</p>
                    <a href="<?= url('private-screenings.php') ?>" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">View Rental Info</a>
                </div>
            </div>

            <div>
                <div class="contact-form-wrap">
                    <form id="contact-form" action="https://formspree.io/f/xaqkjakn" method="POST" novalidate>
                        <input type="text" name="_gotcha" style="display:none" tabindex="-1" autocomplete="off">
                        <input type="hidden" name="_subject" value="New Message — Alex Movie Theatre">

                        <div class="form-group">
                            <label for="cf-name">Name <span style="color:var(--crimson-light)">*</span></label>
                            <input type="text" id="cf-name" name="name" required autocomplete="name" placeholder="Your name">
                        </div>

                        <div class="form-group">
                            <label for="cf-email">Email Address <span style="color:var(--crimson-light)">*</span></label>
                            <input type="email" id="cf-email" name="email" required autocomplete="email" placeholder="you@example.com">
                        </div>

                        <div class="form-group">
                            <label for="cf-phone">Phone <span style="color:var(--text-muted); font-weight:400; text-transform:none; letter-spacing:0;">(optional)</span></label>
                            <input type="tel" id="cf-phone" name="phone" autocomplete="tel" placeholder="765-555-0000">
                        </div>

                        <div class="form-group">
                            <label for="cf-subject">Subject <span style="color:var(--crimson-light)">*</span></label>
                            <select id="cf-subject" name="subject" required>
                                <option value="">Select a topic&hellip;</option>
                                <option value="General Inquiry">General Inquiry</option>
                                <option value="Showtimes &amp; Tickets">Showtimes &amp; Tickets</option>
                                <option value="Private Screening / Rental">Private Screening / Rental</option>
                                <option value="Events">Events</option>
                                <option value="Feedback">Feedback</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cf-message">Message <span style="color:var(--crimson-light)">*</span></label>
                            <textarea id="cf-message" name="message" rows="5" required placeholder="How can we help?"></textarea>
                        </div>

                        <button type="submit" class="btn btn-crimson" id="cf-submit" style="width:100%;">
                            <span class="btn-text">Send Message</span>
                            <span class="btn-loading" style="display:none">Sending&hellip;</span>
                        </button>
                    </form>

                    <div id="cf-success" style="display:none" class="form-feedback form-success">
                        <p>Thank you · your message has been sent. We&rsquo;ll get back to you soon.</p>
                    </div>

                    <div id="cf-error" style="display:none" class="form-feedback form-error">
                        <p>Something went wrong. Please try again or call us at <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require TEMPLATES_PATH . '/footer.php'; ?>
