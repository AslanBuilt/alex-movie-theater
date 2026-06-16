<?php
declare(strict_types=1);

$currentPage = 'concessions';
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

$repo = new ConcessionRepo(Database::isAvailable() ? Database::getInstance() : null);
$items = $repo->getAvailable();

$errors  = [];
$success = false;
$orderNum = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $showHint = trim($_POST['show']     ?? '');
    $qtys     = $_POST['qty'] ?? [];

    if ($name === '') $errors[] = 'Your name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';

    $lineItems  = [];
    $totalCents = 0;

    foreach ($items as $item) {
        $qty = (int)($qtys[$item['id']] ?? 0);
        if ($qty < 0) $qty = 0;
        if ($qty > 20) { $errors[] = 'Maximum 20 of any one item.'; break; }
        if ($qty > 0) {
            $lineItems[] = [
                'id'    => $item['id'],
                'name'  => $item['name'],
                'price' => $item['price'],
                'qty'   => $qty,
            ];
            $totalCents += (int)round($item['price'] * 100) * $qty;
        }
    }

    if (empty($lineItems)) $errors[] = 'Please add at least one item to your order.';

    if (empty($errors)) {
        $orderNum = 'AX-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
        $saved = $repo->saveOrder([
            'order_number'  => $orderNum,
            'customer_name' => $name,
            'customer_email'=> $email,
            'customer_phone'=> $phone,
            'show_info'     => $showHint,
            'items_json'    => json_encode($lineItems),
            'total_amount'  => $totalCents / 100,
        ]);
        if ($saved) {
            $success = true;
        } else {
            $errors[] = 'Unable to save your order right now. Please call us at (765) 620-9093.';
        }
    }
}

$pageTitle       = 'Pre-Order Concessions | The Alex — Alexandria, Indiana';
$pageDescription = 'Order your concessions online — pick up ready when you arrive at The Alex in Alexandria, Indiana.';
require __DIR__ . '/templates/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><a href="concessions.php">Concessions</a><span class="sep">/</span>Pre-Order</p>
    <h1>Pre-Order Concessions</h1>
    <p class="subtitle">Order now &mdash; we&rsquo;ll have everything ready when you arrive.</p>
  </div>
</section>

<section>
  <div class="container">

    <?php if ($success): ?>
      <div class="order-confirm-box">
        <div class="order-confirm-icon" aria-hidden="true">&#10003;</div>
        <h2 class="order-confirm-heading">Order Received!</h2>
        <p class="order-confirm-number">Order # <strong><?= e($orderNum) ?></strong></p>
        <p class="order-confirm-msg">Show this number at the concession stand when you arrive. Payment is collected at the theatre &mdash; cash and card accepted.</p>
        <a href="index.php" class="btn btn-crimson" style="margin-top:1.5rem;">Back to Now Showing</a>
      </div>

    <?php else: ?>

      <?php if (!empty($errors)): ?>
        <div class="form-feedback form-error" style="margin-bottom:2rem;">
          <?php foreach ($errors as $err): ?>
            <p><?= e($err) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="order-layout">

        <!-- Menu -->
        <div class="order-menu">
          <div class="section-header">
            <p class="section-label">Select Items</p>
            <h2 class="section-title">Menu</h2>
            <div class="section-divider"></div>
          </div>

          <form method="POST" action="order.php" id="order-form">

            <?php if (empty($items)): ?>
              <p class="text-secondary">Our online menu isn&rsquo;t set up yet. Please visit the concession stand on arrival or call us at <a href="tel:765-620-9093">(765) 620-9093</a>.</p>
            <?php else: ?>
              <?php
              $categories = [];
              foreach ($items as $item) {
                  $categories[$item['category']][] = $item;
              }
              foreach ($categories as $cat => $catItems):
              ?>
                <div class="order-category">
                  <h3 class="order-cat-heading"><?= e($cat) ?></h3>
                  <?php foreach ($catItems as $item): ?>
                    <div class="order-item">
                      <?php if (!empty($item['image_path'])): ?>
                        <img class="order-item-img" src="assets/<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
                      <?php endif; ?>
                      <div class="order-item-body">
                        <div class="order-item-name"><?= e($item['name']) ?></div>
                        <?php if (!empty($item['description'])): ?>
                          <div class="order-item-desc"><?= e($item['description']) ?></div>
                        <?php endif; ?>
                        <div class="order-item-price">$<?= number_format((float)$item['price'], 2) ?></div>
                      </div>
                      <div class="order-item-qty">
                        <label for="qty-<?= (int)$item['id'] ?>" class="sr-only">Quantity of <?= e($item['name']) ?></label>
                        <input type="number" id="qty-<?= (int)$item['id'] ?>"
                               name="qty[<?= (int)$item['id'] ?>]"
                               value="<?= (int)($_POST['qty'][$item['id']] ?? 0) ?>"
                               min="0" max="20" class="qty-input">
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <!-- Customer info -->
            <div class="section-header" style="margin-top:2.5rem;">
              <p class="section-label">Your Info</p>
              <h2 class="section-title" style="font-size:1.4rem;">Contact Details</h2>
              <div class="section-divider"></div>
            </div>

            <div class="form-group">
              <label for="order-name">Name <span style="color:var(--crimson-light)">*</span></label>
              <input type="text" id="order-name" name="name" required autocomplete="name"
                     placeholder="Your name"
                     value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="order-email">Email <span style="color:var(--crimson-light)">*</span></label>
              <input type="email" id="order-email" name="email" required autocomplete="email"
                     placeholder="you@example.com"
                     value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="order-phone">Phone <span style="color:var(--text-muted); font-weight:400; text-transform:none; letter-spacing:0;">(optional)</span></label>
              <input type="tel" id="order-phone" name="phone" autocomplete="tel"
                     placeholder="765-555-0000"
                     value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="order-show">Which showtime are you coming to? <span style="color:var(--text-muted); font-weight:400; text-transform:none; letter-spacing:0;">(optional)</span></label>
              <input type="text" id="order-show" name="show"
                     placeholder="e.g. Saturday 7:15 PM Mandalorian"
                     value="<?= e($_POST['show'] ?? '') ?>">
            </div>

            <div class="highlight-box" style="margin-bottom:2rem;">
              <p><strong>Payment at the theatre.</strong> This is a pre-order only &mdash; no payment is collected here. Bring cash or card and pay at the concession stand when you arrive. We&rsquo;ll have your order ready.</p>
            </div>

            <button type="submit" class="btn btn-crimson" style="width:100%;">Place Pre-Order</button>
          </form>
        </div>

      </div><!-- /.order-layout -->
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
