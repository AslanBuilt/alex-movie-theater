<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/MovieRepo.php';
require_once INCLUDES_PATH . '/helpers.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');

$boot = [
    'concessions' => [],
    'showtimes'   => [],
];

try {
    $db = Database::getInstance();
    $conRepo = new ConcessionRepo($db);
    $available = $conRepo->getAvailable();

    $categories = [];
    foreach ($available as $row) {
        $category = trim((string)$row['category'] ?: 'Other');
        if (!isset($categories[$category])) {
            $categories[$category] = [
                'name'  => $category,
                'items' => [],
            ];
        }

        $options = [];
        try {
            foreach ($conRepo->getOptions((int)$row['id']) as $opt) {
                if ((int)($opt['is_available'] ?? 0) === 1) {
                    $options[] = (string)$opt['option_label'];
                }
            }
        } catch (Throwable $e) {
            // ignore option-loading failures; the item can still be ordered if it has no options.
        }

        $categories[$category]['items'][] = [
            'id'          => (int)$row['id'],
            'name'        => (string)$row['name'],
            'description' => (string)$row['description'],
            'price'       => (float)$row['price'],
            'stock'       => (int)$row['stock_quantity'],
            'image'       => posterUrl((string)($row['image_path'] ?? '')),
            'options'     => $options,
        ];
    }

    $stmt = $db->query(
        'SELECT s.id, s.movie_id, s.available_tickets, s.tickets_sold, s.showtime_date, s.showtime_time, s.label, m.title, m.poster_path
         FROM showtimes s
         LEFT JOIN movies m ON m.id = s.movie_id
         WHERE s.is_active = 1
         ORDER BY s.sort_order ASC, s.id ASC'
    );
    foreach ($stmt->fetchAll() as $row) {
        $availableSeats = max(0, (int)$row['available_tickets'] - (int)$row['tickets_sold']);
        $when = '';
        if (!empty($row['showtime_date'])) {
            try {
                $when = (new DateTime((string)$row['showtime_date']))->format('D, M j');
            } catch (Throwable $e) {
                $when = '';
            }
            if (!empty($row['showtime_time'])) {
                $when .= ' ' . date('g:i A', strtotime((string)$row['showtime_time']));
            }
        }
        if ($availableSeats < 1) {
            continue;
        }
        $boot['showtimes'][] = [
            'showtime_id' => (int)$row['id'],
            'movie_id'    => (int)$row['movie_id'],
            'title'       => (string)($row['title'] ?? 'Movie'),
            'when'        => $when !== '' ? $when : (string)($row['label'] ?? ''),
            'image'       => posterUrl((string)($row['poster_path'] ?? '')),
            'adult'       => TICKET_PRICE_ADULT,
            'child'       => TICKET_PRICE_CHILD,
            'available'   => $availableSeats,
        ];
    }

    $boot['concessions'] = array_values($categories);
} catch (Throwable $e) {
    error_log('[kiosk/index] ' . $e->getMessage());
}

$pageTitle       = 'Self-Service Kiosk | The Alex';
$pageDescription = 'Buy tickets and concessions at The Alex self-service kiosk.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="../assets/css/kiosk.css?v=<?= @filemtime(__DIR__ . '/../assets/css/kiosk.css') ?>">
  <script>
    window.KIOSK_BOOT = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
  </script>
</head>
<body>
  <div class="identity-banner">
    <span class="identity-icon">🎬</span>
    <div><strong>CONCESSION &amp; TICKET ORDERING KIOSK</strong><span>Mount this tablet at the concession stand. Customers tap here to order.</span></div>
  </div>
  <div class="kiosk-shell">
    <div class="kiosk-screen kiosk-screen-welcome show" id="welcome-screen">
      <div class="welcome-copy">
        <img class="welcome-logo" src="../assets/images/logo.webp" alt="The Alex logo" width="180" height="180">
        <h1>Tap to Start</h1>
        <p>Order tickets and concessions at your own pace.</p>
      </div>
      <div class="welcome-cta">Tap anywhere to begin</div>
    </div>

    <div class="kiosk-screen" id="menu-screen">
      <header class="kiosk-header">
        <div>
          <div class="kiosk-brand">The Alex</div>
          <div class="kiosk-subtitle">Tickets &amp; Concessions</div>
        </div>
        <button type="button" class="kiosk-action" id="menu-cart-btn">View Cart</button>
      </header>
      <div class="kiosk-tabs" id="category-tabs"></div>
      <div class="kiosk-grid" id="menu-grid"></div>
      <footer class="kiosk-footer">
        <div class="kiosk-footer-summary" id="menu-summary"></div>
        <button type="button" class="btn btn-primary" id="menu-go-cart">Review Order</button>
      </footer>
    </div>

    <div class="kiosk-screen" id="cart-screen">
      <header class="kiosk-header">
        <div>
          <div class="kiosk-brand">Your Order</div>
          <div class="kiosk-subtitle">Adjust quantities or remove items</div>
        </div>
      </header>
      <div class="cart-body" id="cart-items"></div>
      <div class="cart-summary">
        <div class="cart-total"><span>Total</span><strong id="cart-total">$0.00</strong></div>
      </div>
      <footer class="kiosk-footer">
        <button type="button" class="btn btn-secondary" id="cart-add-more">Add More</button>
        <button type="button" class="btn btn-secondary" id="cart-start-over">Start Over</button>
        <button type="button" class="btn btn-primary" id="cart-checkout">Pay Now</button>
      </footer>
    </div>

    <div class="kiosk-screen" id="payment-screen">
      <header class="kiosk-header">
        <div>
          <div class="kiosk-brand">Payment</div>
          <div class="kiosk-subtitle">Choose how you'd like to pay</div>
        </div>
      </header>
      <div class="payment-options" id="payment-methods">
        <button type="button" class="payment-option active" data-method="card">Card</button>
        <button type="button" class="payment-option" data-method="cash">Cash</button>
      </div>
      <div class="payment-panel" id="card-panel">
        <p class="payment-hint">Tap card when ready. This kiosk accepts staff-supervised cash and card orders.</p>
      </div>
      <div class="payment-panel hidden" id="cash-panel">
        <p class="payment-hint">Enter employee PIN to confirm a cash order.</p>
        <div class="pin-display" id="pin-display">••••</div>
        <div class="pin-pad" id="pin-pad"></div>
        <button type="button" class="btn btn-primary disabled" id="cash-confirm" disabled>Confirm Cash</button>
        <button type="button" class="btn btn-secondary" id="pin-clear">Clear</button>
      </div>
      <div class="payment-summary" id="payment-summary"></div>
      <footer class="kiosk-footer">
        <button type="button" class="btn btn-secondary" id="payment-back">Back to Cart</button>
      </footer>
    </div>

    <div class="kiosk-screen" id="confirmation-screen">
      <header class="kiosk-header">
        <div>
          <div class="kiosk-brand">Order Confirmed</div>
          <div class="kiosk-subtitle" id="confirm-subtitle"></div>
        </div>
      </header>
      <div class="confirmation-body">
        <div class="confirm-number">Reference: <strong id="confirm-ref">—</strong></div>
        <div class="confirm-note hidden" id="confirm-note">Your concessions will be ready shortly — we'll call your number when it's ready.</div>
        <div class="confirm-items" id="confirm-items"></div>
        <div class="confirm-tickets" id="confirm-tickets"></div>
      </div>
      <footer class="kiosk-footer">
        <button type="button" class="btn btn-primary" id="done-button">Done</button>
      </footer>
    </div>
  </div>
  <script src="../assets/js/kiosk.js?v=<?= @filemtime(__DIR__ . '/../assets/js/kiosk.js') ?>"></script>
</body>
</html>
