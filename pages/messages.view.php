<?php

/**
 * Frühere Nachrichtenansicht (auch Ziel von E-Mail-Links) – öffnet die Unterhaltung im Postfach.
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Message;

$messageId = rex_request('message_id', 'int', 0);
$message = $messageId > 0 ? Message::get($messageId) : null;
$box = $message && $message->getSenderId() === (int) rex::getUser()->getId() ? 'sent' : 'inbox';
$params = ['box' => $box, 'message_id' => $messageId];
if (rex_request('msg_sort', 'string', '') !== '') {
    $params['msg_sort'] = rex_request('msg_sort', 'string', '');
}
rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', $params, false));
