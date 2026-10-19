<?php

/**
 * Projekt erstellen
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Project;
use FriendsOfREDAXO\IssueTracker\PermissionService;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

// Prüfen ob User Projekte erstellen darf (nur Admin oder Issue-Manager)
if (!PermissionService::canCreateProject()) {
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
$formErrors = [];
$postedMembers = null;
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
    
    // Pflichtfeld prüfen – bei Fehler bleibt das Formular mit den eingegebenen Werten stehen
    if (trim($project->getName()) === '') {
        $formErrors['project-name'] = rex_i18n::rawMsg('issue_tracker_ui_error_project_name');
    }

    if ($formErrors) {
        $postedMembers = array_map('intval', rex_post('members', 'array', []));
    } elseif ($project->save()) {
        // Mitglieder hinzufügen
        $memberIds = rex_post('members', 'array', []);
        foreach ($memberIds as $memberId) {
            if ($memberId != $currentUser->getId()) {
                $project->addUser((int) $memberId, 'member');
            }
        }
        
        rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', ['filter_status' => '', 'filter_project' => $project->getId()], false));
    } else {
        echo rex_view::error($package->i18n('issue_tracker_project_save_error'));
    }
}


// Im gemeinsamen Postfach-Fenster anzeigen (Postfächer links, Formular rechts)
$itSidebar = new rex_fragment();
$itSidebar->setVar('mode', 'project_form');
echo '<div class="it-mail it-mail--full it-mail--sel it-mail--compose" data-it-mail data-mode="project_form">' . $itSidebar->parse('issue_tracker_filter.php') . '<section class="it-rp-wrap it-rp-wrap--sheet"><div class="it-sheet-pad">';

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('project', $project);
$fragment->setVar('isNew', true);
$fragment->setVar('allUsers', $allUsers);
$fragment->setVar('formErrors', $formErrors);
$fragment->setVar('currentMembers', $postedMembers ?? []);
echo $fragment->parse('issue_tracker_project_form.php');
echo '</div></section></div>';
