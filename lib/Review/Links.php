<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker\Review;

use rex;
use rex_config;
use rex_sql;

/**
 * Persönliche Zugangslinks der Gäste.
 *
 * Der Schlüssel (43 Zeichen, base64url aus 32 Zufallsbytes) steht nur im Link; gespeichert wird sein Hash mit einem
 * geheimen Wert der Installation. Ein Datenbank-Abzug verrät also keine Links. Jeder Aufruf prüft Link, Teilnehmer
 * und Runde neu – Sperren wirkt sofort.
 *
 * @package issue_tracker
 */
final class Links
{
    public const PARAM = 'it_review';
    public const DEFAULT_DAYS = 30;

    public static function table(): string
    {
        return rex::getTable('issue_tracker_review_links');
    }

    /** Geheimer Wert der Installation (wird beim ersten Gebrauch erzeugt) */
    public static function secret(): string
    {
        $secret = (string) rex_config::get('issue_tracker', 'review_secret', '');
        if (strlen($secret) < 32) {
            $secret = bin2hex(random_bytes(32));
            rex_config::set('issue_tracker', 'review_secret', $secret);
        }
        return $secret;
    }

    public static function hash(string $token): string
    {
        return hash_hmac('sha256', 'review-link|' . $token, self::secret());
    }

    /** Neuen Link anlegen; gibt den Schlüssel zurück (nur jetzt im Klartext verfügbar) */
    public static function create(Participant $participant, ?Round $round = null, ?int $days = null): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = time() + 86400 * ($days ?? self::DEFAULT_DAYS);
        if (null !== $round?->expiresAt) {
            $expires = min($expires, $round->expiresAt->getTimestamp());
        }
        rex_sql::factory()->setTable(self::table())
            ->setValue('participant_id', $participant->id)
            ->setValue('token_hash', self::hash($token))
            ->setValue('created_at', date('Y-m-d H:i:s'))
            ->setValue('expires_at', date('Y-m-d H:i:s', $expires))
            ->insert();
        self::cleanup();
        return $token;
    }

    /** Link zur Adresse mit Schlüssel (und optional Sprung zu einer Anmerkung) */
    public static function url(string $pageUrl, string $token, int $issueId = 0): string
    {
        $url = preg_replace('/#.*$/', '', $pageUrl);
        $url .= (str_contains((string) $url, '?') ? '&' : '?') . self::PARAM . '=' . rawurlencode($token);
        return $url . ($issueId > 0 ? '#it-review-' . $issueId : '');
    }

    /**
     * Schlüssel prüfen.
     *
     * @return array{link_id: int, participant: Participant, round: Round}|null
     */
    public static function resolve(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return null;
        }
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE token_hash = ?', [self::hash($token)]);
        return [] === $rows ? null : self::check($rows[0]);
    }

    /**
     * Gespeicherten Link (Cookie) prüfen.
     *
     * @return array{link_id: int, participant: Participant, round: Round}|null
     */
    public static function resolveId(int $linkId): ?array
    {
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE id = ?', [$linkId]);
        return [] === $rows ? null : self::check($rows[0]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{link_id: int, participant: Participant, round: Round}|null
     */
    private static function check(array $row): ?array
    {
        if (strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        $participant = Participant::get((int) $row['participant_id']);
        if (null === $participant || !$participant->isActive() || !$participant->isGuest()) {
            return null;
        }
        $round = Round::get($participant->roundId);
        if (null === $round || !$round->isOpen()) {
            return null;
        }
        if (null === $row['last_used_at'] || strtotime((string) $row['last_used_at']) < time() - 60) {
            rex_sql::factory()->setQuery('UPDATE ' . self::table() . ' SET last_used_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int) $row['id']]);
        }
        return ['link_id' => (int) $row['id'], 'participant' => $participant, 'round' => $round];
    }

    /** Wert für das Sitzungs-Cookie: Link-ID und Signatur (ändert sich, wenn der Link gelöscht und neu angelegt wird) */
    public static function cookieValue(int $linkId): string
    {
        $row = rex_sql::factory()->getArray('SELECT token_hash FROM ' . self::table() . ' WHERE id = ?', [$linkId]);
        $hash = (string) ($row[0]['token_hash'] ?? '');
        return $linkId . '.' . hash_hmac('sha256', 'review-cookie|' . $linkId . '|' . $hash, self::secret());
    }

    public static function linkIdFromCookie(string $value): int
    {
        if (!preg_match('/^(\d{1,10})\.([a-f0-9]{64})$/', $value, $m)) {
            return 0;
        }
        return hash_equals(self::cookieValue((int) $m[1]), $value) ? (int) $m[1] : 0;
    }

    public static function deleteForParticipant(int $participantId): void
    {
        rex_sql::factory()->setQuery('DELETE FROM ' . self::table() . ' WHERE participant_id = ?', [$participantId]);
    }

    private static function cleanup(): void
    {
        rex_sql::factory()->setQuery('DELETE FROM ' . self::table() . ' WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}
