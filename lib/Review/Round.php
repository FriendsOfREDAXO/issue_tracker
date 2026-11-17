<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker\Review;

use DateTimeImmutable;
use rex;
use rex_sql;

/**
 * Review-Runde: Wer darf welche Seiten im Frontend kommentieren, in welches Projekt landen die Anmerkungen,
 * wer ist zuständig. Aktive Runden ohne abgelaufenes Datum sind im Frontend sichtbar.
 *
 * @package issue_tracker
 */
final class Round
{
    public int $id = 0;
    public string $title = '';
    public string $description = '';
    public string $status = 'active';
    public ?int $projectId = null;
    public ?int $assignedUserId = null;
    public string $category = '';
    public ?int $startArticleId = null;
    /** @var list<int> leer = ganze Website */
    public array $articleIds = [];
    public ?DateTimeImmutable $expiresAt = null;
    public int $createdBy = 0;
    public ?DateTimeImmutable $createdAt = null;

    public static function table(): string
    {
        return rex::getTable('issue_tracker_review_rounds');
    }

    public static function get(int $id): ?self
    {
        if ($id <= 0) {
            return null;
        }
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE id = ?', [$id]);
        return [] === $rows ? null : self::fromRow($rows[0]);
    }

    /** @return list<self> alle Runden, neueste zuerst */
    public static function all(): array
    {
        return array_map(self::fromRow(...), rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' ORDER BY status = "active" DESC, created_at DESC'));
    }

    /** @return list<self> aktive, nicht abgelaufene Runden */
    public static function active(): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT * FROM ' . self::table() . ' WHERE status = "active" AND (expires_at IS NULL OR expires_at > ?) ORDER BY created_at DESC',
            [date('Y-m-d H:i:s')],
        );
        return array_map(self::fromRow(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        $round = new self();
        $round->id = (int) $row['id'];
        $round->title = (string) $row['title'];
        $round->description = (string) ($row['description'] ?? '');
        $round->status = (string) $row['status'];
        $round->projectId = $row['project_id'] ? (int) $row['project_id'] : null;
        $round->assignedUserId = $row['assigned_user_id'] ? (int) $row['assigned_user_id'] : null;
        $round->category = (string) ($row['category'] ?? '');
        $round->startArticleId = $row['start_article_id'] ? (int) $row['start_article_id'] : null;
        $ids = json_decode((string) ($row['article_ids'] ?? ''), true);
        $round->articleIds = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
        $round->expiresAt = $row['expires_at'] ? new DateTimeImmutable((string) $row['expires_at']) : null;
        $round->createdBy = (int) $row['created_by'];
        $round->createdAt = $row['created_at'] ? new DateTimeImmutable((string) $row['created_at']) : null;
        return $round;
    }

    public function save(): void
    {
        $sql = rex_sql::factory()->setTable(self::table());
        $sql->setValue('title', $this->title);
        $sql->setValue('description', $this->description);
        $sql->setValue('status', 'closed' === $this->status ? 'closed' : 'active');
        $sql->setValue('project_id', $this->projectId);
        $sql->setValue('assigned_user_id', $this->assignedUserId);
        $sql->setValue('category', '' !== $this->category ? $this->category : null);
        $sql->setValue('start_article_id', $this->startArticleId);
        $sql->setValue('article_ids', [] !== $this->articleIds ? json_encode(array_values($this->articleIds)) : null);
        $sql->setValue('expires_at', $this->expiresAt?->format('Y-m-d H:i:s'));
        $sql->setValue('updated_at', date('Y-m-d H:i:s'));
        if ($this->id > 0) {
            $sql->setWhere(['id' => $this->id])->update();
            return;
        }
        $sql->setValue('created_by', $this->createdBy);
        $sql->setValue('created_at', date('Y-m-d H:i:s'));
        $sql->insert();
        $this->id = (int) $sql->getLastId();
    }

    /** Löscht die Runde samt Teilnehmern, Links und Ankern – die Issues bleiben erhalten. */
    public function delete(): void
    {
        foreach (Participant::byRound($this->id) as $participant) {
            $participant->delete();
        }
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('issue_tracker_review_anchors') . ' WHERE round_id = ?', [$this->id]);
        rex_sql::factory()->setQuery('DELETE FROM ' . self::table() . ' WHERE id = ?', [$this->id]);
    }

    public function isOpen(): bool
    {
        return 'active' === $this->status && (null === $this->expiresAt || $this->expiresAt->getTimestamp() > time());
    }

    /** Gehört der Artikel zum Umfang der Runde? (ohne Artikel: Seiten ohne Strukturartikel, z. B. URL-Profile, immer) */
    public function coversArticle(?int $articleId): bool
    {
        if ([] === $this->articleIds || null === $articleId || $articleId <= 0) {
            return true;
        }
        return in_array($articleId, $this->articleIds, true);
    }

    /** Adresse, mit der Einladungen starten: Startartikel oder Startseite der Website */
    public function startUrl(): string
    {
        $id = $this->startArticleId ?: ($this->articleIds[0] ?? 0) ?: self::siteStartId();
        return \FriendsOfREDAXO\IssueTracker\Issue::toAbsoluteUrl(rex_getUrl($id));
    }

    /** Anzahl der Anmerkungen der Runde (offen / gesamt) @return array{open: int, total: int} */
    public function counts(): array
    {
        $row = rex_sql::factory()->getArray(
            'SELECT COUNT(*) AS total, SUM(i.status NOT IN ("closed", "rejected")) AS open FROM ' . rex::getTable('issue_tracker_review_anchors') . ' a'
            . ' JOIN ' . rex::getTable('issue_tracker_issues') . ' i ON i.id = a.issue_id WHERE a.round_id = ?',
            [$this->id],
        )[0] ?? [];
        return ['open' => (int) ($row['open'] ?? 0), 'total' => (int) ($row['total'] ?? 0)];
    }

    /** Start-Artikel der Website (YRewrite-Domain, sonst System-Einstellung) */
    private static function siteStartId(): int
    {
        if (\rex_addon::get('yrewrite')->isAvailable() && \rex_yrewrite::getCurrentDomain()) {
            return (int) \rex_yrewrite::getCurrentDomain()->getStartId();
        }
        return (int) rex::getProperty('start_article_id', 1);
    }
}
