/**
 * Issue Tracker – Aufruf von jeder Backend-Seite (Hauptmenü, Knöpfe in der Kopfzeile).
 *
 * Klein gehalten: zeigt die Ebene sofort mit einem Platzhalter (Kopfleiste + drei Spalten), lädt Inhalt und
 * Dateien der App parallel nach und übergibt dann an it-app.js (PJAX in der Ebene, ohne iframe).
 * Schneller Start: beim Zeigen auf den Knopf (bzw. Fokus, Antippen) werden Inhalt und Dateien vorab geladen,
 * nach dem Laden der Seite werden die Dateien im Leerlauf in den Browser-Cache geholt.
 * „Neues Thema“ übernimmt die Adresse der aktuellen Seite (geprüft wird serverseitig).
 */
(function () {
    'use strict';

    if (window.ITLaunch) { return; }

    var html = document.documentElement;
    var KEY_RETURN = 'issue_tracker_app_return';
    var cfg = null;
    var assets = null;      // Promise: Dateien der App geladen
    var pre = {};           // vorab geladene Antworten je Adresse
    var ov = null;
    var opener = null;
    var waiting = false;    // Ebene offen, App noch nicht übernommen

    function config() {
        if (cfg) { return cfg; }
        var el = document.querySelector('[data-it-launch]');
        cfg = { labels: { title: 'Issue Tracker', close: 'Schließen', loading: '' }, css: [], js: [] };
        if (el) {
            try {
                var c = JSON.parse(el.getAttribute('data-it-launch'));
                Object.keys(c).forEach(function (k) { cfg[k] = c[k]; });
            } catch (e) { /* Standard */ }
        }
        return cfg;
    }

    /* Dateien der App einmal einbinden (CSS vollständig geladen, JS in fester Reihenfolge) */
    function loadAssets() {
        if (assets) { return assets; }
        if (window.ITApp) { assets = Promise.resolve(); return assets; }
        var c = config();
        var head = document.head;
        var waits = c.css.map(function (href) {
            if (document.querySelector('link[rel="stylesheet"][href="' + href + '"]')) { return Promise.resolve(); }
            return new Promise(function (resolve) {
                var l = document.createElement('link');
                l.rel = 'stylesheet';
                l.href = href;
                l.onload = l.onerror = resolve;
                head.appendChild(l);
            });
        });
        waits.push(new Promise(function (resolve, reject) {
            var last = null;
            c.js.forEach(function (src) {
                var s = document.createElement('script');
                s.src = src;
                s.async = false; // parallel laden, in Reihenfolge ausführen
                s.onerror = reject;
                head.appendChild(s);
                last = s;
            });
            if (!last) { resolve(); return; }
            last.onload = resolve;
        }));
        assets = Promise.all(waits);
        assets.catch(function () { assets = null; });
        return assets;
    }

    /* PJAX-Anfrage wie in it-app.js: REDAXO liefert für den Container „#it-app“ nur den Seiteninhalt */
    function fetchApp(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'X-PJAX': 'true', 'X-PJAX-Container': '#it-app' } })
            .then(function (r) {
                return r.text().then(function (t) { return { html: t, url: r.url, status: r.status }; });
            });
    }

    function targetUrl(a) {
        if (!a.hasAttribute('data-it-app-new')) { return a.href; }
        var href = a.href.replace(/[?&]it_src(_title)?=[^&#]*/g, '');
        return href + (href.indexOf('?') === -1 ? '?' : '&') + 'it_src=' + encodeURIComponent(window.location.href) + '&it_src_title=' + encodeURIComponent(document.title);
    }

    /* Vorab laden (Zeigen, Fokus, Antippen): gilt 20 Sekunden */
    function prefetch(a) {
        var url = targetUrl(a);
        var p = pre[url];
        if (!p || Date.now() - p.t > 20000) {
            pre[url] = { t: Date.now(), p: fetchApp(url) };
            pre[url].p.catch(function () { delete pre[url]; });
        }
        loadAssets().catch(function () {});
    }
    function take(url) {
        var p = pre[url];
        delete pre[url];
        return p && Date.now() - p.t <= 20000 ? p.p : fetchApp(url);
    }

    function esc(str) {
        return String(str).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }

    /* Ebene mit Platzhalter (gleiche Struktur wie AppShell::overlayStart) */
    function build() {
        var l = config().labels;
        ov = document.createElement('div');
        ov.className = 'it-ov';
        ov.setAttribute('data-it-ov', '');
        ov.hidden = true;
        ov.innerHTML = '<div class="it-ov-win"><div class="it-ov-progress" aria-hidden="true"></div><div class="it-ov-host"></div></div>';
        document.body.appendChild(ov);
    }
    function skeleton() {
        var l = config().labels;
        var lines = function (n) { var h = ''; for (var i = 0; i < n; i++) { h += '<span class="it-sk-line"></span>'; } return h; };
        return '<div class="it-sk" role="dialog" aria-modal="true" aria-label="' + esc(l.title) + '" aria-busy="true" tabindex="-1">'
            + '<div class="it-sk-bar"><span class="it-sk-icon" aria-hidden="true"><i class="rex-icon fa-tasks"></i></span><span class="it-sk-title">' + esc(l.title) + '</span>'
            + '<button type="button" class="it-sk-close" data-it-sk-close><i class="rex-icon fa-times" aria-hidden="true"></i> <span>' + esc(l.close) + '</span></button></div>'
            + '<div class="it-sk-cols" aria-hidden="true"><div class="it-sk-col it-sk-col--mbx">' + lines(8) + '</div><div class="it-sk-col it-sk-col--ml">' + lines(10) + '</div><div class="it-sk-col it-sk-col--rp">' + lines(6) + '</div></div>'
            + '<span class="it-sk-status" role="status">' + esc(l.loading) + '</span></div>';
    }

    function readMax() {
        try { return window.localStorage.getItem('it_app_max') === '1'; } catch (e) { return false; }
    }

    function open(a) {
        var url = targetUrl(a);
        if (window.ITApp && window.ITApp.active()) { window.ITApp.navigate(url); return; }
        if (!ov) { build(); }
        opener = a;
        var host = ov.querySelector('.it-ov-host');
        host.innerHTML = skeleton();
        html.classList.toggle('it-ov-max', readMax());
        html.classList.add('it-ov-lock');
        ov.hidden = false;
        waiting = true;
        var sk = host.querySelector('.it-sk');
        if (sk) { sk.focus({ preventScroll: true }); }
        var response = take(url);
        loadAssets().then(function () {
            if (!waiting) { return; }
            waiting = false;
            if (!window.ITApp) { throw new Error('ITApp'); }
            window.ITApp.launch({ ov: ov, url: url, response: response, opener: opener });
        }).catch(function () {
            // Dateien nicht ladbar: Seite normal öffnen
            window.location.href = url;
        });
    }

    function cancel() {
        if (!waiting || !ov) { return; }
        waiting = false;
        ov.hidden = true;
        ov.querySelector('.it-ov-host').innerHTML = '';
        html.classList.remove('it-ov-lock');
        if (opener && opener.focus) { opener.focus({ preventScroll: true }); }
    }

    var TRIGGER = 'a[data-it-app-open], a[data-it-app-new]';

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        if (closest(e.target, '[data-it-sk-close]')) { e.preventDefault(); cancel(); return; }
        var a = closest(e.target, TRIGGER);
        if (!a) { return; }
        e.preventDefault();
        e.stopPropagation();
        open(a);
    }, true);

    function closest(el, sel) {
        return el && el.closest ? el.closest(sel) : null;
    }

    // Vorab laden, sobald der Knopf angesteuert wird
    ['pointerover', 'focusin', 'pointerdown', 'touchstart'].forEach(function (type) {
        document.addEventListener(type, function (e) {
            var a = closest(e.target, TRIGGER);
            if (a) { prefetch(a); }
        }, { capture: true, passive: true });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && waiting) {
            e.preventDefault();
            cancel();
        }
    });

    // Letzte Backend-Seite außerhalb des Issue Trackers (Ziel für „Schließen“ nach direktem Aufruf)
    window.addEventListener('pagehide', function () {
        if (window.ITApp && window.ITApp.active()) { return; }
        try { window.sessionStorage.setItem(KEY_RETURN, window.location.href); } catch (e) { /* ohne Speicher */ }
    });

    // Im Leerlauf: Dateien der App in den Browser-Cache holen (ohne sie auszuführen), einmal je Sitzung
    function warm() {
        try {
            if (window.sessionStorage.getItem('it_app_warm') === '1') { return; }
            window.sessionStorage.setItem('it_app_warm', '1');
        } catch (e) { return; }
        var c = config();
        c.css.concat(c.js).forEach(function (href) {
            var l = document.createElement('link');
            l.rel = 'prefetch';
            l.href = href;
            document.head.appendChild(l);
        });
    }
    window.addEventListener('load', function () {
        if (!document.querySelector('[data-it-launch]')) { return; }
        if ('requestIdleCallback' in window) { window.requestIdleCallback(warm, { timeout: 4000 }); } else { setTimeout(warm, 2000); }
    });

    window.ITLaunch = { open: open, prefetch: prefetch };
})();
