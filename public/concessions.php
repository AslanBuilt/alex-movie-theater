<?php
$pageTitle = 'Concessions | Alex Movie Theatre — Alexandria, Indiana';
$pageDescription = 'Enjoy concessions at Alex Movie Theatre. Popcorn, drinks, candy, kids\' combos and more — pre-order online or grab them at the stand.';
$currentPage = 'concessions';
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

$repo  = new ConcessionRepo(Database::isAvailable() ? Database::getInstance() : null);
$items = $repo->getAvailable();

$categories = [];
foreach ($items as $item) {
    $categories[$item['category']][] = $item;
}

require __DIR__ . '/templates/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Concessions</p>
    <h1>Concession Stand</h1>
    <p class="subtitle">Cheaper than the big chains &middot; classic movie snacks done right.</p>
  </div>
</section>

<section>
  <div class="container">

    <div class="highlight-box" style="margin-bottom:2.5rem;">
      <p><strong>Our prices are cheaper than other theaters</strong> &mdash; but still great quality. Pre-order online and we&rsquo;ll have it ready when you arrive. No outside food or beverages permitted inside the theatre.</p>
    </div>

    <div style="text-align:center; margin-bottom:3rem;">
      <a href="order.php" class="btn btn-crimson" style="font-size:1rem;">Pre-Order for Pick-Up</a>
      <p class="text-secondary" style="margin-top:0.75rem; font-size:0.9rem;">Order now, pay and collect at the stand &mdash; no wait.</p>
    </div>

    <?php if (!empty($categories)): ?>

      <?php foreach ($categories as $cat => $catItems): ?>
        <div class="section-header" style="margin-top:2.5rem;">
          <p class="section-label">Concession Stand</p>
          <h2 class="section-title"><?= htmlspecialchars($cat) ?></h2>
          <div class="section-divider"></div>
        </div>

        <div class="concession-db-grid">
          <?php foreach ($catItems as $item): ?>
            <div class="concession-db-card">
              <?php if (!empty($item['image_path'])): ?>
                <div class="concession-db-img">
                  <img src="assets/<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                </div>
              <?php endif; ?>
              <div class="concession-db-body">
                <div class="concession-db-name"><?= htmlspecialchars($item['name']) ?></div>
                <?php if (!empty($item['description'])): ?>
                  <div class="concession-db-desc"><?= htmlspecialchars($item['description']) ?></div>
                <?php endif; ?>
                <div class="concession-db-price">$<?= number_format((float)$item['price'], 2) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

    <?php else: ?>
      <!-- Fallback when DB is unavailable -->
      <div class="section-header">
        <p class="section-label">Classic Movie Favorites</p>
        <h2 class="section-title">What We Offer</h2>
        <div class="section-divider"></div>
      </div>
      <div class="info-grid">
        <div class="info-card"><h3>Popcorn</h3><p>Fresh-popped buttered popcorn in multiple sizes.</p></div>
        <div class="info-card"><h3>Drinks</h3><p>Fountain sodas and bottled water in multiple sizes.</p></div>
        <div class="info-card"><h3>Candy &amp; Snacks</h3><p>Classic movie candies and packaged snacks.</p></div>
        <div class="info-card"><h3>Hot Items</h3><p>Hot dogs, nachos, and more. Check with staff for current offerings.</p></div>
        <div class="info-card"><h3>Kids&rsquo; Combos</h3><p>Popcorn + drink combo deals for the little ones.</p></div>
      </div>
    <?php endif; ?>

    <div class="policy-box mt-3">
      <h3>Concession Policies</h3>
      <p>No outside food or beverages are permitted inside the theatre &mdash; this helps us keep ticket prices low for everyone. Exception: birthday cakes are allowed for private rental events. For questions call us at <a href="tel:765-620-9093">(765) 620-9093</a>.</p>
    </div>

    <div style="text-align:center; margin-top:3rem;">
      <p class="text-secondary mb-2">Ready to catch a show?</p>
      <a href="index.php#now-showing" class="btn btn-crimson">View Showtimes</a>
      <a href="order.php" class="btn btn-outline" style="margin-left:1rem;">Pre-Order Concessions</a>
    </div>

  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
