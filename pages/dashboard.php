<?php

/**
 * Dashboard Seite
 * 
 * @package issue_tracker
 */

$package = rex_addon::get('issue_tracker');

// Statistiken berechnen
$sql = rex_sql::factory();
$currentUser = rex::getUser();
$userId = $currentUser->getId();

// Nur eigene Issues (erstellt oder zugewiesen)
$userCondition = '(created_by = ' . $userId . ' OR assigned_user_id = ' . $userId . ')';

// Offene Issues (eigene)
$sql->setQuery('SELECT COUNT(*) as cnt FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE status = "open" AND ' . $userCondition);
$openIssues = (int) $sql->getValue('cnt');

// In Bearbeitung (eigene)
$sql->setQuery('SELECT COUNT(*) as cnt FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE status = "in_progress" AND ' . $userCondition);
$inProgressIssues = (int) $sql->getValue('cnt');

// Überfällige Issues (eigene)
$sql->setQuery('
    SELECT COUNT(*) as cnt 
    FROM ' . rex::getTable('issue_tracker_issues') . ' 
    WHERE due_date < NOW()
    AND status NOT IN ("closed", "rejected")
    AND ' . $userCondition . '
');
$overdueIssues = (int) $sql->getValue('cnt');

// Erledigte Issues (letzte 30 Tage, eigene)
$sql->setQuery('
    SELECT COUNT(*) as cnt 
    FROM ' . rex::getTable('issue_tracker_issues') . ' 
    WHERE status = "closed" 
    AND closed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND ' . $userCondition . '
');
$closedIssues = (int) $sql->getValue('cnt');

// Issues nach Priorität (eigene, nicht geschlossen)
$sql->setQuery('
    SELECT priority, COUNT(*) as cnt 
    FROM ' . rex::getTable('issue_tracker_issues') . ' 
    WHERE status NOT IN ("closed", "rejected")
    AND ' . $userCondition . '
    GROUP BY priority
    ORDER BY FIELD(priority, "critical", "high", "normal", "low")
');
$priorityCounts = [];
foreach ($sql as $row) {
    $priorityCounts[$row->getValue('priority')] = (int) $row->getValue('cnt');
}

// Neueste eigene Issues
$sql->setQuery('
    SELECT * 
    FROM ' . rex::getTable('issue_tracker_issues') . ' 
    WHERE ' . $userCondition . '
    ORDER BY created_at DESC 
    LIMIT 5
');
$recentIssues = [];
foreach ($sql as $row) {
    $recentIssues[] = \FriendsOfREDAXO\IssueTracker\Issue::get((int) $row->getValue('id'));
}

// Letzte Aktivitäten bei eigenen Issues
$sql->setQuery('
    SELECT h.*, i.title as issue_title
    FROM ' . rex::getTable('issue_tracker_history') . ' h
    JOIN ' . rex::getTable('issue_tracker_issues') . ' i ON h.issue_id = i.id
    WHERE ' . $userCondition . '
    ORDER BY h.created_at DESC
    LIMIT 10
');
$recentActivities = [];
foreach ($sql as $row) {
    $recentActivities[] = [
        'id' => (int) $row->getValue('id'),
        'issue_id' => (int) $row->getValue('issue_id'),
        'issue_title' => $row->getValue('issue_title'),
        'user_id' => (int) $row->getValue('user_id'),
        'action' => $row->getValue('action'),
        'field' => $row->getValue('field'),
        'old_value' => $row->getValue('old_value'),
        'new_value' => $row->getValue('new_value'),
        'created_at' => new DateTime($row->getValue('created_at')),
    ];
}

// Dashboard ausgeben
$fragment = new rex_fragment();
$fragment->setVar('openIssues', $openIssues);
$fragment->setVar('inProgressIssues', $inProgressIssues);
$fragment->setVar('overdueIssues', $overdueIssues);
$fragment->setVar('closedIssues', $closedIssues);
$fragment->setVar('priorityCounts', $priorityCounts);
$fragment->setVar('recentIssues', $recentIssues);
$fragment->setVar('recentActivities', $recentActivities);
echo $fragment->parse('issue_tracker_dashboard.php');
