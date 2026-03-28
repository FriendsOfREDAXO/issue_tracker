<?php

/**
 * Issue Thread-Ansicht
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\Comment;
use FriendsOfREDAXO\IssueTracker\Attachment;
use FriendsOfREDAXO\IssueTracker\NotificationService;
use FriendsOfREDAXO\IssueTracker\HistoryService;
use FriendsOfREDAXO\IssueTracker\PermissionService;
use FriendsOfREDAXO\IssueTracker\Tag;
use FriendsOfREDAXO\IssueTracker\ContentRenderer;

$package = rex_addon::get('issue_tracker');

$issueId = rex_request('issue_id', 'int', 0);

if ($issueId === 0) {
    echo rex_view::error($package->i18n('issue_tracker_issue_not_found'));
    return;
}

$issue = Issue::get($issueId);

if (!$issue) {
    echo rex_view::error($package->i18n('issue_tracker_issue_not_found'));
    return;
}

// Berechtigungsprüfung für private Issues
if (!PermissionService::canView($issue)) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

// Checklisten-Item umschalten (interaktive Checklisten)
if (rex_post('toggle_checklist', 'int', 0) === 1) {
    $clSource = rex_post('cl_source', 'string', '');
    $clIndex  = rex_post('cl_index', 'int', -1);

    if ($clIndex >= 0 && $clSource !== '') {
        if ($clSource === 'description' && PermissionService::canEdit($issue)) {
            $toggled = ContentRenderer::toggleChecklistItem($issue->getDescription(), $clIndex);
            $issue->setDescription($toggled);
            $issue->save();
        } elseif (str_starts_with($clSource, 'comment_')) {
            $commentId = (int) substr($clSource, 8);
            $clComment = Comment::get($commentId);
            if (
                $clComment !== null
                && $clComment->getIssueId() === $issue->getId()
                && (rex::getUser()->isAdmin() || $clComment->getCreatedBy() === PermissionService::getUserId())
            ) {
                $toggled = ContentRenderer::toggleChecklistItem($clComment->getComment(), $clIndex);
                $clComment->setComment($toggled);
                $clComment->save();
            }
        }
    }

    $redirectUrl = rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]);
    $redirectUrl = html_entity_decode($redirectUrl, ENT_QUOTES, 'UTF-8');
    header('Location: ' . $redirectUrl . '#' . ($clSource === 'description' ? 'description' : 'comment-' . substr($clSource, 8)));
    exit;
}

// Als verwandtes Issue markieren
if (rex_post('func', 'string', '') === 'mark_related' && rex::getUser()->isAdmin()) {
    $relatedToId = rex_post('related_to', 'int', 0);
    
    if ($relatedToId > 0 && $relatedToId !== $issue->getId()) {
        if ($issue->markAsDuplicate($relatedToId, PermissionService::getUserId())) {
            echo rex_view::success($package->i18n('issue_tracker_related_marked'));
            
            // Benachrichtigungen senden
            $relatedIssue = Issue::get($relatedToId);
            if ($relatedIssue) {
                NotificationService::sendDuplicateMarked($issue, $relatedIssue);
            }
            
            // Issue neu laden
            $issue = Issue::get($issueId);
        } else {
            if (Issue::get($relatedToId) === null) {
                echo rex_view::error($package->i18n('issue_tracker_related_not_found'));
            } else {
                echo rex_view::error($package->i18n('issue_tracker_related_error'));
            }
        }
    } elseif ($relatedToId === $issue->getId()) {
        echo rex_view::error($package->i18n('issue_tracker_related_self_reference'));
    } else {
        echo rex_view::error($package->i18n('issue_tracker_related_invalid_id'));
    }
}

// Verknüpfung entfernen
if (rex_post('func', 'string', '') === 'unmark_related' && rex::getUser()->isAdmin()) {
    if ($issue->unmarkAsDuplicate(PermissionService::getUserId())) {
        echo rex_view::success($package->i18n('issue_tracker_related_unmarked'));
        
        // Issue neu laden
        $issue = Issue::get($issueId);
    } else {
        echo rex_view::error($package->i18n('issue_tracker_related_error'));
    }
}

// Kommentar löschen
if (rex_post('delete_comment', 'int', 0) > 0) {
    $commentId = rex_post('delete_comment', 'int', 0);
    $comment = Comment::get($commentId);
    
    if ($comment && $comment->getIssueId() === $issue->getId()) {
        if (PermissionService::canDeleteComment($comment)) {
            if ($comment->delete()) {
                // History-Eintrag
                HistoryService::add(
                    $issue->getId(),
                    PermissionService::getUserId(),
                    'comment_deleted',
                    'comment',
                    null,
                    null
                );
                
                $redirectUrl = rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]);
                $redirectUrl = html_entity_decode($redirectUrl, ENT_QUOTES, 'UTF-8');
                header('Location: ' . $redirectUrl);
                exit;
            }
        }
    }
}

// Kommentar bearbeiten (Ersteller oder Admin)
if (rex_post('edit_comment', 'int', 0) > 0) {
    $commentId = rex_post('edit_comment', 'int', 0);
    $comment = Comment::get($commentId);
    
    if ($comment && $comment->getIssueId() === $issue->getId()) {
        if (PermissionService::canEditComment($comment)) {
            $newText = rex_post('comment_text', 'string', '');
            if ($newText !== '') {
                $comment->setComment($newText);
                if ($comment->save()) {
                    // History-Eintrag
                    HistoryService::add(
                        $issue->getId(),
                        PermissionService::getUserId(),
                        'comment_edited',
                        'comment',
                        null,
                        null
                    );
                    
                    $redirectUrl = rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]);
                    $redirectUrl = html_entity_decode($redirectUrl, ENT_QUOTES, 'UTF-8');
                    $redirectUrl .= '#comment-' . $comment->getId();
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            }
        }
    }
}

// Kommentar pinnen/unpinnen
if (rex_post('toggle_pin', 'int', 0) > 0) {
    $commentId = rex_post('toggle_pin', 'int', 0);
    $comment = Comment::get($commentId);
    
    if ($comment && $comment->getIssueId() === $issue->getId()) {
        if (PermissionService::canModerateComments() || $issue->getCreatedBy() === PermissionService::getUserId()) {
            $comment->setPinned(!$comment->isPinned());
            if ($comment->save()) {
                $message = $comment->isPinned() ? 'Kommentar angepinnt' : 'Pin entfernt';
                echo rex_view::success($message);
            }
        }
    }
}

// Kommentar als Lösung markieren/entfernen
if (rex_post('toggle_solution', 'int', 0) > 0) {
    $commentId = rex_post('toggle_solution', 'int', 0);
    $comment = Comment::get($commentId);
    
    if ($comment && $comment->getIssueId() === $issue->getId()) {
        if (PermissionService::canModerateComments() || $issue->getCreatedBy() === PermissionService::getUserId()) {
            // Nur ein Kommentar kann Lösung sein - andere zurücksetzen
            if (!$comment->isSolution()) {
                $sql = rex_sql::factory();
                $sql->setQuery(
                    'UPDATE ' . rex::getTable('issue_tracker_comments') . 
                    ' SET is_solution = 0 WHERE issue_id = ?',
                    [$comment->getIssueId()]
                );
            }
            
            $comment->setSolution(!$comment->isSolution());
            if ($comment->save()) {
                $message = $comment->isSolution() ? 'Als Lösung markiert' : 'Lösung-Markierung entfernt';
                echo rex_view::success($message);
            }
        }
    }
}

// Tag hinzufügen
if (rex_post('add_tag', 'int', 0) > 0 && PermissionService::canEdit($issue)) {
    $tagId = rex_post('add_tag', 'int', 0);
    $issue->addTag($tagId);
    $issue = Issue::get($issueId);
}

// Tag entfernen
if (rex_post('remove_tag', 'int', 0) > 0 && PermissionService::canEdit($issue)) {
    $tagId = rex_post('remove_tag', 'int', 0);
    $issue->removeTag($tagId);
    $issue = Issue::get($issueId);
}

// Kommentar hinzufügen
if (rex_post('add_comment', 'int', 0) === 1) {
    $commentText = rex_post('comment', 'string', '');
    $parentCommentId = rex_post('parent_comment_id', 'int', 0);
    $closeAndComment = rex_post('close_and_comment', 'int', 0) === 1;
    
    if ($commentText !== '') {
        $comment = new Comment();
        $comment->setIssueId($issue->getId());
        $comment->setCreatedBy(PermissionService::getUserId());
        $comment->setComment($commentText);
        
        if ($parentCommentId > 0) {
            $comment->setParentCommentId($parentCommentId);
        }
        
        if ($comment->save()) {
            // Attachments für Kommentar verarbeiten
            if (!empty($_FILES['comment_attachments']['name'][0])) {
                $uploadedFiles = [];
                $fileCount = count($_FILES['comment_attachments']['name']);
                
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['comment_attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        // Eindeutigen Dateinamen generieren
                        $extension = strtolower(pathinfo($_FILES['comment_attachments']['name'][$i], PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','ico',
                            'mp4','mov','avi','mkv','webm','ogg',
                            'pdf','doc','docx','odt','rtf',
                            'xls','xlsx','ods','csv',
                            'ppt','pptx','odp',
                            'txt','md','json','xml','html','htm','log',
                            'zip','rar','7z','tar','gz'];
                        if (!in_array($extension, $allowedExtensions, true)) {
                            continue;
                        }
                        $uniqueFilename = uniqid('comment_', true) . '.' . $extension;
                        
                        // Upload-Verzeichnis
                        $uploadDir = rex_path::addonData('issue_tracker', 'attachments/');
                        $targetPath = $uploadDir . $uniqueFilename;
                        
                        // Datei verschieben
                        if (move_uploaded_file($_FILES['comment_attachments']['tmp_name'][$i], $targetPath)) {
                            @chmod($targetPath, rex::getFilePerm());
                            
                            // Attachment erstellen
                            $attachment = new Attachment();
                            $attachment->setIssueId($issue->getId());
                            $attachment->setCommentId($comment->getId());
                            $attachment->setFilename($uniqueFilename);
                            $attachment->setOriginalFilename($_FILES['comment_attachments']['name'][$i]);
                            $attachment->setMimetype($_FILES['comment_attachments']['type'][$i]);
                            $attachment->setFilesize($_FILES['comment_attachments']['size'][$i]);
                            $attachment->setCreatedBy(PermissionService::getUserId());
                            $attachment->save();
                        }
                    }
                }
            }
            
            // History-Eintrag erstellen
            HistoryService::add(
                $issue->getId(),
                PermissionService::getUserId(),
                'commented',
                'comment',
                null,
                null
            );
            
            // @Mentions aus Kommentar extrahieren und speichern
            if (preg_match_all('/@([\w\-\.]+)/', $commentText, $mentionMatches)) {
                $mentionedLogins = array_unique($mentionMatches[1]);
                $mentioner = rex::getUser();
                foreach ($mentionedLogins as $login) {
                    $mentionedUserSql = rex_sql::factory();
                    $mentionedUserSql->setQuery(
                        'SELECT id FROM ' . rex::getTable('user') . ' WHERE login = ? AND status = 1',
                        [$login]
                    );
                    if ($mentionedUserSql->getRows() > 0) {
                        $mentionedUserId = (int) $mentionedUserSql->getValue('id');
                        $mentionedUser = rex_user::get($mentionedUserId);
                        // Permission-Check: Nur erwähnen wenn User das private Issue sehen darf
                        if ($issue->getIsPrivate()) {
                            $mentionedCanView = $mentionedUserId === $issue->getCreatedBy()
                                || $mentionedUserId === $issue->getAssignedUserId()
                                || ($mentionedUser !== null && ($mentionedUser->isAdmin() || $mentionedUser->hasPerm('issue_tracker[manager]')));
                            if (!$mentionedCanView) {
                                continue;
                            }
                        }
                        // Mention speichern
                        $mentionSql = rex_sql::factory();
                        $mentionSql->setTable(rex::getTable('issue_tracker_mentions'));
                        $mentionSql->setValue('issue_id', $issue->getId());
                        $mentionSql->setValue('comment_id', $comment->getId());
                        $mentionSql->setValue('mentioned_user_id', $mentionedUserId);
                        $mentionSql->setValue('created_by', PermissionService::getUserId());
                        $mentionSql->setValue('created_at', date('Y-m-d H:i:s'));
                        $mentionSql->insert();
                        // E-Mail-Benachrichtigung
                        if ($mentionedUser !== null && $mentioner) {
                            NotificationService::notifyMentioned($issue, $mentionedUser, $mentioner, $comment->getId());
                        }
                    }
                }
            }

            // /spent Zeiterfassung aus Kommentar extrahieren
            $spentMinutes = ContentRenderer::extractSpentMinutes($commentText);
            if ($spentMinutes > 0) {
                $timeSql = rex_sql::factory();
                $timeSql->setTable(rex::getTable('issue_tracker_time_entries'));
                $timeSql->setValue('issue_id', $issue->getId());
                $timeSql->setValue('comment_id', $comment->getId());
                $timeSql->setValue('user_id', PermissionService::getUserId());
                $timeSql->setValue('minutes', $spentMinutes);
                $note = ContentRenderer::stripSpentCommand($commentText);
                $timeSql->setValue('note', substr($note, 0, 255));
                $timeSql->setValue('created_at', date('Y-m-d H:i:s'));
                $timeSql->insert();
                HistoryService::add(
                    $issue->getId(),
                    PermissionService::getUserId(),
                    'time_logged',
                    'time',
                    null,
                    ContentRenderer::formatMinutes($spentMinutes)
                );
            }

            // #Issue-Referenzen aus Kommentar extrahieren und History-Eintrag erstellen
            if (preg_match_all('/(?<!\w)#(\d+)(?!\w)/', $commentText, $refMatches)) {
                $refIds = array_unique($refMatches[1]);
                foreach ($refIds as $refIdStr) {
                    $refId = (int) $refIdStr;
                    if ($refId === $issue->getId() || $refId <= 0) {
                        continue;
                    }
                    $refIssue = Issue::get($refId);
                    if ($refIssue !== null) {
                        HistoryService::add(
                            $refId,
                            PermissionService::getUserId(),
                            'referenced',
                            'reference',
                            null,
                            '#' . $issue->getId()
                        );
                    }
                }
            }

            // Schließen via Kommentar (GitHub-Stil)
            if ($closeAndComment && PermissionService::canEdit($issue) && !in_array($issue->getStatus(), ['closed', 'rejected'], true)) {
                $oldStatus = $issue->getStatus();
                $issue->setStatus('closed');
                $issue->save();
                HistoryService::add(
                    $issue->getId(),
                    PermissionService::getUserId(),
                    'updated',
                    'status',
                    $oldStatus,
                    'closed'
                );
                // Kombinierte Benachrichtigung (1 E-Mail statt 2)
                NotificationService::notifyCommentWithClose($comment, $issue, $oldStatus);
            } else {
                // Normale Kommentar-Benachrichtigung
                NotificationService::notifyNewComment($comment, $issue);
            }
            
            // Redirect zur aktuellen Seite mit Anker zum neuen Kommentar
            $redirectUrl = rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]);
            $redirectUrl = html_entity_decode($redirectUrl, ENT_QUOTES, 'UTF-8');
            $redirectUrl .= '#comment-' . $comment->getId();
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

// Status-Änderung direkt in der Ansicht
if (rex_post('change_status', 'int', 0) === 1) {
    $canChangeStatus = PermissionService::isAdmin() || 
                       PermissionService::canEdit($issue);
    
    if ($canChangeStatus) {
        $newStatus = rex_post('status', 'string', '');
        if ($newStatus !== '') {
            $oldStatus = $issue->getStatus();
            $issue->setStatus($newStatus);
            if ($issue->save()) {
                // History-Eintrag erstellen
                HistoryService::add(
                    $issue->getId(),
                    rex::getUser()->getId(),
                    'updated',
                    'status',
                    $oldStatus,
                    $newStatus
                );
                
                // Benachrichtigung senden
                NotificationService::notifyStatusChange($issue, $oldStatus, $newStatus, rex::getUser()->getId());
                echo rex_view::success($package->i18n('issue_tracker_status_changed'));
            }
        }
    } else {
        echo rex_view::warning($package->i18n('issue_tracker_no_permission'));
    }
}

// Reminder senden
if (rex_post('send_reminder', 'int', 0) === 1) {
    $canRemind = PermissionService::isAdmin()
        || $issue->getCreatedBy() === PermissionService::getUserId()
        || $issue->getAssignedUserId() === PermissionService::getUserId()
        || PermissionService::isManager();

    if ($canRemind && $issue->getAssignedUserId()) {
        $result = NotificationService::sendReminder($issue, PermissionService::getUserId());
        if ($result === true) {
            echo rex_view::success($package->i18n('issue_tracker_reminder_sent'));
        } elseif ($result === 'cooldown') {
            $lastReminder = NotificationService::getLastReminder($issue->getId());
            $lastTime = $lastReminder ? (new DateTime($lastReminder))->format('d.m.Y H:i') : '';
            echo rex_view::warning($package->i18n('issue_tracker_reminder_cooldown', $lastTime));
        } else {
            echo rex_view::error($package->i18n('issue_tracker_reminder_failed'));
        }
        // Issue neu laden
        $issue = Issue::get($issueId);
    } else {
        echo rex_view::warning($package->i18n('issue_tracker_no_permission'));
    }
}

// Watch/Unwatch Toggle
if (rex_post('toggle_watch', 'int', 0) === 1) {
    $currentUserId = PermissionService::getUserId();
    if (NotificationService::isWatching($issue->getId(), $currentUserId)) {
        NotificationService::removeWatcher($issue->getId(), $currentUserId);
        echo rex_view::success($package->i18n('issue_tracker_unwatched'));
    } else {
        NotificationService::addWatcher($issue->getId(), $currentUserId, $currentUserId);
        echo rex_view::success($package->i18n('issue_tracker_watched'));
    }
}

// Watcher entfernen
if (rex_post('remove_watcher', 'int', 0) > 0) {
    $removeUserId = rex_post('remove_watcher', 'int', 0);
    $canManage = PermissionService::isAdmin()
        || PermissionService::isManager()
        || $issue->getCreatedBy() === PermissionService::getUserId();
    if ($canManage) {
        NotificationService::removeWatcher($issue->getId(), $removeUserId);
        echo rex_view::success($package->i18n('issue_tracker_watcher_removed'));
    }
}

// Watcher hinzufügen (Multi-Select / Einladen)
if (rex_post('add_watchers', 'int', 0) === 1) {
    $canManage = PermissionService::isAdmin()
        || PermissionService::isManager()
        || $issue->getCreatedBy() === PermissionService::getUserId();
    if ($canManage) {
        $watcherUserIds = rex_post('watcher_user_ids', 'array', []);
        $addedUserIds = [];
        foreach ($watcherUserIds as $wuid) {
            $wuid = (int) $wuid;
            if ($wuid > 0 && NotificationService::addWatcher($issue->getId(), $wuid, PermissionService::getUserId())) {
                $addedUserIds[] = $wuid;
            }
        }
        if (!empty($addedUserIds)) {
            NotificationService::notifyWatchersAdded($issue, $addedUserIds, PermissionService::getUserId());
            echo rex_view::success($package->i18n('issue_tracker_watchers_added', (string) count($addedUserIds)));
        }
    }
}

// Einstellungen laden
$settingsSql = rex_sql::factory();
$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
$statuses = $settingsSql->getRows() > 0 ? json_decode($settingsSql->getValue('setting_value'), true) : [];

$prioritiesSql = rex_sql::factory();
$prioritiesSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "priorities"');
$priorities = $prioritiesSql->getRows() > 0 ? json_decode($prioritiesSql->getValue('setting_value'), true) : [];

// Kommentare laden
$comments = Comment::getByIssue($issue->getId());

// Attachments laden
$attachments = Attachment::getByIssue($issue->getId());

// History laden
$history = \FriendsOfREDAXO\IssueTracker\HistoryService::getByIssue($issue->getId());

// Reminder-Daten laden
$canSendReminder = false;
$lastReminderAt = null;
if ($issue->getAssignedUserId()) {
    $canRemind = PermissionService::isAdmin()
        || $issue->getCreatedBy() === PermissionService::getUserId()
        || $issue->getAssignedUserId() === PermissionService::getUserId()
        || PermissionService::isManager();
    if ($canRemind && !in_array($issue->getStatus(), ['closed', 'rejected'], true)) {
        $canSendReminder = NotificationService::canSendReminder($issue->getId());
        $lastReminderAt = NotificationService::getLastReminder($issue->getId());
    }
}

// Watcher-Daten laden
$currentUserId = PermissionService::getUserId();
$isWatching = NotificationService::isWatching($issue->getId(), $currentUserId);
$watcherIds = NotificationService::getWatchers($issue->getId());
$watcherCount = count($watcherIds);
$watcherUsers = [];
foreach ($watcherIds as $wid) {
    $wUser = rex_user::get($wid);
    if ($wUser) {
        $watcherUsers[] = ['id' => $wUser->getId(), 'name' => $wUser->getName()];
    }
}

// Verfügbare User für Watcher-Einladung (alle berechtigten User minus aktuelle Watcher)
$availableWatcherUsers = [];
$canManageWatchers = rex::getUser()->isAdmin()
    || rex::getUser()->hasPerm('issue_tracker[issue_manager]')
    || $issue->getCreatedBy() === $currentUserId;

// Alle berechtigten User laden (für Watcher + Mentions)
$allIssueTrackerUsers = [];
$allUsersSql = rex_sql::factory();
$allUsersSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE status = 1');
foreach ($allUsersSql as $row) {
    $uid = (int) $row->getValue('id');
    $u = rex_user::get($uid);
    if ($u && ($u->isAdmin() || $u->hasPerm('issue_tracker[]') || $u->hasPerm('issue_tracker[issuer]') || $u->hasPerm('issue_tracker[issue_manager]'))) {
        $allIssueTrackerUsers[$uid] = ['name' => $u->getName(), 'login' => $u->getLogin()];
        if ($canManageWatchers && !in_array($uid, $watcherIds, true)) {
            $availableWatcherUsers[$uid] = $u->getName() . ' (' . $u->getLogin() . ')';
        }
    }
}

// Tags laden
$allTags = Tag::getAll();
$currentTags = Tag::getByIssue($issue->getId());
$currentTagIds = array_map(static fn($t) => $t->getId(), $currentTags);

// Zeiterfassung: Gesamtzeit für dieses Issue summieren
$totalTimeMinutes = 0;
$timeEntriesSql = rex_sql::factory();
$timeEntriesSql->setQuery(
    'SELECT COALESCE(SUM(te.minutes), 0) as total, COUNT(*) as entries'
    . ' FROM ' . rex::getTable('issue_tracker_time_entries') . ' te'
    . ' WHERE te.issue_id = ?',
    [$issue->getId()]
);
if ($timeEntriesSql->getRows() > 0) {
    $totalTimeMinutes = (int) $timeEntriesSql->getValue('total');
}

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('issue', $issue);
$fragment->setVar('comments', $comments);
$fragment->setVar('attachments', $attachments);
$fragment->setVar('statuses', $statuses);
$fragment->setVar('history', $history);
$fragment->setVar('canSendReminder', $canSendReminder);
$fragment->setVar('lastReminderAt', $lastReminderAt);
$fragment->setVar('priorities', $priorities);
$fragment->setVar('allUsers', $allIssueTrackerUsers);
$fragment->setVar('allTags', $allTags);
$fragment->setVar('currentTags', $currentTags);
$fragment->setVar('currentTagIds', $currentTagIds);
$fragment->setVar('isWatching', $isWatching);
$fragment->setVar('watcherCount', $watcherCount);
$fragment->setVar('watcherUsers', $watcherUsers);
$fragment->setVar('availableWatcherUsers', $availableWatcherUsers);
$fragment->setVar('totalTimeMinutes', $totalTimeMinutes);
echo $fragment->parse('issue_tracker_view.php');
