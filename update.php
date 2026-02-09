<?php

/**
 * Update-Script für Issue Tracker AddOn
 * Prüft und installiert fehlende Media Manager Typen und Datenbank-Spalten
 *
 * @package issue_tracker
 */

// Upload-Verzeichnis erstellen falls nicht vorhanden
$uploadDir = rex_path::addonData('issue_tracker', 'attachments');
if (!is_dir($uploadDir)) {
    rex_dir::create($uploadDir);
}

// Datenbank-Schema aktualisieren - duplicate_of Spalte hinzufügen
rex_sql_table::get(rex::getTable('issue_tracker_issues'))
    ->ensureColumn(new rex_sql_column('duplicate_of', 'int(10) unsigned', true))
    ->ensureIndex(new rex_sql_index('duplicate_of', ['duplicate_of']))
    ->ensure();

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
