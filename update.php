<?php

/**
 * Update-Script für Issue Tracker AddOn
 * Prüft und installiert fehlende Media Manager Typen und Datenbank-Spalten
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\EmailTemplateService;

// Upload-Verzeichnis erstellen falls nicht vorhanden
$uploadDir = rex_path::addonData('issue_tracker', 'attachments');
if (!is_dir($uploadDir)) {
    rex_dir::create($uploadDir);
}

// Datenbank-Tabellen erstellen/aktualisieren (gleiche Definitionen wie in install.php)
include __DIR__ . '/table_setup.php';

// Reminder Cooldown Standard-Setting (falls noch nicht vorhanden)
$sql = rex_sql::factory();
$sql->setQuery('SELECT id FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "reminder_cooldown_hours"');
if ($sql->getRows() === 0) {
    rex_sql::factory()
        ->setTable(rex::getTable('issue_tracker_settings'))
        ->setValue('setting_key', 'reminder_cooldown_hours')
        ->setValue('setting_value', '24')
        ->insert();
}

// Fehlende E-Mail-Templates nachrüsten (ohne bestehende zu überschreiben)
$defaultTemplates = EmailTemplateService::getDefaultHtmlTemplates();
foreach ($defaultTemplates as $key => $value) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT id FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = ?', [$key]);
    if ($sql->getRows() === 0) {
        rex_sql::factory()
            ->setTable(rex::getTable('issue_tracker_settings'))
            ->setValue('setting_key', $key)
            ->setValue('setting_value', $value)
            ->insert();
    }
}

// Media Manager Typen prüfen und ggf. installieren (falls Media Manager verfügbar)
if (rex_addon::get('media_manager')->isAvailable()) {
    // Prüfe ob Type "issue_tracker_attachment" existiert
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

    // Prüfe ob Type "issue_tracker_thumbnail" existiert
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

// Status "Info" hinzufügen falls noch nicht vorhanden
$sql = rex_sql::factory();
$sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
if ($sql->getRows() > 0) {
    $statuses = json_decode($sql->getValue('setting_value'), true);
    if (is_array($statuses) && !isset($statuses['info'])) {
        $statuses['info'] = 'Info';
        
        $updateSql = rex_sql::factory();
        $updateSql->setTable(rex::getTable('issue_tracker_settings'));
        $updateSql->setWhere(['setting_key' => 'statuses']);
        $updateSql->setValue('setting_value', json_encode($statuses));
        $updateSql->update();
    }
}

// Standard-Benachrichtigungseinträge für alle aktiven berechtigten User sicherstellen
$allUsersSql = rex_sql::factory();
$allUsersSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE status = 1');
foreach ($allUsersSql as $row) {
    $userId = (int) $row->getValue('id');
    $user = rex_user::get($userId);
    if (!$user) {
        continue;
    }

    // Nur User mit Issue-Tracker-Berechtigung
    if (!$user->isAdmin() && !$user->hasPerm('issue_tracker[]') && !$user->hasPerm('issue_tracker[issuer]') && !$user->hasPerm('issue_tracker[issue_manager]')) {
        continue;
    }

    // Prüfen ob Eintrag existiert
    $checkSql = rex_sql::factory();
    $checkSql->setQuery(
        'SELECT id FROM ' . rex::getTable('issue_tracker_notifications') . ' WHERE user_id = ?',
        [$userId],
    );

    if ($checkSql->getRows() === 0) {
        $insertSql = rex_sql::factory();
        $insertSql->setTable(rex::getTable('issue_tracker_notifications'));
        $insertSql->setValue('user_id', $userId);
        $insertSql->setValue('email_on_new', 1);
        $insertSql->setValue('email_on_comment', 1);
        $insertSql->setValue('email_on_status_change', 1);
        $insertSql->setValue('email_on_assignment', 1);
        $insertSql->setValue('email_on_message', 1);
        $insertSql->setValue('email_message_full_text', 0);
        $insertSql->insert();
    }
}
