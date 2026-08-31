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

// Filter: Status & Suche
$filterStatus = rex_request('filter_status', 'string', '');
$searchTerm = rex_request('search', 'string', '');

// Projekte laden (gefiltert nach Berechtigung und Status)
$projects = Project::getAll($currentUser->getId(), $filterStatus !== '' ? $filterStatus : null);

// Suche client-seitig anwenden (Projektlisten sind klein, kein eigener SQL-Roundtrip nötig)
if ($searchTerm !== '') {
    $needle = mb_strtolower($searchTerm);
    $projects = array_values(array_filter($projects, static function (Project $project) use ($needle): bool {
        return str_contains(mb_strtolower($project->getName()), $needle)
            || str_contains(mb_strtolower($project->getDescription()), $needle);
    }));
}

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('projects', $projects);
$fragment->setVar('filterStatus', $filterStatus);
$fragment->setVar('searchTerm', $searchTerm);
echo $fragment->parse('issue_tracker_project_list.php');
