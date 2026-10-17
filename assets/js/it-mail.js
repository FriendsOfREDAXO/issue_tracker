/**
 * Issue Tracker – Postfach-Bedienung wie Mail (progressive Verbesserung):
 * Zeile anklicken lädt den Lesebereich nach, Pfeiltasten wechseln das Thema,
 * Eingabetaste öffnet die Vollansicht. Ohne JavaScript funktionieren alle Links und Formulare.
 */
(function () {
    'use strict';

    var controller = null;

    function closest(el, sel) {
        return el && el.closest ? el.closest(sel) : null;
    }

    function isTypingTarget(el) {
        if (!el) { return false; }
        var tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable || !!closest(el, '.CodeMirror');
    }

    /* Höhe der dreispaltigen Ansicht an das Fenster anpassen */
    function fitHeight(root) {
        if (!root || root.classList.contains('it-mail--table')) { return; }
        var top = root.getBoundingClientRect().top + window.pageYOffset;
        var h = Math.max(540, window.innerHeight - top - 24);
        root.style.setProperty('--it-mail-h', h + 'px');
    }

    function rows(root) {
        return Array.prototype.slice.call(root.querySelectorAll('.it-ml-row'));
    }

    function markSelected(root, row) {
        rows(root).forEach(function (r) {
            r.classList.toggle('is-selected', r === row);
            if (r === row) { r.setAttribute('aria-current', 'true'); } else { r.removeAttribute('aria-current'); }
        });
    }

    function initEditor(pane) {
        var textarea = pane.querySelector('#new-comment-text');
        if (!textarea || typeof window.EasyMDE === 'undefined') { return; }
        if (textarea.nextSibling && textarea.nextSibling.classList && textarea.nextSibling.classList.contains('EasyMDEContainer')) { return; }
        var editor = new window.EasyMDE({
            element: textarea,
            spellChecker: false,
            toolbar: ['bold', 'italic', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', 'code', '|', 'preview', 'guide'],
            status: false,
            placeholder: textarea.getAttribute('placeholder') || '',
            minHeight: '120px'
        });
        if (window._itAttachMentionToEditor) { window._itAttachMentionToEditor(editor); }
    }

    function initPane(pane, fromFetch) {
        if (!pane) { return; }
        if (fromFetch) {
            initEditor(pane);
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.selectpicker) {
                window.jQuery(pane).find('.selectpicker').selectpicker();
            }
        }
        // Dateiauswahl: Anzahl anzeigen
        Array.prototype.forEach.call(pane.querySelectorAll('.it-file input[type="file"]'), function (input) {
            input.addEventListener('change', function () {
                var label = input.parentNode.querySelector('span');
                if (!label.getAttribute('data-label')) { label.setAttribute('data-label', label.textContent); }
                label.textContent = input.files.length ? label.getAttribute('data-label') + ' (' + input.files.length + ')' : label.getAttribute('data-label');
                input.parentNode.classList.toggle('has-files', input.files.length > 0);
            });
        });
        // Schwebenden „Antworten“-Knopf ausblenden, sobald das Antwortfeld sichtbar ist
        var sticky = pane.querySelector('.it-rp-sticky');
        var compose = pane.querySelector('#it-reply');
        if (sticky && compose && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { sticky.classList.toggle('is-hidden', e.isIntersecting); });
            }).observe(compose);
        }
    }

    function loadPane(root, row, push) {
        var pane = root.querySelector('[data-it-pane]');
        if (!pane || !root.getAttribute('data-pane-url')) {
            window.location.href = row.href;
            return;
        }
        markSelected(root, row);
        root.classList.add('it-mail--sel');
        if (controller) { controller.abort(); }
        controller = 'AbortController' in window ? new AbortController() : null;
        var url = row.href + (row.href.indexOf('?') === -1 ? '?' : '&') + 'it_pane=1';
        pane.setAttribute('aria-busy', 'true');
        fetch(url, { credentials: 'same-origin', signal: controller ? controller.signal : undefined, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.text();
            })
            .then(function (html) {
                if (html.indexOf('it-rp') === -1 && html.indexOf('alert') === -1) { throw new Error('unexpected'); }
                pane.innerHTML = html;
                pane.removeAttribute('aria-busy');
                var scroller = pane.querySelector('.it-rp-scroll');
                if (scroller) { scroller.scrollTop = 0; }
                if (push && window.history && window.history.pushState) {
                    window.history.pushState({ itIssue: row.getAttribute('data-issue-id') }, '', row.href);
                }
                initPane(pane, true);
                restoreSections(pane);
                Array.prototype.forEach.call(pane.querySelectorAll('.it-compose'), initDrop);
                labelToolbars(pane);
                if (window.innerWidth < 1200) { root.scrollIntoView({ block: 'start' }); }
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') { return; }
                window.location.href = row.href;
            });
    }

    function initRoot(root) {
        if (root.getAttribute('data-it-ready')) { return; }
        root.setAttribute('data-it-ready', '1');
        fitHeight(root);
        initPane(root.querySelector('[data-it-pane]'), false);
        var sel = root.querySelector('.it-ml-row.is-selected');
        if (sel && sel.scrollIntoView) { sel.scrollIntoView({ block: 'nearest' }); }
    }

    /* Deutsche Tooltips für die Editor-Knöpfe */
    function labelToolbars(scope) {
        Array.prototype.forEach.call(scope.querySelectorAll('[data-it-tb-labels]'), function (host) {
            var labels;
            try { labels = JSON.parse(host.getAttribute('data-it-tb-labels')); } catch (e) { return; }
            Array.prototype.forEach.call(host.querySelectorAll('.editor-toolbar button, .editor-toolbar a'), function (btn) {
                Object.keys(labels).forEach(function (cls) {
                    if (btn.classList.contains(cls)) {
                        btn.setAttribute('title', labels[cls]);
                        btn.setAttribute('aria-label', labels[cls]);
                    }
                });
            });
        });
    }

    /* Dateien per Ziehen & Ablegen an das Dateifeld übergeben */
    function initDrop(zone) {
        if (!zone || zone.getAttribute('data-it-drop')) { return; }
        var input = zone.querySelector('input[type="file"]');
        if (!input || typeof DataTransfer === 'undefined') { return; }
        zone.setAttribute('data-it-drop', '1');
        Array.prototype.forEach.call(zone.querySelectorAll('.it-dnd-hint'), function (h) { h.hidden = false; });
        var hasFiles = function (e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1; };
        zone.addEventListener('dragover', function (e) {
            if (!hasFiles(e)) { return; }
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('is-dragover');
        }, true);
        zone.addEventListener('dragleave', function (e) {
            if (!zone.contains(e.relatedTarget)) { zone.classList.remove('is-dragover'); }
        });
        zone.addEventListener('drop', function (e) {
            if (!hasFiles(e)) { return; }
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('is-dragover');
            var dt = new DataTransfer();
            Array.prototype.forEach.call(input.files, function (f) { dt.items.add(f); });
            Array.prototype.forEach.call(e.dataTransfer.files, function (f) { dt.items.add(f); });
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }, true);
    }

    /* Ungültiges Feld in „Weitere Angaben“: Bereich automatisch öffnen */
    function initForms(scope) {
        Array.prototype.forEach.call(scope.querySelectorAll('form[data-it-form]'), function (form) {
            if (form.getAttribute('data-it-form-ready')) { return; }
            form.setAttribute('data-it-form-ready', '1');
            form.addEventListener('invalid', function (e) {
                var more = closest(e.target, 'details');
                if (more && !more.open) { more.open = true; }
            }, true);
        });
    }

    /* Auf-/zugeklappte Bereiche: sofort lokal merken, dauerhaft beim Benutzer speichern */
    var restoringSections = false;
    function lsGet() {
        try { return JSON.parse(window.localStorage.getItem('itSections') || '{}') || {}; } catch (e) { return {}; }
    }
    function lsSet(obj) {
        try { window.localStorage.setItem('itSections', JSON.stringify(obj)); } catch (e) { /* ohne Speicher weiter */ }
    }
    function restoreSections(scope) {
        var local = lsGet();
        restoringSections = true;
        Array.prototype.forEach.call(scope.querySelectorAll('details[data-it-sec]'), function (d) {
            var key = d.getAttribute('data-it-sec');
            if (!Object.prototype.hasOwnProperty.call(local, key)) { return; }
            var want = !!local[key];
            if (!want && d.querySelector('.is-active')) { return; } // aktiven Eintrag sichtbar lassen
            if (d.open !== want) { d.open = want; }
        });
        setTimeout(function () { restoringSections = false; }, 0);
    }
    function saveSection(d) {
        var key = d.getAttribute('data-it-sec');
        var local = lsGet();
        local[key] = d.open;
        lsSet(local);
        var nav = document.querySelector('[data-it-state-url]');
        if (!nav || !window.fetch) { return; }
        var body = new FormData();
        body.append('it_ui_state', '1');
        body.append('key', key);
        body.append('open', d.open ? '1' : '0');
        body.append('_csrf_token', nav.getAttribute('data-it-state-token'));
        fetch(nav.getAttribute('data-it-state-url'), { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
    }

    function initAll() {
        restoreSections(document);
        Array.prototype.forEach.call(document.querySelectorAll('[data-it-mail]'), initRoot);
        // Verfassen-Fenster: Dateiauswahl-Anzeige
        Array.prototype.forEach.call(document.querySelectorAll('.it-sheet'), function (sheet) { initPane(sheet, false); initDrop(sheet); });
        Array.prototype.forEach.call(document.querySelectorAll('.it-compose'), initDrop);
        initForms(document);
        // EasyMDE wird von issue_tracker.js beim selben Ereignis aufgebaut – danach beschriften
        setTimeout(function () { labelToolbars(document); }, 250);
    }

    if (!window.__itMailBound) {
        window.__itMailBound = true;

        document.addEventListener('click', function (e) {
            // Menüs: nur eines offen, Klick daneben schließt
            var inMenu = closest(e.target, 'details.it-menu');
            Array.prototype.forEach.call(document.querySelectorAll('details.it-menu[open]'), function (d) {
                if (d !== inMenu) { d.removeAttribute('open'); }
            });

            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }

            var row = closest(e.target, '.it-ml-row');
            var root = closest(e.target, '[data-it-mail]');
            if (row && root && root.querySelector('[data-it-pane]') && root.getAttribute('data-pane-url')) {
                e.preventDefault();
                loadPane(root, row, true);
                return;
            }

            var back = closest(e.target, '[data-it-back]');
            if (back && root && root.querySelector('.it-ml')) {
                e.preventDefault();
                root.classList.remove('it-mail--sel');
                root.classList.add('it-mail--list');
                markSelected(root, null);
                if (window.history && window.history.pushState) { window.history.pushState({}, '', back.href); }
                return;
            }

            var copy = closest(e.target, '[data-it-copy]');
            if (copy && navigator.clipboard && window.isSecureContext !== false) {
                e.preventDefault();
                var abs = new URL(copy.getAttribute('href'), window.location.href).href;
                navigator.clipboard.writeText(abs).then(function () {
                    var old = copy.getAttribute('title');
                    copy.setAttribute('title', copy.getAttribute('data-copied') || old);
                    copy.classList.add('is-on');
                    var live = copy.querySelector('.sr-only');
                    var oldLive = live ? live.textContent : '';
                    if (live) { live.textContent = copy.getAttribute('data-copied') || oldLive; live.setAttribute('aria-live', 'polite'); }
                    setTimeout(function () {
                        copy.setAttribute('title', old);
                        copy.classList.remove('is-on');
                        if (live) { live.textContent = oldLive; }
                    }, 1800);
                }, function () { window.location.href = abs; });
                return;
            }

            var reply = closest(e.target, '[data-it-reply]');
            if (reply) {
                var art = closest(reply, '.it-rp');
                var compose = art ? art.querySelector('#it-reply') : null;
                if (compose) {
                    e.preventDefault();
                    compose.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    var cm = compose.querySelector('.CodeMirror');
                    if (cm && cm.CodeMirror) { setTimeout(function () { cm.CodeMirror.focus(); }, 250); } else {
                        var ta = compose.querySelector('textarea');
                        if (ta) { ta.focus(); }
                    }
                }
            }
        });

        // Nur vom Benutzer ausgelöstes Auf-/Zuklappen speichern (Browser melden „toggle“ auch beim Laden offener Bereiche)
        var userToggled = null;
        document.addEventListener('click', function (e) {
            var sum = closest(e.target, 'summary');
            if (sum && sum.parentNode && sum.parentNode.matches && sum.parentNode.matches('details[data-it-sec]')) { userToggled = sum.parentNode; }
        }, true);
        // Menüs der Seitenleiste neben dem Auslöser einblenden (die Leiste scrollt und würde sie abschneiden)
        document.addEventListener('toggle', function (e) {
            var m = e.target;
            if (!m || !m.matches || !m.matches('.it-mbx details.it-menu') || !m.open) { return; }
            var r = m.querySelector('summary').getBoundingClientRect();
            var pop = m.querySelector('.it-menu-pop');
            if (!pop) { return; }
            pop.style.setProperty('--pop-top', Math.round(r.bottom + 4) + 'px');
            pop.style.setProperty('--pop-left', Math.round(Math.min(r.left, window.innerWidth - 240)) + 'px');
            m.classList.add('is-fixed');
        }, true);
        document.addEventListener('toggle', function (e) {
            var d = e.target;
            if (restoringSections || !d || d !== userToggled) { return; }
            userToggled = null;
            saveSection(d);
        }, true);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                Array.prototype.forEach.call(document.querySelectorAll('details.it-menu[open]'), function (d) {
                    d.removeAttribute('open');
                    var s = d.querySelector('summary');
                    if (s) { s.focus(); }
                });
                return;
            }
            if (e.metaKey || e.ctrlKey || e.altKey || e.shiftKey || isTypingTarget(e.target)) { return; }
            // „e“ = Bearbeiten (im Wissen), „r“ = Antworten (Themen und Nachrichten)
            if (e.key === 'e') {
                var modeRoot = document.querySelector('[data-it-mail]');
                var editLink = document.querySelector('[data-it-pane] [data-it-edit]');
                if (modeRoot && modeRoot.getAttribute('data-mode') === 'knowledge' && editLink) { e.preventDefault(); window.location.href = editLink.href; return; }
            }
            // „f“ = Weiterleiten, „u“ = gelesen/ungelesen umschalten (Nachrichten)
            if (e.key === 'f') {
                var fwd = document.querySelector('[data-it-pane] [data-it-forward]');
                if (fwd) { e.preventDefault(); window.location.href = fwd.href; return; }
            }
            if (e.key === 'u') {
                var unreadForm = document.querySelector('[data-it-pane] form[data-it-unread]');
                if (unreadForm) { e.preventDefault(); if (unreadForm.requestSubmit) { unreadForm.requestSubmit(); } else { unreadForm.submit(); } return; }
            }
            if (e.key === 'r') {
                var replyLink = document.querySelector('[data-it-pane] [data-it-reply]');
                if (replyLink) { e.preventDefault(); replyLink.click(); return; }
            }
            var root = document.querySelector('[data-it-mail]');
            if (!root || !root.querySelector('.it-ml-items')) { return; }
            var active = document.activeElement;
            var focusOk = !active || active === document.body || !!closest(active, '.it-ml') || !!closest(active, '.it-mbx');
            if (!focusOk) { return; }

            var list = rows(root);
            if (!list.length) { return; }
            var current = root.querySelector('.it-ml-row.is-selected');
            var idx = current ? list.indexOf(current) : -1;

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                var next = e.key === 'ArrowDown' ? Math.min(list.length - 1, idx + 1) : Math.max(0, idx - 1);
                if (idx === -1) { next = 0; }
                if (next === idx) { return; }
                var row = list[next];
                row.focus({ preventScroll: true });
                row.scrollIntoView({ block: 'nearest' });
                loadPane(root, row, true);
            } else if (e.key === 'Enter' && current && (active === current || active === document.body || !active)) {
                e.preventDefault();
                window.location.href = current.getAttribute('data-view-href') || current.href;
            }
        });

        window.addEventListener('popstate', function () {
            if (document.querySelector('[data-it-mail][data-pane-url]')) { window.location.reload(); }
        });

        window.addEventListener('resize', function () {
            Array.prototype.forEach.call(document.querySelectorAll('[data-it-mail]'), fitHeight);
        });

        if (window.jQuery) {
            window.jQuery(document).on('rex:ready', initAll);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll);
        } else {
            initAll();
        }
    }
})();
