/**
 * Issue Tracker – Vollbild-App als Ebene über dem REDAXO-Backend (ohne iframe).
 *
 * - Die Ebene (.it-ov > .it-ov-win > .it-ov-host) liegt im Dokument der aktuellen Backend-Seite. Geöffnet wird sie
 *   von it-launcher.js (Menüpunkt, Knöpfe in der Kopfzeile) oder sie kommt beim direkten Aufruf schon vom Server.
 * - Navigation in der App per PJAX: Links und Formulare innerhalb von [data-it-app] werden mit „X-PJAX: true“ und
 *   dem Container „#it-app“ geladen. REDAXO rendert solche Anfragen ohne Backend-Layout (rex_be_controller), es kommt
 *   nur der App-Rahmen zurück. Danach wird – wie bei REDAXOs eigenem PJAX – „rex:ready“ auf dem neuen Inhalt ausgelöst.
 * - Adresse und Verlauf: in der Ebene steht die Adresse des Issue Trackers in der Adresszeile (pushState), Zurück/Vor
 *   funktionieren in der App, Zurück vor den ersten Eintrag schließt die Ebene. Schließen stellt die Adresse der Seite
 *   darunter wieder her, ohne sie neu zu laden.
 * - „Vollbild“: Fenster auf die ganze Fläche (gemerkt im localStorage), bis 1199 px immer ganze Fläche.
 */
(function () {
    'use strict';

    if (window.ITApp) { return; }

    var html = document.documentElement;
    var $ = window.jQuery;
    var KEY_RETURN = 'issue_tracker_app_return';
    var KEY_MAX = 'it_app_max';
    var CONTAINER = '#it-app';

    var st = {
        ov: null,          // .it-ov
        host: null,        // .it-ov-host
        app: null,         // aktueller [data-it-app]
        open: false,
        isStatic: false,   // direkt aufgerufen (Ebene kam vom Server)
        base: null,        // Seite unter der Ebene: { url, title, state }
        opener: null,      // Element, das den Fokus nach dem Schließen zurückbekommt
        depth: 0,          // Einträge im Verlauf seit dem Öffnen
        lastUrl: '',       // zuletzt angezeigte Adresse (für Zurück/Vor)
        ctrl: null,        // AbortController der laufenden Anfrage
        progressTimer: null,
        progressCount: 0
    };

    /* ---------- Hilfen ---------- */
    function closest(el, sel) {
        return el && el.closest ? el.closest(sel) : null;
    }
    function parseUrl(href) {
        try { return new URL(href, window.location.href); } catch (e) { return null; }
    }
    function backendPath(path) {
        return String(path || '').replace(/index\.php$/, '');
    }
    function sameBackend(u) {
        return !!u && u.origin === window.location.origin && backendPath(u.pathname) === backendPath(window.location.pathname);
    }
    /* Seite des Issue Trackers in diesem Backend (kein API-Aufruf, kein Download)? */
    function isAppUrl(u) {
        if (!sameBackend(u)) { return false; }
        var page = u.searchParams.get('page') || '';
        return page.indexOf('issue_tracker') === 0 && !u.searchParams.has('rex-api-call') && !u.searchParams.has('export') && !u.searchParams.has('export_pdf');
    }
    /* Backend-Seite außerhalb des Issue Trackers? */
    function isOutsideBackendPage(u) {
        if (!sameBackend(u)) { return false; }
        var page = u.searchParams.get('page') || '';
        return page !== '' && page.indexOf('issue_tracker') !== 0 && !u.searchParams.has('rex-api-call');
    }
    function store(key, value) {
        try { if (value) { window.sessionStorage.setItem(key, value); } } catch (e) { /* ohne Speicher: Startseite */ }
    }
    function read(key) {
        try { return window.sessionStorage.getItem(key) || ''; } catch (e) { return ''; }
    }
    function isTypingTarget(el) {
        if (!el) { return false; }
        var tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable || !!closest(el, '.CodeMirror');
    }
    function openPopupState() {
        return !!document.querySelector('.modal.in, #it-lightbox-overlay, details.it-menu[open], .it-qtag-pop, .bootstrap-select.open, .dropdown.open, .btn-group.open, .editor-toolbar.fullscreen, .CodeMirror-fullscreen');
    }

    /* ---------- Fenster / Vollbild ---------- */
    function readMax() {
        try { return window.localStorage.getItem(KEY_MAX) === '1'; } catch (e) { return false; }
    }
    function setMax(on) {
        html.classList.toggle('it-ov-max', !!on);
        try { window.localStorage.setItem(KEY_MAX, on ? '1' : '0'); } catch (e) { /* nur für diese Sitzung */ }
        syncMaxButton();
    }
    function syncMaxButton() {
        var btn = st.app ? st.app.querySelector('[data-it-app-max]') : null;
        if (!btn) { return; }
        var on = html.classList.contains('it-ov-max');
        var label = btn.getAttribute(on ? 'data-label-off' : 'data-label-on');
        btn.title = label;
        btn.setAttribute('aria-label', label);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        var icon = btn.querySelector('.rex-icon');
        if (icon) { icon.className = 'rex-icon ' + (on ? 'fa-compress' : 'fa-expand'); }
    }

    /* ---------- Fortschritt: dezente Leiste oben im Fenster, erst nach 150 ms ---------- */
    function progress(on) {
        var bar = st.ov ? st.ov.querySelector('.it-ov-progress') : null;
        if (on) {
            st.progressCount++;
            if (st.progressCount > 1 || !bar) { return; }
            clearTimeout(st.progressTimer);
            bar.classList.remove('is-done');
            st.progressTimer = setTimeout(function () { bar.classList.add('is-active'); }, 150);
            return;
        }
        st.progressCount = Math.max(0, st.progressCount - 1);
        if (st.progressCount > 0 || !bar) { return; }
        clearTimeout(st.progressTimer);
        if (bar.classList.contains('is-active')) {
            bar.classList.add('is-done');
            st.progressTimer = setTimeout(function () { bar.classList.remove('is-active', 'is-done'); }, 250);
        }
    }

    /* ---------- Benutzereinstellungen am <body> (Schriftgröße, Spaltenbreiten) ---------- */
    function applyBody(app) {
        var scale = parseInt(app.getAttribute('data-it-scale') || '100', 10);
        [90, 110, 120, 130, 140].forEach(function (s) { document.body.classList.toggle('it-scale-' + s, s === scale); });
        var cols = (app.getAttribute('data-it-cols') || '').split(',');
        if (cols.length === 2 && document.body.style.getPropertyValue('--it-col-mbx') === '') {
            document.body.style.setProperty('--it-col-mbx', parseInt(cols[0], 10) + 'px');
            document.body.style.setProperty('--it-col-ml', parseInt(cols[1], 10) + 'px');
        }
    }

    /* ---------- Verlauf ---------- */
    function stateFor(extra) {
        var s = {};
        if (extra && typeof extra === 'object') { Object.keys(extra).forEach(function (k) { s[k] = extra[k]; }); }
        s.itApp = 1;
        s.depth = st.depth;
        return s;
    }
    function push(url) {
        if (url === window.location.href) {
            window.history.replaceState(stateFor(), '', url);
        } else {
            st.depth++;
            window.history.pushState(stateFor(), '', url);
        }
        st.lastUrl = window.location.href;
    }

    /* ---------- Ungespeicherte Änderungen ---------- */
    function canLeave(scope, except) {
        var msg = window.ITMail && window.ITMail.dirtyMessage ? window.ITMail.dirtyMessage(scope || st.app || document, except || null) : '';
        if (!msg) { return true; }
        if (!window.confirm(msg)) { return false; }
        // Bestätigt: für diese Formulare nicht noch einmal fragen
        Array.prototype.forEach.call((scope || st.app || document).querySelectorAll('form[data-it-form]'), function (f) { f._itLeaving = true; });
        return true;
    }

    /* ---------- Inhalt austauschen ---------- */
    function unmount(app) {
        if (!app) { return; }
        document.dispatchEvent(new CustomEvent('it:unload', { detail: { root: app } }));
        // An <body> gehängte Bootstrap-Fenster der App entfernen
        Array.prototype.forEach.call(document.querySelectorAll('body > .modal[data-it-moved]'), function (m) {
            if ($ && $.fn.modal) { $(m).modal('hide'); }
            if (m.parentNode) { m.parentNode.removeChild(m); }
        });
        if (!document.querySelector('.modal.in')) {
            Array.prototype.forEach.call(document.querySelectorAll('body > .modal-backdrop'), function (b) { b.parentNode.removeChild(b); });
            document.body.classList.remove('modal-open');
        }
        if (window.ITEditors) { window.ITEditors.destroy(app); }
    }

    var SCROLLERS = ['.it-mbx', '.it-ml', '.it-ml-items', '.it-rp-scroll', '.it-app-body', '.it-rp-wrap--sheet'];
    function listKey(href) {
        var u = parseUrl(href);
        if (!u) { return ''; }
        ['issue_id', 'message_id', 'it_pane', 'screen', 'func'].forEach(function (k) { u.searchParams.delete(k); });
        u.searchParams.sort();
        return u.searchParams.toString();
    }
    function pageKey(href) {
        var u = parseUrl(href);
        return u ? (u.searchParams.get('page') || '') : '';
    }

    function swap(app, opts) {
        var old = st.app;
        // Scrollpositionen behalten: Seitenleiste immer, Liste bei gleicher Liste, alles nach Aktionen auf derselben Seite
        var keep = {};
        if (old) {
            SCROLLERS.forEach(function (sel) {
                var el = old.querySelector(sel);
                if (!el) { return; }
                if (sel === '.it-mbx' || ((sel === '.it-ml' || sel === '.it-ml-items') && opts.sameList) || opts.samePage) { keep[sel] = el.scrollTop; }
            });
            unmount(old);
        }
        app.id = 'it-app';
        if (old && old.parentNode === st.host) {
            st.host.replaceChild(app, old);
        } else {
            st.host.innerHTML = '';
            st.host.appendChild(app);
        }
        st.app = app;
        applyBody(app);
        Object.keys(keep).forEach(function (sel) {
            var el = app.querySelector(sel);
            if (el) { el.scrollTop = keep[sel]; }
        });
        mount(app, opts);
    }

    function mount(app, opts) {
        opts = opts || {};
        syncMaxButton();
        // REDAXO-Konvention: Inhalt ist bereit (Auswahllisten, Editoren, Formulare, Schriftgröße …)
        if (!opts.initial && $) { $(app).trigger('rex:ready', [$(app)]); }
        // Sprungmarke (#…) im Inhalt anzeigen
        var u = parseUrl(window.location.href);
        var target = null;
        if (u && u.hash.length > 1) {
            try { target = document.getElementById(decodeURIComponent(u.hash.slice(1))); } catch (e) { target = null; }
        }
        if (target && app.contains(target) && !opts.samePage) { target.scrollIntoView({ block: 'start' }); }
        // Fokus in die App (Tastatur, Bildschirmleser), sofern nicht schon ein Feld darin gewählt ist
        var active = document.activeElement;
        if (opts.focus !== false && (!active || active === document.body || active === html || !app.contains(active))) {
            app.focus({ preventScroll: true });
        }
    }

    /* Antwort auswerten: App-Rahmen einsetzen, sonst normal weiter (z. B. abgemeldet) */
    function apply(res, opts) {
        if (res.download) { return saveDownload(res); }
        var doc = new DOMParser().parseFromString(res.html, 'text/html');
        var app = doc.querySelector('[data-it-app]');
        var finalUrl = res.url || opts.url;
        if (!app) {
            window.location.href = finalUrl;
            return;
        }
        var prevUrl = st.lastUrl || window.location.href;
        var u = parseUrl(finalUrl);
        var req = parseUrl(opts.url);
        // Sprungmarke der angefragten Adresse übernehmen (fetch kennt keine #…)
        if (u && req && req.hash && !u.hash) { u.hash = req.hash; finalUrl = u.href; }
        if (opts.pop) {
            // Weiterleitung auf dem Server (z. B. Hauptseite -> Postfach): Adresse angleichen
            if (finalUrl !== window.location.href && !req.hash) { window.history.replaceState(stateFor(), '', finalUrl); }
            st.lastUrl = window.location.href;
        } else if (opts.replace) {
            window.history.replaceState(stateFor(), '', finalUrl);
            st.lastUrl = window.location.href;
        } else {
            push(finalUrl);
        }
        var title = doc.querySelector('title');
        if (title && title.textContent) { document.title = title.textContent; }
        swap(document.importNode(app, true), {
            sameList: pageKey(prevUrl) === pageKey(finalUrl) && listKey(prevUrl) === listKey(finalUrl),
            samePage: !!opts.keepScroll && pageKey(prevUrl) === pageKey(finalUrl),
            focus: opts.focus
        });
    }

    /* Download aus einer Antwort speichern (z. B. Export per Formular) */
    function saveDownload(res) {
        return res.response.blob().then(function (blob) {
            var name = 'download';
            var m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(res.disposition || '');
            if (m) { try { name = decodeURIComponent(m[1]); } catch (e) { name = m[1]; } }
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = name;
            a.hidden = true;
            document.body.appendChild(a);
            a.click();
            setTimeout(function () { URL.revokeObjectURL(a.href); a.parentNode.removeChild(a); }, 1000);
        });
    }

    /* PJAX-Anfrage: REDAXO liefert für fremde Container nur den Seiteninhalt ohne Layout */
    function request(url, opts) {
        if (st.ctrl) { st.ctrl.abort(); }
        st.ctrl = 'AbortController' in window ? new AbortController() : null;
        return fetch(url, {
            method: opts.method || 'GET',
            body: opts.body || undefined,
            credentials: 'same-origin',
            redirect: 'follow',
            signal: st.ctrl ? st.ctrl.signal : undefined,
            headers: { 'X-PJAX': 'true', 'X-PJAX-Container': CONTAINER }
        }).then(function (r) {
            var type = r.headers.get('Content-Type') || '';
            var disposition = r.headers.get('Content-Disposition') || '';
            if (/attachment/i.test(disposition) || (type && type.indexOf('text/html') === -1)) {
                return { download: true, response: r, disposition: disposition, url: r.url };
            }
            return r.text().then(function (t) { return { html: t, url: r.url, status: r.status }; });
        });
    }

    /**
     * Zu einer Adresse des Issue Trackers wechseln.
     * opts: method, body (FormData), replace, pop (Zurück/Vor), keepScroll, form (absendendes Formular)
     */
    function navigate(url, opts) {
        opts = opts || {};
        opts.url = url;
        if (!st.open) { return Promise.resolve(false); }
        if (!opts.pop && !canLeave(null, opts.form || null)) { return Promise.resolve(false); }
        progress(true);
        if (st.app) { st.app.setAttribute('aria-busy', 'true'); }
        return request(url, opts).then(function (res) {
            progress(false);
            if (st.app) { st.app.removeAttribute('aria-busy'); }
            return Promise.resolve(apply(res, opts)).then(function () { return true; });
        }, function (err) {
            progress(false);
            if (st.app) { st.app.removeAttribute('aria-busy'); }
            if (err && err.name === 'AbortError') { return false; }
            // Netzwerkfehler: normal weiter (Formular klassisch absenden)
            if (opts.form && opts.method === 'POST') {
                opts.form.setAttribute('data-it-nopjax', '1');
                HTMLFormElement.prototype.submit.call(opts.form);
            } else {
                window.location.href = url;
            }
            return false;
        });
    }

    /* ---------- Öffnen / Schließen ---------- */
    function show() {
        st.ov.hidden = false;
        st.open = true;
        html.classList.add('it-ov-lock');
        html.classList.toggle('it-ov-max', readMax());
    }

    /**
     * Von it-launcher.js: Ebene steht (mit Platzhalter), die Antwort kommt als Promise (ggf. vorab geladen).
     */
    function launch(o) {
        st.ov = o.ov;
        st.host = st.ov.querySelector('.it-ov-host');
        if (!st.open) {
            st.base = { url: window.location.href, title: document.title, state: window.history.state };
            st.opener = o.opener || document.activeElement;
            st.depth = 0;
            st.app = null;
        }
        show();
        progress(true);
        return o.response.then(function (res) {
            progress(false);
            if (!st.open) { return; }
            apply(res, { url: o.url });
        }, function () {
            progress(false);
            window.location.href = o.url;
        });
    }

    function close() {
        if (!st.open || !canLeave(null, null)) { return; }
        if (st.isStatic) {
            // Direkt aufgerufen: zur letzten Backend-Seite außerhalb des Issue Trackers (oder Startseite)
            var home = st.app ? st.app.getAttribute('data-it-home') : '';
            window.location.href = read(KEY_RETURN) || home || 'index.php';
            return;
        }
        var d = st.depth;
        closeNow();
        st.depth = 0;
        if (d > 0) {
            // Zurück zum Eintrag der Seite darunter (ohne Neuladen; popstate kommt dann ohne App-Zustand)
            window.history.go(-d);
        } else if (st.base) {
            window.history.replaceState(st.base.state, '', st.base.url);
        }
    }

    /* Ebene ausblenden und Inhalt abbauen (ohne Verlauf) */
    function closeNow() {
        if (st.ctrl) { st.ctrl.abort(); }
        st.open = false;
        unmount(st.app);
        st.app = null;
        if (st.host) { st.host.innerHTML = ''; }
        if (st.ov) { st.ov.hidden = true; }
        st.progressCount = 1;
        progress(false);
        html.classList.remove('it-ov-lock');
        if (st.base) { document.title = st.base.title; }
        var opener = st.opener;
        st.opener = null;
        if (opener && opener.focus && document.documentElement.contains(opener)) { opener.focus({ preventScroll: true }); }
    }

    /* ---------- Ereignisse ---------- */

    // Zurück/Vor
    window.addEventListener('popstate', function (e) {
        var s = e.state;
        if (s && s.itApp) {
            var prev = st.lastUrl;
            st.depth = s.depth || 0;
            if (!st.open) {
                // Vor nach dem Schließen: Ebene wieder öffnen
                if (!st.ov || !st.host) { window.location.reload(); return; }
                show();
            }
            if (st.app && window.ITMail && window.ITMail.tryPane && window.ITMail.tryPane(window.location.href, prev)) {
                st.lastUrl = window.location.href;
                return;
            }
            navigate(window.location.href, { pop: true });
        } else if (st.open && !st.isStatic) {
            // Zurück vor den ersten Eintrag der App: Ebene schließen
            if (!canLeave(null, null)) {
                st.depth++;
                window.history.pushState(stateFor(), '', st.lastUrl || window.location.href);
                return;
            }
            st.depth = 0;
            closeNow();
        }
    });

    // Klicks in der App: PJAX für Seiten des Issue Trackers, Schließen, Vollbild
    document.addEventListener('click', function (e) {
        if (!st.open || !st.app) { return; }
        var max = closest(e.target, '[data-it-app-max]');
        if (max && st.app.contains(max)) {
            e.preventDefault();
            setMax(!html.classList.contains('it-ov-max'));
            return;
        }
        var closeBtn = closest(e.target, '[data-it-app-close]');
        if (closeBtn && st.app.contains(closeBtn)) {
            e.preventDefault();
            close();
            return;
        }
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var a = closest(e.target, 'a[href]');
        if (!a || !st.app.contains(a)) { return; }
        if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-it-nopjax')) { return; }
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) { return; }
        var u = parseUrl(href);
        if (!isAppUrl(u)) { return; } // andere Backend-Seiten: normal im ganzen Fenster
        var here = parseUrl(window.location.href);
        if (u.hash && here && u.pathname === here.pathname && u.search === here.search) { return; } // Sprungmarke

        // „Neues Thema“: Seite übernehmen, von der aus der Issue Tracker geöffnet wurde
        var target = u.href;
        if (u.searchParams.get('page') === 'issue_tracker/issues/create' && !u.searchParams.has('issue_id') && !u.searchParams.has('it_src')) {
            var src = sourcePage();
            if (src && src.url) {
                target = target.replace(/#.*$/, '') + '&it_src=' + encodeURIComponent(src.url) + (src.title ? '&it_src_title=' + encodeURIComponent(src.title) : '');
            }
        }
        e.preventDefault();
        navigate(target);
    });

    // Formulare in der App: GET als Adresse, POST (auch mit Dateien) als FormData – die Antwort landet in der Ebene
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!st.open || !st.app || e.defaultPrevented || !form || !st.app.contains(form)) { return; }
        if ((form.target && form.target !== '_self') || form.hasAttribute('data-it-nopjax')) { return; }
        var actionAttr = form.getAttribute('action');
        var u = parseUrl(actionAttr === null || actionAttr === '' ? window.location.href : actionAttr);
        if (!isAppUrl(u)) { return; }
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        var submitter = e.submitter || null;
        e.preventDefault();
        if (form.getAttribute('aria-busy') === 'true') { return; }

        var data;
        try {
            data = submitter ? new FormData(form, submitter) : new FormData(form);
        } catch (err) {
            data = new FormData(form);
            if (submitter && submitter.name) { data.append(submitter.name, submitter.value); }
        }
        if (method === 'get') {
            var params = new URLSearchParams();
            data.forEach(function (v, k) { if (typeof v === 'string') { params.append(k, v); } });
            u.search = params.toString();
            navigate(u.href, { form: form });
            return;
        }
        form._itLeaving = true;
        optimistic(form, submitter);
        form.setAttribute('aria-busy', 'true');
        navigate(u.href, { method: 'POST', body: data, form: form, keepScroll: pageKey(u.href) === pageKey(window.location.href) }).then(function () {
            form.removeAttribute('aria-busy');
        });
    });

    /*
     * Sofortige Rückmeldung für kleine Aktionen (der Server bestätigt mit dem neuen Inhalt):
     * Status-Menü (Auswahl markieren, Menü schließen), Beobachten (Knopf umschalten), gelesen/ungelesen.
     */
    function optimistic(form, submitter) {
        var menu = closest(form, 'details.it-menu');
        if (menu) {
            if (submitter && submitter.classList.contains('it-menu-item')) {
                Array.prototype.forEach.call(form.querySelectorAll('.it-menu-item.is-checked'), function (b) { b.classList.remove('is-checked'); });
                submitter.classList.add('is-checked');
            }
            menu.removeAttribute('open');
        }
        if (form.querySelector('input[name="toggle_watch"]')) {
            var w = form.querySelector('button[type="submit"]');
            if (w) {
                var on = !w.classList.contains('is-on');
                w.classList.toggle('is-on', on);
                w.setAttribute('aria-pressed', on ? 'true' : 'false');
            }
        }
        if (form.hasAttribute('data-it-unread')) {
            var row = st.app.querySelector('.it-ml-row.is-selected');
            if (row) { row.classList.toggle('is-unread'); }
        }
    }

    // „Neues Thema“: Backend-Seite, von der aus der Issue Tracker geöffnet wurde
    function sourcePage() {
        if (st.base && st.base.url) { return { url: st.base.url, title: st.base.title }; }
        var back = read(KEY_RETURN);
        return back ? { url: back, title: '' } : null;
    }

    // Esc schließt die App – nur, wenn kein Feld, Menü oder Fenster offen ist (Prüfung vor den anderen Esc-Handlern)
    window.addEventListener('keydown', function (e) {
        if (!st.open || e.key !== 'Escape' || e.defaultPrevented || e.metaKey || e.ctrlKey || e.altKey || e.shiftKey) { return; }
        if (isTypingTarget(e.target) || openPopupState()) { return; }
        if (!st.app || !st.app.querySelector('[data-it-app-close]')) { return; }
        e.preventDefault();
        close();
    }, true);

    // Bootstrap-Fenster an <body> hängen, damit sie über der Ebene liegen (beim Austausch wieder entfernt)
    if ($) {
        $(document).on('show.bs.modal', '.it-app .modal', function () {
            if (this.parentNode !== document.body) {
                this.setAttribute('data-it-moved', '1');
                document.body.appendChild(this);
            }
        });
    }

    /* ---------- Start: Ebene vom Server (direkter Aufruf, Neuladen) übernehmen ---------- */
    function boot() {
        var ov = document.querySelector('[data-it-ov][data-it-ov-static]');
        if (!ov) { return; }
        st.ov = ov;
        st.host = ov.querySelector('.it-ov-host');
        st.app = st.host.querySelector('[data-it-app]');
        st.isStatic = true;
        st.open = true;
        html.classList.add('it-ov-lock');
        // Rücksprungziel merken, falls von einer anderen Backend-Seite (Menü ohne JavaScript, Link aus einer E-Mail)
        var ref = document.referrer ? parseUrl(document.referrer) : null;
        if (isOutsideBackendPage(ref)) { store(KEY_RETURN, ref.href); }
        window.history.replaceState(stateFor(window.history.state), '', window.location.href);
        st.lastUrl = window.location.href;
        if (st.app) { mount(st.app, { initial: true }); }
    }
    // Zustand „Vollbild“ sofort setzen (kein Springen des Fensters beim Laden)
    html.classList.toggle('it-ov-max', readMax());
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.ITApp = {
        active: function () { return st.open; },
        navigate: function (url, opts) { return navigate(url, opts); },
        push: push,
        progress: progress,
        canLeave: function (scope) { return canLeave(scope, null); },
        launch: launch,
        close: close
    };
})();
