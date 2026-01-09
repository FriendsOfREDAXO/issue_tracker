<?php
/**
 * Issues Liste Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$issues = $this->getVar('issues', []);
$sortColumn = $this->getVar('sortColumn', 'created_at');
$sortOrder = $this->getVar('sortOrder', 'desc');

// Funktion für Sortier-Link
function getSortUrl($column, $currentColumn, $currentOrder) {
    $newOrder = ($column === $currentColumn && $currentOrder === 'ASC') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $newOrder;
    return rex_url::currentBackendPage($params);
}

// Funktion für Sortier-Icon
function getSortIcon($column, $currentColumn, $currentOrder) {
    if ($column !== $currentColumn) {
        return '<i class="rex-icon fa-sort" style="opacity: 0.3;"></i>';
    }
    return $currentOrder === 'ASC' 
        ? '<i class="rex-icon fa-sort-asc"></i>' 
        : '<i class="rex-icon fa-sort-desc"></i>';
}
?>

<!-- Issues Tabelle -->
<div class="panel panel-default">
    <div class="panel-heading">
        <div class="panel-title">
            <?= $package->i18n('issue_tracker_issues') ?>
            <a href="<?= rex_url::backendPage('issue_tracker/issues/create') ?>" class="btn btn-primary btn-xs pull-right" style="color: #fff !important;">
                <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_create_new') ?>
            </a>
        </div>
    </div>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th style="width: 50px;">
                    <a href="<?= getSortUrl('id', $sortColumn, $sortOrder) ?>" style="color: inherit; text-decoration: none;">
                        # <?= getSortIcon('id', $sortColumn, $sortOrder) ?>
                    </a>
                </th>
                <th>
                    <a href="<?= getSortUrl('title', $sortColumn, $sortOrder) ?>" style="color: inherit; text-decoration: none;">
                        <?= $package->i18n('issue_tracker_title') ?> <?= getSortIcon('title', $sortColumn, $sortOrder) ?>
                    </a>
                </th>
                <th style="width: 120px;">
                    <a href="<?= getSortUrl('category', $sortColumn, $sortOrder) ?>" style="color: inherit; text-decoration: none;">
                        <?= $package->i18n('issue_tracker_category') ?> <?= getSortIcon('category', $sortColumn, $sortOrder) ?>
                    </a>
                </th>
                <th style="width: 100px;">
                    <a href="<?= getSortUrl('status', $sortColumn, $sortOrder) ?>" style="color: inherit; text-decoration: none;">
                        <?= $package->i18n('issue_tracker_status') ?> <?= getSortIcon('status', $sortColumn, $sortOrder) ?>
                    </a>
                </th>
                <th style="width: 100px;">
                    <a href="<?= getSortUrl('priority', $sortColumn, $sortOrder) ?>" style="color: inherit; text-decoration: none;">
                        <?= $package->i18n('issue_tracker_priority') ?> <?= getSortIcon('priority', $sortColumn, $sortOrder) ?>
                    </a>
                </th>
                <th style="width: 120px;">
                    <a href="<?= getSortUrl('assigned_user_id', $sortColumn, $sortOrder) ?>" style="color: inherit; text-decoration: none;">
                        <?= $package->i18n('issue_tracker_assigned') ?> <?= getSortIcon('assigned_user_id', $sortColumn, $sortOrder) ?>
                    </a>
                </th>
                <th style="width: 130px;">
                    <a href="<?= getSortUrl('due_date', $sortColumn, $sortOrder) ?>" style="color: inherit; text-decoration: none;">
                        <?= $package->i18n('issue_tracker_due_date') ?> <?= getSortIcon('due_date', $sortColumn, $sortOrder) ?>
                    </a>
                </th>
                <th style="width: 150px;">
                    <a href="<?= getSortUrl('created_at', $sortColumn, $sortOrder) ?>" style="color: inherit; text-decoration: none;">
                        <?= $package->i18n('issue_tracker_created_at') ?> <?= getSortIcon('created_at', $sortColumn, $sortOrder) ?>
                    </a>
                </th>
                <th style="width: 120px;"><?= $package->i18n('issue_tracker_actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($issues)): ?>
            <tr>
                <td colspan="9" class="text-center">
                    <?= $package->i18n('issue_tracker_no_issues') ?>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($issues as $issue): ?>
                    <?php
                    $statusClasses = [
                        'open' => 'label-warning',
                        'in_progress' => 'label-info',
                        'closed' => 'label-success',
                        'rejected' => 'label-danger',
                    ];
                    
                    $priorityClasses = [
                        'low' => 'label-default',
                        'medium' => 'label-info',
                        'high' => 'label-warning',
                        'critical' => 'label-danger',
                    ];
                    
                    $assignedUser = $issue->getAssignedUser();
                    $tags = $issue->getTags();
                    $comments = $issue->getComments();
                    ?>
                    <tr>
                        <td><?= $issue->getId() ?></td>
                        <td>
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>">
                                <?= rex_escape($issue->getTitle()) ?>
                            </a>
                            <?php if (count($comments) > 0): ?>
                                <span class="text-muted" style="font-size: 0.9em; margin-left: 5px;">
                                    <i class="rex-icon fa-comment"></i> <?= count($comments) ?>
                                </span>
                            <?php endif; ?>
                            <?php 
                            // Domain- und YForm-Tabellen-Info anzeigen
                            $domainIds = $issue->getDomainIds();
                            $yformTables = $issue->getYformTables();
                            if (!empty($domainIds) || !empty($yformTables)): 
                            ?>
                                <br>
                                <small class="text-muted">
                                    <?php if (!empty($domainIds) && rex_addon::exists('yrewrite') && rex_addon::get('yrewrite')->isAvailable()): ?>
                                        <i class="rex-icon fa-globe"></i>
                                        <?php 
                                        $domainNames = [];
                                        foreach (rex_yrewrite::getDomains() as $domainName => $domain) {
                                            $domainId = method_exists($domain, 'getId') ? (int) $domain->getId() : null;
                                            if ($domainId !== null && in_array($domainId, $domainIds, true)) {
                                                $domainNames[] = rex_escape($domainName);
                                            }
                                        }
                                        echo implode(', ', $domainNames);
                                        ?>
                                    <?php endif; ?>
                                    <?php if (!empty($domainIds) && !empty($yformTables)): ?> | <?php endif; ?>
                                    <?php if (!empty($yformTables)): ?>
                                        <i class="rex-icon fa-database"></i>
                                        <?= rex_escape(implode(', ', $yformTables)) ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                            <?php if (!empty($tags)): ?>
                                <br>
                                <?php foreach ($tags as $tag): ?>
                                    <span class="label" style="background-color: <?= rex_escape($tag->getColor()) ?>; margin-right: 3px; margin-top: 3px;">
                                        <?= rex_escape($tag->getName()) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= rex_escape($issue->getCategory()) ?></td>
                        <td>
                            <span class="label <?= $statusClasses[$issue->getStatus()] ?? 'label-default' ?>">
                                <?= $package->i18n('issue_tracker_status_' . $issue->getStatus()) ?>
                            </span>
                        </td>
                        <td>
                            <span class="label <?= $priorityClasses[$issue->getPriority()] ?? 'label-default' ?>">
                                <?= $package->i18n('issue_tracker_priority_' . $issue->getPriority()) ?>
                            </span>
                        </td>
                        <td>
                            <?= $assignedUser ? rex_escape($assignedUser->getValue('name')) : '-' ?>
                        </td>
                        <td>
                            <?php if ($issue->getDueDate()): ?>
                                <?php if ($issue->isOverdue()): ?>
                                    <span class="label label-danger" title="<?= $package->i18n('issue_tracker_overdue') ?>">
                                        <?= $issue->getDueDate()->format('d.m.Y') ?>
                                    </span>
                                <?php else: ?>
                                    <?= $issue->getDueDate()->format('d.m.Y') ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $issue->getCreatedAt() ? $issue->getCreatedAt()->format('d.m.Y H:i') : '-' ?>
                            <?php 
                            $creator = $issue->getCreator();
                            if ($creator):
                            ?>
                                <br><small class="text-muted">von <?= rex_escape($creator->getValue('name')) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>" class="btn btn-xs btn-default" title="<?= $package->i18n('issue_tracker_view') ?>">
                                <i class="rex-icon fa-eye"></i>
                            </a>
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/edit', ['issue_id' => $issue->getId()]) ?>" class="btn btn-xs btn-primary" title="<?= $package->i18n('issue_tracker_edit') ?>">
                                <i class="rex-icon fa-edit"></i>
                            </a>
                            <?php if (rex::getUser()->isAdmin() || rex::getUser()->hasPerm('issue_tracker[issue_manager]')): ?>
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/list', ['func' => 'delete', 'issue_id' => $issue->getId()]) ?>" 
                               class="btn btn-xs btn-danger" 
                               title="<?= $package->i18n('issue_tracker_delete') ?>"
                               onclick="return confirm('<?= $package->i18n('issue_tracker_delete_confirm') ?>')">
                                <i class="rex-icon fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>