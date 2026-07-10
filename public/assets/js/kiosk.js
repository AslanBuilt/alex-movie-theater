(function () {
  'use strict';

  var BOOT = window.KIOSK_BOOT || { concessions: [], showtimes: [] };
  var cart = [];
  var activeTab = BOOT.showtimes.length ? 'Tickets' : (BOOT.concessions[0] ? BOOT.concessions[0].name : 'Tickets');
  var method = 'card';
  var pin = '';
  var idleTimer = null;
  var confirmationTimer = null;

  var screens = {
    welcome: document.getElementById('welcome-screen'),
    menu: document.getElementById('menu-screen'),
    cart: document.getElementById('cart-screen'),
    payment: document.getElementById('payment-screen'),
    confirmation: document.getElementById('confirmation-screen'),
  };

  var els = {
    tabs: document.getElementById('category-tabs'),
    grid: document.getElementById('menu-grid'),
    summary: document.getElementById('menu-summary'),
    cartItems: document.getElementById('cart-items'),
    cartTotal: document.getElementById('cart-total'),
    paymentMethods: document.getElementById('payment-methods'),
    cardPanel: document.getElementById('card-panel'),
    cashPanel: document.getElementById('cash-panel'),
    pinDisplay: document.getElementById('pin-display'),
    pinPad: document.getElementById('pin-pad'),
    cashConfirm: document.getElementById('cash-confirm'),
    paymentSummary: document.getElementById('payment-summary'),
    confirmSubtitle: document.getElementById('confirm-subtitle'),
    confirmRef: document.getElementById('confirm-ref'),
    confirmNote: document.getElementById('confirm-note'),
    confirmItems: document.getElementById('confirm-items'),
    confirmTickets: document.getElementById('confirm-tickets'),
  };

  function formatMoney(amount) {
    return '$' + amount.toFixed(2);
  }

  function clamp(value, min, max) {
    return value < min ? min : value > max ? max : value;
  }

  function go(screenId) {
    Object.keys(screens).forEach(function (key) {
      screens[key].classList.toggle('show', key === screenId);
    });
    if (screenId === 'menu') {
      renderTabs();
      renderGrid();
      renderMenuSummary();
    }
    if (screenId === 'cart') {
      renderCart();
    }
    if (screenId === 'payment') {
      renderPayment();
    }
    if (screenId === 'confirmation') {
      startConfirmationTimer();
    } else {
      clearConfirmationTimer();
    }
    resetIdleTimer();
  }

  function resetIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(function () {
      go('welcome');
      cart = [];
      pin = '';
      renderCart();
    }, 60000);
  }

  function startConfirmationTimer() {
    clearConfirmationTimer();
    confirmationTimer = setTimeout(function () {
      go('welcome');
      cart = [];
      pin = '';
      renderCart();
    }, 15000);
  }

  function clearConfirmationTimer() {
    if (confirmationTimer) {
      clearTimeout(confirmationTimer);
      confirmationTimer = null;
    }
  }

  function renderTabs() {
    els.tabs.innerHTML = '';
    var tabs = [];
    if (BOOT.showtimes.length) tabs.push('Tickets');
    BOOT.concessions.forEach(function (category) {
      if (!tabs.includes(category.name)) {
        tabs.push(category.name);
      }
    });

    tabs.forEach(function (tab) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'tab' + (tab === activeTab ? ' active' : '');
      button.textContent = tab;
      button.addEventListener('click', function () {
        activeTab = tab;
        renderTabs();
        renderGrid();
      });
      els.tabs.appendChild(button);
    });
  }

  function renderGrid() {
    els.grid.innerHTML = '';

    if (activeTab === 'Tickets') {
      if (!BOOT.showtimes.length) {
        els.grid.innerHTML = '<div class="empty-state">No tickets are available at the moment.</div>';
        return;
      }
      BOOT.showtimes.forEach(function (showtime) {
        var card = document.createElement('article');
        card.className = 'item-card';
        card.innerHTML = '<div class="item-art" style="background-image:url(' + encodeURI(showtime.image || '../assets/images/logo.webp') + ')"></div>' +
          '<div class="item-body"><div class="item-title">' + escapeText(showtime.title) + '</div>' +
          '<div class="item-meta">' + escapeText(showtime.when) + '</div>' +
          '<div class="item-qty">Available: ' + showtime.available + '</div>' +
          '<div class="ticket-prices"><button type="button" class="ticket-action" data-age="Adult">Adult ' + formatMoney(showtime.adult) + '</button><button type="button" class="ticket-action" data-age="Child">Child ' + formatMoney(showtime.child) + '</button></div></div>';
        card.querySelectorAll('.ticket-action').forEach(function (button) {
          button.addEventListener('click', function () {
            addTicket(showtime, button.getAttribute('data-age'));
          });
        });
        els.grid.appendChild(card);
      });
      return;
    }

    var category = BOOT.concessions.find(function (cat) { return cat.name === activeTab; });
    if (!category || !category.items.length) {
      els.grid.innerHTML = '<div class="empty-state">No items are available in this category.</div>';
      return;
    }

    category.items.forEach(function (item) {
      var card = document.createElement('article');
      card.className = 'item-card';
      card.innerHTML = '<div class="item-art" style="background-image:url(' + encodeURI(item.image || '../assets/images/logo.webp') + ')"></div>' +
        '<div class="item-body"><div class="item-title">' + escapeText(item.name) + '</div>' +
        '<div class="item-meta">' + escapeText(item.description || 'Concession') + '</div>' +
        '<div class="item-bottom"><span>' + formatMoney(item.price) + '</span><span>Stock: ' + item.stock + '</span></div></div>';
      var action = document.createElement('div');
      action.className = 'item-actions';
      if (item.options && item.options.length) {
        item.options.forEach(function (opt) {
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'option-btn';
          button.textContent = opt;
          button.addEventListener('click', function () { addConcession(item, opt); });
          action.appendChild(button);
        });
      } else {
        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'btn btn-add';
        add.textContent = 'Add';
        add.addEventListener('click', function () { addConcession(item, null); });
        action.appendChild(add);
      }
      card.appendChild(action);
      els.grid.appendChild(card);
    });
  }

  function escapeText(value) {
    return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function addConcession(item, option) {
    if (lineQuantity('concession', item.id, option) >= item.stock) {
      showToast('Only ' + item.stock + ' available.');
      return;
    }
    var key = 'c:' + item.id + '|' + (option || '');
    var line = findLine(key);
    if (line) {
      line.qty += 1;
    } else {
      cart.push({
        key: key,
        kind: 'concession',
        id: item.id,
        name: item.name,
        option: option,
        price: item.price,
        qty: 1,
      });
    }
    renderMenuSummary();
    showToast(item.name + (option ? ' (' + option + ')' : '') + ' added');
  }

  function addTicket(showtime, age) {
    var key = 't:' + showtime.showtime_id + '|' + age;
    var line = findLine(key);
    if (line) {
      if (line.qty < showtime.available) {
        line.qty += 1;
      } else {
        showToast('No more seats available for this showtime.');
      }
    } else {
      if (showtime.available < 1) {
        showToast('This showtime is sold out.');
        return;
      }
      cart.push({
        key: key,
        kind: 'ticket',
        showtimeId: showtime.showtime_id,
        age: age,
        title: showtime.title,
        when: showtime.when,
        price: age === 'Child' ? showtime.child : showtime.adult,
        qty: 1,
      });
    }
    renderMenuSummary();
    showToast(age + ' ticket added');
  }

  function findLine(key) {
    return cart.find(function (line) { return line.key === key; });
  }

  function lineQuantity(kind, id, option) {
    return cart.reduce(function (total, line) {
      if (kind === 'ticket') {
        return total + (line.kind === 'ticket' && line.showtimeId === id ? line.qty : 0);
      }
      return total + (line.kind === 'concession' && line.id === id && line.option === option ? line.qty : 0);
    }, 0);
  }

  function renderMenuSummary() {
    var total = cart.reduce(function (sum, line) { return sum + line.price * line.qty; }, 0);
    var count = cart.reduce(function (sum, line) { return sum + line.qty; }, 0);
    els.summary.textContent = count ? count + ' items • ' + formatMoney(total) : 'Tap items to add them to your order.';
  }

  function renderCart() {
    els.cartItems.innerHTML = '';
    var total = 0;
    if (!cart.length) {
      els.cartItems.innerHTML = '<div class="empty-state">Your cart is empty. Add tickets or concessions to continue.</div>';
      els.cartTotal.textContent = '$0.00';
      return;
    }
    cart.forEach(function (line) {
      total += line.qty * line.price;
      var row = document.createElement('div');
      row.className = 'cart-line';
      row.innerHTML = '<div class="cart-line-main"><div class="cart-line-title">' + escapeText(lineLabel(line)) + '</div>' +
        '<div class="cart-line-meta">' + escapeText(lineMeta(line)) + '</div></div>' +
        '<div class="cart-line-controls"><button type="button" class="qty-btn" data-action="dec">-</button>' +
        '<span class="qty-value">' + line.qty + '</span>' +
        '<button type="button" class="qty-btn" data-action="inc">+</button>' +
        '<button type="button" class="rm-btn" data-action="rm">Remove</button></div>' +
        '<div class="cart-line-price">' + formatMoney(line.qty * line.price) + '</div>';
      row.querySelectorAll('[data-action]').forEach(function (button) {
        button.addEventListener('click', function () {
          var action = button.getAttribute('data-action');
          if (action === 'inc') { line.qty += 1; }
          else if (action === 'dec') { line.qty -= 1; if (line.qty <= 0) removeLine(line); }
          else if (action === 'rm') { removeLine(line); }
          renderCart();
          renderMenuSummary();
        });
      });
      els.cartItems.appendChild(row);
    });
    els.cartTotal.textContent = formatMoney(total);
  }

  function removeLine(line) {
    cart = cart.filter(function (item) { return item.key !== line.key; });
  }

  function lineLabel(line) {
    if (line.kind === 'ticket') {
      return line.title + ' (' + line.age + ')';
    }
    return line.name;
  }

  function lineMeta(line) {
    if (line.kind === 'ticket') {
      return line.when || 'Ticket';
    }
    return line.option ? line.option : 'Concession';
  }

  function renderPayment() {
    method = 'card';
    updatePaymentMethod();
    renderPaymentSummary();
    buildPinPad();
  }

  function updatePaymentMethod() {
    els.paymentMethods.querySelectorAll('.payment-option').forEach(function (button) {
      button.classList.toggle('active', button.getAttribute('data-method') === method);
    });
    els.cardPanel.classList.toggle('hidden', method !== 'card');
    els.cashPanel.classList.toggle('hidden', method !== 'cash');
    updatePinDisplay();
  }

  function renderPaymentSummary() {
    var total = cart.reduce(function (sum, line) { return sum + line.price * line.qty; }, 0);
    var html = '<div class="payment-summary-title">Order total</div>' +
      '<div class="payment-summary-total">' + formatMoney(total) + '</div>';
    cart.forEach(function (line) {
      html += '<div class="payment-summary-line"><span>' + escapeText(lineLabel(line)) + ' ×' + line.qty + '</span><span>' + formatMoney(line.price * line.qty) + '</span></div>';
    });
    els.paymentSummary.innerHTML = html;
  }

  function buildPinPad() {
    els.pinPad.innerHTML = '';
    ['1','2','3','4','5','6','7','8','9','⌫','0'].forEach(function (key) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'pin-key';
      button.textContent = key;
      button.addEventListener('click', function () {
        if (key === '⌫') {
          pin = pin.slice(0, -1);
        } else if (pin.length < 6) {
          pin += key;
        }
        updatePinDisplay();
      });
      els.pinPad.appendChild(button);
    });
    document.getElementById('pin-clear').addEventListener('click', function () {
      pin = '';
      updatePinDisplay();
    });
  }

  function updatePinDisplay() {
    var dots = pin.split('').map(function () { return '•'; }).join('');
    els.pinDisplay.textContent = dots || '••••';
    var total = cart.reduce(function (sum, line) { return sum + line.price * line.qty; }, 0);
    els.cashConfirm.disabled = (method !== 'cash') || total <= 0 || pin.length < 4;
    els.cashConfirm.classList.toggle('disabled', els.cashConfirm.disabled);
  }

  function showToast(message) {
    var toast = document.querySelector('.kiosk-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'kiosk-toast';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(function () { toast.classList.remove('show'); }, 2500);
  }

  function doCheckout() {
    if (!cart.length) {
      showToast('Your cart is empty.');
      return;
    }
    var items = cart.map(function (line) {
      if (line.kind === 'ticket') {
        return { kind: 'ticket', showtime_id: line.showtimeId, age: line.age, qty: line.qty };
      }
      return { kind: 'concession', id: line.id, option: line.option, qty: line.qty };
    });
    var payload = { method: method, items: items };
    if (method === 'cash') payload.pin = pin;
    fetch('../api/kiosk-checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(function (response) {
        return response.json().then(function (data) { return { status: response.status, body: data }; });
      })
      .then(function (result) {
        if (result.body && result.body.ok) {
          showConfirmation(result.body);
          cart = [];
          renderMenuSummary();
        } else {
          var msg = (result.body && result.body.error) ? result.body.error : 'Checkout failed. Please try again.';
          showToast(msg);
          if (result.status === 401 && method === 'cash') {
            pin = '';
            updatePinDisplay();
          }
          if (result.status === 409) {
            go('cart');
            renderCart();
          }
        }
      })
      .catch(function () {
        showToast('Network error. Please try again.');
      });
  }

  function showConfirmation(body) {
    els.confirmRef.textContent = body.daily_order_number
      ? ('#' + body.daily_order_number)
      : (body.transaction_ref || '—');
    els.confirmNote.classList.toggle('hidden', !body.items.some(function (item) { return item.item_type === 'concession'; }));
    els.confirmSubtitle.textContent = body.items.some(function (item) { return item.item_type === 'ticket'; })
      ? 'Show each QR code at the door.'
      : 'Collect your concessions at the counter.';
    renderConfirmationItems(body.items);
    renderConfirmationTickets(body.ticket_tokens || []);
    go('confirmation');
  }

  function renderConfirmationItems(items) {
    els.confirmItems.innerHTML = '';
    if (!items.length) return;
    var list = document.createElement('div');
    list.className = 'confirmation-list';
    items.forEach(function (item) {
      if (item.item_type === 'ticket') return;
      var row = document.createElement('div');
      row.className = 'confirmation-line';
      row.innerHTML = '<span>' + escapeText(item.item_name) + (item.selected_option ? ' (' + escapeText(item.selected_option) + ')' : '') + ' ×' + item.quantity + '</span>';
      list.appendChild(row);
    });
    if (list.children.length) {
      var heading = document.createElement('h3');
      heading.textContent = 'Concession order';
      els.confirmItems.appendChild(heading);
      els.confirmItems.appendChild(list);
    }
  }

  function renderConfirmationTickets(tokens) {
    els.confirmTickets.innerHTML = '';
    if (!tokens.length) return;
    var header = document.createElement('div');
    header.className = 'confirmation-ticket-header';
    header.textContent = 'Your Tickets';
    els.confirmTickets.appendChild(header);
    tokens.forEach(function (token) {
      var card = document.createElement('article');
      card.className = 'ticket-card';
      card.innerHTML = '<div class="ticket-qr"><img src="' + escapeText(token.ticket_qr) + '" alt="Ticket QR code"></div>' +
        '<div class="ticket-meta"><div class="ticket-type">' + escapeText(token.ticket_type + ' Ticket') + '</div>' +
        '<div class="ticket-film">' + escapeText(token.movie_title) + '</div>' +
        '<div class="ticket-when">' + escapeText(token.when) + '</div>' +
        '<div class="ticket-seq">Ticket ' + escapeText(String(token.seq)) + ' of ' + escapeText(String(token.seq_total)) + '</div></div>';
      els.confirmTickets.appendChild(card);
    });
  }

  document.getElementById('menu-cart-btn').addEventListener('click', function () { go('cart'); });
  document.getElementById('menu-go-cart').addEventListener('click', function () { go('cart'); });
  document.getElementById('cart-add-more').addEventListener('click', function () { go('menu'); });
  document.getElementById('cart-start-over').addEventListener('click', function () { cart = []; renderCart(); renderMenuSummary(); go('menu'); });
  document.getElementById('cart-checkout').addEventListener('click', function () { if (!cart.length) return; go('payment'); });
  document.getElementById('payment-back').addEventListener('click', function () { go('cart'); });
  document.getElementById('cash-confirm').addEventListener('click', function () { doCheckout(); });
  document.getElementById('payment-methods').addEventListener('click', function (event) {
    var button = event.target.closest('.payment-option');
    if (!button) return;
    method = button.getAttribute('data-method');
    updatePaymentMethod();
  });
  document.getElementById('done-button').addEventListener('click', function () { go('welcome'); cart = []; renderMenuSummary(); });
  document.body.addEventListener('click', function (event) {
    if (screens.welcome.classList.contains('show')) {
      go('menu');
    }
  });
  document.addEventListener('click', resetIdleTimer);
  document.addEventListener('keydown', resetIdleTimer);

  renderTabs();
  renderGrid();
  renderMenuSummary();
  resetIdleTimer();
})();
