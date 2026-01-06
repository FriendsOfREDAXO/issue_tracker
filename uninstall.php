<?php

/**
 * Deinstallation des Issue Tracker AddOns
 *
 * WARNUNG: Alle Daten (Issues, Kommentare, Attachments) werden gelöscht!
 * Erstellen Sie vorher ein Backup der Datenbank und des Attachment-Verzeichnisses.
 * 
 * @package issue_tracker
 */

// Warnung ausgeben (wird im Backend angezeigt)
if (!rex_request('confirm_uninstall', 'boolean', false)) {
    throw new rex_functional_exception(
        'ACHTUNG: Bei der Deinstallation werden ALLE Daten gelöscht! ' .
        'Bitte erstellen Sie zuerst ein Backup der Datenbank.'
    );
}

// Tabellen in umgekehrter Reihenfolge entfernen (wegen Foreign Keys)
rex_sql_table::get(rex::getTable('issue_tracker_saved_filters'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_history'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_email_tokens'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_attachments'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_issue_tags'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_comments'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_tags'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_notifications'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_settings'))->drop();
rex_sql_table::get(rex::getTable('issue_tracker_issues'))->drop();

// Upload-Verzeichnis entfernen
$uploadDir = rex_path::addonData('issue_tracker', 'attachments');
if (is_dir($uploadDir)) {
    rex_dir::delete($uploadDir);
}
