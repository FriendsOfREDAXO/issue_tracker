<?php

/**
 * Projekt bearbeiten
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Project;

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

// Nur Owner oder Admin dürfen bearbeiten
if (!$project->isOwner($currentUser->getId())) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

// Alle Backend-User laden
$usersSql = rex_sql::factory();
$usersSql->setQuery('SELECT id, name, login FROM ' . rex::getTable('user') . ' WHERE status = 1 ORDER BY name');
$allUsers = [];
foreach ($usersSql as $row) {
    $allUsers[$usersSql->getValue('id')] = $usersSql->getValue('name') ?: $usersSql->getValue('login');
}

// Aktuelle Mitglieder
$currentMembers = array_column($project->getUsers(), 'user_id');

// Speichern
if (rex_post('save', 'int', 0) === 1) {
    $project->setName(rex_post('name', 'string', ''));
    $project->setDescription(rex_post('description', 'string', ''));
    $project->setStatus(rex_post('status', 'string', 'active'));
    $project->setIsPrivate(rex_post('is_private', 'int', 0) === 1);
    $project->setColor(rex_post('color', 'string', '#007bff'));
    
    // Due Date verarbeiten
    $dueDateInput = rex_post('due_date', 'string', '');
    if ($dueDateInput) {
        $project->setDueDate(new DateTime($dueDateInput));
    } else {
        $project->setDueDate(null);
    }
    
    if ($project->save()) {
        // Mitglieder aktualisieren
        $newMemberIds = rex_post('members', 'array', []);
        $newMemberIds = array_map('intval', $newMemberIds);
        
        // Aktuelle Mitglieder laden (ohne Owner)
        $currentUsersData = $project->getUsers();
        $currentMemberIds = [];
        $ownerIds = [];
        
        foreach ($currentUsersData as $userData) {
            if ($userData['role'] === 'owner') {
                $ownerIds[] = (int) $userData['user_id'];
            } else {
                $currentMemberIds[] = (int) $userData['user_id'];
            }
        }
        
        // User entfernen, die nicht mehr in der Auswahl sind (außer Owner)
        foreach ($currentMemberIds as $memberId) {
            if (!in_array($memberId, $newMemberIds)) {
                $project->removeUser($memberId);
            }
        }
        
        // Neue User hinzufügen
        foreach ($newMemberIds as $memberId) {
            if (!in_array($memberId, $currentMemberIds) && !in_array($memberId, $ownerIds)) {
                $project->addUser($memberId, 'member');
            }
        }
        
        // Mitgliederliste neu laden für Anzeige
        $currentMembers = array_column($project->getUsers(), 'user_id');
        
        echo rex_view::success($package->i18n('issue_tracker_project_updated'));
    } else {
        echo rex_view::error($package->i18n('issue_tracker_project_save_error'));
    }
}

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('project', $project);
$fragment->setVar('isNew', false);
$fragment->setVar('allUsers', $allUsers);
$fragment->setVar('currentMembers', $currentMembers);
echo $fragment->parse('issue_tracker_project_form.php');
