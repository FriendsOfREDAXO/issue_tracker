<?php

/**
 * Issues Liste
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Issue;

$package = rex_addon::get('issue_tracker');

// Filter speichern
if (rex_post('save_filter', 'int', 0) === 1) {
    $filterName = rex_post('filter_name', 'string', '');
    $isDefault = rex_post('is_default', 'int', 0) === 1;
    
    if ($filterName !== '') {
        $filters = [
            'filter_status' => rex_post('filter_status', 'string', ''),
            'filter_category' => rex_post('filter_category', 'string', ''),
            'filter_tag' => rex_post('filter_tag', 'int', 0),
            'filter_created_by' => rex_post('filter_created_by', 'int', 0),
            'search' => rex_post('search', 'string', ''),
        ];
        
        // Leere Filter entfernen
        $filters = array_filter($filters, function($value) {
            return $value !== '' && $value !== 0;
        });
        
        $filterId = \FriendsOfREDAXO\IssueTracker\SavedFilterService::save(
            rex::getUser()->getId(),
            $filterName,
            $filters,
            $isDefault
        );
        
        echo rex_view::success($package->i18n('issue_tracker_filter_saved'));
    }
}

// Filter löschen
if (rex_request('delete_filter', 'int', 0) > 0) {
    $filterId = rex_request('delete_filter', 'int', 0);
    \FriendsOfREDAXO\IssueTracker\SavedFilterService::delete($filterId, rex::getUser()->getId());
    echo rex_view::success($package->i18n('issue_tracker_filter_deleted'));
}

// Filter als Default setzen
if (rex_request('set_default_filter', 'int', 0) > 0) {
    $filterId = rex_request('set_default_filter', 'int', 0);
    \FriendsOfREDAXO\IssueTracker\SavedFilterService::setDefault($filterId, rex::getUser()->getId());
    echo rex_view::success($package->i18n('issue_tracker_filter_saved'));
}

// Filter
$filterStatus = rex_request('filter_status', 'string', '');
$filterCategory = rex_request('filter_category', 'string', '');
$filterTag = rex_request('filter_tag', 'int', 0);
$filterCreatedBy = rex_request('filter_created_by', 'int', 0);
$searchTerm = rex_request('search', 'string', '');

// Löschaktion
$func = rex_request('func', 'string', '');
if ($func === 'delete' && rex::getUser()->isAdmin()) {
    $issueId = rex_request('issue_id', 'int', 0);
    $issue = Issue::get($issueId);
    
    if ($issue) {
        $issue->delete();
        echo rex_view::success($package->i18n('issue_tracker_issue_deleted'));
    }
}

// Kategorien und Status für Filter
$settingsSql = rex_sql::factory();
$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "categories"');
$categories = $settingsSql->getRows() > 0 ? json_decode($settingsSql->getValue('setting_value'), true) : [];

$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
$statuses = $settingsSql->getRows() > 0 ? json_decode($settingsSql->getValue('setting_value'), true) : [];

// Alle Tags laden
$allTags = \FriendsOfREDAXO\IssueTracker\Tag::getAll();

// Alle Benutzer für Filter laden
$usersSql = rex_sql::factory();
$usersSql->setQuery('SELECT id, name FROM ' . rex::getTable('user') . ' ORDER BY name');
$users = [];
foreach ($usersSql as $row) {
    $users[(int) $row->getValue('id')] = $row->getValue('name');
}

// Sortierung
$sortColumn = rex_request('sort', 'string', 'created_at');
$sortOrder = rex_request('order', 'string', 'desc');

// Erlaubte Sortier-Spalten
$allowedSortColumns = ['id', 'title', 'category', 'status', 'priority', 'assigned_user_id', 'due_date', 'created_at'];
if (!in_array($sortColumn, $allowedSortColumns)) {
    $sortColumn = 'created_at';
}
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Query für Issues aufbauen
$where = [];
$joins = '';
$escapeSql = rex_sql::factory();

if ($filterStatus === '_all_') {
    // Alle Issues anzeigen, kein Status-Filter
} elseif ($filterStatus !== '') {
    $where[] = "i.status = " . $escapeSql->escape($filterStatus);
} else {
    // Standard: Nur aktive Issues (nicht geschlossen)
    $where[] = "i.status != 'closed'";
}

if ($filterCategory !== '') {
    $where[] = 'i.category = ' . $escapeSql->escape($filterCategory);
}

if ($filterTag > 0) {
    $joins .= ' INNER JOIN ' . rex::getTable('issue_tracker_issue_tags') . ' it ON i.id = it.issue_id';
    $where[] = 'it.tag_id = ' . (int) $filterTag;
}

if ($filterCreatedBy > 0) {
    $where[] = 'i.created_by = ' . (int) $filterCreatedBy;
}

if ($searchTerm !== '') {
    $escapedTerm = $escapeSql->escape($searchTerm);
    // escape() fügt Anführungszeichen hinzu, für LIKE müssen wir sie entfernen
    $escapedTermNoQuotes = trim($escapedTerm, "'");
    $where[] = "(i.title LIKE '%" . $escapedTermNoQuotes . "%' OR i.description LIKE '%" . $escapedTermNoQuotes . "%')";
}

// Filter für private Issues: Nur Ersteller und Admins können private Issues sehen
$currentUser = rex::getUser();
if (!$currentUser->isAdmin()) {
    $where[] = '(i.is_private = 0 OR i.created_by = ' . (int) $currentUser->getId() . ')';
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
$query = 'SELECT DISTINCT i.* FROM ' . rex::getTable('issue_tracker_issues') . ' i' . $joins . $whereClause . ' ORDER BY i.' . $sortColumn . ' ' . $sortOrder;

// Issues laden
$sql = rex_sql::factory();
$sql->setQuery($query);

$issues = [];
foreach ($sql as $row) {
    $issues[] = \FriendsOfREDAXO\IssueTracker\Issue::get((int) $row->getValue('id'));
}

// Filter-Panel anzeigen
$fragment = new rex_fragment();
$fragment->setVar('categories', $categories);
$fragment->setVar('statuses', $statuses);
$fragment->setVar('allTags', $allTags);
$fragment->setVar('users', $users);
$fragment->setVar('filterStatus', $filterStatus);
$fragment->setVar('filterCategory', $filterCategory);
$fragment->setVar('filterTag', $filterTag);
$fragment->setVar('filterCreatedBy', $filterCreatedBy);
$fragment->setVar('searchTerm', $searchTerm);
echo $fragment->parse('issue_tracker_filter.php');

// Issues-Liste anzeigen
$fragment = new rex_fragment();
$fragment->setVar('issues', $issues);
$fragment->setVar('sortColumn', $sortColumn);
$fragment->setVar('sortOrder', $sortOrder);
echo $fragment->parse('issue_tracker_list.php');
