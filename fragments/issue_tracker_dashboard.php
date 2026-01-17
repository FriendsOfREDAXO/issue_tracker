<?php
/**
 * Dashboard Fragment - 2-spaltiges Layout
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$openIssues = $this->getVar('openIssues', 0);
$inProgressIssues = $this->getVar('inProgressIssues', 0);
$overdueIssues = $this->getVar('overdueIssues', 0);
$closedIssues = $this->getVar('closedIssues', 0);
$priorityCounts = $this->getVar('priorityCounts', []);
$recentIssues = $this->getVar('recentIssues', []);
$recentActivities = $this->getVar('recentActivities', []);
$unreadMessages = $this->getVar('unreadMessages', 0);
$recentMessages = $this->getVar('recentMessages', []);
$userProjects = $this->getVar('userProjects', []);
$isManager = $this->getVar('isManager', false);
$currentViewType = $this->getVar('currentViewType', 'own');

// Status- und Prioritäts-Klassen
$statusClasses = [
    'open' => 'danger',
    'in_progress' => 'warning',
    'rejected' => 'default',
    'closed' => 'success',
];
$priorityClasses = [
    'critical' => 'danger',
    'high' => 'warning',
    'normal' => 'info',
    'low' => 'default',
];
?>

<div class="issue-tracker-dashboard">
    <div class="row">
        <!-- ========== LINKE SPALTE ========== -->
        <div class="col-md-8">
            
            <!-- Überschrift für Normal-User -->
            <?php if (!$isManager): ?>
            <div style="margin-bottom: 20px;">
                <h3><?= $package->i18n('issue_tracker_own_issues') ?></h3>
            </div>
            <?php endif; ?>
            
            <!-- Filter für Manager -->
            <?php if ($isManager): ?>
            <div style="margin-bottom: 20px;">
                <small style="display: block; margin-bottom: 8px;"><strong><?= $package->i18n('issue_tracker_filter_by_view') ?>:</strong></small>
                <div class="btn-group" role="group">
                    <a href="<?= rex_url::backendPage('issue_tracker/dashboard', ['view' => 'own']) ?>" class="btn btn-sm <?= $currentViewType === 'own' ? 'btn-primary' : 'btn-default' ?>">
                        <i class="rex-icon fa-user"></i> <?= $package->i18n('issue_tracker_filter_personal') ?>
                    </a>
                    <a href="<?= rex_url::backendPage('issue_tracker/dashboard', ['view' => 'all']) ?>" class="btn btn-sm <?= $currentViewType === 'all' ? 'btn-primary' : 'btn-default' ?>">
                        <i class="rex-icon fa-list"></i> <?= $package->i18n('issue_tracker_filter_all') ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statistik-Kacheln -->
            <div class="row">
                <div class="col-sm-3">
                    <div class="panel panel-default">
                        <div class="panel-body text-center" style="padding: 15px 10px;">
                            <h3 class="text-danger" style="margin: 0;"><?= $openIssues ?></h3>
                            <small><?= $package->i18n('issue_tracker_status_open') ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="panel panel-default">
                        <div class="panel-body text-center" style="padding: 15px 10px;">
                            <h3 class="text-warning" style="margin: 0;"><?= $inProgressIssues ?></h3>
                            <small><?= $package->i18n('issue_tracker_status_in_progress') ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="panel panel-default">
                        <div class="panel-body text-center" style="padding: 15px 10px;">
                            <h3 class="text-danger" style="margin: 0; font-weight: bold;"><?= $overdueIssues ?></h3>
                            <small><i class="rex-icon fa-exclamation-triangle"></i> <?= $package->i18n('issue_tracker_status_overdue') ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="panel panel-default">
                        <div class="panel-body text-center" style="padding: 15px 10px;">
                            <h3 class="text-success" style="margin: 0;"><?= $closedIssues ?></h3>
                            <small><?= $package->i18n('issue_tracker_closed_30_days_short') ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schnellzugriff -->
            <div style="margin-bottom: 20px;">
                <a href="<?= rex_url::backendPage('issue_tracker/issues/create') ?>" class="btn btn-primary" style="color: #fff;">
                    <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_create_new') ?>
                </a>
            </div>

            <?php if (!empty($recentIssues)): ?>
            <!-- Meine letzten Issues -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="rex-icon fa-list"></i> <?= $currentViewType === 'all' ? $package->i18n('issue_tracker_all_recent_issues') : $package->i18n('issue_tracker_my_recent_issues') ?></h3>
                </div>
                <div class="panel-body" style="padding: 0;">
                    <table class="table table-striped table-hover" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th><?= $package->i18n('issue_tracker_title') ?></th>
                                <th width="90"><?= $package->i18n('issue_tracker_status') ?></th>
                                <th width="90"><?= $package->i18n('issue_tracker_priority') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentIssues as $issue): ?>
                            <tr>
                                <td><?= $issue->getId() ?></td>
                                <td>
                                    <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>">
                                        <?= rex_escape(mb_strlen($issue->getTitle()) > 40 ? mb_substr($issue->getTitle(), 0, 40) . '...' : $issue->getTitle()) ?>
                                    </a>
                                    <?php if ($issue->isOverdue()): ?>
                                        <span class="label label-danger" style="font-size: 9px;">
                                            <i class="rex-icon fa-exclamation-triangle"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="label label-<?= $statusClasses[$issue->getStatus()] ?? 'default' ?>" style="font-size: 10px;">
                                        <?= $package->i18n('issue_tracker_status_' . $issue->getStatus()) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="label label-<?= $priorityClasses[$issue->getPriority()] ?? 'default' ?>" style="font-size: 10px;">
                                        <?= $package->i18n('issue_tracker_priority_' . $issue->getPriority()) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($recentActivities)): ?>
            <!-- Letzte Aktivitäten -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="rex-icon fa-history"></i> <?= $currentViewType === 'all' ? $package->i18n('issue_tracker_all_recent_activities') : $package->i18n('issue_tracker_my_recent_activities') ?></h3>
                </div>
                <div class="panel-body" style="padding: 0;">
                    <div class="list-group" style="margin-bottom: 0;">
                        <?php 
                        foreach ($recentActivities as $activity): 
                            $user = rex_user::get($activity['user_id']);
                            $userName = $user ? $user->getValue('name') : 'Unbekannt';
                            $formattedEntry = \FriendsOfREDAXO\IssueTracker\HistoryService::formatEntry($activity, $package);
                        ?>
                        <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $activity['issue_id']]) ?>" class="list-group-item" style="padding: 10px 15px;">
                            <div class="row">
                                <div class="col-xs-9">
                                    <strong>#<?= $activity['issue_id'] ?></strong>
                                    <?php 
                                    $titleText = rex_escape($activity['issue_title']);
                                    echo mb_strlen($titleText) > 35 ? mb_substr($titleText, 0, 35) . '...' : $titleText;
                                    ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php
                                        $entryText = strip_tags($formattedEntry);
                                        echo mb_strlen($entryText) > 50 ? mb_substr($entryText, 0, 50) . '...' : $entryText;
                                        ?>
                                    </small>
                                </div>
                                <div class="col-xs-3 text-right">
                                    <small class="text-muted"><?= $activity['created_at']->format('d.m. H:i') ?></small>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ========== RECHTE SPALTE ========== -->
        <div class="col-md-4">
            
            <!-- Nachrichten-Panel -->
            <div class="panel panel-<?= $unreadMessages > 0 ? 'primary' : 'default' ?>">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_messages') ?>
                        <?php if ($unreadMessages > 0): ?>
                        <span class="badge" style="background: #d9534f; color: #fff;"><?= $unreadMessages ?></span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <?php if ($unreadMessages > 0): ?>
                    <p class="text-info" style="margin-bottom: 10px; font-size: 12px;">
                        <i class="rex-icon fa-info-circle"></i>
                        <?= $package->i18n('issue_tracker_unread_messages_info', $unreadMessages) ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($recentMessages)): ?>
                    <ul class="list-unstyled" style="margin-bottom: 15px;">
                        <?php foreach (array_slice($recentMessages, 0, 5) as $msg): ?>
                        <li style="padding: 8px 0; border-bottom: 1px solid rgba(128,128,128,0.2); <?= !$msg->isRead() ? 'border-left: 3px solid #d9534f; margin: 0 -15px; padding-left: 12px; padding-right: 15px;' : '' ?>">
                            <a href="<?= rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $msg->getId()]) ?>" style="text-decoration: none; color: inherit;">
                                <div style="<?= !$msg->isRead() ? 'font-weight: bold;' : '' ?>">
                                    <i class="rex-icon fa-user-o"></i> <?= rex_escape($msg->getSenderName()) ?>
                                    <?php if (!$msg->isRead()): ?><span class="label label-danger" style="font-size: 9px; margin-left: 5px;">NEU</span><?php endif; ?>
                                    <span class="pull-right text-muted" style="font-size: 11px;"><?= $msg->getCreatedAt()->format('d.m. H:i') ?></span>
                                </div>
                                <small class="text-muted"><?= rex_escape(mb_substr($msg->getSubject(), 0, 30)) ?><?= mb_strlen($msg->getSubject()) > 30 ? '...' : '' ?></small>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted text-center">
                        <i class="rex-icon fa-envelope-o" style="font-size: 24px;"></i><br>
                        <?= $package->i18n('issue_tracker_no_messages_inbox') ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="text-center" style="margin-top: 10px;">
                        <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>" class="btn btn-default btn-sm">
                            <i class="rex-icon fa-inbox"></i> <?= $package->i18n('issue_tracker_inbox') ?>
                        </a>
                        <a href="<?= rex_url::backendPage('issue_tracker/messages/compose') ?>" class="btn btn-primary btn-sm">
                            <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_compose') ?>
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($userProjects)): ?>
            <!-- Projekte -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="rex-icon fa-folder-open"></i> <?= $package->i18n('issue_tracker_projects') ?>
                        <a href="<?= rex_url::backendPage('issue_tracker/projects') ?>" class="btn btn-xs btn-default pull-right">
                            <?= $package->i18n('issue_tracker_show_all') ?>
                        </a>
                    </h3>
                </div>
                <div class="panel-body" style="padding: 10px;">
                    <?php foreach (array_slice($userProjects, 0, 5) as $project): 
                        $stats = $project->getStats();
                        $statusClass = 'default';
                        if ($project->getStatus() === 'active') $statusClass = 'success';
                        if ($project->getStatus() === 'on_hold') $statusClass = 'warning';
                    ?>
                    <div style="border-left: 3px solid <?= rex_escape($project->getColor()) ?>; padding: 8px 10px; margin-bottom: 10px; background: rgba(0,0,0,0.02);">
                        <a href="<?= rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId()]) ?>" style="color: inherit; font-weight: 500;">
                            <?= rex_escape($project->getName()) ?>
                        </a>
                        <?php if ($project->isOverdue()): ?>
                            <span class="label label-danger" style="font-size: 9px; margin-left: 3px;">!</span>
                        <?php endif; ?>
                        <div class="progress" style="height: 4px; margin: 6px 0 4px 0;">
                            <div class="progress-bar progress-bar-success" style="width: <?= $stats['progress'] ?>%;"></div>
                        </div>
                        <small class="text-muted">
                            <span class="text-danger"><?= $stats['open'] ?></span> offen · 
                            <span class="text-success"><?= $stats['closed'] ?></span> erledigt
                        </small>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($userProjects) > 5): ?>
                    <div class="text-center">
                        <a href="<?= rex_url::backendPage('issue_tracker/projects') ?>" class="text-muted" style="font-size: 12px;">
                            + <?= count($userProjects) - 5 ?> weitere Projekte
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
