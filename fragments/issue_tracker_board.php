<?php
/**
 * Kanban Board Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$project = $this->getVar('project');
$issues = $this->getVar('issues', []);
$statuses = $this->getVar('statuses', []);
$canWrite = $this->getVar('canWrite', false);

// Organize issues by status with sort_order
$issuesByStatus = [];
$allStatuses = !empty($statuses) ? array_keys($statuses) : ['open', 'in_progress', 'planned', 'info', 'rejected', 'closed'];

foreach ($allStatuses as $status) {
    $issuesByStatus[$status] = [];
}

foreach ($issues as $issue) {
    $status = $issue->getStatus();
    if (!isset($issuesByStatus[$status])) {
        $issuesByStatus[$status] = [];
    }
    $issuesByStatus[$status][] = $issue;
}

// Sort by sort_order within each status
foreach ($issuesByStatus as $status => &$statusIssues) {
    usort($statusIssues, function($a, $b) {
        return $a->getSortOrder() - $b->getSortOrder();
    });
}
unset($statusIssues);

// Pre-load all tags for all issues to avoid N+1 queries
$issueIds = array_map(function($issue) { return $issue->getId(); }, $issues);
$tagsByIssue = [];
if (!empty($issueIds)) {
    // Create placeholders for parameterized query
    $placeholders = rtrim(str_repeat('?,', count($issueIds)), ',');
    $tagSql = rex_sql::factory();
    $tagSql->setQuery(
        'SELECT it.issue_id, t.name, t.color FROM ' . rex::getTable('issue_tracker_tags') . ' t ' .
        'INNER JOIN ' . rex::getTable('issue_tracker_issue_tags') . ' it ON t.id = it.tag_id ' .
        'WHERE it.issue_id IN (' . $placeholders . ')',
        $issueIds
    );
    foreach ($tagSql as $row) {
        $issueId = (int) $row->getValue('issue_id');
        if (!isset($tagsByIssue[$issueId])) {
            $tagsByIssue[$issueId] = [];
        }
        $tagsByIssue[$issueId][] = [
            'name' => $row->getValue('name'),
            'color' => $row->getValue('color'),
        ];
    }
}

$statusClasses = [
    'open' => 'danger',
    'in_progress' => 'warning',
    'planned' => 'info',
    'info' => 'primary',
    'rejected' => 'default',
    'closed' => 'success',
];

$priorityClasses = [
    'low' => 'default',
    'normal' => 'info',
    'high' => 'warning',
    'urgent' => 'danger',
];

$currentUser = rex::getUser();
$now = new DateTime(); // Create once for all isOverdue comparisons
?>

<div class="kanban-board" id="kanban-board" 
     data-project-id="<?= $project->getId() ?>" 
     data-can-write="<?= $canWrite ? '1' : '0' ?>"
     data-api-url="<?= rex::getServer() ?>index.php?rex-api-call=issue_tracker_board"
     data-empty-text="<?= rex_escape($package->i18n('issue_tracker_no_issues')) ?>">
    <?php foreach ($allStatuses as $status): 
        $statusLabel = $statuses[$status] ?? $package->i18n('issue_tracker_status_' . $status);
        $statusClass = $statusClasses[$status] ?? 'default';
        $columnIssues = $issuesByStatus[$status] ?? [];
    ?>
    <div class="kanban-column">
        <div class="kanban-column-header kanban-column-header-<?= $statusClass ?>">
            <span><?= rex_escape($statusLabel) ?></span>
            <span class="badge"><?= count($columnIssues) ?></span>
        </div>
        <div class="kanban-column-body" data-status="<?= $status ?>">
            <?php if (empty($columnIssues)): ?>
                <div class="kanban-empty"><?= $package->i18n('issue_tracker_no_issues') ?></div>
            <?php else: ?>
                <?php foreach ($columnIssues as $issue): 
                    $assignedUser = $issue->getAssignedUserId() ? rex_user::get($issue->getAssignedUserId()) : null;
                    $dueDate = $issue->getDueDate();
                    $isOverdue = $dueDate && $dueDate < $now;
                    
                    // Get pre-loaded tags for this issue
                    $tags = $tagsByIssue[$issue->getId()] ?? [];
                ?>
                <div class="kanban-card"
                     data-issue-id="<?= $issue->getId() ?>" 
                     data-status="<?= $issue->getStatus() ?>"
                     data-sort-order="<?= $issue->getSortOrder() ?>"
                     onclick="window.location='<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]) ?>'">
                    <div class="kanban-card-id">#<?= $issue->getId() ?></div>
                    <div class="kanban-card-title"><?= rex_escape($issue->getTitle()) ?></div>
                    
                    <div class="kanban-card-meta">
                        <!-- Priority -->
                        <span class="label label-<?= $priorityClasses[$issue->getPriority()] ?? 'default' ?> kanban-card-badge">
                            <?= $package->i18n('issue_tracker_priority_' . $issue->getPriority()) ?>
                        </span>
                        
                        <!-- Category -->
                        <?php if ($issue->getCategory()): ?>
                        <span class="label label-default kanban-card-badge">
                            <?= rex_escape($issue->getCategory()) ?>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Tags -->
                        <?php foreach ($tags as $tag): ?>
                        <span class="label kanban-card-badge" style="background-color: <?= rex_escape($tag['color']) ?>; color: #fff;">
                            <?= rex_escape($tag['name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="kanban-card-meta" style="margin-top: 5px;">
                        <!-- Assigned User -->
                        <?php if ($assignedUser): ?>
                        <div class="kanban-card-user">
                            <i class="rex-icon fa-user"></i>
                            <span><?= rex_escape($assignedUser->getValue('name') ?: $assignedUser->getValue('login')) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Due Date -->
                        <?php if ($dueDate): ?>
                        <div class="kanban-card-due <?= $isOverdue ? 'overdue' : '' ?>">
                            <i class="rex-icon fa-calendar"></i>
                            <?= $dueDate->format('d.m.Y') ?>
                            <?php if ($isOverdue): ?>
                            <i class="rex-icon fa-exclamation-triangle"></i>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($canWrite): ?>
<!-- Drag & drop functionality is loaded via issue_tracker_board.js -->
<?php endif; ?>
