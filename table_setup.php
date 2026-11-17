<?php

/**
 * Zentrale Tabellendefinitionen für Issue Tracker AddOn.
 * Wird von install.php und update.php eingebunden.
 * Durch rex_sql_table::ensure() idempotent – kann beliebig oft aufgerufen werden.
 *
 * @package issue_tracker
 */

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
    ->ensureColumn(new rex_sql_column('duplicate_of', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('sort_order', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('updated_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('closed_at', 'datetime', true))
    // Betroffene Seite: Artikel aus der Struktur (mit Sprache) oder freie Adresse
    ->ensureColumn(new rex_sql_column('article_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('clang_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('affected_url', 'varchar(500)', true))
    // Backend-Seite, von der aus gemeldet wurde (Vollbild-App, „Neues Thema“ in der Kopfzeile)
    ->ensureColumn(new rex_sql_column('source_url', 'varchar(500)', true))
    ->ensureColumn(new rex_sql_column('source_title', 'varchar(255)', true))
    // Verfasser ist ein Review-Gast (issue_tracker_review_participants.id); 0 = Backend-Benutzer (created_by)
    ->ensureColumn(new rex_sql_column('guest_id', 'int(10) unsigned', false, '0'))
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensureIndex(new rex_sql_index('duplicate_of', ['duplicate_of']))
    ->ensureIndex(new rex_sql_index('category', ['category']))
    ->ensureIndex(new rex_sql_index('assigned_user_id', ['assigned_user_id']))
    ->ensureIndex(new rex_sql_index('created_by', ['created_by']))
    ->ensureIndex(new rex_sql_index('due_date', ['due_date']))
    ->ensureIndex(new rex_sql_index('project_status_sort', ['project_id', 'status', 'sort_order']))
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
    ->ensureColumn(new rex_sql_column('guest_id', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('updated_at', 'datetime', true))
    ->ensureIndex(new rex_sql_index('issue_id', ['issue_id']))
    ->ensureIndex(new rex_sql_index('parent_comment_id', ['parent_comment_id']))
    ->ensureIndex(new rex_sql_index('created_by', ['created_by']))
    ->ensureForeignKey(
        new rex_sql_foreign_key('fk_comment_issue', rex::getTable('issue_tracker_issues'), ['issue_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE),
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
        new rex_sql_foreign_key('fk_issue_tag_issue', rex::getTable('issue_tracker_issues'), ['issue_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE),
    )
    ->ensureForeignKey(
        new rex_sql_foreign_key('fk_issue_tag_tag', rex::getTable('issue_tracker_tags'), ['tag_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE),
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
    ->ensureColumn(new rex_sql_column('email_on_mention', 'tinyint(1)', false, '1'))
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

// Tabelle für E-Mail-Tokens (Deep Links für Issues und Nachrichten)
rex_sql_table::get(rex::getTable('issue_tracker_email_tokens'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('token', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('issue_id', 'int(11)', true))
    ->ensureColumn(new rex_sql_column('message_id', 'int(11)', true))
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
        new rex_sql_foreign_key('fk_history_issue', rex::getTable('issue_tracker_issues'), ['issue_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE),
    )
    ->ensure();

// Tabelle für Reminder-Tracking
rex_sql_table::get(rex::getTable('issue_tracker_reminders'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('sent_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('sent_to', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('sent_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('issue_id', ['issue_id']))
    ->ensureIndex(new rex_sql_index('sent_at', ['sent_at']))
    ->ensure();

// Tabelle für Issue-Beobachter (Watchers)
rex_sql_table::get(rex::getTable('issue_tracker_watchers'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('added_by', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('issue_user', ['issue_id', 'user_id'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('issue_id', ['issue_id']))
    ->ensureIndex(new rex_sql_index('user_id', ['user_id']))
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
        new rex_sql_foreign_key('fk_project_user_project', rex::getTable('issue_tracker_projects'), ['project_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE),
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

// Tabelle für Zeiterfassung (via /spent Kommandobefehl)
rex_sql_table::get(rex::getTable('issue_tracker_time_entries'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('comment_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('minutes', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('note', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('issue_id', ['issue_id']))
    ->ensureIndex(new rex_sql_index('user_id', ['user_id']))
    ->ensure();

// Tabelle für @Mentions in Kommentaren und Beschreibungen
rex_sql_table::get(rex::getTable('issue_tracker_mentions'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('comment_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('mentioned_user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('read_at', 'datetime', true))
    ->ensureIndex(new rex_sql_index('issue_id', ['issue_id']))
    ->ensureIndex(new rex_sql_index('mentioned_user_id', ['mentioned_user_id']))
    ->ensureIndex(new rex_sql_index('issue_comment', ['issue_id', 'comment_id']))
    ->ensure();

// ---------------------------------------------------------------------------------------------
// Review im Frontend (seit 3.0): Runden, Teilnehmer (Backend-Benutzer und Gäste), Zugangslinks,
// Anker der Anmerkungen am Element und Zähler für die Begrenzung von Anfragen
// ---------------------------------------------------------------------------------------------

rex_sql_table::get(rex::getTable('issue_tracker_review_rounds'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('title', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('description', 'text', true))
    ->ensureColumn(new rex_sql_column('status', 'varchar(20)', false, 'active'))
    ->ensureColumn(new rex_sql_column('project_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('assigned_user_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('category', 'varchar(50)', true))
    // Startseite für Einladungen und Umfang: leere Liste = ganze Website
    ->ensureColumn(new rex_sql_column('start_article_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('article_ids', 'text', true))
    ->ensureColumn(new rex_sql_column('expires_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('updated_at', 'datetime', true))
    ->ensureIndex(new rex_sql_index('status', ['status']))
    ->ensure();

rex_sql_table::get(rex::getTable('issue_tracker_review_participants'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('round_id', 'int(10) unsigned'))
    // Backend-Benutzer (user_id > 0) oder Gast (user_id = 0, Name und E-Mail)
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(100)', true))
    ->ensureColumn(new rex_sql_column('email', 'varchar(190)', true))
    ->ensureColumn(new rex_sql_column('notify', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('invited_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('last_seen_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('revoked_at', 'datetime', true))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('round_id', ['round_id']))
    ->ensureIndex(new rex_sql_index('user_id', ['user_id']))
    ->ensure();

// Zugangslinks der Gäste: gespeichert wird nur der Hash des Schlüssels
rex_sql_table::get(rex::getTable('issue_tracker_review_links'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('participant_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('token_hash', 'char(64)'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('expires_at', 'datetime'))
    ->ensureColumn(new rex_sql_column('last_used_at', 'datetime', true))
    ->ensureIndex(new rex_sql_index('token_hash', ['token_hash'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('participant_id', ['participant_id']))
    ->ensure();

// Wo eine Anmerkung sitzt: Seite, Slice, Element (CSS-Pfad, Textausschnitt, Position im Element)
rex_sql_table::get(rex::getTable('issue_tracker_review_anchors'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('issue_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('round_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('kind', 'varchar(20)', false, 'note'))
    ->ensureColumn(new rex_sql_column('path', 'varchar(500)'))
    ->ensureColumn(new rex_sql_column('url', 'varchar(1000)', true))
    ->ensureColumn(new rex_sql_column('page_title', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('article_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('clang_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('slice_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('slice_hash', 'char(32)', true))
    ->ensureColumn(new rex_sql_column('selector', 'varchar(1000)', true))
    ->ensureColumn(new rex_sql_column('element_label', 'varchar(120)', true))
    ->ensureColumn(new rex_sql_column('text_quote', 'varchar(300)', true))
    ->ensureColumn(new rex_sql_column('rel_x', 'decimal(5,4)', true))
    ->ensureColumn(new rex_sql_column('rel_y', 'decimal(5,4)', true))
    ->ensureColumn(new rex_sql_column('viewport', 'varchar(20)', true))
    ->ensureColumn(new rex_sql_column('user_agent', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('issue_id', ['issue_id'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('round_path', ['round_id', 'path'], rex_sql_index::INDEX))
    ->ensure();

// Begrenzung der Anfragen aus dem Frontend (je Teilnehmer bzw. Benutzer und Aktion)
rex_sql_table::get(rex::getTable('issue_tracker_review_hits'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('actor', 'varchar(40)'))
    ->ensureColumn(new rex_sql_column('action', 'varchar(20)'))
    ->ensureColumn(new rex_sql_column('created_at', 'datetime'))
    ->ensureIndex(new rex_sql_index('actor_action', ['actor', 'action', 'created_at']))
    ->ensure();
