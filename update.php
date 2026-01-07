<?php

/**
 * Update-Skript für Issue Tracker
 *
 * @package issue_tracker
 */

// Update: comment_id Spalte zur Attachments-Tabelle hinzufügen
$sql = rex_sql::factory();

// Prüfen ob is_private Spalte bereits existiert
$issuesColumns = $sql->getArray('SHOW COLUMNS FROM ' . rex::getTable('issue_tracker_issues'));
$hasIsPrivate = false;

foreach ($issuesColumns as $column) {
    if ($column['Field'] === 'is_private') {
        $hasIsPrivate = true;
        break;
    }
}

if (!$hasIsPrivate) {
    // is_private Spalte hinzufügen
    rex_sql_table::get(rex::getTable('issue_tracker_issues'))
        ->ensureColumn(new rex_sql_column('is_private', 'tinyint(1)', false, '0'), 'due_date')
        ->alter();
}

// Prüfen ob comment_id Spalte bereits existiert
$columns = $sql->getArray('SHOW COLUMNS FROM ' . rex::getTable('issue_tracker_attachments'));
$hasCommentId = false;

foreach ($columns as $column) {
    if ($column['Field'] === 'comment_id') {
        $hasCommentId = true;
        break;
    }
}

if (!$hasCommentId) {
    // comment_id Spalte hinzufügen
    rex_sql_table::get(rex::getTable('issue_tracker_attachments'))
        ->ensureColumn(new rex_sql_column('comment_id', 'int(10) unsigned', true), 'issue_id')
        ->ensureIndex(new rex_sql_index('comment_id', ['comment_id']))
        ->alter();
    
    // issue_id kann jetzt auch NULL sein (für Kommentar-only Attachments)
    rex_sql_table::get(rex::getTable('issue_tracker_attachments'))
        ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned', true))
        ->alter();
}

// Update: E-Mail-Token-Tabelle hinzufügen
$tokenTable = rex::getTable('issue_tracker_email_tokens');
$tables = $sql->getArray('SHOW TABLES LIKE "' . $tokenTable . '"');

if (count($tables) === 0) {
    rex_sql_table::get($tokenTable)
        ->ensurePrimaryIdColumn()
        ->ensureColumn(new rex_sql_column('token', 'varchar(64)'))
        ->ensureColumn(new rex_sql_column('issue_id', 'int(11)'))
        ->ensureColumn(new rex_sql_column('used', 'tinyint(1)', false, 0))
        ->ensureColumn(new rex_sql_column('used_at', 'datetime', true))
        ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
        ->ensureColumn(new rex_sql_column('expires_at', 'datetime'))
        ->ensureIndex(new rex_sql_index('token', ['token'], rex_sql_index::UNIQUE))
        ->ensure();
}

// Update: Benachrichtigungseinstellungen-Tabelle hinzufügen
$notificationTable = rex::getTable('issue_tracker_notifications');
$tables = $sql->getArray('SHOW TABLES LIKE "' . $notificationTable . '"');

if (count($tables) === 0) {
    rex_sql_table::get($notificationTable)
        ->ensurePrimaryIdColumn()
        ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
        ->ensureColumn(new rex_sql_column('email_on_new', 'tinyint(1)', false, '1'))
        ->ensureColumn(new rex_sql_column('email_on_comment', 'tinyint(1)', false, '1'))
        ->ensureColumn(new rex_sql_column('email_on_status_change', 'tinyint(1)', false, '1'))
        ->ensureColumn(new rex_sql_column('email_on_assignment', 'tinyint(1)', false, '1'))
        ->ensureIndex(new rex_sql_index('user_id', ['user_id'], rex_sql_index::UNIQUE))
        ->ensure();
}
// Update: due_date Feld hinzufügen
$columns = $sql->getArray('SHOW COLUMNS FROM ' . rex::getTable('issue_tracker_issues'));
$hasDueDate = false;

foreach ($columns as $column) {
    if ($column['Field'] === 'due_date') {
        $hasDueDate = true;
        break;
    }
}

if (!$hasDueDate) {
    rex_sql_table::get(rex::getTable('issue_tracker_issues'))
        ->ensureColumn(new rex_sql_column('due_date', 'datetime', true))
        ->ensureIndex(new rex_sql_index('due_date', ['due_date']))
        ->alter();
}

// Update: History-Tabelle hinzufügen
$historyTable = rex::getTable('issue_tracker_history');
$tables = $sql->getArray('SHOW TABLES LIKE "' . $historyTable . '"');

if (count($tables) === 0) {
    rex_sql_table::get($historyTable)
        ->ensurePrimaryIdColumn()
        ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned'))
        ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
        ->ensureColumn(new rex_sql_column('action', 'varchar(50)'))
        ->ensureColumn(new rex_sql_column('field', 'varchar(50)', true))
        ->ensureColumn(new rex_sql_column('old_value', 'text', true))
        ->ensureColumn(new rex_sql_column('new_value', 'text', true))
        ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
        ->ensureIndex(new rex_sql_index('issue_id', ['issue_id']))
        ->ensureIndex(new rex_sql_index('user_id', ['user_id']))
        ->ensureIndex(new rex_sql_index('created_at', ['created_at']))
        ->ensureForeignKey(
            new rex_sql_foreign_key('fk_history_issue', rex::getTable('issue_tracker_issues'), ['issue_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE)
        )
        ->ensure();
}

// Update: Saved Filters-Tabelle hinzufügen
$savedFiltersTable = rex::getTable('issue_tracker_saved_filters');
$tables = $sql->getArray('SHOW TABLES LIKE "' . $savedFiltersTable . '"');

if (count($tables) === 0) {
    rex_sql_table::get($savedFiltersTable)
        ->ensurePrimaryIdColumn()
        ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
        ->ensureColumn(new rex_sql_column('name', 'varchar(100)'))
        ->ensureColumn(new rex_sql_column('filters', 'text'))
        ->ensureColumn(new rex_sql_column('is_default', 'tinyint(1)', false, '0'))
        ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
        ->ensureIndex(new rex_sql_index('user_id', ['user_id']))
        ->ensure();
}