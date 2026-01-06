<?php

/**
 * History Service - Verwaltet Aktivitätsverlauf
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_sql;
use rex_user;
use DateTime;

class HistoryService
{
    /**
     * Fügt einen History-Eintrag hinzu
     */
    public static function add(int $issueId, int $userId, string $action, ?string $field = null, $oldValue = null, $newValue = null): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_history'));
        $sql->setValue('issue_id', $issueId);
        $sql->setValue('user_id', $userId);
        $sql->setValue('action', $action);
        $sql->setValue('field', $field);
        $sql->setValue('old_value', $oldValue !== null ? (string) $oldValue : null);
        $sql->setValue('new_value', $newValue !== null ? (string) $newValue : null);
        $sql->setValue('created_at', date('Y-m-d H:i:s'));
        $sql->insert();
    }

    /**
     * Gibt die History für ein Issue zurück
     */
    public static function getByIssue(int $issueId): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_history') . 
            ' WHERE issue_id = ? ORDER BY created_at DESC',
            [$issueId]
        );

        $history = [];
        foreach ($sql as $row) {
            $history[] = [
                'id' => (int) $row->getValue('id'),
                'issue_id' => (int) $row->getValue('issue_id'),
                'user_id' => (int) $row->getValue('user_id'),
                'user' => rex_user::get((int) $row->getValue('user_id')),
                'action' => $row->getValue('action'),
                'field' => $row->getValue('field'),
                'old_value' => $row->getValue('old_value'),
                'new_value' => $row->getValue('new_value'),
                'created_at' => new DateTime($row->getValue('created_at')),
            ];
        }

        return $history;
    }

    /**
     * Trackt Änderungen an einem Issue
     */
    public static function trackChanges(Issue $oldIssue, Issue $newIssue, int $userId): void
    {
        $fields = [
            'title' => 'Titel',
            'description' => 'Beschreibung',
            'category' => 'Kategorie',
            'status' => 'Status',
            'priority' => 'Priorität',
            'assigned_user_id' => 'Zuweisung',
            'due_date' => 'Fälligkeit',
        ];

        foreach ($fields as $field => $label) {
            $oldValue = null;
            $newValue = null;

            switch ($field) {
                case 'title':
                    $oldValue = $oldIssue->getTitle();
                    $newValue = $newIssue->getTitle();
                    break;
                case 'description':
                    $oldValue = $oldIssue->getDescription();
                    $newValue = $newIssue->getDescription();
                    break;
                case 'category':
                    $oldValue = $oldIssue->getCategory();
                    $newValue = $newIssue->getCategory();
                    break;
                case 'status':
                    $oldValue = $oldIssue->getStatus();
                    $newValue = $newIssue->getStatus();
                    break;
                case 'priority':
                    $oldValue = $oldIssue->getPriority();
                    $newValue = $newIssue->getPriority();
                    break;
                case 'assigned_user_id':
                    $oldValue = $oldIssue->getAssignedUserId();
                    $newValue = $newIssue->getAssignedUserId();
                    // Usernamen anstelle von IDs
                    if ($oldValue) {
                        $user = rex_user::get($oldValue);
                        $oldValue = $user ? $user->getValue('name') : 'User #' . $oldValue;
                    }
                    if ($newValue) {
                        $user = rex_user::get($newValue);
                        $newValue = $user ? $user->getValue('name') : 'User #' . $newValue;
                    }
                    break;
                case 'due_date':
                    $oldValue = $oldIssue->getDueDate() ? $oldIssue->getDueDate()->format('d.m.Y') : null;
                    $newValue = $newIssue->getDueDate() ? $newIssue->getDueDate()->format('d.m.Y') : null;
                    break;
            }

            if ($oldValue != $newValue) {
                self::add(
                    $newIssue->getId(),
                    $userId,
                    'update',
                    $label,
                    $oldValue,
                    $newValue
                );
            }
        }
    }

    /**
     * Gibt einen formatierten History-Text zurück
     */
    public static function formatEntry(array $entry): string
    {
        $package = \rex_addon::get('issue_tracker');
        
        switch ($entry['action']) {
            case 'create':
                return $package->i18n('issue_tracker_history_created');
            
            case 'update':
                if (!empty($entry['field'])) {
                    $old = $entry['old_value'] ?: '—';
                    $new = $entry['new_value'] ?: '—';
                    return sprintf(
                        '%s: <span class="text-muted">%s</span> → <strong>%s</strong>',
                        $entry['field'],
                        \rex_escape($old),
                        \rex_escape($new)
                    );
                }
                return $package->i18n('issue_tracker_history_updated');
            
            case 'comment':
                return $package->i18n('issue_tracker_history_commented');
            
            case 'close':
                return $package->i18n('issue_tracker_history_closed');
            
            default:
                return $entry['action'];
        }
    }
}
