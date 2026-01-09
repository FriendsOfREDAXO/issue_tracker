<?php

/**
 * Projekt erstellen
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Project;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

// Prüfen ob User Projekte erstellen darf (Admin, Issue-Manager oder Issuer)
if (!$currentUser->isAdmin() && !$currentUser->hasPerm('issue_tracker[issue_manager]') && !$currentUser->hasPerm('issue_tracker[issuer]')) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

$project = new Project();
$project->setCreatedBy($currentUser->getId());

// Alle Backend-User laden
$usersSql = rex_sql::factory();
$usersSql->setQuery('SELECT id, name, login FROM ' . rex::getTable('user') . ' WHERE status = 1 ORDER BY name');
$allUsers = [];
foreach ($usersSql as $row) {
    $allUsers[$usersSql->getValue('id')] = $usersSql->getValue('name') ?: $usersSql->getValue('login');
}

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
    }
    
    if ($project->save()) {
        // Mitglieder hinzufügen
        $memberIds = rex_post('members', 'array', []);
        foreach ($memberIds as $memberId) {
            if ($memberId != $currentUser->getId()) {
                $project->addUser((int) $memberId, 'member');
            }
        }
        
        rex_response::sendRedirect(rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId()], false));
    } else {
        echo rex_view::error($package->i18n('issue_tracker_project_save_error'));
    }
}

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('project', $project);
$fragment->setVar('isNew', true);
$fragment->setVar('allUsers', $allUsers);
echo $fragment->parse('issue_tracker_project_form.php');
