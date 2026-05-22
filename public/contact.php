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
                                <option value="Showtimes & Tickets">Showtimes &amp; Tickets</option>
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
                        <p>Thank you &mdash; your message has been sent. We&rsquo;ll get back to you soon.</p>
                    </div>

                    <div id="cf-error" style="display:none" class="form-feedback form-error">
                        <p>Something went wrong. Please try again or call us at <a href="tel:<?= SITE_PHONE ?>"><?= e(SITE_PHONE) ?></a>.</p>
                    </div>
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
