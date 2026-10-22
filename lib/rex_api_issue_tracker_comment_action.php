<?php

/**
 * API für Kommentar-Aktionen (Pinnen, Lösung markieren)
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Comment;
use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\PermissionService;

class rex_api_issue_tracker_comment_action extends rex_api_function
{
    protected $published = false;

    public function execute()
    {
        rex_response::cleanOutputBuffers();
        
        // Nur eingeloggte User
        if (!rex::getUser()) {
            return new rex_api_result(false, 'Nicht eingeloggt');
        }

        // Nur per POST mit CSRF-Token (Token: rex_csrf_token::factory('issue_tracker_comment_action'))
        if ('post' !== rex_request_method() || !rex_csrf_token::factory('issue_tracker_comment_action')->isValid()) {
            return new rex_api_result(false, rex_i18n::msg('csrf_token_invalid'));
        }

        $commentId = rex_post('comment_id', 'int', 0);
        $action = rex_post('action', 'string', '');

        if ($commentId === 0 || !in_array($action, ['toggle_pin', 'toggle_solution'])) {
            return new rex_api_result(false, 'Ungültige Parameter');
        }

        $comment = Comment::get($commentId);
        if (!$comment) {
            return new rex_api_result(false, 'Kommentar nicht gefunden');
        }

        // Rechte wie in der Themenansicht: Moderation oder Ersteller des Themas
        $issue = Issue::get($comment->getIssueId());
        if (!$issue || !PermissionService::canView($issue)
            || !(PermissionService::canModerateComments() || $issue->getCreatedBy() === PermissionService::getUserId())) {
            return new rex_api_result(false, 'Keine Berechtigung');
        }

        // Aktion ausführen
        if ($action === 'toggle_pin') {
            $comment->setPinned(!$comment->isPinned());
            $message = $comment->isPinned() ? 'Kommentar angepinnt' : 'Pin entfernt';
        } elseif ($action === 'toggle_solution') {
            // Nur ein Kommentar kann Lösung sein - andere zurücksetzen
            if (!$comment->isSolution()) {
                $sql = rex_sql::factory();
                $sql->setQuery(
                    'UPDATE ' . rex::getTable('issue_tracker_comments') . 
                    ' SET is_solution = 0 WHERE issue_id = ?',
                    [$comment->getIssueId()]
                );
            }
            $comment->setSolution(!$comment->isSolution());
            $message = $comment->isSolution() ? 'Als Lösung markiert' : 'Lösung-Markierung entfernt';
        }

        if ($comment->save()) {
            rex_response::sendJson([
                'success' => true,
                'message' => $message,
                'is_pinned' => $comment->isPinned(),
                'is_solution' => $comment->isSolution(),
            ]);
            exit;
        }

        return new rex_api_result(false, 'Fehler beim Speichern');
    }
}
