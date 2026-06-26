<?php
declare(strict_types=1);

/**
 * Employee POS — PIN login (/pos/login.php).
 *
 * Runs on the ALEX_POS_SESS session. An already-logged-in admin is sent
 * straight through to the order screen (admins may use the POS). Otherwise a
 * numeric PIN is required. Login is rate-limited and CSRF-protected; the
 * per-account 5-attempt / 15-minute lockout lives in PosAuth.
 */

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/PosAuth.php';
require_once INCLUDES_PATH . '/RateLimiter.php';

// Open POS session (and detect an active admin) before any output.
PosAuth::bootstrap();

try {
    $db = Database::getInstance();
} catch (RuntimeException $e) {
    http_response_code(503);
    ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>POS Unavailable</title>
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f5}
.box{background:#fff;padding:2rem 2.5rem;border-radius:6px;max-width:400px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1)}
h1{font-size:1.25rem;margin-bottom:.75rem}p{color:#555;font-size:.9rem}</style>
</head><body><div class="box">
<h1>POS unavailable</h1>
<p>The database has not been configured on this server yet.</p>
</div></body></html><?php
    exit;
}

$auth = new PosAuth($db);

// Already authenticated (employee PIN earlier, or an admin) → go to order screen.
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Throttle login attempts by IP (defence in depth on top of the per-account lockout).
    if (!RateLimiter::allow('pos-login:' . RateLimiter::clientIp(), 10, 300)) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } elseif (!$auth->validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Please try again.';
    } else {
        $result = $auth->login((string)($_POST['pin'] ?? ''));
        if ($result['ok']) {
            header('Location: index.php');
            exit;
        }
        $error = (string)$result['error'];
    }
}

$csrf = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex,nofollow">
    <title>POS Sign in — <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/pos.css?v=<?= @filemtime(__DIR__ . '/../assets/css/pos.css') ?>">
</head>
<body>
<div class="stage">
  <section class="screen show">
    <div class="center-wrap">
      <div class="pin-card">
        <div class="logo">A</div>
        <h3 style="font-size:18px">The Alex · Register</h3>
        <p style="color:var(--c-text-muted);font-size:14px;margin-top:4px">Enter your PIN to start</p>

        <form method="post" id="pinForm" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="pin" id="pinField" value="">

          <div class="pin-dots" id="pinDots">
            <i></i><i></i><i></i><i></i>
          </div>

          <?php if ($error !== '') : ?>
            <div class="pin-error" role="alert"><?= e($error) ?></div>
          <?php endif; ?>

          <div class="keypad pin" id="pinpad" style="margin-top:18px">
            <button type="button" class="key" data-k="1">1</button>
            <button type="button" class="key" data-k="2">2</button>
            <button type="button" class="key" data-k="3">3</button>
            <button type="button" class="key" data-k="4">4</button>
            <button type="button" class="key" data-k="5">5</button>
            <button type="button" class="key" data-k="6">6</button>
            <button type="button" class="key" data-k="7">7</button>
            <button type="button" class="key" data-k="8">8</button>
            <button type="button" class="key" data-k="9">9</button>
            <button type="button" class="key" data-k="clr" aria-label="clear">C</button>
            <button type="button" class="key" data-k="0">0</button>
            <button type="button" class="key" data-k="del" aria-label="delete">&#9003;</button>
          </div>

          <button type="submit" class="btn-checkout" style="margin-top:18px;width:100%">Sign in</button>
        </form>
      </div>
    </div>
  </section>
</div>

<script>
(function () {
  var MAX = 6;          // allow 4-6 digit PINs
  var pin = '';
  var field = document.getElementById('pinField');
  var dots  = document.getElementById('pinDots');
  var form  = document.getElementById('pinForm');

  function render() {
    field.value = pin;
    var html = '';
    var shown = Math.max(4, pin.length);
    for (var i = 0; i < shown; i++) {
      html += '<i class="' + (i < pin.length ? 'f' : '') + '"></i>';
    }
    dots.innerHTML = html;
  }

  document.getElementById('pinpad').addEventListener('click', function (e) {
    var btn = e.target.closest('.key');
    if (!btn) return;
    var k = btn.getAttribute('data-k');
    if (k === 'del') pin = pin.slice(0, -1);
    else if (k === 'clr') pin = '';
    else if (pin.length < MAX) pin += k;
    render();
  });

  form.addEventListener('submit', function (e) {
    if (pin.length < 4) { e.preventDefault(); }
  });

  render();
})();
</script>
</body>
</html>
