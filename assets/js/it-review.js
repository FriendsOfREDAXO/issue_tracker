/**
 * Issue Tracker – Review im Frontend: Web Component <it-review-bar>
 *
 * Bar (oben/unten zentriert oder frei verschoben) mit „Seite kommentieren“, „Element wählen“ und „Anmerkungen“;
 * Sidebar (Overlay rechts, mobil als Blatt von unten) mit Liste, Diskussion und Formular; nummerierte Marker an
 * den kommentierten Elementen. Alles im Shadow DOM – Stile der Website wirken nicht hinein und nicht heraus.
 *
 * Anker einer Anmerkung: Slice (unsichtbare Marker <!--it-slice:ID--> der Ausgabe), CSS-Pfad, Textausschnitt und
 * Position im Element. Fehlt das Element später, sucht die Leiste über den Text, dann den Slice, sonst gilt die
 * Anmerkung für die Seite.
 */
(function () {
    'use strict';

    var configEl = document.getElementById('it-review-config');
    if (!configEl || !window.customElements || customElements.get('it-review-bar')) {
        return;
    }
    var C;
    try {
        C = JSON.parse(configEl.textContent || '{}');
    } catch (e) {
        return;
    }
    var T = C.t || {};
    var STORE = 'it-review:';

    function t(key, a) {
        var s = T[key] || key;
        return a === undefined ? s : s.replace('{0}', a);
    }
    function store(key, value) {
        try {
            if (value === undefined) {
                return JSON.parse(localStorage.getItem(STORE + key) || 'null');
            }
            localStorage.setItem(STORE + key, JSON.stringify(value));
        } catch (e) { /* privater Modus */ }
        return null;
    }
    function h(tag, attrs, children) {
        var el = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k];
            if (v === null || v === undefined || v === false) { return; }
            if (k === 'text') { el.textContent = v; } else if (k.slice(0, 2) === 'on') { el.addEventListener(k.slice(2), v); } else { el.setAttribute(k, v === true ? '' : v); }
        });
        (children || []).forEach(function (c) { if (c) { el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
        return el;
    }
    function icon(name) {
        var paths = {
            comment: 'M4 5h16v11H8l-4 4z',
            target: 'M12 3v4M12 17v4M3 12h4M17 12h4M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z',
            list: 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
            grip: 'M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01',
            more: 'M5 12h.01M12 12h.01M19 12h.01',
            close: 'M6 6l12 12M18 6L6 18',
            back: 'M15 6l-6 6 6 6',
            send: 'M4 12l16-8-6 16-3-6z',
            eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
            external: 'M14 4h6v6M20 4l-9 9M18 14v6H4V6h6'
        };
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('d', paths[name] || '');
        svg.appendChild(p);
        return svg;
    }
    function fmtDate(iso) {
        if (!iso) { return ''; }
        try {
            return new Date(iso).toLocaleString(C.lang === 'de' ? 'de-DE' : 'en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        } catch (e) { return iso; }
    }
    /** Schlüssel der Seite (wie ReviewService::normalizePath): Pfad, ohne URL-Rewriting plus article_id und clang */
    function normPath(p) {
        try { p = decodeURIComponent(p); } catch (e) { /* unverändert */ }
        p = '/' + p.replace(/^\/+/, '');
        return p !== '/' ? p.replace(/\/+$/, '') : p;
    }
    function pageKey(loc) {
        var keep = [];
        var params = new URLSearchParams(loc.search);
        ['article_id', 'clang'].forEach(function (name) {
            var v = params.get(name);
            if (v !== null && v !== '') { keep.push(name + '=' + (parseInt(v, 10) || 0)); }
        });
        return normPath(loc.pathname) + (keep.length ? '?' + keep.join('&') : '');
    }
    function clean(s) {
        return (s || '').replace(/\s+/g, ' ').trim();
    }

    // ------------------------------------------------------------------------------------------
    // Slices und Anker
    // ------------------------------------------------------------------------------------------

    /** Slice-ID => Elemente zwischen <!--it-slice:ID--> und <!--/it-slice--> */
    function sliceMap() {
        var map = {};
        var stack = [];
        var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT);
        var node;
        while ((node = walker.nextNode())) {
            var v = node.nodeValue || '';
            var m = /^it-slice:(\d+)$/.exec(v);
            if (m) {
                stack.push({ id: m[1], start: node });
            } else if (v === '/it-slice' && stack.length) {
                var s = stack.pop();
                var els = [];
                for (var n = s.start.nextSibling; n && n !== node; n = n.nextSibling) {
                    if (n.nodeType === 1) { els.push(n); }
                }
                // Marker in verschiedenen Ebenen (Modul öffnet und schließt Elemente selbst): Eltern des Starts
                if (!els.length && s.start.parentElement) { els.push(s.start.parentElement); }
                map[s.id] = (map[s.id] || []).concat(els);
            }
        }
        return map;
    }
    function sliceOf(el, map) {
        var best = 0;
        var bestDepth = -1;
        Object.keys(map).forEach(function (id) {
            map[id].forEach(function (root) {
                if (root === el || root.contains(el)) {
                    var depth = 0;
                    for (var n = root; n; n = n.parentElement) { depth++; }
                    if (depth > bestDepth) { best = +id; bestDepth = depth; }
                }
            });
        });
        return best;
    }
    function isOwn(el) {
        return !el || el === host || (el.closest && el.closest('it-review-bar'));
    }
    function cssPath(el) {
        var parts = [];
        for (var n = el; n && n.nodeType === 1 && n !== document.body && n !== document.documentElement; n = n.parentElement) {
            if (n.id && /^[A-Za-z][\w-]*$/.test(n.id) && document.querySelectorAll('#' + n.id).length === 1) {
                parts.unshift('#' + n.id);
                return parts.join(' > ');
            }
            var tag = n.tagName.toLowerCase();
            var i = 1;
            for (var s = n.previousElementSibling; s; s = s.previousElementSibling) {
                if (s.tagName === n.tagName) { i++; }
            }
            parts.unshift(tag + ':nth-of-type(' + i + ')');
        }
        parts.unshift('body');
        return parts.join(' > ');
    }
    function quoteOf(el) {
        if (el.tagName === 'IMG') { return clean(el.getAttribute('alt') || (el.getAttribute('src') || '').split('/').pop()); }
        return clean(el.innerText || el.textContent || '').slice(0, 120);
    }
    function labelOf(el) {
        var tag = el.tagName.toLowerCase();
        var names = C.lang === 'de'
            ? { h1: 'Überschrift', h2: 'Überschrift', h3: 'Überschrift', h4: 'Überschrift', p: 'Absatz', img: 'Bild', picture: 'Bild', figure: 'Bild', a: 'Link', button: 'Button', ul: 'Liste', ol: 'Liste', li: 'Listenpunkt', table: 'Tabelle', form: 'Formular', nav: 'Navigation', header: 'Kopfbereich', footer: 'Fußbereich', section: 'Abschnitt', video: 'Video', iframe: 'Einbettung' }
            : { h1: 'Heading', h2: 'Heading', h3: 'Heading', h4: 'Heading', p: 'Paragraph', img: 'Image', picture: 'Image', figure: 'Image', a: 'Link', button: 'Button', ul: 'List', ol: 'List', li: 'List item', table: 'Table', form: 'Form', nav: 'Navigation', header: 'Header', footer: 'Footer', section: 'Section', video: 'Video', iframe: 'Embed' };
        var q = quoteOf(el);
        var name = names[tag] || (C.lang === 'de' ? 'Element' : 'Element');
        var quote = C.lang === 'de' ? ['„', '“'] : ['“', '”'];
        return q ? name + ' ' + quote[0] + (q.length > 40 ? q.slice(0, 38) + '…' : q) + quote[1] : name;
    }
    function resolve(anchor, map) {
        var roots = anchor.slice && map[anchor.slice] ? map[anchor.slice] : null;
        var el = null;
        if (anchor.selector) {
            try { el = document.querySelector(anchor.selector); } catch (e) { el = null; }
            if (el && anchor.quote && quoteOf(el).slice(0, 30) !== anchor.quote.slice(0, 30)) { el = null; }
            if (el && roots && !roots.some(function (r) { return r === el || r.contains(el); })) { el = null; }
        }
        if (!el && anchor.quote) {
            var scope = roots || [document.body];
            var best = null;
            scope.forEach(function (root) {
                [root].concat(Array.prototype.slice.call(root.querySelectorAll('*'))).forEach(function (cand) {
                    if (isOwn(cand) || !cand.getClientRects().length) { return; }
                    if (quoteOf(cand).indexOf(anchor.quote.slice(0, 60)) === 0 && (!best || best.contains(cand))) { best = cand; }
                });
            });
            el = best;
        }
        if (!el && roots) { el = roots[0]; }
        return el;
    }

    // ------------------------------------------------------------------------------------------
    // Web Component
    // ------------------------------------------------------------------------------------------

    var host;

    function ReviewBar() {
        return Reflect.construct(HTMLElement, [], ReviewBar);
    }
    ReviewBar.prototype = Object.create(HTMLElement.prototype);
    ReviewBar.prototype.constructor = ReviewBar;
    Object.setPrototypeOf(ReviewBar, HTMLElement);

    ReviewBar.prototype.connectedCallback = function () {
        if (this._ready) { return; }
        this._ready = true;
        host = this;
        var self = this;
        var root = this.attachShadow({ mode: 'open' });
        this.state = {
            items: [], all: null, tab: 'page', view: 'list', current: null, picking: false, target: null, draft: null,
            round: store('round') || (C.rounds[0] && C.rounds[0].id),
            pos: store('pos') || { dock: 'bottom' },
            hidden: !!store('hidden')
        };
        if (!C.rounds.some(function (r) { return r.id === self.state.round; })) { this.state.round = C.rounds[0].id; }

        root.appendChild(h('link', { rel: 'stylesheet', href: C.css }));
        this.live = h('div', { class: 'sr', role: 'status', 'aria-live': 'polite' });
        this.markers = h('div', { class: 'markers', 'aria-label': t('markers') });
        this.hover = h('div', { class: 'hover', hidden: true }, [h('span', { class: 'hover-label' })]);
        this.flash = h('div', { class: 'flash', hidden: true });
        this.bar = this.buildBar();
        this.tab = h('button', { type: 'button', class: 'tab', hidden: true, onclick: function () { self.setHidden(false); } }, [icon('comment'), h('span', { text: t('show_bar') })]);
        this.drawer = h('aside', { class: 'drawer', role: 'dialog', 'aria-modal': 'false', 'aria-label': t('list'), hidden: true });
        root.appendChild(this.markers);
        root.appendChild(this.hover);
        root.appendChild(this.flash);
        root.appendChild(this.bar);
        root.appendChild(this.tab);
        root.appendChild(this.drawer);
        root.appendChild(this.live);

        this.setHidden(this.state.hidden);
        this.place();
        this.bindPick();
        this.load().then(function () {
            var m = /^#it-review-(\d+)$/.exec(location.hash);
            if (m) { self.openItem(+m[1], true); }
        });

        var raf = 0;
        var reposition = function () {
            if (raf) { return; }
            raf = requestAnimationFrame(function () { raf = 0; self.drawMarkers(); if (self.state.target) { self.outline(self.hover, self.state.target, true); } });
        };
        window.addEventListener('scroll', reposition, { passive: true });
        window.addEventListener('resize', function () { reposition(); self.place(); });
        if (window.ResizeObserver) { new ResizeObserver(reposition).observe(document.body); }
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(reposition); }
        window.addEventListener('load', reposition);
        window.addEventListener('hashchange', function () {
            var m = /^#it-review-(\d+)$/.exec(location.hash);
            if (m) { self.openItem(+m[1], true); }
        });
    };

    // ---------------- Bar ----------------

    ReviewBar.prototype.buildBar = function () {
        var self = this;
        var grip = h('button', { type: 'button', class: 'grip', title: t('move'), 'aria-label': t('move') }, [icon('grip')]);
        this.countEl = h('span', { class: 'count', text: '0' });
        this.pickBtn = h('button', { type: 'button', class: 'btn', 'aria-pressed': 'false', onclick: function () { self.togglePick(); } }, [icon('target'), h('span', { class: 'lbl', text: t('pick') })]);
        var menuBtn = h('button', { type: 'button', class: 'icon-btn', 'aria-haspopup': 'true', 'aria-expanded': 'false', title: '…', 'aria-label': t('bar_label') }, [icon('more')]);
        var menuItems = [
            h('button', { type: 'button', role: 'menuitem', text: t('dock_top'), onclick: function () { self.dock('top'); } }),
            h('button', { type: 'button', role: 'menuitem', text: t('dock_bottom'), onclick: function () { self.dock('bottom'); } }),
            h('button', { type: 'button', role: 'menuitem', text: t('hide'), onclick: function () { self.setHidden(true); } })
        ];
        // Review-Modus ganz beenden (Gast: abmelden; Backend: aus, bis „Im Frontend öffnen“ im Issue Tracker)
        menuItems.push(h('button', { type: 'button', role: 'menuitem', class: 'danger', text: t('end'), onclick: function () {
            if (!window.confirm(t(C.me.guest ? 'end_confirm_guest' : 'end_confirm_user'))) { return; }
            self.post('end', {}).then(function () {
                location.replace(location.href.replace(/#.*$/, ''));
            }).catch(function (err) { self.say(err.message); });
        } }));
        var menu = h('div', { class: 'menu', role: 'menu', hidden: true }, menuItems);
        menuBtn.addEventListener('click', function () {
            var open = menu.hidden;
            menu.hidden = !open;
            menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { menuItems[0].focus(); }
        });
        menu.addEventListener('click', function () { menu.hidden = true; menuBtn.setAttribute('aria-expanded', 'false'); });
        menu.addEventListener('keydown', function (e) { if (e.key === 'Escape') { menu.hidden = true; menuBtn.focus(); } });

        var round = null;
        if (C.rounds.length > 1) {
            round = h('select', { class: 'round', 'aria-label': t('round'), onchange: function (e) { self.state.round = +e.target.value; store('round', self.state.round); self.load(); } },
                C.rounds.map(function (r) { return h('option', { value: r.id, selected: r.id === self.state.round, text: r.title }); }));
        }
        this.hint = h('div', { class: 'hint', hidden: true }, [
            h('span', { text: t('pick_hint') }),
            h('button', { type: 'button', class: 'link', text: t('pick_cancel'), onclick: function () { self.togglePick(false); } })
        ]);
        var bar = h('div', { class: 'bar', role: 'toolbar', 'aria-label': t('bar_label') }, [
            grip,
            h('span', { class: 'title' }, [h('strong', { text: 'Review' }), C.rounds.length === 1 ? h('span', { class: 'sub', text: C.rounds[0].title }) : null]),
            round,
            h('button', { type: 'button', class: 'btn', onclick: function () { self.compose(null); } }, [icon('comment'), h('span', { class: 'lbl', text: t('comment_page') })]),
            this.pickBtn,
            h('button', { type: 'button', class: 'btn', onclick: function () { self.openList(); } }, [icon('list'), h('span', { class: 'lbl', text: t('list') }), this.countEl]),
            h('div', { class: 'more' }, [menuBtn, menu]),
            this.hint
        ]);
        this.dragging(grip, bar);
        return bar;
    };

    ReviewBar.prototype.dragging = function (grip, bar) {
        var self = this;
        var start = null;
        grip.addEventListener('pointerdown', function (e) {
            var r = bar.getBoundingClientRect();
            start = { x: e.clientX - r.left, y: e.clientY - r.top };
            grip.setPointerCapture(e.pointerId);
            bar.classList.add('is-dragging');
        });
        grip.addEventListener('pointermove', function (e) {
            if (!start) { return; }
            self.state.pos = { dock: 'free', x: e.clientX - start.x, y: e.clientY - start.y };
            self.place();
        });
        var end = function () {
            if (!start) { return; }
            start = null;
            bar.classList.remove('is-dragging');
            // nahe am Rand oben/unten: wieder andocken
            var r = bar.getBoundingClientRect();
            if (r.top < 24) { self.state.pos = { dock: 'top' }; } else if (window.innerHeight - r.bottom < 24) { self.state.pos = { dock: 'bottom' }; }
            store('pos', self.state.pos);
            self.place();
        };
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
        // Tastatur: Pfeile verschieben, Pos1/Ende docken
        grip.addEventListener('keydown', function (e) {
            var r = bar.getBoundingClientRect();
            var step = e.shiftKey ? 40 : 10;
            var d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
            if (d) {
                e.preventDefault();
                self.state.pos = { dock: 'free', x: r.left + d[0], y: r.top + d[1] };
            } else if (e.key === 'Home' || e.key === 'End') {
                e.preventDefault();
                self.state.pos = { dock: e.key === 'Home' ? 'top' : 'bottom' };
            } else {
                return;
            }
            store('pos', self.state.pos);
            self.place();
        });
    };

    ReviewBar.prototype.dock = function (where) {
        this.state.pos = { dock: where };
        store('pos', this.state.pos);
        this.place();
    };

    ReviewBar.prototype.place = function () {
        var bar = this.bar;
        var pos = this.state.pos;
        bar.classList.toggle('dock-top', pos.dock === 'top');
        bar.classList.toggle('dock-bottom', pos.dock === 'bottom');
        bar.classList.toggle('dock-free', pos.dock === 'free');
        this.tab.classList.toggle('dock-top', pos.dock === 'top');
        if (pos.dock === 'free') {
            var w = bar.offsetWidth || 320;
            var hgt = bar.offsetHeight || 48;
            bar.style.left = Math.max(8, Math.min(pos.x, window.innerWidth - w - 8)) + 'px';
            bar.style.top = Math.max(8, Math.min(pos.y, window.innerHeight - hgt - 8)) + 'px';
        } else {
            bar.style.left = '';
            bar.style.top = '';
        }
    };

    ReviewBar.prototype.setHidden = function (hidden) {
        this.state.hidden = hidden;
        store('hidden', hidden);
        this.bar.hidden = hidden;
        this.tab.hidden = !hidden;
        this.markers.hidden = hidden;
        if (hidden) { this.closeDrawer(); this.togglePick(false); }
        this.place();
    };

    // ---------------- Daten ----------------

    ReviewBar.prototype.api = function (func, params) {
        var url = C.api + (C.api.indexOf('?') >= 0 ? '&' : '?') + 'func=' + func;
        Object.keys(params || {}).forEach(function (k) { url += '&' + k + '=' + encodeURIComponent(params[k]); });
        return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); });
    };
    ReviewBar.prototype.post = function (func, body) {
        var url = C.api + (C.api.indexOf('?') >= 0 ? '&' : '?') + 'func=' + func;
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-IT-Review': '1' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                if (!r.ok || data.error) { throw new Error(data.error || t('error')); }
                return data;
            });
        });
    };
    ReviewBar.prototype.load = function () {
        var self = this;
        return this.api('list', { path: pageKey(location), round: this.state.round }).then(function (data) {
            self.state.items = data.items || [];
            self.state.all = null;
            self.countEl.textContent = String(self.state.items.filter(function (i) { return !i.done; }).length);
            self.drawMarkers();
            if (!self.drawer.hidden) { self.renderDrawer(); }
        }).catch(function () { self.say(t('error')); });
    };
    ReviewBar.prototype.loadAll = function () {
        var self = this;
        return this.api('list', { all: 1, round: this.state.round }).then(function (data) { self.state.all = data.items || []; });
    };
    /** Fehler im Formular anzeigen (role=alert), nicht als Browser-Dialog */
    ReviewBar.prototype.fail = function (form, msg) {
        var box = form.querySelector('.error');
        if (!box) {
            box = h('p', { class: 'error', role: 'alert' });
            form.insertBefore(box, form.firstChild);
        }
        box.textContent = msg;
    };

    ReviewBar.prototype.say = function (msg) {
        var live = this.live;
        live.textContent = '';
        setTimeout(function () { live.textContent = msg; }, 30);
    };

    // ---------------- Marker ----------------

    ReviewBar.prototype.drawMarkers = function () {
        var self = this;
        var map = sliceMap();
        this.markers.textContent = '';
        var used = [];
        this.state.items.forEach(function (item, index) {
            var el = resolve(item.anchor, map);
            item._el = el;
            if (!el || (!item.anchor.selector && !item.anchor.slice)) { return; }
            var r = el.getBoundingClientRect();
            if (!r.width && !r.height) { return; }
            var x = item.anchor.x !== null ? r.left + r.width * item.anchor.x : r.right;
            var y = item.anchor.y !== null ? r.top + r.height * item.anchor.y : r.top;
            // gleiche Stelle: leicht versetzen
            used.forEach(function (u) { if (Math.abs(u[0] - x) < 20 && Math.abs(u[1] - y) < 20) { x += 22; } });
            used.push([x, y]);
            if (y < -30 || y > window.innerHeight + 30) { return; }
            var m = h('button', {
                type: 'button', class: 'marker' + (item.done ? ' is-done' : '') + ' kind-' + item.kind,
                'aria-label': '#' + (index + 1) + ' ' + item.title, title: item.title,
                onclick: function () { self.openItem(item.id, false); },
                onmouseenter: function () { self.outline(self.flash, el); },
                onmouseleave: function () { self.flash.hidden = true; },
                onfocus: function () { self.outline(self.flash, el); },
                onblur: function () { self.flash.hidden = true; }
            }, [String(index + 1)]);
            m.style.left = Math.round(x - 13) + 'px';
            m.style.top = Math.round(y - 13) + 'px';
            self.markers.appendChild(m);
        });
    };

    ReviewBar.prototype.outline = function (box, el, withLabel) {
        if (!el) { box.hidden = true; return; }
        var r = el.getBoundingClientRect();
        box.hidden = false;
        box.style.left = (r.left - 3) + 'px';
        box.style.top = (r.top - 3) + 'px';
        box.style.width = (r.width + 6) + 'px';
        box.style.height = (r.height + 6) + 'px';
        var label = box.querySelector('.hover-label');
        if (label) { label.textContent = withLabel ? labelOf(el) : ''; }
    };

    // ---------------- Element wählen ----------------

    ReviewBar.prototype.bindPick = function () {
        var self = this;
        var current = null;
        var move = function (e) {
            if (!self.state.picking) { return; }
            var el = document.elementFromPoint(e.clientX, e.clientY);
            if (isOwn(el) || el === document.body || el === document.documentElement) { self.hover.hidden = true; current = null; return; }
            current = el;
            self.outline(self.hover, el, true);
        };
        var click = function (e) {
            if (!self.state.picking) { return; }
            var path = e.composedPath ? e.composedPath() : [];
            if (path.indexOf(host) >= 0) { return; }
            e.preventDefault();
            e.stopPropagation();
            var el = current || document.elementFromPoint(e.clientX, e.clientY);
            if (!el || isOwn(el)) { return; }
            var r = el.getBoundingClientRect();
            self.togglePick(false);
            self.compose(el, r.width ? (e.clientX - r.left) / r.width : null, r.height ? (e.clientY - r.top) / r.height : null);
        };
        var key = function (e) {
            if (!self.state.picking) { return; }
            if (e.key === 'Escape') { e.preventDefault(); self.togglePick(false); return; }
            // Pfeil hoch/runter: übergeordnetes / erstes untergeordnetes Element
            if (current && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
                e.preventDefault();
                var next = e.key === 'ArrowUp' ? current.parentElement : current.firstElementChild;
                if (next && next !== document.body && next !== document.documentElement && !isOwn(next)) {
                    current = next;
                    self.outline(self.hover, current, true);
                }
            }
            if (current && e.key === 'Enter') {
                e.preventDefault();
                self.togglePick(false);
                self.compose(current, null, null);
            }
        };
        document.addEventListener('pointermove', move, true);
        document.addEventListener('click', click, true);
        document.addEventListener('keydown', key, true);
    };

    ReviewBar.prototype.togglePick = function (on) {
        var picking = on === undefined ? !this.state.picking : on;
        this.state.picking = picking;
        this.pickBtn.setAttribute('aria-pressed', picking ? 'true' : 'false');
        this.hint.hidden = !picking;
        document.documentElement.classList.toggle('it-review-picking', picking);
        document.documentElement.style.cursor = picking ? 'crosshair' : '';
        if (!picking) { this.hover.hidden = true; } else { this.closeDrawer(); this.say(t('pick_hint')); }
    };

    // ---------------- Sidebar ----------------

    ReviewBar.prototype.openDrawer = function () {
        this.drawer.hidden = false;
        this.renderDrawer();
        this.syncBar();
    };
    /** Bar neben die offene Sidebar rücken (Mitte des freien Bereichs) */
    ReviewBar.prototype.syncBar = function () {
        var open = !this.drawer.hidden;
        var left = this.drawer.classList.contains('side-left');
        this.bar.classList.toggle('beside-left', open && left);
        this.bar.classList.toggle('beside-right', open && !left);
    };
    ReviewBar.prototype.closeDrawer = function () {
        this.drawer.hidden = true;
        this.syncBar();
        this.state.target = null;
        this.hover.hidden = true;
        if (this.bar.classList.contains('is-evading')) { this.bar.classList.remove('is-evading'); this.place(); }
        if (this.state.view === 'compose') { this.state.view = 'list'; }
    };
    ReviewBar.prototype.openList = function () {
        this.state.view = 'list';
        this.state.target = null;
        this.hover.hidden = true;
        this.openDrawer();
    };
    ReviewBar.prototype.openItem = function (id, scroll) {
        var self = this;
        var item = this.state.items.filter(function (i) { return i.id === id; })[0];
        var go = function () {
            self.state.view = 'item';
            self.state.current = id;
            self.openDrawer();
            if (item && item._el) {
                if (scroll) { self.show(item); } else { self.reveal(item._el); self.hover.hidden = true; }
            }
        };
        if (!item && this.state.all === null) {
            this.loadAll().then(function () { item = self.state.all.filter(function (i) { return i.id === id; })[0]; if (item) { go(); } });
            return;
        }
        if (!item && this.state.all) { item = this.state.all.filter(function (i) { return i.id === id; })[0]; }
        if (item) { go(); }
    };
    ReviewBar.prototype.show = function (item) {
        var self = this;
        if (!item._el) { return; }
        this.reveal(item._el);
        this.hover.hidden = true;
        setTimeout(function () {
            self.outline(self.flash, item._el);
            self.flash.classList.add('is-pulse');
            setTimeout(function () { self.flash.hidden = true; self.flash.classList.remove('is-pulse'); }, 1800);
        }, 350);
    };

    /**
     * Element frei halten: Sidebar auf die Seite gegenüber dem Element, Element in den freien Bereich scrollen,
     * Bar ausweichen lassen (an den anderen Rand), wenn sie über dem Element liegt.
     */
    ReviewBar.prototype.reveal = function (el) {
        var self = this;
        var mobile = window.matchMedia('(max-width: 720px)').matches;
        var r = el ? el.getBoundingClientRect() : null;
        this.drawer.classList.toggle('side-left', !!r && !mobile && r.left + r.width / 2 > window.innerWidth / 2);
        this.syncBar();
        if (!r) { return; }
        var freeTop = 16;
        var freeBottom = mobile ? window.innerHeight * 0.42 : window.innerHeight - 16;
        var target = null;
        if (r.height > freeBottom - freeTop) {
            target = r.top - freeTop;
        } else if (r.top < freeTop || r.bottom > freeBottom) {
            target = r.top - (freeTop + (freeBottom - freeTop - r.height) / 2);
        }
        var after = function () {
            var rr = el.getBoundingClientRect();
            var b = self.bar.getBoundingClientRect();
            var overlap = !(b.right < rr.left || b.left > rr.right || b.bottom < rr.top || b.top > rr.bottom);
            self.bar.classList.toggle('is-evading', false);
            if (overlap && self.state.pos.dock !== 'free') {
                self.bar.classList.add('is-evading');
                self.bar.classList.toggle('dock-top', self.state.pos.dock !== 'top');
                self.bar.classList.toggle('dock-bottom', self.state.pos.dock === 'top');
            }
            self.outline(self.hover, el, true);
        };
        if (target !== null && Math.abs(target) > 4) {
            window.scrollBy({ top: target, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            setTimeout(after, 400);
        } else {
            after();
        }
    };

    ReviewBar.prototype.drawerHead = function (title, back) {
        var self = this;
        return h('header', { class: 'd-head' }, [
            back ? h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('cancel'), onclick: back }, [icon('back')]) : null,
            h('h2', { text: title, tabindex: '-1' }),
            h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('close'), onclick: function () { self.closeDrawer(); } }, [icon('close')])
        ]);
    };

    ReviewBar.prototype.renderDrawer = function () {
        var view = this.state.view;
        this.drawer.textContent = '';
        if (view === 'compose') { this.renderCompose(); } else if (view === 'item') { this.renderItem(); } else { this.renderList(); }
        var heading = this.drawer.querySelector('h2');
        if (heading && view !== 'compose') { heading.focus({ preventScroll: true }); }
        this.drawer.setAttribute('aria-label', heading ? heading.textContent : t('list'));
    };

    ReviewBar.prototype.renderList = function () {
        var self = this;
        var tabs = h('div', { class: 'tabs', role: 'tablist' }, [
            h('button', { type: 'button', role: 'tab', 'aria-selected': this.state.tab === 'page' ? 'true' : 'false', text: t('list_page'), onclick: function () { self.state.tab = 'page'; self.renderDrawer(); } }),
            h('button', { type: 'button', role: 'tab', 'aria-selected': this.state.tab === 'all' ? 'true' : 'false', text: t('list_all'), onclick: function () { self.state.tab = 'all'; if (self.state.all === null) { self.loadAll().then(function () { self.renderDrawer(); }); } else { self.renderDrawer(); } } })
        ]);
        this.drawer.appendChild(this.drawerHead(t('list')));
        this.drawer.appendChild(tabs);
        var items = this.state.tab === 'all' ? (this.state.all || []) : this.state.items;
        var here = pageKey(location);
        var list = h('ol', { class: 'items' });
        if (!items.length) {
            this.drawer.appendChild(h('p', { class: 'empty', text: this.state.tab === 'all' ? t('empty_all') : t('empty') }));
        }
        items.forEach(function (item) {
            var index = self.state.items.indexOf(self.state.items.filter(function (i) { return i.id === item.id; })[0]);
            var other = item.page.path !== here;
            var replies = item.comments.length;
            list.appendChild(h('li', { class: 'item' + (item.done ? ' is-done' : '') }, [
                h('button', { type: 'button', class: 'item-btn', onclick: function () {
                    if (other) { location.href = (item.page.url || item.page.path).replace(/#.*$/, '') + '#it-review-' + item.id; return; }
                    self.openItem(item.id, true);
                } }, [
                    h('span', { class: 'num kind-' + item.kind, text: other ? '↗' : String(index + 1) }),
                    h('span', { class: 'item-main' }, [
                        h('span', { class: 'item-title', text: item.title }),
                        h('span', { class: 'item-meta' }, [
                            h('span', { class: 'pill kind-' + item.kind, text: C.kinds[item.kind] || item.kind }),
                            h('span', { class: 'pill status-' + item.status, text: item.statusLabel }),
                            h('span', { text: item.author + ' · ' + fmtDate(item.created) + (replies ? ' · ' + replies + ' ↩' : '') }),
                            other ? h('span', { class: 'page', text: t('other_page') + ': ' + (item.page.title || item.page.path) }) : null
                        ])
                    ])
                ])
            ]));
        });
        this.drawer.appendChild(list);
        this.drawer.appendChild(h('div', { class: 'd-foot' }, [
            h('button', { type: 'button', class: 'btn btn-primary', onclick: function () { self.compose(null); } }, [icon('comment'), h('span', { text: t('comment_page') })]),
            h('button', { type: 'button', class: 'btn', onclick: function () { self.togglePick(true); } }, [icon('target'), h('span', { text: t('pick') })])
        ]));
    };

    ReviewBar.prototype.renderItem = function () {
        var self = this;
        var id = this.state.current;
        var pool = this.state.items.concat(this.state.all || []);
        var item = pool.filter(function (i) { return i.id === id; })[0];
        if (!item) { this.state.view = 'list'; this.renderList(); return; }
        var index = this.state.items.indexOf(item);
        this.drawer.appendChild(this.drawerHead((index >= 0 ? '#' + (index + 1) + ' ' : '') + item.title, function () { self.openList(); }));
        var body = h('div', { class: 'd-body' });
        body.appendChild(h('p', { class: 'item-meta' }, [
            h('span', { class: 'pill kind-' + item.kind, text: C.kinds[item.kind] || item.kind }),
            h('span', { class: 'pill status-' + item.status, text: item.statusLabel }),
            item.anchor.gone ? h('span', { class: 'pill warn', text: t('gone') }) : (item.anchor.changed ? h('span', { class: 'pill warn', text: t('changed') }) : null)
        ]));
        if (item.anchor.label) {
            body.appendChild(h('p', { class: 'where' }, [
                h('span', { text: t('element') + ': ' + item.anchor.label }),
                item._el ? h('button', { type: 'button', class: 'link', onclick: function () { self.show(item); } }, [icon('eye'), ' ' + t('show')]) : null
            ]));
        }
        body.appendChild(this.message({ author: item.author, created: item.created, text: item.text, agency: false, own: item.own }));
        var thread = h('ol', { class: 'thread' });
        item.comments.forEach(function (c) {
            thread.appendChild(h('li', { class: c.parent ? 'is-reply' : '' }, [self.message(c)]));
        });
        body.appendChild(thread);
        if (item.backend) {
            body.appendChild(h('p', { class: 'backend' }, [h('a', { href: item.backend, target: '_blank', rel: 'noopener' }, [icon('external'), ' ' + t('open_backend')])]));
        }
        this.drawer.appendChild(body);

        var ta = h('textarea', { id: 'it-reply', rows: '3', maxlength: String(C.maxText), required: true });
        var form = h('form', { class: 'd-foot reply' }, [
            h('label', { for: 'it-reply', text: t('reply') }),
            ta,
            h('button', { type: 'submit', class: 'btn btn-primary' }, [icon('send'), h('span', { text: t('reply_send') })])
        ]);
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!ta.value.trim()) { ta.focus(); return; }
            var btn = form.querySelector('button[type=submit]');
            btn.disabled = true;
            self.post('reply', { issue_id: item.id, text: ta.value }).then(function () {
                self.say(t('sent'));
                return self.load().then(function () { if (self.state.all) { return self.loadAll(); } return null; });
            }).then(function () {
                self.state.view = 'item';
                self.renderDrawer();
            }).catch(function (err) { btn.disabled = false; self.fail(form, err.message); });
        });
        this.drawer.appendChild(form);
    };

    ReviewBar.prototype.message = function (c) {
        return h('article', { class: 'msg' + (c.agency ? ' is-agency' : '') + (c.own ? ' is-own' : '') + (c.solution ? ' is-solution' : '') }, [
            h('header', {}, [
                h('strong', { text: c.author }),
                c.agency && !c.own ? h('span', { class: 'pill', text: t('agency') }) : null,
                c.own ? h('span', { class: 'pill', text: t('you') }) : null,
                h('time', { datetime: c.created, text: fmtDate(c.created) })
            ]),
            h('div', { class: 'msg-text', text: c.text })
        ]);
    };

    // ---------------- Neue Anmerkung ----------------

    ReviewBar.prototype.compose = function (el, x, y) {
        var map = sliceMap();
        this.state.draft = el ? {
            el: el,
            anchor: { slice_id: sliceOf(el, map), selector: cssPath(el), quote: quoteOf(el), label: labelOf(el), x: x, y: y }
        } : { el: null, anchor: {} };
        this.state.target = el;
        this.state.view = 'compose';
        this.openDrawer();
        if (el) { this.reveal(el); } else { this.hover.hidden = true; this.drawer.classList.remove('side-left'); this.syncBar(); }
    };

    ReviewBar.prototype.renderCompose = function () {
        var self = this;
        var draft = this.state.draft || { el: null, anchor: {} };
        this.drawer.appendChild(this.drawerHead(t('new_title'), function () { self.openList(); }));
        var kinds = Object.keys(C.kinds);
        var kindGroup = h('fieldset', { class: 'kinds' }, [h('legend', { text: t('kind') })].concat(kinds.map(function (k, i) {
            return h('label', {}, [h('input', { type: 'radio', name: 'kind', value: k, checked: i === 0 }), h('span', { text: C.kinds[k] })]);
        })));
        var ta = h('textarea', { id: 'it-text', name: 'text', rows: '6', maxlength: String(C.maxText), required: true, 'aria-describedby': 'it-text-hint' });
        var shot = h('input', { type: 'checkbox', name: 'screenshot', checked: true });
        var honey = h('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'hp', 'aria-hidden': 'true' });
        var where = draft.el
            ? h('p', { class: 'where' }, [h('span', { text: t('element') + ': ' + draft.anchor.label }), h('button', { type: 'button', class: 'link', text: t('pick'), onclick: function () { self.togglePick(true); } })])
            : h('p', { class: 'where' }, [h('span', { text: t('page_only') }), h('button', { type: 'button', class: 'link', text: t('pick'), onclick: function () { self.togglePick(true); } })]);
        var form = h('form', { class: 'd-body compose' }, [
            where,
            kindGroup,
            h('label', { for: 'it-text', text: t('text') }),
            ta,
            h('p', { id: 'it-text-hint', class: 'hint-text', text: t('text_hint') }),
            h('label', { class: 'check' }, [shot, h('span', { text: t('screenshot') })]),
            honey,
            h('div', { class: 'actions' }, [
                h('button', { type: 'submit', class: 'btn btn-primary' }, [icon('send'), h('span', { text: t('send') })]),
                h('button', { type: 'button', class: 'btn', text: t('cancel'), onclick: function () { self.openList(); } })
            ])
        ]);
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!ta.value.trim()) { ta.focus(); return; }
            var btn = form.querySelector('button[type=submit]');
            btn.disabled = true;
            var kind = (form.querySelector('input[name=kind]:checked') || {}).value || 'note';
            var payload = {
                round: self.state.round, kind: kind, text: ta.value, website: honey.value,
                path: pageKey(location), url: location.href.replace(/#.*$/, ''), page_title: document.title,
                article_id: C.page.article_id, clang_id: C.page.clang_id,
                anchor: draft.anchor, viewport: window.innerWidth + 'x' + window.innerHeight
            };
            var label = btn.querySelector('span');
            var before = label.textContent;
            if (shot.checked) { label.textContent = t('capturing'); }
            var go = function (screenshot) {
                label.textContent = before;
                payload.screenshot = screenshot || '';
                return self.post('create', payload).then(function (res) {
                    self.say(t('sent'));
                    self.state.draft = null;
                    self.state.target = null;
                    self.hover.hidden = true;
                    return self.load().then(function () { self.state.all = null; self.openItem(res.id, false); });
                });
            };
            (shot.checked ? self.capture(draft.el) : Promise.resolve('')).then(go).catch(function (err) {
                btn.disabled = false;
                self.fail(form, err.message);
            });
        });
        this.drawer.appendChild(form);
        setTimeout(function () { ta.focus(); }, 30);
    };

    /**
     * Bildschirmausschnitt (modern-screenshot, erst bei Bedarf geladen; der Browser rendert selbst, daher stimmt jedes CSS):
     * Umgebung des Elements rendern, Element mit Rand ausschneiden und rot einrahmen; ohne Element der sichtbare Bereich.
     */
    ReviewBar.prototype.capture = function (el) {
        var self = this;
        this.say(t('capturing'));
        var load = window.modernScreenshot ? Promise.resolve() : new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = C.capture;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
        var pad = 60;
        var vw = document.documentElement.clientWidth;
        var minW = Math.min(720, vw);
        var minH = Math.min(360, window.innerHeight);
        // Kontext: kleinster Vorfahre, der das Element mit Rand umschließt (sonst body)
        var ctx = document.body;
        if (el) {
            var er = el.getBoundingClientRect();
            for (var n = el.parentElement; n && n !== document.body; n = n.parentElement) {
                var nr = n.getBoundingClientRect();
                if (nr.height >= Math.max(er.height + pad * 2, minH) && nr.width >= Math.min(Math.max(er.width + pad * 2, minW), vw)) { ctx = n; break; }
            }
        }
        var keepRect = el ? el.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };
        var keep = { top: keepRect.top - Math.max(pad, minH), bottom: keepRect.bottom + Math.max(pad, minH) };
        return load.then(function () {
            var cr = ctx.getBoundingClientRect();
            var scale = Math.min(window.devicePixelRatio || 1, 1.5);
            var opts = {
                scale: scale,
                // Elemente weit unterhalb des Ausschnitts nicht rendern (schneller)
                filter: function (node) {
                    if (node.nodeType !== 1) { return true; }
                    if (node.tagName === 'IT-REVIEW-BAR') { return false; }
                    // noch nicht geladene Bilder (loading=lazy außerhalb des Fensters) blockieren sonst bis zum Timeout
                    if (node.tagName === 'IMG' && !(node.complete && node.naturalWidth)) { return false; }
                    var b = node.getBoundingClientRect();
                    if (!b.width && !b.height) { return true; }
                    // nur unterhalb weglassen – oberhalb würde das Layout nach oben rutschen
                    return b.top <= keep.bottom;
                },
                backgroundColor: getComputedStyle(document.body).backgroundColor || '#ffffff',
                timeout: 3000
            };
            if (ctx === document.body) {
                opts.width = cr.width;
                opts.height = Math.min(ctx.scrollHeight, window.scrollY + window.innerHeight);
            }
            return window.modernScreenshot.domToCanvas(ctx, opts).then(function (full) {
                var s = full.width / cr.width;
                // Ausschnitt in Koordinaten des Kontexts
                var r = el ? el.getBoundingClientRect() : { left: 0, top: 0, width: window.innerWidth, height: window.innerHeight };
                // um das Element zentriert, mindestens minW × minH, innerhalb des Kontexts
                var w = el ? Math.min(cr.width, Math.max(r.width + pad * 2, minW)) : r.width;
                var hgt = el ? Math.min(full.height / s, Math.max(r.height + pad * 2, minH), 2400) : r.height;
                var x = el ? r.left - cr.left + r.width / 2 - w / 2 : 0;
                var y = el ? r.top - cr.top + r.height / 2 - hgt / 2 : window.scrollY;
                x = Math.max(0, Math.min(x, cr.width - w));
                y = Math.max(0, Math.min(y, full.height / s - hgt));
                var out = document.createElement('canvas');
                out.width = Math.round(w * s);
                out.height = Math.round(hgt * s);
                var c = out.getContext('2d');
                c.drawImage(full, x * s, y * s, w * s, hgt * s, 0, 0, out.width, out.height);
                if (el) {
                    c.strokeStyle = '#e03131';
                    c.lineWidth = 3 * s;
                    c.strokeRect((r.left - cr.left - x) * s, (r.top - cr.top - y) * s, r.width * s, r.height * s);
                }
                var data = out.toDataURL('image/jpeg', 0.85);
                if (data.length > 5200000) { data = out.toDataURL('image/jpeg', 0.6); }
                return data.length > 5400000 ? '' : data;
            });
        }).catch(function (e) {
            if (window.console) { console.warn('it-review: screenshot failed', e); }
            return '';
        });
    };

    customElements.define('it-review-bar', ReviewBar);
})();
