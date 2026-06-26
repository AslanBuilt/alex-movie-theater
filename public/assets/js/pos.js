/* ============================================================
   THE ALEX — EMPLOYEE POS (client)
   Catalog render, cart, overlays, cash keypad, checkout.
   Cart is held client-side; the server re-prices and re-verifies
   stock atomically at checkout (pos-checkout.php).
   ============================================================ */
(function () {
  'use strict';

  var BOOT = window.POS_BOOT || { csrf: '', products: [], tickets: [], hasTickets: false };
  var CSRF = BOOT.csrf || '';

  var ICON_BOX  = '<svg class="ico" viewBox="0 0 24 24"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg>';
  var ICON_TICK = '<svg class="ico" viewBox="0 0 24 24"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4z"/><path d="M13 7v10"/></svg>';

  var BADGE = { ok: 'In Stock', low: 'Low Stock', out: 'Out of Stock' };

  /* cart line shape:
     concession: {key, kind:'concession', id, name, option, price, qty}
     ticket:     {key, kind:'ticket', showtimeId, age:'Adult'|'Child', title, when, price, qty} */
  var cart = [];
  var curTab = BOOT.hasTickets ? 'Tickets' : (firstCategory() || 'Tickets');

  function firstCategory() {
    for (var i = 0; i < BOOT.products.length; i++) { return BOOT.products[i].cat; }
    return null;
  }
  function money(n) { return '$' + (Math.round(n * 100) / 100).toFixed(2); }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function statusOf(p) {
    if (p.stock <= 0) return 'out';
    if (p.reorder != null && p.stock <= p.reorder) return 'low';
    return 'ok';
  }
  /* Thumb with graceful fallback: a fallback icon sits behind the image; if the
     image 404s the onerror handler hides it, revealing the icon. */
  function makeThumb(image, iconSvg) {
    if (!image) return '<div class="thumb">' + iconSvg + '</div>';
    return '<div class="thumb">' + iconSvg +
      '<img src="' + esc(image) + '" alt="" style="position:absolute;inset:0" ' +
      'onerror="this.style.display=\'none\'"></div>';
  }

  /* ---------------- tabs ---------------- */
  function buildTabs() {
    var wrap = document.getElementById('tabs');
    var tabs = [];
    if (BOOT.hasTickets) tabs.push('Tickets');
    var seen = {};
    BOOT.products.forEach(function (p) {
      if (!seen[p.cat]) { seen[p.cat] = true; tabs.push(p.cat); }
    });
    wrap.innerHTML = '';
    tabs.forEach(function (t) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'tab' + (t === curTab ? ' on' : '');
      b.setAttribute('data-tab', t);
      b.textContent = t;
      wrap.appendChild(b);
    });
    wrap.addEventListener('click', function (e) {
      var t = e.target.closest('.tab');
      if (!t) return;
      curTab = t.getAttribute('data-tab');
      wrap.querySelectorAll('.tab').forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
      renderGrid();
    });
  }

  /* ---------------- grid ---------------- */
  function renderGrid() {
    var g = document.getElementById('pgrid');
    g.innerHTML = '';

    if (curTab === 'Tickets') {
      BOOT.tickets.forEach(function (t) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'card';
        var thumb = makeThumb(t.image, ICON_TICK);
        b.innerHTML = thumb +
          '<div class="body">' +
          '<span class="badge ticket">Ticket</span>' +
          '<div class="nm">' + esc(t.title) + '</div>' +
          (t.when ? '<div class="opt-flag">' + esc(t.when) + '</div>' : '') +
          '<div class="pr tnum">' + money(t.adult) + ' / ' + money(t.child) + '</div>' +
          '</div>';
        b.addEventListener('click', function () { openTicket(t); });
        g.appendChild(b);
      });
      return;
    }

    BOOT.products.filter(function (p) { return p.cat === curTab; }).forEach(function (p) {
      var st = statusOf(p);
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'card' + (st === 'out' ? ' out' : '');
      var thumb = makeThumb(p.image, ICON_BOX);
      b.innerHTML = thumb +
        '<div class="body">' +
        '<span class="badge ' + st + '">' + BADGE[st] + '</span>' +
        '<div class="nm">' + esc(p.name) + '</div>' +
        (p.options && p.options.length
          ? '<div class="opt-flag"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>choose option</div>'
          : '') +
        '<div class="pr tnum">' + money(p.price) + '</div>' +
        '</div>';
      if (st !== 'out') {
        b.addEventListener('click', function () { tapConcession(p); });
      }
      g.appendChild(b);
    });
  }

  /* ---------------- add to cart ---------------- */
  function tapConcession(p) {
    if (p.options && p.options.length) { openOptions(p); return; }
    addConcession(p, null);
  }
  function addConcession(p, option) {
    var key = 'c:' + p.id + '|' + (option || '');
    var line = findKey(key);
    if (line) line.qty++;
    else cart.push({ key: key, kind: 'concession', id: p.id, name: p.name, option: option, price: p.price, qty: 1 });
    renderCart();
    toast((option ? p.name + ' (' + option + ')' : p.name) + ' added');
  }
  function addTicket(t, age) {
    var price = age === 'Child' ? t.child : t.adult;
    var key = 't:' + t.showtime_id + '|' + age;
    var line = findKey(key);
    if (line) line.qty++;
    else cart.push({
      key: key, kind: 'ticket', showtimeId: t.showtime_id, age: age,
      title: t.title, when: t.when, price: price, qty: 1
    });
    renderCart();
    toast(age + ' ticket — ' + t.title + ' added');
  }
  function findKey(key) {
    for (var i = 0; i < cart.length; i++) { if (cart[i].key === key) return cart[i]; }
    return null;
  }

  /* ---------------- overlay (concession options + ticket age) ---------------- */
  var overlay = document.getElementById('overlay');
  function openOptions(p) {
    document.getElementById('optTitle').textContent = p.name;
    document.getElementById('optSub').textContent = 'Pick one — it adds to the order instantly.';
    var og = document.getElementById('optGrid');
    og.innerHTML = '';
    p.options.forEach(function (o) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'optbtn';
      b.textContent = o;
      b.addEventListener('click', function () { addConcession(p, o); closeOverlay(); });
      og.appendChild(b);
    });
    overlay.classList.add('show');
  }
  function openTicket(t) {
    document.getElementById('optTitle').textContent = t.title;
    document.getElementById('optSub').textContent = (t.when ? t.when + ' · ' : '') + 'Choose ticket type.';
    var og = document.getElementById('optGrid');
    og.innerHTML = '';
    [['Adult', t.adult], ['Child', t.child]].forEach(function (pair) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'optbtn';
      b.innerHTML = '<span>' + pair[0] + '</span><span class="op-pr tnum">' + money(pair[1]) + '</span>';
      b.addEventListener('click', function () { addTicket(t, pair[0]); closeOverlay(); });
      og.appendChild(b);
    });
    overlay.classList.add('show');
  }
  function closeOverlay() { overlay.classList.remove('show'); }
  document.getElementById('optCancel').addEventListener('click', closeOverlay);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeOverlay(); });

  /* ---------------- cart render ---------------- */
  function cartCount() { return cart.reduce(function (a, l) { return a + l.qty; }, 0); }
  function cartTotal() { return cart.reduce(function (a, l) { return a + l.qty * l.price; }, 0); }

  function lineLabel(l) {
    if (l.kind === 'ticket') return 'Ticket: ' + l.title + ' (' + l.age + ')';
    return l.name;
  }
  function lineSub(l) {
    if (l.kind === 'ticket') return l.when || '';
    return l.option || '';
  }

  function renderCart() {
    var box = document.getElementById('clines');
    var n = cartCount();
    document.getElementById('ccount').textContent = n + (n === 1 ? ' item' : ' items');
    document.getElementById('subtot').textContent = money(cartTotal());
    document.getElementById('goPay').disabled = n === 0;

    if (!cart.length) {
      box.innerHTML = '<div class="cempty"><svg class="ico" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h2l2.5 13h11l2-9H6"/></svg><div><div style="font-weight:800;color:var(--c-text-2)">No items yet</div>Tap a product to add it</div></div>';
      return;
    }
    box.innerHTML = '';
    cart.forEach(function (l) {
      var sub = lineSub(l);
      var row = document.createElement('div');
      row.className = 'cline';
      row.innerHTML =
        '<div><div class="nm">' + esc(lineLabel(l)) + '</div>' +
        (sub ? '<div class="op">' + esc(sub) + '</div>' : '') +
        '<div class="qty">' +
        '<button type="button" class="qbtn" data-a="dec"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M5 12h14"/></svg></button>' +
        '<span class="qn tnum">' + l.qty + '</span>' +
        '<button type="button" class="qbtn" data-a="inc"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>' +
        '</div></div>' +
        '<div style="display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between">' +
        '<span class="lp tnum">' + money(l.qty * l.price) + '</span>' +
        '<button type="button" class="rm" data-a="rm"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m2 0v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7"/></svg></button>' +
        '</div>';
      row.querySelectorAll('[data-a]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var a = btn.getAttribute('data-a');
          if (a === 'inc') l.qty++;
          else if (a === 'dec') { l.qty--; if (l.qty <= 0) removeLine(l); }
          else if (a === 'rm') removeLine(l);
          renderCart();
        });
      });
      box.appendChild(row);
    });
  }
  function removeLine(l) {
    cart = cart.filter(function (x) { return x.key !== l.key; });
  }

  document.getElementById('clearBtn').addEventListener('click', function () {
    if (!cart.length) return;
    if (window.confirm('Clear the whole order?')) { cart = []; renderCart(); toast('Cart cleared'); }
  });

  /* ---------------- payment summary ---------------- */
  function fillPay() {
    var tot = cartTotal();
    document.getElementById('payTotal').textContent = money(tot);
    document.getElementById('cashDue').textContent = money(tot);
    document.getElementById('cardTotal').textContent = money(tot);
    cashTot = tot;
    var s = document.getElementById('paySummary');
    s.innerHTML = '';
    cart.forEach(function (l) {
      var sub = lineSub(l);
      var r = document.createElement('div');
      r.className = 'row';
      r.innerHTML = '<div><div class="nm">' + esc(lineLabel(l)) + (l.qty > 1 ? ' &times;' + l.qty : '') + '</div>' +
        (sub ? '<div class="op">' + esc(sub) + '</div>' : '') + '</div>' +
        '<div class="lp tnum">' + money(l.qty * l.price) + '</div>';
      s.appendChild(r);
    });
  }

  /* ---------------- cash keypad ---------------- */
  var cashStr = '', cashTot = 0;
  function cashValue() { var v = parseFloat(cashStr || '0'); return isNaN(v) ? 0 : v; }
  function renderCash() {
    var v = cashValue();
    document.getElementById('cashEntry').textContent = money(v);
    var ce = document.getElementById('cashChange');
    var ok = v >= cashTot && cashTot > 0;
    if (v > cashTot && cashTot > 0) {
      ce.style.display = 'inline-block';
      document.getElementById('changeAmt').textContent = money(v - cashTot);
    } else {
      ce.style.display = 'none';
    }
    document.getElementById('confirmCash').disabled = !ok;
  }
  document.getElementById('cashpad').addEventListener('click', function (e) {
    var k = e.target.closest('.key'); if (!k) return;
    var t = k.textContent;
    if (t === '⌫') cashStr = cashStr.slice(0, -1);
    else if (t === '.') { if (cashStr.indexOf('.') < 0) cashStr += (cashStr === '' ? '0.' : '.'); }
    else {
      // limit to 2 decimal places
      var dot = cashStr.indexOf('.');
      if (dot >= 0 && cashStr.length - dot > 2) return;
      cashStr += t;
    }
    renderCash();
  });
  document.getElementById('quickcash').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var a = b.getAttribute('data-amt');
    cashStr = (a === 'exact' ? cashTot : parseFloat(a)).toFixed(2);
    renderCash();
  });

  /* ---------------- checkout ---------------- */
  var submitting = false;
  function buildPayload(method, cashReceived) {
    return {
      method: method,
      cash_received: cashReceived,
      items: cart.map(function (l) {
        if (l.kind === 'ticket') {
          return { kind: 'ticket', showtime_id: l.showtimeId, age: l.age, qty: l.qty };
        }
        return { kind: 'concession', id: l.id, option: l.option || null, qty: l.qty };
      })
    };
  }

  function doCheckout(method, cashReceived, onError) {
    if (submitting || !cart.length) return;
    submitting = true;

    fetch('../api/pos-checkout.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(buildPayload(method, cashReceived))
    })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (res) {
        submitting = false;
        if (res.body && res.body.ok) {
          completeOrder(res.body, method, cashReceived);
        } else {
          var msg = (res.body && res.body.error) ? res.body.error : 'Checkout failed. Please try again.';
          toast(msg, true);
          if (onError) onError(msg);
        }
      })
      .catch(function () {
        submitting = false;
        var msg = 'Network error. Please try again.';
        toast(msg, true);
        if (onError) onError(msg);
      });
  }

  function completeOrder(body, method, cashReceived) {
    document.getElementById('doneRef').textContent = body.transaction_ref || '—';
    document.getElementById('donePaid').textContent = money(body.total != null ? body.total : cartTotal());
    var chg = '';
    if (method === 'cash' && cashReceived != null) {
      var change = cashReceived - (body.total != null ? body.total : cartTotal());
      if (change > 0) chg = 'Change due: ' + money(change);
    }
    document.getElementById('doneChange').textContent = chg;
    cart = [];
    renderCart();
    go('done');
  }

  document.getElementById('confirmCash').addEventListener('click', function () {
    var v = cashValue();
    if (v < cashTot) { toast('Cash received is less than total due.', true); return; }
    this.disabled = true;
    var self = this;
    doCheckout('cash', v, function () { self.disabled = false; });
  });
  document.getElementById('confirmCard').addEventListener('click', function () {
    this.disabled = true;
    var self = this;
    doCheckout('card', null, function () { self.disabled = false; });
  });

  /* ---------------- screen routing ---------------- */
  function go(id) {
    document.querySelectorAll('.screen').forEach(function (s) { s.classList.toggle('show', s.id === id); });
    if (id === 'pay' || id === 'cash' || id === 'card') fillPay();
    if (id === 'cash') { cashStr = ''; renderCash(); }
    if (id === 'card') { document.getElementById('confirmCard').disabled = false; }
    window.scrollTo(0, 0);
  }
  document.getElementById('goPay').addEventListener('click', function () { if (cart.length) go('pay'); });
  document.getElementById('newOrderBtn').addEventListener('click', function () { go('order'); });
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-go]');
    if (!t) return;
    go(t.getAttribute('data-go'));
  });

  /* ---------------- toast ---------------- */
  var toastT;
  function toast(msg, err) {
    var t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.toggle('err', !!err);
    t.classList.add('show');
    clearTimeout(toastT);
    toastT = setTimeout(function () { t.classList.remove('show'); }, 2400);
  }

  /* ---------------- init ---------------- */
  buildTabs();
  renderGrid();
  renderCart();
})();
