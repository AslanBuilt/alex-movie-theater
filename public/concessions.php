<?php
$pageTitle = 'Concessions | The Alex — Alexandria, Indiana';
$pageDescription = 'Enjoy concessions at The Alex. Popcorn, drinks, candy, kids\' combos and more — pre-order online or grab them at the stand.';
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

<section class="page-hero page-hero--photo" style="--hero-img: url('assets/images/hero-1.png')">
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
                  <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
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
      <!-- Static fallback menu with images -->
      <?php
      $staticMenu = [
        'Combos' => [
          ['name'=>'Two Person Combo','desc'=>'Large Popcorn + Two Large Drinks. Best value for two.','price'=>'15.50','img'=>'images/concessions/combo-two.png'],
          ['name'=>'One Person Combo','desc'=>'Medium Popcorn + Large Drink.','price'=>'9.50','img'=>'images/concessions/combo-one.png'],
          ['name'=>'Kids Combo','desc'=>'Popcorn + Kids Drink + Small Gummy.','price'=>'4.00','img'=>'images/concessions/combo-kids.png'],
        ],
        'Popcorn' => [
          ['name'=>'Large Popcorn (170oz)','desc'=>'Fresh-popped buttered popcorn — our biggest size.','price'=>'7.50','img'=>'images/concessions/popcorn-large.png'],
          ['name'=>'Medium Popcorn (130oz)','desc'=>'Fresh-popped buttered popcorn — medium size.','price'=>'5.50','img'=>'images/concessions/popcorn-medium.png'],
          ['name'=>'Small Popcorn (85oz)','desc'=>'Fresh-popped buttered popcorn — small size.','price'=>'3.50','img'=>'images/concessions/popcorn-small.png'],
        ],
        'Drinks' => [
          ['name'=>'Large Fountain (32oz)','desc'=>'Pepsi, Mtn Dew, Dr Pepper, Diet Mtn Dew, Tropicana, Crush, Sierra Mist.','price'=>'4.00','img'=>'images/concessions/drink-fountain.png'],
          ['name'=>'Medium Fountain (20oz)','desc'=>'Pepsi, Mtn Dew, Dr Pepper, Diet Mtn Dew, Tropicana, Crush, Sierra Mist.','price'=>'3.00','img'=>'images/concessions/drink-fountain.png'],
          ['name'=>'Bottle Drinks','desc'=>'Water, Diet Pepsi, or Sweet Tea.','price'=>'2.00','img'=>'images/concessions/drink-bottle.png'],
        ],
        'Candy' => [
          ['name'=>'Box Candy','desc'=>"Reese's Pieces, Skittles, M&M's, Mike & Ike, Sour Patch, Whoppers, Junior Mints, Cookie Dough Bites, Milk Duds, Buncha Crunch.",'price'=>'2.50','img'=>'images/concessions/candy-box.png'],
          ['name'=>'Wrapper Candy','desc'=>'Single-wrapper candy bars and treats.','price'=>'1.50','img'=>'images/concessions/candy-box.png'],
          ['name'=>'Cotton Candy','desc'=>'Classic spun cotton candy — pink &amp; blue.','price'=>'3.00','img'=>'images/concessions/candy-cotton.png'],
        ],
      ];
      foreach ($staticMenu as $cat => $items): ?>
        <div class="section-header" style="margin-top:2.5rem;">
          <p class="section-label">Concession Stand</p>
          <h2 class="section-title"><?= htmlspecialchars($cat) ?></h2>
          <div class="section-divider"></div>
        </div>
        <div class="concession-db-grid">
          <?php foreach ($items as $item): ?>
            <div class="concession-db-card">
              <div class="concession-db-img">
                <img src="assets/<?= htmlspecialchars($item['img']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
              </div>
              <div class="concession-db-body">
                <div class="concession-db-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="concession-db-desc"><?= $item['desc'] ?></div>
                <div class="concession-db-price">$<?= $item['price'] ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
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
