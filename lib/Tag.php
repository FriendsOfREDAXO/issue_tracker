<?php

/**
 * Tag Model
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_sql;
use DateTime;

class Tag
{
    private int $id = 0;
    private string $name = '';
    private string $color = '#007bff';
    private ?DateTime $createdAt = null;

    /**
     * Lädt einen Tag
     */
    public static function get(int $id): ?self
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_tags') . ' WHERE id = ?', [$id]);
        
        if ($sql->getRows() === 0) {
            return null;
        }

        return self::fromSql($sql);
    }

    /**
     * Lädt einen Tag anhand des Namens
     */
    public static function getByName(string $name): ?self
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_tags') . ' WHERE name = ?', [$name]);
        
        if ($sql->getRows() === 0) {
            return null;
        }

        return self::fromSql($sql);
    }

    /**
     * Gibt alle Tags zurück
     */
    public static function getAll(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_tags') . ' ORDER BY name ASC');

        $tags = [];
        foreach ($sql as $row) {
            $tags[] = self::fromSql($row);
        }

        return $tags;
    }

    /**
     * Gibt alle Tags eines Issues zurück
     */
    public static function getByIssue(int $issueId): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT t.* 
            FROM ' . rex::getTable('issue_tracker_tags') . ' t
            INNER JOIN ' . rex::getTable('issue_tracker_issue_tags') . ' it ON t.id = it.tag_id
            WHERE it.issue_id = ?
            ORDER BY t.name ASC
        ', [$issueId]);

        $tags = [];
        foreach ($sql as $row) {
            $tags[] = self::fromSql($row);
        }

        return $tags;
    }

    /**
     * Erstellt einen Tag aus SQL-Daten
     */
    private static function fromSql(rex_sql $sql): self
    {
        $tag = new self();
        $tag->id = (int) $sql->getValue('id');
        $tag->name = (string) $sql->getValue('name');
        $tag->color = (string) $sql->getValue('color');
        $tag->createdAt = new DateTime((string) $sql->getValue('created_at'));

        return $tag;
    }

    /**
     * Speichert den Tag
     */
    public function save(): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_tags'));

        $sql->setValue('name', $this->name);
        $sql->setValue('color', $this->color);

        if ($this->id > 0) {
            $sql->setWhere(['id' => $this->id]);
            $sql->update();
        } else {
            $sql->setValue('created_at', date('Y-m-d H:i:s'));
            $sql->insert();
            $this->id = (int) $sql->getLastId();
        }

        return true;
    }

    /**
     * Löscht den Tag
     */
    public function delete(): bool
    {
        if ($this->id === 0) {
            return false;
        }

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('issue_tracker_tags') . ' WHERE id = ?', [$this->id]);

        return true;
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

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }
}
