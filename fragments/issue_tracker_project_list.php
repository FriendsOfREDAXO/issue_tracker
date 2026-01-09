<?php
/**
 * Projektliste Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');
$projects = $this->getVar('projects', []);
$currentUser = rex::getUser();

$statusLabels = [
    'active' => ['label' => $package->i18n('issue_tracker_project_status_active'), 'class' => 'success'],
    'completed' => ['label' => $package->i18n('issue_tracker_project_status_completed'), 'class' => 'info'],
    'archived' => ['label' => $package->i18n('issue_tracker_project_status_archived'), 'class' => 'default'],
];
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <div class="panel-title">
            <?= $package->i18n('issue_tracker_projects') ?>
            <a href="<?= rex_url::backendPage('issue_tracker/projects/create') ?>" class="btn btn-primary btn-xs pull-right" style="color: #fff !important;">
                <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_project_create') ?>
            </a>
        </div>
    </div>
    
    <?php if (empty($projects)): ?>
    <div class="panel-body">
        <p class="text-muted"><?= $package->i18n('issue_tracker_no_projects') ?></p>
    </div>
    <?php else: ?>
    <div class="row" style="padding: 15px;">
        <?php foreach ($projects as $project): 
            $stats = $project->getStats();
            $statusInfo = $statusLabels[$project->getStatus()] ?? ['label' => $project->getStatus(), 'class' => 'default'];
            $isOverdue = $project->isOverdue();
        ?>
        <div class="col-sm-6 col-md-4" style="margin-bottom: 20px;">
            <div class="panel panel-default" style="border-left: 4px solid <?= rex_escape($project->getColor()) ?>; height: 100%;">
                <div class="panel-heading" style="background: linear-gradient(to right, <?= rex_escape($project->getColor()) ?>15, transparent);">
                    <h4 class="panel-title" style="margin: 0;">
                        <a href="<?= rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId()]) ?>" style="color: inherit;">
                            <?php if ($project->getIsPrivate()): ?>
                                <i class="rex-icon fa-lock" title="<?= $package->i18n('issue_tracker_private') ?>"></i>
                            <?php endif; ?>
                            <?= rex_escape($project->getName()) ?>
                        </a>
                    </h4>
                </div>
                <div class="panel-body">
                    <!-- Status & Due Date -->
                    <div style="margin-bottom: 10px;">
                        <span class="label label-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                        <?php if ($project->getDueDate()): ?>
                            <span class="label label-<?= $isOverdue ? 'danger' : 'default' ?>" style="margin-left: 5px;">
                                <i class="rex-icon fa-calendar"></i>
                                <?= $project->getDueDate()->format('d.m.Y') ?>
                                <?php if ($isOverdue): ?>
                                    <i class="rex-icon fa-exclamation-triangle"></i>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Fortschrittsbalken -->
                    <div style="margin-bottom: 10px;">
                        <div class="progress" style="margin-bottom: 5px; height: 20px;">
                            <div class="progress-bar progress-bar-success" role="progressbar" 
                                 style="width: <?= $stats['progress'] ?>%; min-width: <?= $stats['progress'] > 0 ? '30px' : '0' ?>;">
                                <?= $stats['progress'] ?>%
                            </div>
                        </div>
                        <small class="text-muted">
                            <?= $stats['closed'] ?> / <?= $stats['total'] ?> <?= $package->i18n('issue_tracker_issues') ?>
                            <?php if ($stats['open'] > 0): ?>
                                | <span class="text-warning"><?= $stats['open'] ?> <?= $package->i18n('issue_tracker_open') ?></span>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <!-- Issue-Statistiken -->
                    <div style="margin-bottom: 10px;">
                        <?php if ($stats['open'] > 0): ?>
                            <span class="badge" style="background-color: #d9534f;"><?= $stats['open'] ?></span>
                            <small class="text-muted"><?= $package->i18n('issue_tracker_open') ?></small>
                        <?php endif; ?>
                        <?php if ($stats['in_progress'] > 0): ?>
                            <span class="badge" style="background-color: #f0ad4e;"><?= $stats['in_progress'] ?></span>
                            <small class="text-muted"><?= $package->i18n('issue_tracker_in_progress') ?></small>
                        <?php endif; ?>
                        <?php if ($stats['closed'] > 0): ?>
                            <span class="badge" style="background-color: #5cb85c;"><?= $stats['closed'] ?></span>
                            <small class="text-muted"><?= $package->i18n('issue_tracker_closed') ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Beschreibung (gekürzt) -->
                    <?php if ($project->getDescription()): ?>
                    <p class="text-muted" style="font-size: 12px; margin-bottom: 0; max-height: 40px; overflow: hidden;">
                        <?= rex_escape(mb_substr(strip_tags($project->getDescription()), 0, 100)) ?>...
                    </p>
                    <?php endif; ?>
                </div>
                <div class="panel-footer" style="padding: 8px 15px;">
                    <a href="<?= rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId()]) ?>" class="btn btn-xs btn-default">
                        <i class="rex-icon fa-eye"></i> <?= $package->i18n('issue_tracker_view') ?>
                    </a>
                    <?php if ($project->isOwner($currentUser->getId())): ?>
                    <a href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $project->getId()]) ?>" class="btn btn-xs btn-primary">
                        <i class="rex-icon fa-edit"></i>
                    </a>
                    <a href="<?= rex_url::backendPage('issue_tracker/projects/list', ['func' => 'delete', 'project_id' => $project->getId()]) ?>" 
                       class="btn btn-xs btn-danger"
                       onclick="return confirm('<?= $package->i18n('issue_tracker_project_delete_confirm') ?>')">
                        <i class="rex-icon fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
