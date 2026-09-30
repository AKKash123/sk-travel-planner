/* =========================================================
   carousel-extras.js
   1) Hero carousel (lazy-loaded slides)
   2) Dual-handle price slider (drives the existing #priceFilter)
   3) Tap-a-star ratings
   Load with `defer`, AFTER app.js
========================================================= */
(function () {
    'use strict';

    var $  = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var inr = function (n) { return '₹' + Number(n).toLocaleString('en-IN'); };

    /* Each feature runs on its own: an error in one can't stop the others */
    function safe(name, fn) {
        try { fn(); }
        catch (e) { if (window.console) console.error('[carousel-extras] ' + name + ' failed:', e); }
    }

    /* ---------------------------------------------------------
       1) HERO CAROUSEL
    --------------------------------------------------------- */
    safe('carousel', function () {
        var root = $('.hero-carousel');
        if (!root) return;

        var slides = $$('.hc-slide', root);
        var dotsWrap = $('.hc-dots', root);
        var place = $('.hc-place span', root);
        var progress = $('.hc-progress', root);
        var delay = 5500;
        var index = 0, timer = null, paused = false, hoverPaused = false;
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* Load a slide's image only when it is needed */
        function load(i) {
            var slide = slides[i];
            if (!slide) return;
            var img = $('img', slide);
            if (!img || img.dataset.loading) return;
            img.dataset.loading = '1';
            if (img.dataset.srcset) img.srcset = img.dataset.srcset;
            if (img.dataset.src) img.src = img.dataset.src;
            img.addEventListener('load', function () { img.classList.add('is-loaded'); });
            img.addEventListener('error', function () { dropSlide(slide); });
            if (img.complete && img.naturalWidth) img.classList.add('is-loaded');
        }

        /* A broken image URL keeps its slide, shown as a gradient with the place name */
        function dropSlide(slide) {
            slide.classList.add('is-fallback');
            var img = $('img', slide);
            if (img) img.style.display = 'none';
            if (window.console) console.warn('[carousel-extras] image failed to load for:', slide.dataset.title);
        }

        function buildDots() {
            dotsWrap.innerHTML = '';
            slides.forEach(function (s, i) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'hc-dot';
                b.setAttribute('aria-label', 'Go to slide ' + (i + 1) + ': ' + (s.dataset.title || ''));
                b.addEventListener('click', function () { go(i); });
                dotsWrap.appendChild(b);
            });
        }

        function restartProgress() {
            if (!progress || reduce) return;
            progress.classList.remove('is-running');
            void progress.offsetWidth;                 // restart the CSS animation
            progress.classList.add('is-running');
        }

        function show(i, silent) {
            index = (i + slides.length) % slides.length;
            load(index);
            load((index + 1) % slides.length);         // warm up the next slide

            slides.forEach(function (s, n) {
                var on = n === index;
                s.classList.toggle('is-active', on);
                s.setAttribute('aria-hidden', on ? 'false' : 'true');
            });
            $$('.hc-dot', root).forEach(function (d, n) {
                d.classList.toggle('is-active', n === index);
                d.setAttribute('aria-current', n === index ? 'true' : 'false');
            });

            var cur = slides[index];
            if (place && cur) {
                var pl = place.parentNode;
                pl.classList.add('is-swapping');
                setTimeout(function () {
                    place.textContent = cur.dataset.title + ', ' + cur.dataset.state;
                    pl.classList.remove('is-swapping');
                }, silent ? 0 : 250);
            }
            schedule();
        }

        function schedule() {
            clearTimeout(timer);
            restartProgress();
            if (paused || hoverPaused || slides.length < 2) return;
            timer = setTimeout(function () { show(index + 1); }, delay);
        }

        function go(i) { show(i); }

        function setPaused(p) {
            paused = p;
            var btn = $('.hc-toggle', root);
            if (btn) {
                btn.setAttribute('aria-label', p ? 'Play slideshow' : 'Pause slideshow');
                btn.innerHTML = p
                    ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg>'
                    : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg>';
            }
            if (progress) progress.classList.toggle('is-paused', p);
            if (p) clearTimeout(timer); else schedule();
        }

        root.style.setProperty('--hc-delay', delay + 'ms');
        buildDots();

        $('.hc-prev', root).addEventListener('click', function () { go(index - 1); });
        $('.hc-next', root).addEventListener('click', function () { go(index + 1); });
        $('.hc-toggle', root).addEventListener('click', function () { setPaused(!paused); });

        /* Pause while the pointer is over the hero or focus is inside it */
        root.addEventListener('mouseenter', function () { hoverPaused = true; clearTimeout(timer); if (progress) progress.classList.add('is-paused'); });
        root.addEventListener('mouseleave', function () { hoverPaused = false; if (progress) progress.classList.remove('is-paused'); schedule(); });

        /* Keyboard arrows while the hero has focus */
        root.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft')  { go(index - 1); }
            if (e.key === 'ArrowRight') { go(index + 1); }
        });

        /* Touch swipe */
        var x0 = null;
        root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        root.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 45) go(index + (dx < 0 ? 1 : -1));
            x0 = null;
        });

        /* Don't burn cycles in background tabs or when the hero is off-screen */
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) clearTimeout(timer); else schedule();
        });
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                hoverPaused = !entries[0].isIntersecting;
                if (hoverPaused) clearTimeout(timer); else schedule();
            }, { threshold: 0.25 }).observe(root);
        }

        if (reduce) setPaused(true);
        show(0, true);
    });

    /* ---------------------------------------------------------
       2) PRICE RANGE SLIDER
       Keeps the hidden <select id="priceFilter"> in sync so the
       existing filter code in app.js keeps working untouched.
       Values: "all"  or  "min-max"  (same format as before)
    --------------------------------------------------------- */
    safe('price slider', function () {
        var wrap = $('#priceSlider');
        var sel  = $('#priceFilter');
        if (!wrap || !sel) return;

        var minEl = $('#priceMin'), maxEl = $('#priceMax');
        var out = $('#priceValue'), minLbl = $('#psMinLabel'), maxLbl = $('#psMaxLabel');
        var FLOOR = +minEl.min, CEIL = +minEl.max, STEP = +minEl.step || 500;
        var custom = document.createElement('option');
        sel.appendChild(custom);
        var debounce;

        function paint() {
            var lo = +minEl.value, hi = +maxEl.value;
            var span = CEIL - FLOOR || 1;
            wrap.style.setProperty('--lo', ((lo - FLOOR) / span * 100) + '%');
            wrap.style.setProperty('--hi', ((hi - FLOOR) / span * 100) + '%');
            var full = lo <= FLOOR && hi >= CEIL;
            out.textContent = full ? 'Any price'
                : (hi >= CEIL ? inr(lo) + '+' : inr(lo) + ' – ' + inr(hi));
            minEl.style.zIndex = lo > CEIL - STEP * 2 ? 3 : 2;   // keep the min thumb reachable at the far right
        }

        function commit() {
            var lo = +minEl.value, hi = +maxEl.value;
            var atFloor = lo <= FLOOR, atCeil = hi >= CEIL;
            if (atFloor && atCeil) {
                sel.value = 'all';
            } else {
                custom.value = (atFloor ? 0 : lo) + '-' + (atCeil ? 999999999 : hi);
                custom.textContent = out.textContent;
                sel.value = custom.value;
            }
            sel.dispatchEvent(new Event('input',  { bubbles: true }));
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function onMove(which) {
            var lo = +minEl.value, hi = +maxEl.value;
            if (which === 'min' && lo > hi - STEP) minEl.value = hi - STEP;
            if (which === 'max' && hi < lo + STEP) maxEl.value = lo + STEP;
            paint();
            clearTimeout(debounce);
            debounce = setTimeout(commit, 120);
        }

        minEl.addEventListener('input', function () { onMove('min'); });
        maxEl.addEventListener('input', function () { onMove('max'); });

        /* When app.js resets / removes a chip it sets the select — mirror that */
        function syncFromSelect() {
            if (sel.value === 'all') {
                minEl.value = FLOOR; maxEl.value = CEIL;
            } else {
                var p = sel.value.split('-').map(Number);
                minEl.value = Math.max(FLOOR, p[0] || FLOOR);
                maxEl.value = p[1] >= CEIL ? CEIL : Math.min(CEIL, p[1]);
            }
            paint();
        }
        document.addEventListener('click', function (e) {
            if (e.target.closest('#resetFilters, #resetFiltersEmpty, #activeFilters')) {
                setTimeout(syncFromSelect, 0);
                setTimeout(syncFromSelect, 60);
            }
        });

        minLbl.textContent = inr(FLOOR);
        maxLbl.textContent = inr(CEIL) + '+';
        paint();
    });

    /* ---------------------------------------------------------
       3) TAP-A-STAR RATINGS
    --------------------------------------------------------- */
    safe('ratings', function () {
        var boxes = $$('.rate-box');
        if (!boxes.length) return;

        /* anonymous per-browser token + remembered ratings */
        var store = {
            get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
            set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
        };
        var token = store.get('sk_reviewer_token');
        if (!token) {
            var arr = new Uint8Array(24);
            (window.crypto || window.msCrypto).getRandomValues(arr);
            token = Array.prototype.map.call(arr, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
            store.set('sk_reviewer_token', token);
        }
        var mine = {};
        try { mine = JSON.parse(store.get('sk_my_ratings') || '{}') || {}; } catch (e) {}

        /* toast */
        var toast = document.createElement('div');
        toast.className = 'rate-toast';
        toast.setAttribute('role', 'status');
        document.body.appendChild(toast);
        var toastTimer;
        function say(msg, isError) {
            toast.textContent = msg;
            toast.classList.toggle('error', !!isError);
            toast.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 2600);
        }

        function paintStars(box, n) {
            $$('.rate-star', box).forEach(function (s, i) {
                var on = i < n;
                s.classList.toggle('on', on);
                s.setAttribute('aria-checked', (i + 1) === (+box.dataset.mine || 0) ? 'true' : 'false');
            });
        }

        function paintHint(box) {
            var hint = $('.rate-hint', box), m = +box.dataset.mine || 0;
            hint.classList.toggle('is-mine', !!m);
            hint.textContent = m ? 'You rated ' + m + '★ · tap to change' : 'Tap a star to rate';
        }

        function updatePill(card, avg, count) {
            var pill = $('.rating-pill', card);
            if (!pill) return;
            pill.classList.toggle('is-new', !count);
            pill.innerHTML = count
                ? '<i class="fas fa-star"></i><b>' + Number(avg).toFixed(1) + '</b><em>(' + count + ')</em>'
                : '<i class="fas fa-star"></i><b>New</b>';
            pill.setAttribute('aria-label', count ? 'Rated ' + Number(avg).toFixed(1) + ' out of 5 by ' + count + ' travellers' : 'No ratings yet');
            pill.classList.remove('is-bump'); void pill.offsetWidth; pill.classList.add('is-bump');
        }

        boxes.forEach(function (box) {
            var id = box.dataset.id;
            var card = box.closest('.itin-card');
            box.dataset.mine = mine[id] || 0;
            paintStars(box, +box.dataset.mine);
            paintHint(box);

            var stars = $$('.rate-star', box);
            stars.forEach(function (star, i) {
                star.addEventListener('mouseenter', function () { paintStars(box, i + 1); });
                star.addEventListener('focus',      function () { paintStars(box, i + 1); });
                star.addEventListener('click', function () { submit(i + 1, star); });
            });
            box.addEventListener('mouseleave', function () { paintStars(box, +box.dataset.mine); });
            box.addEventListener('focusout',   function () { paintStars(box, +box.dataset.mine); });

            function submit(value, star) {
                var previous = +box.dataset.mine || 0;
                box.dataset.mine = value;
                paintStars(box, value);
                star.classList.remove('pop'); void star.offsetWidth; star.classList.add('pop');
                box.classList.add('is-busy');

                var fd = new FormData();
                fd.append('itinerary_id', id);
                fd.append('rating', value);
                fd.append('token', token);

                fetch('review.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.text().then(function (t) {
                            try { return JSON.parse(t); }
                            catch (e) {
                                if (window.console) console.error('[carousel-extras] review.php returned:', r.status, t.slice(0, 300));
                                throw new Error(r.status === 404 ? 'review.php was not found on the server'
                                                                 : 'review.php sent an unexpected response (see browser console)');
                            }
                        });
                    })
                    .then(function (d) {
                        if (!d || !d.ok) throw new Error((d && d.error) || 'Failed');
                        mine[id] = d.mine;
                        store.set('sk_my_ratings', JSON.stringify(mine));
                        box.dataset.mine = d.mine;
                        updatePill(card, d.avg, d.count);
                        paintHint(box);
                        say(previous ? 'Rating updated to ' + d.mine + '★' : 'Thanks! You rated this ' + d.mine + '★');
                    })
                    .catch(function (err) {
                        box.dataset.mine = previous;
                        paintStars(box, previous);
                        paintHint(box);
                        say((err && err.message && err.message !== 'Failed') ? err.message : 'Could not save your rating. Please try again.', true);
                    })
                    .then(function () { box.classList.remove('is-busy'); });
            }
        });
    });
})();