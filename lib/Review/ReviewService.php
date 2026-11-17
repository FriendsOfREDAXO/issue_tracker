<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker\Review;

use FriendsOfREDAXO\IssueTracker\Attachment;
use FriendsOfREDAXO\IssueTracker\Comment;
use FriendsOfREDAXO\IssueTracker\HistoryService;
use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\NotificationService;
use rex;
use rex_article;
use rex_i18n;
use rex_path;
use rex_sql;
use RuntimeException;

/**
 * Anmerkungen aus dem Frontend: als Issue anlegen, beantworten, für eine Seite auflisten.
 *
 * @package issue_tracker
 */
final class ReviewService
{
    public const KINDS = ['note', 'wish', 'bug', 'question'];
    public const MAX_TEXT = 4000;
    public const MAX_SCREENSHOT = 4 * 1024 * 1024;

    /**
     * Schlüssel einer Seite für den Vergleich: Pfad ohne Schrägstrich am Ende; ohne URL-Rewriting (index.php?article_id=…)
     * gehören article_id und clang dazu, andere Abfrage-Parameter nicht. Gleiche Regel in it-review.js (pageKey).
     */
    public static function normalizePath(string $path): string
    {
        $query = (string) parse_url($path, PHP_URL_QUERY);
        $path = (string) parse_url($path, PHP_URL_PATH);
        $path = '/' . ltrim(rawurldecode($path), '/');
        $path = '/' !== $path ? rtrim($path, '/') : '/';
        parse_str($query, $params);
        $keep = [];
        foreach (['article_id', 'clang'] as $name) {
            if (isset($params[$name]) && is_scalar($params[$name]) && '' !== (string) $params[$name]) {
                $keep[] = $name . '=' . (int) $params[$name];
            }
        }
        return mb_substr($path . ([] !== $keep ? '?' . implode('&', $keep) : ''), 0, 500);
    }

    /** Prüfsumme der Inhalte eines Slices (Werte, Medien, Links) – zeigt „seitdem geändert“ */
    public static function sliceHash(int $sliceId): ?string
    {
        if ($sliceId <= 0) {
            return null;
        }
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable('article_slice') . ' WHERE id = ?', [$sliceId]);
        if ([] === $rows) {
            return null;
        }
        $values = array_filter(
            $rows[0],
            static fn (string $key): bool => (bool) preg_match('/^(value|media|medialist|link|linklist)\d+$/', $key) || 'module_id' === $key,
            ARRAY_FILTER_USE_KEY,
        );
        return md5((string) json_encode($values));
    }

    /**
     * Neue Anmerkung → Issue (Kategorie und Projekt der Runde, zuständig wie in der Runde), Anker, optional Screenshot.
     *
     * @param array<string, mixed> $data
     */
    public static function create(Reviewer $reviewer, Round $round, array $data): Issue
    {
        $text = self::cleanText((string) ($data['text'] ?? ''));
        if ('' === $text) {
            throw new RuntimeException(rex_i18n::msg('issue_tracker_review_err_text'));
        }
        $kind = in_array($data['kind'] ?? '', self::KINDS, true) ? (string) $data['kind'] : 'note';
        $path = self::normalizePath((string) ($data['path'] ?? '/'));
        $articleId = (int) ($data['article_id'] ?? 0);
        $clangId = (int) ($data['clang_id'] ?? 0);
        $article = $articleId > 0 ? rex_article::get($articleId, $clangId ?: null) : null;
        if (!$round->coversArticle($article?->getId())) {
            throw new RuntimeException(rex_i18n::msg('issue_tracker_review_err_scope'));
        }

        $issue = new Issue();
        $firstLine = trim((string) strtok($text, "\n"));
        $issue->setTitle(mb_strlen($firstLine) > 90 ? rtrim(mb_substr($firstLine, 0, 88)) . ' …' : $firstLine);
        $issue->setDescription($text);
        $issue->setCategory('' !== $round->category ? $round->category : 'Review');
        $issue->setStatus('open');
        $issue->setPriority('bug' === $kind ? 'high' : 'normal');
        $issue->setProjectId($round->projectId);
        $issue->setAssignedUserId($round->assignedUserId);
        if (null !== $article) {
            $issue->setAffectedArticle($article->getId(), $article->getClangId());
        }
        $url = (string) ($data['url'] ?? '');
        if ('' !== $url && Issue::isValidAffectedUrl($url)) {
            $issue->setAffectedUrl(mb_substr($url, 0, 500));
        }
        $issue->setCreatedBy($reviewer->isGuest() ? 0 : (int) $reviewer->user?->getId());
        $issue->setGuestId($reviewer->guest->id ?? 0);
        $issue->save();

        $anchor = is_array($data['anchor'] ?? null) ? $data['anchor'] : [];
        $sliceId = (int) ($anchor['slice_id'] ?? 0);
        $sql = rex_sql::factory()->setTable(rex::getTable('issue_tracker_review_anchors'));
        $sql->setValue('issue_id', $issue->getId());
        $sql->setValue('round_id', $round->id);
        $sql->setValue('kind', $kind);
        $sql->setValue('path', $path);
        $sql->setValue('url', '' !== $url ? mb_substr($url, 0, 1000) : null);
        $sql->setValue('page_title', mb_substr(self::cleanLine((string) ($data['page_title'] ?? '')), 0, 255) ?: null);
        $sql->setValue('article_id', $article?->getId());
        $sql->setValue('clang_id', $article?->getClangId());
        $sql->setValue('slice_id', $sliceId > 0 ? $sliceId : null);
        $sql->setValue('slice_hash', self::sliceHash($sliceId));
        $sql->setValue('selector', self::nullable(mb_substr(self::cleanLine((string) ($anchor['selector'] ?? '')), 0, 1000)));
        $sql->setValue('element_label', self::nullable(mb_substr(self::cleanLine((string) ($anchor['label'] ?? '')), 0, 120)));
        $sql->setValue('text_quote', self::nullable(mb_substr(self::cleanLine((string) ($anchor['quote'] ?? '')), 0, 300)));
        $sql->setValue('rel_x', isset($anchor['x']) ? max(0, min(1, (float) $anchor['x'])) : null);
        $sql->setValue('rel_y', isset($anchor['y']) ? max(0, min(1, (float) $anchor['y'])) : null);
        $sql->setValue('viewport', self::nullable(preg_match('/^\d{2,5}x\d{2,5}$/', (string) ($data['viewport'] ?? '')) ? (string) $data['viewport'] : ''));
        $sql->setValue('user_agent', self::nullable(mb_substr(self::cleanLine((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255)));
        $sql->setValue('created_at', date('Y-m-d H:i:s'));
        $sql->insert();

        $screenshot = (string) ($data['screenshot'] ?? '');
        if ('' !== $screenshot) {
            self::saveScreenshot($issue, $reviewer, $screenshot);
        }

        HistoryService::add($issue->getId(), (int) $issue->getCreatedBy(), 'create');
        NotificationService::notifyNewIssue($issue);
        return $issue;
    }

    /** Antwort auf eine Anmerkung (oder auf einen Kommentar darin) */
    public static function reply(Reviewer $reviewer, Issue $issue, string $text, int $parentId = 0): Comment
    {
        $text = self::cleanText($text);
        if ('' === $text) {
            throw new RuntimeException(rex_i18n::msg('issue_tracker_review_err_text'));
        }
        if ($parentId > 0) {
            $parent = Comment::get($parentId);
            // Antworten nur eine Ebene tief, nie auf interne Kommentare
            if (null === $parent || $parent->getIssueId() !== $issue->getId() || $parent->isInternal()) {
                $parentId = 0;
            } elseif (null !== $parent->getParentCommentId()) {
                $parentId = (int) $parent->getParentCommentId();
            }
        }
        $comment = new Comment();
        $comment->setIssueId($issue->getId());
        $comment->setParentCommentId($parentId > 0 ? $parentId : null);
        $comment->setComment($text);
        $comment->setCreatedBy($reviewer->isGuest() ? 0 : (int) $reviewer->user?->getId());
        $comment->setGuestId($reviewer->guest->id ?? 0);
        $comment->save();
        // Gast antwortet auf eine erledigte Anmerkung → wieder öffnen, damit es nicht untergeht
        if (in_array($issue->getStatus(), ['closed', 'rejected'], true) && $reviewer->isGuest()) {
            $old = $issue->getStatus();
            $issue->setStatus('open');
            $issue->save();
            HistoryService::add($issue->getId(), 0, 'status_change', 'status', $old, 'open');
        }
        NotificationService::notifyNewComment($comment, $issue);
        return $comment;
    }

    /** Anker eines Issues (null = keine Review-Anmerkung) @return array<string, mixed>|null */
    public static function anchor(int $issueId): ?array
    {
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable('issue_tracker_review_anchors') . ' WHERE issue_id = ?', [$issueId]);
        return $rows[0] ?? null;
    }

    /**
     * Anmerkungen der Runden für die Ausgabe im Frontend.
     *
     * @param list<int> $roundIds
     * @return list<array<string, mixed>>
     */
    public static function items(Reviewer $reviewer, array $roundIds, ?string $path = null): array
    {
        if ([] === $roundIds) {
            return [];
        }
        $params = $roundIds;
        $where = 'a.round_id IN (' . implode(',', array_fill(0, count($roundIds), '?')) . ')';
        if (null !== $path) {
            $where .= ' AND a.path = ?';
            $params[] = self::normalizePath($path);
        }
        $rows = rex_sql::factory()->getArray(
            'SELECT a.*, i.title, i.description, i.status, i.created_by, i.guest_id, i.created_at AS issue_created FROM ' . rex::getTable('issue_tracker_review_anchors') . ' a'
            . ' JOIN ' . rex::getTable('issue_tracker_issues') . ' i ON i.id = a.issue_id'
            . ' WHERE ' . $where . ' AND i.is_private = 0 ORDER BY i.created_at',
            $params,
        );
        $statuses = self::statusLabels();
        $items = [];
        $hashes = [];
        foreach ($rows as $row) {
            $issueId = (int) $row['issue_id'];
            $issue = Issue::get($issueId);
            if (null === $issue) {
                continue;
            }
            $sliceId = (int) $row['slice_id'];
            if ($sliceId > 0 && !array_key_exists($sliceId, $hashes)) {
                $hashes[$sliceId] = self::sliceHash($sliceId);
            }
            $status = (string) $row['status'];
            $items[] = [
                'id' => $issueId,
                'round' => (int) $row['round_id'],
                'kind' => (string) $row['kind'],
                'title' => (string) $row['title'],
                'text' => (string) $row['description'],
                'status' => $status,
                'statusLabel' => $statuses[$status] ?? $status,
                'done' => in_array($status, ['closed', 'rejected'], true),
                'author' => $issue->getAuthorName('?'),
                'own' => self::isOwn($reviewer, (int) $row['created_by'], (int) $row['guest_id']),
                'created' => self::date((string) $row['issue_created']),
                'page' => ['path' => (string) $row['path'], 'url' => (string) ($row['url'] ?? ''), 'title' => (string) ($row['page_title'] ?? '')],
                'anchor' => [
                    'slice' => $sliceId,
                    'selector' => (string) ($row['selector'] ?? ''),
                    'label' => (string) ($row['element_label'] ?? ''),
                    'quote' => (string) ($row['text_quote'] ?? ''),
                    'x' => null !== $row['rel_x'] ? (float) $row['rel_x'] : null,
                    'y' => null !== $row['rel_y'] ? (float) $row['rel_y'] : null,
                    // Slice gelöscht (null) oder Inhalt geändert
                    'changed' => $sliceId > 0 && ($hashes[$sliceId] ?? null) !== $row['slice_hash'],
                    'gone' => $sliceId > 0 && null === ($hashes[$sliceId] ?? null),
                ],
                'comments' => self::publicComments($reviewer, $issue),
                'backend' => $reviewer->isGuest() ? null : self::backendUrl($issueId),
            ];
        }
        return $items;
    }

    /** Öffentliche Kommentare (nie interne) @return list<array<string, mixed>> */
    private static function publicComments(Reviewer $reviewer, Issue $issue): array
    {
        $list = [];
        foreach (Comment::getByIssue($issue->getId()) as $comment) {
            foreach (array_merge([$comment], $comment->getReplies()) as $c) {
                if ($c->isInternal()) {
                    continue;
                }
                $list[] = [
                    'id' => $c->getId(),
                    'parent' => (int) ($c->getParentCommentId() ?? 0),
                    'author' => $c->getAuthorName('?'),
                    'agency' => 0 === $c->getGuestId(),
                    'own' => self::isOwn($reviewer, $c->getCreatedBy(), $c->getGuestId()),
                    'text' => $c->getComment(),
                    'created' => self::date($c->getCreatedAt()?->format('Y-m-d H:i:s') ?? ''),
                    'solution' => $c->isSolution(),
                ];
            }
        }
        return $list;
    }

    private static function isOwn(Reviewer $reviewer, int $createdBy, int $guestId): bool
    {
        return $reviewer->isGuest() ? $guestId === $reviewer->guest?->id : (0 === $guestId && $createdBy === (int) $reviewer->user?->getId());
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        $labels = [];
        $rows = rex_sql::factory()->getArray('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "statuses"');
        $list = json_decode((string) ($rows[0]['setting_value'] ?? ''), true);
        if (is_array($list)) {
            foreach ($list as $key => $label) {
                $labels[(string) $key] = (string) $label;
            }
        }
        foreach (['open', 'in_progress', 'planned', 'info', 'closed', 'rejected'] as $key) {
            if (!isset($labels[$key]) && rex_i18n::hasMsg('issue_tracker_status_' . $key)) {
                $labels[$key] = rex_i18n::msg('issue_tracker_status_' . $key);
            }
        }
        return $labels;
    }

    public static function backendUrl(int $issueId): string
    {
        return \rex_url::backendController(['page' => 'issue_tracker/issues/view', 'issue_id' => $issueId], false);
    }

    private static function saveScreenshot(Issue $issue, Reviewer $reviewer, string $dataUrl): void
    {
        if (!preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return;
        }
        $binary = base64_decode($m[2], true);
        if (false === $binary || strlen($binary) > self::MAX_SCREENSHOT) {
            return;
        }
        // nur echte Bilder speichern (Bild neu einlesen lässt Fremdinhalte nicht durch)
        $info = @getimagesizefromstring($binary);
        $ext = ['png' => 'png', 'jpeg' => 'jpg', 'webp' => 'webp'][$m[1]];
        if (false === $info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return;
        }
        $filename = uniqid('review_', true) . '.' . $ext;
        $filename = str_replace('.', '', substr($filename, 0, -strlen($ext) - 1)) . '.' . $ext;
        $target = rex_path::addonData('issue_tracker', 'attachments/' . $filename);
        \rex_dir::create(dirname($target));
        if (false === file_put_contents($target, $binary)) {
            return;
        }
        $attachment = new Attachment();
        $attachment->setIssueId($issue->getId());
        $attachment->setFilename($filename);
        $attachment->setOriginalFilename('screenshot-' . date('Y-m-d-His') . '.' . $ext);
        $attachment->setMimetype($info['mime']);
        $attachment->setFilesize(strlen($binary));
        $attachment->setCreatedBy($reviewer->isGuest() ? 0 : (int) $reviewer->user?->getId());
        $attachment->save();
    }

    /** Kurzer Browsername aus dem User-Agent („Safari · iOS“) */
    public static function browserName(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };
        return $browser . ('' !== $os ? ' · ' . $os : '');
    }

    /** Klartext: Steuerzeichen entfernen, Zeilenumbrüche behalten, Länge begrenzen */
    private static function cleanText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[^\P{C}\n\t]/u', '', $text);
        return trim(mb_substr($text, 0, self::MAX_TEXT));
    }

    private static function cleanLine(string $text): string
    {
        return trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $text));
    }

    private static function nullable(string $value): ?string
    {
        return '' !== $value ? $value : null;
    }

    private static function date(string $value): string
    {
        return '' !== $value ? date('c', (int) strtotime($value)) : '';
    }
}
