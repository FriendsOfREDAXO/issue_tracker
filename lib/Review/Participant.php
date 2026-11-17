<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker\Review;

use rex;
use rex_sql;
use rex_user;

/**
 * Teilnehmer einer Review-Runde: Backend-Benutzer (userId > 0) oder Gast mit Name und E-Mail.
 * Gäste melden sich über persönliche Links an (Links), Backend-Benutzer über ihre Backend-Sitzung.
 *
 * @package issue_tracker
 */
final class Participant
{
    public int $id = 0;
    public int $roundId = 0;
    public int $userId = 0;
    public string $name = '';
    public string $email = '';
    public bool $notify = true;
    public ?string $invitedAt = null;
    public ?string $lastSeenAt = null;
    public ?string $revokedAt = null;

    /** @var array<int, string> */
    private static array $names = [];

    public static function table(): string
    {
        return rex::getTable('issue_tracker_review_participants');
    }

    public static function get(int $id): ?self
    {
        if ($id <= 0) {
            return null;
        }
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE id = ?', [$id]);
        return [] === $rows ? null : self::fromRow($rows[0]);
    }

    /** @return list<self> */
    public static function byRound(int $roundId): array
    {
        return array_map(self::fromRow(...), rex_sql::factory()->getArray(
            'SELECT * FROM ' . self::table() . ' WHERE round_id = ? ORDER BY user_id = 0, name, id',
            [$roundId],
        ));
    }

    /** Teilnahme eines Backend-Benutzers an einer Runde */
    public static function forUser(int $roundId, int $userId): ?self
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT * FROM ' . self::table() . ' WHERE round_id = ? AND user_id = ? AND revoked_at IS NULL',
            [$roundId, $userId],
        );
        return [] === $rows ? null : self::fromRow($rows[0]);
    }

    /** @return list<int> Runden, an denen der Backend-Benutzer teilnimmt */
    public static function roundIdsOfUser(int $userId): array
    {
        return array_map('intval', array_column(rex_sql::factory()->getArray(
            'SELECT round_id FROM ' . self::table() . ' WHERE user_id = ? AND revoked_at IS NULL',
            [$userId],
        ), 'round_id'));
    }

    /** Anzeigename eines Gasts für Issues und Kommentare: „Name (Gast)“ */
    public static function displayName(int $id, string $fallback = ''): string
    {
        if (!isset(self::$names[$id])) {
            $participant = self::get($id);
            self::$names[$id] = null === $participant ? '' : $participant->label();
        }
        return '' !== self::$names[$id] ? self::$names[$id] : $fallback;
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        $p = new self();
        $p->id = (int) $row['id'];
        $p->roundId = (int) $row['round_id'];
        $p->userId = (int) $row['user_id'];
        $p->name = (string) ($row['name'] ?? '');
        $p->email = (string) ($row['email'] ?? '');
        $p->notify = (bool) $row['notify'];
        $p->invitedAt = $row['invited_at'] ? (string) $row['invited_at'] : null;
        $p->lastSeenAt = $row['last_seen_at'] ? (string) $row['last_seen_at'] : null;
        $p->revokedAt = $row['revoked_at'] ? (string) $row['revoked_at'] : null;
        return $p;
    }

    public function isGuest(): bool
    {
        return 0 === $this->userId;
    }

    public function isActive(): bool
    {
        return null === $this->revokedAt;
    }

    public function user(): ?rex_user
    {
        return $this->userId > 0 ? rex_user::get($this->userId) : null;
    }

    /** Name für die Anzeige (Gäste mit Zusatz „(Gast)“) */
    public function label(): string
    {
        if (!$this->isGuest()) {
            $user = $this->user();
            return null === $user ? '#' . $this->userId : (string) ($user->getValue('name') ?: $user->getLogin());
        }
        return ('' !== $this->name ? $this->name : $this->email) . ' (' . \rex_i18n::msg('issue_tracker_review_guest') . ')';
    }

    public function save(): void
    {
        $sql = rex_sql::factory()->setTable(self::table());
        $sql->setValue('round_id', $this->roundId);
        $sql->setValue('user_id', $this->userId);
        $sql->setValue('name', '' !== $this->name ? mb_substr($this->name, 0, 100) : null);
        $sql->setValue('email', '' !== $this->email ? mb_substr($this->email, 0, 190) : null);
        $sql->setValue('notify', $this->notify ? 1 : 0);
        $sql->setValue('invited_at', $this->invitedAt);
        $sql->setValue('revoked_at', $this->revokedAt);
        if ($this->id > 0) {
            $sql->setWhere(['id' => $this->id])->update();
            return;
        }
        $sql->setValue('created_by', (int) (rex::getUser()?->getId() ?? 0));
        $sql->setValue('created_at', date('Y-m-d H:i:s'));
        $sql->insert();
        $this->id = (int) $sql->getLastId();
    }

    /** Zugang sperren: alle Links ungültig, offene Sitzungen enden beim nächsten Aufruf */
    public function revoke(): void
    {
        $this->revokedAt = date('Y-m-d H:i:s');
        $this->save();
        Links::deleteForParticipant($this->id);
    }

    public function delete(): void
    {
        Links::deleteForParticipant($this->id);
        rex_sql::factory()->setQuery('DELETE FROM ' . self::table() . ' WHERE id = ?', [$this->id]);
    }

    public function touch(): void
    {
        // höchstens einmal pro Minute schreiben
        if (null !== $this->lastSeenAt && strtotime($this->lastSeenAt) > time() - 60) {
            return;
        }
        $this->lastSeenAt = date('Y-m-d H:i:s');
        rex_sql::factory()->setQuery('UPDATE ' . self::table() . ' SET last_seen_at = ? WHERE id = ?', [$this->lastSeenAt, $this->id]);
    }
}
