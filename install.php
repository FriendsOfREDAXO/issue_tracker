<?php

/**
 * Installation des Issue Tracker AddOns
 * Erstellt die benötigten Datenbanktabellen
 *
 * @package issue_tracker
 */

// Upload-Verzeichnis erstellen (ohne .htaccess - wird über Media Manager ausgegeben)
$uploadDir = rex_path::addonData('issue_tracker', 'attachments');
if (!is_dir($uploadDir)) {
    rex_dir::create($uploadDir);
}

// Tabelle für Issues
rex_sql_table::get(rex::getTable('issue_tracker_issues'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('title', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('description', 'text'))
    ->ensureColumn(new rex_sql_column('category', 'varchar(50)'))
    ->ensureColumn(new rex_sql_column('status', 'varchar(20)', false, 'open'))
    ->ensureColumn(new rex_sql_column('priority', 'varchar(20)', false, 'normal'))
    ->ensureColumn(new rex_sql_column('assigned_user_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('assigned_addon', 'varchar(100)', true))
    ->ensureColumn(new rex_sql_column('version', 'varchar(50)', true))
    ->ensureColumn(new rex_sql_column('due_date', 'datetime', true))
    ->ensureColumn(new rex_sql_column('is_private', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('notified', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('domain_ids', 'text', true, null))
    ->ensureColumn(new rex_sql_column('yform_tables', 'text', true, null))
    ->ensureColumn(new rex_sql_column('project_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('updated_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('closed_at', 'datetime', true))
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensureIndex(new rex_sql_index('category', ['category']))
    ->ensureIndex(new rex_sql_index('assigned_user_id', ['assigned_user_id']))
    ->ensureIndex(new rex_sql_index('created_by', ['created_by']))
    ->ensureIndex(new rex_sql_index('due_date', ['due_date']))
    ->ensure();

// Tabelle für Kommentare
rex_sql_table::get(rex::getTable('issue_tracker_comments'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('parent_comment_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('comment', 'text'))
    ->ensureColumn(new rex_sql_column('is_internal', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('is_pinned', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('is_solution', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('updated_at', 'datetime', true))
    ->ensureIndex(new rex_sql_index('issue_id', ['issue_id']))
    ->ensureIndex(new rex_sql_index('parent_comment_id', ['parent_comment_id']))
    ->ensureIndex(new rex_sql_index('created_by', ['created_by']))
    ->ensureForeignKey(
        new rex_sql_foreign_key('fk_comment_issue', rex::getTable('issue_tracker_issues'), ['issue_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE)
    )
    ->ensure();

// Tabelle für Tags
rex_sql_table::get(rex::getTable('issue_tracker_tags'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('name', 'varchar(100)'))
    ->ensureColumn(new rex_sql_column('color', 'varchar(7)', false, '#007bff'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('name', ['name'], rex_sql_index::UNIQUE))
    ->ensure();

// Zuordnungstabelle Issues <-> Tags
rex_sql_table::get(rex::getTable('issue_tracker_issue_tags'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('tag_id', 'int(10) unsigned'))
    ->ensureIndex(new rex_sql_index('issue_tag', ['issue_id', 'tag_id'], rex_sql_index::UNIQUE))
    ->ensureForeignKey(
        new rex_sql_foreign_key('fk_issue_tag_issue', rex::getTable('issue_tracker_issues'), ['issue_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE)
    )
    ->ensureForeignKey(
        new rex_sql_foreign_key('fk_issue_tag_tag', rex::getTable('issue_tracker_tags'), ['tag_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE)
    )
    ->ensure();

// Tabelle für Attachments
rex_sql_table::get(rex::getTable('issue_tracker_attachments'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('comment_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('filename', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('original_filename', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('mimetype', 'varchar(100)', true))
    ->ensureColumn(new rex_sql_column('filesize', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('issue_id', ['issue_id']))
    ->ensureIndex(new rex_sql_index('comment_id', ['comment_id']))
    ->ensureIndex(new rex_sql_index('created_by', ['created_by']))
    ->ensure();

// Tabelle für Benachrichtigungseinstellungen
rex_sql_table::get(rex::getTable('issue_tracker_notifications'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('email_on_new', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('email_on_comment', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('email_on_status_change', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('email_on_assignment', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('email_on_message', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('email_message_full_text', 'tinyint(1)', false, '0'))
    ->ensureIndex(new rex_sql_index('user_id', ['user_id'], rex_sql_index::UNIQUE))
    ->ensure();

// Tabelle für Einstellungen
rex_sql_table::get(rex::getTable('issue_tracker_settings'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('setting_key', 'varchar(100)'))
    ->ensureColumn(new rex_sql_column('setting_value', 'text'))
    ->ensureIndex(new rex_sql_index('setting_key', ['setting_key'], rex_sql_index::UNIQUE))
    ->ensure();

// Tabelle für E-Mail-Tokens (Deep Links)
rex_sql_table::get(rex::getTable('issue_tracker_email_tokens'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('token', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('issue_id', 'int(11)'))
    ->ensureColumn(new rex_sql_column('used', 'tinyint(1)', false, 0))
    ->ensureColumn(new rex_sql_column('used_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('expires_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('token', ['token'], rex_sql_index::UNIQUE))
    ->ensure();

// Tabelle für Aktivitätsverlauf/History
rex_sql_table::get(rex::getTable('issue_tracker_history'))
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

// Tabelle für gespeicherte Filter
rex_sql_table::get(rex::getTable('issue_tracker_saved_filters'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(100)'))
    ->ensureColumn(new rex_sql_column('filters', 'text'))
    ->ensureColumn(new rex_sql_column('is_default', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('user_id', ['user_id']))
    ->ensure();

// Tabelle für Projekte
rex_sql_table::get(rex::getTable('issue_tracker_projects'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('description', 'text', true))
    ->ensureColumn(new rex_sql_column('status', 'varchar(20)', false, 'active'))
    ->ensureColumn(new rex_sql_column('is_private', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('due_date', 'datetime', true))
    ->ensureColumn(new rex_sql_column('color', 'varchar(7)', false, '#007bff'))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('updated_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensureIndex(new rex_sql_index('created_by', ['created_by']))
    ->ensure();

// Tabelle für Projekt-Mitglieder
rex_sql_table::get(rex::getTable('issue_tracker_project_users'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('project_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('role', 'varchar(20)', false, 'member'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('project_user', ['project_id', 'user_id'], rex_sql_index::UNIQUE))
    ->ensureForeignKey(
        new rex_sql_foreign_key('fk_project_user_project', rex::getTable('issue_tracker_projects'), ['project_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE)
    )
    ->ensure();

// Tabelle für private Nachrichten
rex_sql_table::get(rex::getTable('issue_tracker_messages'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('sender_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('recipient_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('subject', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('message', 'text'))
    ->ensureColumn(new rex_sql_column('is_read', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('read_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('deleted_by_sender', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('deleted_by_recipient', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('sender_id', ['sender_id']))
    ->ensureIndex(new rex_sql_index('recipient_id', ['recipient_id']))
    ->ensureIndex(new rex_sql_index('is_read', ['is_read']))
    ->ensureIndex(new rex_sql_index('created_at', ['created_at']))
    ->ensure();

// Standard-Kategorien einfügen
$defaultCategories = ['Redaktion', 'Technik', 'AddOn', 'Support', 'Medien', 'Struktur'];
rex_sql::factory()
    ->setTable(rex::getTable('issue_tracker_settings'))
    ->setValue('setting_key', 'categories')
    ->setValue('setting_value', json_encode($defaultCategories))
    ->insertOrUpdate();

// Standard-Status einfügen
$defaultStatuses = [
    'open' => 'Offen',
    'in_progress' => 'In Arbeit',
    'planned' => 'Geplant',
    'rejected' => 'Abgelehnt',
    'closed' => 'Erledigt'
];
rex_sql::factory()
    ->setTable(rex::getTable('issue_tracker_settings'))
    ->setValue('setting_key', 'statuses')
    ->setValue('setting_value', json_encode($defaultStatuses))
    ->insertOrUpdate();

// Standard-Prioritäten einfügen
$defaultPriorities = [
    'low' => 'Niedrig',
    'normal' => 'Normal',
    'high' => 'Hoch',
    'urgent' => 'Dringend'
];
rex_sql::factory()
    ->setTable(rex::getTable('issue_tracker_settings'))
    ->setValue('setting_key', 'priorities')
    ->setValue('setting_value', json_encode($defaultPriorities))
    ->insertOrUpdate();

// E-Mail-Einstellungen
rex_sql::factory()
    ->setTable(rex::getTable('issue_tracker_settings'))
    ->setValue('setting_key', 'email_enabled')
    ->setValue('setting_value', '1')
    ->insertOrUpdate();

rex_sql::factory()
    ->setTable(rex::getTable('issue_tracker_settings'))
    ->setValue('setting_key', 'email_from_name')
    ->setValue('setting_value', 'REDAXO Issue Tracker')
    ->insertOrUpdate();
