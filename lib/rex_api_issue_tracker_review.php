<?php

use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\Review\Reviewer;
use FriendsOfREDAXO\IssueTracker\Review\ReviewService;

/**
 * JSON-Schnittstelle der Review-Leiste im Frontend.
 *
 *   GET  ?rex-api-call=issue_tracker_review&func=list&path=/seite/   Anmerkungen dieser Seite (all=1: alle Seiten)
 *   POST func=create   neue Anmerkung (JSON-Körper)
 *   POST func=reply    Antwort (issue_id, text, parent_id)
 *   POST func=end      Review-Modus beenden (Gast: abmelden; Backend-Benutzer: Leiste aus bis „Im Frontend öffnen“)
 *
 * Schutz: Zugang wie im Frontend (Reviewer::current), POST nur mit Kopfzeile „X-IT-Review: 1“ und passender Herkunft
 * (Origin) – ein fremdes Formular kann beides nicht setzen –, Honigtopf-Feld, Begrenzung je Person und Aktion.
 *
 * @package issue_tracker
 */
class rex_api_issue_tracker_review extends rex_api_function
{
    protected $published = true;

    /** Aktion => [Anzahl, Sekunden] */
    private const LIMITS = ['create' => [20, 600], 'reply' => [30, 600]];

    public function execute(): never
    {
        rex_response::cleanOutputBuffers();
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');

        $reviewer = Reviewer::current();
        if (null === $reviewer) {
            $this->send(['error' => rex_i18n::msg('issue_tracker_review_err_access')], 403);
        }

        $func = rex_request('func', 'string', 'list');
        if ('list' === $func) {
            $roundIds = $reviewer->roundIds();
            $round = rex_get('round', 'int', 0);
            if ($round > 0) {
                $roundIds = in_array($round, $roundIds, true) ? [$round] : [];
            }
            $path = rex_get('all', 'bool', false) ? null : rex_get('path', 'string', '/');
            $this->send(['items' => ReviewService::items($reviewer, $roundIds, $path)]);
        }

        if ('post' !== rex_request_method() || '1' !== ($_SERVER['HTTP_X_IT_REVIEW'] ?? '') || !$this->sameOrigin()) {
            $this->send(['error' => rex_i18n::msg('issue_tracker_review_err_request')], 400);
        }

        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];

        // Honigtopf: ausgefüllt = Bot – freundlich antworten, nichts speichern
        if ('' !== trim((string) ($data['website'] ?? ''))) {
            $this->send(['ok' => true, 'items' => []]);
        }

        try {
            switch ($func) {
                case 'create':
                    $this->limit($reviewer, 'create');
                    $round = $reviewer->round((int) ($data['round'] ?? 0)) ?? ($reviewer->rounds[0] ?? null);
                    if (null === $round) {
                        $this->send(['error' => rex_i18n::msg('issue_tracker_review_err_access')], 403);
                    }
                    $issue = ReviewService::create($reviewer, $round, $data);
                    $this->send(['ok' => true, 'id' => $issue->getId()]);
                    // no break
                case 'reply':
                    $this->limit($reviewer, 'reply');
                    $issue = $this->issue($reviewer, (int) ($data['issue_id'] ?? 0));
                    ReviewService::reply($reviewer, $issue, (string) ($data['text'] ?? ''), (int) ($data['parent_id'] ?? 0));
                    $this->send(['ok' => true]);
                    // no break
                case 'end':
                    if ($reviewer->isGuest()) {
                        Reviewer::logout();
                    } else {
                        Reviewer::switchOff();
                    }
                    $this->send(['ok' => true]);
            }
        } catch (RuntimeException $e) {
            $this->send(['error' => $e->getMessage()], 422);
        }
        $this->send(['error' => 'unknown func'], 400);
    }

    /** Issue nur, wenn es zu einer Runde des Reviewers gehört */
    private function issue(Reviewer $reviewer, int $id): Issue
    {
        $anchor = ReviewService::anchor($id);
        $issue = null !== $anchor && in_array((int) $anchor['round_id'], $reviewer->roundIds(), true) ? Issue::get($id) : null;
        if (null === $issue || $issue->getIsPrivate()) {
            $this->send(['error' => rex_i18n::msg('issue_tracker_review_err_access')], 404);
        }
        return $issue;
    }

    private function sameOrigin(): bool
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ('' === $origin) {
            // ältere Browser schicken bei gleicher Herkunft kein Origin – dann den Referer prüfen
            $origin = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        }
        $host = (string) parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $own = (string) ($_SERVER['HTTP_HOST'] ?? '');
        return '' !== $host && strtolower($host . (null !== $port ? ':' . $port : '')) === strtolower($own);
    }

    private function limit(Reviewer $reviewer, string $action): void
    {
        [$max, $seconds] = self::LIMITS[$action];
        $table = rex::getTable('issue_tracker_review_hits');
        $sql = rex_sql::factory();
        $count = (int) ($sql->getArray(
            'SELECT COUNT(*) AS n FROM ' . $table . ' WHERE actor = ? AND action = ? AND created_at > ?',
            [$reviewer->actor(), $action, date('Y-m-d H:i:s', time() - $seconds)],
        )[0]['n'] ?? 0);
        if ($count >= $max) {
            header('Retry-After: ' . $seconds);
            $this->send(['error' => rex_i18n::msg('issue_tracker_review_err_limit')], 429);
        }
        $sql->setTable($table)->setValue('actor', $reviewer->actor())->setValue('action', $action)->setValue('created_at', date('Y-m-d H:i:s'))->insert();
        if (1 === random_int(1, 50)) {
            rex_sql::factory()->setQuery('DELETE FROM ' . $table . ' WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        }
    }

    /** @param array<string, mixed> $data */
    private function send(array $data, int $status = 200): never
    {
        rex_response::setStatus(200 === $status ? rex_response::HTTP_OK : $status . ' ' . ([400 => 'Bad Request', 403 => 'Forbidden', 404 => 'Not Found', 422 => 'Unprocessable Content', 429 => 'Too Many Requests'][$status] ?? 'Error'));
        rex_response::sendJson($data);
        exit;
    }
}
