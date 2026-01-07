/**
 * Issue Tracker JavaScript
 * 
 * @package issue_tracker
 */

(function($) {
    'use strict';

    // Warten auf rex:ready Event (falls REDAXO noch nicht bereit ist)
    var initIssueTracker = function() {
        // Kategorie hinzufügen
        $('#add-category').on('click', function() {
            var categoryRow = $('<div class="form-group category-row">' +
                '<div class="input-group">' +
                    '<input type="text" class="form-control" name="categories[]" required>' +
                    '<span class="input-group-btn">' +
                        '<button type="button" class="btn btn-danger remove-category">' +
                            '<i class="rex-icon fa-trash"></i>' +
                        '</button>' +
                    '</span>' +
                '</div>' +
            '</div>');
            
            $('#issue-tracker-categories').append(categoryRow);
        });

        // Kategorie entfernen
        $(document).on('click', '.remove-category', function() {
            var categoryRows = $('.category-row');
            
            // Mindestens eine Kategorie muss vorhanden sein
            if (categoryRows.length > 1) {
                $(this).closest('.category-row').remove();
            } else {
                alert('Mindestens eine Kategorie muss vorhanden sein!');
            }
        });

        // Filter-Formular auto-submit bei Änderung (optional)
        $('.issue-tracker-list #filter_status, .issue-tracker-list #filter_category').on('change', function() {
            // Uncomment wenn automatisches Filtern gewünscht ist
            // $(this).closest('form').submit();
        });

        // EasyMDE Markdown Editor initialisieren
        if (typeof EasyMDE !== 'undefined') {
            // Globale Variable für aktuellen Editor
            window.currentEasyMDEEditor = null;
            
            // Custom Button für REDAXO Linkmap
            var rexLinkButton = {
                name: "rex-link",
                action: function(editor) {
                    var linkMapId = 'EASYMDE_LINKMAP_' + Date.now();
                    
                    // Versteckte Input-Felder für Linkmap erstellen
                    var hiddenInput = $('<input type="hidden" id="' + linkMapId + '" />').appendTo('body');
                    var hiddenInputName = $('<input type="hidden" id="' + linkMapId + '_NAME" />').appendTo('body');
                    
                    // Aktuellen Editor speichern
                    window.currentEasyMDEEditor = editor;
                    
                    // Event-Handler für diesen spezifischen Link-Vorgang
                    var checkInterval = setInterval(function() {
                        var linkId = hiddenInput.val();
                        var linkName = hiddenInputName.val();
                        
                        if (linkId && linkName) {
                            clearInterval(checkInterval);
                            
                            // Backend-URL zum Artikel erstellen
                            var clangId = (typeof rex !== 'undefined' && rex.clang_id ? rex.clang_id : 1);
                            var backendUrl = 'index.php?page=content/edit&article_id=' + linkId + '&clang=' + clangId + '&mode=edit';
                            
                            // Markdown-Link erstellen und einfügen
                            var markdown = '[' + linkName + '](' + backendUrl + ')';
                            editor.codemirror.replaceSelection(markdown);
                            editor.codemirror.focus();
                            
                            // Cleanup
                            hiddenInput.remove();
                            hiddenInputName.remove();
                            window.currentEasyMDEEditor = null;
                        }
                    }, 100);
                    
                    // Cleanup nach 30 Sekunden
                    setTimeout(function() {
                        clearInterval(checkInterval);
                        hiddenInput.remove();
                        hiddenInputName.remove();
                        window.currentEasyMDEEditor = null;
                    }, 30000);
                    
                    // Linkmap öffnen
                    openLinkMap(linkMapId, '&clang=' + (typeof rex !== 'undefined' && rex.clang_id ? rex.clang_id : 1));
                },
                className: "fa fa-sitemap",
                title: "REDAXO-Seite verlinken"
            };

            // Issue-Beschreibung
            var descriptionField = document.getElementById('issue-description');
            if (descriptionField && !descriptionField.nextSibling?.classList?.contains('EasyMDEContainer')) {
                new EasyMDE({
                    element: descriptionField,
                    spellChecker: false,
                    autosave: {
                        enabled: true,
                        uniqueId: "issue_description",
                        delay: 1000,
                    },
                    toolbar: [
                        "bold", "italic", "heading", "|",
                        "quote", "unordered-list", "ordered-list", "|",
                        "link", rexLinkButton, "image", "code", "|",
                        "preview", "|",
                        "guide"
                    ],
                    status: false,
                    placeholder: "Beschreibung des Issues..."
                });
            }

            // Kommentar-Felder (alle textareas mit name="comment")
            var commentFields = document.querySelectorAll('textarea[name="comment"]');
            commentFields.forEach(function(commentField) {
                // Nur sichtbare Felder initialisieren und nur wenn noch nicht geschehen
                var isVisible = commentField.offsetParent !== null || commentField.id === 'new-comment-text';
                if (isVisible && !commentField.nextSibling?.classList?.contains('EasyMDEContainer')) {
                    new EasyMDE({
                        element: commentField,
                        spellChecker: false,
                        toolbar: [
                            "bold", "italic", "|",
                            "quote", "unordered-list", "ordered-list", "|",
                            "link", rexLinkButton, "code", "|",
                            "preview", "guide"
                        ],
                        status: false,
                        placeholder: "Kommentar schreiben...",
                        minHeight: "150px"
                    });
                }
            });
        }
        
        // Event-Handler für Antworten-Buttons: EasyMDE dynamisch initialisieren
        $(document).on('click', 'button[onclick*="toggleReplyForm"]', function() {
            var commentId = $(this).attr('onclick').match(/\d+/)[0];
            var replyForm = $('#reply-form-' + commentId);
            
            if (replyForm.is(':visible')) {
                return; // Formular wird geschlossen, nichts zu tun
            }
            
            // Kurze Verzögerung damit das Formular sichtbar wird
            setTimeout(function() {
                var textarea = replyForm.find('textarea[name="comment"]')[0];
                if (textarea && !textarea.nextSibling?.classList?.contains('EasyMDEContainer')) {
                    if (typeof EasyMDE !== 'undefined') {
                        var rexLinkButton = {
                            name: "rex-link",
                            action: function(editor) {
                                var linkMapId = 'EASYMDE_LINKMAP_' + Date.now();
                                var hiddenInput = $('<input type="hidden" id="' + linkMapId + '" />').appendTo('body');
                                var hiddenInputName = $('<input type="hidden" id="' + linkMapId + '_NAME" />').appendTo('body');
                                window.currentEasyMDEEditor = editor;
                                var checkInterval = setInterval(function() {
                                    var linkId = hiddenInput.val();
                                    var linkName = hiddenInputName.val();
                                    if (linkId && linkName) {
                                        clearInterval(checkInterval);
                                        var clangId = (typeof rex !== 'undefined' && rex.clang_id ? rex.clang_id : 1);
                                        var backendUrl = 'index.php?page=content/edit&article_id=' + linkId + '&clang=' + clangId + '&mode=edit';
                                        var markdown = '[' + linkName + '](' + backendUrl + ')';
                                        editor.codemirror.replaceSelection(markdown);
                                        editor.codemirror.focus();
                                        hiddenInput.remove();
                                        hiddenInputName.remove();
                                        window.currentEasyMDEEditor = null;
                                    }
                                }, 100);
                                setTimeout(function() {
                                    clearInterval(checkInterval);
                                    hiddenInput.remove();
                                    hiddenInputName.remove();
                                    window.currentEasyMDEEditor = null;
                                }, 30000);
                                openLinkMap(linkMapId, '&clang=' + (typeof rex !== 'undefined' && rex.clang_id ? rex.clang_id : 1));
                            },
                            className: "fa fa-sitemap",
                            title: "REDAXO-Seite verlinken"
                        };
                        
                        new EasyMDE({
                            element: textarea,
                            spellChecker: false,
                            toolbar: [
                                "bold", "italic", "|",
                                "quote", "unordered-list", "ordered-list", "|",
                                "link", rexLinkButton, "code", "|",
                                "preview", "guide"
                            ],
                            status: false,
                            placeholder: "Antwort schreiben...",
                            minHeight: "120px"
                        });
                    }
                }
            }, 50);
        });

        // Bestätigungsdialog für gefährliche Aktionen
        $('a[data-confirm]').on('click', function(e) {
            var message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-save Draft (optional - für zukünftige Erweiterung)
        var draftTimeout;
        $('.issue-tracker-form textarea, .issue-tracker-form input[type="text"]').on('input', function() {
            clearTimeout(draftTimeout);
            draftTimeout = setTimeout(function() {
                // Hier könnte ein Draft gespeichert werden
                console.log('Auto-save draft...');
            }, 3000);
        });

        // Keyboard Shortcuts (optional)
        $(document).on('keydown', function(e) {
            // Ctrl+S zum Speichern
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                $('.issue-tracker-form form').submit();
            }
        });
    };

    // Initialisierung mit mehreren Events für maximale Kompatibilität
    $(document).on('rex:ready', function() {
        initIssueTracker();
    });
    
    // Fallback für den Fall dass rex:ready schon gefeuert hat oder nicht existiert
    $(document).ready(function() {
        // Kurze Verzögerung damit rex:ready Vorrang hat
        setTimeout(function() {
            // Prüfen ob bereits initialisiert
            if (!window.issueTrackerInitialized) {
                initIssueTracker();
            }
        }, 100);
    });
    
    // Flag setzen nach Initialisierung
    var originalInit = initIssueTracker;
    initIssueTracker = function() {
        if (!window.issueTrackerInitialized) {
            window.issueTrackerInitialized = true;
            originalInit();
        }
    };

})(jQuery);
