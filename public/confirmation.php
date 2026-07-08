<?php
declare(strict_types=1);

$currentPage = '';
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/StripeService.php';
require_once INCLUDES_PATH . '/TicketTokenRepo.php';
require_once INCLUDES_PATH . '/QrCode.php';

session_name('ALEX_ADMIN_SESS');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$piId = isset($_GET['payment_intent']) ? trim((string)$_GET['payment_intent']) : '';

$txn = $ref !== '' ? tryDb(fn() => TransactionRepo::getByRef($ref), null) : null;

// Authoritative paid state. The webhook flips the DB to 'paid', but it can lag
// the redirect — so if the txn is still pending, ask Stripe directly about the
// PaymentIntent. (redirect_status in the URL is client-supplied; don't trust it.)
$paid   = $txn && $txn['payment_status'] === 'paid';
$failed = $txn && $txn['payment_status'] === 'failed';

if (!$paid && !$failed && $piId !== '') {
    $stripeConfigPath = __DIR__ . '/config/stripe.php';
    if (is_file($stripeConfigPath)) {
        try {
            $stripe = new StripeService(require $stripeConfigPath);
            $pi = $stripe->getPaymentIntent($piId);
            $status = (string)($pi['status'] ?? '');
            if ($status === 'succeeded' || $status === 'processing') {
                $paid = true;            // fulfillment is finalizing via webhook
            } elseif ($status === 'requires_payment_method' || $status === 'canceled') {
                $failed = true;
            }
        } catch (Throwable $e) {
            error_log('[confirmation] PI lookup failed: ' . $e->getMessage());
        }
    }
}

// Clear the cart once payment is in — but only for the order that was actually
// built from it. checkout.php stamps the cart-origin ref in the session, so a
// direct "Buy Now" ticket purchase never wipes concessions a shopper left in
// their cart, and a cart that held only a ticket still clears correctly.
if ($paid && $ref !== '' && ($_SESSION['cart_pending_ref'] ?? '') === $ref) {
    $_SESSION['cart'] = [];
    unset($_SESSION['cart_pending_ref']);
}

$tickets = ($paid && $txn) ? tryDb(fn() => TicketTokenRepo::getByTransaction((int)$txn['id']), []) : [];

$pageTitle       = ($paid
                      ? 'Order Confirmed'
                      : ($failed ? 'Payment Not Completed' : 'Order Not Found'))
                   . ' | The Alex — Alexandria, Indiana';
$pageDescription = $paid
    ? 'Your order at The Alex Theater is confirmed.'
    : 'Order status at The Alex Theater.';

require __DIR__ . '/templates/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Confirmation</p>
    <h1><?= $paid ? 'Order Confirmed!' : ($failed ? 'Payment Not Completed' : 'Order Not Found') ?></h1>
  </div>
</section>

<section>
  <div class="container" style="max-width:600px;">

    <?php if ($paid && $txn): ?>
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
          <p>Doors open 30 minutes before showtime.</p>
          <p style="margin-top:0.5rem;"><strong><?= e(SITE_ADDRESS) ?></strong></p>
        </div>
      <?php endif; ?>

      <?php if ($tickets): ?>
        <style>
          @media print {
            .navbar, .footer, .no-print { display: none !important; }
            body, .container { background: #fff !important; }
          }
          .ticket-card {
            display: flex; align-items: center; gap: 1.25rem;
            border: 1px solid rgba(0,0,0,0.1); border-radius: 8px;
            padding: 1rem; margin-bottom: 0.85rem; background: #fff;
          }
          .ticket-card img { flex: 0 0 auto; width: 120px; height: 120px; border-radius: 4px; }
          .ticket-card .ticket-meta { font-size: 0.95rem; }
          .ticket-card .ticket-meta .movie { font-weight: 700; margin-bottom: 0.2rem; }
          .ticket-card .ticket-meta .num { color: var(--color-text-muted); font-size: 0.85rem; }
        </style>
        <div class="policy-box" style="margin-bottom:1.5rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
            <h3 style="margin:0;">Your Tickets</h3>
            <button type="button" class="btn btn-outline no-print" onclick="window.print()">Print / Save</button>
          </div>
          <p style="color:var(--color-text-muted); margin:0 0 1rem;" class="no-print">Show a QR code at the door for each ticket.</p>
          <?php foreach ($tickets as $t): ?>
            <div class="ticket-card">
              <img src="<?= e(QrCode::pngDataUri((string)$t['ticket_token'])) ?>" alt="Ticket QR code">
              <div class="ticket-meta">
                <div class="movie"><?= e((string)($t['ticket_type'] ?? 'Adult')) ?> Ticket &mdash; <?= e((string)$t['movie_title']) ?></div>
                <div><?= e((string)$t['when']) ?></div>
                <div class="num">Ticket <?= (int)$t['seq'] ?> of <?= (int)$t['seq_total'] ?></div>
              </div>
            </div>
          <?php endforeach; ?>
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

    <?php elseif ($failed): ?>
      <div class="alert alert-error">
        <p>This payment did not go through. Please <a href="javascript:history.back()">try again</a> or call us at <a href="tel:765-620-9093">(765) 620-9093</a>.</p>
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
