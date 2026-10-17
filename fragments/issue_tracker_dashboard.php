<?php
/**
 * Dashboard Fragment – Übersicht im Stil der macOS-Systemeinstellungen
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');

$openIssues = (int) $this->getVar('openIssues', 0);
$inProgressIssues = (int) $this->getVar('inProgressIssues', 0);
$overdueIssues = (int) $this->getVar('overdueIssues', 0);
$closedIssues = (int) $this->getVar('closedIssues', 0);
$priorityCounts = $this->getVar('priorityCounts', []);
$recentIssues = $this->getVar('recentIssues', []);
$recentActivities = $this->getVar('recentActivities', []);
$unreadMessages = (int) $this->getVar('unreadMessages', 0);
$recentMessages = $this->getVar('recentMessages', []);
$userProjects = $this->getVar('userProjects', []);
$watchedIssues = $this->getVar('watchedIssues', []);
$recentMentions = $this->getVar('recentMentions', []);
$unreadMentionsCount = (int) $this->getVar('unreadMentionsCount', 0);
$isManager = $this->getVar('isManager', false);
$currentViewType = $this->getVar('currentViewType', 'own');

$currentUser = rex::getUser();
$issueUrl = static fn (int $id): string => rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $id]);
$listUrl = static fn (array $params = []): string => rex_url::backendPage('issue_tracker/issues/list', $params);

// Begrüßung und Statuszeile
$hour = (int) date('G');
$greetingKey = $hour < 11 ? 'issue_tracker_ui_greeting_morning' : ($hour < 18 ? 'issue_tracker_ui_greeting_day' : 'issue_tracker_ui_greeting_evening');
$displayName = trim((string) ($currentUser->getValue('name') ?: $currentUser->getLogin()));
$active = $openIssues + $inProgressIssues;
if ($overdueIssues > 0) {
    $statusLine = $package->i18n('issue_tracker_ui_status_overdue', (string) $active, (string) $overdueIssues);
    $heroIcon = '<span class="it-icon it-icon--lg it-c-orange" aria-hidden="true"><i class="rex-icon fa-exclamation"></i></span>';
} elseif ($active > 0) {
    $statusLine = $package->i18n('issue_tracker_ui_status_active', (string) $active);
    $heroIcon = '<span class="it-icon it-icon--lg it-c-blue" aria-hidden="true"><i class="rex-icon fa-tasks"></i></span>';
} else {
    $statusLine = $package->i18n('issue_tracker_ui_status_clear');
    $heroIcon = '<span class="it-icon it-icon--lg it-c-green" aria-hidden="true"><i class="rex-icon fa-check"></i></span>';
}

// Kachel (ganze Kachel ist ein Link)
$tile = static function (string $title, string $fa, string $color, int $value, string $url, string $extraClass = ''): string {
    return '<a class="it-tile' . ('' !== $extraClass ? ' ' . $extraClass : '') . '" href="' . $url . '">'
        . '<div class="it-tile-head"><span class="it-icon it-c-' . $color . '" aria-hidden="true"><i class="rex-icon ' . $fa . '"></i></span><h3>' . rex_escape($title) . '</h3></div>'
        . '<div class="it-tile-value">' . $value . '</div></a>';
};

// Prioritäten (nicht geschlossen) als einfache Balken
$priorityOrder = ['critical', 'urgent', 'high', 'normal', 'low'];
$maxPriority = $priorityCounts ? max($priorityCounts) : 0;
?>

<div class="issue-tracker-dashboard">
    <div class="it-hero">
        <div class="it-hero-main">
            <?= $heroIcon ?>
            <div>
                <h2><?= $package->i18n($greetingKey, $displayName) ?></h2>
                <p><?= $statusLine ?></p>
            </div>
        </div>
        <div class="it-hero-actions">
            <?php if ($isManager): ?>
            <nav class="it-segmented" aria-label="<?= $package->i18n('issue_tracker_filter_by_view') ?>">
                <a href="<?= rex_url::backendPage('issue_tracker/dashboard', ['view' => 'own']) ?>"<?= $currentViewType === 'own' ? ' class="active" aria-current="page"' : '' ?>><?= $package->i18n('issue_tracker_filter_personal') ?></a>
                <a href="<?= rex_url::backendPage('issue_tracker/dashboard', ['view' => 'all']) ?>"<?= $currentViewType === 'all' ? ' class="active" aria-current="page"' : '' ?>><?= $package->i18n('issue_tracker_filter_all') ?></a>
            </nav>
            <?php endif; ?>
            <a href="<?= rex_url::backendPage('issue_tracker/issues/create') ?>" class="btn btn-primary it-btn-primary">
                <i class="rex-icon fa-plus" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_create_new') ?>
            </a>
        </div>
    </div>

    <div class="it-tiles">
        <?= $tile($package->i18n('issue_tracker_status_open'), 'fa-circle-o', 'blue', $openIssues, $listUrl(['filter_status' => 'open'])) ?>
        <?= $tile($package->i18n('issue_tracker_status_in_progress'), 'fa-spinner', 'orange', $inProgressIssues, $listUrl(['filter_status' => 'in_progress'])) ?>
        <?= $tile($package->i18n('issue_tracker_status_overdue'), 'fa-clock-o', 'red', $overdueIssues, $listUrl(), $overdueIssues > 0 ? 'it-tile--alert' : '') ?>
        <?= $tile($package->i18n('issue_tracker_closed_30_days_short'), 'fa-check', 'green', $closedIssues, $listUrl(['filter_status' => 'closed'])) ?>
        <?= $tile($package->i18n('issue_tracker_projects'), 'fa-folder-open', 'indigo', count($userProjects), rex_url::backendPage('issue_tracker/projects/list')) ?>
        <?= $tile($package->i18n('issue_tracker_ui_unread_messages'), 'fa-envelope', 'purple', $unreadMessages, rex_url::backendPage('issue_tracker/messages/inbox')) ?>
    </div>

    <div class="it-cols">
        <div>
            <?php if (!empty($recentIssues)): ?>
            <h3 class="it-group-title"><?= $currentViewType === 'all' ? $package->i18n('issue_tracker_all_recent_issues') : $package->i18n('issue_tracker_my_recent_issues') ?></h3>
            <div class="it-group">
                <ul class="it-list">
                <?php foreach ($recentIssues as $issue):
                    if (!$issue) {
                        continue;
                    }
                    $creatorName = UiHelper::userName($issue->getCreator(), '?');
                    $project = $issue->getProjectId() ? $issue->getProject() : null;
                ?>
                    <li>
                        <a class="it-item" href="<?= $issueUrl($issue->getId()) ?>">
                            <?= UiHelper::avatar($issue->getCreatedBy(), $creatorName) ?>
                            <div class="it-item-body">
                                <div class="it-item-title">
                                    <strong><?= rex_escape($issue->getTitle()) ?></strong>
                                    <span class="it-item-date"><?= rex_escape(UiHelper::relDate($issue->getCreatedAt())) ?></span>
                                </div>
                                <div class="it-item-sub">
                                    <span class="it-item-id">#<?= $issue->getId() ?></span>
                                    <span><?= rex_escape($creatorName) ?></span>
                                    <?php if ($project): ?>
                                    <span><span class="it-dot" style="<?= UiHelper::colorVar($project->getColor()) ?>"></span> <?= rex_escape($project->getName()) ?></span>
                                    <?php endif; ?>
                                    <?= UiHelper::statusBadge($issue->getStatus()) ?>
                                    <?php if (in_array($issue->getPriority(), ['critical', 'urgent', 'high'], true)): ?>
                                    <?= UiHelper::priorityBadge($issue->getPriority()) ?>
                                    <?php endif; ?>
                                    <?php if ($issue->isOverdue()): ?>
                                    <?= UiHelper::badge(rex_i18n::rawMsg('issue_tracker_overdue'), 'danger') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($recentActivities)): ?>
            <h3 class="it-group-title"><?= $currentViewType === 'all' ? $package->i18n('issue_tracker_all_recent_activities') : $package->i18n('issue_tracker_my_recent_activities') ?></h3>
            <div class="it-group">
                <ul class="it-list">
                <?php foreach ($recentActivities as $activity):
                    $userName = UiHelper::userName(rex_user::get($activity['user_id']), $package->i18n('issue_tracker_ui_unknown_user'));
                    $entryText = trim(strip_tags(\FriendsOfREDAXO\IssueTracker\HistoryService::formatEntry($activity, $package)));
                ?>
                    <li>
                        <a class="it-item" href="<?= $issueUrl($activity['issue_id']) ?>">
                            <?= UiHelper::avatar($activity['user_id'], $userName) ?>
                            <div class="it-item-body">
                                <div class="it-item-title">
                                    <strong><?= rex_escape($activity['issue_title']) ?></strong>
                                    <span class="it-item-date"><?= rex_escape(UiHelper::relDate($activity['created_at'])) ?></span>
                                </div>
                                <div class="it-item-sub">
                                    <span class="it-item-id">#<?= (int) $activity['issue_id'] ?></span>
                                    <span><?= rex_escape($userName) ?>: <?= rex_escape($entryText) ?></span>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (empty($recentIssues) && empty($recentActivities)): ?>
            <div class="it-group"><div class="it-empty"><i class="rex-icon fa-inbox" aria-hidden="true"></i><?= $package->i18n('issue_tracker_no_issues') ?></div></div>
            <?php endif; ?>
        </div>

        <div>
            <?php if (!empty($recentMentions)): ?>
            <div class="it-group-title-row">
                <h3 class="it-group-title"><?= $package->i18n('issue_tracker_my_mentions') ?><?php if ($unreadMentionsCount > 0): ?> <span class="it-count">(<?= $unreadMentionsCount ?>)</span><?php endif; ?></h3>
                <?php if ($unreadMentionsCount > 0): ?>
                <form method="post" action="<?= rex_url::backendPage('issue_tracker/dashboard', [], false) ?>">
                    <input type="hidden" name="mark_all_mentions_read" value="1">
                    <button type="submit" class="btn btn-xs btn-default"><?= $package->i18n('issue_tracker_mark_all_read') ?></button>
                </form>
                <?php endif; ?>
            </div>
            <div class="it-group">
                <?php foreach ($recentMentions as $mention):
                    $isUnread = $mention['read_at'] === null;
                    $mentioner = (string) ($mention['mentioner_name'] ?: $mention['mentioner_login']);
                ?>
                <div class="it-item<?= $isUnread ? ' it-item--unread' : '' ?>">
                    <span class="it-icon it-c-blue" aria-hidden="true"><i class="rex-icon fa-at"></i></span>
                    <div class="it-item-body">
                        <div class="it-item-title">
                            <strong><a href="<?= $issueUrl((int) $mention['issue_id']) . ($mention['comment_id'] ? '#comment-' . (int) $mention['comment_id'] : '') ?>"><?= rex_escape($mention['issue_title']) ?></a></strong>
                        </div>
                        <div class="it-item-sub">
                            <span><?= rex_escape($mentioner) ?> <?= $package->i18n('issue_tracker_mentioned_you_in') ?> #<?= (int) $mention['issue_id'] ?></span>
                            <span class="it-item-date"><?= rex_escape(UiHelper::relDate(new DateTime((string) $mention['created_at']))) ?></span>
                        </div>
                    </div>
                    <?php if ($isUnread): ?>
                    <div class="it-item-side">
                        <form method="post" action="<?= rex_url::backendPage('issue_tracker/dashboard', [], false) ?>">
                            <input type="hidden" name="mark_mention_read" value="<?= (int) $mention['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-default" title="<?= $package->i18n('issue_tracker_mark_read') ?>" aria-label="<?= $package->i18n('issue_tracker_mark_read') ?>">
                                <i class="rex-icon fa-check" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="it-group-title-row">
                <h3 class="it-group-title"><?= $package->i18n('issue_tracker_messages') ?><?php if ($unreadMessages > 0): ?> <span class="it-count">(<?= $unreadMessages ?>)</span><?php endif; ?></h3>
                <a href="<?= rex_url::backendPage('issue_tracker/messages/compose') ?>" class="btn btn-xs btn-default"><i class="rex-icon fa-pencil-square-o" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_compose') ?></a>
            </div>
            <div class="it-group">
                <?php if (!empty($recentMessages)): ?>
                <ul class="it-list">
                    <?php foreach (array_slice($recentMessages, 0, 5) as $msg): ?>
                    <li>
                        <a class="it-item<?= !$msg->isRead() ? ' it-item--unread' : '' ?>" href="<?= rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $msg->getId()]) ?>">
                            <?= UiHelper::avatar($msg->getSenderId(), $msg->getSenderName(), true) ?>
                            <div class="it-item-body">
                                <div class="it-item-title">
                                    <strong><?= rex_escape($msg->getSenderName()) ?></strong>
                                    <span class="it-item-date"><?= rex_escape(UiHelper::relDate($msg->getCreatedAt())) ?></span>
                                </div>
                                <div class="it-item-sub"><span><?= rex_escape($msg->getSubject()) ?></span></div>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="it-group-foot">
                    <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>"><?= $package->i18n('issue_tracker_inbox') ?></a>
                </div>
                <?php else: ?>
                <div class="it-empty"><i class="rex-icon fa-envelope-o" aria-hidden="true"></i><?= $package->i18n('issue_tracker_no_messages_inbox') ?></div>
                <?php endif; ?>
            </div>

            <?php if ($maxPriority > 0): ?>
            <h3 class="it-group-title"><?= $package->i18n('issue_tracker_ui_by_priority') ?></h3>
            <div class="it-group">
                <div class="it-bars">
                    <?php foreach ($priorityOrder as $prio):
                        if (!isset($priorityCounts[$prio])) {
                            continue;
                        }
                        $variant = UiHelper::priorityVariant($prio);
                        $pct = (int) round($priorityCounts[$prio] / $maxPriority * 100);
                    ?>
                    <div class="it-bar<?= 'muted' !== $variant ? ' it-bar--' . $variant : '' ?>">
                        <span><?= $package->i18n('issue_tracker_priority_' . $prio) ?></span>
                        <span class="it-bar-track" aria-hidden="true"><span style="--p:<?= $pct ?>%"></span></span>
                        <span class="it-bar-count"><?= (int) $priorityCounts[$prio] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($userProjects)): ?>
            <div class="it-group-title-row">
                <h3 class="it-group-title"><?= $package->i18n('issue_tracker_projects') ?></h3>
                <a href="<?= rex_url::backendPage('issue_tracker/projects') ?>" class="btn btn-xs btn-default"><?= $package->i18n('issue_tracker_show_all') ?></a>
            </div>
            <div class="it-group">
                <ul class="it-list">
                <?php foreach (array_slice($userProjects, 0, 5) as $project):
                    $stats = $project->getStats();
                ?>
                    <li>
                        <a class="it-item" href="<?= rex_url::backendPage('issue_tracker/projects/view', ['project_id' => $project->getId()]) ?>">
                            <span class="it-dot" style="<?= UiHelper::colorVar($project->getColor()) ?>"></span>
                            <div class="it-item-body">
                                <div class="it-item-title">
                                    <strong><?= rex_escape($project->getName()) ?></strong>
                                    <span class="it-item-date"><?= (int) $stats['progress'] ?> %</span>
                                </div>
                                <div class="it-progress" aria-hidden="true"><span style="--p:<?= (int) $stats['progress'] ?>%"></span></div>
                                <div class="it-item-sub">
                                    <span><?= $package->i18n('issue_tracker_ui_open_closed', (string) $stats['open'], (string) $stats['closed']) ?></span>
                                    <?php if ($project->isOverdue()): ?><?= UiHelper::badge(rex_i18n::rawMsg('issue_tracker_overdue'), 'danger') ?><?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
                <?php if (count($userProjects) > 5): ?>
                <div class="it-group-foot"><a href="<?= rex_url::backendPage('issue_tracker/projects') ?>"><?= $package->i18n('issue_tracker_ui_more_projects', (string) (count($userProjects) - 5)) ?></a></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($watchedIssues)): ?>
            <h3 class="it-group-title"><?= $package->i18n('issue_tracker_watched_issues') ?> <span class="it-count">(<?= count($watchedIssues) ?>)</span></h3>
            <div class="it-group">
                <ul class="it-list">
                <?php foreach (array_slice($watchedIssues, 0, 10) as $wIssue): ?>
                    <li>
                        <a class="it-item" href="<?= $issueUrl($wIssue->getId()) ?>">
                            <span class="it-icon it-c-gray" aria-hidden="true"><i class="rex-icon fa-eye"></i></span>
                            <div class="it-item-body">
                                <div class="it-item-title">
                                    <strong><?= rex_escape($wIssue->getTitle()) ?></strong>
                                </div>
                                <div class="it-item-sub">
                                    <span class="it-item-id">#<?= $wIssue->getId() ?></span>
                                    <?= UiHelper::statusBadge($wIssue->getStatus()) ?>
                                    <?php if ($wIssue->isOverdue()): ?><?= UiHelper::badge(rex_i18n::rawMsg('issue_tracker_overdue'), 'danger') ?><?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
                <?php if (count($watchedIssues) > 10): ?>
                <div class="it-group-foot"><span class="it-toolbar-count">+ <?= count($watchedIssues) - 10 ?> <?= $package->i18n('issue_tracker_more_watched') ?></span></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
