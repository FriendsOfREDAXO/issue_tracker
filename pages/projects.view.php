<?php

/**
 * Frühere Projektansicht – das Projekt ist jetzt ein Ordner im Postfach (Liste mit Projektkarte).
 *
 * @package issue_tracker
 */

$params = ['filter_status' => rex_request('filter_status', 'string', ''), 'filter_project' => rex_request('project_id', 'int', 0)];
if (rex_request('search', 'string', '') !== '') {
    $params['search'] = rex_request('search', 'string', '');
}
rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', $params, false));
