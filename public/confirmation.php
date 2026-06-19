<?php
declare(strict_types=1);

$currentPage = '';
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';

$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$txn = null;

if ($ref !== '') {
    $txn = tryDb(fn() => TransactionRepo::getByRef($ref), null);
}

$pageTitle       = 'Order Confirmed | The Alex — Alexandria, Indiana';
$pageDescription = 'Your order at The Alex Theater is confirmed.';

require __DIR__ . '/templates/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Confirmation</p>
    <h1><?= $txn ? 'Order Confirmed!' : 'Order Not Found' ?></h1>
  </div>
</section>

<section>
  <div class="container" style="max-width:600px;">

    <?php if ($txn && $txn['payment_status'] === 'paid'): ?>
      <div class="highlight-box" style="text-align:center; padding:2rem; margin-bottom:2rem;">
        <div style="font-size:3rem; margin-bottom:0.5rem;">&#10003;</div>
        <h2 style="margin:0 0 0.5rem;">Thank you<?= $txn['customer_name'] ? ', ' . e((string)$txn['customer_name']) : '' ?>!</h2>
        <p style="color:var(--color-text-muted); margin:0;">Reference: <strong><?= e($txn['transaction_ref']) ?></strong></p>
      </div>

      <div class="policy-box" style="margin-bottom:2rem;">
        <h3>Order Details</h3>
        <?php foreach ($txn['items'] as $item): ?>
          <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid rgba(0,0,0,0.06);">
            <span>
              <?= e($item['item_name']) ?>
              <?= $item['selected_option'] ? ' (' . e($item['selected_option']) . ')' : '' ?>
              &times; <?= (int)$item['quantity'] ?>
            </span>
            <strong>$<?= number_format((float)$item['subtotal'], 2) ?></strong>
          </div>
        <?php endforeach; ?>
        <p style="margin-top:0.75rem; text-align:right; font-size:1.05rem;">
          Total: <strong style="color:var(--color-crimson);">$<?= number_format((float)$txn['total_amount'], 2) ?></strong>
        </p>
      </div>

      <?php if ($txn['type'] === 'ticket' || $txn['type'] === 'combo'): ?>
        <div class="policy-box" style="margin-bottom:1.5rem; background:var(--color-section-alt);">
          <h3>Picking Up Your Tickets</h3>
          <p>Show this confirmation at the box office. Doors open 30 minutes before showtime.</p>
          <p style="margin-top:0.5rem;"><strong><?= e(SITE_ADDRESS) ?></strong></p>
        </div>
      <?php endif; ?>

      <?php if ($txn['type'] === 'concession' || $txn['type'] === 'combo'): ?>
        <div class="policy-box" style="margin-bottom:1.5rem; background:var(--color-section-alt);">
          <h3>Picking Up Your Order</h3>
          <p>Your concession order will be ready when you arrive. Show this confirmation at the concession stand.</p>
        </div>
      <?php endif; ?>

      <div style="text-align:center; margin-top:2rem;">
        <a href="index.php#now-showing" class="btn btn-crimson">See More Movies</a>
      </div>

    <?php elseif ($txn && $txn['payment_status'] === 'failed'): ?>
      <div class="alert alert-error">
        <p>This transaction did not go through. Please <a href="javascript:history.back()">try again</a> or call us at <a href="tel:765-620-9093">(765) 620-9093</a>.</p>
      </div>

    <?php else: ?>
      <div class="policy-box" style="text-align:center; padding:2rem;">
        <p>We couldn't find an order with that reference number.</p>
        <a href="index.php" class="btn btn-crimson" style="margin-top:1rem;">Return Home</a>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
