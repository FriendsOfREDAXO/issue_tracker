<?php

/**
 * Saved Filter Service
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_sql;
use DateTime;

class SavedFilterService
{
    /**
     * Speichert einen Filter
     */
    public static function save(int $userId, string $name, array $filters, bool $isDefault = false): int
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_saved_filters'));
        $sql->setValue('user_id', $userId);
        $sql->setValue('name', $name);
        $sql->setValue('filters', json_encode($filters));
        $sql->setValue('is_default', $isDefault ? 1 : 0);
        $sql->setValue('created_at', date('Y-m-d H:i:s'));
        $sql->insert();

        return (int) $sql->getLastId();
    }

    /**
     * Aktualisiert einen Filter
     */
    public static function update(int $id, int $userId, string $name, array $filters, bool $isDefault = false): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_saved_filters'));
        $sql->setWhere(['id' => $id, 'user_id' => $userId]);
        $sql->setValue('name', $name);
        $sql->setValue('filters', json_encode($filters));
        $sql->setValue('is_default', $isDefault ? 1 : 0);
        $sql->update();

        return true;
    }

    /**
     * Löscht einen Filter
     */
    public static function delete(int $id, int $userId): bool
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . rex::getTable('issue_tracker_saved_filters') . 
            ' WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        return true;
    }

    /**
     * Gibt alle Filter eines Users zurück
     */
    public static function getByUser(int $userId): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_saved_filters') . 
            ' WHERE user_id = ? ORDER BY is_default DESC, name ASC',
            [$userId]
        );

        $filters = [];
        foreach ($sql as $row) {
            $filters[] = [
                'id' => (int) $row->getValue('id'),
                'user_id' => (int) $row->getValue('user_id'),
                'name' => $row->getValue('name'),
                'filters' => json_decode($row->getValue('filters'), true),
                'is_default' => (bool) $row->getValue('is_default'),
                'created_at' => new DateTime($row->getValue('created_at')),
            ];
        }

        return $filters;
    }

    /**
     * Gibt einen spezifischen Filter zurück
     */
    public static function get(int $id, int $userId): ?array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_saved_filters') . 
            ' WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if ($sql->getRows() === 0) {
            return null;
        }

        return [
            'id' => (int) $sql->getValue('id'),
            'user_id' => (int) $sql->getValue('user_id'),
            'name' => $sql->getValue('name'),
            'filters' => json_decode($sql->getValue('filters'), true),
            'is_default' => (bool) $sql->getValue('is_default'),
            'created_at' => new DateTime($sql->getValue('created_at')),
        ];
    }

    /**
     * Gibt den Default-Filter eines Users zurück
     */
    public static function getDefault(int $userId): ?array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_saved_filters') . 
            ' WHERE user_id = ? AND is_default = 1 LIMIT 1',
            [$userId]
        );

        if ($sql->getRows() === 0) {
            return null;
        }

        return [
            'id' => (int) $sql->getValue('id'),
            'user_id' => (int) $sql->getValue('user_id'),
            'name' => $sql->getValue('name'),
            'filters' => json_decode($sql->getValue('filters'), true),
            'is_default' => true,
            'created_at' => new DateTime($sql->getValue('created_at')),
        ];
    }

    /**
     * Setzt einen Filter als Default
     */
    public static function setDefault(int $id, int $userId): bool
    {
        // Alle anderen auf nicht-default setzen
        $sql = rex_sql::factory();
        $sql->setQuery(
            'UPDATE ' . rex::getTable('issue_tracker_saved_filters') . 
            ' SET is_default = 0 WHERE user_id = ?',
            [$userId]
        );

        // Gewünschten Filter auf default setzen
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_saved_filters'));
        $sql->setWhere(['id' => $id, 'user_id' => $userId]);
        $sql->setValue('is_default', 1);
        $sql->update();

        return true;
    }
}
