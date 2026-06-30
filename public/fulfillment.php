<?php
declare(strict_types=1);

/**
 * Order fulfillment board (/fulfillment.php) — the wall/iPad screen showing
 * all open orders, FIFO. No login: same model as /checkin (unauthenticated
 * but unlinked, noindex). See api/fulfillment.php for the data + mutation.
 */

require_once __DIR__ . '/config/config.php';

header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="robots" content="noindex,nofollow">
<title>Fulfillment — <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/css/fulfillment.css?v=<?= @filemtime(__DIR__ . '/assets/css/fulfillment.css') ?>">
</head>
<body>
<div class="board">
  <div class="board-head">
    <h1>Open Orders</h1>
    <span class="ts tnum" id="lastUpdated">Loading…</span>
  </div>
  <div id="empty" class="empty-state" hidden>All caught up — no pending orders</div>
  <div class="order-grid" id="orderGrid"></div>
</div>

<script>
(function () {
  'use strict';
  var grid = document.getElementById('orderGrid');
  var empty = document.getElementById('empty');
  var lastUpdated = document.getElementById('lastUpdated');
  var known = {}; // id -> true, tracks which cards already exist (skip re-animate-in)

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function timeAgo(iso) {
    var t = new Date(iso.replace(' ', 'T'));
    var diffSec = Math.max(0, Math.round((Date.now() - t.getTime()) / 1000));
    if (diffSec < 60) return diffSec + 's ago';
    return Math.round(diffSec / 60) + 'm ago';
  }
  function buildCard(order) {
    var card = document.createElement('div');
    card.className = 'order-card';
    card.setAttribute('data-id', order.id);
    var itemsHtml = order.items.map(function (it) {
      return '<li><span class="qty tnum">' + it.qty + '&times;</span><span>' + esc(it.name) +
        (it.option ? ' <span class="opt">(' + esc(it.option) + ')</span>' : '') + '</span></li>';
    }).join('');
    card.innerHTML =
      '<div class="order-card-top">' +
        '<div><div class="order-id tnum">#' + esc(order.ref) + '</div>' +
        '<span class="channel-badge ' + esc(order.channelClass) + '">' + esc(order.channelLabel) + '</span></div>' +
        '<div class="order-time tnum" data-created="' + esc(order.created_at) + '">' + timeAgo(order.created_at) + '</div>' +
      '</div>' +
      '<ul class="order-items">' + itemsHtml + '</ul>' +
      '<button type="button" class="complete-btn">Order Ready — Mark Complete</button>';
    card.querySelector('.complete-btn').addEventListener('click', function (e) {
      var btn = e.currentTarget;
      btn.disabled = true;
      btn.textContent = 'Completing…';
      fetch('api/fulfillment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: order.id })
      }).then(function (r) { return r.json(); }).then(function (res) {
        if (res && res.ok) {
          card.classList.add('leaving');
          delete known[order.id];
          setTimeout(function () { card.remove(); checkEmpty(); }, 350);
        } else {
          btn.disabled = false;
          btn.textContent = 'Order Ready — Mark Complete';
        }
      }).catch(function () {
        btn.disabled = false;
        btn.textContent = 'Order Ready — Mark Complete';
      });
    });
    return card;
  }
  function checkEmpty() {
    empty.hidden = grid.children.length > 0;
  }
  function render(orders) {
    var seenIds = {};
    orders.forEach(function (order) { seenIds[order.id] = true; });

    // Remove cards for orders no longer pending (completed elsewhere).
    Array.prototype.slice.call(grid.children).forEach(function (card) {
      var id = parseInt(card.getAttribute('data-id'), 10);
      if (!seenIds[id]) { card.remove(); delete known[id]; }
    });

    // Add cards for orders we haven't rendered yet (oldest-first order from the API).
    orders.forEach(function (order) {
      if (known[order.id]) return;
      known[order.id] = true;
      grid.appendChild(buildCard(order));
    });

    checkEmpty();
  }
  function refreshTimestamps() {
    grid.querySelectorAll('[data-created]').forEach(function (el) {
      el.textContent = timeAgo(el.getAttribute('data-created'));
    });
  }
  function poll() {
    fetch('api/fulfillment.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok) {
          render(data.orders);
          lastUpdated.textContent = 'Updated ' + new Date().toLocaleTimeString();
        }
      })
      .catch(function () { /* keep last known state; next tick retries */ });
  }
  poll();
  setInterval(poll, 10000);
  setInterval(refreshTimestamps, 15000);
})();
</script>
</body>
</html>
