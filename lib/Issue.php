<?php

/**
 * Issue Model
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_sql;
use rex_user;
use DateTime;

class Issue
{
    private int $id = 0;
    private string $title = '';
    private string $description = '';
    private string $category = '';
    private string $status = 'open';
    private string $priority = 'normal';
    private ?int $assignedUserId = null;
    private ?string $assignedAddon = null;
    private ?string $version = null;
    private ?DateTime $dueDate = null;
    private bool $notified = false;
    private int $createdBy = 0;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;
    private ?DateTime $closedAt = null;

    /**
     * Lädt ein Issue aus der Datenbank
     */
    public static function get(int $id): ?self
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE id = ?', [$id]);
        
        if ($sql->getRows() === 0) {
            return null;
        }

        return self::fromSql($sql);
    }

    /**
     * Gibt alle Issues zurück
     */
    public static function getAll(?string $status = null, ?string $category = null): array
    {
        $sql = rex_sql::factory();
        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if ($category !== null) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_issues') . $whereClause . ' ORDER BY created_at DESC',
            $params
        );

        $issues = [];
        foreach ($sql as $row) {
            $issues[] = self::fromSql($row);
        }

        return $issues;
    }

    /**
     * Erstellt ein Issue aus SQL-Daten
     */
    private static function fromSql(rex_sql $sql): self
    {
        $issue = new self();
        $issue->id = (int) $sql->getValue('id');
        $issue->title = (string) $sql->getValue('title');
        $issue->description = (string) $sql->getValue('description');
        $issue->category = (string) $sql->getValue('category');
        $issue->status = (string) $sql->getValue('status');
        $issue->priority = (string) $sql->getValue('priority');
        $issue->assignedUserId = $sql->hasValue('assigned_user_id') && $sql->getValue('assigned_user_id') ? (int) $sql->getValue('assigned_user_id') : null;
        $issue->assignedAddon = $sql->hasValue('assigned_addon') && $sql->getValue('assigned_addon') ? (string) $sql->getValue('assigned_addon') : null;
        $issue->version = $sql->hasValue('version') && $sql->getValue('version') ? (string) $sql->getValue('version') : null;
        $issue->notified = (bool) $sql->getValue('notified');
        $issue->createdBy = (int) $sql->getValue('created_by');
        $issue->createdAt = new DateTime((string) $sql->getValue('created_at'));
        $issue->updatedAt = new DateTime((string) $sql->getValue('updated_at'));
        
        if ($sql->hasValue('due_date') && $sql->getValue('due_date')) {
            $issue->dueDate = new DateTime((string) $sql->getValue('due_date'));
        }
        
        if ($sql->hasValue('closed_at') && $sql->getValue('closed_at')) {
            $issue->closedAt = new DateTime((string) $sql->getValue('closed_at'));
        }

        return $issue;
    }

    /**
     * Speichert das Issue
     */
    public function save(): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_issues'));

        $sql->setValue('title', $this->title);
        $sql->setValue('description', $this->description);
        $sql->setValue('category', $this->category);
        $sql->setValue('status', $this->status);
        $sql->setValue('priority', $this->priority);
        $sql->setValue('assigned_user_id', $this->assignedUserId);
        $sql->setValue('assigned_addon', $this->assignedAddon);
        $sql->setValue('version', $this->version);
        $sql->setValue('due_date', $this->dueDate ? $this->dueDate->format('Y-m-d H:i:s') : null);
        $sql->setValue('notified', $this->notified ? 1 : 0);
        $sql->setValue('updated_at', date('Y-m-d H:i:s'));

        if ($this->id > 0) {
            $sql->setWhere(['id' => $this->id]);
            $sql->update();
        } else {
            $sql->setValue('created_by', $this->createdBy);
            $sql->setValue('created_at', date('Y-m-d H:i:s'));
            $sql->insert();
            $this->id = (int) $sql->getLastId();
        }

        return true;
    }

    /**
     * Löscht das Issue
     */
    public function delete(): bool
    {
        if ($this->id === 0) {
            return false;
        }

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE id = ?', [$this->id]);

        return true;
    }

    /**
     * Schließt das Issue
     */
    public function close(): void
    {
        $this->status = 'closed';
        $this->closedAt = new DateTime();
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_issues'));
        $sql->setWhere(['id' => $this->id]);
        $sql->setValue('closed_at', $this->closedAt->format('Y-m-d H:i:s'));
        $sql->update();
    }

    /**
     * Gibt den Ersteller zurück
     */
    public function getCreator(): ?rex_user
    {
        return rex_user::get($this->createdBy);
    }

    /**
     * Gibt den zugewiesenen User zurück
     */
    public function getAssignedUser(): ?rex_user
    {
        if ($this->assignedUserId === null) {
            return null;
        }
        return rex_user::get($this->assignedUserId);
    }

    /**
     * Gibt die Kommentare zurück
     */
    public function getComments(): array
    {
        return Comment::getByIssue($this->id);
    }

    /**
     * Gibt die Tags zurück
     */
    public function getTags(): array
    {
        return Tag::getByIssue($this->id);
    }

    /**
     * Fügt einen Tag hinzu
     */
    public function addTag(int $tagId): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_issue_tags'));
        $sql->setValue('issue_id', $this->id);
        $sql->setValue('tag_id', $tagId);
        
        try {
            $sql->insert();
        } catch (\Exception $e) {
            // Tag bereits vorhanden, ignorieren
        }
    }

    /**
     * Entfernt einen Tag
     */
    public function removeTag(int $tagId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . rex::getTable('issue_tracker_issue_tags') . ' WHERE issue_id = ? AND tag_id = ?',
            [$this->id, $tagId]
        );
    }

    // Getter und Setter
    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): void
    {
        $this->priority = $priority;
    }

    public function getAssignedUserId(): ?int
    {
        return $this->assignedUserId;
    }

    public function setAssignedUserId(?int $userId): void
    {
        $this->assignedUserId = $userId;
    }

    public function getAssignedAddon(): ?string
    {
        return $this->assignedAddon;
    }

    public function setAssignedAddon(?string $addon): void
    {
        $this->assignedAddon = $addon;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function isNotified(): bool
    {
        return $this->notified;
    }

    public function setNotified(bool $notified): void
    {
        $this->notified = $notified;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $userId): void
    {
        $this->createdBy = $userId;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function getClosedAt(): ?DateTime
    {
        return $this->closedAt;
    }

    public function getDueDate(): ?DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(?DateTime $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function isOverdue(): bool
    {
        if (!$this->dueDate || $this->status === 'closed') {
            return false;
        }
        return $this->dueDate < new DateTime();
    }
}
