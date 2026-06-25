<?php
declare(strict_types=1);

$currentPage = '';
$showCart = true;
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/StripeService.php';

session_name('ALEX_ADMIN_SESS');
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Stripe config (generated from secrets at deploy) ──────────────────────────
$stripeConfigPath = __DIR__ . '/config/stripe.php';
$stripeConfig     = is_file($stripeConfigPath) ? require $stripeConfigPath : null;

// ── Detect mode: ticket checkout vs concession cart ───────────────────────────
$mode       = 'ticket';
$showtimeId = isset($_GET['showtime']) ? (int)$_GET['showtime'] : 0;
$qty        = isset($_GET['qty'])      ? max(1, (int)$_GET['qty']) : 1;
$timeLabel  = isset($_GET['t'])        ? urldecode($_GET['t']) : '';

$showtime = null;
$movie    = null;
$concessionItems = [];

if ($showtimeId > 0) {
    $mode     = 'ticket';
    $showtime = tryDb(fn() => ShowtimeRepo::getById($showtimeId), null);
    if ($showtime) {
        require_once INCLUDES_PATH . '/MovieRepo.php';
        $movie = tryDb(fn() => MovieRepo::getById((int)$showtime['movie_id']), null);
    }
} else {
    $mode = 'concession';
    $cart = $_SESSION['cart'] ?? [];
    if (!empty($cart)) {
        $repo = new ConcessionRepo(Database::getInstance());
        foreach ($cart as $entry) {
            $item = $repo->getById((int)$entry['id']);
            if ($item) {
                $concessionItems[] = array_merge($item, [
                    'qty'    => (int)$entry['qty'],
                    'option' => $entry['option'] ?? null,
                ]);
            }
        }
    }
}

if ($mode === 'ticket' && $showtime === null) {
    header('Location: index.php');
    exit;
}

// ── Build authoritative, server-priced line items + validate availability ─────
// Prices come from the DB, never the client. Mirrors the old api/checkout.php.
$lineItems   = [];
$totalAmount = 0.0;
$hasTicket   = false;
$hasConcession = false;
$checkoutError = null;

if ($mode === 'ticket') {
    if (!$showtime['is_active']) {
        $checkoutError = 'This showtime is no longer available.';
    } else {
        $available = (int)$showtime['available_tickets'] - (int)$showtime['tickets_sold'];
        if ($available < $qty) {
            $checkoutError = "Only $available ticket(s) remain for this showtime.";
        } else {
            $unit = 5.00;
            $lineItems[] = [
                'item_type' => 'ticket', 'item_id' => (int)$showtime['id'],
                'item_name' => 'Ticket: ' . ($showtime['label'] ?? 'Showtime'),
                'quantity'  => $qty, 'unit_price' => $unit, 'selected_option' => null,
                'subtotal'  => round($unit * $qty, 2),
            ];
            $totalAmount += $unit * $qty;
            $hasTicket = true;
        }
    }
} else {
    if (empty($concessionItems)) {
        $checkoutError = 'Your cart is empty.';
    }
    foreach ($concessionItems as $ci) {
        if (empty($ci['is_available'])) {
            $checkoutError = 'An item in your cart is no longer available: ' . $ci['name'];
            break;
        }
        if ((int)$ci['stock_quantity'] > 0 && (int)$ci['qty'] > (int)$ci['stock_quantity']) {
            $checkoutError = 'Not enough stock for: ' . $ci['name'];
            break;
        }
        $unit = (float)$ci['price'];
        $lineItems[] = [
            'item_type' => 'concession', 'item_id' => (int)$ci['id'],
            'item_name' => $ci['name'], 'quantity' => (int)$ci['qty'],
            'unit_price' => $unit, 'selected_option' => $ci['option'] ?? null,
            'subtotal'  => round($unit * (int)$ci['qty'], 2),
        ];
        $totalAmount += $unit * (int)$ci['qty'];
        $hasConcession = true;
    }
}
$totalAmount = round($totalAmount, 2);

$txnType = $hasTicket && $hasConcession ? 'combo' : ($hasTicket ? 'ticket' : 'concession');

// ── Create the pending transaction + Stripe PaymentIntent (checkout-start) ────
$clientSecret = null;
$txnRef       = null;
if ($checkoutError === null && !empty($lineItems)) {
    if (!$stripeConfig) {
        $checkoutError = 'Online payment is not configured yet. Please call us at ' . SITE_PHONE . ' to order.';
    } else {
        try {
            $txnRef = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));
            $txnId  = TransactionRepo::create([
                'transaction_ref' => $txnRef,
                'type'            => $txnType,
                'source_channel'  => 'website',
                'total_amount'    => $totalAmount,
                'payment_status'  => 'pending',
                'payment_method'  => 'stripe',
            ]);
            if ($txnId === 0) {
                throw new RuntimeException('Failed to create transaction');
            }
            foreach ($lineItems as $li) {
                TransactionRepo::addItem($txnId, $li);
            }
            $stripe = new StripeService($stripeConfig);
            $intent = $stripe->createPaymentIntent((int)round($totalAmount * 100), [
                'transaction_id'  => (string)$txnId,
                'transaction_ref' => $txnRef,
            ]);
            $clientSecret = $intent['client_secret'] ?? null;
            if (isset($intent['id'])) {
                TransactionRepo::setStripePaymentIntent($txnId, (string)$intent['id']);
            }
            if (!$clientSecret) {
                throw new RuntimeException('No client secret returned');
            }
        } catch (Throwable $e) {
            error_log('[checkout] PaymentIntent failed: ' . $e->getMessage());
            $checkoutError = 'We could not start checkout right now. Please try again or call ' . SITE_PHONE . '.';
        }
    }
}

$pageTitle       = 'Checkout | The Alex — Alexandria, Indiana';
$pageDescription = 'Complete your purchase at The Alex Theater.';

require __DIR__ . '/templates/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Checkout</p>
    <h1>Checkout</h1>
    <p class="subtitle">Review your order and pay securely</p>
  </div>
</section>

<section>
  <div class="container" style="max-width:640px;">

    <?php if ($mode === 'ticket' && $showtime): ?>
      <div class="policy-box" style="margin-bottom:2rem;">
        <h3 style="margin-bottom:0.5rem;">Order Summary</h3>
        <p style="margin:0.25rem 0;"><strong><?= e($movie ? $movie['title'] : 'Movie') ?></strong></p>
        <p style="margin:0.25rem 0; color:var(--color-text-muted);">
          <?php
            $dateStr = $showtime['showtime_date'] ?? '';
            $timeStr = $showtime['showtime_time'] ?? $timeLabel;
            if ($dateStr) {
                $d = new DateTime($dateStr);
                echo e($d->format('D, M j') . ' at ' . ($timeStr ? date('g:i A', strtotime($timeStr)) : $timeLabel));
            } else {
                echo e($showtime['label'] ?? '');
            }
          ?>
        </p>
        <p style="margin:0.75rem 0 0;">
          <?= $qty ?> ticket<?= $qty !== 1 ? 's' : '' ?> &times; $5.00 =
          <strong style="color:var(--color-crimson);">$<?= number_format($qty * 5.00, 2) ?></strong>
        </p>
      </div>
    <?php elseif ($mode === 'concession' && !empty($concessionItems)): ?>
      <div class="policy-box" style="margin-bottom:2rem;">
        <h3 style="margin-bottom:0.75rem;">Order Summary</h3>
        <?php foreach ($concessionItems as $ci): $sub = $ci['qty'] * (float)$ci['price']; ?>
          <div style="display:flex; justify-content:space-between; padding:0.35rem 0; border-bottom:1px solid rgba(0,0,0,0.06);">
            <span><?= e($ci['name']) ?><?= $ci['option'] ? ' (' . e($ci['option']) . ')' : '' ?> &times; <?= $ci['qty'] ?></span>
            <strong>$<?= number_format($sub, 2) ?></strong>
          </div>
        <?php endforeach; ?>
        <p style="margin-top:0.75rem; text-align:right;">
          Total: <strong style="color:var(--color-crimson);">$<?= number_format($totalAmount, 2) ?></strong>
        </p>
      </div>
    <?php endif; ?>

    <?php if ($checkoutError !== null): ?>
      <div class="alert alert-error" style="margin-bottom:1rem;"><?= e($checkoutError) ?></div>
      <a href="javascript:history.back()" class="btn btn-outline">Back</a>

    <?php elseif ($clientSecret): ?>
      <!-- ── Customer info (optional) ── -->
      <div style="margin-bottom:1.5rem;">
        <h3 style="margin-bottom:1rem;">Your Info <span style="font-weight:400; font-size:0.85rem; color:var(--color-text-muted);">(optional)</span></h3>
        <div class="form-group">
          <label for="cust-name">Name</label>
          <input type="text" id="cust-name" placeholder="Your name" style="width:100%; padding:0.6rem; border:1px solid #ddd; border-radius:4px;">
        </div>
        <div class="form-group" style="margin-top:0.75rem;">
          <label for="cust-email">Email</label>
          <input type="email" id="cust-email" placeholder="For your receipt (optional)" style="width:100%; padding:0.6rem; border:1px solid #ddd; border-radius:4px;">
        </div>
      </div>

      <form id="payment-form">
        <h3 style="margin-bottom:1rem;">Payment</h3>
        <div id="payment-element"></div>
        <div id="checkout-error" class="alert alert-error" style="display:none; margin:1rem 0;"></div>
        <button id="submit-btn" type="submit" class="btn btn-crimson" style="min-width:200px; margin-top:1.25rem;">
          Pay $<?= number_format($totalAmount, 2) ?>
        </button>
        <p style="margin-top:1rem; font-size:0.8rem; color:var(--color-text-muted);">
          Payments are processed securely by Stripe. Tickets and concessions are available at the box office.
        </p>
      </form>

      <script src="https://js.stripe.com/v3/"></script>
      <script>
      (function () {
        var stripe   = Stripe(<?= json_encode($stripeConfig['publishable_key']) ?>);
        var elements = stripe.elements({ clientSecret: <?= json_encode($clientSecret) ?> });
        var payEl    = elements.create('payment');
        payEl.mount('#payment-element');

        var CSRF    = <?= json_encode($csrfToken) ?>;
        var REF     = <?= json_encode($txnRef) ?>;
        var RETURN  = <?= json_encode(SITE_URL . 'confirmation.php?ref=' . $txnRef) ?>;
        var form    = document.getElementById('payment-form');
        var btn      = document.getElementById('submit-btn');
        var errBox   = document.getElementById('checkout-error');

        form.addEventListener('submit', async function (e) {
          e.preventDefault();
          btn.disabled = true;
          btn.textContent = 'Processing…';
          errBox.style.display = 'none';

          // Persist optional customer info to the pending transaction first.
          try {
            await fetch('api/order-customer.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                csrf_token: CSRF,
                ref:        REF,
                name:       (document.getElementById('cust-name').value  || '').trim(),
                email:      (document.getElementById('cust-email').value || '').trim()
              })
            });
          } catch (err) { /* non-fatal — proceed to payment */ }

          var result = await stripe.confirmPayment({
            elements: elements,
            confirmParams: { return_url: RETURN }
          });

          if (result.error) {
            errBox.textContent = result.error.message || 'Payment could not be completed. Please try again.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Pay $<?= number_format($totalAmount, 2) ?>';
          }
          // On success Stripe redirects to RETURN; no code runs after.
        });
      })();
      </script>
    <?php endif; ?>

  </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
