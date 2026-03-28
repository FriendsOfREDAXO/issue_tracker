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
$isManager = \FriendsOfREDAXO\IssueTracker\PermissionService::isAdminOrManager();

// Filter: Manager sehen standardmäßig alle Issues, Normal User nur eigene
$viewType = rex_request('view', 'string', $isManager ? 'all' : 'own');
if (!$isManager) {
    $viewType = 'own'; // Force own view for non-managers
}

if ($viewType === 'all' && $isManager) {
    // Alle Issues für Manager
    $filterCondition = '';
} else {
    // Nur eigene Issues (erstellt oder zugewiesen)
    $filterCondition = ' AND (created_by = ' . $userId . ' OR assigned_user_id = ' . $userId . ')';
}

// Offene Issues
$sql->setQuery('SELECT COUNT(*) as cnt FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE status = "open"' . $filterCondition);
$openIssues = (int) $sql->getValue('cnt');

// In Bearbeitung
$sql->setQuery('SELECT COUNT(*) as cnt FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE status = "in_progress"' . $filterCondition);
$inProgressIssues = (int) $sql->getValue('cnt');

// Überfällige Issues
$sql->setQuery('
    SELECT COUNT(*) as cnt 
    FROM ' . rex::getTable('issue_tracker_issues') . ' 
    WHERE due_date < NOW()
    AND status NOT IN ("closed", "rejected")' . $filterCondition . '
');
$overdueIssues = (int) $sql->getValue('cnt');

// Erledigte Issues (letzte 30 Tage)
$sql->setQuery('
    SELECT COUNT(*) as cnt 
    FROM ' . rex::getTable('issue_tracker_issues') . ' 
    WHERE status = "closed" 
    AND closed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)' . $filterCondition . '
');
$closedIssues = (int) $sql->getValue('cnt');

// Issues nach Priorität (nicht geschlossen)
$sql->setQuery('
    SELECT priority, COUNT(*) as cnt 
    FROM ' . rex::getTable('issue_tracker_issues') . ' 
    WHERE status NOT IN ("closed", "rejected")' . $filterCondition . '
    GROUP BY priority
    ORDER BY FIELD(priority, "critical", "high", "normal", "low")
');
$priorityCounts = [];
foreach ($sql as $row) {
    $priorityCounts[$row->getValue('priority')] = (int) $row->getValue('cnt');
}

// Neueste Issues
$sql->setQuery('
    SELECT * 
    FROM ' . rex::getTable('issue_tracker_issues') . ' 
    WHERE 1=1' . $filterCondition . '
    ORDER BY created_at DESC 
    LIMIT 5
');
$recentIssues = [];
foreach ($sql as $row) {
    $recentIssues[] = \FriendsOfREDAXO\IssueTracker\Issue::get((int) $row->getValue('id'));
}

// Letzte Aktivitäten
$sql->setQuery('
    SELECT h.*, i.title as issue_title
    FROM ' . rex::getTable('issue_tracker_history') . ' h
    JOIN ' . rex::getTable('issue_tracker_issues') . ' i ON h.issue_id = i.id
    WHERE 1=1' . $filterCondition . '
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

// Nachrichten-Statistiken
$unreadMessages = \FriendsOfREDAXO\IssueTracker\Message::getUnreadCount($userId);
$recentMessages = \FriendsOfREDAXO\IssueTracker\Message::getInbox($userId, 5);

// Projekte laden (je nach Berechtigung)
if ($currentUser->isAdmin()) {
    // Admins sehen alle aktiven Projekte
    $userProjects = \FriendsOfREDAXO\IssueTracker\Project::getAll(null, 'active');
} else {
    // Normale User sehen nur ihre Projekte
    $userProjects = \FriendsOfREDAXO\IssueTracker\Project::getByUser($userId);
}

// Beobachtete Issues laden
$watchedIssues = [];
$watchedIds = \FriendsOfREDAXO\IssueTracker\NotificationService::getWatchedIssues($userId);
foreach ($watchedIds as $wIssue) {
    $watchedIssues[] = $wIssue;
}

// Erwähnung als gelesen markieren (nur via POST, PRG-Pattern)
if (rex_post('mark_mention_read', 'int', 0) > 0) {
    $mentionId = rex_post('mark_mention_read', 'int', 0);
    $markSql = rex_sql::factory();
    $markSql->setQuery(
        'UPDATE ' . rex::getTable('issue_tracker_mentions') . ' SET read_at = ? WHERE id = ? AND mentioned_user_id = ? AND read_at IS NULL',
        [date('Y-m-d H:i:s'), $mentionId, $userId]
    );
    rex_response::sendRedirect(rex_url::currentBackendPage());
}

// Alle Erwähnungen als gelesen markieren (nur via POST, PRG-Pattern)
if (rex_post('mark_all_mentions_read', 'int', 0) === 1) {
    $markSql = rex_sql::factory();
    $markSql->setQuery(
        'UPDATE ' . rex::getTable('issue_tracker_mentions') . ' SET read_at = ? WHERE mentioned_user_id = ? AND read_at IS NULL',
        [date('Y-m-d H:i:s'), $userId]
    );
    rex_response::sendRedirect(rex_url::currentBackendPage());
}

// Letzte Erwähnungen laden (max. 10, ungelesene zuerst)
$mentionSql = rex_sql::factory();
$mentionSql->setQuery('
    SELECT m.id, m.issue_id, m.comment_id, m.created_at, m.read_at,
           u.name AS mentioner_name,
           u.login AS mentioner_login,
           i.title AS issue_title
    FROM ' . rex::getTable('issue_tracker_mentions') . ' m
    INNER JOIN ' . rex::getTable('user') . ' u ON u.id = m.created_by
    INNER JOIN ' . rex::getTable('issue_tracker_issues') . ' i ON i.id = m.issue_id
    WHERE m.mentioned_user_id = ' . $userId . '
    AND m.read_at IS NULL
    ORDER BY m.created_at DESC
    LIMIT 10
');
$recentMentions = $mentionSql->getArray();

// Exakte Anzahl ungelesener Erwähnungen per eigener Abfrage (unabhängig vom LIMIT)
$countSql = rex_sql::factory();
$countSql->setQuery(
    'SELECT COUNT(*) AS cnt FROM ' . rex::getTable('issue_tracker_mentions') . ' WHERE mentioned_user_id = ? AND read_at IS NULL',
    [$userId]
);
$unreadMentionsCount = (int) $countSql->getValue('cnt');

// Dashboard ausgeben
$fragment = new rex_fragment();
$fragment->setVar('openIssues', $openIssues);
$fragment->setVar('inProgressIssues', $inProgressIssues);
$fragment->setVar('overdueIssues', $overdueIssues);
$fragment->setVar('closedIssues', $closedIssues);
$fragment->setVar('priorityCounts', $priorityCounts);
$fragment->setVar('recentIssues', $recentIssues);
$fragment->setVar('recentActivities', $recentActivities);
$fragment->setVar('unreadMessages', $unreadMessages);
$fragment->setVar('recentMessages', $recentMessages);
$fragment->setVar('userProjects', $userProjects);
$fragment->setVar('watchedIssues', $watchedIssues);
$fragment->setVar('recentMentions', $recentMentions);
$fragment->setVar('unreadMentionsCount', $unreadMentionsCount);
$fragment->setVar('isManager', $isManager);
$fragment->setVar('currentViewType', $viewType);
echo $fragment->parse('issue_tracker_dashboard.php');
