<?php

use FriendsOfREDAXO\IssueTracker\EmailTemplateService;

/**
 * Installation des Issue Tracker AddOns
 * Erstellt die benötigten Datenbanktabellen.
 *
 * @package issue_tracker
 */

// Upload-Verzeichnis erstellen (ohne .htaccess - wird über Media Manager ausgegeben)
$uploadDir = rex_path::addonData('issue_tracker', 'attachments');
if (!is_dir($uploadDir)) {
    rex_dir::create($uploadDir);
}

// Datenbank-Tabellen erstellen/aktualisieren
include __DIR__ . '/table_setup.php';

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
    'info' => 'Info',
    'rejected' => 'Abgelehnt',
    'closed' => 'Erledigt',
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
    'urgent' => 'Dringend',
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

rex_sql::factory()
    ->setTable(rex::getTable('issue_tracker_settings'))
    ->setValue('setting_key', 'email_from_address')
    ->setValue('setting_value', '')
    ->insertOrUpdate();

// Reminder Cooldown (in Stunden)
rex_sql::factory()
    ->setTable(rex::getTable('issue_tracker_settings'))
    ->setValue('setting_key', 'reminder_cooldown_hours')
    ->setValue('setting_value', '24')
    ->insertOrUpdate();

// Standard E-Mail-Templates einfügen - HTML-Version
$defaultTemplates = EmailTemplateService::getDefaultHtmlTemplates();

foreach ($defaultTemplates as $key => $value) {
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', $key)
        ->setValue('setting_value', $value)
        ->insertOrUpdate();
}

// Media Manager Typen für Issue Tracker Attachments erstellen (falls Media Manager verfügbar)
if (rex_addon::get('media_manager')->isAvailable()) {
    // Prüfe ob Type bereits existiert
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT id FROM ' . rex::getTable('media_manager_type') . ' WHERE name = ?', ['issue_tracker_attachment']);

    if (0 === $sql->getRows()) {
        // Media Manager Type erstellen
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media_manager_type'));
        $sql->setValue('name', 'issue_tracker_attachment');
        $sql->setValue('description', 'Issue Tracker Attachments - Original');
        $sql->setValue('status', 0); // 0 = normaler Type
        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();

        $typeId = $sql->getLastId();

        // Effect für Issue Tracker Attachments hinzufügen
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media_manager_type_effect'));
        $sql->setValue('type_id', $typeId);
        $sql->setValue('effect', 'issue_attachment');
        $sql->setValue('priority', 1);
        $sql->setValue('parameters', '{}');
        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();
    }

    // Thumbnail-Type für Issue Tracker Bilder
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT id FROM ' . rex::getTable('media_manager_type') . ' WHERE name = ?', ['issue_tracker_thumbnail']);

    if (0 === $sql->getRows()) {
        // Media Manager Type erstellen
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media_manager_type'));
        $sql->setValue('name', 'issue_tracker_thumbnail');
        $sql->setValue('description', 'Issue Tracker Attachments - Thumbnail 200x200');
        $sql->setValue('status', 0);
        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();

        $typeId = $sql->getLastId();

        // Effect 1: Issue Attachment laden
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media_manager_type_effect'));
        $sql->setValue('type_id', $typeId);
        $sql->setValue('effect', 'issue_attachment');
        $sql->setValue('priority', 1);
        $sql->setValue('parameters', '{}');
        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();

        // Effect 2: Resize auf 200x200
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media_manager_type_effect'));
        $sql->setValue('type_id', $typeId);
        $sql->setValue('effect', 'resize');
        $sql->setValue('priority', 2);
        $sql->setValue('parameters', json_encode([
            'rex_effect_resize' => [
                'rex_effect_resize_width' => '200',
                'rex_effect_resize_height' => '200',
                'rex_effect_resize_style' => 'maximum',
                'rex_effect_resize_allow_enlarge' => 'not_enlarge',
            ],
        ]));
        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();
    }
}
