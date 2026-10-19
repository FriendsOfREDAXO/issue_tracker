<?php

/**
 * Frühere Projektliste – Projekte sind jetzt Ordner im Postfach. Löschen bleibt hier erreichbar.
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Project;

$currentUser = rex::getUser();

// Projekt löschen (unverändert: Admin oder Owner)
if (rex_request('func', 'string') === 'delete' && rex_request('project_id', 'int', 0) > 0) {
    $project = Project::get(rex_request('project_id', 'int', 0));
    if ($project && ($currentUser->isAdmin() || $project->isOwner($currentUser->getId()))) {
        $project->delete();
    }
    rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', ['reset_filter' => 1], false));
}

rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', [], false));
