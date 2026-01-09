<?php

/**
 * Projektliste
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Project;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

// Projekt löschen
if (rex_request('func', 'string') === 'delete' && rex_request('project_id', 'int', 0) > 0) {
    $projectId = rex_request('project_id', 'int', 0);
    $project = Project::get($projectId);
    
    if ($project && ($currentUser->isAdmin() || $project->isOwner($currentUser->getId()))) {
        if ($project->delete()) {
            echo rex_view::success($package->i18n('issue_tracker_project_deleted'));
        }
    } else {
        echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    }
}

// Projekte laden (gefiltert nach Berechtigung)
$projects = Project::getAll($currentUser->getId());

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('projects', $projects);
echo $fragment->parse('issue_tracker_project_list.php');
