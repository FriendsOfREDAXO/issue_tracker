/**
 * Issue Tracker – Aufruf von jeder Backend-Seite (Hauptmenü, Knöpfe in der Kopfzeile).
 * Öffnet den Issue Tracker als Ebene über der aktuellen Seite; „Schließen“/Esc kehrt ohne Neuladen zurück.
 * „Neues Thema“ übernimmt Adresse und Titel der aktuellen Seite (geprüft wird serverseitig).
 */
(function () {
    'use strict';

    if (window.top !== window.self || window.ITLauncher) { return; }

    var html = document.documentElement;
    var ov = null;
    var frame = null;
    var opener = null;
    var labels = { title: 'Issue Tracker', close: 'Schließen', loading: '' };

    function readLabels() {
        var el = document.querySelector('[data-it-labels]');
        if (!el) { return; }
        try {
            var l = JSON.parse(el.getAttribute('data-it-labels'));
            Object.keys(l).forEach(function (k) { labels[k] = l[k]; });
        } catch (e) { /* Standardtexte */ }
    }

    function build() {
        readLabels();
        ov = document.createElement('div');
        ov.className = 'it-ov';
        ov.hidden = true;
        ov.innerHTML = '<div class="it-ov-loading" role="status"><span class="it-ov-spinner" aria-hidden="true"></span><span class="it-ov-loading-text"></span>'
            + '<button type="button" class="it-ov-close"><i class="rex-icon fa-times" aria-hidden="true"></i> <span></span></button></div>'
            + '<iframe class="it-ov-frame"></iframe>';
        ov.querySelector('.it-ov-loading-text').textContent = labels.loading;
        ov.querySelector('.it-ov-close span').textContent = labels.close;
        ov.querySelector('.it-ov-close').addEventListener('click', close);
        frame = ov.querySelector('.it-ov-frame');
        frame.title = labels.title;
        frame.addEventListener('load', function () {
            if (!frame.getAttribute('src')) { return; }
            ov.classList.remove('is-loading');
            // Seite im Rahmen ist kein Issue Tracker mehr (z. B. abgemeldet): im ganzen Fenster öffnen
            try {
                var doc = frame.contentDocument;
                if (doc && frame.contentWindow.location.href !== 'about:blank' && !doc.querySelector('[data-it-app]')) {
                    window.location.href = frame.contentWindow.location.href;
                }
            } catch (e) { /* fremde Seite: nichts tun */ }
        });
        document.body.appendChild(ov);
    }

    function isOpen() {
        return !!ov && !ov.hidden;
    }

    function open(url, reload) {
        if (!ov) { build(); }
        if (!isOpen()) { opener = document.activeElement; }
        if (url && (reload || !frame.getAttribute('src'))) {
            ov.classList.add('is-loading');
            frame.setAttribute('src', url);
        }
        ov.hidden = false;
        html.classList.add('it-ov-lock');
        if (ov.classList.contains('is-loading')) {
            ov.querySelector('.it-ov-close').focus({ preventScroll: true });
        } else {
            focusFrame();
        }
    }

    function focusFrame() {
        try {
            frame.focus();
            var app = frame.contentDocument && frame.contentDocument.querySelector('[data-it-app]');
            if (app && (!frame.contentDocument.activeElement || frame.contentDocument.activeElement === frame.contentDocument.body)) { app.focus({ preventScroll: true }); }
        } catch (e) { /* ohne Zugriff */ }
    }

    function close() {
        if (!isOpen()) { return; }
        ov.hidden = true;
        html.classList.remove('it-ov-lock');
        if (opener && opener.focus && document.contains(opener)) { opener.focus({ preventScroll: true }); }
        opener = null;
    }

    window.ITLauncher = {
        open: open,
        close: close,
        isOpen: isOpen,
        // Aktuelle Backend-Seite (für „Neues Thema“ in der App)
        source: function () { return { url: window.location.href, title: document.title }; },
        loaded: function () {
            ov.classList.remove('is-loading');
            if (isOpen()) { focusFrame(); }
        }
    };

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var t = e.target;
        var a = t && t.closest ? t.closest('a[data-it-app-open], a[data-it-app-new]') : null;
        if (!a) { return; }
        e.preventDefault();
        e.stopPropagation();
        if (a.hasAttribute('data-it-app-new')) {
            var u = new URL(a.getAttribute('href'), window.location.href);
            u.searchParams.set('it_src', window.location.href);
            u.searchParams.set('it_src_title', document.title);
            open(u.href, true);
        } else {
            open(a.href, false);
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) {
            e.preventDefault();
            close();
        }
    });
})();
