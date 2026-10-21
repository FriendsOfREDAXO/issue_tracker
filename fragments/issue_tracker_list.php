<?php
/**
 * Themenliste: Postfach (Zeilen wie Mail) oder klassische Tabelle
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\PermissionService;
use FriendsOfREDAXO\IssueTracker\Project;
use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');

$issues = $this->getVar('issues', []);
$totalIssues = (int) $this->getVar('totalIssues', count($issues));
$showCount = (int) $this->getVar('showCount', 50);
$pageSize = (int) $this->getVar('pageSize', 50);
$sortColumn = $this->getVar('sortColumn', 'created_at');
$sortOrder = $this->getVar('sortOrder', 'DESC');
$watchedIds = $this->getVar('watchedIds', []);
$unreadMentionIds = $this->getVar('unreadMentionIds', []);
$selectedIssueId = (int) $this->getVar('selectedIssueId', 0);
$viewMode = $this->getVar('viewMode', 'mail');
$state = $this->getVar('filterState', []);
$statuses = $this->getVar('statuses', []);
$categories = $this->getVar('categories', []);
$allTags = $this->getVar('allTags', []);
$users = $this->getVar('users', []);
$userId = PermissionService::getUserId();
$mode = (string) $this->getVar('mode', 'issues');
$isKnowledge = $mode === 'knowledge';
$isProject = $mode === 'project';

// Titel des aktiven Postfachs
$mailboxTitle = $package->i18n('issue_tracker_all_active');
if ((int) ($state['filter_mentioned'] ?? 0) === 1) {
    $mailboxTitle = $package->i18n('issue_tracker_mail_mentioned');
} elseif ((int) ($state['filter_watched'] ?? 0) === 1) {
    $mailboxTitle = $package->i18n('issue_tracker_mail_watched');
} elseif ((int) ($state['filter_assigned'] ?? 0) === $userId && $userId > 0) {
    $mailboxTitle = $package->i18n('issue_tracker_mail_assigned_to_me');
} elseif ((int) ($state['filter_created_by'] ?? 0) === $userId && $userId > 0) {
    $mailboxTitle = $package->i18n('issue_tracker_mail_created_by_me');
} elseif ((int) ($state['filter_recent_closed'] ?? 0) === 1) {
    $mailboxTitle = $package->i18n('issue_tracker_mail_recent_closed');
} elseif ((int) ($state['filter_overdue'] ?? 0) === 1) {
    $mailboxTitle = $package->i18n('issue_tracker_overdue');
} elseif ((int) ($state['filter_project'] ?? 0) > 0) {
    $p = Project::get((int) $state['filter_project']);
    $mailboxTitle = $p ? $p->getName() : $mailboxTitle;
} elseif ($isKnowledge) {
    $mailboxTitle = $package->i18n('issue_tracker_knowledge') . (($state['filter_category'] ?? '') !== '' ? ' · ' . $state['filter_category'] : '');
} elseif ((int) ($state['filter_tag'] ?? 0) > 0) {
    foreach ($allTags as $tag) {
        if ($tag->getId() === (int) $state['filter_tag']) {
            $mailboxTitle = $tag->getName();
        }
    }
} elseif (($state['filter_category'] ?? '') !== '') {
    $mailboxTitle = (string) $state['filter_category'];
} elseif (($state['filter_status'] ?? '') === '_all_') {
    $mailboxTitle = $package->i18n('issue_tracker_all_issues');
} elseif (($state['filter_status'] ?? '') !== '') {
    $mailboxTitle = $package->i18n('issue_tracker_status_' . $state['filter_status']);
}

// Kommentar- und Anhangszahlen in einer Abfrage
$commentCounts = [];
$attachmentCounts = [];
$ids = array_map(static fn ($i) => (int) $i->getId(), $issues);
if ($ids !== []) {
    $in = implode(',', $ids);
    foreach (rex_sql::factory()->getArray('SELECT issue_id, COUNT(*) c FROM ' . rex::getTable('issue_tracker_comments') . " WHERE issue_id IN ($in) GROUP BY issue_id") as $r) {
        $commentCounts[(int) $r['issue_id']] = (int) $r['c'];
    }
    foreach (rex_sql::factory()->getArray('SELECT issue_id, COUNT(*) c FROM ' . rex::getTable('issue_tracker_attachments') . " WHERE issue_id IN ($in) GROUP BY issue_id") as $r) {
        $attachmentCounts[(int) $r['issue_id']] = (int) $r['c'];
    }
}

$listUrl = static fn (array $params = []): string => rex_url::backendPage('issue_tracker/issues/list', $params);
// Sortierung und Umfang beim Auswählen eines Themas beibehalten
$carry = [];
if ($sortColumn !== 'created_at' || $sortOrder !== 'DESC') {
    $carry = ['sort' => $sortColumn, 'order' => strtolower($sortOrder)];
}
if ($showCount > $pageSize) {
    $carry['show'] = $showCount;
}

// Klartext-Vorschau aus Markdown
$preview = static function (string $md): string {
    $txt = preg_replace('/```.*?```/s', ' ', $md) ?? '';
    $txt = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $txt) ?? '';
    $txt = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $txt) ?? '';
    $txt = preg_replace('/^\s{0,3}(#{1,6}|>|[-*+]\s+\[[ xX]\]|[-*+]|\d+\.)\s*/m', '', $txt) ?? '';
    $txt = str_replace(['**', '__', '`', '~~'], '', $txt);
    $txt = trim((string) preg_replace('/\s+/u', ' ', strip_tags($txt)));
    return mb_strlen($txt) > 220 ? mb_substr($txt, 0, 220) . '…' : $txt;
};

$colorVar = static fn (string $c): string => preg_match('/^#[0-9a-f]{3,8}$/i', $c) ? '--c:' . $c : '--c:#7c7c82';

// Sortier-Links (Tabelle)
$sortUrl = static function (string $column) use ($sortColumn, $sortOrder, $listUrl): string {
    $newOrder = ($column === $sortColumn && $sortOrder === 'ASC') ? 'desc' : 'asc';
    return $listUrl(['sort' => $column, 'order' => $newOrder]);
};
$sortIcon = static function (string $column) use ($sortColumn, $sortOrder): string {
    if ($column !== $sortColumn) {
        return '<i class="rex-icon fa-sort it-sort-idle" aria-hidden="true"></i>';
    }
    return $sortOrder === 'ASC' ? '<i class="rex-icon fa-sort-asc" aria-hidden="true"></i>' : '<i class="rex-icon fa-sort-desc" aria-hidden="true"></i>';
};

$sortOptions = $isKnowledge ? [
    'title' => $package->i18n('issue_tracker_title'),
    'updated_at' => $package->i18n('issue_tracker_updated_at'),
] : [
    'created_at' => $package->i18n('issue_tracker_mail_sort_date'),
    'priority' => $package->i18n('issue_tracker_priority'),
    'status' => $package->i18n('issue_tracker_status'),
    'due_date' => $package->i18n('issue_tracker_due_date'),
    'title' => $package->i18n('issue_tracker_title'),
];

// Kontext je Modus: Projektkarte, Hinweis für Wissen, passende „Neu“-Aktion und Suchtext
$projectCard = null;
if ($isProject) {
    $cardProject = Project::get((int) $state['filter_project']);
    if ($cardProject && $cardProject->hasAccess($userId)) {
        $projectCard = $cardProject;
    }
}
$newHref = rex_url::backendPage('issue_tracker/issues/create');
$newLabel = $package->i18n('issue_tracker_mail_new_issue');
if ($isProject && $projectCard) {
    $newHref = rex_url::backendPage('issue_tracker/issues/create', ['project_id' => $projectCard->getId()]);
    $newLabel = $package->i18n('issue_tracker_mail_new_issue_in_project');
} elseif ($isKnowledge && PermissionService::isAdminOrManager()) {
    $newHref = rex_url::backendPage('issue_tracker/issues/create', array_filter(['status' => 'info', 'category' => (string) ($state['filter_category'] ?? '')]));
    $newLabel = $package->i18n('issue_tracker_mail_new_knowledge');
}
$searchPlaceholder = $package->i18n($isKnowledge ? 'issue_tracker_mail_search_knowledge' : ($isProject ? 'issue_tracker_mail_search_project' : 'issue_tracker_mail_search_issues'));
$emptyText = $package->i18n($isKnowledge ? 'issue_tracker_mail_empty_knowledge' : ($isProject ? 'issue_tracker_mail_empty_project' : 'issue_tracker_mail_empty_issues'));
if (($state['search'] ?? '') !== '') {
    $emptyText = $package->i18n('issue_tracker_mail_empty_search');
}

// Export: dieselben Filter wie die Liste (auch wenn sie aus der Sitzung kommen)
$exportFilter = [];
foreach (['filter_status', 'filter_category', 'filter_tag', 'filter_created_by', 'filter_assigned', 'filter_project', 'search'] as $key) {
    $value = $state[$key] ?? '';
    if ('' !== (string) $value && '0' !== (string) $value) {
        $exportFilter[$key] = $value;
    }
}
$exportParams = $exportFilter + ['rex-api-call' => 'issue_tracker_export', 'format' => 'csv'];
$hasPdf = \rex_addon::exists('pdfout') && \rex_addon::get('pdfout')->isAvailable();
$pdfParams = $exportFilter + ['export_pdf' => 1];

$activeFilterCount = 0;
foreach ($state as $k => $v) {
    if ($k !== 'search' && $v !== '' && $v !== 0 && $v !== '0') {
        ++$activeFilterCount;
    }
}
?>
<section class="it-ml<?= $viewMode === 'table' ? ' it-ml--table' : '' ?>" aria-label="<?= rex_escape($mailboxTitle) ?>">
    <header class="it-ml-bar">
        <a class="it-tb-back it-tb-back--list" href="<?= $listUrl() ?>"><i class="rex-icon fa-angle-left" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_mailboxes') ?></a>
        <div class="it-ml-head">
            <div class="it-ml-heading">
                <h2 class="it-ml-title"><?= rex_escape($mailboxTitle) ?></h2>
                <span class="it-ml-total"><?= $package->i18n('issue_tracker_mail_count', $totalIssues) ?></span>
            </div>
            <div class="it-ml-head-actions">
                <div class="it-seg" role="group" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_view_switch')) ?>">
                    <a class="it-seg-btn<?= $viewMode === 'mail' ? ' is-active' : '' ?>" href="<?= $listUrl(['view_mode' => 'mail']) ?>"<?= $viewMode === 'mail' ? ' aria-current="true"' : '' ?>><?= $package->i18n('issue_tracker_mail_view_mailbox') ?></a>
                    <a class="it-seg-btn<?= $viewMode === 'table' ? ' is-active' : '' ?>" href="<?= $listUrl(['view_mode' => 'table']) ?>"<?= $viewMode === 'table' ? ' aria-current="true"' : '' ?>><?= $package->i18n('issue_tracker_mail_view_table') ?></a>
                </div>
                <a class="it-tb-btn it-tb-btn--accent" href="<?= $newHref ?>" title="<?= rex_escape($newLabel) ?>">
                    <i class="rex-icon fa-pencil-square-o" aria-hidden="true"></i><span class="sr-only"><?= rex_escape($newLabel) ?></span>
                </a>
            </div>
        </div>
        <div class="it-ml-tools">
            <form class="it-search" method="get" action="<?= rex_url::backendController() ?>" role="search">
                <input type="hidden" name="page" value="issue_tracker/issues/list" />
                <?php foreach ($state as $key => $value): if ($key === 'search') { continue; } ?>
                <input type="hidden" name="<?= rex_escape($key) ?>" value="<?= rex_escape((string) $value) ?>" />
                <?php endforeach; ?>
                <i class="rex-icon fa-search it-search-icon" aria-hidden="true"></i>
                <input type="search" name="search" class="it-search-input" value="<?= rex_escape((string) ($state['search'] ?? '')) ?>"
                       placeholder="<?= rex_escape($searchPlaceholder) ?>" aria-label="<?= rex_escape($searchPlaceholder) ?>" />
            </form>

            <details class="it-menu">
                <summary class="it-tb-btn" title="<?= rex_escape($package->i18n('issue_tracker_mail_sort')) ?>"><i class="rex-icon fa-sort-amount-desc" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_sort') ?></span></summary>
                <div class="it-menu-pop it-menu-pop--right">
                    <div class="it-menu-label"><?= $package->i18n('issue_tracker_mail_sort') ?></div>
                    <?php foreach ($sortOptions as $col => $label): ?>
                    <a class="it-menu-item<?= $sortColumn === $col ? ' is-checked' : '' ?>" href="<?= $listUrl(['sort' => $col, 'order' => strtolower($sortOrder)]) ?>"><i class="rex-icon fa-check" aria-hidden="true"></i> <?= rex_escape($label) ?></a>
                    <?php endforeach; ?>
                    <div class="it-menu-sep"></div>
                    <a class="it-menu-item<?= $sortOrder === 'ASC' ? ' is-checked' : '' ?>" href="<?= $listUrl(['sort' => $sortColumn, 'order' => 'asc']) ?>"><i class="rex-icon fa-check" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_sort_asc') ?></a>
                    <a class="it-menu-item<?= $sortOrder === 'DESC' ? ' is-checked' : '' ?>" href="<?= $listUrl(['sort' => $sortColumn, 'order' => 'desc']) ?>"><i class="rex-icon fa-check" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_sort_desc') ?></a>
                </div>
            </details>

            <details class="it-menu">
                <summary class="it-tb-btn<?= $activeFilterCount > 0 ? ' is-on' : '' ?>" title="<?= rex_escape($package->i18n('issue_tracker_mail_more_filters')) ?>"><i class="rex-icon fa-sliders" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_more_filters') ?></span></summary>
                <div class="it-menu-pop it-menu-pop--right it-menu-pop--form">
                    <form method="get" action="<?= rex_url::backendController() ?>">
                        <input type="hidden" name="page" value="issue_tracker/issues/list" />
                        <?php foreach (['filter_tag', 'filter_assigned', 'filter_project', 'filter_watched', 'filter_mentioned', 'filter_overdue', 'filter_recent_closed', 'search'] as $key): ?>
                        <input type="hidden" name="<?= $key ?>" value="<?= rex_escape((string) ($state[$key] ?? '')) ?>" />
                        <?php endforeach; ?>
                        <?php if ($isKnowledge): ?>
                        <input type="hidden" name="filter_status" value="info" />
                        <?php else: ?>
                        <label class="it-menu-field"><span><?= $package->i18n('issue_tracker_status') ?></span>
                            <select name="filter_status" class="form-control">
                                <option value=""><?= $package->i18n('issue_tracker_all_active') ?></option>
                                <option value="_all_"<?= ($state['filter_status'] ?? '') === '_all_' ? ' selected' : '' ?>><?= $package->i18n('issue_tracker_all_issues') ?></option>
                                <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                                <option value="<?= rex_escape($statusKey) ?>"<?= ($state['filter_status'] ?? '') === $statusKey ? ' selected' : '' ?>><?= $package->i18n('issue_tracker_status_' . $statusKey) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php endif; ?>
                        <label class="it-menu-field"><span><?= $package->i18n('issue_tracker_category') ?></span>
                            <select name="filter_category" class="form-control">
                                <option value=""><?= $package->i18n('issue_tracker_all') ?></option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= rex_escape($category) ?>"<?= ($state['filter_category'] ?? '') === $category ? ' selected' : '' ?>><?= rex_escape($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="it-menu-field"><span><?= $package->i18n('issue_tracker_created_by') ?></span>
                            <select name="filter_created_by" class="form-control">
                                <option value="0"><?= $package->i18n('issue_tracker_all') ?></option>
                                <?php foreach ($users as $uid => $uname): ?>
                                <option value="<?= (int) $uid ?>"<?= (int) ($state['filter_created_by'] ?? 0) === (int) $uid ? ' selected' : '' ?>><?= rex_escape($uname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="it-menu-actions">
                            <a class="btn btn-default btn-sm" href="<?= $listUrl(['reset_filter' => 1]) ?>"><?= $package->i18n('issue_tracker_filter_reset') ?></a>
                            <button type="submit" class="btn btn-primary it-btn-primary btn-sm"><?= $package->i18n('issue_tracker_filter_apply') ?></button>
                        </div>
                    </form>
                </div>
            </details>

            <details class="it-menu">
                <summary class="it-tb-btn" title="<?= rex_escape($package->i18n('issue_tracker_export')) ?>"><i class="rex-icon fa-download" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_export') ?></span></summary>
                <div class="it-menu-pop it-menu-pop--right">
                    <a class="it-menu-item" href="<?= rex_url::backendPage('', $exportParams) ?>"><i class="rex-icon fa-file-text-o" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_export_csv') ?></a>
                    <?php if ($hasPdf): ?>
                    <a class="it-menu-item" href="<?= rex_url::backendPage('issue_tracker/issues/list', $pdfParams) ?>" target="_blank"><i class="rex-icon fa-file-pdf-o" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_export_pdf') ?></a>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </header>

    <?php if ($projectCard):
        $pStats = $projectCard->getStats();
        $pMembers = $projectCard->getUsers();
        $canEditProject = (rex::getUser()->isAdmin() || rex::getUser()->hasPerm('issue_tracker[issue_manager]')) && $projectCard->isOwner($userId);
    ?>
    <div class="it-pcard" style="<?= rex_escape($colorVar($projectCard->getColor())) ?>">
        <div class="it-pcard-head">
            <span class="it-dot it-dot--custom" aria-hidden="true"></span>
            <strong class="it-pcard-name"><?= rex_escape($projectCard->getName()) ?></strong>
            <?php if ($projectCard->getStatus() !== 'active'): ?><span class="it-badge"><?= $package->i18n('issue_tracker_project_status_' . $projectCard->getStatus()) ?></span><?php endif; ?>
            <?php if ($canEditProject): ?>
            <a class="it-tb-btn it-tb-btn--sm it-pcard-edit" href="<?= rex_url::backendPage('issue_tracker/projects/edit', ['project_id' => $projectCard->getId()]) ?>" title="<?= rex_escape($package->i18n('issue_tracker_edit')) ?>"><i class="rex-icon fa-pencil" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_edit') ?></span></a>
            <?php endif; ?>
        </div>
        <?php if ($projectCard->getDescription() !== ''): ?>
        <p class="it-pcard-desc"><?= rex_escape(mb_strimwidth($preview($projectCard->getDescription()), 0, 180, '…')) ?></p>
        <?php endif; ?>
        <div class="it-pcard-progress">
            <progress class="it-progress" max="100" value="<?= (int) $pStats['progress'] ?>" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_progress')) ?>"></progress>
            <span><?= $package->i18n('issue_tracker_mail_progress_text', (int) $pStats['progress'], (int) ($pStats['closed'] + $pStats['rejected']), (int) $pStats['total']) ?></span>
        </div>
        <?php
        $uiState = rex_config::get('issue_tracker', 'sidebar_state_' . $userId, []);
        $uiOpen = static fn (string $key): string => is_array($uiState) && !empty($uiState[$key]) ? ' open' : '';
        ?>
        <details class="it-pcard-more" data-it-sec="project_status"<?= $uiOpen('project_status') ?>>
            <summary><?= $package->i18n('issue_tracker_status') ?> <span class="it-disclosure-count"><?= (int) $pStats['total'] ?></span></summary>
            <ul class="it-pcard-status">
                <?php foreach ($statuses as $stKey => $stLabel): if ((int) ($pStats[$stKey] ?? 0) === 0) { continue; } ?>
                <li><a href="<?= $listUrl(['filter_status' => $stKey, 'filter_project' => $projectCard->getId()]) ?>"><span class="it-dot it-dot--<?= rex_escape($stKey) ?>" aria-hidden="true"></span> <?= $package->i18n('issue_tracker_status_' . $stKey) ?> <span class="it-mbx-count"><?= (int) $pStats[$stKey] ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php if ($pMembers): ?>
        <details class="it-pcard-more" data-it-sec="project_members"<?= $uiOpen('project_members') ?>>
            <summary><?= $package->i18n('issue_tracker_mail_members') ?> <span class="it-disclosure-count"><?= count($pMembers) ?></span></summary>
            <ul class="it-pcard-memberlist">
                <?php foreach ($pMembers as $member): $mName = (string) (($member['name'] ?? '') !== '' ? $member['name'] : ($member['login'] ?? '')); ?>
                <li><?= UiHelper::avatar((int) $member['user_id'], $mName, true) ?> <?= rex_escape($mName) ?> <span class="it-muted-text">· <?= $package->i18n('issue_tracker_role_' . $member['role']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
    <?php elseif ($isKnowledge): ?>
    <p class="it-ml-hint"><i class="rex-icon fa-lightbulb-o" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_knowledge_hint') ?></p>
    <?php endif; ?>

    <?php if (empty($issues)): ?>
        <div class="it-empty">
            <i class="rex-icon <?= $isKnowledge ? 'fa-book' : 'fa-inbox' ?>" aria-hidden="true"></i>
            <?= rex_escape($emptyText) ?>
            <?php if (($state['search'] ?? '') === ''): ?>
            <p><a class="btn btn-default btn-sm" href="<?= $newHref ?>"><?= rex_escape($newLabel) ?></a></p>
            <?php endif; ?>
        </div>
    <?php elseif ($viewMode === 'mail'): ?>
        <ul class="it-ml-items" data-it-list>
            <?php foreach ($issues as $issue):
                $iid = $issue->getId();
                $creator = $issue->getCreator();
                $assigned = $issue->getAssignedUser();
                $project = $issue->getProject();
                $tags = $issue->getTags();
                $isSel = $iid === $selectedIssueId;
                $unread = in_array($iid, $unreadMentionIds, true);
                $prio = $issue->getPriority();
                $whoFrom = UiHelper::userName($creator, '–');
                $whoTo = $issue->getAssignedUserId() === null ? $package->i18n('issue_tracker_assigned_all_users') : UiHelper::userName($assigned, '–');
                $cCount = $commentCounts[$iid] ?? 0;
                $aCount = $attachmentCounts[$iid] ?? 0;
                $text = $preview($issue->getDescription());
            ?>
            <li>
                <a class="it-ml-row<?= $isSel ? ' is-selected' : '' ?><?= $unread ? ' is-unread' : '' ?>" href="<?= $listUrl($carry + ['issue_id' => $iid]) ?>"
                   data-issue-id="<?= $iid ?>" data-view-href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $iid]) ?>"<?= $isSel ? ' aria-current="true"' : '' ?>>
                    <span class="it-ml-mark">
                        <?php if ($isKnowledge): ?>
                        <i class="rex-icon fa-book it-ml-kicon" aria-hidden="true"></i>
                        <?php elseif ($unread): ?>
                        <span class="it-dot it-dot--unread" title="<?= rex_escape($package->i18n('issue_tracker_mail_unread_mention')) ?>"></span><span class="sr-only"><?= $package->i18n('issue_tracker_mail_unread_mention') ?></span>
                        <?php else: ?>
                        <span class="it-dot it-dot--<?= rex_escape($issue->getStatus()) ?>" title="<?= rex_escape($package->i18n('issue_tracker_status_' . $issue->getStatus())) ?>"></span>
                        <?php endif; ?>
                    </span>
                    <span class="it-ml-main">
                        <span class="it-ml-line1">
                            <span class="it-ml-subject"><?= rex_escape($issue->getTitle()) ?></span>
                            <?php $rowDate = $isKnowledge ? ($issue->getUpdatedAt() ?? $issue->getCreatedAt()) : ((int) ($state['filter_recent_closed'] ?? 0) === 1 ? ($issue->getClosedAt() ?? $issue->getCreatedAt()) : $issue->getCreatedAt()); ?>
                            <time class="it-ml-date" datetime="<?= $rowDate ? $rowDate->format('c') : '' ?>"><?= rex_escape(UiHelper::relDate($rowDate)) ?></time>
                        </span>
                        <?php if ($isKnowledge): ?>
                        <span class="it-ml-line2"><span class="it-ml-who"><i class="rex-icon fa-folder-o" aria-hidden="true"></i> <?= rex_escape($issue->getCategory() !== '' ? $issue->getCategory() : $package->i18n('issue_tracker_mail_no_category')) ?></span></span>
                        <?php else: ?>
                        <span class="it-ml-line2">
                            <span class="it-ml-who"><?= rex_escape($whoFrom) ?> <i class="rex-icon fa-long-arrow-right" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_to') ?></span> <?= rex_escape($whoTo) ?></span>
                            <?php if ($project && !$isProject): ?><span class="it-ml-proj"><span class="it-dot it-dot--custom it-dot--sm" style="<?= rex_escape($colorVar($project->getColor())) ?>" aria-hidden="true"></span><?= rex_escape($project->getName()) ?></span><?php endif; ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($text !== ''): ?>
                        <span class="it-ml-preview"><?= rex_escape($text) ?></span>
                        <?php endif; ?>
                        <?php if (!$isKnowledge): ?>
                        <span class="it-ml-meta">
                            <span class="it-ml-id">#<?= $iid ?></span>
                            <span class="it-ml-status"><?= $package->i18n('issue_tracker_status_' . $issue->getStatus()) ?></span>
                            <?php if (in_array($prio, ['urgent', 'critical'], true)): ?>
                            <span class="it-ml-flag it-ml-flag--danger" title="<?= rex_escape($package->i18n('issue_tracker_priority_' . $prio)) ?>"><i class="rex-icon fa-flag" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_priority') ?>: <?= $package->i18n('issue_tracker_priority_' . $prio) ?></span></span>
                            <?php elseif ($prio === 'high'): ?>
                            <span class="it-ml-flag it-ml-flag--warn" title="<?= rex_escape($package->i18n('issue_tracker_priority_high')) ?>"><i class="rex-icon fa-flag" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_priority') ?>: <?= $package->i18n('issue_tracker_priority_high') ?></span></span>
                            <?php endif; ?>
                            <?php if ($issue->isOverdue()): ?>
                            <span class="it-ml-flag it-ml-flag--danger" title="<?= rex_escape($package->i18n('issue_tracker_overdue')) ?>"><i class="rex-icon fa-calendar-times-o" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_overdue') ?></span></span>
                            <?php endif; ?>
                            <?php if ($issue->getIsPrivate()): ?>
                            <span class="it-ml-icon" title="<?= rex_escape($package->i18n('issue_tracker_private')) ?>"><i class="rex-icon fa-lock" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_private') ?></span></span>
                            <?php endif; ?>
                            <?php if (in_array($iid, $watchedIds, true)): ?>
                            <span class="it-ml-icon" title="<?= rex_escape($package->i18n('issue_tracker_watching')) ?>"><i class="rex-icon fa-eye" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_watching') ?></span></span>
                            <?php endif; ?>
                            <?php if ($aCount > 0): ?>
                            <span class="it-ml-icon" title="<?= rex_escape($package->i18n('issue_tracker_attachments')) ?>"><i class="rex-icon fa-paperclip" aria-hidden="true"></i> <?= $aCount ?></span>
                            <?php endif; ?>
                            <?php if ($cCount > 0): ?>
                            <span class="it-ml-icon" title="<?= rex_escape($package->i18n('issue_tracker_comments')) ?>"><i class="rex-icon fa-comment-o" aria-hidden="true"></i> <?= $cCount ?></span>
                            <?php endif; ?>
                            <?php foreach (array_slice($tags, 0, 2) as $tag): ?>
                            <span class="it-chip"><span class="it-dot it-dot--custom it-dot--sm" style="<?= rex_escape($colorVar($tag->getColor())) ?>" aria-hidden="true"></span><?= rex_escape($tag->getName()) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($tags) > 2): ?><span class="it-chip">+<?= count($tags) - 2 ?></span><?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if (count($issues) < $totalIssues): ?>
        <div class="it-ml-more">
            <a class="it-ml-more-link" href="<?= $listUrl(['show' => $showCount + $pageSize] + $carry) ?>"><?= $package->i18n('issue_tracker_mail_load_more') ?> (<?= count($issues) ?>/<?= $totalIssues ?>)</a>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="it-group it-table-wrap">
            <table class="table table-hover it-table">
                <thead>
                    <tr>
                        <th class="it-col-id"><a href="<?= $sortUrl('id') ?>"># <?= $sortIcon('id') ?></a></th>
                        <th><a href="<?= $sortUrl('title') ?>"><?= $package->i18n('issue_tracker_title') ?> <?= $sortIcon('title') ?></a></th>
                        <th><a href="<?= $sortUrl('category') ?>"><?= $package->i18n('issue_tracker_category') ?> <?= $sortIcon('category') ?></a></th>
                        <th><a href="<?= $sortUrl('status') ?>"><?= $package->i18n('issue_tracker_status') ?> <?= $sortIcon('status') ?></a></th>
                        <th><a href="<?= $sortUrl('priority') ?>"><?= $package->i18n('issue_tracker_priority') ?> <?= $sortIcon('priority') ?></a></th>
                        <th><a href="<?= $sortUrl('assigned_user_id') ?>"><?= $package->i18n('issue_tracker_assigned') ?> <?= $sortIcon('assigned_user_id') ?></a></th>
                        <th><a href="<?= $sortUrl('due_date') ?>"><?= $package->i18n('issue_tracker_due_date') ?> <?= $sortIcon('due_date') ?></a></th>
                        <th><a href="<?= $sortUrl('created_at') ?>"><?= $package->i18n('issue_tracker_created_at') ?> <?= $sortIcon('created_at') ?></a></th>
                        <th class="it-col-actions"><?= $package->i18n('issue_tracker_actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($issues as $issue):
                    $assignedUser = $issue->getAssignedUser();
                    $tags = $issue->getTags();
                    $cCount = $commentCounts[$issue->getId()] ?? 0;
                    $creator = $issue->getCreator();
                    $clProgress = \FriendsOfREDAXO\IssueTracker\ContentRenderer::getChecklistProgress($issue->getDescription());
                ?>
                    <tr>
                        <td class="it-col-id">
                            <?= $issue->getId() ?>
                            <?php if (in_array($issue->getId(), $watchedIds, true)): ?>
                                <i class="rex-icon fa-eye it-muted-icon" title="<?= rex_escape($package->i18n('issue_tracker_watching')) ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>"><?= rex_escape($issue->getTitle()) ?></a>
                            <?php if ($cCount > 0): ?>
                                <span class="it-muted-text"><i class="rex-icon fa-comment-o" aria-hidden="true"></i> <?= $cCount ?></span>
                            <?php endif; ?>
                            <?php
                            $domainIds = $issue->getDomainIds();
                            $yformTables = $issue->getYformTables();
                            if (!empty($domainIds) || !empty($yformTables)):
                            ?>
                                <br><small class="it-muted-text">
                                    <?php if (!empty($domainIds) && rex_addon::exists('yrewrite') && rex_addon::get('yrewrite')->isAvailable()): ?>
                                        <i class="rex-icon fa-globe" aria-hidden="true"></i>
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
                                    <?php if (!empty($yformTables)): ?>
                                        <i class="rex-icon fa-database" aria-hidden="true"></i> <?= rex_escape(implode(', ', $yformTables)) ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                            <?php if (!empty($tags)): ?>
                                <div class="it-chips">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="it-chip"><span class="it-dot it-dot--custom it-dot--sm" style="<?= rex_escape($colorVar($tag->getColor())) ?>" aria-hidden="true"></span><?= rex_escape($tag->getName()) ?></span>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($clProgress['total'] > 0): ?>
                                <div class="it-progress-mini"><small class="it-muted-text"><i class="rex-icon fa-check-square-o" aria-hidden="true"></i> <?= $clProgress['checked'] ?>/<?= $clProgress['total'] ?></small>
                                <progress class="it-progress" max="<?= (int) $clProgress['total'] ?>" value="<?= (int) $clProgress['checked'] ?>"></progress></div>
                            <?php endif; ?>
                        </td>
                        <td><?= rex_escape($issue->getCategory()) ?></td>
                        <td><?= UiHelper::statusBadge($issue->getStatus()) ?></td>
                        <td><?= UiHelper::priorityBadge($issue->getPriority()) ?></td>
                        <td>
                            <?php if ($issue->getAssignedUserId() === null): ?>
                                <?= $package->i18n('issue_tracker_assigned_all_users') ?>
                            <?php else: ?>
                                <?= $assignedUser ? rex_escape($assignedUser->getValue('name')) : '-' ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($issue->getDueDate()): ?>
                                <?php if ($issue->isOverdue()): ?>
                                    <span class="it-badge it-badge--danger" title="<?= rex_escape($package->i18n('issue_tracker_overdue')) ?>"><?= $issue->getDueDate()->format('d.m.Y') ?></span>
                                <?php else: ?>
                                    <?= $issue->getDueDate()->format('d.m.Y') ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="it-muted-text">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $issue->getCreatedAt() ? $issue->getCreatedAt()->format('d.m.Y H:i') : '-' ?>
                            <?php if ($creator): ?>
                                <br><small class="it-muted-text"><?= $package->i18n('issue_tracker_from') ?> <?= rex_escape($creator->getValue('name')) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="it-col-actions">
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>" class="it-tb-btn it-tb-btn--sm" title="<?= rex_escape($package->i18n('issue_tracker_view')) ?>"><i class="rex-icon fa-eye" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_view') ?></span></a>
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/edit', ['issue_id' => $issue->getId()]) ?>" class="it-tb-btn it-tb-btn--sm" title="<?= rex_escape($package->i18n('issue_tracker_edit')) ?>"><i class="rex-icon fa-pencil" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_edit') ?></span></a>
                            <?php if (PermissionService::canDelete()): ?>
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/list', ['func' => 'delete', 'issue_id' => $issue->getId()]) ?>" class="it-tb-btn it-tb-btn--sm it-tb-btn--danger"
                               title="<?= rex_escape($package->i18n('issue_tracker_delete')) ?>" onclick="return confirm('<?= rex_escape($package->i18n('issue_tracker_delete_confirm')) ?>')"><i class="rex-icon fa-trash" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_delete') ?></span></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
