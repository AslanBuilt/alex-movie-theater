document.addEventListener('DOMContentLoaded', function () {

    // ── Mobile nav toggle ──
    var toggle = document.querySelector('.nav-toggle');
    var menu   = document.querySelector('.nav-menu');

    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', menu.classList.contains('open'));
        });

        document.addEventListener('click', function (e) {
            if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('open');
            }
        });
    }

    // ── Page fade-in ──
    var mainEl = document.querySelector('main');
    if (mainEl) mainEl.classList.add('page-main');

    // ── Stagger reveal on scroll (cards and components only — NOT sections) ──
    var revealEls = document.querySelectorAll('.movie-card, .info-card, .coming-soon-card, .price-card, .rental-card, .next-showing-card, .highlight-box, .policy-box');

    if ('IntersectionObserver' in window) {
        var delay = 0;
        revealEls.forEach(function (el) {
            el.classList.add('reveal');
            el.style.transitionDelay = delay + 'ms';
            delay = (delay + 80) % 480;
        });

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

        revealEls.forEach(function (el) { observer.observe(el); });
    }

    // ── Photo slideshow ──
    var slideshows = document.querySelectorAll('.photo-slideshow');

    slideshows.forEach(function (show) {
        var track  = show.querySelector('.slideshow-track');
        var slides = show.querySelectorAll('.slideshow-slide');
        var prev   = show.querySelector('.slideshow-btn-prev');
        var next   = show.querySelector('.slideshow-btn-next');
        var dots   = show.querySelectorAll('.slideshow-dot');

        if (!track || slides.length < 2) return;

        var current = 0;
        var total   = slides.length;
        var timer;
        var isFade  = show.classList.contains('hero-slideshow');

        function goTo(idx) {
            current = (idx + total) % total;
            if (isFade) {
                slides.forEach(function (s, i) {
                    s.classList.toggle('active', i === current);
                });
            } else {
                track.style.transform = 'translateX(-' + (current * 100) + '%)';
            }
            dots.forEach(function (d, i) {
                d.classList.toggle('active', i === current);
            });
        }

        function startTimer() {
            clearInterval(timer);
            timer = setInterval(function () { goTo(current + 1); }, 4500);
        }

        if (prev) prev.addEventListener('click', function () { goTo(current - 1); startTimer(); });
        if (next) next.addEventListener('click', function () { goTo(current + 1); startTimer(); });

        dots.forEach(function (d, i) {
            d.addEventListener('click', function () { goTo(i); startTimer(); });
        });

        // Touch / swipe support
        var touchStartX = 0;
        track.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        track.addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 40) {
                goTo(dx < 0 ? current + 1 : current - 1);
                startTimer();
            }
        }, { passive: true });

        goTo(0);
        startTimer();
    });

    // ── Formspree forms — validation, GOV.UK-style error summary, AJAX submit ──
    // Shared by every .js-formspree-form on the site (general contact, rental
    // inquiry, ...) so validation/error UX stays identical per frontend-form-patterns.
    function fieldLabelText(form, field) {
        var label = form.querySelector('label[for="' + field.id + '"]');
        return label ? label.textContent.replace('*', '').trim() : (field.name || 'This field');
    }

    function fieldErrorMessage(form, field) {
        if (field.validity.valueMissing) return fieldLabelText(form, field) + ' is required';
        if (field.validity.typeMismatch && field.type === 'email') return 'Enter a valid email address';
        if (field.validity.typeMismatch && field.type === 'tel') return 'Enter a valid phone number';
        return fieldLabelText(form, field) + ' is not valid';
    }

    function setFieldError(field, msg) {
        var group = field.closest('.form-group');
        if (group) group.classList.add('has-error');
        field.setAttribute('aria-invalid', 'true');
        var errId = field.getAttribute('aria-describedby');
        var errEl = errId ? document.getElementById(errId) : null;
        if (errEl) {
            errEl.hidden = false;
            errEl.innerHTML = '<span class="sr-only">Error: </span>' + msg;
        }
    }

    function clearFieldError(field) {
        var group = field.closest('.form-group');
        if (group) group.classList.remove('has-error');
        field.removeAttribute('aria-invalid');
        var errId = field.getAttribute('aria-describedby');
        var errEl = errId ? document.getElementById(errId) : null;
        if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
    }

    function initFormspreeForm(form) {
        var wrap       = form.closest('.contact-form-wrap') || form.parentElement;
        var submitBtn  = form.querySelector('button[type="submit"]');
        var btnText    = submitBtn ? submitBtn.querySelector('.btn-text') : null;
        var btnLoading = submitBtn ? submitBtn.querySelector('.btn-loading') : null;
        var success    = wrap ? wrap.querySelector('.form-success') : null;
        var bannerError = wrap ? wrap.querySelector('.form-feedback.form-error') : null;
        var summary    = form.querySelector('.form-error-summary');
        var summaryList = summary ? summary.querySelector('ul') : null;

        var requiredFields = Array.prototype.slice.call(form.querySelectorAll('[required]'));

        requiredFields.forEach(function (field) {
            field.addEventListener('blur', function () {
                if (!field.checkValidity()) {
                    setFieldError(field, fieldErrorMessage(form, field));
                } else {
                    clearFieldError(field);
                }
            });
            field.addEventListener('input', function () {
                var group = field.closest('.form-group');
                if (group && group.classList.contains('has-error') && field.checkValidity()) {
                    clearFieldError(field);
                }
            });
        });

        function validate() {
            var invalid = [];
            requiredFields.forEach(function (field) {
                if (!field.checkValidity()) {
                    var msg = fieldErrorMessage(form, field);
                    setFieldError(field, msg);
                    invalid.push({ field: field, msg: msg });
                } else {
                    clearFieldError(field);
                }
            });
            return invalid;
        }

        function showSummary(invalid) {
            if (!summary || !summaryList) return;
            summaryList.innerHTML = '';
            invalid.forEach(function (item) {
                var li = document.createElement('li');
                var a  = document.createElement('a');
                a.href = '#' + item.field.id;
                a.textContent = item.msg;
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    item.field.focus();
                });
                li.appendChild(a);
                summaryList.appendChild(li);
            });
            summary.hidden = false;
            summary.focus();
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var invalid = validate();
            if (invalid.length) {
                showSummary(invalid);
                return;
            }
            if (summary) summary.hidden = true;

            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'inline';
            if (submitBtn) submitBtn.disabled = true;
            if (success) success.style.display = 'none';
            if (bannerError) bannerError.style.display = 'none';

            fetch(form.action, {
                method:  'POST',
                body:    new FormData(form),
                headers: { Accept: 'application/json' }
            })
            .then(function (r) {
                if (r.ok) {
                    form.style.display = 'none';
                    if (success) success.style.display = 'block';
                } else {
                    throw new Error('server');
                }
            })
            .catch(function () {
                if (bannerError) bannerError.style.display = 'block';
                if (btnText) btnText.style.display = 'inline';
                if (btnLoading) btnLoading.style.display = 'none';
                if (submitBtn) submitBtn.disabled = false;
            });
        });
    }

    document.querySelectorAll('.js-formspree-form').forEach(initFormspreeForm);

    // ── Rental package buttons — preselect + scroll to the inquiry form ──
    document.querySelectorAll('.js-package-select').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var target = document.querySelector('#rental-inquiry');
            var select = document.getElementById('ri-package');
            if (!target) return;
            e.preventDefault();
            if (select) select.value = btn.getAttribute('data-package') || '';
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // ── Poster carousel (Now Showing) ──
    document.querySelectorAll('.poster-carousel-wrap').forEach(function (wrap) {
        var carousel = wrap.querySelector('.poster-carousel');
        var track    = wrap.querySelector('.poster-track');
        var prevBtn  = wrap.querySelector('.carousel-prev');
        var nextBtn  = wrap.querySelector('.carousel-next');

        if (!track || track.children.length === 0) return;

        var pos = 0;

        function cardWidth() {
            var card = track.children[0];
            var gap  = parseInt(getComputedStyle(track).gap) || 24;
            return card.offsetWidth + gap;
        }

        function visibleCount() {
            return Math.max(1, Math.floor(carousel.offsetWidth / cardWidth()));
        }

        function maxPos() {
            return Math.max(0, track.children.length - visibleCount());
        }

        function scrollTo(idx) {
            pos = Math.max(0, Math.min(idx, maxPos()));
            track.style.transform = 'translateX(-' + (pos * cardWidth()) + 'px)';
            if (prevBtn) prevBtn.disabled = pos === 0;
            if (nextBtn) nextBtn.disabled = pos >= maxPos();
        }

        function updateArrows() {
            var needed = track.children.length > visibleCount();
            if (prevBtn) prevBtn.style.display = needed ? '' : 'none';
            if (nextBtn) nextBtn.style.display = needed ? '' : 'none';
        }

        if (prevBtn) prevBtn.addEventListener('click', function () { scrollTo(pos - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { scrollTo(pos + 1); });

        // Touch / swipe
        var touchX = 0;
        track.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
        track.addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - touchX;
            if (Math.abs(dx) > 40) scrollTo(dx < 0 ? pos + 1 : pos - 1);
        }, { passive: true });

        scrollTo(0);
        updateArrows();
        window.addEventListener('resize', function () { scrollTo(0); updateArrows(); });
    });

    // ── Showtime day tabs + time buttons are handled by the inline script in
    //    movie.php (handles both new-style transactional slots and legacy
    //    label/times modes). A previous handler lived here but conflicted with
    //    it — on day-tab click it rebuilt the time buttons as <a> links to
    //    tickets.php, hijacking the in-page purchase flow. Removed. ──

    // ── Click tracking (fires gtag/fbq if loaded; no-op otherwise) ──
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-track]');
        if (!el) return;
        var eventName = el.getAttribute('data-track');
        var label     = el.getAttribute('data-track-label') || el.textContent.trim().slice(0, 60);
        if (typeof gtag === 'function') {
            gtag('event', eventName, { event_label: label });
        }
        if (typeof fbq === 'function' && eventName === 'movie-click') {
            fbq('track', 'ViewContent', { content_name: label });
        }
        if (typeof fbq === 'function' && (eventName === 'showtime-click' || eventName === 'buy-tickets')) {
            fbq('track', 'InitiateCheckout', { content_name: label });
        }
    });

    // ── Admin: poster image preview ──
    var posterFile = document.getElementById('poster_file');
    var posterPreview = document.getElementById('poster-preview');
    if (posterFile && posterPreview) {
        posterFile.addEventListener('change', function () {
            var file = posterFile.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                posterPreview.src = e.target.result;
                posterPreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    }

});
