<?php
/**
 * Projektliste Fragment – Karten mit Farbe, Mitgliedern und Fortschritt
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\PermissionService;
use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');
$projects = $this->getVar('projects', []);
$filterStatus = $this->getVar('filterStatus', '');
$searchTerm = $this->getVar('searchTerm', '');
$currentUser = rex::getUser();

$statusLabels = [
    'active' => ['label' => rex_i18n::rawMsg('issue_tracker_project_status_active'), 'variant' => 'ok'],
    'completed' => ['label' => rex_i18n::rawMsg('issue_tracker_project_status_completed'), 'variant' => 'info'],
    'archived' => ['label' => rex_i18n::rawMsg('issue_tracker_project_status_archived'), 'variant' => 'muted'],
];

$hasActiveFilter = $filterStatus !== '' || $searchTerm !== '';
?>

<div class="issue-tracker-projects">
    <div class="it-toolbar">
        <form method="get" action="<?= rex_url::currentBackendPage() ?>" role="search">
            <input type="hidden" name="page" value="issue_tracker/projects/list" />
            <div class="it-search">
                <i class="rex-icon fa-search" aria-hidden="true"></i>
                <label class="it-sr" for="it-project-search"><?= $package->i18n('issue_tracker_search_placeholder') ?></label>
                <input type="search" id="it-project-search" name="search" class="form-control" value="<?= rex_escape($searchTerm) ?>" placeholder="<?= $package->i18n('issue_tracker_search_placeholder') ?>">
            </div>
            <label class="it-sr" for="it-project-status"><?= $package->i18n('issue_tracker_status') ?></label>
            <select id="it-project-status" name="filter_status" class="form-control it-select" data-it-autosubmit>
                <option value=""><?= $package->i18n('issue_tracker_ui_all_statuses') ?></option>
                <?php foreach ($statusLabels as $statusKey => $info): ?>
                <option value="<?= rex_escape($statusKey) ?>" <?= $filterStatus === $statusKey ? 'selected' : '' ?>><?= rex_escape($info['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($hasActiveFilter): ?>
            <a href="<?= rex_url::backendPage('issue_tracker/projects/list') ?>" class="btn btn-default">
                <i class="rex-icon fa-times" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_filter_reset') ?>
            </a>
            <?php endif; ?>
        </form>
        <?php if (PermissionService::canCreateProject()): ?>
        <div class="it-toolbar-end">
            <a href="<?= rex_url::backendPage('issue_tracker/projects/create') ?>" class="btn btn-primary it-btn-primary">
                <i class="rex-icon fa-plus" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_project_create') ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($projects)): ?>
    <div class="it-group">
        <div class="it-empty"><i class="rex-icon fa-folder-open-o" aria-hidden="true"></i><?= $package->i18n($hasActiveFilter ? 'issue_tracker_no_projects_filtered' : 'issue_tracker_no_projects') ?></div>
    </div>
    <?php else: ?>
    <div class="it-projects">
        <?php foreach ($projects as $project):
            $stats = $project->getStats();
            $statusInfo = $statusLabels[$project->getStatus()] ?? ['label' => $project->getStatus(), 'variant' => 'muted'];
            $isOverdue = $project->isOverdue();
            $members = $project->getUsers();
            $viewUrl = rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId()]);
        ?>
        <article class="it-pcard">
            <div class="it-pcard-head">
                <span class="it-icon it-icon--color" style="<?= UiHelper::colorVar($project->getColor()) ?>" aria-hidden="true"><i class="rex-icon <?= $project->getIsPrivate() ? 'fa-lock' : 'fa-folder' ?>"></i></span>
                <div class="it-pcard-title">
                    <h3><a href="<?= $viewUrl ?>"><?= rex_escape($project->getName()) ?></a></h3>
                    <div class="it-item-sub">
                        <?= UiHelper::badge($statusInfo['label'], $statusInfo['variant']) ?>
                        <?php if ($project->getIsPrivate()): ?>
                        <span><?= $package->i18n('issue_tracker_private') ?></span>
                        <?php endif; ?>
                        <?php if ($project->getDueDate()): ?>
                        <span<?= $isOverdue ? ' class="it-badge it-badge--danger"' : '' ?>><i class="rex-icon fa-calendar" aria-hidden="true"></i> <?= $project->getDueDate()->format('d.m.Y') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($project->getDescription()): ?>
            <p class="it-pcard-desc"><?= rex_escape(trim(strip_tags($project->getDescription()))) ?></p>
            <?php endif; ?>

            <div>
                <div class="it-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $stats['progress'] ?>" aria-label="<?= $package->i18n('issue_tracker_progress') ?>"><span style="--p:<?= (int) $stats['progress'] ?>%"></span></div>
                <div class="it-progress-label">
                    <span><?= $package->i18n('issue_tracker_ui_closed_of_total', (string) $stats['closed'], (string) $stats['total']) ?></span>
                    <span><?= (int) $stats['progress'] ?> %</span>
                </div>
            </div>

            <div class="it-pcard-stats">
                <?php if ($stats['open'] > 0): ?><?= UiHelper::badge($stats['open'] . ' ' . rex_i18n::rawMsg('issue_tracker_open'), 'info') ?><?php endif; ?>
                <?php if ($stats['in_progress'] > 0): ?><?= UiHelper::badge($stats['in_progress'] . ' ' . rex_i18n::rawMsg('issue_tracker_in_progress'), 'warn') ?><?php endif; ?>
                <?php if ($stats['closed'] > 0): ?><?= UiHelper::badge($stats['closed'] . ' ' . rex_i18n::rawMsg('issue_tracker_closed'), 'ok') ?><?php endif; ?>
            </div>

            <div class="it-pcard-foot">
                <span class="it-avatars" aria-label="<?= $package->i18n('issue_tracker_project_members') ?>: <?= count($members) ?>">
                    <?php foreach (array_slice($members, 0, 5) as $member): ?>
                    <?= UiHelper::avatar($member['user_id'], $member['name'] ?: $member['login'], true) ?>
                    <?php endforeach; ?>
                    <?php if (count($members) > 5): ?><span class="it-avatars-more">+<?= count($members) - 5 ?></span><?php endif; ?>
                </span>
                <span class="it-pcard-actions">
                    <a href="<?= $viewUrl ?>" class="btn btn-xs btn-default"><?= $package->i18n('issue_tracker_view') ?></a>
                    <?php if ($project->isOwner($currentUser->getId())): ?>
                    <a href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $project->getId()]) ?>" class="btn btn-xs btn-default" title="<?= $package->i18n('issue_tracker_edit') ?>" aria-label="<?= $package->i18n('issue_tracker_edit') ?>">
                        <i class="rex-icon fa-pencil" aria-hidden="true"></i>
                    </a>
                    <a href="<?= rex_url::backendPage('issue_tracker/projects/list', ['func' => 'delete', 'project_id' => $project->getId()]) ?>"
                       class="btn btn-xs btn-default" title="<?= $package->i18n('issue_tracker_delete') ?>" aria-label="<?= $package->i18n('issue_tracker_delete') ?>"
                       data-confirm="<?= $package->i18n('issue_tracker_project_delete_confirm') ?>">
                        <i class="rex-icon fa-trash" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </span>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
