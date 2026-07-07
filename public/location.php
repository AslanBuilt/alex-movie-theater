<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Location & Contact | The Alex — Alexandria, Indiana';
$pageDescription = 'Find The Alex at 407 N. Harrison Street, Alexandria, Indiana 46001. Directions, parking, phone, and contact form.';
$currentPage = 'location';
require __DIR__ . '/templates/header.php';
?>

<section class="page-hero page-hero--photo" style="background-image: linear-gradient(rgba(250,245,235,0.87),rgba(250,245,235,0.87)), url('assets/images/hero-theater.webp')">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Location &amp; Contact</p>
    <h1>Location &amp; Contact</h1>
    <p class="subtitle">407 N. Harrison Street &bull; Alexandria, Indiana 46001</p>
  </div>
</section>

<!-- ── Location + Map ── -->
<section>
  <div class="container">
    <div class="two-col">

      <!-- Left: address, contact, parking -->
      <div>
        <div class="section-header">
          <p class="section-label">Find Us</p>
          <h2 class="section-title">Address &amp; Directions</h2>
          <div class="section-divider"></div>
        </div>

        <div class="contact-grid" style="grid-template-columns:1fr; gap:1.5rem; margin-bottom:2rem;">

          <div class="contact-item">
            <div class="contact-icon-svg" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div class="contact-detail">
              <div class="label">Address</div>
              <div class="value">407 N. Harrison Street<br>Alexandria, Indiana 46001</div>
              <a href="https://maps.google.com/?q=407+N+Harrison+Street+Alexandria+Indiana+46001" target="_blank" rel="noopener" class="btn btn-outline" style="display:inline-block; margin-top:0.75rem; font-size:0.78rem; padding:0.4rem 0.9rem;">Open in Google Maps</a>
            </div>
          </div>

          <div class="contact-item">
            <div class="contact-icon-svg" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
              </svg>
            </div>
            <div class="contact-detail">
              <div class="label">Phone</div>
              <div class="value"><a href="tel:765-620-9093">(765) 620-9093</a></div>
            </div>
          </div>

          <div class="contact-item">
            <div class="contact-icon-svg" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
              </svg>
            </div>
            <div class="contact-detail">
              <div class="label">Facebook</div>
              <div class="value"><a href="https://www.facebook.com/TheAlexandriaTheatre" target="_blank" rel="noopener">The Alexandria Theatre</a></div>
            </div>
          </div>

          <div class="contact-item">
            <div class="contact-icon-svg" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="4" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="17.5" cy="6.5" r="0.5" fill="currentColor" stroke="none"/>
              </svg>
            </div>
            <div class="contact-detail">
              <div class="label">Instagram</div>
              <div class="value"><a href="https://www.instagram.com/the.alextheatre" target="_blank" rel="noopener">@the.alextheatre</a></div>
            </div>
          </div>
        </div>

        <!-- Directions -->
        <div class="section-header" style="margin-top:2rem;">
          <p class="section-label">How to Get Here</p>
          <h2 class="section-title">Directions</h2>
          <div class="section-divider"></div>
        </div>
        <ul class="parking-list">
          <li>5 blocks north of W. Washington Street (1100N)</li>
          <li>6 blocks west of N. State Road 9</li>
          <li>Located in downtown Alexandria on N. Harrison Street</li>
        </ul>

        <!-- Parking -->
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

      <!-- Right: map + about -->
      <div>
        <div class="map-wrapper">
          <iframe
            src="https://www.google.com/maps?q=407+N.+Harrison+St,+Alexandria,+IN+46001&t=&z=16&ie=UTF8&iwloc=&output=embed"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="The Alex — 407 N Harrison St, Alexandria IN">
          </iframe>
        </div>

        <div class="info-card mt-3">
          <h3>About Alexandria</h3>
          <p>Alexandria is a small, friendly town in east-central Indiana, located approximately 50 miles northeast of Indianapolis. The Alex has been a part of this community, bringing affordable entertainment to families, seniors, and film fans of all ages.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Contact Form ── -->
<section id="contact" style="background:var(--cream); border-top:1px solid var(--border);">
  <div class="container">
    <div class="two-col">
      <div>
        <div class="section-header">
          <p class="section-label">Get in Touch</p>
          <h2 class="section-title">Send a Message</h2>
          <div class="section-divider"></div>
        </div>
        <p style="color:var(--text-secondary); line-height:1.8; margin-bottom:2rem;">Have a question about showtimes, private rentals, or anything else? Fill out the form and we&rsquo;ll get back to you soon &mdash; or just give us a call at <a href="tel:765-620-9093"><strong>(765) 620-9093</strong></a>.</p>

        <div class="info-card">
          <h3>Employment</h3>
          <p>Interested in working at the Alex? We&rsquo;d love to have you on the team.</p>
          <a href="#employment" class="btn btn-outline mt-2" style="display:inline-block; margin-top:1rem; font-size:0.8rem; padding:0.5rem 1rem;">Apply Now</a>
        </div>
      </div>

      <div>
        <div class="contact-form-wrap">
          <form id="contact-form" class="js-formspree-form" action="https://formspree.io/f/xaqkjakn" method="POST" novalidate>
            <div class="form-error-summary" role="alert" tabindex="-1" hidden>
              <h3 class="form-error-summary__title">There is a problem</h3>
              <ul></ul>
            </div>
            <input type="text" name="_gotcha" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;">
            <input type="hidden" name="_subject" value="New Message — The Alex">

            <div class="form-group">
              <label for="cf-name">Name <abbr class="required" title="required">*</abbr></label>
              <input type="text" id="cf-name" name="name" required autocomplete="name" placeholder="Your name" aria-describedby="cf-name-error">
              <p id="cf-name-error" class="field-error" hidden></p>
            </div>

            <div class="form-group">
              <label for="cf-email">Email Address <abbr class="required" title="required">*</abbr></label>
              <input type="email" id="cf-email" name="email" required autocomplete="email" placeholder="you@example.com" aria-describedby="cf-email-error">
              <p id="cf-email-error" class="field-error" hidden></p>
            </div>

            <div class="form-group">
              <label for="cf-phone">Phone <span class="optional">(optional)</span></label>
              <input type="tel" id="cf-phone" name="phone" autocomplete="tel" placeholder="765-555-0000">
            </div>

            <div class="form-group">
              <label for="cf-subject">Subject <abbr class="required" title="required">*</abbr></label>
              <select id="cf-subject" name="subject" required aria-describedby="cf-subject-error">
                <option value="">Select a topic&hellip;</option>
                <option value="General Inquiry">General Inquiry</option>
                <option value="Showtimes & Tickets">Showtimes &amp; Tickets</option>
                <option value="Private Screening / Rental">Private Screening / Rental</option>
                <option value="Events">Events</option>
                <option value="Feedback">Feedback</option>
                <option value="Other">Other</option>
              </select>
              <p id="cf-subject-error" class="field-error" hidden></p>
            </div>

            <div class="form-group">
              <label for="cf-message">Message <abbr class="required" title="required">*</abbr></label>
              <textarea id="cf-message" name="message" rows="5" required placeholder="How can we help?" aria-describedby="cf-message-error"></textarea>
              <p id="cf-message-error" class="field-error" hidden></p>
            </div>

            <button type="submit" class="btn btn-crimson" id="cf-submit" style="width:100%;">
              <span class="btn-text">Send Message</span>
              <span class="btn-loading" style="display:none">Sending&hellip;</span>
            </button>
          </form>

          <div id="cf-success" style="display:none" class="form-feedback form-success">
            <p>Thank you &middot; your message has been sent. We&rsquo;ll get back to you soon.</p>
          </div>
          <div id="cf-error" style="display:none" class="form-feedback form-error">
            <p>Something went wrong. Please try again or call us at <a href="tel:765-620-9093">(765) 620-9093</a>.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Employment ── -->
<section id="employment" style="background:var(--cream); border-top:1px solid var(--border);">
  <div class="container">
    <div class="two-col">
      <div>
        <div class="section-header">
          <p class="section-label">Join Our Team</p>
          <h2 class="section-title">Apply for a Job</h2>
          <div class="section-divider"></div>
        </div>
        <p style="color:var(--text-secondary); line-height:1.8; margin-bottom:2rem;">Interested in working at the Alex? We&rsquo;re always looking for friendly, reliable people for box office, concessions, and more. Fill out the form and we&rsquo;ll be in touch &mdash; or call us at <a href="tel:765-620-9093"><strong>(765) 620-9093</strong></a>.</p>
      </div>

      <div>
        <div class="contact-form-wrap">
          <form id="employment-form" class="js-formspree-form" action="https://formspree.io/f/mjgqdvvn" method="POST" novalidate>
            <div class="form-error-summary" role="alert" tabindex="-1" hidden>
              <h3 class="form-error-summary__title">There is a problem</h3>
              <ul></ul>
            </div>
            <input type="text" name="_gotcha" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;">
            <input type="hidden" name="_subject" value="Employment Application — The Alex">

            <div class="form-group">
              <label for="ef-name">Full Name <abbr class="required" title="required">*</abbr></label>
              <input type="text" id="ef-name" name="name" required autocomplete="name" placeholder="Your full name" aria-describedby="ef-name-error">
              <p id="ef-name-error" class="field-error" hidden></p>
            </div>

            <div class="form-group">
              <label for="ef-email">Email Address <abbr class="required" title="required">*</abbr></label>
              <input type="email" id="ef-email" name="email" required autocomplete="email" placeholder="you@example.com" aria-describedby="ef-email-error">
              <p id="ef-email-error" class="field-error" hidden></p>
            </div>

            <div class="form-group">
              <label for="ef-phone">Phone Number <abbr class="required" title="required">*</abbr></label>
              <input type="tel" id="ef-phone" name="phone" required autocomplete="tel" placeholder="765-555-0000" aria-describedby="ef-phone-error">
              <p id="ef-phone-error" class="field-error" hidden></p>
            </div>

            <div class="form-group">
              <label for="ef-position">Position Applying For <abbr class="required" title="required">*</abbr></label>
              <select id="ef-position" name="position" required aria-describedby="ef-position-error">
                <option value="">Select a position&hellip;</option>
                <option value="Box Office / Ticket Sales">Box Office / Ticket Sales</option>
                <option value="Concessions">Concessions</option>
                <option value="Usher / Theater Staff">Usher / Theater Staff</option>
                <option value="Projectionist">Projectionist</option>
                <option value="Manager / Supervisor">Manager / Supervisor</option>
                <option value="Other">Other</option>
              </select>
              <p id="ef-position-error" class="field-error" hidden></p>
            </div>

            <div class="form-group">
              <label for="ef-why">Why do you want to work here? <abbr class="required" title="required">*</abbr></label>
              <textarea id="ef-why" name="why" rows="4" required placeholder="Tell us a bit about yourself and why you'd be a great fit." aria-describedby="ef-why-error"></textarea>
              <p id="ef-why-error" class="field-error" hidden></p>
            </div>

            <div class="form-group">
              <label>Availability</label>
              <div class="availability-days">
                <label class="checkbox-pill"><input type="checkbox" name="availability[]" value="Monday"> Mon</label>
                <label class="checkbox-pill"><input type="checkbox" name="availability[]" value="Tuesday"> Tue</label>
                <label class="checkbox-pill"><input type="checkbox" name="availability[]" value="Wednesday"> Wed</label>
                <label class="checkbox-pill"><input type="checkbox" name="availability[]" value="Thursday"> Thu</label>
                <label class="checkbox-pill"><input type="checkbox" name="availability[]" value="Friday"> Fri</label>
                <label class="checkbox-pill"><input type="checkbox" name="availability[]" value="Saturday"> Sat</label>
                <label class="checkbox-pill"><input type="checkbox" name="availability[]" value="Sunday"> Sun</label>
              </div>
            </div>

            <button type="submit" class="btn btn-crimson" style="width:100%;">
              <span class="btn-text">Submit Application</span>
              <span class="btn-loading" style="display:none">Sending&hellip;</span>
            </button>
          </form>

          <div class="form-feedback form-success" style="display:none">
            <p>Thank you &middot; your application has been received. We&rsquo;ll be in touch if there&rsquo;s a fit.</p>
          </div>
          <div class="form-feedback form-error" style="display:none">
            <p>Something went wrong. Please try again or call us at <a href="tel:765-620-9093">(765) 620-9093</a>.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
