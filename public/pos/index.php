<?php
declare(strict_types=1);

/**
 * Employee POS — main order screen (/pos/index.php).
 *
 * Single-page register: tap product image cards (concessions + movie tickets)
 * to build an order, manage an always-visible cart, and check out with cash
 * (keypad + change) or card (mock confirm). Mirrors the approved mockup at
 * public/pos-preview/index.html.
 *
 * TICKET MODEL (documented assumption):
 *   Tickets use FLAT pricing — Adult $5 / Child $3 — the theater's standard.
 *   Tapping a now-showing movie opens a small Adult/Child overlay. The cart
 *   line's item_id is the movie's NEXT ACTIVE SHOWTIME with remaining capacity
 *   (the same showtime id space the website cart + Stripe webhook already use,
 *   so the existing admin Void / reporting code stays symmetric — void restores
 *   tickets by showtime id). Only movies that resolve to such a showtime get a
 *   ticket card; the rest are skipped. No seat selection — capacity is just the
 *   existing showtimes.available_tickets / tickets_sold counter.
 */

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/PosAuth.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/MovieRepo.php';

// Flat ticket pricing — keep in sync with pos-checkout.php.
const POS_TICKET_ADULT = 5.00;
const POS_TICKET_CHILD = 3.00;

PosAuth::bootstrap();

try {
    $db = Database::getInstance();
} catch (RuntimeException $e) {
    header('Location: login.php');
    exit;
}

$auth = new PosAuth($db);
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$csrf         = $auth->generateCsrfToken();
$operatorName = $auth->operatorName();
$initials     = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $operatorName) ?: 'OP', 0, 2));

/**
 * Resolve a movie's next active showtime that still has capacity.
 * Prefers the soonest future-dated showtime; falls back to sort order.
 *
 * @param array<int, array<string, mixed>> $showtimes
 * @return array<string, mixed>|null
 */
function posNextShowtime(array $showtimes): ?array
{
    $candidates = [];
    foreach ($showtimes as $st) {
        if (empty($st['is_active'])) {
            continue;
        }
        $remaining = (int)($st['available_tickets'] ?? 0) - (int)($st['tickets_sold'] ?? 0);
        if ($remaining < 1) {
            continue;
        }
        $candidates[] = $st;
    }
    if (!$candidates) {
        return null;
    }

    // Sort: dated showtimes first (soonest date/time), then by sort_order/id.
    usort($candidates, static function (array $a, array $b): int {
        $da = (string)($a['showtime_date'] ?? '');
        $db = (string)($b['showtime_date'] ?? '');
        if ($da !== '' && $db !== '' && $da !== $db) {
            return strcmp($da, $db);
        }
        if ($da !== $db) {
            return $da === '' ? 1 : -1; // dated before undated
        }
        $ta = (string)($a['showtime_time'] ?? '');
        $tb = (string)($b['showtime_time'] ?? '');
        if ($ta !== $tb) {
            return strcmp($ta, $tb);
        }
        $soA = (int)($a['sort_order'] ?? 0);
        $soB = (int)($b['sort_order'] ?? 0);
        return $soA <=> $soB ?: ((int)$a['id'] <=> (int)$b['id']);
    });

    return $candidates[0];
}

function posShowtimeLabel(array $st): string
{
    if (!empty($st['showtime_date'])) {
        try {
            $when = (new DateTime((string)$st['showtime_date']))->format('D, M j');
        } catch (\Throwable $e) {
            $when = '';
        }
        if (!empty($st['showtime_time'])) {
            $when .= ' ' . date('g:i A', strtotime((string)$st['showtime_time']));
        }
        if ($when !== '') {
            return $when;
        }
    }
    return (string)($st['label'] ?? '');
}

// ── Build the product catalog ────────────────────────────────────────────────
// Concessions (available only). Categories drive the filter tabs.
$concRepo    = new ConcessionRepo($db);
$concessions = $concRepo->getAvailable();

$products   = [];
$categories = [];

foreach ($concessions as $c) {
    $cat = (string)($c['category'] ?? 'Other');
    if ($cat !== '' && !in_array($cat, $categories, true)) {
        $categories[] = $cat;
    }

    $options = [];
    foreach ($concRepo->getOptions((int)$c['id']) as $opt) {
        if ((int)($opt['is_available'] ?? 1) === 1) {
            $options[] = (string)$opt['option_label'];
        }
    }

    $stock    = (int)($c['stock_quantity'] ?? 0);
    $reorder  = $c['reorder_point'] !== null ? (int)$c['reorder_point'] : null;
    $imageRel = assetRel((string)($c['image_path'] ?? ''));

    $products[] = [
        'kind'    => 'concession',
        'id'      => (int)$c['id'],
        'cat'     => $cat,
        'name'    => (string)$c['name'],
        'price'   => round((float)$c['price'], 2),
        'stock'   => $stock,
        'reorder' => $reorder,
        'image'   => $imageRel !== '' ? '../assets/' . $imageRel : '',
        'options' => $options,
    ];
}

// Movie tickets — one card per now-showing movie that resolves to an active
// showtime with remaining capacity. The card carries Adult/Child pricing and
// the resolved showtime id (used as transaction_items.item_id).
$ticketProducts = [];
foreach (MovieRepo::getNowShowing() as $movie) {
    $st = posNextShowtime($movie['showtimes'] ?? []);
    if ($st === null) {
        continue; // no purchasable showtime → skip (FOR UPDATE target must exist)
    }
    $remaining = (int)$st['available_tickets'] - (int)$st['tickets_sold'];
    $ticketProducts[] = [
        'kind'        => 'ticket',
        'showtime_id' => (int)$st['id'],
        'title'       => (string)$movie['title'],
        'when'        => posShowtimeLabel($st),
        'adult'       => POS_TICKET_ADULT,
        'child'       => POS_TICKET_CHILD,
        'remaining'   => max(0, $remaining),
        'image'       => !empty($movie['poster_path']) ? '../assets/' . assetRel((string)$movie['poster_path']) : '',
    ];
}

$hasTickets = !empty($ticketProducts);

// Tab order: Tickets first (if any), then concession categories.
$tabs = [];
if ($hasTickets) {
    $tabs[] = 'Tickets';
}
foreach ($categories as $cat) {
    $tabs[] = $cat;
}

$bootData = [
    'csrf'        => $csrf,
    'products'    => $products,
    'tickets'     => $ticketProducts,
    'hasTickets'  => $hasTickets,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex,nofollow">
    <title>Register — <?= e(SITE_NAME) ?> POS</title>
    <link rel="stylesheet" href="../assets/css/pos.css?v=<?= @filemtime(__DIR__ . '/../assets/css/pos.css') ?>">
</head>
<body>
<div class="stage">

  <!-- ===================== ORDER ===================== -->
  <section class="screen show" id="order">
    <div class="layout">
      <nav class="railcats">
        <div class="railbrand"><span class="dot">A</span> <span>The Alex</span></div>
        <div class="cats" id="cats"><!-- JS fills --></div>
      </nav>
      <div class="left">
        <div class="appbar">
          <div class="cattitle"><h1 id="catTitle">Menu</h1><span class="catsub" id="catSub"></span></div>
          <div class="spacer"></div>
          <div class="who"><span class="av"><?= e($initials) ?></span> <?= e($operatorName) ?></div>
        </div>
        <div class="grid-wrap">
          <div class="pgrid" id="pgrid"><!-- JS fills --></div>
        </div>
      </div>

      <aside class="cart">
        <div class="chead">
          <h2><svg class="ico" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h2l2.5 13h11l2-9H6"/></svg> Current Order</h2>
          <span class="ccount" id="ccount">0 items</span>
        </div>
        <div class="clines" id="clines"></div>
        <div class="cfoot">
          <div class="subrow"><span>Subtotal</span><span class="big tnum" id="subtot">$0.00</span></div>
          <button class="btn-checkout" id="goPay" disabled>Checkout <svg class="ico" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
          <button class="btn-clear" id="clearBtn"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m2 0v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7"/></svg> Clear Cart</button>
        </div>
        <div class="cart-logout">
          <a class="btn-lock" href="logout.php"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg> Log out</a>
        </div>
      </aside>
    </div>
  </section>

  <!-- ===================== PAYMENT METHOD ===================== -->
  <section class="screen" id="pay">
    <div class="appbar"><div class="brand"><span class="dot">A</span> Payment</div></div>
    <div class="backbar"><button class="back" data-go="order"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg> Back to order</button></div>
    <div class="pay-wrap">
      <div class="pay-grid">
        <div class="panel">
          <div class="ph">Order Summary</div>
          <div class="osum" id="paySummary"></div>
        </div>
        <div class="panel">
          <div class="totbox"><div class="lbl">Total Due</div><div class="amt tnum" id="payTotal">$0.00</div></div>
          <div class="paybtns">
            <button class="paybtn cash" data-go="cash"><svg class="ico ico-lg" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg> Cash</button>
            <button class="paybtn card" data-go="card"><svg class="ico ico-lg" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg> Card</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== CASH ===================== -->
  <section class="screen" id="cash">
    <div class="appbar"><div class="brand"><span class="dot">A</span> Cash Payment</div></div>
    <div class="backbar"><button class="back" data-go="pay"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg> Payment methods</button></div>
    <div class="cash-wrap">
      <div class="tender">
        <div class="due">Total due <b class="tnum" id="cashDue">$0.00</b> · Cash received</div>
        <div class="entry tnum" id="cashEntry">$0.00</div>
        <div class="change" id="cashChange" style="display:none">Change due: <span class="tnum" id="changeAmt">$0.00</span></div>
      </div>
      <div class="quickcash" id="quickcash">
        <button type="button" data-amt="exact">Exact</button>
        <button type="button" data-amt="20">$20</button>
        <button type="button" data-amt="50">$50</button>
        <button type="button" data-amt="100">$100</button>
      </div>
      <div class="keypad" id="cashpad">
        <button type="button" class="key">1</button><button type="button" class="key">2</button><button type="button" class="key">3</button>
        <button type="button" class="key">4</button><button type="button" class="key">5</button><button type="button" class="key">6</button>
        <button type="button" class="key">7</button><button type="button" class="key">8</button><button type="button" class="key">9</button>
        <button type="button" class="key">.</button><button type="button" class="key">0</button><button type="button" class="key">&#9003;</button>
      </div>
      <button class="btn-checkout" style="width:100%;margin-top:18px" id="confirmCash" disabled>Confirm Cash Payment</button>
    </div>
  </section>

  <!-- ===================== CARD ===================== -->
  <section class="screen" id="card">
    <div class="appbar"><div class="brand"><span class="dot">A</span> Card Payment</div></div>
    <div class="backbar"><button class="back" data-go="pay"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg> Payment methods</button></div>
    <div class="wait-wrap">
      <div class="reader pulse"><svg class="ico" viewBox="0 0 24 24"><path d="M5 12a9 9 0 0 1 14 0"/><path d="M8.5 15a5 5 0 0 1 7 0"/><path d="M11 18h2"/></svg></div>
      <h3 style="font-size:22px;font-weight:800">Present card to reader</h3>
      <p style="color:var(--c-text-muted);margin-top:8px;font-size:15px">Total due <b class="tnum" id="cardTotal">$0.00</b></p>
      <button class="btn-checkout" style="margin-top:28px;min-width:280px" id="confirmCard">Confirm Card Payment <span style="font-size:12px;opacity:.8">(mock)</span></button>
      <p style="color:var(--c-text-muted);margin-top:14px;font-size:12px;max-width:380px;margin-inline:auto">
        Placeholder for a real Square / Stripe Terminal reader callback.</p>
    </div>
  </section>

  <!-- ===================== COMPLETE ===================== -->
  <section class="screen" id="done">
    <div class="done-wrap">
      <div class="done-check"><svg class="ico" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg></div>
      <h2 style="font-size:28px">Order Complete</h2>
      <p style="color:var(--c-text-2);font-size:16px">Transaction <b class="tnum" id="doneRef">—</b> · <b class="tnum" id="donePaid">$0.00</b></p>
      <p style="color:var(--c-text-muted);font-size:14px" id="doneChange"></p>
      <button class="btn-checkout" style="min-width:240px;margin-top:8px" id="newOrderBtn">New Order</button>
    </div>
  </section>

</div><!-- /stage -->

<!-- options / ticket overlay -->
<div class="overlay" id="overlay">
  <div class="sheet">
    <div class="opt-poster" id="optPoster"></div>
    <h3 id="optTitle">Choose an option</h3>
    <p id="optSub">One tap adds it to the order.</p>
    <div class="optgrid" id="optGrid"></div>
    <button type="button" class="cancel" id="optCancel">Cancel</button>
  </div>
</div>

<div class="toast" id="toast"><svg class="ico" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg><span id="toastMsg">Added</span></div>

<script>window.POS_BOOT = <?= json_encode($bootData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="../assets/js/pos.js?v=<?= @filemtime(__DIR__ . '/../assets/js/pos.js') ?>"></script>
</body>
</html>
