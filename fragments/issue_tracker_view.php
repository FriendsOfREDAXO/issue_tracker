<?php
/**
 * Issue Thread-Ansicht Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$issue = $this->getVar('issue');
$comments = $this->getVar('comments', []);
$attachments = $this->getVar('attachments', []);
$statuses = $this->getVar('statuses', []);

if (!$issue) {
    throw new \RuntimeException('Issue object is required');
}

$createdBy = $issue->getCreator();
$assignedUser = $issue->getAssignedUser();
$tags = $issue->getTags();

$statusClass = [
    'open' => 'danger',
    'in_progress' => 'warning',
    'planned' => 'info',
    'rejected' => 'default',
    'closed' => 'success',
    'info' => 'primary'
];

$priorityClass = [
    'low' => 'default',
    'normal' => 'info',
    'high' => 'warning',
    'urgent' => 'danger'
];
?>

<div class="issue-tracker-view">
    <!-- Header mit Aktionsbuttons -->
    <div class="panel panel-default">
        <div class="panel-body">
            <a href="<?= rex_url::backendPage('issue_tracker/issues/list') ?>" class="btn btn-default">
                <i class="rex-icon fa-arrow-left"></i> <?= $package->i18n('issue_tracker_back_to_list') ?>
            </a>
            
            <?php 
            $history = $this->getVar('history', []);
            $canViewHistory = \FriendsOfREDAXO\IssueTracker\PermissionService::canViewHistory();
            if (!empty($history) && $canViewHistory): 
            ?>
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#history-modal">
                <i class="rex-icon fa-history"></i> <?= $package->i18n('issue_tracker_history') ?> (<?= count($history) ?>)
            </button>
            <?php endif; ?>
            
            <?php 
            $currentUser = rex::getUser();
            $canEdit = $currentUser->isAdmin() || $issue->getCreatedBy() === $currentUser->getId();
            if ($canEdit): 
            ?>
            <a href="<?= rex_url::backendPage('issue_tracker/issues/edit', ['issue_id' => $issue->getId()]) ?>" class="btn btn-primary pull-right">
                <i class="rex-icon fa-edit"></i> <?= $package->i18n('issue_tracker_edit') ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Issue Header -->
    <div class="panel panel-default">
        <div class="panel-heading issue-tracker-title-header">
            <h2 style="margin: 10px 0; font-size: 24px; color: inherit;">
                <span class="label label-default" style="font-size: 16px;">#<?= $issue->getId() ?></span>
                <?= rex_escape($issue->getTitle()) ?>
            </h2>
            <div style="margin-top: 10px;">
                <span class="label label-<?= $statusClass[$issue->getStatus()] ?? 'default' ?>" style="font-size: 13px;">
                    <i class="rex-icon fa-circle"></i> <?= rex_escape($statuses[$issue->getStatus()] ?? $issue->getStatus()) ?>
                </span>
                <span class="label label-<?= $priorityClass[$issue->getPriority()] ?? 'default' ?>" style="font-size: 13px; margin-left: 5px;">
                    <i class="rex-icon fa-exclamation"></i> <?= rex_escape($issue->getPriority()) ?>
                </span>
                <?php if (!empty($tags)): ?>
                    <?php foreach ($tags as $tag): ?>
                        <span class="label" style="background-color: <?= rex_escape($tag->getColor()) ?>; font-size: 13px; margin-left: 5px;">
                            <i class="rex-icon fa-tag"></i> <?= rex_escape($tag->getName()) ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-9">
                    <!-- Beschreibung -->
                    <div style="margin-bottom: 20px;">
                        <?= rex_markdown::factory()->parse($issue->getDescription()) ?>
                    </div>

                    <!-- Attachments -->
                    <?php if (!empty($attachments)): ?>
                    <div class="panel panel-default" style="margin-top: 20px;">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <i class="rex-icon fa-paperclip"></i> <?= $package->i18n('issue_tracker_attachments') ?>
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <?php foreach ($attachments as $attachment): ?>
                                <div class="col-sm-6 col-md-4" style="margin-bottom: 15px;">
                                    <div class="thumbnail">
                                        <?php if ($attachment->isImage()): ?>
                                            <a href="<?= $attachment->getUrl() ?>" class="issue-attachment-lightbox" data-type="image" title="<?= rex_escape($attachment->getOriginalFilename()) ?>">
                                                <img src="<?= $attachment->getThumbnailUrl() ?>" alt="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="max-height: 150px; width: auto;">
                                            </a>
                                        <?php elseif ($attachment->isVideo()): ?>
                                            <a href="<?= $attachment->getUrl() ?>" class="issue-attachment-lightbox" data-type="video" title="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="text-decoration: none; display: block;">
                                                <div style="padding: 30px; text-align: center; background: #f5f5f5; position: relative;">
                                                    <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 48px; color: #999;"></i>
                                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-shadow: 0 0 5px rgba(0,0,0,0.5);">
                                                        <i class="rex-icon fa-play-circle" style="font-size: 32px;"></i>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php else: ?>
                                            <div style="padding: 30px; text-align: center; background: #f5f5f5;">
                                                <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 48px; color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="caption">
                                            <h5 style="margin-top: 5px; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                                title="<?= rex_escape($attachment->getOriginalFilename()) ?>">
                                                <?= rex_escape($attachment->getOriginalFilename()) ?>
                                            </h5>
                                            <p class="text-muted" style="font-size: 12px; margin-bottom: 10px;">
                                                <?= $attachment->getFormattedFilesize() ?>
                                            </p>
                                            <a href="<?= $attachment->getUrl() ?>" class="btn btn-xs btn-default" target="_blank">
                                                <i class="rex-icon fa-download"></i> <?= $package->i18n('issue_tracker_download') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar mit Metadaten -->
                <div class="col-sm-3">

                    <!-- Panel 1: Status & Priorität -->
                    <div class="panel panel-default">
                        <div class="panel-heading" style="padding: 8px 12px;">
                            <strong><i class="rex-icon fa-info-circle"></i> <?= $package->i18n('issue_tracker_status') ?> & <?= $package->i18n('issue_tracker_priority') ?></strong>
                        </div>
                        <div class="panel-body" style="padding: 12px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">
                                <span class="label label-<?= $statusClass[$issue->getStatus()] ?? 'default' ?>" style="font-size: 13px; padding: 4px 10px;">
                                    <?= rex_escape($statuses[$issue->getStatus()] ?? $issue->getStatus()) ?>
                                </span>
                                <span class="label label-<?= $priorityClass[$issue->getPriority()] ?? 'default' ?>" style="font-size: 13px; padding: 4px 10px;">
                                    <?= rex_escape($issue->getPriority()) ?>
                                </span>
                            </div>
                            <?php if ($issue->getDueDate()): ?>
                            <div style="margin-top: 6px;">
                                <small class="text-muted"><i class="rex-icon fa-calendar"></i> <?= $package->i18n('issue_tracker_due_date') ?>:</small><br>
                                <?php if ($issue->isOverdue()): ?>
                                    <span class="label label-danger"><i class="rex-icon fa-exclamation-triangle"></i> <?= $issue->getDueDate()->format('d.m.Y H:i') ?></span>
                                <?php else: ?>
                                    <span style="font-size: 12px;"><?= $issue->getDueDate()->format('d.m.Y H:i') ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php 
                            // Tags direkt unter Status/Priorität
                            if (!empty($tags)): ?>
                            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #eee;">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="label" style="background-color: <?= rex_escape($tag->getColor()) ?>; margin: 1px; font-size: 11px;">
                                        <?= rex_escape($tag->getName()) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Panel 2: Personen -->
                    <div class="panel panel-default">
                        <div class="panel-heading" style="padding: 8px 12px;">
                            <strong><i class="rex-icon fa-users"></i> <?= $package->i18n('issue_tracker_people') ?></strong>
                        </div>
                        <div class="panel-body" style="padding: 0;">
                            <table class="table" style="margin: 0; font-size: 13px;">
                                <tr>
                                    <td style="padding: 8px 12px; width: 40%; color: #888; border-top: none;"><?= $package->i18n('issue_tracker_assigned') ?></td>
                                    <td style="padding: 8px 12px; border-top: none; font-weight: 500;"><?= $assignedUser ? rex_escape($assignedUser->getValue('name')) : '<span class="text-muted">–</span>' ?></td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; color: #888;"><?= $package->i18n('issue_tracker_created_by') ?></td>
                                    <td style="padding: 8px 12px;"><?= $createdBy ? rex_escape($createdBy->getValue('name')) : '–' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Panel 3: Details (Kategorie, AddOn, Domain, etc.) -->
                    <div class="panel panel-default">
                        <div class="panel-heading" style="padding: 8px 12px;">
                            <strong><i class="rex-icon fa-list"></i> <?= $package->i18n('issue_tracker_details') ?></strong>
                        </div>
                        <div class="panel-body" style="padding: 0;">
                            <table class="table" style="margin: 0; font-size: 13px;">
                                <tr>
                                    <td style="padding: 6px 12px; width: 40%; color: #888; border-top: none;"><?= $package->i18n('issue_tracker_category') ?></td>
                                    <td style="padding: 6px 12px; border-top: none;"><?= rex_escape($issue->getCategory()) ?></td>
                                </tr>
                                <?php if ($issue->getAssignedAddon()): ?>
                                <tr>
                                    <td style="padding: 6px 12px; color: #888;"><?= $package->i18n('issue_tracker_addon') ?></td>
                                    <td style="padding: 6px 12px;"><?= rex_escape($issue->getAssignedAddon()) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($issue->getVersion()): ?>
                                <tr>
                                    <td style="padding: 6px 12px; color: #888;"><?= $package->i18n('issue_tracker_version') ?></td>
                                    <td style="padding: 6px 12px;"><?= rex_escape($issue->getVersion()) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="padding: 6px 12px; color: #888;"><?= $package->i18n('issue_tracker_created_at') ?></td>
                                    <td style="padding: 6px 12px;"><?= $issue->getCreatedAt() ? $issue->getCreatedAt()->format('d.m.Y H:i') : '–' ?></td>
                                </tr>
                                <?php if ($issue->getUpdatedAt()): ?>
                                <tr>
                                    <td style="padding: 6px 12px; color: #888;"><?= $package->i18n('issue_tracker_updated_at') ?></td>
                                    <td style="padding: 6px 12px;"><?= $issue->getUpdatedAt()->format('d.m.Y H:i') ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($issue->getClosedAt()): ?>
                                <tr>
                                    <td style="padding: 6px 12px; color: #888;"><?= $package->i18n('issue_tracker_closed') ?></td>
                                    <td style="padding: 6px 12px;"><?= $issue->getClosedAt()->format('d.m.Y H:i') ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php 
                                $domainIds = $issue->getDomainIds();
                                if (!empty($domainIds) && rex_addon::exists('yrewrite') && rex_addon::get('yrewrite')->isAvailable()): 
                                ?>
                                <tr>
                                    <td style="padding: 6px 12px; color: #888;"><?= $package->i18n('issue_tracker_domain') ?></td>
                                    <td style="padding: 6px 12px;">
                                        <?php 
                                        foreach (rex_yrewrite::getDomains() as $domainName => $domain) {
                                            $domainId = method_exists($domain, 'getId') ? (int) $domain->getId() : null;
                                            if ($domainId !== null && in_array($domainId, $domainIds, true)) {
                                                echo '<span class="label label-info" style="font-size: 10px; margin: 1px;"><i class="rex-icon fa-globe"></i> ' . rex_escape($domainName) . '</span> ';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php 
                                $yformTables = $issue->getYformTables();
                                if (!empty($yformTables)): 
                                ?>
                                <tr>
                                    <td style="padding: 6px 12px; color: #888;"><?= $package->i18n('issue_tracker_yform_table') ?></td>
                                    <td style="padding: 6px 12px;">
                                        <?php foreach ($yformTables as $tableName): ?>
                                            <span class="label label-default" style="font-size: 10px; margin: 1px;"><i class="rex-icon fa-database"></i> <?= rex_escape($tableName) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php 
                                $project = $issue->getProject();
                                if ($project): 
                                ?>
                                <tr>
                                    <td style="padding: 6px 12px; color: #888;"><?= $package->i18n('issue_tracker_project') ?></td>
                                    <td style="padding: 6px 12px;">
                                        <a href="<?= rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId()]) ?>" 
                                           class="label" style="background-color: <?= rex_escape($project->getColor()) ?>; font-size: 10px;">
                                            <?php if ($project->getIsPrivate()): ?><i class="rex-icon fa-lock"></i> <?php endif; ?>
                                            <i class="rex-icon fa-folder-open"></i> <?= rex_escape($project->getName()) ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>

                            <?php 
                            $relatedTo = $issue->getDuplicateOf();
                            if ($relatedTo !== null): 
                                $relatedIssue = $issue->getDuplicateIssue();
                            ?>
                            <div style="padding: 6px 12px; border-top: 1px solid #ddd;">
                                <small class="text-muted"><?= $package->i18n('issue_tracker_related_to') ?>:</small><br>
                                <?php if ($relatedIssue): ?>
                                    <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $relatedIssue->getId()]) ?>" class="label label-info" style="font-size: 10px;">
                                        <i class="rex-icon fa-link"></i> #<?= $relatedIssue->getId() ?> – <?= rex_escape($relatedIssue->getTitle()) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="label label-warning" style="font-size: 10px;"><i class="rex-icon fa-link"></i> #<?= $relatedTo ?> (<?= $package->i18n('issue_tracker_related_not_found') ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php 
                            $relatedIssues = $issue->getDuplicates();
                            if (!empty($relatedIssues)): 
                            ?>
                            <div style="padding: 6px 12px; border-top: 1px solid #ddd;">
                                <small class="text-muted"><?= $package->i18n('issue_tracker_related_issues') ?>:</small><br>
                                <?php foreach ($relatedIssues as $related): ?>
                                    <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $related->getId()]) ?>" class="label label-default" style="display: inline-block; margin: 1px; font-size: 10px;">
                                        <i class="rex-icon fa-link"></i> #<?= $related->getId() ?> – <?= rex_escape($related->getTitle()) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Panel 4: Aktionen -->
                    <?php 
                    $currentUser = rex::getUser();
                    $canChangeStatus = $currentUser->isAdmin() || 
                                       $issue->getAssignedUserId() === $currentUser->getId() ||
                                       $issue->getCreatedBy() === $currentUser->getId();
                    if ($canChangeStatus): 
                    ?>
                    <div class="panel panel-default">
                        <div class="panel-heading" style="padding: 8px 12px;">
                            <strong><i class="rex-icon fa-cogs"></i> <?= $package->i18n('issue_tracker_actions') ?></strong>
                        </div>
                        <div class="panel-body" style="padding: 12px;">
                            <!-- Status ändern -->
                            <form method="post" id="status-change-form">
                                <input type="hidden" name="change_status" value="1" />
                                <div class="form-group" style="margin-bottom: 8px;">
                                    <label style="font-size: 12px; margin-bottom: 4px;"><i class="rex-icon fa-exchange"></i> <?= $package->i18n('issue_tracker_change_status') ?></label>
                                    <select name="status" class="form-control selectpicker" data-width="100%" id="status-select">
                                        <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                                        <option value="<?= rex_escape($statusKey) ?>" 
                                                <?= $issue->getStatus() === $statusKey ? 'selected' : '' ?>
                                                data-content="<span class='label label-<?= ['open' => 'danger', 'in_progress' => 'warning', 'planned' => 'info', 'rejected' => 'default', 'closed' => 'success'][$statusKey] ?? 'default' ?>'><?= $package->i18n('issue_tracker_status_' . $statusKey) ?></span>">
                                            <?= $package->i18n('issue_tracker_status_' . $statusKey) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success btn-block">
                                    <i class="rex-icon fa-check"></i> <?= $package->i18n('issue_tracker_change_status') ?>
                                </button>
                            </form>

                            <!-- Reminder -->
                            <?php 
                            $canSendReminder = $this->getVar('canSendReminder', false);
                            $lastReminderAt = $this->getVar('lastReminderAt', null);
                            $assignedUser = $issue->getAssignedUser();
                            if ($assignedUser && !in_array($issue->getStatus(), ['closed', 'rejected'], true)): 
                            ?>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                                <form method="post" onsubmit="return confirm('<?= $package->i18n('issue_tracker_reminder_confirm') ?>')">
                                    <input type="hidden" name="send_reminder" value="1" />
                                    <button type="submit" class="btn btn-sm btn-block <?= $canSendReminder ? 'btn-warning' : 'btn-default' ?>" <?= $canSendReminder ? '' : 'disabled' ?>>
                                        <i class="rex-icon fa-bell"></i> <?= $package->i18n('issue_tracker_reminder_send') ?>
                                    </button>
                                    <?php if ($lastReminderAt): ?>
                                    <p class="text-muted text-center" style="font-size: 10px; margin-top: 4px; margin-bottom: 0;">
                                        <i class="rex-icon fa-clock-o"></i> 
                                        <?= $package->i18n('issue_tracker_reminder_last_sent', (new DateTime($lastReminderAt))->format('d.m.Y H:i')) ?>
                                    </p>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Panel 5: Beobachter -->
                    <?php
                    $isWatching = $this->getVar('isWatching', false);
                    $watcherCount = $this->getVar('watcherCount', 0);
                    $watcherUsers = $this->getVar('watcherUsers', []);
                    $canManageWatchers = $currentUser->isAdmin() 
                        || $currentUser->hasPerm('issue_tracker[issue_manager]') 
                        || $issue->getCreatedBy() === $currentUser->getId();
                    ?>
                    <div class="panel panel-default">
                        <div class="panel-heading" style="padding: 8px 12px;">
                            <strong><i class="rex-icon fa-eye"></i> <?= $package->i18n('issue_tracker_watchers') ?></strong>
                            <?php if ($watcherCount > 0): ?>
                            <span class="badge" style="font-size: 10px; background: #999;"><?= $watcherCount ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="panel-body" style="padding: 12px;">
                            <!-- Toggle Watch -->
                            <form method="post" style="margin-bottom: 0;">
                                <input type="hidden" name="toggle_watch" value="1" />
                                <?php if ($isWatching): ?>
                                <button type="submit" class="btn btn-sm btn-block btn-default" style="margin-bottom: 8px;">
                                    <i class="rex-icon fa-eye-slash"></i> <?= $package->i18n('issue_tracker_unwatch') ?>
                                </button>
                                <?php else: ?>
                                <button type="submit" class="btn btn-sm btn-block btn-info" style="margin-bottom: 8px;">
                                    <i class="rex-icon fa-eye"></i> <?= $package->i18n('issue_tracker_watch') ?>
                                </button>
                                <?php endif; ?>
                            </form>

                            <?php if ($watcherCount > 0): ?>
                            <ul class="list-unstyled" style="font-size: 12px; margin: 0; padding: 0;">
                                <?php foreach ($watcherUsers as $watcher): ?>
                                <li style="padding: 3px 0; display: flex; align-items: center; justify-content: space-between;">
                                    <span><i class="rex-icon fa-user" style="color: #aaa;"></i> <?= rex_escape($watcher['name']) ?></span>
                                    <?php if ($canManageWatchers && $watcher['id'] !== $currentUser->getId()): ?>
                                    <form method="post" style="display: inline; margin: 0;">
                                        <input type="hidden" name="remove_watcher" value="<?= $watcher['id'] ?>" />
                                        <button type="submit" class="btn btn-xs btn-link text-danger" title="<?= $package->i18n('issue_tracker_watcher_remove') ?>" style="padding: 0; line-height: 1;">
                                            <i class="rex-icon fa-times"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>

                            <!-- Watcher einladen -->
                            <?php if ($canManageWatchers): ?>
                            <?php
                            $availableUsers = $this->getVar('availableWatcherUsers', []);
                            if (!empty($availableUsers)):
                            ?>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                                <form method="post">
                                    <input type="hidden" name="add_watchers" value="1" />
                                    <div class="form-group" style="margin-bottom: 6px;">
                                        <select name="watcher_user_ids[]" class="form-control selectpicker" 
                                                multiple="multiple" 
                                                data-live-search="true" 
                                                data-actions-box="true"
                                                data-selected-text-format="count > 2"
                                                data-size="8"
                                                data-width="100%"
                                                title="<?= $package->i18n('issue_tracker_watcher_add') ?>">
                                            <?php foreach ($availableUsers as $uid => $uname): ?>
                                            <option value="<?= $uid ?>"><?= rex_escape($uname) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-block btn-success">
                                        <i class="rex-icon fa-user-plus"></i> <?= $package->i18n('issue_tracker_watcher_invite') ?>
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Verwandtes Issue Panel (außerhalb der Sidebar, admin-only) -->
    <?php if ($currentUser->isAdmin() && $relatedTo === null): ?>
    <div class="panel panel-info">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="rex-icon fa-link"></i> <?= $package->i18n('issue_tracker_mark_as_related') ?>
            </h3>
        </div>
        <div class="panel-body">
            <form method="post" id="mark-related-form">
                <input type="hidden" name="func" value="mark_related" />
                <div class="row">
                    <div class="col-md-9">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="related-issue-select"><?= $package->i18n('issue_tracker_select_related_issue') ?>:</label>
                            <p class="help-block" style="margin-top: 5px; margin-bottom: 10px;">
                                <small><?= $package->i18n('issue_tracker_related_help_text') ?></small>
                            </p>
                            <select name="related_to" 
                                    id="related-issue-select" 
                                    class="form-control selectpicker" 
                                    data-live-search="true"
                                    data-width="100%"
                                    data-size="10"
                                    required>
                                <option value="">-- <?= $package->i18n('issue_tracker_select_open_issue') ?> --</option>
                                <?php 
                                // Offene Issues laden
                                $sqlIssues = rex_sql::factory();
                                $sqlIssues->setQuery('
                                    SELECT id, title, category, priority, status
                                    FROM ' . rex::getTable('issue_tracker_issues') . '
                                    WHERE id != ?
                                    AND status IN ("open", "in_progress", "planned")
                                    ORDER BY id DESC
                                    LIMIT 100
                                ', [$issue->getId()]);
                                
                                foreach ($sqlIssues as $issueOption) {
                                    $statusLabels = [
                                        'open' => '🔴',
                                        'in_progress' => '🟡',
                                        'planned' => '🔵'
                                    ];
                                    $statusIcon = $statusLabels[$issueOption->getValue('status')] ?? '⚪';
                                    
                                    echo '<option value="' . (int) $issueOption->getValue('id') . '">'
                                        . $statusIcon . ' #' . (int) $issueOption->getValue('id') . ' - ' . rex_escape($issueOption->getValue('title'))
                                        . ' (' . rex_escape($issueOption->getValue('category')) . ')'
                                        . '</option>';
                                }
                                ?>
                            </select>
                            <small class="help-block">
                                <i class="rex-icon fa-info-circle"></i> Dieses Issue wird mit dem ausgewählten Issue verknüpft und geschlossen.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-info btn-block">
                            <i class="rex-icon fa-link"></i> <?= $package->i18n('issue_tracker_link_action') ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verknüpfung entfernen Panel -->
    <?php if ($currentUser->isAdmin() && $relatedTo !== null): ?>
    <div class="panel panel-warning">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="rex-icon fa-link"></i> <?= $package->i18n('issue_tracker_related_info') ?>
            </h3>
        </div>
        <div class="panel-body">
            <p>
                <?php if ($relatedIssue): ?>
                    Dieses Issue ist verknüpft mit 
                    <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $relatedIssue->getId()]) ?>" 
                       class="label label-info">
                        <i class="rex-icon fa-link"></i> #<?= $relatedIssue->getId() ?> - <?= rex_escape($relatedIssue->getTitle()) ?>
                    </a>
                <?php else: ?>
                    <?= $package->i18n('issue_tracker_related_to') ?> #<?= $relatedTo ?>
                <?php endif; ?>
            </p>
            <form method="post" style="margin-top: 15px;">
                <input type="hidden" name="func" value="unmark_related" />
                <button type="submit" class="btn btn-warning">
                    <i class="rex-icon fa-unlink"></i> <?= $package->i18n('issue_tracker_unmark_as_related') ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php 
    // Verwandtes Issue Warnung anzeigen
    if ($relatedTo !== null && $relatedIssue): 
    ?>
    <div class="alert alert-info">
        <h4><i class="rex-icon fa-link"></i> <?= $package->i18n('issue_tracker_related_info') ?></h4>
        <p>
            Dieses Issue wurde als verwandt markiert mit: 
            <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $relatedIssue->getId()]) ?>">
                #<?= $relatedIssue->getId() ?> - <?= rex_escape($relatedIssue->getTitle()) ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <!-- Kommentare -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title">
                <i class="rex-icon fa-comments"></i> <?= $package->i18n('issue_tracker_comments') ?> (<?= count($comments) ?>)
            </h4>
        </div>
        <div class="panel-body">
            <?php if (empty($comments)): ?>
                <p class="text-muted"><?= $package->i18n('issue_tracker_no_comments') ?></p>
            <?php else: ?>
                <?php 
                // Gepinnte Kommentare und Lösungen oben anzeigen
                $pinnedComments = array_filter($comments, function($c) { return $c->isPinned() || $c->isSolution(); });
                
                if (!empty($pinnedComments)):
                ?>
                <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #eee;">
                    <h5 style="margin-bottom: 15px; color: #666;">
                        <i class="rex-icon fa-star"></i> <?= $package->i18n('issue_tracker_highlighted_answers') ?>
                    </h5>
                    <?php 
                    foreach ($pinnedComments as $comment): 
                        $commentUser = $comment->getCreator();
                        $commentAttachments = \FriendsOfREDAXO\IssueTracker\Attachment::getByComment($comment->getId());
                    ?>
                    <div class="issue-tracker-comment-card <?= $comment->isSolution() ? 'border-success' : ($comment->isPinned() ? 'border-info' : '') ?>" style="<?= $comment->isSolution() ? 'border-left: 4px solid #5cb85c;' : ($comment->isPinned() ? 'border-left: 4px solid #5bc0de;' : '') ?>">
                        <div class="media-body">
                            <h5 class="media-heading">
                                <?php if ($comment->isSolution()): ?>
                                    <span class="label label-success" style="margin-right: 5px;">
                                        <i class="rex-icon fa-check-circle"></i> <?= $package->i18n('issue_tracker_solution') ?>
                                    </span>
                                <?php elseif ($comment->isPinned()): ?>
                                    <span class="label label-info" style="margin-right: 5px;">
                                        <i class="rex-icon fa-thumb-tack"></i> <?= $package->i18n('issue_tracker_pinned') ?>
                                    </span>
                                <?php endif; ?>
                                <strong><?= $commentUser ? rex_escape($commentUser->getValue('name')) : 'Unknown' ?></strong>
                                <small class="text-muted"> - <?= $comment->getCreatedAt() ? $comment->getCreatedAt()->format('d.m.Y H:i') : '-' ?></small>
                                <?php if ($comment->getUpdatedAt()): ?>
                                    <small class="text-muted" style="font-style: italic;"> (<?= $package->i18n('issue_tracker_edited') ?> <?= $comment->getUpdatedAt()->format('d.m.Y H:i') ?>)</small>
                                <?php endif; ?>
                                <?php 
                                $currentUser = rex::getUser();
                                $canModerate = $currentUser->isAdmin() || $issue->getCreatedBy() === $currentUser->getId();
                                $canEdit = $currentUser->isAdmin() || $comment->getCreatedBy() === $currentUser->getId();
                                if ($canModerate || $canEdit): 
                                ?>
                                <div class="pull-right">
                                    <?php if ($canModerate): ?>
                                    <form method="post" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="toggle_pin" value="<?= $comment->getId() ?>" />
                                        <button type="submit" class="btn btn-xs <?= $comment->isPinned() ? 'btn-info' : 'btn-default' ?>" 
                                                title="<?= $comment->isPinned() ? 'Pin entfernen' : 'Kommentar pinnen' ?>">
                                            <i class="rex-icon fa-thumb-tack"></i>
                                        </button>
                                    </form>
                                    <form method="post" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="toggle_solution" value="<?= $comment->getId() ?>" />
                                        <button type="submit" class="btn btn-xs <?= $comment->isSolution() ? 'btn-success' : 'btn-default' ?>" 
                                                title="<?= $comment->isSolution() ? 'Lösung entfernen' : 'Als Lösung markieren' ?>">
                                            <i class="rex-icon fa-check-circle"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-xs btn-default" onclick="toggleEditForm(<?= $comment->getId() ?>)" title="<?= $package->i18n('issue_tracker_edit') ?>">
                                        <i class="rex-icon fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($currentUser->isAdmin()): ?>
                                    <form method="post" style="display: inline-block; margin: 0;" onsubmit="return confirm('<?= $package->i18n('issue_tracker_delete_comment_confirm') ?>');">
                                        <input type="hidden" name="delete_comment" value="<?= $comment->getId() ?>" />
                                        <button type="submit" class="btn btn-xs btn-danger" title="<?= $package->i18n('issue_tracker_delete') ?>">
                                            <i class="rex-icon fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </h5>
                            
                            <!-- Edit-Formular (versteckt) -->
                            <div id="edit-form-<?= $comment->getId() ?>" style="display: none; margin-bottom: 15px; padding: 15px; background: #fffbcc; border-radius: 4px; border: 1px solid #e5d700;">
                                <form method="post">
                                    <input type="hidden" name="edit_comment" value="<?= $comment->getId() ?>" />
                                    <div class="form-group">
                                        <textarea name="comment_text" class="form-control" rows="4"><?= rex_escape($comment->getComment()) ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-default" onclick="toggleEditForm(<?= $comment->getId() ?>)">
                                        <?= $package->i18n('issue_tracker_cancel') ?>
                                    </button>
                                </form>
                            </div>
                            
                            <div class="issue-tracker-comment-content" id="comment-content-<?= $comment->getId() ?>"><?= rex_markdown::factory()->parse($comment->getComment()) ?></div>
                        
                            <?php if (!empty($commentAttachments)): ?>
                            <div class="row" style="margin-top: 10px;">
                                <?php foreach ($commentAttachments as $attachment): ?>
                                <div class="col-sm-6 col-md-3" style="margin-bottom: 10px;">
                                    <div class="thumbnail" style="margin-bottom: 0;">
                                        <?php if ($attachment->isImage()): ?>
                                            <a href="<?= $attachment->getUrl() ?>" class="issue-attachment-lightbox" data-type="image" title="<?= rex_escape($attachment->getOriginalFilename()) ?>">
                                                <img src="<?= $attachment->getThumbnailUrl() ?>" alt="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="max-height: 100px; width: auto;">
                                            </a>
                                        <?php elseif ($attachment->isVideo()): ?>
                                            <a href="<?= $attachment->getUrl() ?>" class="issue-attachment-lightbox" data-type="video" title="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="text-decoration: none; display: block;">
                                                <div style="padding: 20px; text-align: center; background: #f5f5f5; position: relative;">
                                                    <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 32px; color: #999;"></i>
                                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-shadow: 0 0 5px rgba(0,0,0,0.5);">
                                                        <i class="rex-icon fa-play-circle" style="font-size: 24px;"></i>
                                                    </div>
                                                </div>
                                            </a   <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 32px; color: #999;"></i>
                                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-shadow: 0 0 5px rgba(0,0,0,0.5);">
                                                        <i class="rex-icon fa-play-circle" style="font-size: 24px;"></i>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php else: ?>
                                            <div style="padding: 20px; text-align: center; background: #f5f5f5;">
                                                <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 32px; color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="caption">
                                            <p style="margin: 0; font-size: 12px; word-break: break-all;">
                                                <a href="<?= $attachment->getUrl() ?>" target="_blank">
                                                    <?= rex_escape($attachment->getOriginalFilename()) ?>
                                                </a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Alle Kommentare in chronologischer Reihenfolge -->
                <h5 style="margin-bottom: 15px; color: #666;">
                    <i class="rex-icon fa-comments"></i> <?= $package->i18n('issue_tracker_all_comments') ?>
                </h5>
                <?php 
                // Nur Top-Level Kommentare (ohne parent_comment_id)
                $topLevelComments = array_filter($comments, function($c) { return $c->getParentCommentId() === null; });
                
                foreach ($topLevelComments as $comment): 
                    $commentUser = $comment->getCreator();
                    $commentAttachments = \FriendsOfREDAXO\IssueTracker\Attachment::getByComment($comment->getId());
                    $replies = $comment->getReplies();
                ?>
                <div class="issue-tracker-comment-card <?= $comment->isSolution() ? 'border-success' : ($comment->isPinned() ? 'border-info' : '') ?>" id="comment-<?= $comment->getId() ?>" style="<?= $comment->isSolution() ? 'border-left: 4px solid #5cb85c;' : ($comment->isPinned() ? 'border-left: 4px solid #5bc0de;' : '') ?>">
                    <div class="media-body">
                        <h5 class="media-heading">
                            <?php if ($comment->isSolution()): ?>
                                <span class="label label-success" style="margin-right: 5px;">
                                    <i class="rex-icon fa-check-circle"></i> <?= $package->i18n('issue_tracker_solution') ?>
                                </span>
                            <?php elseif ($comment->isPinned()): ?>
                                <span class="label label-info" style="margin-right: 5px;">
                                    <i class="rex-icon fa-thumb-tack"></i> <?= $package->i18n('issue_tracker_pinned') ?>
                                </span>
                            <?php endif; ?>
                            <strong><?= $commentUser ? rex_escape($commentUser->getValue('name')) : 'Unknown' ?></strong>
                            <small class="text-muted"> - <?= $comment->getCreatedAt() ? $comment->getCreatedAt()->format('d.m.Y H:i') : '-' ?></small>
                            <?php if ($comment->getUpdatedAt()): ?>
                                <small class="text-muted" style="font-style: italic;"> (<?= $package->i18n('issue_tracker_edited') ?> <?= $comment->getUpdatedAt()->format('d.m.Y H:i') ?>)</small>
                            <?php endif; ?>
                            <?php 
                            $currentUser = rex::getUser();
                            $canModerate = $currentUser->isAdmin() || $issue->getCreatedBy() === $currentUser->getId();
                            $canEdit = $currentUser->isAdmin() || $comment->getCreatedBy() === $currentUser->getId();
                            if ($canModerate || $canEdit): 
                            ?>
                            <div class="pull-right">
                                <?php if ($canModerate): ?>
                                <form method="post" style="display: inline-block; margin: 0;">
                                    <input type="hidden" name="toggle_pin" value="<?= $comment->getId() ?>" />
                                    <button type="submit" class="btn btn-xs <?= $comment->isPinned() ? 'btn-info' : 'btn-default' ?>" 
                                            title="<?= $comment->isPinned() ? 'Pin entfernen' : 'Kommentar pinnen' ?>">
                                        <i class="rex-icon fa-thumb-tack"></i>
                                    </button>
                                </form>
                                <form method="post" style="display: inline-block; margin: 0;">
                                    <input type="hidden" name="toggle_solution" value="<?= $comment->getId() ?>" />
                                    <button type="submit" class="btn btn-xs <?= $comment->isSolution() ? 'btn-success' : 'btn-default' ?>" 
                                            title="<?= $comment->isSolution() ? 'Lösung entfernen' : 'Als Lösung markieren' ?>">
                                        <i class="rex-icon fa-check-circle"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($canEdit): ?>
                                <button type="button" class="btn btn-xs btn-default" onclick="toggleEditForm(<?= $comment->getId() ?>)" title="<?= $package->i18n('issue_tracker_edit') ?>">
                                    <i class="rex-icon fa-edit"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($currentUser->isAdmin()): ?>
                                <form method="post" style="display: inline-block; margin: 0;" onsubmit="return confirm('<?= $package->i18n('issue_tracker_delete_comment_confirm') ?>');">
                                    <input type="hidden" name="delete_comment" value="<?= $comment->getId() ?>" />
                                    <button type="submit" class="btn btn-xs btn-danger" title="<?= $package->i18n('issue_tracker_delete') ?>">
                                        <i class="rex-icon fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </h5>
                        
                        <!-- Edit-Formular (versteckt) -->
                        <div id="edit-form-<?= $comment->getId() ?>" style="display: none; margin-bottom: 15px; padding: 15px; background: #fffbcc; border-radius: 4px; border: 1px solid #e5d700;">
                            <form method="post">
                                <input type="hidden" name="edit_comment" value="<?= $comment->getId() ?>" />
                                <div class="form-group">
                                    <textarea name="comment_text" class="form-control" rows="4"><?= rex_escape($comment->getComment()) ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-default" onclick="toggleEditForm(<?= $comment->getId() ?>)">
                                    <?= $package->i18n('issue_tracker_cancel') ?>
                                </button>
                            </form>
                        </div>
                        
                        <div class="issue-tracker-comment-content" id="comment-content-<?= $comment->getId() ?>"><?= rex_markdown::factory()->parse($comment->getComment()) ?></div>
                        
                        <?php if (!empty($commentAttachments)): ?>
                        <div class="row" style="margin-top: 10px;">
                            <?php foreach ($commentAttachments as $attachment): ?>
                            <div class="col-sm-6 col-md-3" style="margin-bottom: 10px;">
                                <div class="thumbnail" style="margin-bottom: 0;">
                                    <?php if ($attachment->isImage()): ?>
                                        <a href="<?= $attachment->getUrl() ?>" class="issue-attachment-lightbox" data-type="image" title="<?= rex_escape($attachment->getOriginalFilename()) ?>">
                                            <img src="<?= $attachment->getThumbnailUrl() ?>" alt="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="max-height: 100px; width: auto;">
                                        </a>
                                    <?php elseif ($attachment->isVideo()): ?>
                                        <a href="<?= $attachment->getUrl() ?>" class="issue-attachment-lightbox" data-type="video" title="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="text-decoration: none; display: block;">
                                            <div style="padding: 20px; text-align: center; background: #f5f5f5; position: relative;">
                                                <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 32px; color: #999;"></i>
                                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-shadow: 0 0 5px rgba(0,0,0,0.5);">
                                                    <i class="rex-icon fa-play-circle" style="font-size: 24px;"></i>
                                                </div>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <div style="padding: 20px; text-align: center; background: #f5f5f5;">
                                            <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 32px; color: #999;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="caption">
                                        <p style="font-size: 11px; margin: 5px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                           title="<?= rex_escape($attachment->getOriginalFilename()) ?>">
                                            <?= rex_escape($attachment->getOriginalFilename()) ?>
                                        </p>
                                        <a href="<?= $attachment->getUrl() ?>" class="btn btn-xs btn-default btn-block" target="_blank">
                                            <i class="rex-icon fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Antworten Button -->
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                            <button type="button" class="btn btn-xs btn-default" onclick="toggleReplyForm(<?= $comment->getId() ?>)">
                                <i class="rex-icon fa-reply"></i> <?= $package->i18n('issue_tracker_reply') ?>
                            </button>
                            <?php if (!empty($replies)): ?>
                                <small class="text-muted">(<?= count($replies) ?> <?= count($replies) === 1 ? 'Antwort' : 'Antworten' ?>)</small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Antwort-Formular (versteckt) -->
                        <div id="reply-form-<?= $comment->getId() ?>" class="issue-tracker-reply-form" style="display: none; margin-top: 15px; padding: 15px; border-radius: 4px;">
                            <form method="post">
                                <input type="hidden" name="add_comment" value="1" />
                                <input type="hidden" name="parent_comment_id" value="<?= $comment->getId() ?>" />
                                <div class="form-group">
                                    <textarea name="comment" class="form-control" rows="3" placeholder="<?= $package->i18n('issue_tracker_reply_placeholder') ?>"></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="rex-icon fa-reply"></i> <?= $package->i18n('issue_tracker_send_reply') ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-default" onclick="toggleReplyForm(<?= $comment->getId() ?>)">
                                    <?= $package->i18n('issue_tracker_cancel') ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Antworten anzeigen -->
                        <?php if (!empty($replies)): ?>
                        <div class="issue-tracker-replies-container" style="margin-top: 15px; margin-left: 30px; padding-left: 15px;">
                            <?php foreach ($replies as $reply):
                                $replyUser = $reply->getCreator();
                                $replyAttachments = \FriendsOfREDAXO\IssueTracker\Attachment::getByComment($reply->getId());
                            ?>
                            <div class="issue-tracker-reply-comment" style="margin-bottom: 15px; padding: 10px; border-radius: 4px;" id="comment-<?= $reply->getId() ?>">
                                <div>
                                    <strong><?= $replyUser ? rex_escape($replyUser->getValue('name')) : 'Unknown' ?></strong>
                                    <small class="text-muted"> - <?= $reply->getCreatedAt() ? $reply->getCreatedAt()->format('d.m.Y H:i') : '-' ?></small>
                                </div>
                                <div style="margin-top: 8px;"><?= rex_markdown::factory()->parse($reply->getComment()) ?></div>
                                
                                <?php if (!empty($replyAttachments)): ?>
                                <div class="row" style="margin-top: 10px;">
                                    <?php foreach ($replyAttachments as $attachment): ?>
                                    <div class="col-sm-6 col-md-3" style="margin-bottom: 10px;">
                                        <div class="thumbnail" style="margin-bottom: 0;">
                                            <?php if ($attachment->isImage()): ?>
                                                <a href="<?= $attachment->getUrl() ?>" class="issue-attachment-lightbox" data-type="image" title="<?= rex_escape($attachment->getOriginalFilename()) ?>">
                                                    <img src="<?= $attachment->getThumbnailUrl() ?>" alt="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="max-height: 100px; width: auto;">
                                                </a>
                                            <?php elseif ($attachment->isVideo()): ?>
                                                <a href="<?= $attachment->getUrl() ?>" class="issue-attachment-lightbox" data-type="video" title="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="text-decoration: none; display: block;">
                                                    <div style="padding: 20px; text-align: center; background: #f5f5f5; position: relative;">
                                                        <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 32px; color: #999;"></i>
                                                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-shadow: 0 0 5px rgba(0,0,0,0.5);">
                                                            <i class="rex-icon fa-play-circle" style="font-size: 24px;"></i>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php else: ?>
                                                <div style="padding: 20px; text-align: center; background: #f5f5f5;">
                                                    <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 32px; color: #999;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="caption">
                                                <p style="font-size: 11px; margin: 5px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                                   title="<?= rex_escape($attachment->getOriginalFilename()) ?>">
                                                    <?= rex_escape($attachment->getOriginalFilename()) ?>
                                                </p>
                                                <a href="<?= $attachment->getUrl() ?>" class="btn btn-xs btn-default btn-block" target="_blank">
                                                    <i class="rex-icon fa-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Neuer Kommentar -->
            <div style="margin-top: 30px; border-top: 2px solid #eee; padding-top: 20px;">
                <h5><?= $package->i18n('issue_tracker_add_comment') ?></h5>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="add_comment" value="1" />
                    <div class="form-group">
                        <textarea name="comment" id="new-comment-text" class="form-control" rows="4"
                                  placeholder="<?= $package->i18n('issue_tracker_comment_placeholder') ?>"></textarea>
                    </div>
                    <div class="form-group">
                        <label><?= $package->i18n('issue_tracker_attachments') ?></label>
                        <input type="file" name="comment_attachments[]" multiple 
                               accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" 
                               class="form-control">
                        <p class="help-block"><?= $package->i18n('issue_tracker_attachments_help') ?></p>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="rex-icon fa-comment"></i> <?= $package->i18n('issue_tracker_add_comment') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Aktivitätsverlauf -->
<?php 
$history = $this->getVar('history', []);
$canViewHistory = \FriendsOfREDAXO\IssueTracker\PermissionService::canViewHistory();
if (!empty($history) && $canViewHistory): 
?>
<div class="modal fade" id="history-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">
                    <i class="rex-icon fa-history"></i> <?= $package->i18n('issue_tracker_history') ?>
                    <small class="text-muted">(<?= count($history) ?> <?= count($history) === 1 ? 'Eintrag' : 'Einträge' ?>)</small>
                </h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 180px;">Zeitpunkt</th>
                                <th style="width: 150px;">Benutzer</th>
                                <th>Änderung</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $entry): ?>
                            <tr>
                                <td class="text-muted" style="font-size: 13px;">
                                    <i class="rex-icon fa-clock-o"></i> <?= $entry['created_at']->format('d.m.Y H:i:s') ?>
                                </td>
                                <td>
                                    <strong><?= $entry['user'] ? rex_escape($entry['user']->getValue('name')) : 'System' ?></strong>
                                </td>
                                <td>
                                    <?= \FriendsOfREDAXO\IssueTracker\HistoryService::formatEntry($entry) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_close') ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
