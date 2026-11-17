<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker\Review;

use rex;
use rex_backend_login;
use rex_request;
use rex_response;
use rex_user;

/**
 * Wer kommentiert gerade im Frontend?
 *
 *  - Gast: persönlicher Link (?it_review=…) → Cookie „it_review“ (HttpOnly, SameSite=Lax), bei jedem Aufruf neu geprüft
 *  - Backend-Benutzer: bestehende Backend-Sitzung; sieht aktive Runden, an denen er teilnimmt
 *    (Admins und Issue-Manager: alle aktiven Runden)
 *
 * @package issue_tracker
 */
final class Reviewer
{
    public const COOKIE = 'it_review';
    /** Backend-Benutzer haben den Review-Modus beendet (bis „Im Frontend öffnen“ ?it_review=on) */
    public const OFF_COOKIE = 'it_review_off';

    /** @var list<Round> */
    public array $rounds = [];
    public ?Participant $guest = null;
    public ?rex_user $user = null;

    private static ?self $current = null;
    private static bool $resolved = false;

    private function __construct()
    {
    }

    public static function current(): ?self
    {
        if (!self::$resolved) {
            self::$resolved = true;
            self::$current = self::resolve();
        }
        return self::$current;
    }

    private static function resolve(): ?self
    {
        // Gast über Cookie
        $cookie = rex_request::cookie(self::COOKIE, 'string', '');
        if ('' !== $cookie) {
            $linkId = Links::linkIdFromCookie($cookie);
            $hit = $linkId > 0 ? Links::resolveId($linkId) : null;
            if (null !== $hit) {
                $me = new self();
                $me->guest = $hit['participant'];
                $me->rounds = [$hit['round']];
                $hit['participant']->touch();
                return $me;
            }
            self::clearCookie();
        }

        // Backend-Benutzer (nur wenn schon ein Sitzungs-Cookie da ist – startet sonst keine Sitzung)
        if ('1' === rex_request::cookie(self::OFF_COOKIE, 'string', '')) {
            return null;
        }
        $user = rex::isBackend() ? rex::getUser() : rex_backend_login::createUser();
        if (null === $user || !($user->isAdmin() || $user->hasPerm('issue_tracker[]'))) {
            return null;
        }
        $manager = $user->isAdmin() || $user->hasPerm('issue_tracker[issue_manager]');
        $mine = Participant::roundIdsOfUser((int) $user->getId());
        $rounds = array_values(array_filter(Round::active(), static fn (Round $r): bool => $manager || in_array($r->id, $mine, true)));
        if ([] === $rounds) {
            return null;
        }
        $me = new self();
        $me->user = $user;
        $me->rounds = $rounds;
        foreach ($rounds as $round) {
            Participant::forUser($round->id, (int) $user->getId())?->touch();
        }
        return $me;
    }

    /**
     * Link mit Schlüssel einlösen: Cookie setzen und ohne Schlüssel in der Adresse weiterleiten
     * (der Schlüssel landet so weder im Verlauf noch im Referer). Ungültige Links führen ohne Leiste weiter.
     */
    public static function redeem(string $token): void
    {
        if ('on' === $token) {
            // „Im Frontend öffnen“ aus dem Backend: beendeten Review-Modus wieder einschalten
            self::switchOn();
        }
        $hit = Links::resolve($token);
        if (null !== $hit) {
            self::setCookie(Links::cookieValue($hit['link_id']));
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $target = (string) preg_replace('/([?&])' . Links::PARAM . '=[^&#]*(&|$)/', '$1', $uri);
        $target = rtrim($target, '?&');
        header('Referrer-Policy: no-referrer');
        rex_response::sendRedirect('' !== $target ? $target : '/');
    }

    public static function logout(): void
    {
        self::clearCookie();
    }

    /** Review-Modus für Backend-Benutzer beenden: keine Leiste, keine Marker (bis ?it_review=on) */
    public static function switchOff(): void
    {
        setcookie(self::OFF_COOKIE, '1', ['expires' => time() + 86400 * 365, 'path' => '/', 'secure' => rex_request::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
    }

    public static function switchOn(): void
    {
        if (!headers_sent()) {
            setcookie(self::OFF_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }
        $_COOKIE[self::OFF_COOKIE] = '';
    }

    private static function setCookie(string $value): void
    {
        setcookie(self::COOKIE, $value, [
            'expires' => time() + 86400 * Links::DEFAULT_DAYS,
            'path' => '/',
            'secure' => rex_request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearCookie(): void
    {
        if (!headers_sent()) {
            setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }
    }

    public function isGuest(): bool
    {
        return null !== $this->guest;
    }

    /** Kennung für Begrenzung und Zuordnung: „g12“ (Gast) bzw. „u3“ (Benutzer) */
    public function actor(): string
    {
        return null !== $this->guest ? 'g' . $this->guest->id : 'u' . (int) $this->user?->getId();
    }

    public function name(): string
    {
        if (null !== $this->guest) {
            return '' !== $this->guest->name ? $this->guest->name : $this->guest->email;
        }
        return (string) ($this->user?->getValue('name') ?: $this->user?->getLogin());
    }

    public function round(int $id): ?Round
    {
        foreach ($this->rounds as $round) {
            if ($round->id === $id) {
                return $round;
            }
        }
        return null;
    }

    /** @return list<int> */
    public function roundIds(): array
    {
        return array_map(static fn (Round $r): int => $r->id, $this->rounds);
    }

    /** Darf der Reviewer die Agentur-Funktionen nutzen (alle Runden, im Backend öffnen)? */
    public function isAgency(): bool
    {
        return null !== $this->user && ($this->user->isAdmin() || $this->user->hasPerm('issue_tracker[issue_manager]'));
    }
}
