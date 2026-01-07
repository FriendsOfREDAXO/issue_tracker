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

// Kommentar löschen (nur Admins)
if (rex_post('delete_comment', 'int', 0) > 0) {
    $commentId = rex_post('delete_comment', 'int', 0);
    $comment = Comment::get($commentId);
    
    if ($comment && $comment->getIssueId() === $issue->getId()) {
        $currentUser = rex::getUser();
        
        if ($currentUser->isAdmin()) {
            if ($comment->delete()) {
                // History-Eintrag
                HistoryService::add(
                    $issue->getId(),
                    $currentUser->getId(),
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
        $currentUser = rex::getUser();
        $canEdit = $currentUser->isAdmin() || $comment->getCreatedBy() === $currentUser->getId();
        
        if ($canEdit) {
            $newText = rex_post('comment_text', 'string', '');
            if ($newText !== '') {
                $comment->setComment($newText);
                if ($comment->save()) {
                    // History-Eintrag
                    HistoryService::add(
                        $issue->getId(),
                        $currentUser->getId(),
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
        $currentUser = rex::getUser();
        $canModerate = $currentUser->isAdmin() || $issue->getCreatedBy() === $currentUser->getId();
        
        if ($canModerate) {
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
        $currentUser = rex::getUser();
        $canModerate = $currentUser->isAdmin() || $issue->getCreatedBy() === $currentUser->getId();
        
        if ($canModerate) {
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
        $comment->setCreatedBy(rex::getUser()->getId());
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
                            $attachment->setCreatedBy(rex::getUser()->getId());
                            $attachment->save();
                        }
                    }
                }
            }
            
            // History-Eintrag erstellen
            HistoryService::add(
                $issue->getId(),
                rex::getUser()->getId(),
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

// Status-Änderung direkt in der Ansicht (nur für zugewiesenen User oder Admin)
if (rex_post('change_status', 'int', 0) === 1) {
    $currentUser = rex::getUser();
    $canChangeStatus = $currentUser->isAdmin() || 
                       $issue->getAssignedUserId() === $currentUser->getId() ||
                       $issue->getCreatedBy() === $currentUser->getId();
    
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
                NotificationService::notifyStatusChange($issue, $oldStatus, $newStatus);
                echo rex_view::success($package->i18n('issue_tracker_status_changed'));
            }
        }
    } else {
        echo rex_view::warning($package->i18n('issue_tracker_no_permission'));
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

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('issue', $issue);
$fragment->setVar('comments', $comments);
$fragment->setVar('attachments', $attachments);
$fragment->setVar('statuses', $statuses);
$fragment->setVar('history', $history);
echo $fragment->parse('issue_tracker_view.php');
