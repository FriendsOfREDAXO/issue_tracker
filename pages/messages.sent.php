<?php

/**
 * Gesendete Nachrichten
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Message;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

// Nachricht löschen
if (rex_request('func', 'string') === 'delete' && rex_request('message_id', 'int', 0) > 0) {
    $messageId = rex_request('message_id', 'int', 0);
    $message = Message::get($messageId);
    
    if ($message && $message->hasAccess($currentUser->getId())) {
        if ($message->delete($currentUser->getId())) {
            echo rex_view::success($package->i18n('issue_tracker_message_deleted'));
        }
    } else {
        echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    }
}

// Gesendete Nachrichten laden
$messages = Message::getSent($currentUser->getId());

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('messages', $messages);
$fragment->setVar('type', 'sent');
echo $fragment->parse('issue_tracker_message_list.php');
