<?php
/**
 * Dashboard Fragment
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
    <!-- Meine Issues Übersicht -->
    <div class="row">
        <div class="col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body text-center">
                    <h3 class="text-danger"><?= $openIssues ?></h3>
                    <p><?= $package->i18n('issue_tracker_status_open') ?></p>
                    <small class="text-muted">Meine offenen Issues</small>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body text-center">
                    <h3 class="text-warning"><?= $inProgressIssues ?></h3>
                    <p><?= $package->i18n('issue_tracker_status_in_progress') ?></p>
                    <small class="text-muted">In Bearbeitung</small>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body text-center">
                    <h3 class="text-danger" style="font-weight: bold;"><?= $overdueIssues ?></h3>
                    <p><i class="rex-icon fa-exclamation-triangle"></i> Überfällig</p>
                    <small class="text-muted">Fälligkeit überschritten</small>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body text-center">
                    <h3 class="text-success"><?= $closedIssues ?></h3>
                    <p>Erledigt (30 Tage)</p>
                    <small class="text-muted">Geschlossene Issues</small>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recentIssues)): ?>
    <!-- Meine letzten Issues -->
    <div class="row">
        <div class="col-sm-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="rex-icon fa-list"></i> Meine letzten Issues</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="60">#</th>
                                <th><?= $package->i18n('issue_tracker_title') ?></th>
                                <th width="120"><?= $package->i18n('issue_tracker_status') ?></th>
                                <th width="120"><?= $package->i18n('issue_tracker_priority') ?></th>
                                <th width="150"><?= $package->i18n('issue_tracker_created_at') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentIssues as $issue): ?>
                            <tr>
                                <td><?= $issue->getId() ?></td>
                                <td>
                                    <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>">
                                        <?= rex_escape($issue->getTitle()) ?>
                                    </a>
                                    <?php if ($issue->isOverdue()): ?>
                                        <span class="label label-danger">
                                            <i class="rex-icon fa-exclamation-triangle"></i> Überfällig
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="label label-<?= $statusClasses[$issue->getStatus()] ?? 'default' ?>">
                                        <?= $package->i18n('issue_tracker_status_' . $issue->getStatus()) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="label label-<?= $priorityClasses[$issue->getPriority()] ?? 'default' ?>">
                                        <?= $package->i18n('issue_tracker_priority_' . $issue->getPriority()) ?>
                                    </span>
                                </td>
                                <td><?= $issue->getCreatedAt() ? $issue->getCreatedAt()->format('d.m.Y H:i') : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($recentActivities)): ?>
    <!-- Letzte Aktivitäten bei meinen Issues -->
    <div class="row">
        <div class="col-sm-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="rex-icon fa-history"></i> Letzte Aktivitäten</h3>
                </div>
                <div class="panel-body">
                    <div class="list-group" style="margin-bottom: 0;">
                        <?php 
                        foreach ($recentActivities as $activity): 
                            $user = rex_user::get($activity['user_id']);
                            $userName = $user ? $user->getValue('name') : 'Unbekannt';
                            $formattedEntry = \FriendsOfREDAXO\IssueTracker\HistoryService::formatEntry($activity, $package);
                        ?>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-sm-8">
                                    <strong>
                                        <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $activity['issue_id']]) ?>">
                                            #<?= $activity['issue_id'] ?> 
                                            <?php 
                                            $titleText = rex_escape($activity['issue_title']);
                                            echo mb_strlen($titleText) > 50 ? mb_substr($titleText, 0, 50) . '...' : $titleText;
                                            ?>
                                        </a>
                                    </strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php
                                        // formatEntry Ausgabe kürzen wenn zu lang
                                        $entryText = strip_tags($formattedEntry);
                                        if (mb_strlen($entryText) > 80) {
                                            echo mb_substr($entryText, 0, 80) . '...';
                                        } else {
                                            echo $formattedEntry;
                                        }
                                        ?>
                                        <em>von <?= rex_escape($userName) ?></em>
                                    </small>
                                </div>
                                <div class="col-sm-4 text-right">
                                    <small class="text-muted">
                                        <?= $activity['created_at']->format('d.m.Y H:i') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Nachrichten-Panel -->
        <div class="col-sm-4">
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
                    <p class="text-info" style="margin-bottom: 10px;">
                        <i class="rex-icon fa-info-circle"></i>
                        <?= sprintf($package->i18n('issue_tracker_unread_messages_info'), $unreadMessages) ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($recentMessages)): ?>
                    <ul class="list-unstyled" style="margin-bottom: 15px;">
                        <?php foreach (array_slice($recentMessages, 0, 3) as $msg): ?>
                        <li style="padding: 8px 0; border-bottom: 1px solid rgba(128,128,128,0.2); <?= !$msg->isRead() ? 'border-left: 3px solid #d9534f; margin: 0 -15px; padding-left: 12px; padding-right: 15px;' : '' ?>">
                            <a href="<?= rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $msg->getId()]) ?>" style="text-decoration: none; color: inherit;">
                                <div style="<?= !$msg->isRead() ? 'font-weight: bold;' : '' ?>">
                                    <i class="rex-icon fa-user-o"></i> <?= rex_escape($msg->getSenderName()) ?>
                                    <?php if (!$msg->isRead()): ?><span class="label label-danger" style="font-size: 9px; margin-left: 5px;">NEU</span><?php endif; ?>
                                    <span class="pull-right text-muted" style="font-size: 11px;"><?= $msg->getCreatedAt()->format('d.m. H:i') ?></span>
                                </div>
                                <small class="text-muted"><?= rex_escape(mb_substr($msg->getSubject(), 0, 25)) ?><?= mb_strlen($msg->getSubject()) > 25 ? '...' : '' ?></small>
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
                            <i class="rex-icon fa-inbox"></i> <?= $package->i18n('issue_tracker_messages_inbox') ?>
                        </a>
                        <a href="<?= rex_url::backendPage('issue_tracker/messages/compose') ?>" class="btn btn-primary btn-sm">
                            <i class="rex-icon fa-pencil"></i> Schreiben
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-sm-12">
            <a href="<?= rex_url::backendPage('issue_tracker/issues/create') ?>" class="btn btn-primary btn-lg" style="color: #fff;">
                <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_create_new') ?>
            </a>
            <a href="<?= rex_url::backendPage('issue_tracker/issues') ?>" class="btn btn-default btn-lg">
                <i class="rex-icon fa-list"></i> Alle meine Issues anzeigen
            </a>
        </div>
    </div>
</div>
