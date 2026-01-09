<?php

/**
 * Deinstallation des Issue Tracker AddOns
 *
 * Löscht alle Tabellen und Daten des AddOns.
 *
 * @package issue_tracker
 */

// Tabellen in umgekehrter Reihenfolge entfernen (wegen Foreign Keys)
// Zuerst Tabellen mit Foreign Keys auf andere Tabellen

// Projekt-Mitglieder (FK auf projects)
rex_sql_table::get(rex::getTable('issue_tracker_project_users'))->drop();

// Gespeicherte Filter
rex_sql_table::get(rex::getTable('issue_tracker_saved_filters'))->drop();

// History (FK auf issues)
rex_sql_table::get(rex::getTable('issue_tracker_history'))->drop();

// E-Mail Tokens
rex_sql_table::get(rex::getTable('issue_tracker_email_tokens'))->drop();

// Attachments
rex_sql_table::get(rex::getTable('issue_tracker_attachments'))->drop();

// Issue-Tags Zuordnung (FK auf issues und tags)
rex_sql_table::get(rex::getTable('issue_tracker_issue_tags'))->drop();

// Kommentare (FK auf issues)
rex_sql_table::get(rex::getTable('issue_tracker_comments'))->drop();

// Tags
rex_sql_table::get(rex::getTable('issue_tracker_tags'))->drop();

// Benachrichtigungseinstellungen
rex_sql_table::get(rex::getTable('issue_tracker_notifications'))->drop();

// Nachrichten
rex_sql_table::get(rex::getTable('issue_tracker_messages'))->drop();

// Einstellungen
rex_sql_table::get(rex::getTable('issue_tracker_settings'))->drop();

// Issues (hat FK von project_id auf projects, aber nullable)
rex_sql_table::get(rex::getTable('issue_tracker_issues'))->drop();

// Projekte (zuletzt, da issues darauf verweisen könnte)
rex_sql_table::get(rex::getTable('issue_tracker_projects'))->drop();

// Gesamtes Data-Verzeichnis des AddOns entfernen (Attachments etc.)
$dataDir = rex_path::addonData('issue_tracker');
if (is_dir($dataDir)) {
    rex_dir::delete($dataDir);
}
