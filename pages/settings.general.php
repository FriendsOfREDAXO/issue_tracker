<?php

/**
 * Einstellungen
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\PermissionService;

$package = rex_addon::get('issue_tracker');

// Nur Admins dürfen Einstellungen ändern
if (!PermissionService::canManageSettings()) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

echo \FriendsOfREDAXO\IssueTracker\UiHelper::settingsStart('general');

$func = rex_request('func', 'string', '');

// Tag hinzufügen/bearbeiten
if (rex_post('save_tag', 'int', 0) === 1 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
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
// Tag löschen nur per POST mit CSRF-Token im Formular
if (rex_post('func', 'string', '') === 'delete_tag' && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $tagId = rex_post('tag_id', 'int', 0);
    $tag = \FriendsOfREDAXO\IssueTracker\Tag::get($tagId);
    
    if ($tag) {
        $tag->delete();
        echo rex_view::success($package->i18n('issue_tracker_tag_deleted'));
    }
}

// Einstellungen speichern
// Eingaben prüfen, bevor gespeichert wird (bei Fehlern bleiben die Eingaben im Formular stehen)
$formErrors = [];
$saveSettings = rex_post('save_settings', 'int', 0) === 1 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck();
if ($saveSettings) {
    $postedFromAddress = trim(rex_post('email_from_address', 'string', ''));
    if ($postedFromAddress !== '' && !filter_var($postedFromAddress, FILTER_VALIDATE_EMAIL)) {
        $formErrors['email-from-address'] = rex_i18n::rawMsg('issue_tracker_ui_error_email');
    }
    if (trim(rex_post('email_from_name', 'string', '')) === '') {
        $formErrors['email-from-name'] = rex_i18n::rawMsg('issue_tracker_ui_error_from_name');
    }
    $postedCooldown = rex_post('reminder_cooldown_hours', 'string', '');
    if (!ctype_digit($postedCooldown) || (int) $postedCooldown < 1 || (int) $postedCooldown > 720) {
        $formErrors['reminder-cooldown'] = rex_i18n::rawMsg('issue_tracker_ui_error_cooldown');
    }
    if (!array_filter(rex_post('categories', 'array', []), 'strlen')) {
        $formErrors['issue-tracker-categories'] = rex_i18n::rawMsg('issue_tracker_ui_error_categories');
    }
}

if ($saveSettings && !$formErrors) {
    // Menü-Titel
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'menu_title')
        ->setValue('setting_value', rex_post('menu_title', 'string', ''))
        ->insertOrUpdate();
    
    // Als Vollbild öffnen (eigene Ebene über dem Backend)
    rex_config::set('issue_tracker', 'fullscreen', rex_post('fullscreen', 'int', 0) === 1);

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
    
    // E-Mail Absender-Adresse
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'email_from_address')
        ->setValue('setting_value', rex_post('email_from_address', 'string', ''))
        ->insertOrUpdate();
    
    // Reminder Cooldown
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'reminder_cooldown_hours')
        ->setValue('setting_value', rex_post('reminder_cooldown_hours', 'int', 24))
        ->insertOrUpdate();
    
    // Installations-Name (für API)
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'installation_name')
        ->setValue('setting_value', rex_post('installation_name', 'string', ''))
        ->insertOrUpdate();
    
    echo rex_view::success($package->i18n('issue_tracker_settings_saved'));
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

$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "email_from_address"');
$emailFromAddress = $sql->getRows() > 0 ? $sql->getValue('setting_value') : '';

$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "reminder_cooldown_hours"');
$reminderCooldownHours = $sql->getRows() > 0 ? (int) $sql->getValue('setting_value') : 24;

$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "installation_name"');
$installationName = $sql->getRows() > 0 ? $sql->getValue('setting_value') : '';

$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "api_token"');
$apiToken = $sql->getRows() > 0 ? $sql->getValue('setting_value') : '';

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
$fragment->setVar('emailFromAddress', $emailFromAddress);
$fragment->setVar('emailFromName', $emailFromName);
$fragment->setVar('reminderCooldownHours', $reminderCooldownHours);
$fragment->setVar('installationName', $installationName);
$fragment->setVar('apiToken', $apiToken);
$fragment->setVar('allTags', $allTags);
$fragment->setVar('editTag', $editTag);
$fragment->setVar('formErrors', $formErrors);
$fragment->setVar('fullscreen', \FriendsOfREDAXO\IssueTracker\AppShell::isEnabled());
if ($formErrors) {
    // Eingegebene Werte wieder anzeigen
    $fragment->setVar('menuTitle', rex_post('menu_title', 'string', ''));
    $fragment->setVar('categories', array_values(array_filter(rex_post('categories', 'array', []), 'strlen')));
    $fragment->setVar('emailEnabled', rex_post('email_enabled', 'int', 0));
    $fragment->setVar('emailFromAddress', rex_post('email_from_address', 'string', ''));
    $fragment->setVar('emailFromName', rex_post('email_from_name', 'string', ''));
    $fragment->setVar('reminderCooldownHours', rex_post('reminder_cooldown_hours', 'string', ''));
    $fragment->setVar('installationName', rex_post('installation_name', 'string', ''));
    $fragment->setVar('fullscreen', rex_post('fullscreen', 'int', 0) === 1);
}
echo $fragment->parse('issue_tracker_settings.php');
echo \FriendsOfREDAXO\IssueTracker\UiHelper::settingsEnd();
