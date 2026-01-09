<?php
/**
 * Projektansicht Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$project = $this->getVar('project');
$stats = $this->getVar('stats', []);
$issues = $this->getVar('issues', []);
$users = $this->getVar('users', []);
$statuses = $this->getVar('statuses', []);
$canEdit = $this->getVar('canEdit', false);
$canWrite = $this->getVar('canWrite', false);

$currentUser = rex::getUser();

$statusLabels = [
    'active' => ['label' => $package->i18n('issue_tracker_project_status_active'), 'class' => 'success'],
    'completed' => ['label' => $package->i18n('issue_tracker_project_status_completed'), 'class' => 'info'],
    'archived' => ['label' => $package->i18n('issue_tracker_project_status_archived'), 'class' => 'default'],
];

$roleLabels = [
    'owner' => ['label' => $package->i18n('issue_tracker_role_owner'), 'class' => 'danger'],
    'member' => ['label' => $package->i18n('issue_tracker_role_member'), 'class' => 'primary'],
    'viewer' => ['label' => $package->i18n('issue_tracker_role_viewer'), 'class' => 'default'],
];

$statusInfo = $statusLabels[$project->getStatus()] ?? ['label' => $project->getStatus(), 'class' => 'default'];
$isOverdue = $project->isOverdue();

// Alle User für Hinzufügen laden
$allUsersSql = rex_sql::factory();
$allUsersSql->setQuery('SELECT id, name, login FROM ' . rex::getTable('user') . ' WHERE status = 1 ORDER BY name');
$allUsers = [];
foreach ($allUsersSql as $row) {
    $allUsers[$allUsersSql->getValue('id')] = $allUsersSql->getValue('name') ?: $allUsersSql->getValue('login');
}

$existingUserIds = array_column($users, 'user_id');
?>

<div class="issue-tracker-project-view">
    <!-- Header -->
    <div class="panel panel-default">
        <div class="panel-body">
            <a href="<?= rex_url::backendPage('issue_tracker/projects/list') ?>" class="btn btn-default">
                <i class="rex-icon fa-arrow-left"></i> <?= $package->i18n('issue_tracker_back_to_list') ?>
            </a>
            
            <?php if ($canWrite): ?>
            <a href="<?= rex_url::backendPage('issue_tracker/issues/create', ['project_id' => $project->getId()]) ?>" class="btn btn-success">
                <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_create_new') ?>
            </a>
            <?php endif; ?>
            
            <?php if ($canEdit): ?>
            <a href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $project->getId()]) ?>" class="btn btn-primary pull-right">
                <i class="rex-icon fa-edit"></i> <?= $package->i18n('issue_tracker_edit') ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Projekt Header -->
    <div class="panel panel-default" style="border-left: 5px solid <?= rex_escape($project->getColor()) ?>;">
        <div class="panel-heading" style="background: linear-gradient(to right, <?= rex_escape($project->getColor()) ?>20, transparent);">
            <h2 style="margin: 10px 0; font-size: 24px;">
                <?php if ($project->getIsPrivate()): ?>
                    <i class="rex-icon fa-lock" title="<?= $package->i18n('issue_tracker_private') ?>"></i>
                <?php endif; ?>
                <?= rex_escape($project->getName()) ?>
                <span class="label label-<?= $statusInfo['class'] ?>" style="font-size: 14px; margin-left: 10px;">
                    <?= $statusInfo['label'] ?>
                </span>
            </h2>
        </div>
        <div class="panel-body">
            <div class="row">
                <!-- Statistiken -->
                <div class="col-md-8">
                    <!-- Fortschrittsbalken -->
                    <h4><?= $package->i18n('issue_tracker_progress') ?></h4>
                    <div class="progress" style="height: 30px; margin-bottom: 15px;">
                        <div class="progress-bar progress-bar-success" role="progressbar" 
                             style="width: <?= $stats['progress'] ?>%; min-width: <?= $stats['progress'] > 0 ? '40px' : '0' ?>; line-height: 30px; font-size: 14px;">
                            <?= $stats['progress'] ?>%
                        </div>
                    </div>
                    
                    <!-- Stats Badges -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-xs-6 col-sm-3 text-center">
                            <div class="panel panel-default">
                                <div class="panel-body" style="padding: 10px;">
                                    <h3 style="margin: 0; color: #333;"><?= $stats['total'] ?></h3>
                                    <small class="text-muted"><?= $package->i18n('issue_tracker_total') ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-3 text-center">
                            <div class="panel panel-danger">
                                <div class="panel-body" style="padding: 10px;">
                                    <h3 style="margin: 0;"><?= $stats['open'] ?></h3>
                                    <small><?= $package->i18n('issue_tracker_open') ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-3 text-center">
                            <div class="panel panel-warning">
                                <div class="panel-body" style="padding: 10px;">
                                    <h3 style="margin: 0;"><?= $stats['in_progress'] ?></h3>
                                    <small><?= $package->i18n('issue_tracker_in_progress') ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-3 text-center">
                            <div class="panel panel-success">
                                <div class="panel-body" style="padding: 10px;">
                                    <h3 style="margin: 0;"><?= $stats['closed'] ?></h3>
                                    <small><?= $package->i18n('issue_tracker_closed') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Beschreibung -->
                    <?php if ($project->getDescription()): ?>
                    <div style="margin-bottom: 20px;">
                        <?= rex_markdown::factory()->parse($project->getDescription()) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-md-4">
                    <dl class="dl-horizontal">
                        <?php if ($project->getDueDate()): ?>
                        <dt><?= $package->i18n('issue_tracker_due_date') ?>:</dt>
                        <dd>
                            <?php if ($isOverdue): ?>
                                <span class="label label-danger">
                                    <i class="rex-icon fa-exclamation-triangle"></i> <?= $project->getDueDate()->format('d.m.Y') ?>
                                </span>
                            <?php else: ?>
                                <?= $project->getDueDate()->format('d.m.Y') ?>
                            <?php endif; ?>
                        </dd>
                        <?php endif; ?>
                        
                        <dt><?= $package->i18n('issue_tracker_created_at') ?>:</dt>
                        <dd><?= $project->getCreatedAt()->format('d.m.Y H:i') ?></dd>
                        
                        <dt><?= $package->i18n('issue_tracker_created_by') ?>:</dt>
                        <dd><?= $project->getCreator() ? rex_escape($project->getCreator()->getValue('name')) : '-' ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Issues Liste -->
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="rex-icon fa-ticket"></i> <?= $package->i18n('issue_tracker_issues') ?> (<?= count($issues) ?>)
                    </h4>
                </div>
                <?php if (empty($issues)): ?>
                <div class="panel-body">
                    <p class="text-muted"><?= $package->i18n('issue_tracker_no_issues') ?></p>
                    <?php if ($canWrite): ?>
                    <a href="<?= rex_url::backendPage('issue_tracker/issues/create', ['project_id' => $project->getId()]) ?>" class="btn btn-success btn-sm">
                        <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_create_new') ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th><?= $package->i18n('issue_tracker_title') ?></th>
                            <th style="width: 100px;"><?= $package->i18n('issue_tracker_status') ?></th>
                            <th style="width: 100px;"><?= $package->i18n('issue_tracker_priority') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $statusClasses = [
                            'open' => 'label-danger',
                            'in_progress' => 'label-warning',
                            'planned' => 'label-info',
                            'closed' => 'label-success',
                            'rejected' => 'label-default',
                        ];
                        $priorityClasses = [
                            'low' => 'label-default',
                            'normal' => 'label-info',
                            'high' => 'label-warning',
                            'urgent' => 'label-danger',
                        ];
                        foreach ($issues as $issue): 
                        ?>
                        <tr>
                            <td><?= $issue->getId() ?></td>
                            <td>
                                <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>">
                                    <?= rex_escape($issue->getTitle()) ?>
                                </a>
                            </td>
                            <td>
                                <span class="label <?= $statusClasses[$issue->getStatus()] ?? 'label-default' ?>">
                                    <?= rex_escape($statuses[$issue->getStatus()] ?? $issue->getStatus()) ?>
                                </span>
                            </td>
                            <td>
                                <span class="label <?= $priorityClasses[$issue->getPriority()] ?? 'label-default' ?>">
                                    <?= $package->i18n('issue_tracker_priority_' . $issue->getPriority()) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Team Mitglieder -->
        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="rex-icon fa-users"></i> <?= $package->i18n('issue_tracker_project_members') ?> (<?= count($users) ?>)
                    </h4>
                </div>
                <ul class="list-group">
                    <?php foreach ($users as $user): 
                        $roleInfo = $roleLabels[$user['role']] ?? ['label' => $user['role'], 'class' => 'default'];
                    ?>
                    <li class="list-group-item">
                        <span class="label label-<?= $roleInfo['class'] ?> pull-right"><?= $roleInfo['label'] ?></span>
                        <i class="rex-icon fa-user"></i>
                        <?= rex_escape($user['name']) ?>
                        <?php if ($canEdit && $user['user_id'] !== $currentUser->getId()): ?>
                        <a href="<?= rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId(), 'func' => 'remove_user', 'user_id' => $user['user_id']]) ?>" 
                           class="text-danger pull-right" style="margin-right: 10px;"
                           onclick="return confirm('<?= $package->i18n('issue_tracker_user_remove_confirm') ?>')">
                            <i class="rex-icon fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if ($canEdit): ?>
                <div class="panel-footer">
                    <form method="post" class="form-inline">
                        <input type="hidden" name="add_user" value="1" />
                        <div class="form-group" style="margin-right: 5px;">
                            <select name="user_id" class="form-control selectpicker" data-live-search="true" data-width="200px" title="<?= $package->i18n('issue_tracker_please_select') ?>" required>
                                <?php foreach ($allUsers as $userId => $userName): 
                                    if (in_array($userId, $existingUserIds)) continue;
                                ?>
                                <option value="<?= $userId ?>"><?= rex_escape($userName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right: 5px;">
                            <select name="role" class="form-control selectpicker" data-width="120px">
                                <option value="member"><?= $package->i18n('issue_tracker_role_member') ?></option>
                                <option value="viewer"><?= $package->i18n('issue_tracker_role_viewer') ?></option>
                                <option value="owner"><?= $package->i18n('issue_tracker_role_owner') ?></option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="rex-icon fa-plus"></i>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
