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

// Auf-/zugeklappte Bereiche (Seitenleiste, Lesebereich) pro Benutzer merken – kleiner POST mit CSRF-Schutz
if (rex_post('it_ui_state', 'int', 0) === 1) {
    rex_response::cleanOutputBuffers();
    if (!rex_csrf_token::factory('issue_tracker_ui_state')->isValid()) {
        rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
        rex_response::sendContent('csrf');
        exit;
    }
    $key = rex_post('key', 'string', '');
    if ('font_scale' === $key) {
        // Schriftgröße 90–140 % in 10er-Schritten
        $scale = rex_post('value', 'int', 100);
        if (in_array($scale, [90, 100, 110, 120, 130, 140], true)) {
            rex_config::set('issue_tracker', 'font_scale_' . $currentUserId, $scale);
        }
    } elseif (preg_match('/^[a-z_]{1,40}$/', $key)) {
        $state = rex_config::get('issue_tracker', 'sidebar_state_' . $currentUserId, []);
        $state = is_array($state) ? $state : [];
        $state[$key] = rex_post('open', 'int', 0) === 1;
        rex_config::set('issue_tracker', 'sidebar_state_' . $currentUserId, $state);
    }
    rex_response::sendContent('ok');
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
            'filter_overdue' => rex_post('filter_overdue', 'int', 0),
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
$filterKeys = ['filter_status' => 'string', 'filter_category' => 'string', 'filter_tag' => 'int', 'filter_created_by' => 'int', 'filter_assigned' => 'int',
    'filter_project' => 'int', 'filter_watched' => 'int', 'filter_mentioned' => 'int', 'filter_overdue' => 'int', 'search' => 'string'];
// Zuletzt genutztes Postfach pro Benutzer (wird beim nächsten Öffnen wiederhergestellt)
$lastBoxKey = 'last_box_' . $currentUserId;

// Reset-Button
if (rex_request('reset_filter', 'int', 0) === 1) {
    rex_set_session($sessionKey, null);
    rex_config::remove('issue_tracker', $lastBoxKey);
    // Redirect ohne Parameter
    header('Location: ' . rex_url::backendPage('issue_tracker/issues/list', ['screen' => 'list'], false));
    exit;
}

// Bereich: Themen (Standard) oder Nachrichten (box=inbox|sent)
$box = rex_request('box', 'string', '');
$box = in_array($box, ['inbox', 'sent'], true) ? $box : '';

// Filter aus Request oder Session laden
$hasFilterParams = false;
foreach ($filterKeys as $key => $type) {
    if (rex_request($key, $type, null) !== null) {
        $hasFilterParams = true;
    }
}
$isFreshVisit = !$hasFilterParams && $box === '' && $_POST === []
    && rex_request('issue_id', 'int', 0) === 0 && rex_request('screen', 'string', '') === '' && rex_request('func', 'string', '') === ''
    && rex_request('view_mode', 'string', '') === '' && rex_request('sort', 'string', '') === '' && rex_request('show', 'int', 0) === 0;
$lastBox = rex_config::get('issue_tracker', $lastBoxKey, null);
if ($isFreshVisit && is_array($lastBox)) {
    if (in_array($lastBox['box'] ?? '', ['inbox', 'sent'], true)) {
        $box = $lastBox['box'];
    } elseif (rex_session($sessionKey, 'array', []) === [] && is_array($lastBox['filters'] ?? null)) {
        rex_set_session($sessionKey, $lastBox['filters']);
    }
}

$filterState = [];
if ($hasFilterParams) {
    // Filter aus Request - in Session speichern
    foreach ($filterKeys as $key => $type) {
        $filterState[$key] = rex_request($key, $type, 'int' === $type ? 0 : '');
    }
    rex_set_session($sessionKey, $filterState);
} else {
    // Filter aus Session laden
    $sessionFilter = rex_session($sessionKey, 'array', []);
    foreach ($filterKeys as $key => $type) {
        $filterState[$key] = 'int' === $type ? (int) ($sessionFilter[$key] ?? 0) : (string) ($sessionFilter[$key] ?? '');
    }
}
if ($box !== '') {
    rex_config::set('issue_tracker', $lastBoxKey, ['box' => $box]);
} elseif ($hasFilterParams) {
    rex_config::set('issue_tracker', $lastBoxKey, ['box' => '', 'filters' => $filterState]);
}
$filterStatus = $filterState['filter_status'];
$filterCategory = $filterState['filter_category'];
$filterTag = $filterState['filter_tag'];
$filterCreatedBy = $filterState['filter_created_by'];
$filterAssigned = $filterState['filter_assigned'];
$filterProject = $filterState['filter_project'];
$filterWatched = $filterState['filter_watched'];
$filterMentioned = $filterState['filter_mentioned'];
$filterOverdue = $filterState['filter_overdue'];
$searchTerm = $filterState['search'];

// Modus: Themen, Projekt-Ordner, Wissen (Status „Info“) oder Nachrichten
$mode = 'issues';
if ($box !== '') {
    $mode = 'messages';
} elseif ($filterProject > 0) {
    $mode = 'project';
} elseif ($filterStatus === 'info') {
    $mode = 'knowledge';
}

// Bildschirm auf schmalen Geräten: Postfächer → Liste → Detail (wie Mail auf dem iPhone)
$screen = 'mailboxes';
if ($box !== '' || $hasFilterParams || rex_request('screen', 'string', '') === 'list' || rex_request('show', 'int', 0) > 0 || rex_request('sort', 'string', '') !== '' || rex_request('view_mode', 'string', '') !== '') {
    $screen = 'list';
}

// ---------- Nachrichten im selben Fenster ----------
if ($mode === 'messages') {
    // Gelesen/ungelesen und „Alle als gelesen“ (POST mit CSRF-Schutz)
    $msgAction = rex_post('msg_action', 'string', '');
    if ($msgAction !== '') {
        if (!rex_csrf_token::factory('issue_tracker_messages')->isValid()) {
            echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
        } elseif ($msgAction === 'all_read') {
            \FriendsOfREDAXO\IssueTracker\Message::markAllAsRead($currentUserId);
            rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', ['box' => $box], false));
        } elseif ($msgAction === 'toggle_read') {
            $toggleMessage = \FriendsOfREDAXO\IssueTracker\Message::get(rex_post('message_id', 'int', 0));
            if (!$toggleMessage || $toggleMessage->getRecipientId() !== $currentUserId) {
                echo rex_view::error($package->i18n('issue_tracker_no_permission'));
            } elseif ($toggleMessage->isRead()) {
                \FriendsOfREDAXO\IssueTracker\Message::markAsUnread($toggleMessage->getId(), $currentUserId);
                // Ohne Auswahl zurück, sonst würde das Öffnen sie gleich wieder als gelesen markieren
                rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', ['box' => $box, 'screen' => 'list'], false));
            } else {
                $toggleMessage->markAsRead();
                rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', ['box' => $box, 'message_id' => $toggleMessage->getId()], false));
            }
        }
    }

    $func = rex_request('func', 'string');
    if ($func === 'delete' && rex_request('message_id', 'int', 0) > 0) {
        $delMessage = \FriendsOfREDAXO\IssueTracker\Message::get(rex_request('message_id', 'int', 0));
        if ($delMessage && $delMessage->hasAccess($currentUserId)) {
            if ($delMessage->delete($currentUserId)) {
                echo rex_view::success($package->i18n('issue_tracker_message_deleted'));
            }
        } else {
            echo rex_view::error($package->i18n('issue_tracker_no_permission'));
        }
    }

    $selectedMessageId = $func === 'delete' ? 0 : rex_request('message_id', 'int', 0);
    $paneHtml = '';
    if ($selectedMessageId > 0) {
        $selMessage = \FriendsOfREDAXO\IssueTracker\Message::get($selectedMessageId);
        if (!$selMessage) {
            $paneHtml = rex_view::error($package->i18n('issue_tracker_message_not_found'));
        } elseif (!$selMessage->hasAccess($currentUserId)) {
            $paneHtml = rex_view::error($package->i18n('issue_tracker_no_permission'));
        } else {
            $partnerId = $selMessage->getSenderId() === $currentUserId ? $selMessage->getRecipientId() : $selMessage->getSenderId();
            $conversation = \FriendsOfREDAXO\IssueTracker\Message::getConversation($currentUserId, $partnerId);
            foreach ($conversation as $convMessage) {
                if ($convMessage->getRecipientId() === $currentUserId && !$convMessage->isRead()) {
                    $convMessage->markAsRead();
                }
            }
            $paneFragment = new rex_fragment();
            $paneFragment->setVar('message', $selMessage);
            $paneFragment->setVar('conversation', $conversation);
            $paneFragment->setVar('partnerId', $partnerId);
            $paneFragment->setVar('embedded', true);
            $paneFragment->setVar('box', $box);
            $paneHtml = $paneFragment->parse('issue_tracker_message_view.php');
        }
    }

    $messages = $box === 'inbox' ? \FriendsOfREDAXO\IssueTracker\Message::getInbox($currentUserId) : \FriendsOfREDAXO\IssueTracker\Message::getSent($currentUserId);
    // Suche in Betreff und Text (vorhandene Daten, kein eigener Index)
    $messageSearch = trim(rex_request('msearch', 'string', ''));
    if ($messageSearch !== '') {
        $needle = mb_strtolower($messageSearch);
        $messages = array_values(array_filter($messages, static fn ($m) => str_contains(mb_strtolower($m->getSubject() . ' ' . $m->getMessage() . ' ' . $m->getSenderName() . ' ' . $m->getRecipientName()), $needle)));
    }

    $fragment = new rex_fragment();
    $fragment->setVar('messages', $messages);
    $fragment->setVar('type', $box);
    $fragment->setVar('selectedMessageId', $selectedMessageId);
    $fragment->setVar('paneHtml', $paneHtml, false);
    $fragment->setVar('messageSearch', $messageSearch, false);
    $fragment->setVar('filterState', $filterState, false);
    $fragment->setVar('screen', $screen);
    echo $fragment->parse('issue_tracker_message_list.php');
    return;
}

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
    $renderPane = static function (string $itMode): string {
        $itEmbedded = true;
        ob_start();
        include __DIR__ . '/issues.view.php';
        return (string) ob_get_clean();
    };
    $paneHtml = $renderPane($mode);

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
// Wissen: standardmäßig alphabetisch nach Titel
$sortColumn = rex_request('sort', 'string', $mode === 'knowledge' ? 'title' : 'created_at');
$sortOrder = rex_request('order', 'string', $mode === 'knowledge' && rex_request('sort', 'string', '') === '' ? 'asc' : 'desc');

// Erlaubte Sortier-Spalten
$allowedSortColumns = ['id', 'title', 'category', 'status', 'priority', 'assigned_user_id', 'due_date', 'created_at', 'updated_at'];
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
if ($filterOverdue === 1) {
    $where[] = "i.due_date IS NOT NULL AND i.due_date < NOW() AND i.status != 'closed'";
}

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
$sidebar->setVar('mode', $mode);
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
$list->setVar('mode', $mode);
$list->setVar('screen', $screen);
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

$rootClass = 'it-mail' . ($viewMode === 'table' ? ' it-mail--table' : '') . ($selectedIssueId > 0 ? ' it-mail--sel' : '') . ($screen === 'list' ? ' it-mail--list' : '');
?>
<div class="<?= $rootClass ?>" data-it-mail data-mode="<?= $mode ?>" data-mention-users="<?= rex_escape(json_encode($mentionUsers)) ?>"
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
