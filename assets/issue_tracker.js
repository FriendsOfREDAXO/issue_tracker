/**
 * Issue Tracker JavaScript
 * 
 * @package issue_tracker
 */

(function($) {
    'use strict';

    if (window.__itTrackerBound) { return; }
    window.__itTrackerBound = true;

    /*
     * Editoren (EasyMDE): zentral anlegen und merken, damit sie beim Austausch von Inhalten (PJAX in der
     * Vollbild-App, Lesebereich im Postfach) wieder abgebaut werden (EasyMDE hängt einen keydown-Listener an document).
     */
    var editors = [];
    var isDe = (document.documentElement.lang || 'de').indexOf('de') === 0;

    function clangId() {
        return (typeof rex !== 'undefined' && rex.clang_id ? rex.clang_id : 1);
    }

    // Knopf „REDAXO-Seite verlinken“: Linkmap öffnen, Auswahl als Markdown-Link einfügen
    var rexLinkButton = {
        name: 'rex-link',
        action: function(editor) {
            var linkMapId = 'EASYMDE_LINKMAP_' + Date.now();
            var hiddenInput = $('<input type="hidden" id="' + linkMapId + '" />').appendTo('body');
            var hiddenInputName = $('<input type="hidden" id="' + linkMapId + '_NAME" />').appendTo('body');
            var checkInterval = setInterval(function() {
                var linkId = hiddenInput.val();
                var linkName = hiddenInputName.val();
                if (linkId && linkName) {
                    cleanup();
                    var backendUrl = 'index.php?page=content/edit&article_id=' + linkId + '&clang=' + clangId() + '&mode=edit';
                    editor.codemirror.replaceSelection('[' + linkName + '](' + backendUrl + ')');
                    editor.codemirror.focus();
                }
            }, 100);
            var stopTimer = setTimeout(cleanup, 30000);
            function cleanup() {
                clearInterval(checkInterval);
                clearTimeout(stopTimer);
                hiddenInput.remove();
                hiddenInputName.remove();
            }
            openLinkMap(linkMapId, '&clang=' + clangId());
        },
        className: 'fa fa-sitemap',
        title: isDe ? 'REDAXO-Seite verlinken' : 'Link REDAXO page'
    };

    var TOOLBAR_FULL = ['bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', rexLinkButton, 'image', 'code', '|', 'preview', '|', 'guide'];
    var TOOLBAR_COMMENT = ['bold', 'italic', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', rexLinkButton, 'code', '|', 'preview', 'guide'];

    function hasEditor(textarea) {
        return !!(textarea && textarea.nextSibling && textarea.nextSibling.classList && textarea.nextSibling.classList.contains('EasyMDEContainer'));
    }

    /* Editor für ein Textfeld anlegen (einmal je Feld) */
    function createEditor(textarea, options) {
        if (!textarea || hasEditor(textarea) || typeof EasyMDE === 'undefined') { return null; }
        var editor = new EasyMDE($.extend({ element: textarea, spellChecker: false, status: false }, options));
        editors.push(editor);
        if (window._itAttachMentionToEditor) { window._itAttachMentionToEditor(editor); }
        return editor;
    }

    /* Editoren abbauen, deren Feld nicht mehr im Dokument ist (oder in root liegt) */
    function destroyEditors(root) {
        editors = editors.filter(function(ed) {
            var wrap = ed.codemirror ? ed.codemirror.getWrapperElement() : null;
            var gone = !wrap || !document.documentElement.contains(wrap) || (root && root.contains(wrap));
            if (gone) {
                try { ed.cleanup(); } catch (e) { /* bereits abgebaut */ }
            }
            return !gone;
        });
    }

    window.ITEditors = {
        create: createEditor,
        comment: function(textarea, placeholder, minHeight) {
            return createEditor(textarea, { toolbar: TOOLBAR_COMMENT, placeholder: placeholder || textarea.getAttribute('placeholder') || '', minHeight: minHeight || '120px' });
        },
        destroy: destroyEditors
    };

    // ---------- Einmal gebunden (delegiert, gilt auch für nachgeladene Inhalte) ----------

    // Kategorie hinzufügen / entfernen
    $(document).on('click', '#add-category', function() {
        $('#issue-tracker-categories').append('<div class="form-group category-row">' +
            '<div class="input-group">' +
                '<input type="text" class="form-control" name="categories[]" required>' +
                '<span class="input-group-btn">' +
                    '<button type="button" class="btn btn-danger remove-category">' +
                        '<i class="rex-icon fa-trash"></i>' +
                    '</button>' +
                '</span>' +
            '</div>' +
        '</div>');
    });
    $(document).on('click', '.remove-category', function() {
        // Mindestens eine Kategorie muss vorhanden sein
        if ($('.category-row').length > 1) {
            $(this).closest('.category-row').remove();
        } else {
            alert(isDe ? 'Mindestens eine Kategorie muss vorhanden sein!' : 'At least one category is required.');
        }
    });

    // Antworten / Bearbeiten von Kommentaren (statt onclick-Attributen)
    $(document).on('click', '[data-it-toggle-reply]', function(e) {
        e.preventDefault();
        toggleReplyForm(parseInt(this.getAttribute('data-it-toggle-reply'), 10));
    });
    $(document).on('click', '[data-it-toggle-edit]', function(e) {
        e.preventDefault();
        toggleEditForm(parseInt(this.getAttribute('data-it-toggle-edit'), 10));
    });

    // Strg/Cmd+S speichert das Formular
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's' && $('.issue-tracker-form form').length) {
            e.preventDefault();
            var form = $('.issue-tracker-form form')[0];
            if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
        }
    });

    // Rundschreiben: bei „Alle REDAXO-Benutzer“ nur E-Mail erlauben
    $(document).on('change', 'input[name="broadcast_recipients"]', function() {
        if ($(this).val() === 'all') {
            $('#method-email').prop('checked', true);
            $('#method-message, #method-both').prop('disabled', true).closest('label').addClass('text-muted');
        } else {
            $('#method-message, #method-both').prop('disabled', false).closest('label').removeClass('text-muted');
        }
    });

    // API-Token erzeugen und kopieren
    $(document).on('click', '#generate-api-token', function() {
        var $btn = $(this);
        if (!confirm(isDe ? 'Möchten Sie einen neuen API-Token generieren? Der alte Token wird ungültig!' : 'Generate a new API token? The old token will stop working.')) {
            return;
        }
        $btn.prop('disabled', true).find('i').removeClass('fa-refresh').addClass('fa-spinner fa-spin');
        $.ajax({
            // URL mit CSRF-Token aus dem Knopf (Fallback für alte Markups)
            url: $btn.data('url') || 'index.php?rex-api-call=issue_tracker_generate_token',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#api-token').val(response.token);
                    $('#copy-api-token').prop('disabled', false);
                    alert(isDe ? 'Neuer API-Token wurde generiert!' : 'A new API token has been generated.');
                } else {
                    alert((isDe ? 'Fehler: ' : 'Error: ') + (response.error || ''));
                }
            },
            error: function() {
                alert(isDe ? 'Fehler beim Generieren des Tokens!' : 'The token could not be generated.');
            },
            complete: function() {
                $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-refresh');
            }
        });
    });
    $(document).on('click', '#copy-api-token', function() {
        var token = $('#api-token').val();
        if (token && navigator.clipboard) {
            navigator.clipboard.writeText(token).then(function() {
                var $icon = $('#copy-api-token i');
                $icon.removeClass('fa-copy').addClass('fa-check');
                setTimeout(function() { $icon.removeClass('fa-check').addClass('fa-copy'); }, 2000);
            });
        }
    });

    // Lightbox – eigenes Overlay statt Bootstrap-Modal, mit Galerie-Navigation
    // wenn mehrere Anhänge im selben [data-lightbox-group]-Container liegen.
    $(document).on('click', '.issue-attachment-lightbox', function(e) {
        e.preventDefault();
        var $group = $(this).closest('[data-lightbox-group]');
        var $items = $group.length ? $group.find('.issue-attachment-lightbox') : $(this);
        var items = $items.toArray();
        var index = items.indexOf(this);
        if (index === -1) { index = 0; items = [this]; }
        showItLightbox(items, index);
    });

    function showItLightbox(items, index) {
        var el = items[index];
        var $el = $(el);
        var url = $el.attr('href');
        var type = $el.data('type') || 'image';
        var title = $el.attr('title') || '';

        var overlayId = 'it-lightbox-overlay';
        $('#' + overlayId).remove();
        $(document).off('keydown.itlightbox');

        var mediaHtml;
        if (type === 'video') {
            mediaHtml = '<video controls autoplay style="max-width:90vw;max-height:80vh;border-radius:4px;box-shadow:0 8px 30px rgba(0,0,0,0.6);">' +
                        '<source src="' + url + '">Ihr Browser unterstützt dieses Video-Format nicht.</video>';
        } else if (type === 'pdf') {
            mediaHtml = '<iframe src="' + url + '" style="width:85vw;height:82vh;border:none;border-radius:4px;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,0.6);"></iframe>';
        } else {
            mediaHtml = '<img src="' + url + '" alt="" style="max-width:90vw;max-height:80vh;border-radius:4px;box-shadow:0 8px 30px rgba(0,0,0,0.6);display:block;">';
        }

        var hasMultiple = items.length > 1;
        var navHtml = hasMultiple
            ? '<button type="button" class="it-lightbox-nav it-lightbox-prev">&lsaquo;</button>' +
              '<button type="button" class="it-lightbox-nav it-lightbox-next">&rsaquo;</button>'
            : '';
        var counterHtml = hasMultiple
            ? '<div class="it-lightbox-counter">' + (index + 1) + ' / ' + items.length + '</div>'
            : '';

        var overlayHtml =
            '<div id="' + overlayId + '" style="' +
                'position:fixed;top:0;left:0;width:100%;height:100%;' +
                'background:rgba(0,0,0,0.88);z-index:99999;' +
                'display:flex;flex-direction:column;align-items:center;justify-content:center;' +
                'cursor:pointer;-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px);">' +
                '<button type="button" class="it-lightbox-close" style="' +
                    'position:absolute;top:16px;right:20px;background:none;border:none;' +
                    'color:#fff;font-size:36px;line-height:1;cursor:pointer;opacity:0.8;z-index:2;' +
                    'text-shadow:0 2px 6px rgba(0,0,0,0.5);">&times;</button>' +
                navHtml +
                '<div style="cursor:default;">' + mediaHtml + '</div>' +
                (title ? '<p style="color:#ddd;font-size:13px;margin-top:12px;text-align:center;' +
                    'max-width:80vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;' +
                    'text-shadow:0 1px 4px rgba(0,0,0,0.8);">' + $('<span>').text(title).html() + '</p>' : '') +
                counterHtml +
            '</div>';

        $('body').append(overlayHtml);
        var $overlay = $('#' + overlayId);

        function closeLightbox() {
            $overlay.find('video').each(function() { this.pause(); });
            $(document).off('keydown.itlightbox');
            $overlay.fadeOut(180, function() { $(this).remove(); });
        }

        function goTo(newIndex) {
            if (newIndex < 0) { newIndex = items.length - 1; }
            if (newIndex >= items.length) { newIndex = 0; }
            $overlay.find('video').each(function() { this.pause(); });
            $overlay.remove();
            showItLightbox(items, newIndex);
        }

        // Schließen bei Klick auf Overlay-Hintergrund oder Close-Button
        $overlay.on('click', function(ev) {
            if ($(ev.target).is('.it-lightbox-close') || $(ev.target).closest('.it-lightbox-close').length) {
                closeLightbox();
            } else if ($(ev.target).is('.it-lightbox-prev') || $(ev.target).closest('.it-lightbox-prev').length) {
                goTo(index - 1);
            } else if ($(ev.target).is('.it-lightbox-next') || $(ev.target).closest('.it-lightbox-next').length) {
                goTo(index + 1);
            } else if ($(ev.target).closest('div > div, iframe, video, img').length === 0) {
                closeLightbox();
            }
        });

        // Tastatursteuerung: ESC schließt, Pfeiltasten blättern (nur bei Galerie)
        $(document).on('keydown.itlightbox', function(ev) {
            if (ev.key === 'Escape') {
                closeLightbox();
            } else if (hasMultiple && ev.key === 'ArrowLeft') {
                goTo(index - 1);
            } else if (hasMultiple && ev.key === 'ArrowRight') {
                goTo(index + 1);
            }
        });
    }


    // ---------- Je Inhalt (Seitenaufruf, PJAX-Wechsel, nachgeladener Lesebereich) ----------
    function initContent(root) {
        root = root || document;

        if (typeof EasyMDE !== 'undefined') {
            // Beschreibung (Anlegen/Bearbeiten)
            var descriptionField = root.querySelector('#issue-description');
            createEditor(descriptionField, {
                toolbar: TOOLBAR_FULL,
                placeholder: isDe ? 'Beschreiben Sie das Anliegen …' : 'Describe the request …'
            });
            // Kommentarfelder: nur sichtbare (Antworten/Bearbeiten folgen beim Aufklappen)
            Array.prototype.forEach.call(root.querySelectorAll('textarea[name="comment"]'), function(field) {
                if (field.offsetParent !== null || field.id === 'new-comment-text') {
                    createEditor(field, {
                        toolbar: TOOLBAR_COMMENT,
                        placeholder: field.getAttribute('placeholder') || (isDe ? 'Kommentar schreiben …' : 'Write a comment …'),
                        minHeight: field.id === 'new-comment-text' ? '120px' : '150px'
                    });
                }
            });
        }

        // Ungelesene Nachrichten in der Unternavigation (klassische Darstellung)
        var badge = root.querySelector ? root.querySelector('[data-it-unread-badge]') : null;
        if (badge) { issueTrackerAddMessageBadge(parseInt(badge.getAttribute('data-it-unread-badge'), 10) || 0); }
    }

    // Mention-Daten (Benutzer) je Inhalt neu lesen
    function initAll(root) {
        destroyEditors(null);
        readMentionUsers(root || document);
        initContent(root || document);
    }

    // REDAXO löst rex:ready beim Laden und nach jedem PJAX-Wechsel aus (Container als Argument)
    $(document).on('rex:ready', function(e, container) {
        initAll(container && container[0] ? container[0] : document);
        window.issueTrackerInitialized = true;
    });
    // Fallback, falls rex:ready nicht ausgelöst wird
    $(function() {
        setTimeout(function() {
            if (!window.issueTrackerInitialized) {
                window.issueTrackerInitialized = true;
                initAll(document);
            }
        }, 100);
    });

    // =============================================================================
    // @mention Autocomplete
    // =============================================================================
    // Benutzer für @-Erwähnungen (aus dem aktuellen Inhalt, data-mention-users)
    var users = [];
    function readMentionUsers(root) {
        var area = root.querySelector('#issue-tracker-comment-area, [data-mention-users]') ||
                   document.querySelector('#issue-tracker-comment-area, [data-mention-users]');
        users = [];
        if (!area || !area.getAttribute('data-mention-users')) { return; }
        try { users = JSON.parse(area.getAttribute('data-mention-users')) || []; } catch (e) { users = []; }
    }

    (function initMentionSupport() {
        // Ein gemeinsames Auswahlfeld für alle Editoren
        var dropdown = null;
        function ensureDropdown() {
            if (!dropdown || !document.body.contains(dropdown)) {
                dropdown = document.createElement('ul');
                dropdown.className = 'it-mention-dropdown';
                dropdown.style.display = 'none';
                document.body.appendChild(dropdown);
            }
            return dropdown;
        }

        var activeIdx = 0;
        var currentMatch = null;

        function closeDropdown() {
            if (!dropdown) return;
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
            currentMatch = null;
        }

        function getCmCoords(cm) {
            var cursor = cm.getCursor();
            var coords = cm.charCoords(cursor, 'window');
            return {top: coords.bottom + window.scrollY, left: coords.left + window.scrollX};
        }

        function insertMention(cm, matchedText, login) {
            var cursor = cm.getCursor();
            var mentionStart = cursor.ch - matchedText.length;
            cm.replaceRange('@' + login + ' ',
                {line: cursor.line, ch: mentionStart},
                {line: cursor.line, ch: cursor.ch}
            );
            cm.focus();
        }

        function attachMentionToCM(cm) {
            if (!cm || cm._itMentionAttached) return;
            cm._itMentionAttached = true;

            cm.on('keyup', function(instance, e) {
                var ignoredKeys = [13, 27, 38, 40, 9];
                if (ignoredKeys.indexOf(e.keyCode) !== -1) return;

                var cursor = cm.getCursor();
                var lineText = cm.getLine(cursor.line).substring(0, cursor.ch);
                var match = lineText.match(/@([\w\-\.]*)$/);

                if (!match || !users.length) { closeDropdown(); return; }
                ensureDropdown();

                currentMatch = match;
                var query = match[1].toLowerCase();
                var filtered = users.filter(function(u) {
                    return u.login.toLowerCase().indexOf(query) === 0
                        || u.name.toLowerCase().indexOf(query) !== -1;
                }).slice(0, 8);

                if (!filtered.length) { closeDropdown(); return; }

                dropdown.innerHTML = '';
                activeIdx = 0;
                filtered.forEach(function(u, i) {
                    var li = document.createElement('li');
                    li.textContent = u.login + ' (' + u.name + ')';
                    if (i === 0) li.classList.add('active');
                    li.setAttribute('data-login', u.login);
                    dropdown.appendChild(li);
                });

                // Einzelner delegierter mousedown-Handler
                dropdown.onmousedown = function(ev) {
                    var li = ev.target.closest('li');
                    if (!li) return;
                    ev.preventDefault();
                    var login = li.getAttribute('data-login');
                    if (login && currentMatch) {
                        insertMention(cm, currentMatch[0], login);
                        closeDropdown();
                    }
                };

                var pos = getCmCoords(cm);
                dropdown.style.top = pos.top + 'px';
                dropdown.style.left = pos.left + 'px';
                dropdown.style.display = 'block';
            });

            cm.on('keydown', function(instance, e) {
                if (!dropdown || dropdown.style.display === 'none') return;
                var items = dropdown.querySelectorAll('li');
                if (!items.length) return;

                if (e.keyCode === 40) { // Pfeil runter
                    e.preventDefault();
                    items[activeIdx].classList.remove('active');
                    activeIdx = (activeIdx + 1) % items.length;
                    items[activeIdx].classList.add('active');
                } else if (e.keyCode === 38) { // Pfeil hoch
                    e.preventDefault();
                    items[activeIdx].classList.remove('active');
                    activeIdx = (activeIdx - 1 + items.length) % items.length;
                    items[activeIdx].classList.add('active');
                } else if (e.keyCode === 13 || e.keyCode === 9) { // Enter oder Tab
                    var activeItem = items[activeIdx];
                    if (activeItem && currentMatch) {
                        e.preventDefault();
                        var login = activeItem.getAttribute('data-login');
                        if (login) {
                            insertMention(cm, currentMatch[0], login);
                            closeDropdown();
                        }
                    }
                } else if (e.keyCode === 27) { // Esc
                    closeDropdown();
                }
            });

            cm.on('blur', function() {
                setTimeout(closeDropdown, 200);
            });
        }

        // Für alle Editoren (createEditor hängt sich hier an)
        window._itAttachMentionToEditor = function(easyMDEInstance) {
            if (easyMDEInstance && easyMDEInstance.codemirror) {
                attachMentionToCM(easyMDEInstance.codemirror);
            }
        };

        // Beim Austausch von Inhalten schließen
        document.addEventListener('it:unload', closeDropdown);
    })();

})(jQuery);

// =============================================================================
// Globale Funktionen (müssen außerhalb des IIFE sein für onclick-Attribute)
// =============================================================================

/**
 * Toggle Reply-Formular für einen Kommentar
 * @param {number} commentId - ID des Kommentars
 */
function toggleReplyForm(commentId) {
    var form = document.getElementById('reply-form-' + commentId);
    if (!form) return;

    // Über das hidden-Attribut umschalten (siehe toggleEditForm)
    if (form.hidden || form.style.display === 'none') {
        form.hidden = false;
        form.style.display = '';
        // Editor für das Antwortfeld anlegen
        var textarea = form.querySelector('textarea[name="comment"]');
        if (textarea && window.ITEditors) {
            window.ITEditors.comment(textarea, (document.documentElement.lang || 'de').indexOf('de') === 0 ? 'Antwort schreiben …' : 'Write a reply …', '120px');
        }
    } else {
        form.hidden = true;
        form.style.display = '';
    }
}

/**
 * Toggle Edit-Formular für einen Kommentar
 * @param {number} commentId - ID des Kommentars
 */
function toggleEditForm(commentId) {
    var form = document.getElementById('edit-form-' + commentId);
    var content = document.getElementById('comment-content-' + commentId);
    if (!form) return;

    // Über das hidden-Attribut umschalten (nicht style.display): manche Backend-Stile verstecken [hidden] fest
    if (form.hidden || form.style.display === 'none') {
        form.hidden = false;
        form.style.display = '';
        if (content) { content.hidden = true; content.style.display = ''; }
        // Editor für das Bearbeiten-Feld anlegen
        var textarea = form.querySelector('textarea[name="comment_text"]');
        if (textarea && window.ITEditors) {
            window.ITEditors.comment(textarea, (document.documentElement.lang || 'de').indexOf('de') === 0 ? 'Kommentar bearbeiten …' : 'Edit comment …', '150px');
        }
    } else {
        form.hidden = true;
        form.style.display = '';
        if (content) { content.hidden = false; content.style.display = ''; }
    }
}

/**
 * Badge für ungelesene Nachrichten hinzufügen (klassische Darstellung, Unternavigation)
 * @param {number} unreadCount - Anzahl ungelesener Nachrichten
 */
function issueTrackerAddMessageBadge(unreadCount) {
    if (unreadCount <= 0) return;

    jQuery(function($) {
        var badge = '<span class="issue-tracker-message-badge">(' + unreadCount + ')</span>';
        $(".rex-page-nav > ul > li > a[href*='issue_tracker/issues']").each(function() {
            if ($(this).find(".issue-tracker-message-badge").length === 0) {
                $(this).append(badge);
            }
        });
    });
}

/**
 * LocalStorage Draft löschen nach erfolgreichem Speichern (Kompatibilität)
 */
function issueTrackerClearDraft() {
    try { localStorage.removeItem("smde_issue_description"); } catch (e) { /* ohne Speicher */ }
}

/**
 * Rundschreiben (Kompatibilität): das Verhalten ist inzwischen delegiert gebunden
 */
function issueTrackerInitBroadcastForm() {}
