<?php

/**
 * Backup/Export
 * 
 * @package issue_tracker
 */

$package = rex_addon::get('issue_tracker');

// JSON Import
if (rex_post('import', 'int', 0) === 1 && !empty($_FILES['import_file']['tmp_name'])) {
    $jsonContent = file_get_contents($_FILES['import_file']['tmp_name']);
    $data = json_decode($jsonContent, true);
    
    if ($data === null) {
        echo rex_view::error($package->i18n('issue_tracker_backup_invalid_json'));
    } else {
        $imported = ['projects' => 0, 'issues' => 0, 'messages' => 0];
        $errors = [];
        $projectIdMap = []; // Mapping alte ID -> neue ID
        
        // Projekte importieren
        foreach ($data['projects'] ?? [] as $projectData) {
            try {
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('issue_tracker_projects'));
                $sql->setValue('name', $projectData['name']);
                $sql->setValue('description', $projectData['description'] ?? '');
                $sql->setValue('created_by', $projectData['created_by']);
                $sql->setValue('created_at', $projectData['created_at']);
                $sql->setValue('updated_at', $projectData['updated_at'] ?? date('Y-m-d H:i:s'));
                $sql->insert();
                
                $newProjectId = (int) $sql->getLastId();
                $projectIdMap[$projectData['id']] = $newProjectId;
                
                // Projekt-Mitglieder importieren
                foreach ($projectData['members'] ?? [] as $memberData) {
                    $memberSql = rex_sql::factory();
                    $memberSql->setTable(rex::getTable('issue_tracker_project_users'));
                    $memberSql->setValue('project_id', $newProjectId);
                    $memberSql->setValue('user_id', $memberData['user_id']);
                    $memberSql->setValue('role', $memberData['role'] ?? 'member');
                    $memberSql->setValue('created_at', date('Y-m-d H:i:s'));
                    $memberSql->insert();
                }
                
                $imported['projects']++;
            } catch (Exception $e) {
                $errors[] = 'Projekt "' . ($projectData['name'] ?? 'unknown') . '": ' . $e->getMessage();
            }
        }
        
        // Issues importieren
        foreach ($data['issues'] ?? [] as $issueData) {
            try {
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('issue_tracker_issues'));
                
                // Issue Daten setzen
                $sql->setValue('title', $issueData['title']);
                $sql->setValue('description', $issueData['description']);
                $sql->setValue('category', $issueData['category']);
                $sql->setValue('status', $issueData['status']);
                $sql->setValue('priority', $issueData['priority']);
                $sql->setValue('assigned_user_id', $issueData['assigned_user_id'] ?? null);
                $sql->setValue('assigned_addon', $issueData['assigned_addon'] ?? null);
                $sql->setValue('version', $issueData['version'] ?? null);
                $sql->setValue('due_date', $issueData['due_date'] ?? null);
                $sql->setValue('is_private', $issueData['is_private'] ?? 0);
                $sql->setValue('domain_ids', $issueData['domain_ids'] ?? null);
                $sql->setValue('yform_tables', $issueData['yform_tables'] ?? null);
                $sql->setValue('created_by', $issueData['created_by']);
                $sql->setValue('created_at', $issueData['created_at']);
                $sql->setValue('updated_at', $issueData['updated_at']);
                $sql->setValue('closed_at', $issueData['closed_at'] ?? null);
                
                // Projekt-ID mappen (wenn vorhanden)
                $oldProjectId = $issueData['project_id'] ?? null;
                if ($oldProjectId && isset($projectIdMap[$oldProjectId])) {
                    $sql->setValue('project_id', $projectIdMap[$oldProjectId]);
                } else {
                    $sql->setValue('project_id', null);
                }
                
                $sql->insert();
                $newIssueId = (int) $sql->getLastId();
                
                // Kommentare importieren (mit Parent-ID Mapping)
                $commentIdMap = [];
                foreach ($issueData['comments'] ?? [] as $commentData) {
                    $commentSql = rex_sql::factory();
                    $commentSql->setTable(rex::getTable('issue_tracker_comments'));
                    $commentSql->setValue('issue_id', $newIssueId);
                    $commentSql->setValue('comment', $commentData['comment']);
                    $commentSql->setValue('is_internal', $commentData['is_internal'] ?? 0);
                    $commentSql->setValue('is_pinned', $commentData['is_pinned'] ?? 0);
                    $commentSql->setValue('is_solution', $commentData['is_solution'] ?? 0);
                    $commentSql->setValue('created_by', $commentData['created_by']);
                    $commentSql->setValue('created_at', $commentData['created_at']);
                    $commentSql->setValue('updated_at', $commentData['updated_at'] ?? null);
                    
                    // Parent-ID wird später gesetzt
                    $commentSql->setValue('parent_id', null);
                    $commentSql->insert();
                    
                    $commentIdMap[$commentData['id']] = (int) $commentSql->getLastId();
                }
                
                // Parent-IDs für Kommentare setzen
                foreach ($issueData['comments'] ?? [] as $commentData) {
                    if (!empty($commentData['parent_id']) && isset($commentIdMap[$commentData['parent_id']])) {
                        $newCommentId = $commentIdMap[$commentData['id']];
                        $newParentId = $commentIdMap[$commentData['parent_id']];
                        
                        $updateSql = rex_sql::factory();
                        $updateSql->setTable(rex::getTable('issue_tracker_comments'));
                        $updateSql->setWhere(['id' => $newCommentId]);
                        $updateSql->setValue('parent_id', $newParentId);
                        $updateSql->update();
                    }
                }
                
                // Tags importieren
                foreach ($issueData['tags'] ?? [] as $tagData) {
                    // Tag suchen oder erstellen
                    $tagSql = rex_sql::factory();
                    $tagSql->setQuery(
                        'SELECT id FROM ' . rex::getTable('issue_tracker_tags') . ' WHERE name = ?',
                        [$tagData['name']]
                    );
                    
                    if ($tagSql->getRows() > 0) {
                        $tagId = (int) $tagSql->getValue('id');
                    } else {
                        $tagSql = rex_sql::factory();
                        $tagSql->setTable(rex::getTable('issue_tracker_tags'));
                        $tagSql->setValue('name', $tagData['name']);
                        $tagSql->setValue('color', $tagData['color']);
                        $tagSql->setValue('created_at', date('Y-m-d H:i:s'));
                        $tagSql->insert();
                        $tagId = (int) $tagSql->getLastId();
                    }
                    
                    // Tag mit Issue verknüpfen
                    $linkSql = rex_sql::factory();
                    $linkSql->setTable(rex::getTable('issue_tracker_issue_tags'));
                    $linkSql->setValue('issue_id', $newIssueId);
                    $linkSql->setValue('tag_id', $tagId);
                    $linkSql->insert();
                }
                
                $imported['issues']++;
            } catch (Exception $e) {
                $errors[] = 'Issue "' . ($issueData['title'] ?? 'unknown') . '": ' . $e->getMessage();
            }
        }
        
        // Nachrichten importieren (optional)
        foreach ($data['messages'] ?? [] as $msgData) {
            try {
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('issue_tracker_messages'));
                $sql->setValue('sender_id', $msgData['sender_id']);
                $sql->setValue('recipient_id', $msgData['recipient_id']);
                $sql->setValue('subject', $msgData['subject']);
                $sql->setValue('message', $msgData['message']);
                $sql->setValue('is_read', $msgData['is_read'] ?? 0);
                $sql->setValue('read_at', $msgData['read_at'] ?? null);
                $sql->setValue('created_at', $msgData['created_at']);
                $sql->insert();
                
                $imported['messages']++;
            } catch (Exception $e) {
                $errors[] = 'Nachricht: ' . $e->getMessage();
            }
        }
        
        $importSummary = [];
        if ($imported['projects'] > 0) {
            $importSummary[] = $imported['projects'] . ' Projekte';
        }
        if ($imported['issues'] > 0) {
            $importSummary[] = $imported['issues'] . ' Issues';
        }
        if ($imported['messages'] > 0) {
            $importSummary[] = $imported['messages'] . ' Nachrichten';
        }
        
        if (!empty($importSummary)) {
            echo rex_view::success($package->i18n('issue_tracker_backup_import_success') . ': ' . implode(', ', $importSummary));
        }
        if (!empty($errors)) {
            echo rex_view::warning($package->i18n('issue_tracker_backup_import_error') . '<br>' . implode('<br>', $errors));
        }
    }
}

// JSON Export
if (rex_request('export', 'string', '') === 'json') {
    $sql = rex_sql::factory();
    
    $export = [
        'export_date' => date('Y-m-d H:i:s'),
        'version' => $package->getVersion(),
        'projects' => [],
        'issues' => [],
        'messages' => [],
    ];
    
    // Projekte exportieren
    $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_projects') . ' ORDER BY id');
    foreach ($sql as $projectRow) {
        $projectId = (int) $projectRow->getValue('id');
        
        // Projekt-Mitglieder laden
        $membersSql = rex_sql::factory();
        $membersSql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_project_users') . ' WHERE project_id = ?',
            [$projectId]
        );
        
        $members = [];
        foreach ($membersSql as $memberRow) {
            $members[] = [
                'user_id' => (int) $memberRow->getValue('user_id'),
                'role' => $memberRow->getValue('role'),
            ];
        }
        
        $export['projects'][] = [
            'id' => $projectId,
            'name' => $projectRow->getValue('name'),
            'description' => $projectRow->getValue('description'),
            'created_by' => (int) $projectRow->getValue('created_by'),
            'created_at' => $projectRow->getValue('created_at'),
            'updated_at' => $projectRow->getValue('updated_at'),
            'members' => $members,
        ];
    }
    
    // Alle Issues mit Kommentaren laden
    $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_issues') . ' ORDER BY id');
    
    foreach ($sql as $issueRow) {
        $issueId = (int) $issueRow->getValue('id');
        
        // Kommentare laden
        $commentsSql = rex_sql::factory();
        $commentsSql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_comments') . ' WHERE issue_id = ? ORDER BY created_at',
            [$issueId]
        );
        
        $comments = [];
        foreach ($commentsSql as $commentRow) {
            $comments[] = [
                'id' => (int) $commentRow->getValue('id'),
                'comment' => $commentRow->getValue('comment'),
                'is_internal' => (bool) $commentRow->getValue('is_internal'),
                'is_pinned' => (bool) $commentRow->getValue('is_pinned'),
                'is_solution' => (bool) $commentRow->getValue('is_solution'),
                'parent_id' => $commentRow->getValue('parent_id') ? (int) $commentRow->getValue('parent_id') : null,
                'created_by' => (int) $commentRow->getValue('created_by'),
                'created_at' => $commentRow->getValue('created_at'),
                'updated_at' => $commentRow->getValue('updated_at'),
            ];
        }
        
        // Tags laden
        $tagsSql = rex_sql::factory();
        $tagsSql->setQuery(
            'SELECT t.* FROM ' . rex::getTable('issue_tracker_tags') . ' t 
             INNER JOIN ' . rex::getTable('issue_tracker_issue_tags') . ' it ON t.id = it.tag_id 
             WHERE it.issue_id = ?',
            [$issueId]
        );
        
        $tags = [];
        foreach ($tagsSql as $tagRow) {
            $tags[] = [
                'name' => $tagRow->getValue('name'),
                'color' => $tagRow->getValue('color'),
            ];
        }
        
        // Attachments laden
        $attachmentsSql = rex_sql::factory();
        $attachmentsSql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_attachments') . ' WHERE issue_id = ? ORDER BY created_at',
            [$issueId]
        );
        
        $attachments = [];
        foreach ($attachmentsSql as $attachmentRow) {
            $attachments[] = [
                'filename' => $attachmentRow->getValue('filename'),
                'original_filename' => $attachmentRow->getValue('original_filename'),
                'filesize' => (int) $attachmentRow->getValue('filesize'),
                'mimetype' => $attachmentRow->getValue('mimetype'),
                'created_by' => (int) $attachmentRow->getValue('created_by'),
                'created_at' => $attachmentRow->getValue('created_at'),
            ];
        }
        
        $export['issues'][] = [
            'id' => $issueId,
            'title' => $issueRow->getValue('title'),
            'description' => $issueRow->getValue('description'),
            'category' => $issueRow->getValue('category'),
            'status' => $issueRow->getValue('status'),
            'priority' => $issueRow->getValue('priority'),
            'assigned_user_id' => $issueRow->getValue('assigned_user_id'),
            'assigned_addon' => $issueRow->getValue('assigned_addon'),
            'version' => $issueRow->getValue('version'),
            'due_date' => $issueRow->getValue('due_date'),
            'is_private' => (bool) $issueRow->getValue('is_private'),
            'project_id' => $issueRow->getValue('project_id') ? (int) $issueRow->getValue('project_id') : null,
            'domain_ids' => $issueRow->getValue('domain_ids'),
            'yform_tables' => $issueRow->getValue('yform_tables'),
            'created_by' => (int) $issueRow->getValue('created_by'),
            'created_at' => $issueRow->getValue('created_at'),
            'updated_at' => $issueRow->getValue('updated_at'),
            'closed_at' => $issueRow->getValue('closed_at'),
            'comments' => $comments,
            'tags' => $tags,
            'attachments' => $attachments,
        ];
    }
    
    // Nachrichten exportieren (optional)
    $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_messages') . ' ORDER BY id');
    foreach ($sql as $msgRow) {
        $export['messages'][] = [
            'id' => (int) $msgRow->getValue('id'),
            'sender_id' => (int) $msgRow->getValue('sender_id'),
            'recipient_id' => (int) $msgRow->getValue('recipient_id'),
            'subject' => $msgRow->getValue('subject'),
            'message' => $msgRow->getValue('message'),
            'is_read' => (bool) $msgRow->getValue('is_read'),
            'read_at' => $msgRow->getValue('read_at'),
            'created_at' => $msgRow->getValue('created_at'),
        ];
    }
    
    // JSON ausgeben
    $filename = 'issue_tracker_backup_' . date('Y-m-d_H-i-s') . '.json';
    
    rex_response::cleanOutputBuffers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

?>

<div class="rex-page-section">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="rex-icon fa-download"></i> <?= $package->i18n('issue_tracker_backup') ?>
            </h3>
        </div>
        <div class="panel-body">
            <p>
                <?= $package->i18n('issue_tracker_backup_export_description') ?>
            </p>
            
            <div class="alert alert-warning">
                <strong><i class="rex-icon fa-exclamation-triangle"></i> <?= $package->i18n('issue_tracker_backup_export_note') ?></strong>
                <ul>
                    <li><?= $package->i18n('issue_tracker_backup_export_note_attachments') ?></li>
                    <li><?= $package->i18n('issue_tracker_backup_export_note_userids') ?></li>
                    <li><?= $package->i18n('issue_tracker_backup_export_note_tokens') ?></li>
                </ul>
            </div>
            
            <a href="<?= rex_url::currentBackendPage(['export' => 'json']) ?>" class="btn btn-primary">
                <i class="rex-icon fa-download"></i> <?= $package->i18n('issue_tracker_backup_export_button') ?>
            </a>
            
            <hr>
            
            <h4><?= $package->i18n('issue_tracker_backup_manual_db') ?></h4>
            <p><?= $package->i18n('issue_tracker_backup_manual_tables') ?></p>
            <ul>
                <li><code>rex_issue_tracker_issues</code></li>
                <li><code>rex_issue_tracker_comments</code></li>
                <li><code>rex_issue_tracker_tags</code></li>
                <li><code>rex_issue_tracker_issue_tags</code></li>
                <li><code>rex_issue_tracker_attachments</code></li>
                <li><code>rex_issue_tracker_history</code></li>
                <li><code>rex_issue_tracker_notifications</code></li>
                <li><code>rex_issue_tracker_saved_filters</code></li>
                <li><code>rex_issue_tracker_email_tokens</code></li>
                <li><code>rex_issue_tracker_settings</code></li>
                <li><code>rex_issue_tracker_projects</code></li>
                <li><code>rex_issue_tracker_project_users</code></li>
                <li><code>rex_issue_tracker_messages</code></li>
            </ul>
            
            <h4><?= $package->i18n('issue_tracker_backup_attachment_dir') ?></h4>
            <p>
                <?= $package->i18n('issue_tracker_backup_attachment_dir_info') ?><br>
                <code><?= rex_path::addonData('issue_tracker', 'attachments') ?></code>
            </p>
        </div>
    </div>
</div>

<div class="rex-page-section">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="rex-icon fa-upload"></i> <?= $package->i18n('issue_tracker_backup_import') ?>
            </h3>
        </div>
        <div class="panel-body">
            <p>
                <?= $package->i18n('issue_tracker_backup_import_description') ?>
            </p>
            
            <div class="alert alert-danger">
                <strong><i class="rex-icon fa-exclamation-triangle"></i> <?= $package->i18n('issue_tracker_backup_import_warning') ?></strong>
                <ul>
                    <li><?= $package->i18n('issue_tracker_backup_import_warning_newids') ?></li>
                    <li><?= $package->i18n('issue_tracker_backup_import_warning_userids') ?></li>
                    <li><?= $package->i18n('issue_tracker_backup_import_warning_attachments') ?></li>
                    <li><?= $package->i18n('issue_tracker_backup_import_warning_duplicates') ?></li>
                </ul>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="import" value="1" />
                <div class="form-group">
                    <label><?= $package->i18n('issue_tracker_backup_import_file') ?></label>
                    <input type="file" name="import_file" accept=".json" class="form-control" required />
                </div>
                <button type="submit" class="btn btn-warning">
                    <i class="rex-icon fa-upload"></i> <?= $package->i18n('issue_tracker_backup_import_button') ?>
                </button>
            </form>
        </div>
    </div>
</div>
