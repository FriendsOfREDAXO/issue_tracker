<?php

/**
 * Comment Model
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_sql;
use rex_user;
use DateTime;

class Comment
{
    private int $id = 0;
    private int $issueId = 0;
    private ?int $parentCommentId = null;
    private string $comment = '';
    private bool $isInternal = false;
    private bool $isPinned = false;
    private bool $isSolution = false;
    private int $createdBy = 0;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    /**
     * Lädt einen Kommentar
     */
    public static function get(int $id): ?self
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_comments') . ' WHERE id = ?', [$id]);
        
        if ($sql->getRows() === 0) {
            return null;
        }

        return self::fromSql($sql);
    }

    /**
     * Gibt alle Kommentare eines Issues zurück
     */
    public static function getByIssue(int $issueId): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_comments') . ' WHERE issue_id = ? ORDER BY created_at ASC',
            [$issueId]
        );

        $comments = [];
        foreach ($sql as $row) {
            $comments[] = self::fromSql($row);
        }

        return $comments;
    }

    /**
     * Erstellt einen Kommentar aus SQL-Daten
     */
    private static function fromSql(rex_sql $sql): self
    {
        $comment = new self();
        $comment->id = (int) $sql->getValue('id');
        $comment->issueId = (int) $sql->getValue('issue_id');
        $comment->parentCommentId = $sql->getValue('parent_comment_id') ? (int) $sql->getValue('parent_comment_id') : null;
        $comment->comment = (string) $sql->getValue('comment');
        $comment->isInternal = (bool) $sql->getValue('is_internal');
        $comment->isPinned = (bool) $sql->getValue('is_pinned');
        $comment->isSolution = (bool) $sql->getValue('is_solution');
        $comment->createdBy = (int) $sql->getValue('created_by');
        $comment->createdAt = new DateTime((string) $sql->getValue('created_at'));
        $comment->updatedAt = $sql->getValue('updated_at') ? new DateTime((string) $sql->getValue('updated_at')) : null;

        return $comment;
    }

    /**
     * Speichert den Kommentar
     */
    public function save(): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_comments'));

        $sql->setValue('issue_id', $this->issueId);
        $sql->setValue('parent_comment_id', $this->parentCommentId);
        $sql->setValue('comment', $this->comment);
        $sql->setValue('is_internal', $this->isInternal ? 1 : 0);
        $sql->setValue('is_pinned', $this->isPinned ? 1 : 0);
        $sql->setValue('is_solution', $this->isSolution ? 1 : 0);

        if ($this->id > 0) {
            $sql->setValue('updated_at', date('Y-m-d H:i:s'));
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
     * Löscht den Kommentar
     */
    public function delete(): bool
    {
        if ($this->id === 0) {
            return false;
        }

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('issue_tracker_comments') . ' WHERE id = ?', [$this->id]);

        return true;
    }

    /**
     * Gibt den Ersteller zurück
     */
    public function getCreator(): ?rex_user
    {
        return rex_user::get($this->createdBy);
    }

    /**
     * Gibt alle Antworten auf diesen Kommentar zurück
     */
    public function getReplies(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_comments') . ' WHERE parent_comment_id = ? ORDER BY created_at ASC',
            [$this->id]
        );

        $replies = [];
        foreach ($sql as $row) {
            $replies[] = self::fromSql($row);
        }

        return $replies;
    }

    // Getter und Setter
    public function getId(): int
    {
        return $this->id;
    }

    public function getIssueId(): int
    {
        return $this->issueId;
    }

    public function setIssueId(int $issueId): void
    {
        $this->issueId = $issueId;
    }

    public function getParentCommentId(): ?int
    {
        return $this->parentCommentId;
    }

    public function setParentCommentId(?int $parentCommentId): void
    {
        $this->parentCommentId = $parentCommentId;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function setIsInternal(bool $isInternal): void
    {
        $this->isInternal = $isInternal;
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

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setPinned(bool $isPinned): void
    {
        $this->isPinned = $isPinned;
    }

    public function isSolution(): bool
    {
        return $this->isSolution;
    }

    public function setSolution(bool $isSolution): void
    {
        $this->isSolution = $isSolution;
    }
}
