<?php

/**
 * Einstellungen
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\NotificationService;
use FriendsOfREDAXO\IssueTracker\PermissionService;

$package = rex_addon::get('issue_tracker');

// Nur Admins dürfen Einstellungen ändern
if (!PermissionService::canManageSettings()) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

$func = rex_request('func', 'string', '');

// Tag hinzufügen/bearbeiten
if (rex_post('save_tag', 'int', 0) === 1) {
    $tagId = rex_post('tag_id', 'int', 0);
    $tagName = trim(rex_post('tag_name', 'string', ''));
    $tagColor = rex_post('tag_color', 'string', '#007bff');
    
    if ($tagName) {
        // Prüfen ob Tag-Name bereits existiert (außer beim Bearbeiten des gleichen Tags)
        $existingTag = \FriendsOfREDAXO\IssueTracker\Tag::getByName($tagName);
        if ($existingTag && $existingTag->getId() !== $tagId) {
            echo rex_view::error($package->i18n('issue_tracker_tag_exists'));
        } else {
            $tag = $tagId > 0 ? \FriendsOfREDAXO\IssueTracker\Tag::get($tagId) : new \FriendsOfREDAXO\IssueTracker\Tag();
            
            if ($tag || $tagId === 0) {
                if (!$tag) {
                    $tag = new \FriendsOfREDAXO\IssueTracker\Tag();
                }
                $tag->setName($tagName);
                $tag->setColor($tagColor);
                $tag->save();
                
                echo rex_view::success($package->i18n('issue_tracker_tag_saved'));
            }
        }
    } else {
        echo rex_view::error($package->i18n('issue_tracker_tag_name_required'));
    }
}

// Tag löschen
if ($func === 'delete_tag') {
    $tagId = rex_request('tag_id', 'int', 0);
    $tag = \FriendsOfREDAXO\IssueTracker\Tag::get($tagId);
    
    if ($tag) {
        $tag->delete();
        echo rex_view::success($package->i18n('issue_tracker_tag_deleted'));
    }
}

// Einstellungen speichern
if (rex_post('save_settings', 'int', 0) === 1) {
    // Menü-Titel
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'menu_title')
        ->setValue('setting_value', rex_post('menu_title', 'string', ''))
        ->insertOrUpdate();
    
    // Kategorien
    $categories = array_filter(rex_post('categories', 'array', []), 'strlen');
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'categories')
        ->setValue('setting_value', json_encode(array_values($categories)))
        ->insertOrUpdate();
    
    // E-Mail aktiviert
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'email_enabled')
        ->setValue('setting_value', rex_post('email_enabled', 'int', 0))
        ->insertOrUpdate();
    
    // E-Mail Absender-Name
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'email_from_name')
        ->setValue('setting_value', rex_post('email_from_name', 'string', 'REDAXO Issue Tracker'))
        ->insertOrUpdate();
    
    echo rex_view::success($package->i18n('issue_tracker_settings_saved'));
}

// Broadcast senden
if (rex_post('send_broadcast', 'int', 0) === 1) {
    $subject = rex_post('broadcast_subject', 'string', '');
    $message = rex_post('broadcast_message', 'string', '');
    $method = rex_post('broadcast_method', 'string', 'message');
    $recipients = rex_post('broadcast_recipients', 'string', 'issue_tracker');
    
    // Bei "alle User" nur E-Mail erlauben
    if ($recipients === 'all') {
        $method = 'email';
    }
    
    if ($subject && $message) {
        $count = NotificationService::sendBroadcast($subject, $message, $method, $recipients);
        echo rex_view::success($package->i18n('issue_tracker_broadcast_sent', $count));
    } else {
        echo rex_view::error($package->i18n('issue_tracker_broadcast_error'));
    }
}

// Einstellungen laden
$sql = rex_sql::factory();

$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "menu_title"');
$menuTitle = $sql->getRows() > 0 ? $sql->getValue('setting_value') : '';

$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "categories"');
$categories = $sql->getRows() > 0 ? json_decode($sql->getValue('setting_value'), true) : [];

$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "email_enabled"');
$emailEnabled = $sql->getRows() > 0 ? (int) $sql->getValue('setting_value') : 1;

$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "email_from_name"');
$emailFromName = $sql->getRows() > 0 ? $sql->getValue('setting_value') : 'REDAXO Issue Tracker';

// Tags laden
$allTags = \FriendsOfREDAXO\IssueTracker\Tag::getAll();

// Tag zum Bearbeiten laden
$editTag = null;
if ($func === 'edit_tag') {
    $tagId = rex_request('tag_id', 'int', 0);
    $editTag = \FriendsOfREDAXO\IssueTracker\Tag::get($tagId);
}

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('menuTitle', $menuTitle);
$fragment->setVar('categories', $categories);
$fragment->setVar('emailEnabled', $emailEnabled);
$fragment->setVar('emailFromName', $emailFromName);
$fragment->setVar('allTags', $allTags);
$fragment->setVar('editTag', $editTag);
echo $fragment->parse('issue_tracker_settings.php');
