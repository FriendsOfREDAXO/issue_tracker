<?php

/**
 * Wissen / Knowledge Base
 *
 * Sammelt alle Themen mit Status "info" durchsuchbar und nach Kategorie
 * gruppiert an einem Ort, statt sie in der normalen Ticket-Liste
 * zwischen offenen Themen verschwinden zu lassen.
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\PermissionService;
use FriendsOfREDAXO\IssueTracker\Tag;

$package = rex_addon::get('issue_tracker');

$searchTerm = rex_request('search', 'string', '');
$filterCategory = rex_request('filter_category', 'string', '');
$filterTag = rex_request('filter_tag', 'int', 0);

// Kategorien für Filter laden
$settingsSql = rex_sql::factory();
$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "categories"');
$categoriesValue = $settingsSql->getRows() > 0 ? $settingsSql->getValue('setting_value') : null;
$categories = is_string($categoriesValue) ? (json_decode($categoriesValue, true) ?: []) : [];

$allTags = Tag::getAll();

// Query aufbauen: immer nur Status "info"
$where = ["i.status = 'info'"];
$joins = '';
$escapeSql = rex_sql::factory();

if ($filterCategory !== '') {
    $where[] = 'i.category = ' . $escapeSql->escape($filterCategory);
}

if ($filterTag > 0) {
    $joins .= ' INNER JOIN ' . rex::getTable('issue_tracker_issue_tags') . ' it ON i.id = it.issue_id';
    $where[] = 'it.tag_id = ' . (int) $filterTag;
}

if ($searchTerm !== '') {
    $escapedTerm = trim($escapeSql->escape($searchTerm), "'");
    $where[] = "(i.title LIKE '%" . $escapedTerm . "%' OR i.description LIKE '%" . $escapedTerm . "%')";
}

// Private Themen nur für Ersteller und Admins
if (!PermissionService::isAdmin()) {
    $where[] = '(i.is_private = 0 OR i.created_by = ' . PermissionService::getUserId() . ')';
}

$query = 'SELECT DISTINCT i.id FROM ' . rex::getTable('issue_tracker_issues') . ' i' . $joins
    . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY i.category ASC, i.title ASC';

$sql = rex_sql::factory();
$sql->setQuery($query);

$articles = [];
foreach ($sql as $row) {
    $article = Issue::get((int) $row->getValue('id'));
    if ($article) {
        $articles[] = $article;
    }
}

// Nach Kategorie gruppieren (Reihenfolge bereits durch SQL sortiert).
// Eine leere Kategorie landet unter dem Schlüssel '' und wird im Fragment
// als "Ohne Kategorie" beschriftet.
$grouped = [];
foreach ($articles as $article) {
    $grouped[$article->getCategory()][] = $article;
}

$fragment = new rex_fragment();
$fragment->setVar('grouped', $grouped);
$fragment->setVar('totalCount', count($articles));
$fragment->setVar('categories', $categories);
$fragment->setVar('allTags', $allTags);
$fragment->setVar('searchTerm', $searchTerm);
$fragment->setVar('filterCategory', $filterCategory);
$fragment->setVar('filterTag', $filterTag);
$fragment->setVar('canCreate', PermissionService::isAdminOrManager());
echo $fragment->parse('issue_tracker_knowledge_list.php');
