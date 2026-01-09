<?php

/**
 * Projekt anzeigen
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Project;
use FriendsOfREDAXO\IssueTracker\Issue;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

$projectId = rex_request('project_id', 'int', 0);

if ($projectId === 0) {
    echo rex_view::error($package->i18n('issue_tracker_project_not_found'));
    return;
}

$project = Project::get($projectId);

if (!$project) {
    echo rex_view::error($package->i18n('issue_tracker_project_not_found'));
    return;
}

// Zugangsberechtigung prüfen
if (!$project->hasAccess($currentUser->getId())) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

// User aus Projekt entfernen
if (rex_request('func', 'string') === 'remove_user' && rex_request('user_id', 'int', 0) > 0) {
    if ($project->isOwner($currentUser->getId())) {
        $project->removeUser(rex_request('user_id', 'int', 0));
        echo rex_view::success($package->i18n('issue_tracker_user_removed'));
    }
}

// User zum Projekt hinzufügen
if (rex_post('add_user', 'int', 0) === 1) {
    if ($project->isOwner($currentUser->getId())) {
        $newUserId = rex_post('user_id', 'int', 0);
        $role = rex_post('role', 'string', 'member');
        
        if ($newUserId > 0 && in_array($role, ['owner', 'member', 'viewer'])) {
            $project->addUser($newUserId, $role);
            echo rex_view::success($package->i18n('issue_tracker_user_added'));
        }
    }
}

// Statistiken und Issues laden
$stats = $project->getStats();
$issues = Issue::getByProject($projectId);
$users = $project->getUsers();

// Einstellungen für Statuses laden
$settingsSql = rex_sql::factory();
$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
$statuses = $settingsSql->getRows() > 0 ? json_decode($settingsSql->getValue('setting_value'), true) : [];

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('project', $project);
$fragment->setVar('stats', $stats);
$fragment->setVar('issues', $issues);
$fragment->setVar('users', $users);
$fragment->setVar('statuses', $statuses);
$fragment->setVar('canEdit', $project->isOwner($currentUser->getId()));
$fragment->setVar('canWrite', $project->canWrite($currentUser->getId()));
echo $fragment->parse('issue_tracker_project_view.php');
