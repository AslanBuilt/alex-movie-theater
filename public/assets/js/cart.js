(function () {
    'use strict';

    var API = 'api/cart.php';

    // CSRF token from the <meta name="csrf-token"> tag, sent on every mutating request.
    function csrfHeaders() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return { 'X-CSRF-Token': m ? m.getAttribute('content') : '' };
    }

    var drawer       = document.getElementById('cartDrawer');
    var overlay      = document.getElementById('cartOverlay');
    var bodyEl       = document.getElementById('cartBody');
    var footerEl     = document.getElementById('cartFooter');
    var badge        = document.getElementById('cartBadge');
    var cartBtn      = document.getElementById('cartBtn');
    var closeBtn     = document.getElementById('cartClose');
    var mobileBar    = document.getElementById('cartMobileBar');
    var mobileBarBtn = document.getElementById('cartMobileBarBtn');
    var mobileCount  = document.getElementById('cartMobileCount');
    var mobileTotalEl= document.getElementById('cartMobileTotalDisplay');
    var totalEl      = document.getElementById('cartTotalDisplay');
    var itemCountEl  = document.getElementById('cartItemCount');
    var toast        = document.getElementById('cartToast');
    var toastText    = document.getElementById('cartToastText');
    var fab          = document.getElementById('cartFab');
    var fabCount     = document.getElementById('cartFabCount');
    var toastTimer   = null;

    if (!drawer) return;

    // ── Escape helper ──────────────────────────────────────────────
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Toast ──────────────────────────────────────────────────────
    function showToast(name, count) {
        if (!toast) return;
        var label = count > 1 ? count + ' items in cart' : '1 item in cart';
        if (toastText) toastText.textContent = esc(name) + ' added — ' + label;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 2500);
    }

    // ── Badge pulse ────────────────────────────────────────────────
    function pulseBadge() {
        if (!badge) return;
        badge.classList.remove('pulse');
        void badge.offsetWidth;
        badge.classList.add('pulse');
    }

    // ── Open / close ───────────────────────────────────────────────
    function openDrawer() {
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        if (overlay) { overlay.classList.add('active'); }
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        if (overlay) { overlay.classList.remove('active'); }
        document.body.style.overflow = '';
    }

    var continueBtn = document.getElementById('cartContinue');
    if (continueBtn) continueBtn.addEventListener('click', closeDrawer);

    if (cartBtn)      cartBtn.addEventListener('click', openDrawer);
    if (closeBtn)     closeBtn.addEventListener('click', closeDrawer);
    if (overlay)      overlay.addEventListener('click', closeDrawer);
    if (mobileBarBtn) mobileBarBtn.addEventListener('click', openDrawer);
    if (fab)          fab.addEventListener('click', openDrawer);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDrawer(); });

    // ── Render ─────────────────────────────────────────────────────
    function renderCart(data) {
        var count = data.count || 0;
        var total = (data.total || 0).toFixed(2);
        var items = data.items || [];

        // Badge
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
        // FAB
        if (fab) {
            fab.style.display = count > 0 ? 'flex' : 'none';
            if (fabCount) fabCount.textContent = count;
        }
        // Mobile bar
        if (mobileBar)      mobileBar.style.display = count > 0 ? 'block' : 'none';
        if (mobileCount)    mobileCount.textContent = count;
        if (mobileTotalEl)  mobileTotalEl.textContent = '$' + total;
        // Drawer totals
        if (totalEl)    totalEl.textContent = '$' + total;
        if (footerEl)   footerEl.style.display = count > 0 ? 'flex' : 'none';
        if (itemCountEl) itemCountEl.textContent = count + (count === 1 ? ' item' : ' items');

        if (!bodyEl) return;

        if (items.length === 0) {
            bodyEl.innerHTML =
                '<div class="cart-empty">' +
                  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>' +
                  '<p>Your cart is empty</p>' +
                  '<span>Add something from the concessions menu</span>' +
                '</div>';
            return;
        }

        var html = '<ul class="cart-item-list">';
        items.forEach(function (item) {
            var img = item.image
                ? '<img class="cart-item-img" src="assets/' + esc(item.image) + '" alt="" loading="lazy">'
                : '<div class="cart-item-img cart-item-img--placeholder"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></div>';
            html +=
                '<li class="cart-item" data-id="' + item.id + '" data-option="' + esc(item.option || '') + '">' +
                  img +
                  '<div class="cart-item-info">' +
                    '<div class="cart-item-name">' + esc(item.name) + '</div>' +
                    '<div class="cart-item-price">$' + item.price.toFixed(2) + ' each</div>' +
                    '<div class="cart-item-controls">' +
                      '<button class="cart-qty-btn" data-action="dec" aria-label="Remove one">&#8722;</button>' +
                      '<span class="cart-qty-val">' + item.qty + '</span>' +
                      '<button class="cart-qty-btn" data-action="inc" aria-label="Add one">&#43;</button>' +
                    '</div>' +
                  '</div>' +
                  '<div class="cart-item-right">' +
                    '<span class="cart-item-subtotal">$' + item.subtotal.toFixed(2) + '</span>' +
                    '<button class="cart-item-remove" aria-label="Remove ' + esc(item.name) + '">' +
                      '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>' +
                    '</button>' +
                  '</div>' +
                '</li>';
        });
        html += '</ul>';
        bodyEl.innerHTML = html;
    }

    // ── Qty + remove (delegation) ──────────────────────────────────
    if (bodyEl) {
        bodyEl.addEventListener('click', function (e) {
            var row = e.target.closest('.cart-item');
            if (!row) return;
            var id  = row.dataset.id;
            var opt = row.dataset.option || '';
            var fd  = new FormData();

            if (e.target.closest('.cart-item-remove')) {
                fd.append('action', 'remove');
                fd.append('id', id);
                if (opt) fd.append('option', opt);
                row.classList.add('removing');
                setTimeout(function () {
                    fetch(API, { method: 'POST', body: fd, headers: csrfHeaders() })
                        .then(function (r) { return r.json(); })
                        .then(renderCart).catch(function () {});
                }, 220);
                return;
            }

            var qtyBtn = e.target.closest('.cart-qty-btn');
            if (!qtyBtn) return;
            var valEl = row.querySelector('.cart-qty-val');
            var cur   = parseInt(valEl ? valEl.textContent : '1', 10);
            var next  = qtyBtn.dataset.action === 'inc' ? cur + 1 : cur - 1;
            if (next < 0) return;
            fd.append('action', 'update');
            fd.append('id', id);
            fd.append('qty', next);
            if (opt) fd.append('option', opt);
            fetch(API, { method: 'POST', body: fd, headers: csrfHeaders() })
                .then(function (r) { return r.json(); })
                .then(renderCart).catch(function () {});
        });
    }

    // ── Add to cart ────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-add-cart');
        if (!btn || !btn.dataset.id) return;

        var origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Adding…';

        var itemName = btn.dataset.name || 'Item';
        var fd = new FormData();
        fd.append('action', 'add');
        fd.append('id', btn.dataset.id);
        fd.append('qty', '1');

        fetch(API, { method: 'POST', body: fd, headers: csrfHeaders() })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderCart(data);
                pulseBadge();
                btn.textContent = '✓ Added';
                btn.classList.add('added');
                showToast(itemName, data.count || 0);
                setTimeout(function () {
                    btn.textContent = origText;
                    btn.classList.remove('added');
                    btn.disabled = false;
                }, 1600);
            })
            .catch(function () {
                btn.textContent = origText;
                btn.disabled = false;
            });
    });

    // ── Init ───────────────────────────────────────────────────────
    fetch(API + '?action=items')
        .then(function (r) { return r.json(); })
        .then(renderCart)
        .catch(function () {});

})();
