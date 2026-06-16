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

    // ── Contact form — Formspree AJAX ──
    var form = document.getElementById('contact-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var submitBtn  = document.getElementById('cf-submit');
            var btnText    = submitBtn.querySelector('.btn-text');
            var btnLoading = submitBtn.querySelector('.btn-loading');
            var success    = document.getElementById('cf-success');
            var error      = document.getElementById('cf-error');

            btnText.style.display    = 'none';
            btnLoading.style.display = 'inline';
            submitBtn.disabled = true;
            success.style.display = 'none';
            error.style.display   = 'none';

            fetch(form.action, {
                method:  'POST',
                body:    new FormData(form),
                headers: { Accept: 'application/json' }
            })
            .then(function (r) {
                if (r.ok) {
                    form.style.display    = 'none';
                    success.style.display = 'block';
                } else {
                    throw new Error('server');
                }
            })
            .catch(function () {
                error.style.display      = 'block';
                btnText.style.display    = 'inline';
                btnLoading.style.display = 'none';
                submitBtn.disabled = false;
            });
        });
    }

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
        window.addEventListener('resize', function () { scrollTo(0); });
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
