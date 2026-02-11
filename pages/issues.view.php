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

// Kommentar hinzufügen
if (rex_post('add_comment', 'int', 0) === 1) {
    $commentText = rex_post('comment', 'string', '');
    $parentCommentId = rex_post('parent_comment_id', 'int', 0);
    
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
            
            // Benachrichtigung senden
            NotificationService::notifyNewComment($comment, $issue);
            
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
if ($canManageWatchers) {
    $allUsersSql = rex_sql::factory();
    $allUsersSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE status = 1');
    foreach ($allUsersSql as $row) {
        $uid = (int) $row->getValue('id');
        $u = rex_user::get($uid);
        if ($u && ($u->isAdmin() || $u->hasPerm('issue_tracker[]') || $u->hasPerm('issue_tracker[issuer]') || $u->hasPerm('issue_tracker[issue_manager]'))) {
            if (!in_array($uid, $watcherIds, true)) {
                $availableWatcherUsers[$uid] = $u->getName() . ' (' . $u->getLogin() . ')';
            }
        }
    }
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
$fragment->setVar('isWatching', $isWatching);
$fragment->setVar('watcherCount', $watcherCount);
$fragment->setVar('watcherUsers', $watcherUsers);
$fragment->setVar('availableWatcherUsers', $availableWatcherUsers);
echo $fragment->parse('issue_tracker_view.php');
