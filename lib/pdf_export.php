<?php

/**
 * PDF Export Hilfsdatei
 * 
 * @package issue_tracker
 */

$sql = \rex_sql::factory();
$currentUser = \rex::getUser();
$userId = $currentUser->getId();

// Filter aus Request
$filterStatus = \rex_request('filter_status', 'string', '');
$filterCategory = \rex_request('filter_category', 'string', '');
$filterTag = \rex_request('filter_tag', 'int', 0);
$search = \rex_request('search', 'string', '');
$sortColumn = \rex_request('sort', 'string', 'created_at');
$sortOrder = \rex_request('order', 'string', 'desc');

// Erlaubte Sortier-Spalten
$allowedSortColumns = ['id', 'title', 'category', 'status', 'priority', 'assigned_user_id', 'due_date', 'created_at'];
if (!in_array($sortColumn, $allowedSortColumns)) {
    $sortColumn = 'created_at';
}
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Query aufbauen
$where = [];
$joins = '';

if ($filterStatus && $filterStatus !== '_all_') {
    $where[] = 'status = ' . $sql->escape($filterStatus);
}

if ($filterCategory) {
    $where[] = 'category = ' . $sql->escape($filterCategory);
}

if ($filterTag > 0) {
    $joins .= ' LEFT JOIN ' . \rex::getTable('issue_tracker_issue_tags') . ' itt ON i.id = itt.issue_id';
    $where[] = 'itt.tag_id = ' . (int)$filterTag;
}

if ($search) {
    $search = $sql->escape('%' . $search . '%');
    $where[] = '(title LIKE ' . $search . ' OR description LIKE ' . $search . ')';
}

if (!\FriendsOfREDAXO\IssueTracker\PermissionService::isAdmin()) {
    $where[] = '(private_issue = 0 OR created_by = ' . $userId . ' OR assigned_user_id = ' . $userId . ')';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$query = '
    SELECT DISTINCT i.* 
    FROM ' . \rex::getTable('issue_tracker_issues') . ' i
    ' . $joins . '
    ' . $whereClause . '
    ORDER BY ' . $sortColumn . ' ' . $sortOrder . '
';

$sql->setQuery($query);

$issues = [];
foreach ($sql as $row) {
    $issues[] = \FriendsOfREDAXO\IssueTracker\Issue::get((int)$row->getValue('id'));
}

// Statistiken berechnen
$sqlStats = \rex_sql::factory();

// Helper-Funktion für Stats-Queries
function buildStatsWhere($baseWhere, $additionalCondition = '') {
    if (!empty($baseWhere)) {
        $allConditions = array_merge($baseWhere, $additionalCondition ? [$additionalCondition] : []);
        return 'WHERE ' . implode(' AND ', $allConditions);
    }
    return $additionalCondition ? 'WHERE ' . $additionalCondition : '';
}

$baseWhere = $where;

// Offene Issues
$whereStats = buildStatsWhere($baseWhere, 'status = "open"');
$sqlStats->setQuery('
    SELECT COUNT(*) as cnt 
    FROM ' . \rex::getTable('issue_tracker_issues') . ' i
    ' . $joins . '
    ' . $whereStats . '
');
$statsOpen = (int)$sqlStats->getValue('cnt');

// In Bearbeitung
$whereStats = buildStatsWhere($baseWhere, 'status = "in_progress"');
$sqlStats->setQuery('
    SELECT COUNT(*) as cnt 
    FROM ' . \rex::getTable('issue_tracker_issues') . ' i
    ' . $joins . '
    ' . $whereStats . '
');
$statsInProgress = (int)$sqlStats->getValue('cnt');

// Geschlossen
$whereStats = buildStatsWhere($baseWhere, 'status = "closed"');
$sqlStats->setQuery('
    SELECT COUNT(*) as cnt 
    FROM ' . \rex::getTable('issue_tracker_issues') . ' i
    ' . $joins . '
    ' . $whereStats . '
');
$statsClosed = (int)$sqlStats->getValue('cnt');

// Überfällig
$whereStats = buildStatsWhere($baseWhere, 'due_date < NOW() AND status NOT IN ("closed", "rejected")');
$sqlStats->setQuery('
    SELECT COUNT(*) as cnt 
    FROM ' . \rex::getTable('issue_tracker_issues') . ' i
    ' . $joins . '
    ' . $whereStats . '
');
$statsOverdue = (int)$sqlStats->getValue('cnt');

// Prioritäten
$whereStats = !empty($baseWhere) ? 'WHERE ' . implode(' AND ', $baseWhere) : '';
$sqlStats->setQuery('
    SELECT priority, COUNT(*) as cnt 
    FROM ' . \rex::getTable('issue_tracker_issues') . ' i
    ' . $joins . '
    ' . $whereStats . '
    GROUP BY priority
    ORDER BY FIELD(priority, "critical", "high", "normal", "low")
');
$priorityCounts = [];
foreach ($sqlStats as $row) {
    $priorityCounts[$row->getValue('priority')] = (int)$row->getValue('cnt');
}

$package = \rex_addon::get('issue_tracker');

// Status und Priority Klassen definieren
$statusClasses = [
    'open' => 'label-warning',
    'in_progress' => 'label-info',
    'closed' => 'label-success',
    'rejected' => 'label-danger',
];

$priorityClasses = [
    'low' => 'label-default',
    'normal' => 'label-info',
    'high' => 'label-warning',
    'critical' => 'label-danger',
];

// HTML für PDF generieren
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= \rex_escape($package->i18n('issue_tracker')) ?> - Export</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 20px;
            padding: 20px;
        }
        .header {
            border-bottom: 3px solid #0066cc;
            padding: 15px 0;
            margin-bottom: 20px;
            page-break-after: avoid;
        }
        h1 {
            font-size: 18px;
            color: #0066cc;
            margin-bottom: 8px;
        }
        .meta {
            font-size: 10px;
            color: #666;
        }
        
        .statistics {
            margin-bottom: 30px;
            padding: 15px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            page-break-inside: avoid;
        }
        .stat-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .stat-item {
            display: table-cell;
            width: 25%;
            padding: 15px;
            border: 1px solid #ddd;
            background: white;
            margin-right: 5px;
        }
        .stat-label {
            font-weight: bold;
            font-size: 10px;
            color: #666;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #0066cc;
        }
        
        .priority-stats {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        .priority-stats td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .priority-stats th {
            background: #0066cc;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
        }
        
        h2 {
            font-size: 14px;
            color: #0066cc;
            margin-top: 25px;
            margin-bottom: 15px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 8px;
            page-break-after: avoid;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th {
            background: #0066cc;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            border: 1px solid #0066cc;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .status-open {
            background: #fff3cd;
            padding: 2px 4px;
        }
        .status-in_progress {
            background: #cfe2ff;
            padding: 2px 4px;
        }
        .status-closed {
            background: #d1e7dd;
            padding: 2px 4px;
        }
        .status-rejected {
            background: #f8d7da;
            padding: 2px 4px;
        }
        
        .priority-critical { color: #d32f2f; font-weight: bold; }
        .priority-high { color: #f57c00; font-weight: bold; }
        .priority-normal { color: #1976d2; }
        .priority-low { color: #388e3c; }
        
        .page-break {
            page-break-before: always;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= \rex_escape($package->i18n('issue_tracker')) ?> - Export</h1>
        <div class="meta">
            <?= date('d.m.Y H:i:s') ?> | <?= \rex_escape($currentUser->getValue('name')) ?>
        </div>
    </div>

    <!-- Statistiken -->
    <div class="statistics">
        <h2><?= \rex_escape($package->i18n('issue_tracker_statistics')) ?></h2>
        
        <div class="stat-row">
            <div class="stat-item">
                <div class="stat-label"><?= \rex_escape($package->i18n('issue_tracker_status_open')) ?></div>
                <div class="stat-value"><?= $statsOpen ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label"><?= \rex_escape($package->i18n('issue_tracker_status_in_progress')) ?></div>
                <div class="stat-value"><?= $statsInProgress ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label"><?= \rex_escape($package->i18n('issue_tracker_status_closed')) ?></div>
                <div class="stat-value"><?= $statsClosed ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label"><?= \rex_escape($package->i18n('issue_tracker_status_overdue')) ?></div>
                <div class="stat-value"><?= $statsOverdue ?></div>
            </div>
        </div>
        
        <?php if (!empty($priorityCounts)): ?>
        <table class="priority-stats">
            <thead>
                <tr>
                    <th><?= \rex_escape($package->i18n('issue_tracker_priority')) ?></th>
                    <th><?= \rex_escape($package->i18n('issue_tracker_count')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($priorityCounts as $priority => $count): ?>
                <tr>
                    <td><span class="priority-<?= \rex_escape($priority) ?>"><?= \rex_escape($package->i18n('issue_tracker_priority_' . $priority)) ?></span></td>
                    <td><?= $count ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="page-break"></div>

    <!-- Issues -->
    <div>
        <h2><?= \rex_escape($package->i18n('issue_tracker_issues')) ?> (<?= count($issues) ?>)</h2>
        
        <?php if (count($issues) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= \rex_escape($package->i18n('issue_tracker_title')) ?></th>
                    <th><?= \rex_escape($package->i18n('issue_tracker_status')) ?></th>
                    <th><?= \rex_escape($package->i18n('issue_tracker_priority')) ?></th>
                    <th><?= \rex_escape($package->i18n('issue_tracker_category')) ?></th>
                    <th><?= \rex_escape($package->i18n('issue_tracker_assigned')) ?></th>
                    <th><?= \rex_escape($package->i18n('issue_tracker_due_date')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issues as $issue): ?>
                <tr>
                    <td><?= $issue->getId() ?></td>
                    <td><?= \rex_escape($issue->getTitle()) ?></td>
                    <td>
                        <span class="status-<?= \rex_escape($issue->getStatus()) ?>">
                            <?= \rex_escape($package->i18n('issue_tracker_status_' . $issue->getStatus())) ?>
                        </span>
                    </td>
                    <td>
                        <span class="priority-<?= \rex_escape($issue->getPriority()) ?>">
                            <?= \rex_escape($package->i18n('issue_tracker_priority_' . $issue->getPriority())) ?>
                        </span>
                    </td>
                    <td><?= \rex_escape($issue->getCategory()) ?></td>
                    <td>
                        <?php 
                        $assigned = '';
                        if ($issue->getAssignedUserId()) {
                            $assignedUser = \rex_user::get($issue->getAssignedUserId());
                            $assigned = $assignedUser ? $assignedUser->getValue('name') : '';
                        }
                        echo \rex_escape($assigned);
                        ?>
                    </td>
                    <td>
                        <?= $issue->getDueDate() ? $issue->getDueDate()->format('d.m.Y') : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?= \rex_escape($package->i18n('issue_tracker_no_issues')) ?></p>
        <?php endif; ?>
    </div>

    <div class="footer">
        <?= \rex_escape($package->i18n('issue_tracker')) ?> • Generiert: <?= date('d.m.Y H:i:s') ?>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// PDF mit pdfout generieren
$pdf = new \FriendsOfRedaxo\PdfOut\PdfOut();
$pdf->setName('issues_export_' . date('Y-m-d_H-i-s'))
    ->setAttachment(true)
    ->setDpi(150)
    ->setPaperSize('A4', 'portrait')
    ->setFont('DejaVu Sans')
    ->setHtml($html)
    ->run();
