<?php
declare(strict_types=1);

$currentPage = '';
$showCart = true;
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/MovieRepo.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/StripeService.php';
require_once INCLUDES_PATH . '/RateLimiter.php';

session_name('ALEX_ADMIN_SESS');
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Stripe config (generated from secrets at deploy) ──────────────────────────
$stripeConfigPath = __DIR__ . '/config/stripe.php';
$stripeConfig     = is_file($stripeConfigPath) ? require $stripeConfigPath : null;

// ── Detect mode ───────────────────────────────────────────────────────────────
// 'ticket' = direct "Buy Now" for a single showtime (?showtime=&qty=).
// 'cart'   = the session cart, which may hold tickets AND concessions together.
// Ticket prices come from ticketPrice() (includes/helpers.php), sourced from the
// TICKET_PRICE_ADULT/TICKET_PRICE_CHILD constants — the single source of truth.
$mode       = 'ticket';
$showtimeId = isset($_GET['showtime']) ? (int)$_GET['showtime'] : 0;
// qty_adult/qty_child are the current params; qty (legacy, all-adult) is kept
// as a fallback for any old links still in circulation.
$qtyAdult   = isset($_GET['qty_adult']) ? max(0, (int)$_GET['qty_adult']) : (isset($_GET['qty']) ? max(0, (int)$_GET['qty']) : 0);
$qtyChild   = isset($_GET['qty_child']) ? max(0, (int)$_GET['qty_child']) : 0;
if ($qtyAdult + $qtyChild < 1) $qtyAdult = 1;
$qty        = $qtyAdult + $qtyChild;
$timeLabel  = isset($_GET['t'])        ? urldecode($_GET['t']) : '';

$showtime = null;   // populated in direct-ticket mode for the summary block
$movie    = null;

/** Human label for a showtime row ("Mon, Jun 30 7:00 PM"), falling back to its label. */
$showtimeWhen = function (array $st) use ($timeLabel): string {
    if (!empty($st['showtime_date'])) {
        $w = (new DateTime($st['showtime_date']))->format('D, M j');
        if (!empty($st['showtime_time'])) {
            $w .= ' ' . date('g:i A', strtotime((string)$st['showtime_time']));
        }
        return $w;
    }
    return (string)($st['label'] ?? $timeLabel);
};

// ── Build authoritative, server-priced line items + validate availability ─────
// Prices come from the DB, never the client.
$lineItems     = [];
$totalAmount   = 0.0;
$hasTicket     = false;
$hasConcession = false;
$checkoutError = null;

if ($showtimeId > 0) {
    // ── Direct single-ticket purchase ──
    $mode     = 'ticket';
    $showtime = tryDb(fn() => ShowtimeRepo::getById($showtimeId), null);
    if ($showtime === null) {
        header('Location: index.php');
        exit;
    }
    $movie = tryDb(fn() => MovieRepo::getById((int)$showtime['movie_id']), null);

    if (empty($showtime['is_active'])) {
        $checkoutError = 'This showtime is no longer available.';
    } else {
        $available = (int)$showtime['available_tickets'] - (int)$showtime['tickets_sold'];
        if ($available < $qty) {
            $checkoutError = "Only $available ticket(s) remain for this showtime.";
        } else {
            $baseName = 'Ticket: ' . ($movie['title'] ?? 'Movie');
            $when = $showtimeWhen($showtime);
            if ($when !== '') $baseName .= ' — ' . $when;
            foreach (['Adult' => $qtyAdult, 'Child' => $qtyChild] as $age => $ageQty) {
                if ($ageQty < 1) continue;
                $price = ticketPrice($age);
                $lineItems[] = [
                    'item_type' => 'ticket', 'item_id' => (int)$showtime['id'],
                    'item_name' => $baseName,
                    'quantity'  => $ageQty, 'unit_price' => $price, 'selected_option' => $age,
                    'subtotal'  => round($price * $ageQty, 2),
                ];
                $totalAmount += $price * $ageQty;
            }
            $hasTicket = true;
        }
    }
} else {
    // ── Cart purchase: tickets and/or concessions in one transaction ──
    $mode = 'cart';
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) {
        $checkoutError = 'Your cart is empty.';
    } else {
        $concRepo = new ConcessionRepo(Database::getInstance());

        // Tickets may appear as separate Adult/Child entries for the same
        // showtime — validate their combined qty against capacity, since each
        // entry is priced/checked independently below.
        $ticketQtyByShowtime = [];
        foreach ($cart as $entry) {
            if (($entry['type'] ?? 'concession') === 'ticket') {
                $sid = (int)($entry['id'] ?? 0);
                $ticketQtyByShowtime[$sid] = ($ticketQtyByShowtime[$sid] ?? 0) + max(0, (int)($entry['qty'] ?? 0));
            }
        }

        foreach ($cart as $entry) {
            $eqty = max(0, (int)($entry['qty'] ?? 0));
            if ($eqty < 1) continue;

            if (($entry['type'] ?? 'concession') === 'ticket') {
                $sid = (int)$entry['id'];
                $st  = tryDb(fn() => ShowtimeRepo::getById($sid), null);
                if (!$st || empty($st['is_active'])) {
                    $checkoutError = 'A showtime in your cart is no longer available. Please review your cart.';
                    break;
                }
                $available = (int)$st['available_tickets'] - (int)$st['tickets_sold'];
                $mv = tryDb(fn() => MovieRepo::getById((int)$st['movie_id']), null);
                if ($available < ($ticketQtyByShowtime[$sid] ?? $eqty)) {
                    $checkoutError = 'Only ' . max(0, $available) . ' ticket(s) remain for ' . ($mv['title'] ?? 'a showtime') . '.';
                    break;
                }
                $age   = in_array($entry['option'] ?? null, ['Adult', 'Child'], true) ? $entry['option'] : 'Adult';
                $price = ticketPrice($age);
                $name  = 'Ticket: ' . ($mv['title'] ?? 'Movie');
                $when  = $showtimeWhen($st);
                if ($when !== '') $name .= ' — ' . $when;
                $lineItems[] = [
                    'item_type' => 'ticket', 'item_id' => $sid,
                    'item_name' => $name,
                    'quantity'  => $eqty, 'unit_price' => $price, 'selected_option' => $age,
                    'subtotal'  => round($price * $eqty, 2),
                ];
                $totalAmount += $price * $eqty;
                $hasTicket = true;
            } else {
                $ci = $concRepo->getById((int)$entry['id']);
                if (!$ci || empty($ci['is_available'])) {
                    $checkoutError = 'An item in your cart is no longer available. Please review your cart.';
                    break;
                }
                if ((int)$ci['stock_quantity'] > 0 && $eqty > (int)$ci['stock_quantity']) {
                    $checkoutError = 'Not enough stock for: ' . $ci['name'];
                    break;
                }
                $unit = (float)$ci['price'];
                $lineItems[] = [
                    'item_type' => 'concession', 'item_id' => (int)$ci['id'],
                    'item_name' => $ci['name'], 'quantity' => $eqty,
                    'unit_price' => $unit, 'selected_option' => $entry['option'] ?? null,
                    'subtotal'  => round($unit * $eqty, 2),
                ];
                $totalAmount += $unit * $eqty;
                $hasConcession = true;
            }
        }
    }
}
$totalAmount = round($totalAmount, 2);

$txnType = $hasTicket && $hasConcession ? 'combo' : ($hasTicket ? 'ticket' : 'concession');

// ── Create the pending transaction + Stripe PaymentIntent (checkout-start) ────
$clientSecret = null;
$txnRef       = null;
if ($checkoutError === null && !empty($lineItems)) {
    if (!RateLimiter::allow('checkout:' . RateLimiter::clientIp(), 10, 60)) {
        $checkoutError = 'You are starting checkout too frequently. Please wait a moment and try again, or call us at ' . SITE_PHONE . '.';
    } elseif (!$stripeConfig) {
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
            // Tag cart-origin orders so confirmation.php clears the right cart.
            if ($mode === 'cart') {
                $_SESSION['cart_pending_ref'] = $txnRef;
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

    <?php if (!empty($lineItems)): ?>
      <div class="policy-box" style="margin-bottom:2rem;">
        <h3 style="margin-bottom:0.75rem;">Order Summary</h3>
        <?php if ($mode === 'ticket' && $showtime): ?>
          <p style="margin:0 0 0.75rem; color:var(--color-text-muted);">
            <strong style="color:var(--color-text);"><?= e($movie ? $movie['title'] : 'Movie') ?></strong> —
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
        <?php endif; ?>
        <?php foreach ($lineItems as $li): ?>
          <div style="display:flex; justify-content:space-between; padding:0.35rem 0; border-bottom:1px solid rgba(0,0,0,0.06);">
            <span><?= e($li['item_name']) ?><?= $li['selected_option'] ? ' (' . e($li['selected_option']) . ')' : '' ?> &times; <?= (int)$li['quantity'] ?></span>
            <strong>$<?= number_format((float)$li['subtotal'], 2) ?></strong>
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
