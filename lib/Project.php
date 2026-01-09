<?php

/**
 * Project Model
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_sql;
use rex_user;
use DateTime;

class Project
{
    private int $id = 0;
    private string $name = '';
    private string $description = '';
    private string $status = 'active';
    private bool $isPrivate = false;
    private ?DateTime $dueDate = null;
    private string $color = '#007bff';
    private int $createdBy = 0;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    /**
     * Lädt ein Projekt aus der Datenbank
     */
    public static function get(int $id): ?self
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_projects') . ' WHERE id = ?', [$id]);
        
        if ($sql->getRows() === 0) {
            return null;
        }

        return self::fromSql($sql);
    }

    /**
     * Gibt alle Projekte zurück (optional gefiltert nach User-Berechtigung)
     */
    public static function getAll(?int $userId = null, ?string $status = null): array
    {
        $sql = rex_sql::factory();
        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        // Wenn ein User angegeben ist und dieser kein Admin ist, nur zugängliche Projekte
        $user = $userId !== null ? rex_user::get($userId) : null;
        if ($user && !$user->isAdmin()) {
            // Nur Projekte wo User Mitglied ist (owner, member oder viewer)
            $where[] = 'pu.user_id = ?';
            $params[] = $userId;
        }

        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        
        $query = 'SELECT DISTINCT p.* FROM ' . rex::getTable('issue_tracker_projects') . ' p
                  LEFT JOIN ' . rex::getTable('issue_tracker_project_users') . ' pu ON p.id = pu.project_id
                  ' . $whereClause . ' ORDER BY p.name ASC';
        
        $sql->setQuery($query, $params);

        $projects = [];
        foreach ($sql as $row) {
            $projects[] = self::fromSql($row);
        }

        return $projects;
    }

    /**
     * Gibt Projekte zurück, in denen der User Mitglied ist (member oder owner)
     */
    public static function getByUser(int $userId): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT p.* FROM ' . rex::getTable('issue_tracker_projects') . ' p
             INNER JOIN ' . rex::getTable('issue_tracker_project_users') . ' pu ON p.id = pu.project_id
             WHERE pu.user_id = ? AND pu.role IN ("owner", "member")
             ORDER BY p.name ASC',
            [$userId]
        );

        $projects = [];
        foreach ($sql as $row) {
            $projects[] = self::fromSql($row);
        }

        return $projects;
    }

    /**
     * Erstellt ein Projekt aus SQL-Daten
     */
    private static function fromSql(rex_sql $sql): self
    {
        $project = new self();
        $project->id = (int) $sql->getValue('id');
        $project->name = (string) $sql->getValue('name');
        $project->description = (string) ($sql->getValue('description') ?? '');
        $project->status = (string) $sql->getValue('status');
        $project->isPrivate = (bool) $sql->getValue('is_private');
        $project->color = (string) ($sql->getValue('color') ?? '#007bff');
        $project->createdBy = (int) $sql->getValue('created_by');
        $project->createdAt = new DateTime((string) $sql->getValue('created_at'));
        $project->updatedAt = new DateTime((string) $sql->getValue('updated_at'));
        
        if ($sql->hasValue('due_date') && $sql->getValue('due_date')) {
            $project->dueDate = new DateTime((string) $sql->getValue('due_date'));
        }

        return $project;
    }

    /**
     * Speichert das Projekt
     */
    public function save(): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_projects'));

        $sql->setValue('name', $this->name);
        $sql->setValue('description', $this->description);
        $sql->setValue('status', $this->status);
        $sql->setValue('is_private', $this->isPrivate ? 1 : 0);
        $sql->setValue('due_date', $this->dueDate ? $this->dueDate->format('Y-m-d H:i:s') : null);
        $sql->setValue('color', $this->color);
        $sql->setValue('updated_at', date('Y-m-d H:i:s'));

        if ($this->id > 0) {
            $sql->setWhere(['id' => $this->id]);
            $sql->update();
        } else {
            $sql->setValue('created_by', $this->createdBy);
            $sql->setValue('created_at', date('Y-m-d H:i:s'));
            $sql->insert();
            $this->id = (int) $sql->getLastId();
            
            // Ersteller automatisch als Owner hinzufügen
            $this->addUser($this->createdBy, 'owner');
        }

        return true;
    }

    /**
     * Löscht das Projekt
     */
    public function delete(): bool
    {
        if ($this->id === 0) {
            return false;
        }

        // Zuerst alle Projekt-User-Zuordnungen löschen
        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('issue_tracker_project_users') . ' WHERE project_id = ?', [$this->id]);
        
        // Issues aus Projekt entfernen (nicht löschen, nur project_id auf null setzen)
        $sql->setQuery('UPDATE ' . rex::getTable('issue_tracker_issues') . ' SET project_id = NULL WHERE project_id = ?', [$this->id]);
        
        // Projekt löschen
        $sql->setQuery('DELETE FROM ' . rex::getTable('issue_tracker_projects') . ' WHERE id = ?', [$this->id]);

        return true;
    }

    /**
     * Fügt einen User zum Projekt hinzu
     */
    public function addUser(int $userId, string $role = 'member'): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_project_users'));
        $sql->setValue('project_id', $this->id);
        $sql->setValue('user_id', $userId);
        $sql->setValue('role', $role);
        $sql->setValue('created_at', date('Y-m-d H:i:s'));
        
        try {
            $sql->insert();
        } catch (\Exception $e) {
            // User bereits vorhanden, Rolle aktualisieren
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('issue_tracker_project_users'));
            $sql->setWhere(['project_id' => $this->id, 'user_id' => $userId]);
            $sql->setValue('role', $role);
            $sql->update();
        }
    }

    /**
     * Entfernt einen User aus dem Projekt
     */
    public function removeUser(int $userId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . rex::getTable('issue_tracker_project_users') . ' WHERE project_id = ? AND user_id = ?',
            [$this->id, $userId]
        );
    }

    /**
     * Gibt alle Mitglieder des Projekts zurück
     */
    public function getUsers(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT pu.*, u.login, u.name as user_name 
             FROM ' . rex::getTable('issue_tracker_project_users') . ' pu
             LEFT JOIN ' . rex::getTable('user') . ' u ON pu.user_id = u.id
             WHERE pu.project_id = ?
             ORDER BY pu.role ASC, u.name ASC',
            [$this->id]
        );

        $users = [];
        foreach ($sql as $row) {
            $users[] = [
                'user_id' => (int) $sql->getValue('user_id'),
                'role' => (string) $sql->getValue('role'),
                'login' => (string) $sql->getValue('login'),
                'name' => (string) $sql->getValue('user_name'),
                'user' => rex_user::get((int) $sql->getValue('user_id'))
            ];
        }

        return $users;
    }

    /**
     * Prüft ob ein User Zugriff auf das Projekt hat
     */
    public function hasAccess(int $userId): bool
    {
        $user = rex_user::get($userId);
        if ($user && $user->isAdmin()) {
            return true;
        }

        // Öffentliches Projekt = alle haben Lesezugriff
        if (!$this->isPrivate) {
            return true;
        }

        // Privates Projekt = nur Mitglieder
        return $this->isMember($userId);
    }

    /**
     * Prüft ob ein User Mitglied des Projekts ist
     */
    public function isMember(int $userId): bool
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT id FROM ' . rex::getTable('issue_tracker_project_users') . ' WHERE project_id = ? AND user_id = ?',
            [$this->id, $userId]
        );
        return $sql->getRows() > 0;
    }

    /**
     * Prüft ob ein User Schreibrechte im Projekt hat (Owner oder Member)
     */
    public function canWrite(int $userId): bool
    {
        $user = rex_user::get($userId);
        if ($user && $user->isAdmin()) {
            return true;
        }

        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT role FROM ' . rex::getTable('issue_tracker_project_users') . ' WHERE project_id = ? AND user_id = ?',
            [$this->id, $userId]
        );
        
        if ($sql->getRows() === 0) {
            return false;
        }

        $role = $sql->getValue('role');
        return in_array($role, ['owner', 'member'], true);
    }

    /**
     * Prüft ob ein User Owner des Projekts ist
     */
    public function isOwner(int $userId): bool
    {
        $user = rex_user::get($userId);
        if ($user && $user->isAdmin()) {
            return true;
        }

        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT role FROM ' . rex::getTable('issue_tracker_project_users') . ' WHERE project_id = ? AND user_id = ? AND role = "owner"',
            [$this->id, $userId]
        );
        return $sql->getRows() > 0;
    }

    /**
     * Gibt die Rolle eines Users im Projekt zurück
     */
    public function getUserRole(int $userId): ?string
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT role FROM ' . rex::getTable('issue_tracker_project_users') . ' WHERE project_id = ? AND user_id = ?',
            [$this->id, $userId]
        );
        
        if ($sql->getRows() === 0) {
            return null;
        }

        return (string) $sql->getValue('role');
    }

    /**
     * Gibt alle Issues des Projekts zurück
     */
    public function getIssues(): array
    {
        return Issue::getByProject($this->id);
    }

    /**
     * Gibt Statistiken zum Projekt zurück
     */
    public function getStats(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT status, COUNT(*) as count FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE project_id = ? GROUP BY status',
            [$this->id]
        );

        $stats = [
            'total' => 0,
            'open' => 0,
            'in_progress' => 0,
            'planned' => 0,
            'closed' => 0,
            'rejected' => 0,
            'progress' => 0
        ];

        foreach ($sql as $row) {
            $status = $sql->getValue('status');
            $count = (int) $sql->getValue('count');
            $stats[$status] = $count;
            $stats['total'] += $count;
        }

        // Fortschritt berechnen (geschlossene + abgelehnte / gesamt)
        if ($stats['total'] > 0) {
            $completed = $stats['closed'] + $stats['rejected'];
            $stats['progress'] = round(($completed / $stats['total']) * 100);
        }

        return $stats;
    }

    /**
     * Prüft ob das Projekt überfällig ist
     */
    public function isOverdue(): bool
    {
        if (!$this->dueDate || $this->status === 'completed' || $this->status === 'archived') {
            return false;
        }
        return $this->dueDate < new DateTime();
    }

    /**
     * Gibt den Ersteller zurück
     */
    public function getCreator(): ?rex_user
    {
        return rex_user::get($this->createdBy);
    }

    // Getter und Setter

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getIsPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): void
    {
        $this->isPrivate = $isPrivate;
    }

    public function getDueDate(): ?DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(?DateTime $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }
}
