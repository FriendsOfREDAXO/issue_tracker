<?php

/**
 * Issue Model.
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use DateTime;
use Exception;
use rex;
use rex_sql;
use rex_user;

use function is_array;

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
    private bool $isPrivate = false;
    private bool $notified = false;
    private int $createdBy = 0;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;
    private ?DateTime $closedAt = null;
    /** @var array<int> */
    private array $domainIds = [];
    /** @var array<string> */
    private array $yformTables = [];
    private ?int $projectId = null;
    private ?int $duplicateOf = null;
    private int $sortOrder = 0;

    /**
     * Lädt ein Issue aus der Datenbank.
     */
    public static function get(int $id): ?self
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE id = ?', [$id]);

        if (0 === $sql->getRows()) {
            return null;
        }

        return self::fromSql($sql);
    }

    /**
     * Gibt alle Issues zurück.
     */
    public static function getAll(?string $status = null, ?string $category = null): array
    {
        $sql = rex_sql::factory();
        $where = [];
        $params = [];

        if (null !== $status) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if (null !== $category) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_issues') . $whereClause . ' ORDER BY created_at DESC',
            $params,
        );

        $issues = [];
        foreach ($sql as $row) {
            $issues[] = self::fromSql($row);
        }

        return $issues;
    }

    /**
     * Erstellt ein Issue aus SQL-Daten.
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
        $issue->isPrivate = (bool) $sql->getValue('is_private');
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

        // Domain IDs aus JSON laden
        if ($sql->hasValue('domain_ids') && $sql->getValue('domain_ids')) {
            $decoded = json_decode((string) $sql->getValue('domain_ids'), true);
            $issue->domainIds = is_array($decoded) ? array_map('intval', $decoded) : [];
        }

        // YForm Tables aus JSON laden
        if ($sql->hasValue('yform_tables') && $sql->getValue('yform_tables')) {
            $decoded = json_decode((string) $sql->getValue('yform_tables'), true);
            $issue->yformTables = is_array($decoded) ? $decoded : [];
        }

        // Project ID laden
        if ($sql->hasValue('project_id') && $sql->getValue('project_id')) {
            $issue->projectId = (int) $sql->getValue('project_id');
        }

        // Duplicate Of laden
        if ($sql->hasValue('duplicate_of') && $sql->getValue('duplicate_of')) {
            $issue->duplicateOf = (int) $sql->getValue('duplicate_of');
        }

        // Sort Order laden
        if ($sql->hasValue('sort_order')) {
            $issue->sortOrder = (int) $sql->getValue('sort_order');
        }

        return $issue;
    }

    /**
     * Speichert das Issue.
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
        $sql->setValue('is_private', $this->isPrivate ? 1 : 0);
        $sql->setValue('notified', $this->notified ? 1 : 0);
        $sql->setValue('domain_ids', !empty($this->domainIds) ? json_encode($this->domainIds) : null);
        $sql->setValue('yform_tables', !empty($this->yformTables) ? json_encode($this->yformTables) : null);
        $sql->setValue('project_id', $this->projectId);
        $sql->setValue('duplicate_of', $this->duplicateOf);
        $sql->setValue('sort_order', $this->sortOrder);
        $sql->setValue('closed_at', $this->closedAt ? $this->closedAt->format('Y-m-d H:i:s') : null);
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
     * Löscht das Issue.
     */
    public function delete(): bool
    {
        if (0 === $this->id) {
            return false;
        }

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE id = ?', [$this->id]);

        return true;
    }

    /**
     * Schließt das Issue.
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
     * Gibt den Ersteller zurück.
     */
    public function getCreator(): ?rex_user
    {
        return rex_user::get($this->createdBy);
    }

    /**
     * Gibt den zugewiesenen User zurück.
     */
    public function getAssignedUser(): ?rex_user
    {
        if (null === $this->assignedUserId) {
            return null;
        }
        return rex_user::get($this->assignedUserId);
    }

    /**
     * Gibt die Kommentare zurück.
     */
    public function getComments(): array
    {
        return Comment::getByIssue($this->id);
    }

    /**
     * Gibt die Tags zurück.
     */
    public function getTags(): array
    {
        return Tag::getByIssue($this->id);
    }

    /**
     * Fügt einen Tag hinzu.
     */
    public function addTag(int $tagId): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_issue_tags'));
        $sql->setValue('issue_id', $this->id);
        $sql->setValue('tag_id', $tagId);

        try {
            $sql->insert();
        } catch (Exception $e) {
            // Tag bereits vorhanden, ignorieren
        }
    }

    /**
     * Entfernt einen Tag.
     */
    public function removeTag(int $tagId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . rex::getTable('issue_tracker_issue_tags') . ' WHERE issue_id = ? AND tag_id = ?',
            [$this->id, $tagId],
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
        $oldStatus = $this->status;
        $this->status = $status;

        // Wenn Status auf "closed" gesetzt wird, closedAt setzen
        if ('closed' === $status && 'closed' !== $oldStatus) {
            $this->closedAt = new DateTime();
        }
        // Wenn Status von "closed" auf etwas anderes wechselt, closedAt löschen
        elseif ('closed' !== $status && 'closed' === $oldStatus) {
            $this->closedAt = null;
        }
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

    public function getIsPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): void
    {
        $this->isPrivate = $isPrivate;
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
        if (!$this->dueDate || 'closed' === $this->status) {
            return false;
        }
        return $this->dueDate < new DateTime();
    }

    public function getDomainIds(): array
    {
        return $this->domainIds;
    }

    /**
     * @param array<int> $domainIds
     */
    public function setDomainIds(array $domainIds): void
    {
        $this->domainIds = array_map('intval', $domainIds);
    }

    /**
     * @return array<string>
     */
    public function getYformTables(): array
    {
        return $this->yformTables;
    }

    /**
     * @param array<string> $yformTables
     */
    public function setYformTables(array $yformTables): void
    {
        $this->yformTables = $yformTables;
    }

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function setProjectId(?int $projectId): void
    {
        $this->projectId = $projectId;
    }

    public function getDuplicateOf(): ?int
    {
        return $this->duplicateOf;
    }

    public function setDuplicateOf(?int $duplicateOf): void
    {
        $this->duplicateOf = $duplicateOf;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    /**
     * Gibt das Issue zurück, von dem dieses Issue ein Duplikat ist.
     */
    public function getDuplicateIssue(): ?self
    {
        if (null === $this->duplicateOf) {
            return null;
        }
        return self::get($this->duplicateOf);
    }

    /**
     * Gibt alle Issues zurück, die Duplikate von diesem Issue sind.
     */
    public function getDuplicates(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE duplicate_of = ? ORDER BY created_at DESC',
            [$this->id],
        );

        $issues = [];
        foreach ($sql as $row) {
            $issues[] = self::fromSql($row);
        }

        return $issues;
    }

    /**
     * Markiert dieses Issue als Duplikat von einem anderen Issue und schließt es.
     *
     * @param int $duplicateOfId ID des Original-Issues
     * @param int|null $userId ID des Benutzers, der die Änderung durchführt
     * @return bool True bei Erfolg, False wenn das Original-Issue nicht existiert
     */
    public function markAsDuplicate(int $duplicateOfId, ?int $userId = null): bool
    {
        // Prüfen ob Original-Issue existiert
        $originalIssue = self::get($duplicateOfId);
        if (null === $originalIssue) {
            return false;
        }

        // Verhindere zirkuläre Referenzen
        if ($duplicateOfId === $this->id) {
            return false;
        }

        // Wenn das Original selbst ein Duplikat ist, verwende dessen Original
        if (null !== $originalIssue->getDuplicateOf()) {
            $duplicateOfId = $originalIssue->getDuplicateOf();
        }

        $oldStatus = $this->status;
        $oldDuplicateOf = $this->duplicateOf;

        // Als Duplikat markieren und schließen
        $this->duplicateOf = $duplicateOfId;
        $this->status = 'closed';
        $this->closedAt = new DateTime();

        if ($this->save()) {
            // History-Eintrag für Status-Änderung
            if (null !== $userId) {
                HistoryService::add(
                    $this->id,
                    $userId,
                    'status_changed',
                    'status',
                    $oldStatus,
                    'closed',
                );

                // History-Eintrag für Duplikat-Markierung
                HistoryService::add(
                    $this->id,
                    $userId,
                    'marked_as_duplicate',
                    'duplicate_of',
                    null !== $oldDuplicateOf ? (string) $oldDuplicateOf : '',
                    (string) $duplicateOfId,
                );
            }

            return true;
        }

        return false;
    }

    /**
     * Entfernt die Duplikat-Markierung von diesem Issue.
     *
     * @param int|null $userId ID des Benutzers, der die Änderung durchführt
     * @return bool True bei Erfolg
     */
    public function unmarkAsDuplicate(?int $userId = null): bool
    {
        $oldDuplicateOf = $this->duplicateOf;

        $this->duplicateOf = null;

        if ($this->save()) {
            // History-Eintrag
            if (null !== $userId && null !== $oldDuplicateOf) {
                HistoryService::add(
                    $this->id,
                    $userId,
                    'unmarked_as_duplicate',
                    'duplicate_of',
                    (string) $oldDuplicateOf,
                    '',
                );
            }

            return true;
        }

        return false;
    }

    /**
     * Gibt das zugehörige Projekt zurück.
     */
    public function getProject(): ?Project
    {
        if (null === $this->projectId) {
            return null;
        }
        return Project::get($this->projectId);
    }

    /**
     * Gibt alle Issues eines Projekts zurück.
     */
    public static function getByProject(int $projectId): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE project_id = ? ORDER BY created_at DESC',
            [$projectId],
        );

        $issues = [];
        foreach ($sql as $row) {
            $issues[] = self::fromSql($row);
        }

        return $issues;
    }
}
