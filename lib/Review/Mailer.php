<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker\Review;

use FriendsOfREDAXO\IssueTracker\Comment;
use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\NotificationService;
use rex;
use rex_i18n;
use rex_sql;

/**
 * E-Mails an Review-Gäste: Einladung und Antworten der Agentur (mit frischem persönlichem Link zur Stelle).
 * Backend-Teilnehmer bekommen die üblichen Benachrichtigungen des Issue Trackers.
 *
 * @package issue_tracker
 */
final class Mailer
{
    /** Einladung (auch bei abgeschalteten Benachrichtigungen – sie wird ausdrücklich ausgelöst) */
    public static function invite(Participant $participant, Round $round, string $message = ''): bool
    {
        if (!$participant->isGuest() || '' === $participant->email) {
            return false;
        }
        $token = Links::create($participant, $round);
        $url = Links::url($round->startUrl(), $token);
        $inviter = rex::getUser();
        $body = '<p>' . rex_escape(rex_i18n::msg('issue_tracker_review_mail_hello', $participant->name ?: $participant->email)) . '</p>'
            . '<p>' . rex_escape(rex_i18n::msg('issue_tracker_review_mail_invite', (string) ($inviter?->getValue('name') ?: rex::getServerName()), $round->title)) . '</p>'
            . ('' !== trim($message) ? '<blockquote style="margin:16px 0;padding:8px 14px;border-left:3px solid #ccc;color:#444;">' . nl2br(rex_escape(trim($message))) . '</blockquote>' : '')
            . ('' !== trim($round->description) ? '<p>' . nl2br(rex_escape(trim($round->description))) . '</p>' : '')
            . '<p>' . rex_escape(rex_i18n::msg('issue_tracker_review_mail_howto')) . '</p>'
            . self::button($url, rex_i18n::msg('issue_tracker_review_mail_open'))
            . '<p style="color:#666;font-size:12px;">' . rex_escape(rex_i18n::msg('issue_tracker_review_mail_personal', null !== $round->expiresAt ? $round->expiresAt->format('d.m.Y') : date('d.m.Y', time() + 86400 * Links::DEFAULT_DAYS))) . '</p>';
        $ok = NotificationService::sendMail($participant->email, rex_i18n::msg('issue_tracker_review_mail_invite_subject', $round->title), $body);
        if ($ok) {
            $participant->invitedAt = date('Y-m-d H:i:s');
            $participant->save();
        }
        return $ok;
    }

    /** Neuer öffentlicher Kommentar: beteiligte Gäste der Runde informieren (Verfasser, Kommentierende), nie den Absender */
    public static function commentAdded(Comment $comment, Issue $issue): void
    {
        if ($comment->isInternal()) {
            return;
        }
        $excerpt = mb_strlen($comment->getComment()) > 600 ? mb_substr($comment->getComment(), 0, 598) . ' …' : $comment->getComment();
        self::toGuests($issue, $comment->getGuestId(), rex_i18n::msg('issue_tracker_review_mail_reply_subject', $issue->getTitle()),
            '<p>' . rex_escape(rex_i18n::msg('issue_tracker_review_mail_reply', $comment->getAuthorName('?'), $issue->getTitle())) . '</p>'
            . '<blockquote style="margin:16px 0;padding:8px 14px;border-left:3px solid #3b5bdb;color:#222;">' . nl2br(rex_escape($excerpt)) . '</blockquote>');
    }

    public static function statusChanged(Issue $issue, string $newStatus): void
    {
        $label = ReviewService::statusLabels()[$newStatus] ?? $newStatus;
        self::toGuests($issue, 0, rex_i18n::msg('issue_tracker_review_mail_status_subject', $issue->getTitle(), $label),
            '<p>' . rex_escape(rex_i18n::msg('issue_tracker_review_mail_status', $issue->getTitle(), $label)) . '</p>');
    }

    private static function toGuests(Issue $issue, int $excludeGuestId, string $subject, string $html): void
    {
        $anchor = ReviewService::anchor($issue->getId());
        $round = null !== $anchor ? Round::get((int) $anchor['round_id']) : null;
        if (null === $round || !$round->isOpen() || $issue->getIsPrivate()) {
            return;
        }
        $ids = array_map('intval', array_column(rex_sql::factory()->getArray(
            'SELECT guest_id FROM ' . rex::getTable('issue_tracker_comments') . ' WHERE issue_id = ? AND guest_id > 0 AND is_internal = 0',
            [$issue->getId()],
        ), 'guest_id'));
        $ids[] = $issue->getGuestId();
        foreach (array_unique(array_filter($ids)) as $guestId) {
            if ($guestId === $excludeGuestId) {
                continue;
            }
            $guest = Participant::get($guestId);
            if (null === $guest || !$guest->isGuest() || !$guest->isActive() || !$guest->notify || '' === $guest->email || $guest->roundId !== $round->id) {
                continue;
            }
            $url = Links::url((string) ($anchor['url'] ?: $round->startUrl()), Links::create($guest, $round), $issue->getId());
            NotificationService::sendMail($guest->email, $subject,
                '<p>' . rex_escape(rex_i18n::msg('issue_tracker_review_mail_hello', $guest->name ?: $guest->email)) . '</p>' . $html
                . self::button($url, rex_i18n::msg('issue_tracker_review_mail_show')));
        }
    }

    private static function button(string $url, string $label): string
    {
        return '<p style="margin:24px 0;"><a href="' . rex_escape($url) . '" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#3b5bdb;color:#fff;text-decoration:none;font-weight:600;">'
            . rex_escape($label) . '</a></p>';
    }
}
