<?php
declare(strict_types=1);

/**
 * Ticket check-in kiosk (/checkin.php) — the screen at the theater entrance.
 * No login (unauthenticated but unlinked, noindex). See api/checkin.php for
 * the atomic claim logic.
 */

require_once __DIR__ . '/config/config.php';

header('X-Robots-Tag: noindex, nofollow');
$gaId = defined('GA_MEASUREMENT_ID') ? GA_MEASUREMENT_ID : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="robots" content="noindex,nofollow">
<title>Check In — <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/css/checkin.css?v=<?= @filemtime(__DIR__ . '/assets/css/checkin.css') ?>">
<?php if ($gaId !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($gaId) ?>');</script>
<?php endif; ?>
</head>
<body>
<div class="kiosk">

  <div class="state show" id="stateReady">
    <div class="cam-wrap">
      <video id="camVideo" playsinline muted></video>
      <div class="cam-fallback-icon" id="camFallback">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M4 7V4a1 1 0 0 1 1-1h3M20 7V4a1 1 0 0 0-1-1h-3M4 17v3a1 1 0 0 0 1 1h3M20 17v3a1 1 0 0 1-1 1h-3M7 12h10" stroke-linecap="round"/></svg>
      </div>
    </div>
    <h1>Scan your ticket</h1>
    <p id="readyHint">Hold your QR code inside the frame</p>
    <div class="manual-row">
      <input type="text" id="manualToken" placeholder="Or type your ticket code" autocomplete="off">
      <button type="button" id="manualGo">Go</button>
    </div>
  </div>

  <div class="state success" id="stateSuccess">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.4"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <h1 id="successTitle">Welcome!</h1>
    <p class="sub" id="successSub"></p>
  </div>

  <div class="state fail" id="stateUsed">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.4"><circle cx="12" cy="12" r="10"/><path d="M9 9l6 6M15 9l-6 6" stroke-linecap="round"/></svg>
    <h1>Ticket already scanned</h1>
    <p class="sub" id="usedSub"></p>
  </div>

  <div class="state fail" id="stateInvalid">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.4"><circle cx="12" cy="12" r="10"/><path d="M9 9l6 6M15 9l-6 6" stroke-linecap="round"/></svg>
    <h1>Invalid ticket</h1>
    <p class="sub">Please see staff at the door</p>
  </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner.umd.min.js" integrity="sha512-a/IwksuXdv0Q60tVkQpwMk5qY+6cJ0FJgi33lrrIddoFItTRiRfSdU1qogP3uYjgHfrGY7+AC+4LU4J+b9HcgQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
  'use strict';

  var states = {
    ready:   document.getElementById('stateReady'),
    success: document.getElementById('stateSuccess'),
    used:    document.getElementById('stateUsed'),
    invalid: document.getElementById('stateInvalid')
  };
  var resetTimer = null;

  function showState(name) {
    Object.keys(states).forEach(function (k) { states[k].classList.toggle('show', k === name); });
  }
  function track(eventName, label) {
    if (typeof gtag === 'function') gtag('event', eventName, { event_label: label || '' });
  }

  // Terminal label: settable once per device via ?terminal=Door-1, persisted locally.
  var params = new URLSearchParams(location.search);
  if (params.get('terminal')) {
    try { localStorage.setItem('checkinTerminal', params.get('terminal')); } catch (e) {}
  }
  var terminal = (function () { try { return localStorage.getItem('checkinTerminal') || 'Front Door'; } catch (e) { return 'Front Door'; } })();

  var busy = false;
  function submitToken(token) {
    token = (token || '').trim();
    if (!token || busy) return;
    busy = true;
    fetch('api/checkin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: token, terminal: terminal })
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.result === 'success') {
        document.getElementById('successTitle').textContent = 'Welcome! Enjoy ' + res.movieTitle;
        document.getElementById('successSub').textContent = res.when || '';
        showState('success');
        track('checkin_success', res.movieTitle);
      } else if (res.result === 'used') {
        document.getElementById('usedSub').textContent = res.checkedInAt ? ('First used today at ' + res.checkedInAt) : '';
        showState('used');
        track('checkin_failed', 'already_used');
      } else {
        showState('invalid');
        track('checkin_failed', 'invalid');
      }
      scheduleReset();
    }).catch(function () {
      showState('invalid');
      track('checkin_failed', 'network_error');
      scheduleReset();
    });
  }
  function scheduleReset() {
    clearTimeout(resetTimer);
    resetTimer = setTimeout(function () {
      busy = false;
      document.getElementById('manualToken').value = '';
      showState('ready');
    }, 3000);
  }

  // Manual fallback.
  document.getElementById('manualGo').addEventListener('click', function () {
    submitToken(document.getElementById('manualToken').value);
  });
  document.getElementById('manualToken').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') submitToken(this.value);
  });

  // Camera scanning.
  if (typeof QrScanner !== 'undefined') {
    QrScanner.WORKER_PATH = 'https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner-worker.min.js';
    var video = document.getElementById('camVideo');
    var scanner = new QrScanner(video, function (result) {
      submitToken(typeof result === 'string' ? result : result.data);
    }, { highlightScanRegion: false, highlightCodeOutline: false, maxScansPerSecond: 5 });
    scanner.start().catch(function () {
      document.querySelector('.cam-wrap').classList.add('no-camera');
      document.getElementById('readyHint').textContent = 'Camera unavailable — type your ticket code below';
    });
  } else {
    document.querySelector('.cam-wrap').classList.add('no-camera');
    document.getElementById('readyHint').textContent = 'Type your ticket code below';
  }
})();
</script>
</body>
</html>
