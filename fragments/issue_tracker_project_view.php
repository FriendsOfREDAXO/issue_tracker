<?php
/**
 * Projektansicht Fragment – Kopf mit Projektfarbe, Kennzahlen, Themen im Stil von Mail, Mitglieder
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');

$project = $this->getVar('project');
$stats = $this->getVar('stats', []);
$issues = $this->getVar('issues', []);
$issuesTotalCount = $this->getVar('issuesTotalCount', count($issues));
$users = $this->getVar('users', []);
$statuses = $this->getVar('statuses', []);
$canEdit = $this->getVar('canEdit', false);
$canWrite = $this->getVar('canWrite', false);
$searchTerm = $this->getVar('searchTerm', '');
$filterStatus = $this->getVar('filterStatus', '');
$pager = $this->getVar('pager');
$hasActiveFilter = $searchTerm !== '' || $filterStatus !== '';

$currentUser = rex::getUser();

$statusLabels = [
    'active' => ['label' => rex_i18n::rawMsg('issue_tracker_project_status_active'), 'variant' => 'ok'],
    'completed' => ['label' => rex_i18n::rawMsg('issue_tracker_project_status_completed'), 'variant' => 'info'],
    'archived' => ['label' => rex_i18n::rawMsg('issue_tracker_project_status_archived'), 'variant' => 'muted'],
];

$roleLabels = [
    'owner' => ['label' => rex_i18n::rawMsg('issue_tracker_role_owner'), 'variant' => 'info'],
    'member' => ['label' => rex_i18n::rawMsg('issue_tracker_role_member'), 'variant' => 'muted'],
    'viewer' => ['label' => rex_i18n::rawMsg('issue_tracker_role_viewer'), 'variant' => 'muted'],
];

$statusInfo = $statusLabels[$project->getStatus()] ?? ['label' => $project->getStatus(), 'variant' => 'muted'];
$isOverdue = $project->isOverdue();

// Alle User für Hinzufügen laden
$allUsersSql = rex_sql::factory();
$allUsersSql->setQuery('SELECT id, name, login FROM ' . rex::getTable('user') . ' WHERE status = 1 ORDER BY name');
$allUsers = [];
foreach ($allUsersSql as $row) {
    $allUsers[$allUsersSql->getValue('id')] = $allUsersSql->getValue('name') ?: $allUsersSql->getValue('login');
}

$existingUserIds = array_column($users, 'user_id');

$statTile = static function (string $title, string $fa, string $color, string $valueHtml): string {
    return '<div class="it-tile"><div class="it-tile-head"><span class="it-icon it-c-' . $color . '" aria-hidden="true"><i class="rex-icon ' . $fa . '"></i></span><h3>'
        . rex_escape($title) . '</h3></div><div class="it-tile-value">' . $valueHtml . '</div></div>';
};
?>

<div class="issue-tracker-project-view">
    <div class="it-hero">
        <div class="it-hero-main">
            <span class="it-icon it-icon--lg it-icon--color" style="<?= UiHelper::colorVar($project->getColor()) ?>" aria-hidden="true"><i class="rex-icon <?= $project->getIsPrivate() ? 'fa-lock' : 'fa-folder' ?>"></i></span>
            <div>
                <h2><?= rex_escape($project->getName()) ?> <?= UiHelper::badge($statusInfo['label'], $statusInfo['variant']) ?></h2>
                <div class="it-hero-meta">
                    <?php if ($project->getIsPrivate()): ?>
                    <span><i class="rex-icon fa-lock" aria-hidden="true"></i><?= $package->i18n('issue_tracker_private') ?></span>
                    <?php endif; ?>
                    <?php if ($project->getDueDate()): ?>
                    <span<?= $isOverdue ? ' class="it-badge it-badge--danger"' : '' ?>><i class="rex-icon fa-calendar" aria-hidden="true"></i><?= $package->i18n('issue_tracker_due_date') ?>: <?= $project->getDueDate()->format('d.m.Y') ?></span>
                    <?php endif; ?>
                    <?php if ($project->getCreatedAt()): ?>
                    <span><i class="rex-icon fa-clock-o" aria-hidden="true"></i><?= $package->i18n('issue_tracker_created_at') ?>: <?= $project->getCreatedAt()->format('d.m.Y') ?></span>
                    <?php endif; ?>
                    <span><i class="rex-icon fa-user" aria-hidden="true"></i><?= $package->i18n('issue_tracker_created_by') ?>: <?= $project->getCreator() ? rex_escape(UiHelper::userName($project->getCreator())) : '-' ?></span>
                </div>
            </div>
        </div>
        <div class="it-hero-actions">
            <a href="<?= rex_url::backendPage('issue_tracker/projects/list') ?>" class="btn btn-default">
                <i class="rex-icon fa-angle-left" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_back_to_list') ?>
            </a>
            <?php if ($canEdit): ?>
            <a href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $project->getId()]) ?>" class="btn btn-default">
                <i class="rex-icon fa-pencil" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_edit') ?>
            </a>
            <?php endif; ?>
            <?php if ($canWrite): ?>
            <a href="<?= rex_url::backendPage('issue_tracker/issues/create', ['project_id' => $project->getId()]) ?>" class="btn btn-primary it-btn-primary">
                <i class="rex-icon fa-plus" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_create_new') ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="it-tiles">
        <?= $statTile(rex_i18n::rawMsg('issue_tracker_total'), 'fa-ticket', 'gray', (string) (int) $stats['total']) ?>
        <?= $statTile(rex_i18n::rawMsg('issue_tracker_open'), 'fa-circle-o', 'blue', (string) (int) $stats['open']) ?>
        <?= $statTile(rex_i18n::rawMsg('issue_tracker_in_progress'), 'fa-spinner', 'orange', (string) (int) $stats['in_progress']) ?>
        <?= $statTile(rex_i18n::rawMsg('issue_tracker_closed'), 'fa-check', 'green', (string) (int) $stats['closed']) ?>
        <div class="it-tile">
            <div class="it-tile-head"><span class="it-icon it-c-teal" aria-hidden="true"><i class="rex-icon fa-tasks"></i></span><h3><?= $package->i18n('issue_tracker_progress') ?></h3></div>
            <div class="it-tile-value"><?= (int) $stats['progress'] ?> <small>%</small></div>
            <div class="it-progress" aria-hidden="true"><span style="--p:<?= (int) $stats['progress'] ?>%"></span></div>
        </div>
    </div>

    <div class="it-cols">
        <div>
            <div class="it-toolbar">
                <form method="get" action="<?= rex_url::currentBackendPage() ?>" role="search">
                    <input type="hidden" name="page" value="issue_tracker/projects/view" />
                    <input type="hidden" name="project_id" value="<?= $project->getId() ?>" />
                    <div class="it-search">
                        <i class="rex-icon fa-search" aria-hidden="true"></i>
                        <label class="it-sr" for="it-project-issue-search"><?= $package->i18n('issue_tracker_search_placeholder') ?></label>
                        <input type="search" id="it-project-issue-search" name="search" class="form-control" value="<?= rex_escape($searchTerm) ?>" placeholder="<?= $package->i18n('issue_tracker_search_placeholder') ?>">
                    </div>
                    <label class="it-sr" for="it-project-issue-status"><?= $package->i18n('issue_tracker_status') ?></label>
                    <select id="it-project-issue-status" name="filter_status" class="form-control it-select" data-it-autosubmit>
                        <option value=""><?= $package->i18n('issue_tracker_ui_all_statuses') ?></option>
                        <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                        <option value="<?= rex_escape($statusKey) ?>" <?= $filterStatus === $statusKey ? 'selected' : '' ?>><?= $package->i18n('issue_tracker_status_' . $statusKey) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($hasActiveFilter): ?>
                    <a href="<?= rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId()]) ?>" class="btn btn-default">
                        <i class="rex-icon fa-times" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_filter_reset') ?>
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <h3 class="it-group-title"><?= $package->i18n('issue_tracker_issues') ?> <span class="it-count">(<?= (int) $issuesTotalCount ?>)</span></h3>
            <div class="it-group">
                <?php if (empty($issues)): ?>
                <div class="it-empty">
                    <i class="rex-icon fa-inbox" aria-hidden="true"></i>
                    <?= $package->i18n($hasActiveFilter ? 'issue_tracker_no_issues_filtered' : 'issue_tracker_no_issues') ?>
                </div>
                <?php else: ?>
                <ul class="it-list">
                    <?php foreach ($issues as $issue):
                        $assigned = $issue->getAssignedUser();
                        $personId = $assigned ? (int) $assigned->getId() : $issue->getCreatedBy();
                        $personName = $assigned ? UiHelper::userName($assigned) : UiHelper::userName($issue->getCreator(), '?');
                    ?>
                    <li>
                        <a class="it-item" href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>">
                            <?= UiHelper::avatar($personId, $personName) ?>
                            <div class="it-item-body">
                                <div class="it-item-title">
                                    <strong><?= rex_escape($issue->getTitle()) ?></strong>
                                    <span class="it-item-date"><?= rex_escape(UiHelper::relDate($issue->getUpdatedAt() ?? $issue->getCreatedAt())) ?></span>
                                </div>
                                <div class="it-item-sub">
                                    <span class="it-item-id">#<?= $issue->getId() ?></span>
                                    <?php if ($assigned): ?><span><i class="rex-icon fa-user" aria-hidden="true"></i> <?= rex_escape($personName) ?></span><?php endif; ?>
                                    <?= UiHelper::badge((string) ($statuses[$issue->getStatus()] ?? rex_i18n::rawMsg('issue_tracker_status_' . $issue->getStatus())), UiHelper::statusVariant($issue->getStatus())) ?>
                                    <?php if ('normal' !== $issue->getPriority()): ?><?= UiHelper::priorityBadge($issue->getPriority()) ?><?php endif; ?>
                                    <?php if ($issue->isOverdue()): ?><?= UiHelper::badge(rex_i18n::rawMsg('issue_tracker_overdue'), 'danger') ?><?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($pager->getRowCount() > $pager->getRowsPerPage()): ?>
                <div class="it-group-foot">
                    <?php
                    $pagerUrlProvider = new rex_context([
                        'page' => 'issue_tracker/projects/view',
                        'project_id' => $project->getId(),
                        'search' => $searchTerm,
                        'filter_status' => $filterStatus,
                    ]);
                    $pagerFragment = new rex_fragment();
                    $pagerFragment->setVar('urlprovider', $pagerUrlProvider);
                    $pagerFragment->setVar('pager', $pager);
                    echo $pagerFragment->parse('core/navigations/pagination.php');
                    ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ($canWrite && !$hasActiveFilter && empty($issues)): ?>
            <div class="it-settings-actions">
                <a href="<?= rex_url::backendPage('issue_tracker/issues/create', ['project_id' => $project->getId()]) ?>" class="btn btn-primary it-btn-primary">
                    <i class="rex-icon fa-plus" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_create_new') ?>
                </a>
            </div>
            <?php endif; ?>

            <?php if ($project->getDescription()): ?>
            <h3 class="it-group-title"><?= $package->i18n('issue_tracker_description') ?></h3>
            <div class="it-group">
                <div class="it-prose"><?= rex_markdown::factory()->parse($project->getDescription()) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div>
            <h3 class="it-group-title"><?= $package->i18n('issue_tracker_project_members') ?> <span class="it-count">(<?= count($users) ?>)</span></h3>
            <div class="it-group">
                <?php foreach ($users as $user):
                    $roleInfo = $roleLabels[$user['role']] ?? ['label' => $user['role'], 'variant' => 'muted'];
                    $memberName = $user['name'] ?: $user['login'];
                ?>
                <div class="it-item">
                    <?= UiHelper::avatar($user['user_id'], $memberName) ?>
                    <div class="it-item-body">
                        <div class="it-item-title"><strong><?= rex_escape($memberName) ?></strong></div>
                    </div>
                    <div class="it-item-side">
                        <?= UiHelper::badge($roleInfo['label'], $roleInfo['variant']) ?>
                        <?php if ($canEdit && $user['user_id'] !== $currentUser->getId()): ?>
                        <a href="<?= rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId(), 'func' => 'remove_user', 'user_id' => $user['user_id']]) ?>"
                           class="btn btn-xs btn-default" title="<?= $package->i18n('issue_tracker_ui_remove_member') ?>" aria-label="<?= $package->i18n('issue_tracker_ui_remove_member') ?>: <?= rex_escape($memberName) ?>"
                           data-confirm="<?= $package->i18n('issue_tracker_user_remove_confirm') ?>">
                            <i class="rex-icon fa-times" aria-hidden="true"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($canEdit): ?>
                <div class="it-row">
                    <form method="post" class="it-add-member">
                        <input type="hidden" name="add_user" value="1" />
                        <label class="it-sr" for="it-add-member-user"><?= $package->i18n('issue_tracker_ui_add_member') ?></label>
                        <select id="it-add-member-user" name="user_id" class="form-control" required>
                            <option value=""><?= $package->i18n('issue_tracker_ui_add_member') ?></option>
                            <?php foreach ($allUsers as $userId => $userName):
                                if (in_array($userId, $existingUserIds)) {
                                    continue;
                                }
                            ?>
                            <option value="<?= $userId ?>"><?= rex_escape($userName) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="it-sr" for="it-add-member-role"><?= $package->i18n('issue_tracker_ui_role') ?></label>
                        <select id="it-add-member-role" name="role" class="form-control">
                            <option value="member"><?= $package->i18n('issue_tracker_role_member') ?></option>
                            <option value="viewer"><?= $package->i18n('issue_tracker_role_viewer') ?></option>
                            <option value="owner"><?= $package->i18n('issue_tracker_role_owner') ?></option>
                        </select>
                        <button type="submit" class="btn btn-default btn-sm" title="<?= $package->i18n('issue_tracker_ui_add_member') ?>" aria-label="<?= $package->i18n('issue_tracker_ui_add_member') ?>">
                            <i class="rex-icon fa-plus" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
