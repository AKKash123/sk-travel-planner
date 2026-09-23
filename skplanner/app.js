/* =========================================================
   app.js — homepage behaviour
   1. Itinerary listing: filters, "Load more", skeletons, lazy images
   2. Chatbot: understands budget / destination / duration and
      recommends real packages from the database
   Expects window.APP = { name, whatsapp, pageSize, packages[] }
========================================================= */
(function () {
    'use strict';

    const APP = window.APP || {};
    const PKGS = APP.packages || [];
    const PAGE_SIZE = APP.pageSize || 3;

    /* ---------- helpers ---------- */
    const $ = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

    const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    const esc = (t) => String(t).replace(/[&<>"']/g, (ch) => ESC[ch]);
    const money = (n) => '₹' + Math.round(n).toLocaleString('en-IN');
    const titleCase = (s) => s.replace(/\b\w/g, (c) => c.toUpperCase());
    const regexEscape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const plural = (n, word) => n + ' ' + word + (n === 1 ? '' : 's');

    const store = {
        get(key, fallback) {
            try {
                const v = sessionStorage.getItem(key);
                return v ? JSON.parse(v) : fallback;
            } catch (e) { return fallback; }
        },
        set(key, val) {
            try { sessionStorage.setItem(key, JSON.stringify(val)); } catch (e) { /* storage blocked */ }
        }
    };

    /* =========================================================
       1. LISTING
    ========================================================= */
    const Listing = (function () {
        const grid = $('#itineraryGrid');
        if (!grid) return null;

        const cards = $$('.itinerary-item', grid);
        const els = {
            price: $('#priceFilter'),
            dest: $('#destinationSearch'),
            reset: $('#resetFilters'),
            resetEmpty: $('#resetFiltersEmpty'),
            count: $('#resultCount'),
            total: $('#totalCount'),
            none: $('#noFilterResults'),
            wrap: $('#loadMoreWrap'),
            btn: $('#loadMoreBtn'),
            label: $('#loadMoreBtn .btn-label'),
            hint: $('#loadMoreHint'),
            chips: $('#activeFilters'),
            skel: $('#skeletonGrid')
        };

        const state = {
            min: 0,
            max: Infinity,
            dest: '',
            shown: PAGE_SIZE,
            loading: false,
            maxFromChat: false
        };

        function matched() {
            return cards.filter((c) => {
                const p = parseFloat(c.dataset.price) || 0;
                if (p < state.min || p > state.max) return false;
                if (!state.dest) return true;
                return (c.dataset.destination || '').includes(state.dest) ||
                       (c.dataset.title || '').includes(state.dest);
            });
        }

        function renderChips() {
            if (!els.chips) return;
            els.chips.innerHTML = (state.maxFromChat && isFinite(state.max))
                ? `<button type="button" class="filter-chip" data-clear="budget">
                       Budget up to ${money(state.max)} <i class="fas fa-times"></i>
                   </button>`
                : '';
        }

        function render() {
            const m = matched();
            const visible = new Set(m.slice(0, state.shown));

            cards.forEach((c) => c.classList.toggle('is-hidden', !visible.has(c)));

            if (els.count) els.count.textContent = visible.size;
            if (els.total) els.total.textContent = m.length;
            if (els.none) els.none.style.display = m.length ? 'none' : 'block';

            const remaining = m.length - visible.size;
            if (els.wrap) {
                els.wrap.hidden = remaining <= 0;
                if (remaining > 0) {
                    const next = Math.min(PAGE_SIZE, remaining);
                    els.label.textContent = 'Load ' + next + ' more';
                    els.hint.textContent = remaining + ' more package' + (remaining === 1 ? '' : 's') + ' available';
                }
            }
            renderChips();
        }

        function skeletonEl() {
            const el = document.createElement('div');
            el.className = 'sk-card';
            el.setAttribute('aria-hidden', 'true');
            el.innerHTML =
                '<div class="skeleton sk-img"></div>' +
                '<div class="sk-body">' +
                    '<div class="skeleton sk-line lg"></div>' +
                    '<div class="skeleton sk-line sm"></div>' +
                    '<div class="skeleton sk-line md"></div>' +
                    '<div class="skeleton sk-line"></div>' +
                    '<div class="sk-row"><div class="skeleton sk-line price"></div><div class="skeleton sk-btn"></div></div>' +
                '</div>';
            return el;
        }

        function loadMore() {
            if (state.loading) return;

            const m = matched();
            const remaining = m.length - state.shown;
            if (remaining <= 0) return;

            state.loading = true;
            els.btn.disabled = true;
            els.btn.classList.add('is-loading');
            els.label.textContent = 'Loading…';

            const n = Math.min(PAGE_SIZE, remaining);
            const placeholders = [];
            for (let i = 0; i < n; i++) {
                const sk = skeletonEl();
                grid.insertBefore(sk, els.none || null);
                placeholders.push(sk);
            }

            // Short pause so the skeletons are visible and the new images can start loading
            setTimeout(function () {
                placeholders.forEach((sk) => sk.remove());

                const prev = state.shown;
                state.shown += n;
                render();

                m.slice(prev, state.shown).forEach((c, i) => {
                    c.classList.add('reveal');
                    c.style.animationDelay = (i * 80) + 'ms';
                });

                const firstNew = m[prev] && $('a', m[prev]);
                if (firstNew) firstNew.focus({ preventScroll: true });

                state.loading = false;
                els.btn.disabled = false;
                els.btn.classList.remove('is-loading');
            }, 500);
        }

        /* ----- filters ----- */
        function readPrice() {
            const v = els.price ? els.price.value : 'all';
            state.maxFromChat = false;
            if (v === 'all') {
                state.min = 0;
                state.max = Infinity;
            } else {
                const r = v.split('-');
                state.min = parseFloat(r[0]);
                state.max = parseFloat(r[1]);
            }
        }

        function onFilterChange() {
            state.shown = PAGE_SIZE;
            render();
        }

        function resetAll() {
            if (els.price) els.price.value = 'all';
            if (els.dest) els.dest.value = '';
            state.min = 0;
            state.max = Infinity;
            state.dest = '';
            state.maxFromChat = false;
            onFilterChange();
        }

        // Used by the chatbot ("Show on page")
        function apply(o) {
            state.min = 0;
            state.max = o.max > 0 ? o.max : Infinity;
            state.maxFromChat = o.max > 0;
            state.dest = (o.dest || '').toLowerCase();
            if (els.price) els.price.value = 'all';
            if (els.dest) els.dest.value = o.dest ? titleCase(o.dest) : '';
            onFilterChange();
        }

        /* ----- images: fade in once loaded, drop shimmer ----- */
        function watchImages() {
            cards.forEach((c) => {
                const wrap = $('.itin-card-img', c);
                const img = $('img', c);
                if (!wrap || !img) return;
                const done = () => wrap.classList.add('is-loaded');
                if (img.complete) done();
                else {
                    img.addEventListener('load', done, { once: true });
                    img.addEventListener('error', done, { once: true });
                }
            });
        }

        function whenImages(imgs, timeout) {
            return new Promise((resolve) => {
                let left = imgs.length;
                if (!left) return resolve();
                const one = () => { if (--left <= 0) resolve(); };
                imgs.forEach((img) => {
                    if (img.complete) one();
                    else {
                        img.addEventListener('load', one, { once: true });
                        img.addEventListener('error', one, { once: true });
                    }
                });
                setTimeout(resolve, timeout);
            });
        }

        function init() {
            watchImages();
            render();

            // Keep the skeleton up until the first batch of images is ready (max 1.5s)
            const firstImgs = matched().slice(0, PAGE_SIZE).map((c) => $('img', c)).filter(Boolean);
            whenImages(firstImgs, 1500).then(function () {
                grid.classList.remove('is-loading');
                if (els.skel) els.skel.remove();
            });

            if (els.price) els.price.addEventListener('change', function () { readPrice(); onFilterChange(); });

            if (els.dest) {
                let t;
                els.dest.addEventListener('input', function () {
                    clearTimeout(t);
                    t = setTimeout(function () {
                        state.dest = els.dest.value.trim().toLowerCase();
                        onFilterChange();
                    }, 150);
                });
            }

            if (els.reset) els.reset.addEventListener('click', resetAll);
            if (els.resetEmpty) els.resetEmpty.addEventListener('click', resetAll);
            if (els.btn) els.btn.addEventListener('click', loadMore);

            if (els.chips) {
                els.chips.addEventListener('click', function (e) {
                    if (!e.target.closest('[data-clear="budget"]')) return;
                    state.max = Infinity;
                    state.maxFromChat = false;
                    onFilterChange();
                });
            }
        }

        init();
        return { apply, reset: resetAll };
    })();

    window.TravelListing = Listing;

    /* =========================================================
       2. CHATBOT
    ========================================================= */
    (function () {
        const toggle = $('#chatbotToggle');
        const win = $('#chatbotWindow');
        if (!toggle || !win) return;

        const closeBtn = $('#chatbotClose');
        const clearBtn = $('#chatbotClear');
        const input = $('#chatbotInput');
        const sendBtn = $('#chatbotSend');
        const box = $('#chatbotMessages');
        const badge = $('#chatbotBadge');
        const nudge = $('#chatbotNudge');
        const nudgeClose = $('#chatbotNudgeClose');
        const welcome = $('#chatbotWelcome');

        let ctx = store.get('chat_ctx', {});          // remembered: budget, dest, destLabel, days
        let history = store.get('chat_history', []);  // [{role, html}]
        let queue = Promise.resolve();
        let ready = false;

        /* ---------- WhatsApp hand-off ---------- */
        const waUrl = (text) => 'https://wa.me/' + APP.whatsapp + '?text=' + encodeURIComponent(text);

        function ctxSummary() {
            const p = [];
            if (ctx.destLabel) p.push('destination: ' + ctx.destLabel);
            if (ctx.days) p.push(ctx.days + ' days');
            if (ctx.budget) p.push('budget up to ' + money(ctx.budget));
            return p.join(', ');
        }

        function waLink(label, message) {
            const s = ctxSummary();
            const msg = s ? message + ' (' + s + ')' : message;
            return '<a class="chat-whatsapp-link" href="' + esc(waUrl(msg)) + '" target="_blank" rel="noopener">' +
                   '<i class="fab fa-whatsapp"></i> ' + esc(label) + '</a>';
        }

        /* ---------- building blocks for replies ---------- */
        const chip = (label, q) => '<button type="button" data-question="' + esc(q) + '">' + esc(label) + '</button>';
        const act = (label, action) => '<button type="button" data-action="' + action + '">' + esc(label) + '</button>';
        const chips = (arr) => '<div class="quick-replies">' + arr.join('') + '</div>';

        function pkgCards(list) {
            return '<div class="chat-pkgs">' + list.map((p) =>
                '<a class="chat-pkg" href="' + esc(p.url) + '">' +
                    '<span class="chat-pkg-title">' + esc(p.title) + '</span>' +
                    '<span class="chat-pkg-meta"><i class="fas fa-map-marker-alt"></i> ' + esc(p.destination) + ' &middot; ' + p.days + ' days</span>' +
                    '<span class="chat-pkg-price">' + esc(p.priceLabel) + '</span>' +
                '</a>'
            ).join('') + '</div>';
        }

        function destinationChips(limit) {
            const seen = [];
            PKGS.forEach((p) => { if (seen.indexOf(p.destination) === -1) seen.push(p.destination); });
            return seen.slice(0, limit).map((d) => chip('📍 ' + d, 'Packages in ' + d));
        }

        function budgetChip() {
            if (!PKGS.length) return [];
            const prices = PKGS.map((p) => p.price).sort((a, b) => a - b);
            const median = prices[Math.floor(prices.length / 2)];
            const step = median >= 20000 ? 5000 : 2500;
            const mid = Math.ceil(median / step) * step;
            return [chip('💰 Under ' + money(mid), 'Packages under ' + mid)];
        }

        /* ---------- parsing the visitor's message ---------- */
        function normalize(t) {
            return t.toLowerCase()
                .replace(/(\d),(\d)/g, '$1$2')
                .replace(/₹/g, ' ')
                .replace(/\b(rs\.?|inr)\b/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function toNumber(v, unit) {
            const n = parseFloat(v);
            if (!unit) return n;
            if (unit === 'k' || unit === 'thousand') return n * 1000;
            return n * 100000; // lakh / lac / l
        }

        function parseBudget(q) {
            let m = q.match(/(?:under|below|within|less than|upto|up to|max(?:imum)?|budget(?: of| is)?|around|about|cheaper than|not more than)\s*(\d+(?:\.\d+)?)\s*(k|thousand|lakhs?|lacs?|l)?\b/);
            if (!m) m = q.match(/\b(\d+(?:\.\d+)?)\s*(k|thousand|lakhs?|lacs?)\b/);
            if (!m) m = q.match(/\b(\d{4,7})\b/);
            if (!m) return null;
            const n = toNumber(m[1], m[2] && m[2][0] === 'l' && m[2].length > 1 ? 'lakh' : m[2]);
            return n >= 1000 ? n : null;
        }

        function parseDays(q) {
            const m = q.match(/\b(\d{1,2})\s*-?\s*(day|days|d|night|nights|n)\b/);
            if (m) {
                let n = parseInt(m[1], 10);
                if (m[2][0] === 'n') n += 1;
                return n >= 1 && n <= 60 ? n : null;
            }
            if (/\bweekend\b/.test(q)) return 3;
            if (/\b(a|one|1)\s+week\b/.test(q)) return 7;
            return null;
        }

        function parseDest(q) {
            let best = null;
            PKGS.forEach((p) => {
                const full = p.destination.toLowerCase();
                const names = [full].concat(full.split(/[,/&|\-]|\band\b/)).map((s) => s.trim()).filter((s) => s.length >= 3);
                names.forEach((n) => {
                    if (new RegExp('\\b' + regexEscape(n) + '\\b').test(q) && (!best || n.length > best.key.length)) {
                        best = { key: n, label: titleCase(n) };
                    }
                });
            });
            return best;
        }

        /* ---------- searching packages ---------- */
        const destMatch = (p, key) => p.destination.toLowerCase().includes(key) || p.title.toLowerCase().includes(key);

        function findPackages(c) {
            return PKGS.filter((p) =>
                (!c.budget || p.price <= c.budget) &&
                (!c.dest || destMatch(p, c.dest)) &&
                (!c.days || Math.abs(p.days - c.days) <= 1)
            ).sort((a, b) => a.price - b.price);
        }

        function describe(c) {
            const parts = [];
            if (c.destLabel) parts.push('in <strong>' + esc(c.destLabel) + '</strong>');
            if (c.days) parts.push('around <strong>' + c.days + ' days</strong>');
            if (c.budget) parts.push('up to <strong>' + money(c.budget) + '</strong>');
            return parts.join(', ');
        }

        function noPackagesYet() {
            return 'We\'re adding new itineraries right now. In the meantime our team can plan a trip for you.<br>' +
                   waLink('Ask our team', 'Hello! I would like help planning a trip.');
        }

        function handleSearch(found) {
            ctx = Object.assign({}, ctx, found);
            store.set('chat_ctx', ctx);

            if (!PKGS.length) return noPackagesYet();

            const list = findPackages(ctx);
            const desc = describe(ctx);

            if (list.length) {
                const more = list.length > 3
                    ? '<span class="chat-note">+ ' + (list.length - 3) + ' more. Tap “Show on page” to see them all.</span>'
                    : '';
                return (desc
                        ? 'I found <strong>' + plural(list.length, 'package') + '</strong> ' + desc + ':'
                        : 'Here are some packages you might like:') +
                    pkgCards(list.slice(0, 3)) + more +
                    chips([
                        act('🔎 Show on page', 'apply'),
                        chip('👨‍💼 Talk to an expert', 'I want to talk to a travel expert'),
                        act('↺ Start over', 'reset')
                    ]);
            }

            // Nothing matched: be honest, then offer the closest options
            const byDest = ctx.dest ? PKGS.filter((p) => destMatch(p, ctx.dest)) : [];

            if (ctx.dest && !byDest.length) {
                return 'I don\'t see a listed package for <strong>' + esc(ctx.destLabel) + '</strong> yet, but our team may still be able to plan it.<br>' +
                    waLink('Ask about ' + ctx.destLabel, 'Hello! I am interested in a trip to ' + ctx.destLabel + '.') +
                    chips(destinationChips(3).concat([act('↺ Start over', 'reset')]));
            }

            const pool = byDest.length ? byDest : PKGS;
            const near = pool.slice().sort((a, b) =>
                ctx.budget ? Math.abs(a.price - ctx.budget) - Math.abs(b.price - ctx.budget) : a.price - b.price
            ).slice(0, 3);

            return 'Nothing matches ' + desc + ' exactly. Here are the closest options' +
                (byDest.length ? ' in <strong>' + esc(ctx.destLabel) + '</strong>' : '') + ':' +
                pkgCards(near) +
                chips([
                    act('↺ Start over', 'reset'),
                    chip('👨‍💼 Talk to an expert', 'I want to talk to a travel expert')
                ]);
        }

        /* ---------- intents ---------- */
        const RX = {
            reset: /\b(start over|reset|clear|new search|forget)\b/,
            thanks: /\b(thanks|thank you|thx|awesome|great|perfect)\b/,
            bye: /\b(bye|goodbye|see you|later)\b/,
            greet: /^(hi+|hello|hey+|namaste|good (morning|afternoon|evening))\b/,
            expert: /\b(expert|agent|human|representative|call me|callback|call back|contact|phone|talk to|speak|whatsapp|team)\b/,
            booking: /\b(book|booking|payment|pay|advance|refund|cancel|cancellation|deposit|emi)\b/,
            custom: /\b(custom|customi[sz]e|personali[sz]e|tailor|modify|own plan|private)\b/,
            inclusions: /\b(include|included|inclusion|inclusions|exclusion|hotel|hotels|stay|meal|meals|food|transport|cab|flight|flights|visa|insurance)\b/,
            destinations: /\b(destination|destinations|where|places|locations|cover)\b/,
            cheap: /\b(cheap|cheapest|affordable|budget|lowest|economical|low cost|inexpensive|price|prices|pricing|cost)\b/,
            premium: /\b(luxury|premium|expensive|costliest|high end|best)\b/,
            packages: /\b(package|packages|itinerary|itineraries|trip|trips|tour|tours|suggest|recommend|show|holiday|vacation)\b/
        };

        function respond(raw) {
            const q = normalize(raw);
            const found = {};
            const budget = parseBudget(q);
            const days = parseDays(q);
            const dest = parseDest(q);
            if (budget) found.budget = budget;
            if (days) found.days = days;
            if (dest) { found.dest = dest.key; found.destLabel = dest.label; }
            const hasSlots = Object.keys(found).length > 0;
            const words = q.split(' ').length;

            if (RX.reset.test(q)) {
                ctx = {};
                store.set('chat_ctx', ctx);
                return 'Done, I\'ve cleared your preferences. What kind of trip are you looking for?' +
                    chips(budgetChip().concat(destinationChips(2)));
            }

            if (!hasSlots && words <= 6 && RX.greet.test(q)) {
                return 'Hi there! 👋 Tell me a destination, budget or trip length and I\'ll suggest packages.' +
                    chips([chip('💰 Affordable packages', 'Show me affordable packages'), chip('📍 Destinations', 'Which destinations do you cover?'), chip('✨ Customize my trip', 'How can I customize my trip?')]);
            }

            if (!hasSlots && RX.thanks.test(q)) {
                return 'You\'re welcome! 😊 Anything else I can help you with?' +
                    chips([act('🔎 Show on page', 'apply'), chip('👨‍💼 Talk to an expert', 'I want to talk to a travel expert')]);
            }

            if (!hasSlots && RX.bye.test(q)) {
                return 'Happy travels! 🌏 I\'m here whenever you need me.';
            }

            if (RX.expert.test(q)) {
                return '👨‍💼 Sure, our travel team can help you directly. Continue on WhatsApp' +
                    (ctxSummary() ? ' and I\'ll include what you told me so far.' : '.') + '<br><br>' +
                    waLink('Chat with a travel expert', 'Hello! I would like to speak with a travel expert about a trip.');
            }

            if (RX.booking.test(q)) {
                return '🧾 Booking, payment and cancellation terms depend on the package and travel dates, so our team confirms them in writing before you pay.' +
                    '<br><br>' + waLink('Ask about booking', 'Hello! I have a question about booking and payment.') +
                    chips([chip('📍 Destinations', 'Which destinations do you cover?')]);
            }

            if (RX.custom.test(q)) {
                return '✨ Yes, trips can be customized. Share these with our team:' +
                    '<br>• Destination<br>• Travel dates<br>• Number of travellers<br>• Approximate budget<br>• Preferred trip duration' +
                    '<br><br>' + waLink('Customize on WhatsApp', 'Hello! I want to create a customized travel itinerary.');
            }

            if (RX.inclusions.test(q) && !hasSlots) {
                return '🏨 Every package page lists its highlights and day-wise plan. Exact inclusions such as hotel category, meals and transfers can vary by package, so it\'s best to confirm them with our team.' +
                    '<br><br>' + waLink('Confirm inclusions', 'Hello! I would like to confirm what is included in a package.') +
                    chips([act('🔎 Show on page', 'apply')]);
            }

            if (RX.destinations.test(q) && !hasSlots) {
                if (!PKGS.length) return noPackagesYet();
                const map = {};
                PKGS.forEach((p) => { map[p.destination] = (map[p.destination] || 0) + 1; });
                const list = Object.keys(map).map((k) => '<strong>' + esc(k) + '</strong> (' + map[k] + ')').join(', ');
                return '📍 We currently have packages for ' + list + '.' + chips(destinationChips(6));
            }

            if (hasSlots) return handleSearch(found);

            if (RX.premium.test(q) && PKGS.length) {
                const top = PKGS.slice().sort((a, b) => b.price - a.price).slice(0, 3);
                return '🌟 Here are our premium packages:' + pkgCards(top) +
                    chips([chip('💰 Affordable packages', 'Show me affordable packages'), act('🔎 Show on page', 'apply')]);
            }

            if (RX.cheap.test(q) && PKGS.length) {
                const low = PKGS.slice().sort((a, b) => a.price - b.price).slice(0, 3);
                return '💰 Our most affordable packages start at <strong>' + esc(low[0].priceLabel) + '</strong>:' + pkgCards(low) +
                    chips(budgetChip().concat(destinationChips(2)));
            }

            if (RX.packages.test(q)) {
                return handleSearch({});
            }

            // Fallback
            return '🤔 I\'m not sure I understood that. Try something like <em>“Goa under 15k”</em> or <em>“5 day trip”</em>, or send your question to our team.' +
                '<br><br>' + waLink('Send my question on WhatsApp', raw) +
                chips([chip('💰 Affordable packages', 'Show me affordable packages'), chip('📍 Destinations', 'Which destinations do you cover?')]);
        }

        /* ---------- rendering ---------- */
        function scrollDown() { box.scrollTop = box.scrollHeight; }

        function bubble(role, html) {
            const w = document.createElement('div');
            if (role === 'user') {
                w.className = 'user-message';
                w.innerHTML = '<div class="message-content">' + html + '</div>';
            } else {
                w.className = 'bot-message';
                w.innerHTML = '<div class="message-avatar"><i class="fas fa-robot"></i></div>' +
                              '<div class="message-content">' + html + '</div>';
            }
            box.appendChild(w);
            scrollDown();
            return w;
        }

        function remember(role, html) {
            history.push({ role: role, html: html });
            history = history.slice(-30);
            store.set('chat_history', history);
        }

        function showTyping() {
            const t = document.createElement('div');
            t.className = 'bot-message typing-message';
            t.id = 'typingIndicator';
            t.innerHTML = '<div class="message-avatar"><i class="fas fa-robot"></i></div>' +
                          '<div class="typing-dots"><span></span><span></span><span></span></div>';
            box.appendChild(t);
            scrollDown();
        }

        function hideTyping() {
            const t = $('#typingIndicator');
            if (t) t.remove();
        }

        function botSay(html) {
            showTyping();
            const delay = Math.min(450 + html.length * 1.2, 1300);
            return new Promise((resolve) => {
                setTimeout(function () {
                    hideTyping();
                    bubble('bot', html);
                    remember('bot', html);
                    resolve();
                }, delay);
            });
        }

        function sendMessage(text) {
            text = (text || '').trim();
            if (!text) return;

            const safe = esc(text);
            bubble('user', safe);
            remember('user', safe);
            input.value = '';

            const answer = respond(text);          // evaluated in order, so context stays consistent
            queue = queue.then(() => botSay(answer));
        }

        /* ---------- actions triggered from chips ---------- */
        function applyToPage() {
            if (!window.TravelListing) {
                queue = queue.then(() => botSay('The package list isn\'t available right now.'));
                return;
            }

            const parts = [];
            if (ctx.destLabel) parts.push(esc(ctx.destLabel));
            if (ctx.budget) parts.push('up to ' + money(ctx.budget));
            const what = parts.length ? ' (' + parts.join(', ') + ')' : '';

            queue = queue.then(() => botSay(
                '✅ I\'ve filtered the list on the page' + what + '. You can clear it any time from the filter bar.'
            )).then(function () {
                window.TravelListing.apply({ max: ctx.budget || 0, dest: ctx.dest || '' });
                const target = $('#itineraries');
                if (target) target.scrollIntoView({ behavior: 'smooth' });
                if (window.innerWidth < 640) setTimeout(closeChat, 500);
            });
        }

        function restart() {
            ctx = {};
            history = [];
            store.set('chat_ctx', ctx);
            store.set('chat_history', history);
            $$('.bot-message, .user-message', box).forEach((n) => { if (n !== welcome) n.remove(); });
            if (welcome) $$('.quick-replies button', welcome).forEach((b) => b.classList.remove('is-used'));
            scrollDown();
        }

        /* ---------- open / close ---------- */
        function hideBadge() {
            if (badge) badge.hidden = true;
            store.set('chat_seen', true);
        }

        function openChat() {
            win.classList.add('open');
            win.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            if (nudge) nudge.hidden = true;
            hideBadge();

            // First open: restore the earlier conversation from this session
            if (!ready) {
                ready = true;
                history.forEach((m) => bubble(m.role, m.html));
            }

            setTimeout(() => input && input.focus(), 200);
        }

        function closeChat() {
            win.classList.remove('open');
            win.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', () => (win.classList.contains('open') ? closeChat() : openChat()));
        if (closeBtn) closeBtn.addEventListener('click', () => { closeChat(); toggle.focus(); });
        if (clearBtn) clearBtn.addEventListener('click', restart);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && win.classList.contains('open')) {
                closeChat();
                toggle.focus();
            }
        });

        if (sendBtn) sendBtn.addEventListener('click', () => sendMessage(input.value));

        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessage(input.value);
                }
            });
        }

        // One handler for every chip / quick reply, including old ones
        box.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-question], button[data-action]');
            if (!btn) return;

            if (btn.dataset.action === 'apply') return applyToPage();
            if (btn.dataset.action === 'reset') return sendMessage('start over');
            if (btn.dataset.question) sendMessage(btn.dataset.question);
        });

        /* ---------- badge + one-time nudge ---------- */
        if (store.get('chat_seen', false) && badge) badge.hidden = true;

        // Open from the "Ask" hooks elsewhere on the page, if any
        $$('[data-open-chat]').forEach((el) => el.addEventListener('click', openChat));

        if (nudge && !store.get('nudge_seen', false)) {
            setTimeout(function () {
                if (win.classList.contains('open')) return;
                nudge.hidden = false;
                store.set('nudge_seen', true);
                setTimeout(() => { nudge.hidden = true; }, 12000);
            }, 15000);

            nudge.addEventListener('click', function (e) {
                if (e.target.closest('#chatbotNudgeClose')) nudge.hidden = true;
                else openChat();
            });
        }
    })();

})();