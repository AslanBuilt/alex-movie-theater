<?php
declare(strict_types=1);
$currentPage = 'index';
$showCart = true;
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/MovieRepo.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';

$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$movie = null;

if ($id > 0) {
    $movie = tryDb(fn() => MovieRepo::getById($id), null);
}

if ($movie === null) {
    $pageTitle       = 'Movie Not Found | The Alex — Alexandria, Indiana';
    $pageDescription = 'Movie information at The Alex in Alexandria, Indiana.';
} else {
    $pageTitle       = htmlspecialchars($movie['title']) . ' | The Alex — Alexandria, Indiana';
    $pageDescription = $movie['description']
        ? htmlspecialchars(substr($movie['description'], 0, 155))
        : 'Now showing at The Alex in Alexandria, Indiana.';
    $ogImage = !empty($movie['poster_path'])
        ? SITE_URL . 'assets/' . ltrim($movie['poster_path'], '/')
        : null;
}

require __DIR__ . '/templates/header.php';
?>

<?php if ($movie === null): ?>
<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span>Movie</p>
    <h1>Movie Not Found</h1>
    <p class="subtitle">This film may have ended its run or the link is outdated.</p>
  </div>
</section>
<section>
  <div class="container" style="text-align:center; padding:4rem 0;">
    <p class="text-secondary" style="margin-bottom:2rem;">Check what&rsquo;s playing now:</p>
    <a href="index.php#now-showing" class="btn btn-crimson">See Now Showing</a>
  </div>
</section>

<?php else:
  $showtimes    = $movie['showtimes'] ?? [];

  // Separate new-style (date+time set) from legacy (label/times only)
  $newShowtimes  = array_filter($showtimes, fn($s) => !empty($s['showtime_date']) && !empty($s['showtime_time']));
  $legShowtimes  = array_filter($showtimes, fn($s) => empty($s['showtime_time']));

  // Group new-style showtimes by date
  $byDate = [];
  foreach ($newShowtimes as $st) {
      if (!(isset($st['is_active']) && (int)$st['is_active'] === 0)) {
          $byDate[$st['showtime_date']][] = $st;
      }
  }
  ksort($byDate);
?>

<section class="page-hero">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><a href="index.php#now-showing">Now Showing</a><span class="sep">/</span><?= e($movie['title']) ?></p>
    <h1><?= e($movie['title']) ?></h1>
    <p class="subtitle">
      <?php if ($movie['screen'] === 'large'): ?>Large Screen<?php elseif ($movie['screen'] === 'small'): ?>Small Screen<?php endif; ?>
      <?php if (!empty($movie['rating'])): ?>&bull; Rated <?= e($movie['rating']) ?><?php endif; ?>
    </p>
  </div>
</section>

<section>
  <div class="container">
    <div class="movie-detail-layout">

      <!-- Poster -->
      <div class="movie-detail-poster">
        <?php if (!empty($movie['poster_path'])): ?>
          <img src="assets/<?= e($movie['poster_path']) ?>" alt="<?= e($movie['title']) ?> movie poster" loading="eager">
        <?php else: ?>
          <div class="movie-poster-placeholder"><?= e($movie['title']) ?></div>
        <?php endif; ?>
        <?php if (!empty($movie['rating'])): ?>
          <span class="movie-rating" style="margin-top:0.75rem; display:inline-block;"><?= e($movie['rating']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="movie-detail-info">
        <?php if (!empty($movie['description'])): ?>
          <p class="movie-detail-desc"><?= e($movie['description']) ?></p>
        <?php endif; ?>

        <?php if ($movie['screen'] === 'small' || $movie['online_only']): ?>
          <div class="highlight-box" style="margin-bottom:1.5rem;">
            <p><strong>Online purchase required</strong> for this film. Tickets for the small screen must be purchased online before your visit.</p>
          </div>
        <?php endif; ?>

        <?php if (!empty($byDate)): ?>
          <!-- ── New-style transactional showtimes ── -->
          <div class="section-header">
            <p class="section-label">Pick a day &amp; time</p>
            <h2 class="section-title" style="font-size:1.6rem;">Showtimes</h2>
            <div class="section-divider"></div>
          </div>

          <div class="day-tabs" id="showtime-day-tabs">
            <?php $first = true; foreach ($byDate as $date => $slots): ?>
              <?php
                $dateLabel = (new DateTime($date))->format('D, M j');
                $today     = (new DateTime())->format('Y-m-d');
                $tomorrow  = (new DateTime('+1 day'))->format('Y-m-d');
                if ($date === $today)    $dateLabel = 'Today';
                if ($date === $tomorrow) $dateLabel = 'Tomorrow';
                // Encode slot IDs for JS
                $slotIds = array_column($slots, 'id');
                $timesStr = implode(' • ', array_map(fn($s) => date('g:i A', strtotime($s['showtime_time'])), $slots));
                $firstSlotId = $slots[0]['id'] ?? 0;
              ?>
              <button
                class="day-tab<?= $first ? ' active' : '' ?>"
                data-times="<?= e($timesStr) ?>"
                data-date="<?= e($date) ?>"
                data-slots="<?= e(json_encode(array_map(fn($s) => [
                  'id'        => (int)$s['id'],
                  'time'      => date('g:i A', strtotime($s['showtime_time'])),
                  'available' => max(0, (int)$s['available_tickets'] - (int)$s['tickets_sold']),
                ], $slots))) ?>"
                data-track="showtime-day"
                data-track-label="<?= e($movie['title'] . ' — ' . $dateLabel) ?>"
                role="tab"
                aria-selected="<?= $first ? 'true' : 'false' ?>">
                <?= e($dateLabel) ?>
              </button>
            <?php $first = false; endforeach; ?>
          </div>

          <!-- Time buttons rendered by JS from data-slots -->
          <div class="time-btn-group" id="showtime-time-btns"></div>

          <!-- Quantity + checkout (shown after time selection) -->
          <div id="ticket-purchase-box" style="display:none; margin-top:1.5rem;">
            <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1rem;">
              <label style="font-weight:700; color:var(--color-text);">Tickets:</label>
              <div class="qty-control">
                <button type="button" id="qty-dec" class="qty-btn" aria-label="Fewer tickets">&#8722;</button>
                <span id="qty-display" style="min-width:2rem; text-align:center; font-weight:700;">1</span>
                <button type="button" id="qty-inc" class="qty-btn" aria-label="More tickets">&#43;</button>
              </div>
              <span id="ticket-total" style="font-size:1.1rem; font-weight:700; color:var(--color-crimson);">$5.00</span>
            </div>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
              <a href="#" id="btn-proceed-checkout" class="btn btn-crimson"
                 data-track="checkout-start"
                 data-track-label="<?= e($movie['title']) ?>">
                Proceed to Checkout
              </a>
              <a href="index.php#now-showing" class="btn btn-outline">All Movies</a>
            </div>
            <p style="margin-top:0.75rem; font-size:0.8rem; color:var(--color-text-muted);">Adults $5 &bull; Children $3 &bull; Pay at door or online</p>
          </div>

        <?php elseif (!empty($legShowtimes)): ?>
          <!-- ── Legacy label/times showtimes ── -->
          <div class="section-header">
            <p class="section-label">This Week</p>
            <h2 class="section-title" style="font-size:1.6rem;">Showtimes</h2>
            <div class="section-divider"></div>
          </div>

          <div class="day-tabs" id="showtime-day-tabs">
            <?php foreach ($legShowtimes as $idx => $st): ?>
              <button
                class="day-tab<?= $idx === 0 ? ' active' : '' ?>"
                data-times="<?= e($st['times']) ?>"
                data-track="showtime-day"
                data-track-label="<?= e($movie['title'] . ' — ' . $st['label']) ?>"
                role="tab"
                aria-selected="<?= $idx === 0 ? 'true' : 'false' ?>">
                <?= e($st['label']) ?>
              </button>
            <?php endforeach; ?>
          </div>

          <?php
            $firstTimes = !empty(array_values($legShowtimes)[0]['times'])
              ? preg_split('/\s*[•·]\s*/', array_values($legShowtimes)[0]['times'])
              : [];
          ?>
          <div class="time-btn-group" id="showtime-time-btns">
            <?php foreach ($firstTimes as $t): $t = trim($t); if ($t === '') continue; ?>
              <span class="time-btn"
                    data-track="showtime-click"
                    data-track-label="<?= e($movie['title'] . ' — ' . $t) ?>">
                <?= e($t) ?>
              </span>
            <?php endforeach; ?>
          </div>

          <div class="movie-cta" style="margin-top:1rem; display:flex; gap:1rem; flex-wrap:wrap;">
            <a href="tickets.php" class="btn btn-crimson" data-track="buy-tickets" data-track-label="<?= e($movie['title']) ?>">Buy Tickets</a>
            <a href="index.php#now-showing" class="btn btn-outline">All Movies</a>
          </div>

        <?php else: ?>
          <div class="policy-box" style="margin-bottom:1.5rem;">
            <h3>Showtimes</h3>
            <p>Showtimes for this film are not yet listed online. Call us at <a href="tel:765-620-9093">(765) 620-9093</a> to confirm times before your visit.</p>
          </div>
          <div class="movie-cta" style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap;">
            <a href="tickets.php" class="btn btn-crimson">Buy Tickets</a>
            <a href="index.php#now-showing" class="btn btn-outline">All Movies</a>
          </div>
        <?php endif; ?>

        <div class="policy-box mt-3">
          <h3>Showtime Policy</h3>
          <p>The theatre reserves the right to adjust showtimes, screens, or auditoriums based on equipment issues or when ticket sales exceed 20 seats.</p>
        </div>
      </div>

    </div>
  </div>
</section>

<script>
(function () {
  var TICKET_PRICE = 5.00;
  var selectedShowtimeId = 0;
  var selectedTime = '';
  var qty = 1;
  var maxQty = 1;

  var dayTabs   = document.getElementById('showtime-day-tabs');
  var timeBtns  = document.getElementById('showtime-time-btns');
  var purchBox  = document.getElementById('ticket-purchase-box');
  var qtyDisp   = document.getElementById('qty-display');
  var totalDisp = document.getElementById('ticket-total');
  var btnDec    = document.getElementById('qty-dec');
  var btnInc    = document.getElementById('qty-inc');
  var btnCheckout = document.getElementById('btn-proceed-checkout');

  if (!dayTabs || !timeBtns) return;

  function renderSlots(slotsJson) {
    var slots = [];
    try { slots = JSON.parse(slotsJson); } catch(e) { return; }
    timeBtns.innerHTML = slots.map(function (s) {
      var avail = s.available;
      var soldOut = avail <= 0;
      var cls = 'time-btn' + (soldOut ? ' time-btn--sold-out' : '');
      return '<button type="button" class="' + cls + '" ' +
             'data-showtime-id="' + s.id + '" ' +
             'data-time="' + s.time.replace(/"/g, '&quot;') + '" ' +
             'data-available="' + avail + '" ' +
             (soldOut ? 'disabled ' : '') +
             'data-track="showtime-click" data-track-label="' + s.time.replace(/"/g, '&quot;') + '">' +
             s.time + (soldOut ? ' <small>(Sold Out)</small>' : '') + '</button>';
    }).join('');
  }

  // Render first tab on load
  var firstTab = dayTabs.querySelector('.day-tab.active');
  if (firstTab) {
    var slotsAttr = firstTab.getAttribute('data-slots');
    if (slotsAttr) renderSlots(slotsAttr);
  }

  // Day tab clicks
  dayTabs.addEventListener('click', function (e) {
    var btn = e.target.closest('.day-tab');
    if (!btn) return;
    dayTabs.querySelectorAll('.day-tab').forEach(function (b) {
      b.classList.remove('active');
      b.setAttribute('aria-selected', 'false');
    });
    btn.classList.add('active');
    btn.setAttribute('aria-selected', 'true');
    var slotsAttr = btn.getAttribute('data-slots');
    if (slotsAttr) {
      renderSlots(slotsAttr);
    } else {
      // Legacy label/times mode
      var timesStr = btn.getAttribute('data-times') || '';
      var times = timesStr.split(/\s*[•·]\s*/).map(function (t) { return t.trim(); }).filter(Boolean);
      timeBtns.innerHTML = times.map(function (t) {
        return '<span class="time-btn" data-track="showtime-click" data-track-label="' +
               t.replace(/"/g, '&quot;') + '">' + t + '</span>';
      }).join('');
    }
    if (purchBox) purchBox.style.display = 'none';
    selectedShowtimeId = 0;
  });

  // Time button clicks
  timeBtns.addEventListener('click', function (e) {
    var btn = e.target.closest('button.time-btn');
    if (!btn || btn.disabled) return;
    timeBtns.querySelectorAll('.time-btn').forEach(function (b) {
      b.classList.remove('active');
    });
    btn.classList.add('active');
    selectedShowtimeId = parseInt(btn.getAttribute('data-showtime-id') || '0', 10);
    selectedTime       = btn.getAttribute('data-time') || '';
    maxQty = Math.min(10, parseInt(btn.getAttribute('data-available') || '10', 10));
    qty = 1;
    if (qtyDisp) qtyDisp.textContent = qty;
    if (totalDisp) totalDisp.textContent = '$' + (qty * TICKET_PRICE).toFixed(2);
    if (purchBox && selectedShowtimeId > 0) purchBox.style.display = 'block';
  });

  // Quantity controls
  if (btnDec) btnDec.addEventListener('click', function () {
    if (qty > 1) { qty--; update(); }
  });
  if (btnInc) btnInc.addEventListener('click', function () {
    if (qty < maxQty) { qty++; update(); }
  });

  function update() {
    if (qtyDisp) qtyDisp.textContent = qty;
    if (totalDisp) totalDisp.textContent = '$' + (qty * TICKET_PRICE).toFixed(2);
  }

  // Checkout button
  if (btnCheckout) btnCheckout.addEventListener('click', function (e) {
    e.preventDefault();
    if (!selectedShowtimeId) return;
    window.location.href = 'checkout.php?showtime=' + selectedShowtimeId + '&qty=' + qty + '&t=' + encodeURIComponent(selectedTime);
  });
})();
</script>

<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
