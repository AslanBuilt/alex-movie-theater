<?php
$pageTitle = 'Free Senior Movie | The Alex — Alexandria, Indiana';
$pageDescription = 'Free monthly movie screenings for seniors 55 and up at The Alex in Alexandria, Indiana. No reservation needed. Sponsored by Senior Essential Connections.';
$currentPage = 'senior-movie';
require __DIR__ . '/templates/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Senior Movie</p>
    <h1>Free Senior Movie</h1>
    <p class="subtitle">A community gift for seniors 55 and up &bull; no ticket required.</p>
  </div>
</section>

<!-- ── Free for Seniors 55+ ── -->
<section>
  <div class="container">
    <div class="senior-badge">Free Admission &bull; Ages 55 &amp; Up &bull; Sponsored by Senior Essential Connections</div>

    <div class="section-header">
      <p class="section-label">About the Program</p>
      <h2 class="section-title">Free for Seniors 55+</h2>
      <div class="section-divider"></div>
    </div>

    <p style="color:var(--text-secondary); margin-bottom:1.75rem; line-height:1.8; max-width:700px;">
      The Alex is proud to partner with <strong class="text-crimson">Senior Essential Connections</strong> to bring free monthly screenings to the community. Every senior 55 or older is welcome at no charge &mdash; no reservations needed, just show up and enjoy.
    </p>

    <div class="info-grid" style="max-width:800px;">
      <div class="info-card">
        <h3>Who Qualifies?</h3>
        <p>Any senior citizen 55 years of age or older. No ID check required &mdash; just arrive and take your seat.</p>
      </div>
      <div class="info-card">
        <h3>Concessions</h3>
        <p>No outside food or drinks. Our concession stand will be open during the screening with affordable snacks and drinks.</p>
      </div>
    </div>

    <div class="reviews-section" style="margin-top:3rem;">
      <div class="reviews-header">
        <span class="reviews-label">Google Reviews</span>
        <span class="reviews-google-badge">via Google</span>
      </div>

      <div class="review-item">
        <div class="review-meta">
          <span class="review-name">Jackie</span>
          <span class="review-time">2 months ago</span>
        </div>
        <div class="review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <p class="review-text">What a great budget friendly place to take the family to see a movie! Even the concessions are budget friendly.</p>
      </div>

      <div class="review-item">
        <div class="review-meta">
          <span class="review-name">Robin</span>
          <span class="review-time">3 months ago</span>
        </div>
        <div class="review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <p class="review-text">We love this historical movie theater. Staff and owners are super nice. Great hospitality.</p>
      </div>

      <div class="review-item">
        <div class="review-meta">
          <span class="review-name">Brian</span>
          <span class="review-time">7 months ago</span>
        </div>
        <div class="review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <p class="review-text">I love the vintage feel of this place. The prices are amazing and it&rsquo;s never too busy.</p>
      </div>

      <div class="review-item">
        <div class="review-meta">
          <span class="review-name">Jim</span>
          <span class="review-time">a year ago</span>
        </div>
        <div class="review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
        <p class="review-text">Really neat small town mom and pop operation. Prices are super reasonable. First run movies at second run pricing &mdash; worth making the trip from surrounding counties.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── Senior Essential Connections ── -->
<section style="background:var(--cream); border-top:1px solid var(--border);">
  <div class="container">
    <div class="section-header">
      <p class="section-label">Our Sponsor</p>
      <h2 class="section-title">Senior Essential Connections</h2>
      <div class="section-divider"></div>
    </div>

    <p style="color:var(--text-secondary); line-height:1.8; max-width:680px;">
      Senior Essential Connections is an organization dedicated to supporting and enriching the lives of older adults in the Alexandria community. They sponsor this free movie program as part of their mission to keep seniors engaged and connected.
    </p>

    <div class="info-card mt-3" style="max-width:480px;">
      <h3>Contact &amp; Questions</h3>
      <p>Reach out to Senior Essential Connections directly, or call the theatre:</p>
      <p style="margin-top:0.75rem;">
        <a href="tel:765-620-9093" style="font-weight:700; font-size:1.1rem;">(765) 620-9093</a>
      </p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
