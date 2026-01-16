<?php

/**
 * Issue erstellen/bearbeiten
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\Comment;
use FriendsOfREDAXO\IssueTracker\Tag;
use FriendsOfREDAXO\IssueTracker\Attachment;
use FriendsOfREDAXO\IssueTracker\NotificationService;
use FriendsOfREDAXO\IssueTracker\Project;

$package = rex_addon::get('issue_tracker');

$issueId = rex_request('issue_id', 'int', 0);
$func = rex_request('func', 'string', 'add');

// Attachment löschen
if (rex_request('delete_attachment', 'int', 0) > 0) {
    $attachmentId = rex_request('delete_attachment', 'int', 0);
    $attachment = Attachment::get($attachmentId);
    
    if ($attachment && $attachment->delete()) {
        echo rex_view::success($package->i18n('issue_tracker_attachment_deleted'));
    }
}

// Einstellungen laden
$settingsSql = rex_sql::factory();
$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "categories"');
$categories = $settingsSql->getRows() > 0 ? json_decode($settingsSql->getValue('setting_value'), true) : [];

$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
$statuses = $settingsSql->getRows() > 0 ? json_decode($settingsSql->getValue('setting_value'), true) : [];

$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "priorities"');
$priorities = $settingsSql->getRows() > 0 ? json_decode($settingsSql->getValue('setting_value'), true) : [];

// Issue laden oder neu erstellen
$issue = null;
$isNew = true;

if ($issueId > 0) {
    $issue = Issue::get($issueId);
    if ($issue) {
        $isNew = false;
        
        // Berechtigungsprüfung: Admin, Issue-Manager oder Ersteller darf bearbeiten
        $currentUser = rex::getUser();
        if (!$currentUser->isAdmin() && !$currentUser->hasPerm('issue_tracker[issue_manager]') && $issue->getCreatedBy() !== $currentUser->getId()) {
            echo rex_view::error($package->i18n('issue_tracker_no_permission'));
            return;
        }
    }
}

if (!$issue) {
    $issue = new Issue();
    $issue->setCreatedBy(rex::getUser()->getId());
    $isNew = true;
    
    // Projekt-ID aus Request übernehmen wenn vorhanden
    $projectIdFromRequest = rex_request('project_id', 'int', 0);
    if ($projectIdFromRequest > 0) {
        $project = Project::get($projectIdFromRequest);
        if ($project && $project->canWrite(rex::getUser()->getId())) {
            $issue->setProjectId($projectIdFromRequest);
        }
    }
}

// Speichern
if (rex_post('save', 'int', 0) === 1) {
    $oldIssue = null;
    if (!$isNew) {
        // Altes Issue für History speichern
        $oldIssue = clone $issue;
    }
    
    $oldStatus = $issue->getStatus();
    
    $issue->setTitle(rex_post('title', 'string', ''));
    $issue->setDescription(rex_post('description', 'string', ''));
    $issue->setCategory(rex_post('category', 'string', ''));
    $issue->setStatus(rex_post('status', 'string', 'open'));
    $issue->setPriority(rex_post('priority', 'string', 'normal'));
    $issue->setAssignedUserId(rex_post('assigned_user_id', 'int', null));
    $issue->setAssignedAddon(rex_post('assigned_addon', 'string', null));
    $issue->setVersion(rex_post('version', 'string', null));
    $issue->setIsPrivate(rex_post('is_private', 'int', 0) === 1);
    
    // Domain IDs (Multiple) und YForm Tabellen (Multiple)
    $domainIds = rex_post('domain_ids', 'array', []);
    $issue->setDomainIds(array_filter($domainIds, fn($v) => $v !== ''));
    $yformTables = rex_post('yform_tables', 'array', []);
    $issue->setYformTables(array_filter($yformTables, fn($v) => $v !== ''));
    
    // Projekt-ID
    $projectId = rex_post('project_id', 'string', '');
    $issue->setProjectId($projectId !== '' ? (int) $projectId : null);
    
    // Due Date verarbeiten
    $dueDateInput = rex_post('due_date', 'string', '');
    if ($dueDateInput) {
        $issue->setDueDate(new DateTime($dueDateInput));
    } else {
        $issue->setDueDate(null);
    }
    
    if ($issue->save()) {
        // History tracken
        if ($isNew) {
            \FriendsOfREDAXO\IssueTracker\HistoryService::add(
                $issue->getId(),
                rex::getUser()->getId(),
                'create'
            );
        } else {
            \FriendsOfREDAXO\IssueTracker\HistoryService::trackChanges(
                $oldIssue,
                $issue,
                rex::getUser()->getId()
            );
        }
        
        // Status "closed" tracken
        if ($oldStatus !== 'closed' && $issue->getStatus() === 'closed') {
            $issue->close();
            \FriendsOfREDAXO\IssueTracker\HistoryService::add(
                $issue->getId(),
                rex::getUser()->getId(),
                'close'
            );
        }
        
        // Tags aktualisieren
        $selectedTags = rex_post('tags', 'array', []);
        $existingTags = $issue->getTags();
        
        // Alte Tags entfernen
        foreach ($existingTags as $tag) {
            if (!in_array($tag->getId(), $selectedTags, true)) {
                $issue->removeTag($tag->getId());
            }
        }
        
        // Neue Tags hinzufügen
        foreach ($selectedTags as $tagId) {
            $issue->addTag($tagId);
        }
        
        // Attachments hinzufügen (Standard File Upload)
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadedFiles = [];
            $fileCount = count($_FILES['attachments']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    // Eindeutigen Dateinamen generieren
                    $extension = strtolower(pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION));
                    $uniqueFilename = uniqid('issue_', true) . '.' . $extension;
                    
                    // Upload-Verzeichnis
                    $uploadDir = rex_path::addonData('issue_tracker', 'attachments/');
                    $targetPath = $uploadDir . $uniqueFilename;
                    
                    // Datei verschieben
                    if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $targetPath)) {
                        @chmod($targetPath, rex::getFilePerm());
                        
                        $uploadedFiles[] = [
                            'filename' => $uniqueFilename,
                            'original_name' => $_FILES['attachments']['name'][$i],
                            'type' => $_FILES['attachments']['type'][$i],
                            'size' => $_FILES['attachments']['size'][$i],
                        ];
                    }
                }
            }
            
            if (!empty($uploadedFiles)) {
                Attachment::createFromUpload($issue->getId(), $uploadedFiles, rex::getUser()->getId());
            }
        }
        
        // Benachrichtigungen
        if ($isNew) {
            NotificationService::notifyNewIssue($issue);
        } elseif ($oldStatus !== $issue->getStatus()) {
            NotificationService::notifyStatusChange($issue, $oldStatus, $issue->getStatus());
        }
        
        if ($issue->getAssignedUserId() && $issue->getAssignedUserId() !== rex_post('old_assigned_user_id', 'int', 0)) {
            $assignedUser = rex_user::get($issue->getAssignedUserId());
            if ($assignedUser) {
                NotificationService::notifyAssignment($issue, $assignedUser);
            }
        }
        
        echo rex_view::success($package->i18n($isNew ? 'issue_tracker_issue_created' : 'issue_tracker_issue_updated'));
        
        // LocalStorage-Draft löschen (zentrale Funktion)
        echo '<script>issueTrackerClearDraft();</script>';
        
        // Redirect zur Liste
        header('Location: ' . rex_url::backendPage('issue_tracker/issues'));
        exit;
    } else {
        echo rex_view::error($package->i18n('issue_tracker_issue_save_error'));
    }
}

// Kommentar hinzufügen
if (!$isNew && rex_post('add_comment', 'int', 0) === 1) {
    $comment = new Comment();
    $comment->setIssueId($issue->getId());
    $comment->setComment(rex_post('comment', 'string', ''));
    $comment->setIsInternal(rex_post('is_internal', 'bool', false));
    $comment->setCreatedBy(rex::getUser()->getId());
    
    if ($comment->save()) {
        NotificationService::notifyNewComment($comment, $issue);
        echo rex_view::success($package->i18n('issue_tracker_comment_added'));
    }
}

// Verfügbare Tags
$allTags = Tag::getAll();

// Verfügbare User (alle aktiven Benutzer)
$userSql = rex_sql::factory();
$userSql->setQuery('
    SELECT id, name 
    FROM ' . rex::getTable('user') . ' 
    WHERE status = 1
    ORDER BY name
');
$users = [];
foreach ($userSql as $row) {
    $users[(int) $row->getValue('id')] = $row->getValue('name');
}

// Verfügbare Add
$addons = [];
foreach (rex_addon::getAvailableAddons() as $addon) {
    $pageProp = $addon->getProperty('page');
    $title = $addon->getName();
    if (is_array($pageProp) && isset($pageProp['title'])) {
        $title = $pageProp['title'];
    }
    $addons[$addon->getName()] = $title;
}

// Verfügbare Projekte (nur wo User schreiben darf)
$currentUser = rex::getUser();
$allProjects = Project::getAll($currentUser->getId());
$projects = [];
foreach ($allProjects as $proj) {
    if ($proj->canWrite($currentUser->getId())) {
        $projects[$proj->getId()] = $proj->getName();
    }
}

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('issue', $issue);
$fragment->setVar('isNew', $isNew);
$fragment->setVar('categories', $categories);
$fragment->setVar('statuses', $statuses);
$fragment->setVar('priorities', $priorities);
$fragment->setVar('allTags', $allTags);
$fragment->setVar('users', $users);
$fragment->setVar('addons', $addons);
$fragment->setVar('projects', $projects);
echo $fragment->parse('issue_tracker_form.php');
