/**
 * Simple table sorting for REDAXO Issue Tracker
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const table = $('#issue-tracker-table');
        if (!table.length) return;

        const tbody = table.find('tbody');
        const headers = table.find('thead th[data-sort]');

        headers.each(function() {
            const th = $(this);
            th.css('cursor', 'pointer');
            th.attr('title', 'Klicken zum Sortieren');
            
            // Add sort indicator
            th.append(' <i class="rex-icon fa-sort"></i>');
        });

        headers.on('click', function() {
            const th = $(this);
            const index = th.index();
            const sortType = th.data('sort');
            const currentOrder = th.data('order') || 'asc';
            const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';

            // Reset all headers
            headers.removeData('order');
            headers.find('.rex-icon').removeClass('fa-sort-asc fa-sort-desc').addClass('fa-sort');

            // Set current header
            th.data('order', newOrder);
            th.find('.rex-icon').removeClass('fa-sort').addClass(newOrder === 'asc' ? 'fa-sort-asc' : 'fa-sort-desc');

            // Get rows and sort
            const rows = tbody.find('tr').toArray();
            
            rows.sort(function(a, b) {
                const aTd = $(a).find('td').eq(index);
                const bTd = $(b).find('td').eq(index);
                
                // Check for data-sort-value attribute first
                let aVal = aTd.data('sort-value') !== undefined ? aTd.data('sort-value') : aTd.text().trim();
                let bVal = bTd.data('sort-value') !== undefined ? bTd.data('sort-value') : bTd.text().trim();

                if (sortType === 'int') {
                    aVal = parseInt(String(aVal).replace(/[^\d]/g, '')) || 0;
                    bVal = parseInt(String(bVal).replace(/[^\d]/g, '')) || 0;
                    return newOrder === 'asc' ? aVal - bVal : bVal - aVal;
                } else if (sortType === 'date') {
                    // Parse dates - expecting format like "06.01.2026 17:42"
                    aVal = parseDateString(String(aVal));
                    bVal = parseDateString(String(bVal));
                    return newOrder === 'asc' ? aVal - bVal : bVal - aVal;
                } else {
                    // String comparison
                    aVal = String(aVal).toLowerCase();
                    bVal = String(bVal).toLowerCase();
                    
                    if (aVal < bVal) return newOrder === 'asc' ? -1 : 1;
                    if (aVal > bVal) return newOrder === 'asc' ? 1 : -1;
                    return 0;
                }
            });
            
            function parseDateString(dateStr) {
                if (!dateStr || dateStr === '-') return 0;
                // Parse "06.01.2026 17:42" or "06.01.2026"
                const parts = dateStr.match(/(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2}))?/);
                if (!parts) return 0;
                const day = parseInt(parts[1]);
                const month = parseInt(parts[2]);
                const year = parseInt(parts[3]);
                const hour = parts[4] ? parseInt(parts[4]) : 0;
                const minute = parts[5] ? parseInt(parts[5]) : 0;
                return new Date(year, month - 1, day, hour, minute).getTime();
            }

            // Re-append sorted rows
            tbody.empty().append(rows);
        });
    });
})(jQuery);
