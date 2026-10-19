<?php
/**
 * Postfächer-Seitenleiste des gemeinsamen Fensters (wie Mail): „Neu“, Themen, Status, Nachrichten,
 * Projekte, Wissen, Tags, gespeicherte Filter, Zahnrad-Menü.
 * Variablen: filterState, mode (issues|project|knowledge|messages|compose|broadcast|project_form|preferences), box, active
 * Jeder Themen-Eintrag ist ein Link mit den bestehenden Filter-Parametern der Themenliste.
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\PermissionService;
use FriendsOfREDAXO\IssueTracker\Project;
use FriendsOfREDAXO\IssueTracker\SavedFilterService;
use FriendsOfREDAXO\IssueTracker\Tag;

$package = rex_addon::get('issue_tracker');
$userId = PermissionService::getUserId();

$defaults = [
    'filter_status' => '',
    'filter_category' => '',
    'filter_tag' => 0,
    'filter_created_by' => 0,
    'filter_assigned' => 0,
    'filter_project' => 0,
    'filter_watched' => 0,
    'filter_mentioned' => 0,
    'filter_overdue' => 0,
    'search' => '',
];
$mode = (string) $this->getVar('mode', 'issues');
$box = (string) $this->getVar('box', '');
$inIssues = in_array($mode, ['issues', 'project', 'knowledge'], true);
$state = $this->getVar('filterState', null);
if (!is_array($state)) {
    $state = rex_session('issue_tracker_filter', 'array', []);
}
$state = array_merge($defaults, array_intersect_key($state, $defaults));
$currentFilter = array_diff_key($state, ['search' => true]);

$statuses = $this->getVar('statuses', null);
if (!is_array($statuses)) {
    $s = rex_sql::factory();
    $s->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
    $statuses = $s->getRows() > 0 ? (json_decode((string) $s->getValue('setting_value'), true) ?: []) : [];
}
$categories = $this->getVar('categories', null);
if (!is_array($categories)) {
    $s = rex_sql::factory();
    $s->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "categories"');
    $categories = $s->getRows() > 0 ? (json_decode((string) $s->getValue('setting_value'), true) ?: []) : [];
}
$allTags = $this->getVar('allTags', null);
if (!is_array($allTags)) {
    $allTags = Tag::getAll();
}
$users = $this->getVar('users', []);

// Zählwerte (sichtbare Themen; aktive = nicht erledigt, wie die Standardliste)
$t = static fn (string $n): string => rex::getTable('issue_tracker_' . $n);
$vis = PermissionService::isAdmin() ? '1=1' : '(i.is_private = 0 OR i.created_by = ' . $userId . ')';
$active = "i.status != 'closed'";
$count = static function (string $sql): array {
    $out = [];
    foreach (rex_sql::factory()->getArray($sql) as $row) {
        $out[(string) $row['k']] = (int) $row['c'];
    }
    return $out;
};
$byStatus = $count('SELECT i.status AS k, COUNT(*) AS c FROM ' . $t('issues') . " i WHERE $vis GROUP BY i.status");
$byProject = $count('SELECT i.project_id AS k, COUNT(*) AS c FROM ' . $t('issues') . " i WHERE $vis AND $active AND i.project_id IS NOT NULL GROUP BY i.project_id");
$byTag = $count('SELECT it.tag_id AS k, COUNT(DISTINCT i.id) AS c FROM ' . $t('issues') . ' i INNER JOIN ' . $t('issue_tags') . " it ON it.issue_id = i.id WHERE $vis AND $active GROUP BY it.tag_id");
$byCategory = $count('SELECT i.category AS k, COUNT(*) AS c FROM ' . $t('issues') . " i WHERE $vis AND $active GROUP BY i.category");
$fav = rex_sql::factory()->getArray(
    'SELECT'
    . ' (SELECT COUNT(*) FROM ' . $t('issues') . " i WHERE $vis AND $active) AS active_all,"
    . ' (SELECT COUNT(*) FROM ' . $t('issues') . " i WHERE $vis AND $active AND i.assigned_user_id = $userId) AS mine,"
    . ' (SELECT COUNT(*) FROM ' . $t('issues') . " i WHERE $vis AND $active AND i.created_by = $userId) AS created,"
    . ' (SELECT COUNT(DISTINCT i.id) FROM ' . $t('issues') . ' i INNER JOIN ' . $t('watchers') . " w ON w.issue_id = i.id AND w.user_id = $userId WHERE $vis AND $active) AS watched,"
    . ' (SELECT COUNT(DISTINCT i.id) FROM ' . $t('issues') . ' i INNER JOIN ' . $t('mentions') . " m ON m.issue_id = i.id AND m.mentioned_user_id = $userId AND m.read_at IS NULL WHERE $vis) AS mentioned_unread,"
    . ' (SELECT COUNT(*) FROM ' . $t('issues') . " i WHERE $vis AND $active AND i.due_date IS NOT NULL AND i.due_date < NOW()) AS overdue,"
    . ' (SELECT COUNT(*) FROM ' . $t('issues') . " i WHERE $vis AND i.due_date IS NOT NULL) AS with_due,"
    . ' (SELECT COUNT(*) FROM ' . $t('issues') . " i WHERE $vis) AS all_issues"
)[0] ?? [];
$byKnowledgeCategory = $count('SELECT i.category AS k, COUNT(*) AS c FROM ' . $t('issues') . " i WHERE $vis AND i.status = 'info' GROUP BY i.category");
$unreadMessages = \FriendsOfREDAXO\IssueTracker\Message::getUnreadCount($userId);

$projects = Project::getAll($userId);
$savedFilters = SavedFilterService::getByUser($userId);

$url = static fn (array $params): string => rex_url::backendPage('issue_tracker/issues/list', array_merge(['filter_status' => ''], $params));
$isActive = static function (array $params) use ($currentFilter, $defaults, $inIssues): bool {
    if (!$inIssues) {
        return false;
    }
    $want = array_merge(array_diff_key($defaults, ['search' => true]), array_intersect_key($params, $defaults));
    foreach ($want as $k => $v) {
        if ((string) $currentFilter[$k] !== (string) $v) {
            return false;
        }
    }
    return true;
};
$statusDot = static fn (string $status): string => '<span class="it-dot it-dot--' . rex_escape(preg_replace('/[^a-z_]/', '', $status)) . '" aria-hidden="true"></span>';

/**
 * @param array<string, int|string> $params
 */
$item = static function (string $label, array $params, string $icon, ?int $cnt = null, string $extraClass = '', string $title = '') use ($url, $isActive): string {
    $active = $isActive($params);
    return '<li><a class="it-mbx-item' . ($active ? ' is-active' : '') . $extraClass . '" href="' . $url($params) . '"'
        . ($active ? ' aria-current="page"' : '') . ($title !== '' ? ' title="' . rex_escape($title) . '"' : '') . '>'
        . $icon
        . '<span class="it-mbx-label">' . rex_escape($label) . '</span>'
        . ($cnt !== null && $cnt > 0 ? '<span class="it-mbx-count">' . $cnt . '</span>' : '')
        . '</a></li>';
};
$fa = static fn (string $name): string => '<i class="it-mbx-icon rex-icon ' . $name . '" aria-hidden="true"></i>';
?>
<?php
$canManager = PermissionService::isAdminOrManager();
$knowledgeCategory = $mode === 'knowledge' ? (string) $state['filter_category'] : '';
$newItems = [
    'issue' => [$package->i18n('issue_tracker_mail_new_issue'), 'fa-pencil-square-o', rex_url::backendPage('issue_tracker/issues/create')],
    'message' => [$package->i18n('issue_tracker_mail_new_message'), 'fa-envelope-o', rex_url::backendPage('issue_tracker/messages/compose')],
];
if ($mode === 'project' && (int) $state['filter_project'] > 0) {
    $newItems = ['project_issue' => [$package->i18n('issue_tracker_mail_new_issue_in_project'), 'fa-pencil-square-o', rex_url::backendPage('issue_tracker/issues/create', ['project_id' => (int) $state['filter_project']])]] + $newItems;
}
if ($canManager) {
    $newItems['knowledge'] = [$package->i18n('issue_tracker_mail_new_knowledge'), 'fa-book', rex_url::backendPage('issue_tracker/issues/create', array_filter(['status' => 'info', 'category' => $knowledgeCategory]))];
}
if (PermissionService::isAdmin()) {
    $newItems['broadcast'] = [$package->i18n('issue_tracker_mail_broadcast_send'), 'fa-bullhorn', rex_url::backendPage('issue_tracker/messages/broadcast')];
}
if (PermissionService::canCreateProject()) {
    $newItems['project'] = [$package->i18n('issue_tracker_mail_new_project'), 'fa-folder-o', rex_url::backendPage('issue_tracker/projects/create')];
}
// Hauptaktion je nach Modus
$primaryKey = match (true) {
    $mode === 'project' && isset($newItems['project_issue']) => 'project_issue',
    $mode === 'messages' => 'message',
    $mode === 'knowledge' && isset($newItems['knowledge']) => 'knowledge',
    default => 'issue',
};
$primary = $newItems[$primaryKey];
$boxUrl = static fn (string $b): string => rex_url::backendPage('issue_tracker/issues/list', ['box' => $b]);
$canProjectForms = rex::getUser()->isAdmin() || rex::getUser()->hasPerm('issue_tracker[issue_manager]');
// Auf- und zuklappbare Bereiche: Zustand pro Benutzer (rex_config), aktive Einträge bleiben sichtbar
$uiState = rex_config::get('issue_tracker', 'sidebar_state_' . $userId, []);
$uiState = is_array($uiState) ? $uiState : [];
$archivedProjects = count(array_filter($projects, static fn ($p) => in_array($p->getStatus(), ['archived', 'completed'], true)));
$section = static function (string $key, string $title, ?int $count, bool $default, string $inner, bool $sub = false) use ($uiState): string {
    $open = array_key_exists($key, $uiState) ? (bool) $uiState[$key] : $default;
    if (str_contains($inner, ' is-active')) {
        $open = true;
    }
    return '<details class="it-mbx-section' . ($sub ? ' it-mbx-section--sub' : '') . '" data-it-sec="' . rex_escape($key) . '"' . ($open ? ' open' : '') . '>'
        . '<summary class="' . ($sub ? 'it-mbx-subtitle' : 'it-mbx-title') . '"><span class="it-mbx-title-text">' . rex_escape($title) . '</span>'
        . (null !== $count ? '<span class="it-mbx-seccount">' . $count . '</span>' : '') . '</summary>' . $inner . '</details>';
};
$stateToken = rex_csrf_token::factory('issue_tracker_ui_state');
?>
<nav class="it-mbx" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_mailboxes')) ?>" data-it-state-url="<?= rex_escape(rex_url::backendPage('issue_tracker/issues/list', [], false)) ?>" data-it-state-token="<?= rex_escape($stateToken->getValue()) ?>">
    <div class="it-mbx-head">
        <div class="it-newbtn">
            <a class="it-newbtn-main" href="<?= $primary[2] ?>" data-it-new="<?= $primaryKey ?>" title="<?= rex_escape($primary[0]) ?>"><i class="rex-icon <?= $primary[1] ?>" aria-hidden="true"></i> <span aria-hidden="true"><?= $package->i18n('issue_tracker_mail_new') ?></span><span class="sr-only"><?= rex_escape($primary[0]) ?></span></a>
            <details class="it-menu">
                <summary class="it-newbtn-more" title="<?= rex_escape($package->i18n('issue_tracker_mail_new_more')) ?>"><i class="rex-icon fa-angle-down" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_new_more') ?></span></summary>
                <div class="it-menu-pop">
                    <?php foreach ($newItems as $key => [$label, $icon, $href]): ?>
                    <a class="it-menu-item" href="<?= $href ?>" data-it-new-item="<?= $key ?>"><i class="rex-icon <?= $icon ?>" aria-hidden="true"></i> <?= rex_escape($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <details class="it-menu">
            <summary class="it-tb-btn" title="<?= rex_escape($package->i18n('issue_tracker_mail_options')) ?>"><i class="rex-icon fa-cog" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_options') ?></span></summary>
            <div class="it-menu-pop it-menu-pop--right">
                <a class="it-menu-item<?= $mode === 'preferences' ? ' is-checked' : '' ?>" href="<?= rex_url::backendPage('issue_tracker/preferences') ?>"><i class="rex-icon fa-bell-o" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_my_notifications') ?></a>
                <?php if (PermissionService::isAdmin()): ?>
                <a class="it-menu-item" href="<?= rex_url::backendPage('issue_tracker/settings') ?>"><i class="rex-icon fa-wrench" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_settings') ?></a>
                <?php endif; ?>
                <a class="it-menu-item" href="<?= rex_url::backendPage('issue_tracker/help') ?>"><i class="rex-icon fa-question-circle" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_help') ?></a>
                <div class="it-menu-label" id="it-fontsize-label"><?= $package->i18n('issue_tracker_mail_font_size') ?></div>
                <div class="it-fontsize" role="group" aria-labelledby="it-fontsize-label" data-it-fontsize>
                    <button type="button" class="it-fontsize-btn" data-it-fontsize-step="-1" title="<?= rex_escape($package->i18n('issue_tracker_mail_font_smaller')) ?>"><span aria-hidden="true">A−</span><span class="sr-only"><?= $package->i18n('issue_tracker_mail_font_smaller') ?></span></button>
                    <button type="button" class="it-fontsize-btn it-fontsize-value" data-it-fontsize-step="0" title="<?= rex_escape($package->i18n('issue_tracker_mail_font_reset')) ?>" aria-live="polite">100 %</button>
                    <button type="button" class="it-fontsize-btn it-fontsize-btn--large" data-it-fontsize-step="1" title="<?= rex_escape($package->i18n('issue_tracker_mail_font_larger')) ?>"><span aria-hidden="true">A+</span><span class="sr-only"><?= $package->i18n('issue_tracker_mail_font_larger') ?></span></button>
                </div>
            </div>
        </details>
    </div>

    <?php ob_start(); ?>
        <ul class="it-mbx-list">
            <?= $item($package->i18n('issue_tracker_all_active'), [], $fa('fa-inbox'), (int) ($fav['active_all'] ?? 0)) ?>
            <?= $item($package->i18n('issue_tracker_mail_assigned_to_me'), ['filter_assigned' => $userId], $fa('fa-user'), (int) ($fav['mine'] ?? 0)) ?>
            <?= $item($package->i18n('issue_tracker_mail_created_by_me'), ['filter_created_by' => $userId], $fa('fa-paper-plane-o'), (int) ($fav['created'] ?? 0)) ?>
            <?= $item($package->i18n('issue_tracker_mail_watched'), ['filter_watched' => 1], $fa('fa-eye'), (int) ($fav['watched'] ?? 0)) ?>
            <?= $item($package->i18n('issue_tracker_mail_mentioned'), ['filter_mentioned' => 1], $fa('fa-at'), (int) ($fav['mentioned_unread'] ?? 0), '', $package->i18n('issue_tracker_mail_mentioned_title')) ?>
            <?php if ((int) ($fav['with_due'] ?? 0) > 0): ?>
            <?= $item($package->i18n('issue_tracker_overdue'), ['filter_overdue' => 1], $fa('fa-calendar-times-o'), (int) ($fav['overdue'] ?? 0)) ?>
            <?php endif; ?>
        </ul>
        <?php ob_start(); ?>
        <ul class="it-mbx-list">
            <?php foreach ($statuses as $statusKey => $statusLabel): if ($statusKey === 'info') { continue; } ?>
                <?= $item($package->i18n('issue_tracker_status_' . $statusKey), ['filter_status' => (string) $statusKey], $statusDot((string) $statusKey), $byStatus[(string) $statusKey] ?? 0) ?>
            <?php endforeach; ?>
            <?= $item($package->i18n('issue_tracker_all_issues'), ['filter_status' => '_all_'], $fa('fa-archive'), (int) ($fav['all_issues'] ?? 0)) ?>
        </ul>
        <?= $section('status', $package->i18n('issue_tracker_status'), count($statuses), false, (string) ob_get_clean(), true) ?>
    <?= $section('issues', $package->i18n('issue_tracker_mail_section_issues'), null, true, (string) ob_get_clean()) ?>

    <?php ob_start(); ?>
        <ul class="it-mbx-list">
            <?php foreach (['inbox' => ['fa-envelope-o', $package->i18n('issue_tracker_messages_inbox'), $unreadMessages], 'sent' => ['fa-paper-plane-o', $package->i18n('issue_tracker_messages_sent'), 0]] as $b => [$icon, $label, $cnt]):
                $on = $mode === 'messages' && $box === $b; ?>
            <li><a class="it-mbx-item<?= $on ? ' is-active' : '' ?>" href="<?= $boxUrl($b) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= $fa($icon) ?><span class="it-mbx-label"><?= rex_escape($label) ?></span><?= $cnt > 0 ? '<span class="it-mbx-count">' . (int) $cnt . '</span>' : '' ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?= $section('messages', $package->i18n('issue_tracker_messages'), $unreadMessages > 0 ? $unreadMessages : null, true, (string) ob_get_clean()) ?>

    <?php ob_start(); ?>
        <ul class="it-mbx-list">
            <?php foreach ($projects as $project): if (in_array($project->getStatus(), ['archived', 'completed'], true)) { continue; }
                $pid = $project->getId();
                $pActive = $isActive(['filter_project' => $pid]);
                $archived = in_array($project->getStatus(), ['archived', 'completed'], true);
                $canManageProject = $canProjectForms && $project->isOwner($userId);
            ?>
            <li class="it-mbx-saved<?= $archived ? ' is-archived' : '' ?>">
                <a class="it-mbx-item<?= $pActive ? ' is-active' : '' ?>" href="<?= $url(['filter_project' => $pid]) ?>"<?= $pActive ? ' aria-current="page"' : '' ?>>
                    <span class="it-mbx-icon"><span class="it-dot it-dot--custom" style="<?= rex_escape(preg_match('/^#[0-9a-f]{3,8}$/i', $project->getColor()) ? '--c:' . $project->getColor() : '--c:#7c7c82') ?>" aria-hidden="true"></span></span>
                    <span class="it-mbx-label"><?= rex_escape($project->getName()) ?><?php if ($archived): ?> <span class="sr-only">(<?= $package->i18n('issue_tracker_project_status_' . $project->getStatus()) ?>)</span><?php endif; ?></span>
                    <?php if (($byProject[(string) $pid] ?? 0) > 0): ?><span class="it-mbx-count"><?= (int) $byProject[(string) $pid] ?></span><?php endif; ?>
                </a>
                <?php if ($canManageProject): ?>
                <details class="it-menu it-menu--mbx">
                    <summary class="it-mbx-more" title="<?= rex_escape($package->i18n('issue_tracker_actions')) ?>"><i class="rex-icon fa-ellipsis-h" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_actions') ?>: <?= rex_escape($project->getName()) ?></span></summary>
                    <div class="it-menu-pop">
                        <a class="it-menu-item" href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $pid]) ?>"><i class="rex-icon fa-pencil" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_edit') ?></a>
                        <a class="it-menu-item" href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $pid]) ?>#it-members"><i class="rex-icon fa-users" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_members') ?></a>
                        <?php if ($project->getStatus() !== 'archived'): ?>
                        <form method="post" action="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $pid]) ?>" onsubmit="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_mail_archive_confirm')) ?>">
                            <?= rex_csrf_token::factory('issue_tracker_project_archive')->getHiddenField() ?>
                            <input type="hidden" name="archive_project" value="1" />
                            <button type="submit" class="it-menu-item"><i class="rex-icon fa-archive" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_archive') ?></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </details>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php if (PermissionService::canCreateProject()): ?>
            <li><a class="it-mbx-item it-mbx-item--action" href="<?= rex_url::backendPage('issue_tracker/projects/create') ?>"><?= $fa('fa-plus') ?><span class="it-mbx-label"><?= $package->i18n('issue_tracker_mail_new_project') ?></span></a></li>
            <?php endif; ?>
        </ul>
        <?php if ($archivedProjects > 0): ob_start(); ?>
        <ul class="it-mbx-list">
            <?php foreach ($projects as $project): if (!in_array($project->getStatus(), ['archived', 'completed'], true)) { continue; }
                $pid = $project->getId();
                $pActive = $isActive(['filter_project' => $pid]);
                $archived = in_array($project->getStatus(), ['archived', 'completed'], true);
                $canManageProject = $canProjectForms && $project->isOwner($userId);
            ?>
            <li class="it-mbx-saved<?= $archived ? ' is-archived' : '' ?>">
                <a class="it-mbx-item<?= $pActive ? ' is-active' : '' ?>" href="<?= $url(['filter_project' => $pid]) ?>"<?= $pActive ? ' aria-current="page"' : '' ?>>
                    <span class="it-mbx-icon"><span class="it-dot it-dot--custom" style="<?= rex_escape(preg_match('/^#[0-9a-f]{3,8}$/i', $project->getColor()) ? '--c:' . $project->getColor() : '--c:#7c7c82') ?>" aria-hidden="true"></span></span>
                    <span class="it-mbx-label"><?= rex_escape($project->getName()) ?><?php if ($archived): ?> <span class="sr-only">(<?= $package->i18n('issue_tracker_project_status_' . $project->getStatus()) ?>)</span><?php endif; ?></span>
                    <?php if (($byProject[(string) $pid] ?? 0) > 0): ?><span class="it-mbx-count"><?= (int) $byProject[(string) $pid] ?></span><?php endif; ?>
                </a>
                <?php if ($canManageProject): ?>
                <details class="it-menu it-menu--mbx">
                    <summary class="it-mbx-more" title="<?= rex_escape($package->i18n('issue_tracker_actions')) ?>"><i class="rex-icon fa-ellipsis-h" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_actions') ?>: <?= rex_escape($project->getName()) ?></span></summary>
                    <div class="it-menu-pop">
                        <a class="it-menu-item" href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $pid]) ?>"><i class="rex-icon fa-pencil" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_edit') ?></a>
                        <a class="it-menu-item" href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $pid]) ?>#it-members"><i class="rex-icon fa-users" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_members') ?></a>
                        <?php if ($project->getStatus() !== 'archived'): ?>
                        <form method="post" action="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $pid]) ?>" onsubmit="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_mail_archive_confirm')) ?>">
                            <?= rex_csrf_token::factory('issue_tracker_project_archive')->getHiddenField() ?>
                            <input type="hidden" name="archive_project" value="1" />
                            <button type="submit" class="it-menu-item"><i class="rex-icon fa-archive" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_archive') ?></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </details>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?= $section('projects_archived', $package->i18n('issue_tracker_mail_projects_archived'), $archivedProjects, false, (string) ob_get_clean(), true) ?>
        <?php endif; ?>
    <?= $section('projects', $package->i18n('issue_tracker_projects'), count($projects), true, (string) ob_get_clean()) ?>

    <?php ob_start(); ?>
        <ul class="it-mbx-list">
            <?= $item($package->i18n('issue_tracker_mail_knowledge_all'), ['filter_status' => 'info'], $fa('fa-book'), $byStatus['info'] ?? 0) ?>
        </ul>
        <?php if (count(array_filter(array_keys($byKnowledgeCategory), static fn ($k) => $k !== '')) > 0): ob_start(); ?>
        <ul class="it-mbx-list">
            <?php foreach ($byKnowledgeCategory as $kCat => $kCount): if ($kCat === '') { continue; } ?>
                <?= $item((string) $kCat, ['filter_status' => 'info', 'filter_category' => (string) $kCat], $fa('fa-folder-o'), $kCount, ' it-mbx-item--sub') ?>
            <?php endforeach; ?>
        </ul>
        <?= $section('knowledge_categories', $package->i18n('issue_tracker_category'), count(array_filter(array_keys($byKnowledgeCategory), static fn ($k) => $k !== '')), false, (string) ob_get_clean(), true) ?>
        <?php endif; ?>
    <?= $section('knowledge', $package->i18n('issue_tracker_knowledge'), null, true, (string) ob_get_clean()) ?>

    <?php if (!empty($allTags)): ob_start(); ?>
        <ul class="it-mbx-list">
            <?php foreach ($allTags as $tag): ?>
                <?= $item($tag->getName(), ['filter_tag' => $tag->getId()], '<i class="it-mbx-icon it-mbx-icon--tag rex-icon fa-tag" style="' . rex_escape(preg_match('/^#[0-9a-f]{3,8}$/i', $tag->getColor()) ? '--c:' . $tag->getColor() : '--c:#7c7c82') . '" aria-hidden="true"></i>', $byTag[(string) $tag->getId()] ?? 0) ?>
            <?php endforeach; ?>
        </ul>
    <?= $section('tags', $package->i18n('issue_tracker_tags'), count($allTags), false, (string) ob_get_clean()) ?>
    <?php endif; ?>

    <?php ob_start(); ?>
        <ul class="it-mbx-list">
            <?php foreach ($savedFilters as $savedFilter):
                $params = array_intersect_key((array) $savedFilter['filters'], $defaults);
                $activeSaved = $isActive($params);
            ?>
            <li class="it-mbx-saved">
                <a class="it-mbx-item<?= $activeSaved ? ' is-active' : '' ?>" href="<?= $url((array) $savedFilter['filters']) ?>"<?= $activeSaved ? ' aria-current="page"' : '' ?>>
                    <i class="it-mbx-icon rex-icon <?= $savedFilter['is_default'] ? 'fa-star' : 'fa-filter' ?>" aria-hidden="true"></i>
                    <span class="it-mbx-label"><?= rex_escape($savedFilter['name']) ?></span>
                    <?php if ($savedFilter['is_default']): ?><span class="sr-only">(<?= $package->i18n('issue_tracker_is_default') ?>)</span><?php endif; ?>
                </a>
                <details class="it-menu it-menu--mbx">
                    <summary class="it-mbx-more" title="<?= rex_escape($package->i18n('issue_tracker_actions')) ?>"><i class="rex-icon fa-ellipsis-h" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_actions') ?>: <?= rex_escape($savedFilter['name']) ?></span></summary>
                    <div class="it-menu-pop">
                        <?php if (!$savedFilter['is_default']): ?>
                        <a class="it-menu-item" href="<?= rex_url::backendPage('issue_tracker/issues/list', ['set_default_filter' => $savedFilter['id']]) ?>"><i class="rex-icon fa-star" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_set_as_default') ?></a>
                        <?php endif; ?>
                        <form method="post" action="<?= rex_url::backendPage('issue_tracker/issues/list') ?>" onsubmit="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_delete_filter')) ?>?">
                            <input type="hidden" name="delete_filter" value="<?= (int) $savedFilter['id'] ?>" />
                            <button type="submit" class="it-menu-item it-menu-item--danger"><i class="rex-icon fa-trash" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_delete') ?></button>
                        </form>
                    </div>
                </details>
            </li>
            <?php endforeach; ?>
            <li>
                <button type="button" class="it-mbx-item it-mbx-item--action" data-toggle="modal" data-target="#save-filter-modal">
                    <i class="it-mbx-icon rex-icon fa-plus" aria-hidden="true"></i>
                    <span class="it-mbx-label"><?= $package->i18n('issue_tracker_save_filter') ?></span>
                </button>
            </li>
        </ul>
    <?= $section('saved', $package->i18n('issue_tracker_mail_saved_filters'), count($savedFilters), false, (string) ob_get_clean()) ?>
</nav>

<!-- Modal zum Filter speichern -->
<div class="modal fade" id="save-filter-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= rex_url::backendPage('issue_tracker/issues/list') ?>">
                <input type="hidden" name="save_filter" value="1" />
                <?php foreach ($state as $key => $value): ?>
                <input type="hidden" name="<?= rex_escape($key) ?>" value="<?= rex_escape((string) $value) ?>" />
                <?php endforeach; ?>

                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><?= $package->i18n('issue_tracker_save_filter') ?></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="filter_name"><?= $package->i18n('issue_tracker_filter_name') ?> *</label>
                        <input type="text" class="form-control" id="filter_name" name="filter_name" required maxlength="100">
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="is_default" value="1">
                            <?= $package->i18n('issue_tracker_set_as_default') ?>
                        </label>
                    </div>
                    <div class="alert alert-info">
                        <p><strong><?= $package->i18n('issue_tracker_filter') ?>:</strong></p>
                        <ul class="it-plain-list">
                            <?php if ($state['filter_status'] !== ''): ?>
                                <li><?= $package->i18n('issue_tracker_status') ?>: <?= $state['filter_status'] === '_all_' ? $package->i18n('issue_tracker_all_issues') : rex_escape($package->i18n('issue_tracker_status_' . $state['filter_status'])) ?></li>
                            <?php endif; ?>
                            <?php if ($state['filter_category'] !== ''): ?>
                                <li><?= $package->i18n('issue_tracker_category') ?>: <?= rex_escape($state['filter_category']) ?></li>
                            <?php endif; ?>
                            <?php if ((int) $state['filter_tag'] > 0): ?>
                                <?php foreach ($allTags as $tag): if ($tag->getId() !== (int) $state['filter_tag']) { continue; } ?>
                                <li><?= $package->i18n('issue_tracker_tags') ?>: <?= rex_escape($tag->getName()) ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if ((int) $state['filter_created_by'] > 0): ?>
                                <li><?= $package->i18n('issue_tracker_created_by') ?>: <?= rex_escape($users[(int) $state['filter_created_by']] ?? ('#' . (int) $state['filter_created_by'])) ?></li>
                            <?php endif; ?>
                            <?php if ((int) $state['filter_assigned'] > 0): ?>
                                <li><?= $package->i18n('issue_tracker_assigned') ?>: <?= rex_escape($users[(int) $state['filter_assigned']] ?? ('#' . (int) $state['filter_assigned'])) ?></li>
                            <?php endif; ?>
                            <?php if ((int) $state['filter_project'] > 0): ?>
                                <?php foreach ($projects as $project): if ($project->getId() !== (int) $state['filter_project']) { continue; } ?>
                                <li><?= $package->i18n('issue_tracker_project') ?>: <?= rex_escape($project->getName()) ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if ((int) $state['filter_watched'] === 1): ?>
                                <li><?= $package->i18n('issue_tracker_mail_watched') ?></li>
                            <?php endif; ?>
                            <?php if ((int) $state['filter_mentioned'] === 1): ?>
                                <li><?= $package->i18n('issue_tracker_mail_mentioned') ?></li>
                            <?php endif; ?>
                            <?php if ($state['search'] !== ''): ?>
                                <li><?= $package->i18n('issue_tracker_search') ?>: "<?= rex_escape($state['search']) ?>"</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?= $package->i18n('issue_tracker_cancel') ?></button>
                    <button type="submit" class="btn btn-success">
                        <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
