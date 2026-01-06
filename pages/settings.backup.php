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
        $imported = 0;
        $errors = [];
        
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
                $sql->setValue('created_by', $issueData['created_by']);
                $sql->setValue('created_at', $issueData['created_at']);
                $sql->setValue('updated_at', $issueData['updated_at']);
                $sql->setValue('closed_at', $issueData['closed_at'] ?? null);
                
                $sql->insert();
                $newIssueId = (int) $sql->getLastId();
                
                // Kommentare importieren
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
                    $commentSql->insert();
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
                
                $imported++;
            } catch (Exception $e) {
                $errors[] = 'Issue "' . ($issueData['title'] ?? 'unknown') . '": ' . $e->getMessage();
            }
        }
        
        if ($imported > 0) {
            echo rex_view::success($imported . ' ' . $package->i18n('issue_tracker_backup_import_success'));
        }
        if (!empty($errors)) {
            echo rex_view::warning($package->i18n('issue_tracker_backup_import_error') . '<br>' . implode('<br>', $errors));
        }
    }
}

// JSON Export
if (rex_request('export', 'string', '') === 'json') {
    $sql = rex_sql::factory();
    
    // Alle Issues mit Kommentaren laden
    $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_issues') . ' ORDER BY id');
    
    $export = [
        'export_date' => date('Y-m-d H:i:s'),
        'version' => $package->getVersion(),
        'issues' => []
    ];
    
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
                'created_by' => (int) $commentRow->getValue('created_by'),
                'created_at' => $commentRow->getValue('created_at'),
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
            'created_by' => (int) $issueRow->getValue('created_by'),
            'created_at' => $issueRow->getValue('created_at'),
            'updated_at' => $issueRow->getValue('updated_at'),
            'closed_at' => $issueRow->getValue('closed_at'),
            'comments' => $comments,
            'tags' => $tags,
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
