<?php
declare(strict_types=1);

$currentPage = '';
$showCart = true;
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

session_name('ALEX_ADMIN_SESS');
session_start();

// Generate/refresh CSRF token for the checkout form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Detect mode: ticket checkout vs concession cart
$mode = 'ticket'; // default

// Ticket mode: ?showtime=ID&qty=N
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
    // Concession cart mode (items in session)
    $mode = 'concession';
    $cart = $_SESSION['cart'] ?? [];
    if (!empty($cart)) {
        $pdo  = Database::getInstance();
        $repo = new ConcessionRepo($pdo);
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

$pageTitle       = 'Checkout | The Alex — Alexandria, Indiana';
$pageDescription = 'Complete your purchase at The Alex Theater.';

require __DIR__ . '/templates/header.php';
?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Checkout</p>
    <h1>Checkout</h1>
    <p class="subtitle">Review your order and confirm</p>
  </div>
</section>

<section>
  <div class="container" style="max-width:640px;">

    <?php if ($mode === 'ticket' && $showtime): ?>
      <!-- ── Ticket order summary ── -->
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
      <!-- ── Concession order summary ── -->
      <div class="policy-box" style="margin-bottom:2rem;">
        <h3 style="margin-bottom:0.75rem;">Order Summary</h3>
        <?php $total = 0; foreach ($concessionItems as $ci): $sub = $ci['qty'] * (float)$ci['price']; $total += $sub; ?>
          <div style="display:flex; justify-content:space-between; padding:0.35rem 0; border-bottom:1px solid rgba(0,0,0,0.06);">
            <span><?= e($ci['name']) ?><?= $ci['option'] ? ' (' . e($ci['option']) . ')' : '' ?> &times; <?= $ci['qty'] ?></span>
            <strong>$<?= number_format($sub, 2) ?></strong>
          </div>
        <?php endforeach; ?>
        <p style="margin-top:0.75rem; text-align:right;">
          Total: <strong style="color:var(--color-crimson);">$<?= number_format($total, 2) ?></strong>
        </p>
      </div>
    <?php endif; ?>

    <!-- ── Customer info (optional) ── -->
    <div style="margin-bottom:2rem;">
      <h3 style="margin-bottom:1rem;">Your Info <span style="font-weight:400; font-size:0.85rem; color:var(--color-text-muted);">(optional)</span></h3>
      <div class="form-group">
        <label for="cust-name">Name</label>
        <input type="text" id="cust-name" placeholder="Your name" style="width:100%; padding:0.6rem; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div class="form-group" style="margin-top:0.75rem;">
        <label for="cust-email">Email</label>
        <input type="email" id="cust-email" placeholder="For confirmation receipt (optional)" style="width:100%; padding:0.6rem; border:1px solid #ddd; border-radius:4px;">
      </div>
    </div>

    <!-- ── Pay button ── -->
    <div id="checkout-error" class="alert alert-error" style="display:none; margin-bottom:1rem;"></div>
    <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
      <button id="btn-pay" class="btn btn-crimson" style="min-width:180px;" data-track="payment-submit">
        Confirm &amp; Pay
      </button>
      <a href="javascript:history.back()" class="btn btn-outline">Back</a>
    </div>
    <p style="margin-top:1rem; font-size:0.8rem; color:var(--color-text-muted);">
      Payment processed securely. Tickets and concessions available at the box office.
    </p>

  </div>
</section>

<script>
(function () {
  var CSRF  = <?= json_encode($csrfToken) ?>;
  var MODE  = <?= json_encode($mode) ?>;
  var ITEMS = <?php
    if ($mode === 'ticket' && $showtime) {
        echo json_encode([[
            'type' => 'ticket',
            'id'   => (int)$showtime['id'],
            'qty'  => $qty,
        ]]);
    } else {
        $cartItems = array_map(fn($ci) => [
            'type'   => 'concession',
            'id'     => (int)$ci['id'],
            'qty'    => (int)$ci['qty'],
            'option' => $ci['option'] ?? null,
        ], $concessionItems);
        echo json_encode($cartItems);
    }
  ?>;

  document.getElementById('btn-pay').addEventListener('click', function () {
    var btn      = this;
    var errBox   = document.getElementById('checkout-error');
    var custName = (document.getElementById('cust-name').value  || '').trim();
    var custEmail= (document.getElementById('cust-email').value || '').trim();

    btn.disabled    = true;
    btn.textContent = 'Processing...';
    errBox.style.display = 'none';

    fetch('api/checkout.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        csrf_token: CSRF,
        items:      ITEMS,
        customer:   {name: custName, email: custEmail},
      }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        window.location.href = 'confirmation.php?ref=' + encodeURIComponent(data.transaction_ref);
      } else {
        errBox.textContent   = data.error || 'Payment failed. Please try again.';
        errBox.style.display = 'block';
        btn.disabled         = false;
        btn.textContent      = 'Confirm & Pay';
      }
    })
    .catch(function () {
      errBox.textContent   = 'Network error. Please try again.';
      errBox.style.display = 'block';
      btn.disabled         = false;
      btn.textContent      = 'Confirm & Pay';
    });
  });
})();
</script>

<?php require __DIR__ . '/templates/footer.php'; ?>
