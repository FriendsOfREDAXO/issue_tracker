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

$currentUser = rex::getUser();
if (!$currentUser) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

// Attachment löschen
if (rex_request('delete_attachment', 'int', 0) > 0 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $attachmentId = rex_request('delete_attachment', 'int', 0);
    $attachment = Attachment::get($attachmentId);
    $attachmentIssue = $attachment && $attachment->getIssueId() ? Issue::get((int) $attachment->getIssueId()) : null;

    // Nur wer das Thema bearbeiten darf, darf seine Anhänge löschen
    if ($attachment && $attachmentIssue && \FriendsOfREDAXO\IssueTracker\PermissionService::canEdit($attachmentIssue) && $attachment->delete()) {
        echo rex_view::success($package->i18n('issue_tracker_attachment_deleted'));
    }
}

// Einstellungen laden
$settingsSql = rex_sql::factory();
$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "categories"');
$categoriesValue = $settingsSql->getRows() > 0 ? $settingsSql->getValue('setting_value') : null;
$categories = is_string($categoriesValue) ? (json_decode($categoriesValue, true) ?: []) : [];

$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
$statusesValue = $settingsSql->getRows() > 0 ? $settingsSql->getValue('setting_value') : null;
$statuses = is_string($statusesValue) ? (json_decode($statusesValue, true) ?: []) : [];

$settingsSql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "priorities"');
$prioritiesValue = $settingsSql->getRows() > 0 ? $settingsSql->getValue('setting_value') : null;
$priorities = is_string($prioritiesValue) ? (json_decode($prioritiesValue, true) ?: []) : [];

// Issue laden oder neu erstellen
$issue = null;
$isNew = true;

if ($issueId > 0) {
    $issue = Issue::get($issueId);
    if ($issue) {
        $isNew = false;
        
        // Berechtigungsprüfung: Admin, Issue-Manager oder Ersteller darf bearbeiten
        if (!$currentUser->isAdmin() && !$currentUser->hasPerm('issue_tracker[issue_manager]') && $issue->getCreatedBy() !== $currentUser->getId()) {
            echo rex_view::error($package->i18n('issue_tracker_no_permission'));
            return;
        }
    }
}

if (!$issue) {
    $issue = new Issue();
    $issue->setCreatedBy($currentUser->getId());
    $isNew = true;
    
    // Vorbelegung aus dem Postfach-Modus: Wissenseintrag (Status „Info“, nur Admin/Issue-Manager) und Kategorie
    $statusFromRequest = rex_request('status', 'string', '');
    if ($statusFromRequest !== '' && isset($statuses[$statusFromRequest]) && \FriendsOfREDAXO\IssueTracker\PermissionService::isAdminOrManager()) {
        $issue->setStatus($statusFromRequest);
    }
    $categoryFromRequest = rex_request('category', 'string', '');
    if ($categoryFromRequest !== '' && in_array($categoryFromRequest, $categories, true)) {
        $issue->setCategory($categoryFromRequest);
    }

    // Projekt-ID aus Request übernehmen wenn vorhanden
    $projectIdFromRequest = rex_request('project_id', 'int', 0);
    if ($projectIdFromRequest > 0) {
        $project = Project::get($projectIdFromRequest);
        if ($project && $project->canWrite($currentUser->getId())) {
            $issue->setProjectId($projectIdFromRequest);
        }
    }
}

// Speichern
if (rex_post('save', 'int', 0) === 1 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $oldIssue = null;
    if (!$isNew) {
        // Altes Issue für History speichern
        $oldIssue = clone $issue;
    }
    
    $oldStatus = $issue->getStatus();
    
    $issue->setTitle(rex_post('title', 'string', ''));
    $issue->setDescription(rex_post('description', 'string', ''));
    $issue->setCategory(rex_post('category', 'string', ''));

    // Beim Neuanlegen dürfen nur Admin/Issue-Manager einen abweichenden Status setzen;
    // normale Melder starten immer mit "Offen" (Feld ist im Formular für sie ausgeblendet).
    $postedStatus = rex_post('status', 'string', 'open');
    if ($isNew && !\FriendsOfREDAXO\IssueTracker\PermissionService::isAdminOrManager()) {
        $postedStatus = 'open';
    }
    $issue->setStatus($postedStatus);
    $issue->setPriority(rex_post('priority', 'string', 'normal'));
    $assignedUserSelection = trim(rex_post('assigned_user_id', 'string', ''));
    if ('all' === $assignedUserSelection) {
        $issue->setAssignedUserId(null);
    } else {
        $issue->setAssignedUserId((int) rex_post('assigned_user_id', 'int', 0) ?: null);
    }
    $issue->setAssignedAddon(rex_post('assigned_addon', 'string', null));
    $issue->setVersion(rex_post('version', 'string', null));
    $issue->setIsPrivate(rex_post('is_private', 'int', 0) === 1);

    // Betroffene Seite: Artikel aus der Struktur (nur existierende) oder freie Adresse (http/https oder Pfad)
    $affectedArticleId = rex_post('article_id', 'int', 0);
    $affectedClangId = rex_post('clang_id', 'int', 0);
    if ($affectedArticleId > 0 && class_exists('rex_article')) {
        if (!rex_clang::exists($affectedClangId)) {
            $affectedClangId = rex_clang::getStartId();
        }
        if (!rex_article::get($affectedArticleId, $affectedClangId)) {
            $affectedArticleId = 0;
        }
    }
    $issue->setAffectedArticle($affectedArticleId > 0 ? $affectedArticleId : null, $affectedArticleId > 0 ? $affectedClangId : null);
    $affectedUrl = trim(rex_post('affected_url', 'string', ''));
    $affectedUrlValid = Issue::isValidAffectedUrl($affectedUrl);
    $issue->setAffectedUrl($affectedUrlValid ? $affectedUrl : null);

    // Pflichtfeld: Zugewiesener User oder gemeinsame Zuständigkeit (Alle); Adresse muss gültig sein
    if (('all' !== $assignedUserSelection && !$issue->getAssignedUserId()) || !$affectedUrlValid) {
        // Formular erneut anzeigen (weiter unten) – die Fehlermeldung steht im Formular direkt am Feld und in der Zusammenfassung
    } else {
    
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
                $currentUser->getId(),
                'create'
            );
        } else {
            \FriendsOfREDAXO\IssueTracker\HistoryService::trackChanges(
                $oldIssue,
                $issue,
                $currentUser->getId()
            );
        }
        
        // Status "closed" tracken
        if ($oldStatus !== 'closed' && $issue->getStatus() === 'closed') {
            $issue->close();
            \FriendsOfREDAXO\IssueTracker\HistoryService::add(
                $issue->getId(),
                $currentUser->getId(),
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
                    $extension = strtolower(pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION));
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
                Attachment::createFromUpload($issue->getId(), $uploadedFiles, $currentUser->getId());
            }
        }
        
        // Benachrichtigungen
        if ($isNew) {
            NotificationService::notifyNewIssue($issue);
        } elseif ($oldStatus !== $issue->getStatus()) {
            NotificationService::notifyStatusChange($issue, $oldStatus, $issue->getStatus(), $currentUser->getId());
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
        
        // Rückmeldung nach der Weiterleitung als Einblendung
        \FriendsOfREDAXO\IssueTracker\UiHelper::flash(rex_i18n::rawMsg($isNew ? 'issue_tracker_flash_created' : 'issue_tracker_flash_updated', $issue->getId()));

        // Redirect zur Liste
        header('Location: ' . rex_url::backendPage('issue_tracker/issues/list', ['issue_id' => $issue->getId()], false));
        exit;
    } else {
        echo rex_view::error($package->i18n('issue_tracker_issue_save_error'));
    }
    } // Ende else (Pflichtfeld-Validierung)
}

// Kommentar hinzufügen
if (!$isNew && rex_post('add_comment', 'int', 0) === 1 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $comment = new Comment();
    $comment->setIssueId($issue->getId());
    $comment->setComment(rex_post('comment', 'string', ''));
    $comment->setIsInternal(rex_post('is_internal', 'bool', false));
    $comment->setCreatedBy($currentUser->getId());
    
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
    SELECT id, name, login
    FROM ' . rex::getTable('user') . ' 
    WHERE status = 1
    ORDER BY name
');
$users = [];
$mentionUsers = [];
foreach ($userSql as $row) {
    $user = rex_user::get((int) $row->getValue('id'));
    if (!$user) {
        continue;
    }

    $hasIssueAccess = $user->isAdmin()
        || $user->hasPerm('issue_tracker[]')
        || $user->hasPerm('issue_tracker[issuer]')
        || $user->hasPerm('issue_tracker[issue_manager]');

    if (!$hasIssueAccess) {
        continue;
    }

    $userId = (int) $row->getValue('id');
    $login = trim((string) $row->getValue('login'));
    $displayName = trim((string) $row->getValue('name'));

    if ('' === $displayName) {
        $displayName = '' !== $login ? $login : 'User #' . $userId;
    }

    $users[$userId] = $displayName;
    $mentionUsers[] = ['login' => $login, 'name' => $displayName];
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
$fragment->setVar('mentionUsers', $mentionUsers);
$fragment->setVar('addons', $addons);
$fragment->setVar('projects', $projects);
echo $fragment->parse('issue_tracker_form.php');
