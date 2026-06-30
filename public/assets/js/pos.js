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

  var BADGE = { ok: 'In Stock', low: 'Low Stock', out: 'Sold Out' };

  /* Left-rail category icons (Tabler-style). Unknown categories fall back to a
     generic tag glyph so a new concession category never renders icon-less. */
  var CAT_ICONS = {
    Tickets: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4z"/><path d="M13 6v12"/></svg>',
    Combos:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-4 9 4-9 4-9-4z"/><path d="M3 9v6l9 4 9-4V9"/></svg>',
    Popcorn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l1.5 12h9L18 9"/><path d="M5 9a2.5 2.5 0 1 1 1-4.8A2.5 2.5 0 0 1 11 4a2.5 2.5 0 0 1 4 .7A2.5 2.5 0 1 1 19 9"/></svg>',
    Drinks:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h12l-1.5 16h-9L6 4z"/><path d="M6.5 9h11"/></svg>',
    Candy:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M16 9l4-3v12l-4-3M8 15l-4 3V6l4 3"/></svg>'
  };
  var CAT_FALLBACK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h7l6 6-7 7-6-6V4z"/><circle cx="10.5" cy="7.5" r="1"/></svg>';
  function catIcon(name) { return CAT_ICONS[name] || CAT_FALLBACK; }

  /* Count of buyable items in a category — drives the rail badge. */
  function availCount(cat) {
    if (cat === 'Tickets') return BOOT.tickets.length;
    var n = 0;
    BOOT.products.forEach(function (p) { if (p.cat === cat && p.stock > 0) n++; });
    return n;
  }
  function setCatHeader() {
    var title = document.getElementById('catTitle');
    var sub = document.getElementById('catSub');
    if (title) title.textContent = curTab;
    if (sub) sub.textContent = curTab === 'Tickets'
      ? (BOOT.tickets.length + (BOOT.tickets.length === 1 ? ' showing' : ' showing'))
      : (availCount(curTab) + ' available');
  }

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

  /* ---------------- left-rail categories ---------------- */
  function buildCats() {
    var wrap = document.getElementById('cats');
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
      b.className = 'cat' + (t === curTab ? ' on' : '');
      b.setAttribute('data-tab', t);
      b.innerHTML = '<span class="ci">' + catIcon(t) + '</span>' +
        '<span class="cl">' + esc(t) + '</span>' +
        '<span class="cb">' + availCount(t) + '</span>';
      wrap.appendChild(b);
    });
    wrap.addEventListener('click', function (e) {
      var t = e.target.closest('.cat');
      if (!t) return;
      curTab = t.getAttribute('data-tab');
      wrap.querySelectorAll('.cat').forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
      setCatHeader();
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
        b.innerHTML =
          '<div class="thumb">' + ICON_TICK +
            (t.image ? '<img src="' + esc(t.image) + '" alt="" onerror="this.style.display=\'none\'">' : '') +
            '<span class="badge ticket">Ticket</span>' +
            '<div class="cardctl"><span class="step-add"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span></div>' +
          '</div>' +
          '<div class="body">' +
            '<div class="nm">' + esc(t.title) + '</div>' +
            (t.when ? '<div class="opt-flag">' + esc(t.when) + '</div>' : '') +
            '<div class="row2"><span class="pr tnum">' + money(t.adult) + ' / ' + money(t.child) + '</span><span class="stock">Adult / Kids</span></div>' +
          '</div>';
        b.addEventListener('click', function () { openTicket(t); });
        g.appendChild(b);
      });
      return;
    }

    BOOT.products.filter(function (p) { return p.cat === curTab; }).forEach(function (p) {
      g.appendChild(buildConcessionCard(p));
    });
    refreshSteppers();
  }

  /* Build one concession card: a tappable picture/name/price region plus a
     footer control. Plain items get an inline "− qty +" stepper; items with
     options get a "+ Add" that opens the option picker (their per-option lines
     are managed in the cart). The card is a <div> — the tap region and the
     stepper buttons are siblings, never nested buttons. */
  function buildConcessionCard(p) {
    var st = statusOf(p);
    var hasOpts = p.options && p.options.length;

    var card = document.createElement('div');
    card.className = 'card' + (st === 'out' ? ' out' : '');
    card.setAttribute('data-pid', p.id);
    card.setAttribute('data-stock', p.stock);

    // Tap region is a DIV (role=button) so the overlay control buttons can live
    // inside the thumb without nesting a <button> inside a <button>.
    var tap = document.createElement('div');
    tap.className = 'card-tap';
    if (st !== 'out') {
      tap.setAttribute('role', 'button');
      tap.setAttribute('tabindex', '0');
    }
    var stockTxt = st === 'out' ? 'Sold out' : (p.stock + ' left');
    var stockCls = st === 'low' ? ' low' : (st === 'out' ? ' out' : '');
    tap.innerHTML =
      '<div class="thumb">' + ICON_BOX +
        (p.image ? '<img src="' + esc(p.image) + '" alt="" onerror="this.style.display=\'none\'">' : '') +
        '<span class="badge ' + st + '">' + BADGE[st] + '</span>' +
        '<span class="qchip" style="display:none">0</span>' +
      '</div>' +
      '<div class="body">' +
        '<div class="nm">' + esc(p.name) + '</div>' +
        (hasOpts
          ? '<div class="opt-flag"><svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>choose option</div>'
          : '') +
        '<div class="row2"><span class="pr tnum">' + money(p.price) + '</span>' +
        '<span class="stock' + stockCls + '">' + stockTxt + '</span></div>' +
      '</div>';
    if (st !== 'out') {
      tap.addEventListener('click', function () { tapConcession(p); });
      tap.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); tapConcession(p); }
      });
    }
    card.appendChild(tap);

    // Control overlay on the thumb's bottom-right.
    if (st !== 'out') {
      var ctl = document.createElement('div');
      ctl.className = 'cardctl';
      if (hasOpts) {
        var addb = document.createElement('button');
        addb.type = 'button';
        addb.className = 'step-add';
        addb.innerHTML = '<svg class="ico ico-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add';
        addb.addEventListener('click', function (e) { e.stopPropagation(); openOptions(p); });
        ctl.appendChild(addb);
      } else {
        var stepper = document.createElement('div');
        stepper.className = 'stepper';
        var minus = document.createElement('button');
        minus.type = 'button';
        minus.className = 'step minus';
        minus.innerHTML = '<svg class="ico" viewBox="0 0 24 24"><path d="M5 12h14"/></svg>';
        minus.addEventListener('click', function (e) { e.stopPropagation(); decConcession(p.id); });
        var num = document.createElement('span');
        num.className = 'stepn tnum';
        num.textContent = '0';
        var plus = document.createElement('button');
        plus.type = 'button';
        plus.className = 'step plus';
        plus.innerHTML = '<svg class="ico" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>';
        plus.addEventListener('click', function (e) { e.stopPropagation(); addConcession(p, null, true); });
        stepper.appendChild(minus);
        stepper.appendChild(num);
        stepper.appendChild(plus);
        ctl.appendChild(stepper);
      }
      tap.querySelector('.thumb').appendChild(ctl);
    }
    return card;
  }

  /* Sync every visible card's stepper/chip to the cart (source of truth) and
     cap "+" at available stock. Targeted DOM updates only — never a full grid
     re-render — so the touchscreen's scroll position is preserved mid-order. */
  function refreshSteppers() {
    var cards = document.querySelectorAll('#pgrid .card[data-pid]');
    for (var i = 0; i < cards.length; i++) {
      var card = cards[i];
      var pid = parseInt(card.getAttribute('data-pid'), 10);
      var stock = parseInt(card.getAttribute('data-stock'), 10);
      var n = concQtyInCart(pid);
      var atMax = !isNaN(stock) && stock > 0 && n >= stock;

      card.classList.toggle('inCart', n > 0);
      var chip = card.querySelector('.qchip');
      if (chip) { chip.textContent = n; chip.style.display = n > 0 ? '' : 'none'; }
      var num = card.querySelector('.stepn');
      if (num) num.textContent = n;
      var minus = card.querySelector('.step.minus');
      if (minus) minus.disabled = n <= 0;
      var plus = card.querySelector('.step.plus');
      if (plus) plus.disabled = atMax;
      var addb = card.querySelector('.step-add');
      if (addb && !card.classList.contains('out')) addb.disabled = atMax;
    }
  }

  /* ---------------- add to cart ---------------- */
  function tapConcession(p) {
    if (p.options && p.options.length) { openOptions(p); return; }
    addConcession(p, null);
  }
  /* Total quantity of a concession in the cart, summed across all its options.
     Drives the on-card stepper number / chip and the stock cap. */
  function concQtyInCart(id) {
    var n = 0;
    for (var i = 0; i < cart.length; i++) {
      if (cart[i].kind === 'concession' && cart[i].id === id) n += cart[i].qty;
    }
    return n;
  }
  function addConcession(p, option, silent) {
    if (p.stock > 0 && concQtyInCart(p.id) >= p.stock) {
      toast('Only ' + p.stock + ' in stock', true);
      return;
    }
    var key = 'c:' + p.id + '|' + (option || '');
    var line = findKey(key);
    if (line) line.qty++;
    else cart.push({ key: key, kind: 'concession', id: p.id, name: p.name, option: option, price: p.price, qty: 1 });
    renderCart();
    if (!silent) toast((option ? p.name + ' (' + option + ')' : p.name) + ' added');
  }
  /* Remove one unit of a no-option concession straight from its card. (Option
     items hold separate lines per option, so their card has no minus — removal
     happens in the cart where the specific option is visible.) */
  function decConcession(id) {
    var line = findKey('c:' + id + '|');
    if (!line) return;
    line.qty--;
    if (line.qty <= 0) removeLine(line);
    renderCart();
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
    document.getElementById('optPoster').classList.remove('show');
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
    var poster = document.getElementById('optPoster');
    if (t.image) { poster.style.backgroundImage = "url('" + t.image + "')"; poster.classList.add('show'); }
    else { poster.classList.remove('show'); }
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
  /* Look up a line's image from BOOT for the cart thumbnail (render-time only —
     the cart line shape sent to checkout is unchanged). */
  function lineImage(l) {
    var i;
    if (l.kind === 'ticket') {
      for (i = 0; i < BOOT.tickets.length; i++) {
        if (BOOT.tickets[i].showtime_id === l.showtimeId) return BOOT.tickets[i].image || '';
      }
      return '';
    }
    for (i = 0; i < BOOT.products.length; i++) {
      if (BOOT.products[i].id === l.id) return BOOT.products[i].image || '';
    }
    return '';
  }

  function renderCart() {
    var box = document.getElementById('clines');
    var n = cartCount();
    document.getElementById('ccount').textContent = n + (n === 1 ? ' item' : ' items');
    document.getElementById('subtot').textContent = money(cartTotal());
    document.getElementById('goPay').disabled = n === 0;
    refreshSteppers();

    if (!cart.length) {
      box.innerHTML = '<div class="cempty"><svg class="ico" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h2l2.5 13h11l2-9H6"/></svg><div style="font-weight:800;color:var(--c-text-2)">No items yet</div></div>';
      return;
    }
    box.innerHTML = '';
    cart.forEach(function (l) {
      var sub = lineSub(l);
      var img = lineImage(l);
      var row = document.createElement('div');
      row.className = 'cline';
      row.innerHTML =
        '<span class="cth"' + (img ? ' style="background-image:url(\'' + img + '\')"' : '') + '></span>' +
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

  /* ---------------- live stock polling ----------------
     Refreshes stock/reorder from the server every 60s so an item sold out on
     another terminal greys out here without a page reload. Updates BOOT.products
     in place and swaps only the cards that actually changed — cards not on the
     currently visible tab are skipped (no node to update), and the rail badge
     counts are patched without re-attaching the category click listener. */
  function refreshCatBadges() {
    var wrap = document.getElementById('cats');
    if (!wrap) return;
    var btns = wrap.querySelectorAll('.cat');
    for (var i = 0; i < btns.length; i++) {
      var cb = btns[i].querySelector('.cb');
      if (cb) cb.textContent = availCount(btns[i].getAttribute('data-tab'));
    }
    setCatHeader();
  }
  function pollStock() {
    fetch('../api/pos-stock.php', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.ok) return;
        var anyChanged = false;
        BOOT.products.forEach(function (p) {
          var fresh = data.concessions[p.id];
          if (!fresh || (fresh.stock === p.stock && fresh.reorder === p.reorder)) return;
          p.stock = fresh.stock;
          p.reorder = fresh.reorder;
          anyChanged = true;
          var old = document.querySelector('#pgrid .card[data-pid="' + p.id + '"]');
          if (old) old.replaceWith(buildConcessionCard(p));
        });
        if (anyChanged) {
          refreshSteppers();
          refreshCatBadges();
        }
      })
      .catch(function () { /* next tick retries */ });
  }
  setInterval(pollStock, 60000);

  /* ---------------- init ---------------- */
  buildCats();
  setCatHeader();
  renderGrid();
  renderCart();
})();
