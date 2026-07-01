<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Private Screenings & Birthday Rentals | The Alex — Alexandria, Indiana';
$pageDescription = 'Book a private movie screening or birthday party at The Alex in Alexandria, Indiana. Affordable rental packages starting at $75.';
$currentPage = 'private-screenings';
require __DIR__ . '/templates/header.php';
?>

<section class="page-hero page-hero--photo" style="background-image: linear-gradient(rgba(250,245,235,0.87),rgba(250,245,235,0.87)), url('assets/images/hero-3.webp')">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Private Screenings</p>
    <h1>Private Screenings &amp; Rentals</h1>
    <p class="subtitle">Book the Alex for your birthday, group outing, or special occasion.</p>
  </div>
</section>

<section>
  <div class="container">
    <div class="highlight-box">
      <p><strong>Make it memorable.</strong> Rent out The Alex for a private experience your group will never forget · whether it's a birthday, a reunion, a corporate outing, or just a fun night out with friends.</p>
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
        <a href="#rental-inquiry" class="btn btn-crimson js-package-select" data-package="New Release Screening">Request This Package</a>
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
        <a href="#rental-inquiry" class="btn btn-outline js-package-select" data-package="Alternative / Classic Film">Request This Package</a>
      </div>
    </div>

    <div class="section-header" style="margin-top:3rem;">
      <p class="section-label">What You Need to Know</p>
      <h2 class="section-title">Rental Policies</h2>
      <div class="section-divider"></div>
    </div>

    <div class="info-grid">
      <div class="info-card">
        <h3>Group Spend Minimum</h3>
        <p>Your entire group must collectively spend at least <strong class="text-crimson">$100</strong> to qualify for a private rental booking.</p>
      </div>
      <div class="info-card">
        <h3>Food &amp; Beverages</h3>
        <p>No outside food or beverages are permitted. Exception: <strong class="text-crimson">birthday cakes are welcome</strong> for birthday rental events. Our full concession stand will be open.</p>
      </div>
      <div class="info-card">
        <h3>Payment Options</h3>
        <p>The host can choose to cover all costs for the group, or have each guest pay individually at the door. Flexibility is built in.</p>
      </div>
      <div class="info-card">
        <h3>How to Book</h3>
        <p>Fill out our inquiry form to get started. We'll reach out to confirm availability, discuss your film choice, and finalize details.</p>
      </div>
    </div>

    <div id="rental-inquiry" style="margin-top:3rem; padding:3rem; background:var(--bg-card); border:1px solid var(--border); border-radius:6px; border-top:3px solid var(--crimson);">
      <div style="text-align:center; margin-bottom:2rem;">
        <p class="section-label">Ready to Book?</p>
        <h2 class="section-title" style="margin-bottom:1rem;">Request a Private Screening</h2>
        <p class="text-secondary" style="max-width:500px; margin:0 auto;">
          Fill out our quick inquiry form and we'll get back to you to confirm your date and details.
        </p>
      </div>

      <div class="contact-form-wrap" style="max-width:560px; margin:0 auto;">
        <form id="rental-form" class="js-formspree-form" action="https://formspree.io/f/xaqkjakn" method="POST" novalidate>
          <div class="form-error-summary" role="alert" tabindex="-1" hidden>
            <h3 class="form-error-summary__title">There is a problem</h3>
            <ul></ul>
          </div>
          <input type="text" name="_gotcha" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;">
          <input type="hidden" name="_subject" value="Private Rental Inquiry — The Alex">

          <div class="form-group">
            <label for="ri-name">Name <abbr class="required" title="required">*</abbr></label>
            <input type="text" id="ri-name" name="name" required autocomplete="name" placeholder="Your name" aria-describedby="ri-name-error">
            <p id="ri-name-error" class="field-error" hidden></p>
          </div>

          <div class="form-group">
            <label for="ri-email">Email Address <abbr class="required" title="required">*</abbr></label>
            <input type="email" id="ri-email" name="email" required autocomplete="email" placeholder="you@example.com" aria-describedby="ri-email-error">
            <p id="ri-email-error" class="field-error" hidden></p>
          </div>

          <div class="form-group">
            <label for="ri-phone">Phone <span class="optional">(optional)</span></label>
            <input type="tel" id="ri-phone" name="phone" autocomplete="tel" placeholder="765-555-0000">
          </div>

          <div class="form-group">
            <label for="ri-date">Event Date <abbr class="required" title="required">*</abbr></label>
            <input type="date" id="ri-date" name="event_date" required aria-describedby="ri-date-error">
            <p id="ri-date-error" class="field-error" hidden></p>
          </div>

          <div class="form-group">
            <label for="ri-package">Package <abbr class="required" title="required">*</abbr></label>
            <select id="ri-package" name="package" required aria-describedby="ri-package-error">
              <option value="">Select a package&hellip;</option>
              <option value="New Release Screening">New Release Screening ($5/adult, $3/child)</option>
              <option value="Alternative / Classic Film">Alternative / Classic Film ($75 flat)</option>
              <option value="Not sure yet">Not sure yet</option>
            </select>
            <p id="ri-package-error" class="field-error" hidden></p>
          </div>

          <div class="form-group">
            <label for="ri-guests">Number of Guests <abbr class="required" title="required">*</abbr></label>
            <input type="number" id="ri-guests" name="guests" min="1" step="1" required placeholder="e.g. 20" aria-describedby="ri-guests-error">
            <p id="ri-guests-error" class="field-error" hidden></p>
          </div>

          <div class="form-group">
            <label for="ri-message">Message <span class="optional">(optional)</span></label>
            <textarea id="ri-message" name="message" rows="4" placeholder="Tell us about your event — birthday, reunion, film choice, anything else we should know."></textarea>
          </div>

          <button type="submit" class="btn btn-crimson" style="width:100%; font-size:1rem; padding:1rem 2.5rem;">
            <span class="btn-text">Submit Rental Inquiry</span>
            <span class="btn-loading" style="display:none">Sending&hellip;</span>
          </button>
        </form>

        <div class="form-feedback form-success" style="display:none">
          <p>Thank you &middot; your rental inquiry has been sent. We'll be in touch soon to confirm your date.</p>
        </div>
        <div class="form-feedback form-error" style="display:none">
          <p>Something went wrong. Please try again or call us directly at <a href="tel:765-620-9093">(765) 620-9093</a>.</p>
        </div>
      </div>

      <p style="margin-top:1.5rem; font-size:0.8rem; color:var(--text-muted); text-align:center;">Or call us directly at <a href="tel:765-620-9093">765-620-9093</a></p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
