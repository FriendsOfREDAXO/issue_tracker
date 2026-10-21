/**
 * Issue Tracker – Vollbild-App (Rahmen um alle Seiten des Issue Trackers).
 *
 * - Sperrt das Scrollen der Seite dahinter (Klasse am <html>, eigenes CSS).
 * - „Schließen“ und Esc: in der Ebene über einer Backend-Seite (it-launcher.js) wird die Ebene geschlossen,
 *   bei direktem Aufruf geht es zur zuletzt besuchten Backend-Seite außerhalb des Issue Trackers zurück.
 * - In der Ebene öffnen Links zu anderen Backend-Seiten im ganzen Fenster.
 * - „Neues Thema“ übernimmt die Backend-Seite, von der aus der Issue Tracker geöffnet wurde.
 */
(function () {
    'use strict';

    var html = document.documentElement;
    html.classList.add('it-app-lock');

    var host = null;
    try {
        if (window.parent && window.parent !== window && window.parent.ITLauncher) { host = window.parent.ITLauncher; }
    } catch (e) { host = null; }
    if (host) { html.classList.add('it-app-framed'); }

    var KEY = 'issue_tracker_app_return';

    function closest(el, sel) {
        while (el && el.nodeType === 1) {
            if (el.matches(sel)) { return el; }
            el = el.parentNode;
        }
        return null;
    }

    function parseUrl(href) {
        try { return new URL(href, window.location.href); } catch (e) { return null; }
    }

    /* Backend-Seite dieser Installation außerhalb des Issue Trackers? */
    function isOutsideBackendPage(u) {
        if (!u || u.origin !== window.location.origin || u.pathname.replace(/index\.php$/, '') !== window.location.pathname.replace(/index\.php$/, '')) { return false; }
        var page = u.searchParams.get('page') || '';
        return page !== '' && page.indexOf('issue_tracker') !== 0 && !u.searchParams.has('rex-api-call');
    }

    function store(key, value) {
        try { if (value) { window.sessionStorage.setItem(key, value); } } catch (e) { /* ohne Speicher: Startseite */ }
    }
    function read(key) {
        try { return window.sessionStorage.getItem(key) || ''; } catch (e) { return ''; }
    }

    // Direkter Aufruf (Menü ohne JavaScript, Link aus einer E-Mail): Rücksprungziel merken
    if (!host && document.referrer) {
        var ref = parseUrl(document.referrer);
        if (isOutsideBackendPage(ref)) { store(KEY, ref.href); }
    }

    function sourcePage() {
        if (host && host.source) { return host.source(); }
        var back = read(KEY);
        return back ? { url: back, title: '' } : null;
    }

    function close(fallbackHref) {
        if (host) { host.close(); return; }
        var back = read(KEY);
        window.location.href = back || fallbackHref || 'index.php';
    }

    function openPopupState() {
        return !!document.querySelector('.modal.in, #it-lightbox-overlay, details.it-menu[open], .it-qtag-pop, .bootstrap-select.open, .dropdown.open, .btn-group.open, .editor-toolbar.fullscreen, .CodeMirror-fullscreen');
    }

    function isTypingTarget(el) {
        if (!el) { return false; }
        var tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable || !!closest(el, '.CodeMirror');
    }

    // „Vollbild“: Fenster des Overlays auf die ganze Fläche (Zustand merkt sich die Seite darunter)
    function initMax() {
        var btn = document.querySelector('[data-it-app-max]');
        var ovHost = null;
        try { ovHost = window.parent !== window ? window.parent.ITLauncher : null; } catch (e) { ovHost = null; }
        if (!btn || !ovHost || !ovHost.toggleMax) { return; }
        btn.hidden = false;
        var sync = function () {
            var on = ovHost.isMax();
            var label = btn.getAttribute(on ? 'data-label-off' : 'data-label-on');
            btn.title = label;
            btn.setAttribute('aria-label', label);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            var icon = btn.querySelector('.rex-icon');
            if (icon) { icon.className = 'rex-icon ' + (on ? 'fa-compress' : 'fa-expand'); }
        };
        btn.addEventListener('click', function () { ovHost.toggleMax(); sync(); });
        sync();
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initMax); } else { initMax(); }

    // Esc schließt die App – nur, wenn kein Feld, Menü oder Fenster offen ist (Prüfung vor den anderen Esc-Handlern)
    window.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || e.defaultPrevented || e.metaKey || e.ctrlKey || e.altKey || e.shiftKey) { return; }
        if (isTypingTarget(e.target) || openPopupState()) { return; }
        var btn = document.querySelector('[data-it-app-close]');
        if (!btn) { return; }
        e.preventDefault();
        close(btn.href);
    }, true);

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0) { return; }
        var btn = closest(e.target, '[data-it-app-close]');
        if (btn) {
            e.preventDefault();
            close(btn.href);
            return;
        }
        var a = closest(e.target, 'a[href]');
        if (!a || !closest(a, '[data-it-app]')) { return; }
        var u = parseUrl(a.getAttribute('href'));
        if (!u) { return; }

        // „Neues Thema“: Backend-Seite übernehmen, von der aus der Issue Tracker geöffnet wurde
        if (u.searchParams.get('page') === 'issue_tracker/issues/create' && !u.searchParams.has('issue_id') && !u.searchParams.has('it_src')) {
            var src = sourcePage();
            if (src && src.url) {
                u.searchParams.set('it_src', src.url);
                if (src.title) { u.searchParams.set('it_src_title', src.title); }
                a.setAttribute('href', u.pathname + u.search + u.hash);
            }
            return;
        }

        // In der Ebene: andere Backend-Seiten (z. B. Artikel bearbeiten) im ganzen Fenster öffnen
        if (host && !a.target && !a.hasAttribute('download') && isOutsideBackendPage(u)) {
            a.target = '_top';
        }
    });

    // Bootstrap-Fenster an <body> hängen, damit sie über der App und ihrem Hintergrund liegen
    if (window.jQuery) {
        window.jQuery(document).on('show.bs.modal', '.it-app .modal', function () {
            if (this.parentNode !== document.body) { document.body.appendChild(this); }
        });
    }

    function init() {
        var app = document.querySelector('[data-it-app]');
        if (!app) { return; }
        // Fokus in die App (Tastatur, Bildschirmleser), sofern nicht schon ein Feld gewählt ist
        var active = document.activeElement;
        if (!active || active === document.body || active === html) {
            app.focus({ preventScroll: true });
        }
        if (host && host.loaded) { host.loaded(document.title); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
