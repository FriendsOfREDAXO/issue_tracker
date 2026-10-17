<?php

/**
 * Issues Liste – Postfach-Ansicht (wie Mail) oder klassische Tabelle
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\PermissionService;

$package = rex_addon::get('issue_tracker');
$currentUserId = (int) rex::getUser()->getId();

// PDF-Export generieren falls angefordert
if (rex_request('export_pdf', 'int', 0) === 1) {
    if (!\FriendsOfREDAXO\IssueTracker\PermissionService::isLoggedIn()) {
        exit;
    }

    if (!\rex_addon::exists('pdfout') || !\rex_addon::get('pdfout')->isAvailable()) {
        exit;
    }

    // PDF-Export Code wird unten eingefügt
    require_once rex_path::addon('issue_tracker', 'lib/pdf_export.php');
    exit;
}

// Ansicht (Postfach | Tabelle) pro Benutzer merken
$viewModeKey = 'view_mode_' . $currentUserId;
$requestedViewMode = rex_request('view_mode', 'string', '');
if (in_array($requestedViewMode, ['mail', 'table'], true)) {
    rex_config::set('issue_tracker', $viewModeKey, $requestedViewMode);
}
$viewMode = (string) rex_config::get('issue_tracker', $viewModeKey, 'mail');
if (!in_array($viewMode, ['mail', 'table'], true)) {
    $viewMode = 'mail';
}

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
            'filter_assigned' => rex_post('filter_assigned', 'int', 0),
            'filter_project' => rex_post('filter_project', 'int', 0),
            'filter_watched' => rex_post('filter_watched', 'int', 0),
            'filter_mentioned' => rex_post('filter_mentioned', 'int', 0),
            'search' => rex_post('search', 'string', ''),
        ];

        // Leere Filter entfernen
        $filters = array_filter($filters, function ($value) {
            return $value !== '' && $value !== 0;
        });

        $filterId = \FriendsOfREDAXO\IssueTracker\SavedFilterService::save(
            $currentUserId,
            $filterName,
            $filters,
            $isDefault
        );

        echo rex_view::success($package->i18n('issue_tracker_filter_saved'));
    }
}

// Filter löschen
if (rex_post('delete_filter', 'int', 0) > 0) {
    $filterId = rex_post('delete_filter', 'int', 0);
    \FriendsOfREDAXO\IssueTracker\SavedFilterService::delete($filterId, $currentUserId);
    echo rex_view::success($package->i18n('issue_tracker_filter_deleted'));
}

// Filter als Default setzen
if (rex_request('set_default_filter', 'int', 0) > 0) {
    $filterId = rex_request('set_default_filter', 'int', 0);
    \FriendsOfREDAXO\IssueTracker\SavedFilterService::setDefault($filterId, $currentUserId);
    echo rex_view::success($package->i18n('issue_tracker_filter_saved'));
}

// Session-Key für Filter
$sessionKey = 'issue_tracker_filter';

// Reset-Button
if (rex_request('reset_filter', 'int', 0) === 1) {
    rex_set_session($sessionKey, null);
    // Redirect ohne Parameter
    header('Location: ' . rex_url::backendPage('issue_tracker/issues/list', [], false));
    exit;
}

// Filter aus Request oder Session laden
$hasFilterParams = rex_request('filter_status', 'string', null) !== null
                || rex_request('filter_category', 'string', null) !== null
                || rex_request('filter_tag', 'int', null) !== null
                || rex_request('filter_created_by', 'int', null) !== null
                || rex_request('filter_assigned', 'int', null) !== null
                || rex_request('filter_project', 'int', null) !== null
                || rex_request('filter_watched', 'int', null) !== null
                || rex_request('filter_mentioned', 'int', null) !== null
                || rex_request('search', 'string', null) !== null;

if ($hasFilterParams) {
    // Filter aus Request - in Session speichern
    $filterStatus = rex_request('filter_status', 'string', '');
    $filterCategory = rex_request('filter_category', 'string', '');
    $filterTag = rex_request('filter_tag', 'int', 0);
    $filterCreatedBy = rex_request('filter_created_by', 'int', 0);
    $filterAssigned = rex_request('filter_assigned', 'int', 0);
    $filterProject = rex_request('filter_project', 'int', 0);
    $filterWatched = rex_request('filter_watched', 'int', 0);
    $filterMentioned = rex_request('filter_mentioned', 'int', 0);
    $searchTerm = rex_request('search', 'string', '');

    rex_set_session($sessionKey, [
        'filter_status' => $filterStatus,
        'filter_category' => $filterCategory,
        'filter_tag' => $filterTag,
        'filter_created_by' => $filterCreatedBy,
        'filter_assigned' => $filterAssigned,
        'filter_project' => $filterProject,
        'filter_watched' => $filterWatched,
        'filter_mentioned' => $filterMentioned,
        'search' => $searchTerm,
    ]);
} else {
    // Filter aus Session laden
    $sessionFilter = rex_session($sessionKey, 'array', []);
    $filterStatus = (string) ($sessionFilter['filter_status'] ?? '');
    $filterCategory = (string) ($sessionFilter['filter_category'] ?? '');
    $filterTag = (int) ($sessionFilter['filter_tag'] ?? 0);
    $filterCreatedBy = (int) ($sessionFilter['filter_created_by'] ?? 0);
    $filterAssigned = (int) ($sessionFilter['filter_assigned'] ?? 0);
    $filterProject = (int) ($sessionFilter['filter_project'] ?? 0);
    $filterWatched = (int) ($sessionFilter['filter_watched'] ?? 0);
    $filterMentioned = (int) ($sessionFilter['filter_mentioned'] ?? 0);
    $searchTerm = (string) ($sessionFilter['search'] ?? '');
}

$filterState = [
    'filter_status' => $filterStatus,
    'filter_category' => $filterCategory,
    'filter_tag' => $filterTag,
    'filter_created_by' => $filterCreatedBy,
    'filter_assigned' => $filterAssigned,
    'filter_project' => $filterProject,
    'filter_watched' => $filterWatched,
    'filter_mentioned' => $filterMentioned,
    'search' => $searchTerm,
];

// Löschaktion (Admin oder Issue-Manager)
$func = rex_request('func', 'string', '');

if ($func === 'delete' && PermissionService::canDelete()) {
    $issueId = rex_request('issue_id', 'int', 0);
    $issue = Issue::get($issueId);

    if ($issue) {
        $issue->delete();
        echo rex_view::success($package->i18n('issue_tracker_issue_deleted'));
    }
}

// Lesebereich (Postfach-Ansicht): ausgewähltes Thema serverseitig rendern.
// Läuft vor der Listenabfrage, damit Aktionen (Status, Kommentar …) sofort in der Liste sichtbar sind.
$selectedIssueId = ($viewMode === 'mail' && $func !== 'delete') ? rex_request('issue_id', 'int', 0) : 0;
$paneHtml = '';
if ($selectedIssueId > 0) {
    $renderPane = static function (): string {
        $itEmbedded = true;
        ob_start();
        include __DIR__ . '/issues.view.php';
        return (string) ob_get_clean();
    };
    $paneHtml = $renderPane();

    // Nur den Lesebereich ausliefern (Tastatur-/Klick-Navigation per it-mail.js)
    if (rex_request('it_pane', 'int', 0) === 1) {
        rex_response::cleanOutputBuffers();
        rex_response::sendContent($paneHtml);
        exit;
    }
}

// Kategorien und Status für Filter
$settingsSql = rex_sql::factory();
$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "categories"');
$categories = $settingsSql->getRows() > 0 ? (json_decode((string) $settingsSql->getValue('setting_value'), true) ?: []) : [];

$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
$statuses = $settingsSql->getRows() > 0 ? (json_decode((string) $settingsSql->getValue('setting_value'), true) ?: []) : [];

$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "priorities"');
$priorities = $settingsSql->getRows() > 0 ? (json_decode((string) $settingsSql->getValue('setting_value'), true) ?: []) : [];

// Alle Tags laden
$allTags = \FriendsOfREDAXO\IssueTracker\Tag::getAll();

// Alle Benutzer für Filter laden
$usersSql = rex_sql::factory();
$usersSql->setQuery('SELECT id, name, login FROM ' . rex::getTable('user') . ' ORDER BY name');
$users = [];
foreach ($usersSql as $row) {
    $userId = (int) $row->getValue('id');
    $login = trim((string) $row->getValue('login'));
    $displayName = trim((string) $row->getValue('name'));

    if ('' === $displayName) {
        $displayName = '' !== $login ? $login : 'User #' . $userId;
    }

    $users[$userId] = $displayName;
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

$escapeSql = rex_sql::factory();

// Status und Priorität in fachlicher Reihenfolge sortieren (nicht alphabetisch)
$orderExpr = 'i.' . $sortColumn;
if ($sortColumn === 'priority') {
    $orderExpr = "FIELD(i.priority, 'low', 'normal', 'high', 'urgent', 'critical')";
} elseif ($sortColumn === 'status' && $statuses !== []) {
    $orderExpr = 'FIELD(i.status, ' . implode(', ', array_map(static fn ($s) => $escapeSql->escape((string) $s), array_keys($statuses))) . ')';
}

// Query für Issues aufbauen
$where = [];
$joins = '';

if ($filterStatus === '_all_') {
    // Alle Issues anzeigen, kein Status-Filter
} elseif ($filterStatus !== '') {
    $where[] = 'i.status = ' . $escapeSql->escape($filterStatus);
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

if ($filterAssigned > 0) {
    $where[] = 'i.assigned_user_id = ' . (int) $filterAssigned;
}

if ($filterProject > 0) {
    $where[] = 'i.project_id = ' . (int) $filterProject;
}

if ($filterWatched === 1) {
    $joins .= ' INNER JOIN ' . rex::getTable('issue_tracker_watchers') . ' w ON i.id = w.issue_id AND w.user_id = ' . $currentUserId;
}

// Filter: Nur Issues in denen der aktuelle User erwähnt wurde
if ($filterMentioned === 1) {
    $joins .= ' INNER JOIN ' . rex::getTable('issue_tracker_mentions') . ' im ON i.id = im.issue_id';
    $where[] = 'im.mentioned_user_id = ' . (int) PermissionService::getUserId();
}

if ($searchTerm !== '') {
    $escapedTerm = $escapeSql->escape($searchTerm);
    // escape() fügt Anführungszeichen hinzu, für LIKE müssen wir sie entfernen
    $escapedTermNoQuotes = trim($escapedTerm, "'");
    $where[] = "(i.title LIKE '%" . $escapedTermNoQuotes . "%' OR i.description LIKE '%" . $escapedTermNoQuotes . "%')";
}

// Filter für private Issues: Nur Ersteller und Admins können private Issues sehen
if (!PermissionService::isAdmin()) {
    $where[] = '(i.is_private = 0 OR i.created_by = ' . PermissionService::getUserId() . ')';
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

// Gesamtzahl
$countSql = rex_sql::factory();
$countSql->setQuery('SELECT COUNT(DISTINCT i.id) AS cnt FROM ' . rex::getTable('issue_tracker_issues') . ' i' . $joins . $whereClause);
$totalIssues = (int) $countSql->getValue('cnt');

// Postfach: 50 Einträge, „Weitere laden“ erhöht serverseitig; Tabelle: alle wie bisher
$pageSize = 50;
$showCount = max($pageSize, rex_request('show', 'int', $pageSize));
$limit = $viewMode === 'mail' ? ' LIMIT ' . (int) $showCount : '';

$query = 'SELECT DISTINCT i.* FROM ' . rex::getTable('issue_tracker_issues') . ' i' . $joins . $whereClause
    . ' ORDER BY ' . $orderExpr . ' ' . $sortOrder . ', i.id ' . $sortOrder . $limit;

// Issues laden
$sql = rex_sql::factory();
$sql->setQuery($query);

$issues = [];
foreach ($sql as $row) {
    $loaded = \FriendsOfREDAXO\IssueTracker\Issue::get((int) $row->getValue('id'));
    if ($loaded) {
        $issues[] = $loaded;
    }
}

// Beobachtete Issue-IDs des aktuellen Users laden
$watchedIds = [];
$watchSql = rex_sql::factory();
$watchSql->setQuery(
    'SELECT issue_id FROM ' . rex::getTable('issue_tracker_watchers') . ' WHERE user_id = ?',
    [$currentUserId]
);
foreach ($watchSql as $row) {
    $watchedIds[] = (int) $row->getValue('issue_id');
}

// Ungelesene Erwähnungen des aktuellen Users (blauer Punkt wie „ungelesen“ in Mail)
$unreadMentionIds = [];
$mentionSql = rex_sql::factory();
$mentionSql->setQuery(
    'SELECT DISTINCT issue_id FROM ' . rex::getTable('issue_tracker_mentions') . ' WHERE mentioned_user_id = ? AND read_at IS NULL',
    [$currentUserId]
);
foreach ($mentionSql as $row) {
    $unreadMentionIds[] = (int) $row->getValue('issue_id');
}

// Postfach-Seitenleiste
$sidebar = new rex_fragment();
$sidebar->setVar('filterState', $filterState, false);
$sidebar->setVar('statuses', $statuses, false);
$sidebar->setVar('categories', $categories, false);
$sidebar->setVar('allTags', $allTags);
$sidebar->setVar('users', $users, false);

// Liste
$list = new rex_fragment();
$list->setVar('issues', $issues);
$list->setVar('totalIssues', $totalIssues);
$list->setVar('showCount', $showCount);
$list->setVar('pageSize', $pageSize);
$list->setVar('sortColumn', $sortColumn);
$list->setVar('sortOrder', $sortOrder);
$list->setVar('watchedIds', $watchedIds);
$list->setVar('unreadMentionIds', $unreadMentionIds);
$list->setVar('selectedIssueId', $selectedIssueId);
$list->setVar('viewMode', $viewMode);
$list->setVar('filterState', $filterState, false);
$list->setVar('statuses', $statuses, false);
$list->setVar('priorities', $priorities, false);
$list->setVar('categories', $categories, false);
$list->setVar('allTags', $allTags);
$list->setVar('users', $users, false);

// Benutzer für @-Erwähnungen (auch für nachgeladene Lesebereiche)
$mentionUsers = [];
$mentionUsersSql = rex_sql::factory();
$mentionUsersSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE status = 1');
foreach ($mentionUsersSql as $row) {
    $u = rex_user::get((int) $row->getValue('id'));
    if ($u && ($u->isAdmin() || $u->hasPerm('issue_tracker[]') || $u->hasPerm('issue_tracker[issuer]') || $u->hasPerm('issue_tracker[issue_manager]'))) {
        $mentionUsers[] = ['name' => $u->getName(), 'login' => $u->getLogin()];
    }
}

$rootClass = 'it-mail' . ($viewMode === 'table' ? ' it-mail--table' : '') . ($selectedIssueId > 0 ? ' it-mail--sel' : '');
?>
<div class="<?= $rootClass ?>" data-it-mail data-mention-users="<?= rex_escape(json_encode($mentionUsers)) ?>"
     data-pane-url="<?= rex_escape(rex_url::backendPage('issue_tracker/issues/list', ['it_pane' => 1], false)) ?>"
     data-list-url="<?= rex_escape(rex_url::backendPage('issue_tracker/issues/list', [], false)) ?>"
     data-view-url="<?= rex_escape(rex_url::backendPage('issue_tracker/issues/view', [], false)) ?>">
    <?= $sidebar->parse('issue_tracker_filter.php') ?>
    <?= $list->parse('issue_tracker_list.php') ?>
    <?php if ($viewMode === 'mail'): ?>
    <section class="it-rp-wrap" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_reading_pane')) ?>" data-it-pane>
        <?php if ($paneHtml !== ''): ?>
            <?= $paneHtml ?>
        <?php else: ?>
            <div class="it-rp-empty">
                <i class="rex-icon fa-envelope-open-o" aria-hidden="true"></i>
                <p><?= $package->i18n('issue_tracker_mail_no_selection') ?></p>
                <small><?= $package->i18n('issue_tracker_mail_no_selection_hint') ?></small>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
