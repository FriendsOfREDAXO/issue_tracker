/**
 * Issue Tracker JavaScript
 * 
 * @package issue_tracker
 */

(function($) {
    'use strict';

    $(document).ready(function() {
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
            if (descriptionField) {
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

            // Kommentar-Feld in Thread-Ansicht
            var commentField = document.querySelector('textarea[name="comment"]');
            if (commentField) {
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
        }

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
    });

})(jQuery);
