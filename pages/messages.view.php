<?php

/**
 * Nachricht anzeigen
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Message;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

$messageId = rex_request('message_id', 'int', 0);

if ($messageId === 0) {
    echo rex_view::error($package->i18n('issue_tracker_message_not_found'));
    return;
}

$message = Message::get($messageId);

if (!$message) {
    echo rex_view::error($package->i18n('issue_tracker_message_not_found'));
    return;
}

// Zugriff prüfen
if (!$message->hasAccess($currentUser->getId())) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

// Konversation laden
$partnerId = $message->getSenderId() === $currentUser->getId() 
    ? $message->getRecipientId() 
    : $message->getSenderId();

$conversation = Message::getConversation($currentUser->getId(), $partnerId);

// Alle ungelesenen Nachrichten in der Konversation als gelesen markieren
foreach ($conversation as $convMessage) {
    if ($convMessage->getRecipientId() === $currentUser->getId() && !$convMessage->isRead()) {
        $convMessage->markAsRead();
    }
}

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('message', $message);
$fragment->setVar('conversation', $conversation);
$fragment->setVar('partnerId', $partnerId);
echo $fragment->parse('issue_tracker_message_view.php');
