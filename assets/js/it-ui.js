/**
 * Issue Tracker – kleine Helfer für Übersicht, Projekte, Wissen und Einstellungen.
 * - [data-it-autosubmit]: Auswahl sendet das Formular sofort ab (Auswahllisten, Häkchen in Aufgabenlisten)
 * - [data-it-tpl]: E-Mail-Vorlagen als Liste + Editor (ohne JavaScript bleiben alle Felder sichtbar)
 */
(function () {
    'use strict';

    function initAutosubmit(root) {
        root.querySelectorAll('[data-it-autosubmit]').forEach(function (el) {
            if (el.dataset.itBound) {
                return;
            }
            el.dataset.itBound = '1';
            el.addEventListener('change', function () {
                // requestSubmit löst „submit“ aus (Prüfungen, PJAX in der Vollbild-App)
                if (el.form && el.form.requestSubmit) {
                    el.form.requestSubmit();
                } else if (el.form) {
                    el.form.submit();
                }
            });
        });
    }

    function initTemplates(root) {
        root.querySelectorAll('[data-it-tpl]').forEach(function (box) {
            if (box.dataset.itBound) {
                return;
            }
            box.dataset.itBound = '1';
            var state = { lang: 'de', type: 'new_issue' };
            var langButtons = box.querySelectorAll('[data-it-tpl-lang]');
            var typeButtons = box.querySelectorAll('[data-it-tpl-type]');
            var panes = box.querySelectorAll('.it-tpl-pane');

            function render() {
                langButtons.forEach(function (b) {
                    b.setAttribute('aria-pressed', b.dataset.itTplLang === state.lang ? 'true' : 'false');
                });
                typeButtons.forEach(function (b) {
                    var on = b.dataset.itTplType === state.type;
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                    b.tabIndex = on ? 0 : -1;
                });
                panes.forEach(function (p) {
                    p.classList.toggle('is-active', p.dataset.lang === state.lang && p.dataset.type === state.type);
                });
                preview();
            }

            // Vorschau mit Beispielwerten (iframe ohne Skripte)
            var sample = {};
            try {
                sample = JSON.parse(box.dataset.itSample || '{}');
            } catch (e) {
                sample = {};
            }
            var previewBox = box.querySelector('.it-tpl-preview');
            var frame = box.querySelector('.it-tpl-frame');
            var timer = null;
            function activeArea() {
                var pane = box.querySelector('.it-tpl-pane.is-active');
                return pane ? pane.querySelector('textarea') : null;
            }
            function preview() {
                var area = activeArea();
                if (!frame || !area) {
                    return;
                }
                var html = area.value.replace(/\{\{\s*([a-z_]+)\s*\}\}/g, function (m, key) {
                    return Object.prototype.hasOwnProperty.call(sample, key) ? sample[key] : m;
                });
                frame.srcdoc = '<!doctype html><meta charset="utf-8"><style>body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#222;background:#fff;margin:16px}</style>' + html;
            }
            box.addEventListener('input', function (e) {
                if (e.target.tagName === 'TEXTAREA') {
                    clearTimeout(timer);
                    timer = setTimeout(preview, 250);
                }
            });

            // Platzhalter per Klick an der Cursorposition einfügen
            box.querySelectorAll('[data-it-insert]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var area = activeArea();
                    if (!area) {
                        return;
                    }
                    var text = chip.dataset.itInsert;
                    var start = area.selectionStart || 0;
                    var end = area.selectionEnd || 0;
                    area.value = area.value.slice(0, start) + text + area.value.slice(end);
                    area.focus();
                    area.selectionStart = area.selectionEnd = start + text.length;
                    preview();
                });
            });
            if (previewBox) {
                previewBox.hidden = false;
            }

            langButtons.forEach(function (b) {
                b.addEventListener('click', function () {
                    state.lang = b.dataset.itTplLang;
                    render();
                });
            });
            typeButtons.forEach(function (b, i) {
                b.addEventListener('click', function () {
                    state.type = b.dataset.itTplType;
                    render();
                });
                b.addEventListener('keydown', function (e) {
                    var next = null;
                    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                        next = typeButtons[(i + 1) % typeButtons.length];
                    } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                        next = typeButtons[(i - 1 + typeButtons.length) % typeButtons.length];
                    }
                    if (next) {
                        e.preventDefault();
                        next.click();
                        next.focus();
                    }
                });
            });

            // Nach dem Absenden eines ungültigen Feldes dessen Vorlage zeigen
            box.addEventListener('invalid', function (e) {
                var pane = e.target.closest('.it-tpl-pane');
                if (pane) {
                    state.lang = pane.dataset.lang;
                    state.type = pane.dataset.type;
                    render();
                }
            }, true);

            box.classList.add('is-ready');
            render();
        });
    }

    function init(e, container) {
        var root = container && container[0] ? container[0] : document;
        initAutosubmit(root);
        initTemplates(root);
    }

    if (window.jQuery) {
        // REDAXO löst rex:ready beim Laden und nach jedem PJAX-Wechsel aus
        window.jQuery(document).on('rex:ready', init);
    }
    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
