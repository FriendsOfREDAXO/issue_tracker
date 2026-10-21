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

// Archivieren (aus dem „…“-Menü des Projektordners)
if (rex_post('archive_project', 'int', 0) === 1) {
    if (rex_csrf_token::factory('issue_tracker_project_archive')->isValid()) {
        $project->setStatus('archived');
        $project->save();
    }
    rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', ['filter_status' => '', 'filter_project' => $project->getId()], false));
}

// Mitglieder mit Rolle verwalten (früher in der Projektansicht)
if (rex_request('func', 'string') === 'remove_user' && rex_request('user_id', 'int', 0) > 0 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $project->removeUser(rex_request('user_id', 'int', 0));
    echo rex_view::success($package->i18n('issue_tracker_user_removed'));
}
if (rex_post('add_user', 'int', 0) === 1 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $newUserId = rex_post('user_id', 'int', 0);
    $role = rex_post('role', 'string', 'member');
    if ($newUserId > 0 && in_array($role, ['owner', 'member', 'viewer'], true)) {
        $project->addUser($newUserId, $role);
        echo rex_view::success($package->i18n('issue_tracker_user_added'));
    }
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
$formErrors = [];
$postedMembers = null;
if (rex_post('save', 'int', 0) === 1 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
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
    
    // Pflichtfeld prüfen – bei Fehler bleibt das Formular mit den eingegebenen Werten stehen
    if (trim($project->getName()) === '') {
        $formErrors['project-name'] = rex_i18n::rawMsg('issue_tracker_ui_error_project_name');
    }

    if ($formErrors) {
        $postedMembers = array_map('intval', rex_post('members', 'array', []));
    } elseif ($project->save()) {
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


// Im gemeinsamen Postfach-Fenster anzeigen (Postfächer links, Formular rechts)
$itSidebar = new rex_fragment();
$itSidebar->setVar('mode', 'project_form');
echo '<div class="it-mail it-mail--full it-mail--sel it-mail--compose" data-it-mail data-mode="project_form">' . $itSidebar->parse('issue_tracker_filter.php') . '<section class="it-rp-wrap it-rp-wrap--sheet"><div class="it-sheet-pad">';

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('project', $project);
$fragment->setVar('isNew', false);
$fragment->setVar('allUsers', $allUsers);
$fragment->setVar('formErrors', $formErrors);
$fragment->setVar('currentMembers', $postedMembers ?? $currentMembers);
echo $fragment->parse('issue_tracker_project_form.php');

// Mitglieder und Rollen
$members = $project->getUsers();
$memberIds = array_map(static fn ($m) => (int) $m['user_id'], $members);
$roleLabels = ['owner' => $package->i18n('issue_tracker_role_owner'), 'member' => $package->i18n('issue_tracker_role_member'), 'viewer' => $package->i18n('issue_tracker_role_viewer')];
?>
<section class="it-members" id="it-members">
    <h3 class="it-group-title"><?= $package->i18n('issue_tracker_project_members') ?> <span class="it-disclosure-count"><?= count($members) ?></span></h3>
    <div class="it-group">
        <?php foreach ($members as $member):
            $memberName = $member['name'] ?: $member['login']; ?>
        <div class="it-row">
            <div class="it-row-label it-members-who"><?= \FriendsOfREDAXO\IssueTracker\UiHelper::avatar((int) $member['user_id'], $memberName, true) ?> <strong><?= rex_escape($memberName) ?></strong></div>
            <div class="it-row-control">
                <span class="it-badge"><?= rex_escape($roleLabels[$member['role']] ?? $member['role']) ?></span>
                <?php if ((int) $member['user_id'] !== (int) $currentUser->getId()): ?>
                <a class="it-tb-btn it-tb-btn--sm it-tb-btn--danger" href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $project->getId(), 'func' => 'remove_user', 'user_id' => $member['user_id']] + \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getUrlParams()) ?>#it-members"
                   onclick="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_user_remove_confirm')) ?>"
                   title="<?= rex_escape($package->i18n('issue_tracker_ui_remove_member')) ?>"><i class="rex-icon fa-times" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_ui_remove_member') ?>: <?= rex_escape($memberName) ?></span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="it-row">
            <form method="post" action="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $project->getId()]) ?>#it-members" class="it-inline-form">
                <input type="hidden" name="add_user" value="1" />
                <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>
                <label class="sr-only" for="it-add-member-user"><?= $package->i18n('issue_tracker_ui_add_member') ?></label>
                <select id="it-add-member-user" name="user_id" class="form-control" required>
                    <option value=""><?= $package->i18n('issue_tracker_ui_add_member') ?></option>
                    <?php foreach ($allUsers as $uid => $uname): if (in_array((int) $uid, $memberIds, true)) { continue; } ?>
                    <option value="<?= (int) $uid ?>"><?= rex_escape($uname) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="sr-only" for="it-add-member-role"><?= $package->i18n('issue_tracker_ui_role') ?></label>
                <select id="it-add-member-role" name="role" class="form-control">
                    <?php foreach ($roleLabels as $rk => $rl): ?><option value="<?= $rk ?>"<?= $rk === 'member' ? ' selected' : '' ?>><?= rex_escape($rl) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-default btn-sm"><?= $package->i18n('issue_tracker_ui_add_member') ?></button>
            </form>
        </div>
    </div>
    <p class="it-group-note"><?= $package->i18n('issue_tracker_mail_members_help') ?></p>
</section>
<?php
echo '</div></section></div>';
