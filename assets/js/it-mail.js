/**
 * Issue Tracker – Postfach-Bedienung wie Mail (progressive Verbesserung):
 * Zeile anklicken lädt den Lesebereich nach, Pfeiltasten wechseln das Thema,
 * Eingabetaste öffnet die Vollansicht. Ohne JavaScript funktionieren alle Links und Formulare.
 */
(function () {
    'use strict';

    var controller = null;

    /* Vollbild-App (it-app.js) aktiv? Dann laufen Seitenwechsel per PJAX in der Ebene */
    function app() {
        return window.ITApp && window.ITApp.active() ? window.ITApp : null;
    }
    /* Zu einer Adresse wechseln: in der Ebene per PJAX, sonst normal */
    function go(url) {
        var a = app();
        if (a) { a.navigate(url); } else { window.location.href = url; }
    }
    function progress(on) {
        var a = app();
        if (a) { a.progress(on); }
    }
    /* REDAXO-Konvention: nach dem Einsetzen von Inhalt rex:ready auf dem Container auslösen */
    function ready(el) {
        if (window.jQuery) { window.jQuery(el).trigger('rex:ready', [window.jQuery(el)]); }
    }

    function closest(el, sel) {
        return el && el.closest ? el.closest(sel) : null;
    }

    function isTypingTarget(el) {
        if (!el) { return false; }
        var tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable || !!closest(el, '.CodeMirror');
    }

    /* Oberkante unter dem festen REDAXO-Kopf (für Scrollziele) */
    function stickyTop(root) {
        var v = root ? parseInt(window.getComputedStyle(root).getPropertyValue('--it-sticky-top'), 10) : NaN;
        return isNaN(v) ? 0 : v;
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

    function initPane(pane) {
        if (!pane) { return; }
        // Dateiauswahl: Anzahl anzeigen
        Array.prototype.forEach.call(pane.querySelectorAll('.it-file input[type="file"]'), function (input) {
            if (input.getAttribute('data-it-file-ready')) { return; }
            input.setAttribute('data-it-file-ready', '1');
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
        if (sticky && compose && !compose._itObserved && 'IntersectionObserver' in window) {
            compose._itObserved = true;
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { sticky.classList.toggle('is-hidden', e.isIntersecting); });
            });
            io.observe(compose);
            observers.push(io);
        }
    }
    var observers = [];

    /* Vollbild-App ab 1200 px: Spalten scrollen einzeln, die Seite nie */
    function inAppColumns(root) {
        return !!(root && closest(root, '[data-it-app]')) && window.innerWidth >= 1200;
    }

    /* Zeile sichtbar machen: zuerst in der eigenen Liste scrollen, auf Wunsch auch die Seite */
    function revealRow(root, row, withPage) {
        if (inAppColumns(root)) {
            var col = closest(row, '.it-ml');
            if (!col) { return; }
            var bar = col.querySelector('.it-ml-bar');
            var cr = col.getBoundingClientRect();
            var rr0 = row.getBoundingClientRect();
            var colTop = cr.top + (bar ? bar.offsetHeight : 0);
            if (rr0.top < colTop) { col.scrollTop -= colTop - rr0.top + 4; } else if (rr0.bottom > cr.bottom) { col.scrollTop += rr0.bottom - cr.bottom + 4; }
            return;
        }
        var list = closest(row, '.it-ml-items');
        var rr;
        if (list && list.scrollHeight > list.clientHeight + 1) {
            var lr = list.getBoundingClientRect();
            rr = row.getBoundingClientRect();
            if (rr.top < lr.top) { list.scrollTop -= lr.top - rr.top + 4; } else if (rr.bottom > lr.bottom) { list.scrollTop += rr.bottom - lr.bottom + 4; }
        }
        if (!withPage) { return; }
        rr = row.getBoundingClientRect();
        var top = stickyTop(root);
        if (rr.top < top) { window.scrollBy(0, rr.top - top - 8); } else if (rr.bottom > window.innerHeight) { window.scrollBy(0, rr.bottom - window.innerHeight + 8); }
    }

    /* Lesebereich nachladen (nur dieser Teil kommt vom Server, ?it_pane=1) */
    function loadPane(root, row, push, keepScroll) {
        var pane = root.querySelector('[data-it-pane]');
        if (!pane || !root.getAttribute('data-pane-url')) {
            go(row.href);
            return;
        }
        // Ungespeicherte Antwort im Lesebereich nicht stillschweigend verwerfen
        if (!keepScroll && app() && !app().canLeave(pane)) { return; }
        markSelected(root, row);
        root.classList.add('it-mail--sel');
        if (controller) { controller.abort(); }
        controller = 'AbortController' in window ? new AbortController() : null;
        var url = row.href + (row.href.indexOf('?') === -1 ? '?' : '&') + 'it_pane=1';
        pane.setAttribute('aria-busy', 'true');
        if (!keepScroll) { progress(true); }
        var scrollBox = pane.querySelector('.it-rp-scroll');
        var oldScroll = scrollBox ? scrollBox.scrollTop : 0;
        fetch(url, { credentials: 'same-origin', signal: controller ? controller.signal : undefined, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.text();
            })
            .then(function (html) {
                if (html.indexOf('it-rp') === -1 && html.indexOf('alert') === -1) { throw new Error('unexpected'); }
                document.dispatchEvent(new CustomEvent('it:unload', { detail: { root: pane } }));
                pane.innerHTML = html;
                if (window.ITEditors) { window.ITEditors.destroy(null); }
                pane.removeAttribute('aria-busy');
                progress(false);
                if (keepScroll) {
                    var sb = pane.querySelector('.it-rp-scroll');
                    if (sb) { sb.scrollTop = oldScroll; }
                }
                // Das Fenster folgt dem Seitenfluss: liegt der Anfang des Lesebereichs über dem Fenster, dorthin zurück
                if (window.innerWidth >= 1200 && !keepScroll && !inAppColumns(root)) {
                    var paneTop = pane.getBoundingClientRect().top;
                    var top = stickyTop(root);
                    if (paneTop < top) { window.scrollTo(0, Math.max(0, window.pageYOffset + paneTop - top - 8)); }
                }
                if (push && app()) {
                    app().push(row.href);
                } else if (push && window.history && window.history.pushState) {
                    window.history.pushState({ itIssue: row.getAttribute('data-issue-id') }, '', row.href);
                }
                initPane(pane);
                restoreSections(pane);
                Array.prototype.forEach.call(pane.querySelectorAll('.it-compose'), initDrop);
                // Editoren, Auswahllisten (REDAXO), Formulare: wie nach einem PJAX-Wechsel
                ready(pane);
                initForms(pane);
                labelToolbars(pane);
                if (window.innerWidth < 1200 && !keepScroll) { root.scrollIntoView({ block: 'start' }); }
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') { return; }
                progress(false);
                go(row.href);
            });
    }

    /* Zurück/Vor in der Ebene: nur den Lesebereich tauschen, wenn dieselbe Liste angezeigt wird */
    function listKey(href) {
        var u;
        try { u = new URL(href, window.location.href); } catch (e) { return ''; }
        ['issue_id', 'message_id', 'it_pane', 'screen'].forEach(function (k) { u.searchParams.delete(k); });
        u.searchParams.sort();
        return u.pathname + '?' + u.searchParams.toString();
    }
    function tryPane(url, prevUrl) {
        var root = document.querySelector('[data-it-mail][data-pane-url]');
        if (!root || !root.querySelector('[data-it-pane]') || listKey(url) !== listKey(prevUrl)) { return false; }
        var id = new URL(url, window.location.href).searchParams.get('issue_id');
        var row = id ? root.querySelector('.it-ml-row[data-issue-id="' + id.replace(/[^0-9]/g, '') + '"]') : null;
        if (!row) { return false; }
        loadPane(root, row, false);
        revealRow(root, row, false);
        return true;
    }

    /* „Weitere laden“: nächste Einträge anhängen – Auswahl, Fokus, Tastatur und Scrollposition bleiben erhalten */
    function loadMore(root, link) {
        var list = root.querySelector('.it-ml-items');
        if (!list || link.getAttribute('aria-busy') === 'true') { return; }
        var sel = root.querySelector('.it-ml-row.is-selected');
        var url = new URL(link.href, window.location.href);
        if (sel) { url.searchParams.set('issue_id', sel.getAttribute('data-issue-id')); }
        var before = list.children.length;
        var hadFocus = document.activeElement === link;
        link.setAttribute('aria-busy', 'true');
        link.classList.add('is-busy');
        fetch(url.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newList = doc.querySelector('[data-it-mail] .it-ml-items');
                if (!newList || newList.children.length <= before) { throw new Error('unexpected'); }
                var listScroll = list.scrollTop;
                // Bestehende Zeilen behalten (Fokus, Auswahl), nur ihre Links auf den neuen Umfang setzen
                Array.prototype.forEach.call(list.querySelectorAll('.it-ml-row'), function (r) {
                    var fresh = newList.querySelector('.it-ml-row[data-issue-id="' + r.getAttribute('data-issue-id') + '"]');
                    if (fresh) { r.setAttribute('href', fresh.getAttribute('href')); }
                });
                var firstNew = null;
                Array.prototype.slice.call(newList.children, before).forEach(function (li) {
                    var node = document.importNode(li, true);
                    var nr = node.querySelector('.it-ml-row');
                    if (nr) {
                        nr.classList.remove('is-selected');
                        nr.removeAttribute('aria-current');
                    }
                    list.appendChild(node);
                    if (!firstNew) { firstNew = nr; }
                });
                list.scrollTop = listScroll;
                var oldMore = closest(link, '.it-ml-more');
                var newMore = doc.querySelector('[data-it-mail] .it-ml-more');
                if (oldMore) {
                    if (newMore) { oldMore.parentNode.replaceChild(document.importNode(newMore, true), oldMore); } else { oldMore.parentNode.removeChild(oldMore); }
                }
                var status = root.querySelector('[data-it-more-status]');
                var newStatus = doc.querySelector('[data-it-more-status]');
                if (status && newStatus) { status.textContent = newStatus.getAttribute('data-msg') || ''; }
                if (window.history && window.history.replaceState) { window.history.replaceState(window.history.state, '', url.href); }
                if (hadFocus && firstNew) {
                    firstNew.focus({ preventScroll: true });
                    revealRow(root, firstNew, true);
                }
            })
            .catch(function () {
                go(url.href);
            });
    }

    function initRoot(root) {
        if (root.getAttribute('data-it-ready')) { return; }
        root.setAttribute('data-it-ready', '1');
        initPane(root.querySelector('[data-it-pane]'));
        var sel = root.querySelector('.it-ml-row.is-selected');
        if (sel) { revealRow(root, sel, false); }
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

    /* Aktueller Inhalt eines Formulars (für die Warnung bei ungespeicherten Änderungen) */
    function formState(form) {
        var parts = [];
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.disabled || el.name === '_csrf_token') { return; }
            if (el.type === 'file') { parts.push(el.name + '#' + (el.files ? el.files.length : 0)); return; }
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { return; }
            var v = el.value;
            if (el.tagName === 'TEXTAREA') {
                var box = closest(el, '.EasyMDEContainer') || (el.nextElementSibling && el.nextElementSibling.classList.contains('EasyMDEContainer') ? el.nextElementSibling : null);
                var cm = box ? box.querySelector('.CodeMirror') : null;
                if (cm && cm.CodeMirror) { v = cm.CodeMirror.getValue(); }
            }
            parts.push(el.name + '=' + v);
        });
        return parts.join('\n');
    }

    /* Ungültiges Feld in „Weitere Angaben“: Bereich automatisch öffnen; vor dem Verlassen mit ungespeicherten Änderungen warnen */
    function initForms(scope) {
        Array.prototype.forEach.call(scope.querySelectorAll('form[data-it-form]'), function (form) {
            if (form.getAttribute('data-it-form-ready')) { return; }
            form.setAttribute('data-it-form-ready', '1');
            form.addEventListener('invalid', function (e) {
                var more = closest(e.target, 'details');
                if (more && !more.open) { more.open = true; }
            }, true);

            var msg = form.getAttribute('data-it-unsaved');
            if (!msg) { return; }
            // Nach einem Fehler beim Absenden sind die Eingaben noch nicht gespeichert
            var initial = form.querySelector('.it-form-summary') ? null : formState(form);
            var isDirty = function () { return initial === null || formState(form) !== initial; };
            form._itLeaving = false;
            // Für die Warnung beim Verlassen (Seite, PJAX-Wechsel, Schließen der Ebene)
            form._itDirty = function () { return !form._itLeaving && document.documentElement.contains(form) && isDirty(); };
            Array.prototype.forEach.call(form.querySelectorAll('[data-it-cancel]'), function (a) {
                a.addEventListener('click', function (e) {
                    if (!isDirty()) { form._itLeaving = true; return; }
                    if (window.confirm(msg)) { form._itLeaving = true; } else { e.preventDefault(); e.stopImmediatePropagation(); }
                });
            });
        });
    }

    /* Meldung des ersten Formulars mit ungespeicherten Änderungen (optional nur in scope, ohne except) */
    function dirtyMessage(scope, except) {
        var forms = (scope || document).querySelectorAll('form[data-it-form][data-it-unsaved]');
        for (var i = 0; i < forms.length; i++) {
            if (forms[i] !== except && forms[i]._itDirty && forms[i]._itDirty()) { return forms[i].getAttribute('data-it-unsaved'); }
        }
        return '';
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

    // Zuletzt genutztes Postfach: Der Server merkt es beim Anzeigen nur vor, gespeichert wird per POST mit Token
    function saveLastBox(scope) {
        var nav = (scope.matches && scope.matches('[data-it-last-box]')) ? scope : scope.querySelector('[data-it-last-box]');
        if (!nav || !window.fetch) { return; }
        nav.removeAttribute('data-it-last-box');
        var body = new FormData();
        body.append('it_ui_state', '1');
        body.append('key', 'last_box');
        body.append('_csrf_token', nav.getAttribute('data-it-state-token'));
        fetch(nav.getAttribute('data-it-state-url'), { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
    }

    // Schriftgröße (90–140 %) – Klasse am body, gespeichert pro Benutzer
    var FONT_STEPS = [90, 100, 110, 120, 130, 140];
    function currentFontScale() {
        var m = document.body.className.match(/\bit-scale-(\d+)\b/);
        return m ? parseInt(m[1], 10) : 100;
    }
    function initFontSize(root) {
        Array.prototype.forEach.call(root.querySelectorAll('[data-it-fontsize]'), function (group) {
            if (group.getAttribute('data-it-fontsize-ready')) { return; }
            group.setAttribute('data-it-fontsize-ready', '1');
            var value = group.querySelector('.it-fontsize-value');
            var show = function () { if (value) { value.textContent = currentFontScale() + ' %'; } };
            show();
            group.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-it-fontsize-step]');
                if (!btn) { return; }
                var step = parseInt(btn.getAttribute('data-it-fontsize-step'), 10);
                var idx = FONT_STEPS.indexOf(currentFontScale());
                var next = step === 0 ? 100 : FONT_STEPS[Math.max(0, Math.min(FONT_STEPS.length - 1, (idx < 0 ? 1 : idx) + step))];
                FONT_STEPS.forEach(function (s) { document.body.classList.remove('it-scale-' + s); });
                if (next !== 100) { document.body.classList.add('it-scale-' + next); }
                show();
                var nav = document.querySelector('[data-it-state-url]');
                if (!nav || !window.fetch) { return; }
                var body = new FormData();
                body.append('it_ui_state', '1');
                body.append('key', 'font_scale');
                body.append('value', String(next));
                body.append('_csrf_token', nav.getAttribute('data-it-state-token'));
                fetch(nav.getAttribute('data-it-state-url'), { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
            });
        });
    }

    // Spaltenbreiten ziehen: Postfächer und Liste, begrenzt, pro Benutzer gespeichert
    var COLS = { mbx: { min: 170, max: 340, def: 214 }, ml: { min: 260, max: 620, def: 310 } };
    var READ_MIN = 380;
    // Tatsächliche Breite der Spalte (gesetzt oder Standard aus dem CSS je Fensterbreite)
    function colWidth(key) {
        var el = document.querySelector(key === 'mbx' ? '[data-it-mail] .it-mbx' : '[data-it-mail] .it-ml');
        var w = el ? Math.round(el.getBoundingClientRect().width) : 0;
        return w > 0 ? w : COLS[key].def;
    }
    function setCol(key, px) {
        document.body.style.setProperty('--it-col-' + key, Math.round(px) + 'px');
    }
    var saveColsTimer = null;
    function saveCols() {
        clearTimeout(saveColsTimer);
        saveColsTimer = setTimeout(function () {
            var nav = document.querySelector('[data-it-state-url]');
            if (!nav || !window.fetch) { return; }
            var body = new FormData();
            body.append('it_ui_state', '1');
            body.append('key', 'columns');
            var set = document.body.style.getPropertyValue('--it-col-mbx') !== '' || document.body.style.getPropertyValue('--it-col-ml') !== '';
            body.append('value', set ? colWidth('mbx') + ',' + colWidth('ml') : 'reset');
            body.append('_csrf_token', nav.getAttribute('data-it-state-token'));
            fetch(nav.getAttribute('data-it-state-url'), { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
        }, 300);
    }
    function initColumns(root) {
        if (root.getAttribute('data-it-cols-ready')) { return; }
        root.setAttribute('data-it-cols-ready', '1');
        var labelEl = document.querySelector('[data-it-cols-label]');
        var label = labelEl ? labelEl.getAttribute('data-it-cols-label') : '';
        var keys = root.classList.contains('it-mail--full') || root.classList.contains('it-mail--table') ? ['mbx'] : ['mbx', 'ml'];
        keys.forEach(function (key) {
            var h = document.createElement('div');
            h.className = 'it-col-handle';
            h.setAttribute('role', 'separator');
            h.setAttribute('aria-orientation', 'vertical');
            h.setAttribute('tabindex', '0');
            h.setAttribute('aria-valuemin', String(COLS[key].min));
            h.setAttribute('aria-valuemax', String(COLS[key].max));
            if (label) { h.setAttribute('aria-label', label); h.title = label; }
            root.appendChild(h);
            var place = function () {
                var left = colWidth('mbx') + (key === 'ml' ? colWidth('ml') : 0);
                h.style.left = left + 'px';
                h.setAttribute('aria-valuenow', String(colWidth(key)));
            };
            var clamp = function (px) {
                var other = key === 'mbx' ? (keys.length > 1 ? colWidth('ml') : 0) : colWidth('mbx');
                var max = Math.min(COLS[key].max, root.clientWidth - other - READ_MIN);
                return Math.max(COLS[key].min, Math.min(max, px));
            };
            place();
            h.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) { return; }
                e.preventDefault();
                h.setPointerCapture(e.pointerId);
                var startX = e.clientX;
                var start = colWidth(key);
                h.classList.add('is-dragging');
                root.classList.add('is-resizing');
                // Beim ersten Ziehen beide Spalten festschreiben, damit die andere nicht auf den Standard springt
                keys.forEach(function (k) { if (document.body.style.getPropertyValue('--it-col-' + k) === '') { setCol(k, colWidth(k)); } });
                var move = function (ev) { setCol(key, clamp(start + ev.clientX - startX)); placeAll(); };
                var up = function () {
                    h.removeEventListener('pointermove', move);
                    h.removeEventListener('pointerup', up);
                    h.removeEventListener('pointercancel', up);
                    h.classList.remove('is-dragging');
                    root.classList.remove('is-resizing');
                    saveCols();
                };
                h.addEventListener('pointermove', move);
                h.addEventListener('pointerup', up);
                h.addEventListener('pointercancel', up);
            });
            h.addEventListener('keydown', function (e) {
                var step = e.shiftKey ? 48 : 16;
                if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    e.preventDefault();
                    setCol(key, clamp(colWidth(key) + (e.key === 'ArrowRight' ? step : -step)));
                } else if (e.key === 'Home' || e.key === 'End') {
                    e.preventDefault();
                    setCol(key, clamp(e.key === 'Home' ? COLS[key].min : COLS[key].max));
                } else { return; }
                placeAll();
                saveCols();
            });
            // Doppelklick: Standardbreite (aus dem CSS)
            h.addEventListener('dblclick', function () { document.body.style.removeProperty('--it-col-' + key); placeAll(); saveCols(); });
            h._place = place;
        });
        function placeAll() { Array.prototype.forEach.call(root.querySelectorAll('.it-col-handle'), function (x) { if (x._place) { x._place(); } }); }
        root._itPlaceAll = placeAll;
    }

    /* ---------- Gemeinsame Helfer für Tag-Auswahl ---------- */
    function esc(str) {
        return String(str).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }
    function colorStyle(c) {
        return /^#[0-9a-f]{3,8}$/i.test(c || '') ? '--c:' + c : '--c:#7c7c82';
    }
    function parseJson(str, fallback) {
        try { return JSON.parse(str || ''); } catch (e) { return fallback; }
    }
    // API-Aufruf per POST; der CSRF-Token steht im Formular-Body (data-…-token), nie in der Adresse
    function postApi(url, data, token) {
        var body = new FormData();
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        if (token) { body.append('_csrf_token', token); }
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (res) {
                return res.text().then(function (txt) {
                    var json = parseJson(txt, null);
                    if (!res.ok || !json || !json.success) { throw new Error(json && json.message ? json.message : 'HTTP ' + res.status); }
                    return json;
                });
            });
    }
    function tagOption(tag, checked, type, name) {
        var label = document.createElement('label');
        label.className = 'it-menu-item it-qchip-opt';
        label.innerHTML = '<input type="' + type + '"' + (name ? ' name="' + name + '"' : '') + ' value="' + (parseInt(tag.id, 10) || 0) + '"' + (checked ? ' checked' : '') + '>'
            + '<i class="rex-icon fa-check" aria-hidden="true"></i>'
            + '<span data-it-qchip-text><span class="it-dot it-dot--custom it-dot--sm" style="' + esc(colorStyle(tag.color)) + '" aria-hidden="true"></span> ' + esc(tag.name) + '</span>';
        return label;
    }
    /* Suchfeld filtert Einträge; liefert die Zahl der sichtbaren und ob es einen exakten Treffer gibt */
    function filterOptions(container, q) {
        q = (q || '').trim().toLowerCase();
        var visible = 0;
        var exact = false;
        Array.prototype.forEach.call(container.querySelectorAll('.it-qchip-opt'), function (opt) {
            var text = (opt.textContent || '').trim().toLowerCase();
            var show = q === '' || text.indexOf(q) !== -1;
            opt.hidden = !show;
            if (show) { visible++; }
            if (text === q) { exact = true; }
        });
        var empty = container.querySelector('.it-qchip-empty');
        if (empty) { empty.hidden = visible > 0; }
        return { visible: visible, exact: exact, q: q };
    }

    /* ---------- Eigenschaften-Leiste im Formular (Anlegen/Bearbeiten) ---------- */
    function initQbar(bar) {
        if (bar.getAttribute('data-it-qbar-ready')) { return; }
        bar.setAttribute('data-it-qbar-ready', '1');
        var form = closest(bar, 'form');
        var labels = parseJson(bar.getAttribute('data-it-qbar-labels'), {});
        var tagApi = bar.getAttribute('data-it-tag-api') || '';
        var tagApiToken = bar.getAttribute('data-it-tag-token') || '';
        var help = form ? form.querySelector('.it-qbar-help') : null;
        var helpDefault = help ? help.innerHTML : '';
        var chips = Array.prototype.slice.call(bar.querySelectorAll('[data-it-qchip]'));

        function setValue(chip, html, isText) {
            var el = chip.querySelector('[data-it-qchip-value]');
            if (!el) { return; }
            if (html === null || html === '') { el.textContent = el.getAttribute('data-empty') || ''; el.classList.add('is-empty'); return; }
            el.classList.remove('is-empty');
            if (isText) { el.textContent = html; } else { el.innerHTML = html; }
        }
        function update(chip) {
            var type = chip.getAttribute('data-it-qchip');
            if (type === 'single') {
                var checked = chip.querySelector('input[type="radio"]:checked');
                var text = checked ? checked.parentNode.querySelector('[data-it-qchip-text]') : null;
                setValue(chip, text && !text.hasAttribute('data-it-qchip-none') ? text.innerHTML : null, false);
                if (checked && chip.hasAttribute('data-it-qchip-required')) { chip.classList.remove('has-error'); }
            } else if (type === 'multi') {
                var names = Array.prototype.map.call(chip.querySelectorAll('input[type="checkbox"]:checked'), function (c) { return (c.parentNode.textContent || '').trim(); });
                setValue(chip, names.length > 3 ? names.slice(0, 2).join(', ') + ' +' + (names.length - 2) : names.join(', '), true);
            } else if (type === 'due') {
                var input = chip.querySelector('input[type="datetime-local"]');
                var v = input ? input.value : '';
                var d = v ? new Date(v) : null;
                var lang = document.documentElement.lang || undefined;
                setValue(chip, d && !isNaN(d.getTime()) ? d.toLocaleString(lang, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : null, true);
            } else if (type === 'page') {
                var name = chip.querySelector('[data-it-page-widget] input[readonly]');
                var url = chip.querySelector('input[name="affected_url"]');
                var label = name && name.value ? name.value : (url && url.value.trim() ? url.value.trim() : '');
                setValue(chip, label, true);
                var clearBtn = chip.querySelector('[data-it-page-clear]');
                if (clearBtn) { clearBtn.hidden = label === ''; }
            }
        }
        function closeChip(chip) {
            chip.open = false;
            var sum = chip.querySelector('summary');
            if (sum) { sum.focus(); }
        }

        chips.forEach(function (chip) {
            var type = chip.getAttribute('data-it-qchip');
            var lastPointer = 0;
            chip.addEventListener('pointerdown', function () { lastPointer = Date.now(); });
            chip.addEventListener('change', function (e) {
                update(chip);
                // Einfachauswahl per Zeiger übernimmt und schließt; Pfeiltasten wechseln nur die Auswahl
                if (type === 'single' && e.target.matches('input[type="radio"]') && Date.now() - lastPointer < 1500) {
                    setTimeout(function () { closeChip(chip); }, 0);
                }
            });
            chip.addEventListener('input', function (e) {
                if (e.target.matches('[data-it-qchip-search]')) {
                    var r = filterOptions(chip, e.target.value);
                    var nb = chip.querySelector('[data-it-qchip-new]');
                    if (nb) {
                        nb.hidden = !(r.q !== '' && !r.exact && tagApi);
                        nb.querySelector('span').textContent = String(labels['new'] || '{0}').replace('{0}', e.target.value.trim());
                    }
                } else if (type === 'page' || type === 'due') {
                    update(chip);
                }
            });
            chip.addEventListener('click', function (e) {
                if (type === 'single' && e.target.matches('input[type="radio"]') && Date.now() - lastPointer < 1500 && chip.open) {
                    setTimeout(function () { closeChip(chip); }, 0);
                }
                if (closest(e.target, '[data-it-qchip-close]')) { e.preventDefault(); closeChip(chip); }
                if (closest(e.target, '[data-it-qchip-clear]')) {
                    e.preventDefault();
                    var inp = chip.querySelector('input[type="datetime-local"]');
                    if (inp) { inp.value = ''; }
                    update(chip);
                }
                if (type === 'page' && closest(e.target, '[data-it-page-widget] a')) { setTimeout(function () { update(chip); }, 0); }
                // „Keine Seite“: Artikel und Adresse leeren
                if (type === 'page' && closest(e.target, '[data-it-page-clear]')) {
                    e.preventDefault();
                    Array.prototype.forEach.call(chip.querySelectorAll('[data-it-page-widget] input, input[name="affected_url"]'), function (inp) { inp.value = ''; });
                    if (urlInput) { showUrlError(false); }
                    update(chip);
                    var sumEl = chip.querySelector('summary');
                    chip.open = false;
                    if (sumEl) { sumEl.focus(); }
                }
                var nb = closest(e.target, '[data-it-qchip-new]');
                if (nb) { e.preventDefault(); createTag(chip); }
            });
            // Eingabetaste im Menü schickt das Formular nicht ab
            chip.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' || !closest(e.target, '.it-menu-pop')) { return; }
                if (e.target.matches('button, a')) { return; }
                e.preventDefault();
                if (e.target.matches('[data-it-qchip-search]')) {
                    var first = Array.prototype.filter.call(chip.querySelectorAll('.it-qchip-opt'), function (o) { return !o.hidden; })[0];
                    var nb = chip.querySelector('[data-it-qchip-new]');
                    if (nb && !nb.hidden) { createTag(chip); return; }
                    if (first) {
                        var inp = first.querySelector('input');
                        if (inp.type === 'radio') { inp.checked = true; } else { inp.checked = !inp.checked; }
                        inp.dispatchEvent(new Event('change', { bubbles: true }));
                        if (type === 'single') { closeChip(chip); }
                    }
                    return;
                }
                if (e.target.matches('input[type="radio"], input[type="checkbox"]')) {
                    if (e.target.type === 'checkbox') { e.target.checked = !e.target.checked; e.target.dispatchEvent(new Event('change', { bubbles: true })); return; }
                    e.target.checked = true;
                    e.target.dispatchEvent(new Event('change', { bubbles: true }));
                }
                closeChip(chip);
            });
            chip.addEventListener('toggle', function () {
                // Linkmap-Popup schreibt ohne Ereignis ins Feld: solange das Menü offen ist, nachsehen
                if (type === 'page') {
                    clearInterval(chip._itPoll);
                    if (chip.open) {
                        chip._itPoll = setInterval(function () {
                            if (!document.documentElement.contains(chip)) { clearInterval(chip._itPoll); return; }
                            update(chip);
                        }, 400);
                    }
                }
                if (!chip.open) { update(chip); return; }
                update(chip);
                var search = chip.querySelector('[data-it-qchip-search]');
                if (search && window.matchMedia && window.matchMedia('(pointer: fine)').matches) { search.focus(); }
            });
        });

        function createTag(chip) {
            var search = chip.querySelector('[data-it-qchip-search]');
            var name = search ? search.value.trim() : '';
            if (!name || !tagApi) { return; }
            postApi(tagApi, { action: 'create', name: name }, tagApiToken).then(function (json) {
                var list = chip.querySelector('[data-it-qchip-list]');
                var existing = list.querySelector('input[value="' + parseInt(json.tag.id, 10) + '"]');
                if (existing) { existing.checked = true; } else { list.appendChild(tagOption(json.tag, true, 'checkbox', 'tags[]')); }
                search.value = '';
                filterOptions(chip, '');
                chip.querySelector('[data-it-qchip-new]').hidden = true;
                update(chip);
                search.focus();
            }, function () {
                if (help) { help.innerHTML = '<span class="it-field-error">' + esc(labels.error || '') + '</span>'; }
            });
        }

        // Linkmap (Popup) schreibt die Auswahl direkt ins Feld – danach anzeigen (Ereignisse einmal global gebunden, s. u.)
        bar._itUpdatePage = function () { chips.forEach(function (c) { if (c.getAttribute('data-it-qchip') === 'page') { update(c); } }); };
        bar._itUpdateAll = function () { chips.forEach(update); };

        // Adresse der betroffenen Seite: gleiche Regel wie auf dem Server (https://… oder Pfad ab „/“), Hinweis direkt am Feld
        var urlInput = bar.querySelector('input[name="affected_url"]');
        var urlChip = urlInput ? closest(urlInput, '[data-it-qchip]') : null;
        function urlValid(v) {
            v = (v || '').trim();
            if (v === '') { return true; }
            if (v.length > 500 || /\s/.test(v)) { return false; }
            if (v.charAt(0) === '/' && v.charAt(1) !== '/') { return true; }
            if (!/^https?:\/\//i.test(v)) { return false; }
            try { return !!new URL(v).hostname; } catch (err) { return false; }
        }
        function showUrlError(on) {
            if (!urlInput) { return true; }
            var errId = 'issue-affected-url-error';
            var err = document.getElementById(errId);
            if (on && !err) {
                err = document.createElement('p');
                err.className = 'it-field-help';
                err.id = errId;
                err.innerHTML = '<span class="it-field-error"></span>';
                urlInput.parentNode.insertBefore(err, urlInput.nextSibling);
            }
            if (err) {
                err.hidden = !on;
                err.querySelector('.it-field-error').textContent = labels.url || '';
            }
            if (on) {
                urlInput.setAttribute('aria-invalid', 'true');
                urlInput.setAttribute('aria-describedby', errId);
            } else {
                urlInput.removeAttribute('aria-invalid');
                urlInput.removeAttribute('aria-describedby');
            }
            if (urlChip) { urlChip.classList.toggle('has-error', on); }
            return !on;
        }
        if (urlInput) {
            urlInput.addEventListener('change', function () { showUrlError(!urlValid(urlInput.value)); });
            urlInput.addEventListener('input', function () { if (urlValid(urlInput.value)) { showUrlError(false); } });
        }

        // Pflichtfeld „Zuweisen an“: vor dem Absenden prüfen, Menü öffnen und Hinweis zeigen
        if (form) {
            form.addEventListener('submit', function (e) {
                if (urlInput && !urlValid(urlInput.value)) {
                    e.preventDefault();
                    showUrlError(true);
                    if (help) { help.innerHTML = '<span class="it-field-error">' + esc(labels.url || '') + '</span>'; }
                    if (urlChip) { urlChip.open = true; }
                    urlInput.focus();
                    return;
                }
                var missing = chips.filter(function (c) { return c.hasAttribute('data-it-qchip-required') && !c.querySelector('input:checked'); })[0];
                if (!missing) { if (help) { help.innerHTML = helpDefault; } return; }
                e.preventDefault();
                missing.classList.add('has-error');
                if (help) { help.innerHTML = '<span class="it-field-error">' + esc(labels.assign || '') + '</span>'; }
                missing.open = true;
                var sum = missing.querySelector('summary');
                if (sum) { sum.focus(); }
            });
        }
    }

    /* ---------- Schnelles Tagging in der Liste (Knopf je Zeile, Taste T) ---------- */
    var qtag = null;
    function closeQtag(focusBack) {
        if (!qtag) { return; }
        var btn = qtag.btn;
        if (qtag.pop.parentNode) { qtag.pop.parentNode.removeChild(qtag.pop); }
        btn.setAttribute('aria-expanded', 'false');
        var li = closest(btn, 'li');
        if (li) { li.classList.remove('is-tagging'); }
        qtag = null;
        if (focusBack) { btn.focus(); }
    }
    function placeQtag() {
        if (!qtag) { return; }
        var pop = qtag.pop;
        if (window.innerWidth < 768) { pop.style.top = ''; pop.style.left = ''; return; }
        var r = qtag.btn.getBoundingClientRect();
        var w = pop.offsetWidth;
        var h = pop.offsetHeight;
        var left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8));
        var top = r.bottom + 4;
        if (top + h > window.innerHeight - 8 && r.top - h - 4 > 8) { top = r.top - h - 4; }
        pop.style.left = Math.round(left) + 'px';
        pop.style.top = Math.round(Math.max(8, top)) + 'px';
    }
    function renderRowTags(issueId, tags) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-it-row-tags="' + issueId + '"]'), function (box) {
            var max = parseInt(box.getAttribute('data-max'), 10) || 0;
            var shown = max > 0 ? tags.slice(0, max) : tags;
            var html = shown.map(function (t) {
                return '<span class="it-chip"><span class="it-dot it-dot--custom it-dot--sm" style="' + esc(colorStyle(t.color)) + '" aria-hidden="true"></span>' + esc(t.name) + '</span>';
            }).join('');
            if (max > 0 && tags.length > max) { html += '<span class="it-chip">+' + (tags.length - max) + '</span>'; }
            box.innerHTML = html;
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-it-qtag][data-issue-id="' + issueId + '"]'), function (b) {
            b.setAttribute('data-tag-ids', tags.map(function (t) { return t.id; }).join(','));
        });
    }
    function openQtag(btn) {
        closeQtag(false);
        Array.prototype.forEach.call(document.querySelectorAll('details.it-menu[open]'), function (d) { d.removeAttribute('open'); });
        var ml = closest(btn, '[data-it-qtag-api]');
        if (!ml) { return; }
        var api = ml.getAttribute('data-it-qtag-api');
        var apiToken = ml.getAttribute('data-it-qtag-token') || '';
        var allTags = parseJson(ml.getAttribute('data-it-qtag-tags'), []);
        var canCreate = ml.getAttribute('data-it-qtag-create') === '1';
        var labels = parseJson(ml.getAttribute('data-it-qtag-labels'), {});
        var issueId = btn.getAttribute('data-issue-id');
        var current = (btn.getAttribute('data-tag-ids') || '').split(',').filter(Boolean);
        var title = String(labels['for'] || '#{0}').replace('{0}', issueId);

        var pop = document.createElement('div');
        pop.className = 'it-qtag-pop';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', title + ' – ' + (btn.getAttribute('data-issue-title') || ''));
        pop.innerHTML = '<div class="it-qtag-head"><span class="it-qtag-title">' + esc(title) + '</span>'
            + '<button type="button" class="it-tb-btn it-tb-btn--sm" data-it-qtag-close title="' + esc(labels.close || '') + '"><span aria-hidden="true">&times;</span><span class="sr-only">' + esc(labels.close || '') + '</span></button></div>'
            + '<input type="search" class="it-qchip-search" placeholder="' + esc(labels.search || '') + '" aria-label="' + esc(labels.search || '') + '">'
            + '<div class="it-qtag-list" role="group" aria-label="' + esc(title) + '"></div>'
            + '<p class="it-qchip-empty" hidden>' + esc(labels.none || '') + '</p>'
            + (allTags.length ? '' : '<p class="it-qtag-empty">' + esc(labels.empty || '') + '</p>')
            + (canCreate ? '<button type="button" class="it-menu-item it-qchip-new" hidden><i class="rex-icon fa-plus" aria-hidden="true"></i> <span></span></button>' : '')
            + '<p class="it-qtag-msg" role="status" aria-live="polite"></p>';
        var list = pop.querySelector('.it-qtag-list');
        allTags.forEach(function (t) { list.appendChild(tagOption(t, current.indexOf(String(t.id)) !== -1, 'checkbox', '')); });
        document.body.appendChild(pop);
        btn.setAttribute('aria-expanded', 'true');
        var li = closest(btn, 'li');
        if (li) { li.classList.add('is-tagging'); }
        qtag = { pop: pop, btn: btn, issueId: issueId };
        placeQtag();

        var search = pop.querySelector('.it-qchip-search');
        var newBtn = pop.querySelector('.it-qchip-new');
        var msg = pop.querySelector('.it-qtag-msg');
        // Ohne Tags und ohne Recht zum Anlegen gibt es nichts zu suchen
        if (!allTags.length && !canCreate) { search.hidden = true; }
        var root = closest(btn, '[data-it-mail]');

        function done(json) {
            renderRowTags(issueId, json.tags || []);
            msg.textContent = labels.saved || '';
            msg.classList.remove('is-error');
            // Zeigt der Lesebereich dasselbe Thema, still neu laden (Tags im Kopf aktuell halten)
            var paneIssue = root ? root.querySelector('[data-it-pane] .it-rp[data-issue-id="' + issueId + '"]') : null;
            var row = root ? root.querySelector('.it-ml-row[data-issue-id="' + issueId + '"]') : null;
            var paneEl = root ? root.querySelector('[data-it-pane]') : null;
            if (paneIssue && row && !dirtyMessage(paneEl)) { loadPane(root, row, false, true); }
        }
        function fail(input) {
            if (input) { input.checked = !input.checked; }
            msg.textContent = labels.error || '';
            msg.classList.add('is-error');
        }
        // Sofort anzeigen (optimistisch), dann speichern; bei Fehler zurücknehmen
        function localTags() {
            var on = Array.prototype.map.call(pop.querySelectorAll('.it-qtag-list input:checked'), function (c) { return String(c.value); });
            return allTags.filter(function (t) { return on.indexOf(String(t.id)) !== -1; });
        }
        var pending = 0;
        pop.addEventListener('change', function (e) {
            var input = e.target;
            if (!input.matches('input[type="checkbox"]')) { return; }
            var before = (btn.getAttribute('data-tag-ids') || '').split(',').filter(Boolean);
            renderRowTags(issueId, localTags());
            msg.textContent = '';
            pending++;
            postApi(api, { action: 'toggle', issue_id: issueId, tag_id: input.value, on: input.checked ? '1' : '0' }, apiToken)
                .then(function (json) {
                    pending--;
                    // Erst nach der letzten Antwort den Serverstand übernehmen (schnelles Mehrfachklicken)
                    if (pending === 0) { done(json); }
                }, function () {
                    pending--;
                    fail(input);
                    renderRowTags(issueId, allTags.filter(function (t) { return before.indexOf(String(t.id)) !== -1; }));
                });
        });
        function create() {
            var name = search.value.trim();
            if (!name) { return; }
            postApi(api, { action: 'create', name: name, issue_id: issueId }, apiToken).then(function (json) {
                if (!allTags.some(function (t) { return String(t.id) === String(json.tag.id); })) {
                    allTags.push(json.tag);
                    ml.setAttribute('data-it-qtag-tags', JSON.stringify(allTags));
                    list.appendChild(tagOption(json.tag, true, 'checkbox', ''));
                } else {
                    var ex = list.querySelector('input[value="' + parseInt(json.tag.id, 10) + '"]');
                    if (ex) { ex.checked = true; }
                }
                search.value = '';
                filterOptions(pop, '');
                newBtn.hidden = true;
                var emptyNote = pop.querySelector('.it-qtag-empty');
                if (emptyNote) { emptyNote.parentNode.removeChild(emptyNote); }
                done(json);
                search.focus();
            }, function () { fail(null); });
        }
        search.addEventListener('input', function () {
            var r = filterOptions(pop, search.value);
            if (newBtn) {
                newBtn.hidden = !(r.q !== '' && !r.exact);
                newBtn.querySelector('span').textContent = String(labels['new'] || '{0}').replace('{0}', search.value.trim());
            }
        });
        pop.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target === search) {
                e.preventDefault();
                if (newBtn && !newBtn.hidden) { create(); return; }
                var first = Array.prototype.filter.call(pop.querySelectorAll('.it-qchip-opt'), function (o) { return !o.hidden; })[0];
                if (first) { var cb = first.querySelector('input'); cb.checked = !cb.checked; cb.dispatchEvent(new Event('change', { bubbles: true })); }
            } else if (e.key === 'Tab') {
                // Fokus im Fenster halten
                var f = Array.prototype.filter.call(pop.querySelectorAll('button, input'), function (x) { return !x.hidden && !closest(x, '[hidden]') && !x.disabled; });
                if (!f.length) { return; }
                if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); } else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
            }
        });
        pop.addEventListener('click', function (e) {
            if (closest(e.target, '[data-it-qtag-close]')) { closeQtag(true); return; }
            if (newBtn && closest(e.target, '.it-qchip-new')) { create(); }
        });
        if (search.hidden) { pop.querySelector('[data-it-qtag-close]').focus(); } else { search.focus(); }
    }

    /* Rückmeldung einblenden und nach einigen Sekunden ausblenden (nicht solange Maus oder Fokus darauf liegen) */
    function initToasts(scope) {
        Array.prototype.forEach.call(scope.querySelectorAll('[data-it-toast]'), function (toast) {
            if (toast.getAttribute('data-it-toast-ready')) { return; }
            toast.setAttribute('data-it-toast-ready', '1');
            var timer = null;
            function hide() {
                toast.classList.add('is-hiding');
                setTimeout(function () { if (toast.parentNode) { toast.parentNode.removeChild(toast); } }, 350);
            }
            function arm() { clearTimeout(timer); timer = setTimeout(hide, 6000); }
            toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
            toast.addEventListener('mouseleave', arm);
            toast.addEventListener('focusin', function () { clearTimeout(timer); });
            toast.addEventListener('focusout', arm);
            toast.querySelector('[data-it-toast-close]').addEventListener('click', hide);
            arm();
        });
    }

    // Abstand für mitlaufende Leisten: Höhe des REDAXO-Kopfs, falls er fest steht (je nach Backend-Stil)
    function setStickyTop() {
        var top = 0;
        var nav = document.querySelector('[data-it-app]') ? null : document.querySelector('#rex-js-nav-top, .rex-nav-top');
        if (nav) {
            var pos = getComputedStyle(nav).position;
            if (pos === 'fixed' || pos === 'sticky') { top = Math.max(0, Math.round(nav.getBoundingClientRect().bottom)); }
        }
        Array.prototype.forEach.call(document.querySelectorAll('[data-it-mail]'), function (m) { m.style.setProperty('--it-sticky-top', top + 'px'); });
    }

    /* Initialisierung je Inhalt: beim Laden und nach jedem PJAX-Wechsel (rex:ready mit Container). Mehrfachaufrufe sind harmlos. */
    function initAll(e, container) {
        var scope = container && container[0] ? container[0] : document;
        setStickyTop();
        initToasts(scope);
        initFontSize(scope);
        saveLastBox(scope);
        Array.prototype.forEach.call(scope.querySelectorAll('[data-it-mail]'), initColumns);
        restoreSections(scope);
        Array.prototype.forEach.call(scope.querySelectorAll('[data-it-mail]'), initRoot);
        // Verfassen-Fenster: Dateiauswahl-Anzeige
        Array.prototype.forEach.call(scope.querySelectorAll('.it-sheet'), function (sheet) { initPane(sheet); initDrop(sheet); });
        Array.prototype.forEach.call(scope.querySelectorAll('.it-compose'), initDrop);
        initForms(scope);
        Array.prototype.forEach.call(scope.querySelectorAll('.it-qbar'), initQbar);
        // EasyMDE wird von issue_tracker.js beim selben Ereignis aufgebaut – danach beschriften
        labelToolbars(scope);
        clearTimeout(labelTimer);
        labelTimer = setTimeout(function () { labelToolbars(document); }, 250);
    }
    var labelTimer = null;

    /* Aufräumen, bevor die Vollbild-App Inhalt austauscht oder schließt */
    function unload(e) {
        var root = e && e.detail ? e.detail.root : null;
        if (qtag && (!root || root.contains(qtag.btn))) { closeQtag(false); }
        if (controller && root && root.querySelector && root.querySelector('[data-it-pane]')) { controller.abort(); controller = null; }
        observers = observers.filter(function (io) {
            io.disconnect();
            return false;
        });
        Array.prototype.forEach.call(document.querySelectorAll('.it-qbar [data-it-qchip]'), function (c) { clearInterval(c._itPoll); });
    }

    window.ITMail = { tryPane: tryPane, dirtyMessage: dirtyMessage };

    if (!window.__itMailBound) {
        window.__itMailBound = true;

        // Einmal global gebunden: Fenstergröße, Scrollen, Linkmap, Warnung beim Verlassen, Aufräumen
        window.addEventListener('resize', function () {
            placeQtag();
            setStickyTop();
            Array.prototype.forEach.call(document.querySelectorAll('[data-it-mail]'), function (r) { if (r._itPlaceAll) { r._itPlaceAll(); } });
        });
        window.addEventListener('scroll', function () { placeQtag(); }, true);
        if (window.jQuery) {
            window.jQuery(window).on('rex:selectLink', function () {
                setTimeout(function () { Array.prototype.forEach.call(document.querySelectorAll('.it-qbar'), function (b) { if (b._itUpdateAll) { b._itUpdateAll(); } }); }, 50);
            });
        }
        window.addEventListener('focus', function () {
            Array.prototype.forEach.call(document.querySelectorAll('.it-qbar'), function (b) { if (b._itUpdatePage) { b._itUpdatePage(); } });
        });
        // Absenden: Warnung für dieses Formular abschalten (auf window: nach den Prüfungen am Formular, die abbrechen können)
        window.addEventListener('submit', function (e) {
            if (!e.defaultPrevented && e.target && e.target.matches && e.target.matches('form[data-it-form]')) { e.target._itLeaving = true; }
        });
        window.addEventListener('beforeunload', function (e) {
            var msg = dirtyMessage(document, null);
            if (!msg) { return; }
            e.preventDefault();
            e.returnValue = msg;
            return msg;
        });
        document.addEventListener('it:unload', unload);

        document.addEventListener('click', function (e) {
            // Menüs: nur eines offen, Klick daneben schließt
            var inMenu = closest(e.target, 'details.it-menu');
            Array.prototype.forEach.call(document.querySelectorAll('details.it-menu[open]'), function (d) {
                if (d !== inMenu) { d.removeAttribute('open'); }
            });

            // Tag-Fenster der Liste: Klick daneben schließt, Knopf öffnet
            if (qtag && !closest(e.target, '.it-qtag-pop') && closest(e.target, '[data-it-qtag]') !== qtag.btn) { closeQtag(false); }
            var qBtn = closest(e.target, '[data-it-qtag]');
            if (qBtn) {
                e.preventDefault();
                if (qtag && qtag.btn === qBtn) { closeQtag(true); } else { openQtag(qBtn); }
                return;
            }

            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }

            var row = closest(e.target, '.it-ml-row');
            var root = closest(e.target, '[data-it-mail]');
            var more = closest(e.target, '[data-it-more]');
            if (more && root && 'DOMParser' in window && 'URL' in window) {
                e.preventDefault();
                loadMore(root, more);
                return;
            }
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
                if (app()) { app().push(back.href); } else if (window.history && window.history.pushState) { window.history.pushState({}, '', back.href); }
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
                }, function () { go(abs); });
                return;
            }

            // „Gemeldet von …“ beim Anlegen nicht übernehmen
            var srcRemove = closest(e.target, '[data-it-source-remove]');
            if (srcRemove) {
                var srcNote = closest(srcRemove, '[data-it-source]');
                if (srcNote && srcNote.parentNode) { srcNote.parentNode.removeChild(srcNote); }
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
            // Unten kein Platz (z. B. kleine Laptop-Höhe): über dem Auslöser öffnen
            var ph = pop.getBoundingClientRect().height;
            if (r.bottom + 4 + ph > window.innerHeight - 8 && r.top - ph - 4 >= 8) {
                pop.style.setProperty('--pop-top', Math.round(r.top - ph - 4) + 'px');
            }
        }, true);
        document.addEventListener('toggle', function (e) {
            var d = e.target;
            if (restoringSections || !d || d !== userToggled) { return; }
            userToggled = null;
            saveSection(d);
        }, true);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && qtag) {
                e.preventDefault();
                closeQtag(true);
                return;
            }
            if (e.key === 'Escape') {
                Array.prototype.forEach.call(document.querySelectorAll('details.it-menu[open]'), function (d) {
                    d.removeAttribute('open');
                    var s = d.querySelector('summary');
                    if (s) { s.focus(); }
                });
                return;
            }
            // „?“ = Übersicht der Tastaturkürzel
            if (e.key === '?' && !e.metaKey && !e.ctrlKey && !e.altKey && !isTypingTarget(e.target) && document.getElementById('it-shortcuts-modal') && window.jQuery && window.jQuery.fn.modal) {
                e.preventDefault();
                window.jQuery('#it-shortcuts-modal').modal('show');
                return;
            }
            if (e.metaKey || e.ctrlKey || e.altKey || e.shiftKey || isTypingTarget(e.target)) { return; }
            // „e“ = Bearbeiten (im Wissen), „r“ = Antworten (Themen und Nachrichten)
            if (e.key === 'e') {
                var modeRoot = document.querySelector('[data-it-mail]');
                var editLink = document.querySelector('[data-it-pane] [data-it-edit]');
                if (modeRoot && modeRoot.getAttribute('data-mode') === 'knowledge' && editLink) { e.preventDefault(); go(editLink.href); return; }
            }
            // „f“ = Weiterleiten, „u“ = gelesen/ungelesen umschalten (Nachrichten)
            if (e.key === 'f') {
                var fwd = document.querySelector('[data-it-pane] [data-it-forward]');
                if (fwd) { e.preventDefault(); go(fwd.href); return; }
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

            // „t“ = Tags der ausgewählten (oder fokussierten) Zeile bearbeiten
            if (e.key === 't') {
                var tRow = (active && closest(active, '.it-ml-items > li')) || (root.querySelector('.it-ml-row.is-selected') ? root.querySelector('.it-ml-row.is-selected').parentNode : null);
                var tBtn = tRow ? tRow.querySelector('[data-it-qtag]') : null;
                if (tBtn) { e.preventDefault(); openQtag(tBtn); }
                return;
            }
            var list = rows(root);
            if (!list.length) { return; }
            var current = root.querySelector('.it-ml-row.is-selected');
            // Vom fokussierten Eintrag aus weiterblättern (z. B. nach „Weitere laden“), sonst vom ausgewählten
            var idx = active && active.classList && active.classList.contains('it-ml-row') ? list.indexOf(active) : (current ? list.indexOf(current) : -1);

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                var next = e.key === 'ArrowDown' ? Math.min(list.length - 1, idx + 1) : Math.max(0, idx - 1);
                if (idx === -1) { next = 0; }
                if (next === idx) { return; }
                var row = list[next];
                row.focus({ preventScroll: true });
                revealRow(root, row, true);
                loadPane(root, row, true);
            } else if (e.key === 'Enter' && current && (active === current || active === document.body || !active)) {
                e.preventDefault();
                go(current.getAttribute('data-view-href') || current.href);
            }
        });

        // Ohne Vollbild-App (klassische Darstellung): Zurück/Vor lädt die Seite neu; in der App übernimmt it-app.js
        window.addEventListener('popstate', function () {
            if (app()) { return; }
            if (document.querySelector('[data-it-mail][data-pane-url]')) { window.location.reload(); }
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
