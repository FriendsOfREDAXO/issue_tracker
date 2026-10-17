<?php
/**
 * Postfächer-Seitenleiste (wie Mail): Favoriten, Status, Projekte, Tags, Kategorien, gespeicherte Filter.
 * Jeder Eintrag ist ein Link mit den bestehenden Filter-Parametern der Themenliste.
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
    'search' => '',
];
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
    . ' (SELECT COUNT(*) FROM ' . $t('issues') . " i WHERE $vis) AS all_issues"
)[0] ?? [];

$projects = Project::getAll($userId);
$savedFilters = SavedFilterService::getByUser($userId);

$url = static fn (array $params): string => rex_url::backendPage('issue_tracker/issues/list', array_merge(['filter_status' => ''], $params));
$isActive = static function (array $params) use ($currentFilter, $defaults): bool {
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
<nav class="it-mbx" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_mailboxes')) ?>">
    <div class="it-mbx-section">
        <h3 class="it-mbx-title"><?= $package->i18n('issue_tracker_mail_favorites') ?></h3>
        <ul class="it-mbx-list">
            <?= $item($package->i18n('issue_tracker_all_active'), [], $fa('fa-inbox'), (int) ($fav['active_all'] ?? 0)) ?>
            <?= $item($package->i18n('issue_tracker_mail_assigned_to_me'), ['filter_assigned' => $userId], $fa('fa-user'), (int) ($fav['mine'] ?? 0)) ?>
            <?= $item($package->i18n('issue_tracker_mail_created_by_me'), ['filter_created_by' => $userId], $fa('fa-paper-plane-o'), (int) ($fav['created'] ?? 0)) ?>
            <?= $item($package->i18n('issue_tracker_mail_watched'), ['filter_watched' => 1], $fa('fa-eye'), (int) ($fav['watched'] ?? 0)) ?>
            <?= $item($package->i18n('issue_tracker_mail_mentioned'), ['filter_mentioned' => 1], $fa('fa-at'), (int) ($fav['mentioned_unread'] ?? 0), '', $package->i18n('issue_tracker_mail_mentioned_title')) ?>
        </ul>
    </div>

    <div class="it-mbx-section">
        <h3 class="it-mbx-title"><?= $package->i18n('issue_tracker_status') ?></h3>
        <ul class="it-mbx-list">
            <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                <?= $item($package->i18n('issue_tracker_status_' . $statusKey), ['filter_status' => (string) $statusKey], $statusDot((string) $statusKey), $byStatus[(string) $statusKey] ?? 0) ?>
            <?php endforeach; ?>
            <?= $item($package->i18n('issue_tracker_all_issues'), ['filter_status' => '_all_'], $fa('fa-archive'), (int) ($fav['all_issues'] ?? 0)) ?>
        </ul>
    </div>

    <?php if (!empty($projects)): ?>
    <div class="it-mbx-section">
        <h3 class="it-mbx-title"><?= $package->i18n('issue_tracker_projects') ?></h3>
        <ul class="it-mbx-list">
            <?php foreach ($projects as $project): ?>
                <?= $item($project->getName(), ['filter_project' => $project->getId()], '<span class="it-dot it-dot--custom" style="' . rex_escape(preg_match('/^#[0-9a-f]{3,8}$/i', $project->getColor()) ? '--c:' . $project->getColor() : '--c:#7c7c82') . '" aria-hidden="true"></span>', $byProject[(string) $project->getId()] ?? 0) ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($allTags)): ?>
    <div class="it-mbx-section">
        <h3 class="it-mbx-title"><?= $package->i18n('issue_tracker_tags') ?></h3>
        <ul class="it-mbx-list">
            <?php foreach ($allTags as $tag): ?>
                <?= $item($tag->getName(), ['filter_tag' => $tag->getId()], '<i class="it-mbx-icon it-mbx-icon--tag rex-icon fa-tag" style="' . rex_escape(preg_match('/^#[0-9a-f]{3,8}$/i', $tag->getColor()) ? '--c:' . $tag->getColor() : '--c:#7c7c82') . '" aria-hidden="true"></i>', $byTag[(string) $tag->getId()] ?? 0) ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($categories)): ?>
    <div class="it-mbx-section">
        <h3 class="it-mbx-title"><?= $package->i18n('issue_tracker_category') ?></h3>
        <ul class="it-mbx-list">
            <?php foreach ($categories as $category): ?>
                <?= $item((string) $category, ['filter_category' => (string) $category], $fa('fa-folder-o'), $byCategory[(string) $category] ?? 0) ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="it-mbx-section">
        <h3 class="it-mbx-title"><?= $package->i18n('issue_tracker_mail_saved_filters') ?></h3>
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
    </div>
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
