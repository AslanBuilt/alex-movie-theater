(function () {
    'use strict';

    var API = 'api/cart.php';

    var drawer      = document.getElementById('cartDrawer');
    var overlay     = document.getElementById('cartOverlay');
    var bodyEl      = document.getElementById('cartBody');
    var footerEl    = document.getElementById('cartFooter');
    var badge       = document.getElementById('cartBadge');
    var cartBtn     = document.getElementById('cartBtn');
    var closeBtn    = document.getElementById('cartClose');
    var mobileBar   = document.getElementById('cartMobileBar');
    var mobileBarBtn= document.getElementById('cartMobileBarBtn');
    var mobileCount = document.getElementById('cartMobileCount');
    var mobileTotalEl = document.getElementById('cartMobileTotalDisplay');
    var totalEl     = document.getElementById('cartTotalDisplay');

    if (!drawer) return;

    // ── Open / close ──────────────────────────────────────────────
    function openDrawer() {
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        if (overlay) { overlay.classList.add('active'); overlay.setAttribute('aria-hidden', 'false'); }
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        if (overlay) { overlay.classList.remove('active'); overlay.setAttribute('aria-hidden', 'true'); }
        document.body.style.overflow = '';
    }

    if (cartBtn)     cartBtn.addEventListener('click', openDrawer);
    if (closeBtn)    closeBtn.addEventListener('click', closeDrawer);
    if (overlay)     overlay.addEventListener('click', closeDrawer);
    if (mobileBarBtn) mobileBarBtn.addEventListener('click', openDrawer);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDrawer();
    });

    // ── HTML-escape helper ────────────────────────────────────────
    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Render cart state ─────────────────────────────────────────
    function renderCart(data) {
        var count = data.count || 0;
        var total = ((data.total || 0)).toFixed(2);
        var items = data.items || [];

        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
        if (mobileBar)     mobileBar.style.display = count > 0 ? 'block' : 'none';
        if (mobileCount)   mobileCount.textContent = count;
        if (mobileTotalEl) mobileTotalEl.textContent = '$' + total;
        if (totalEl)       totalEl.textContent = '$' + total;
        if (footerEl)      footerEl.style.display = count > 0 ? 'block' : 'none';

        if (!bodyEl) return;

        if (items.length === 0) {
            bodyEl.innerHTML = '<p class="cart-empty">Your cart is empty.</p>';
            return;
        }

        var html = '';
        items.forEach(function (item) {
            var imgHtml = item.image
                ? '<img class="cart-item-img" src="assets/' + esc(item.image) + '" alt="" loading="lazy">'
                : '<div class="cart-item-img cart-item-img--placeholder"></div>';
            html +=
                '<div class="cart-item">' +
                imgHtml +
                '<div class="cart-item-info">' +
                  '<div class="cart-item-name">' + esc(item.name) + '</div>' +
                  '<div class="cart-item-meta">Qty: ' + item.qty + ' &bull; $' + item.price.toFixed(2) + ' ea</div>' +
                '</div>' +
                '<span class="cart-item-subtotal">$' + item.subtotal.toFixed(2) + '</span>' +
                '<button class="cart-item-remove" data-id="' + item.id + '" data-option="' + esc(item.option) + '" aria-label="Remove ' + esc(item.name) + '">&times;</button>' +
                '</div>';
        });
        bodyEl.innerHTML = html;
    }

    // ── Remove item ───────────────────────────────────────────────
    if (bodyEl) {
        bodyEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.cart-item-remove');
            if (!btn) return;
            var fd = new FormData();
            fd.append('action', 'remove');
            fd.append('id', btn.dataset.id);
            if (btn.dataset.option) fd.append('option', btn.dataset.option);
            fetch(API, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(renderCart)
                .catch(function () {});
        });
    }

    // ── Add to cart (event delegation on document) ────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-add-cart');
        if (!btn || !btn.dataset.id) return;

        var origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Adding…';

        var fd = new FormData();
        fd.append('action', 'add');
        fd.append('id', btn.dataset.id);
        fd.append('qty', '1');

        fetch(API, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderCart(data);
                btn.textContent = '✓ Added';
                btn.classList.add('added');
                openDrawer();
                setTimeout(function () {
                    btn.textContent = origText;
                    btn.classList.remove('added');
                    btn.disabled = false;
                }, 1800);
            })
            .catch(function () {
                btn.textContent = origText;
                btn.disabled = false;
            });
    });

    // ── Init: load cart state on every page load ──────────────────
    fetch(API + '?action=items')
        .then(function (r) { return r.json(); })
        .then(renderCart)
        .catch(function () {});

})();
