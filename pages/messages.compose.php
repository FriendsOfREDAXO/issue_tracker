<?php

/**
 * Nachricht verfassen
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Message;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

$replyToId = rex_request('reply_to', 'int', 0);
$recipientId = rex_request('recipient_id', 'int', 0);

$replyToMessage = null;
$prefilledSubject = '';
$prefilledRecipient = 0;

// Antwort auf vorhandene Nachricht
if ($replyToId > 0) {
    $replyToMessage = Message::get($replyToId);
    if ($replyToMessage && $replyToMessage->hasAccess($currentUser->getId())) {
        $prefilledSubject = 'Re: ' . $replyToMessage->getSubject();
        // Antwort geht an den anderen Teilnehmer
        $prefilledRecipient = $replyToMessage->getSenderId() === $currentUser->getId() 
            ? $replyToMessage->getRecipientId() 
            : $replyToMessage->getSenderId();
    }
}

// Vorausgewählter Empfänger
if ($recipientId > 0) {
    $prefilledRecipient = $recipientId;
}

// Alle verfügbaren User laden (mit Issue-Tracker Berechtigung)
$usersSql = rex_sql::factory();
$usersSql->setQuery('SELECT id, name, login FROM ' . rex::getTable('user') . ' WHERE status = 1 AND id != ? ORDER BY name', [$currentUser->getId()]);
$allUsers = [];
foreach ($usersSql as $row) {
    $allUsers[(int) $usersSql->getValue('id')] = $usersSql->getValue('name') ?: $usersSql->getValue('login');
}

// Nachricht senden
if (rex_post('send', 'int', 0) === 1) {
    $recipientId = rex_post('recipient_id', 'int', 0);
    $subject = rex_post('subject', 'string', '');
    $messageText = rex_post('message', 'string', '');
    
    if ($recipientId === 0 || empty($subject) || empty($messageText)) {
        echo rex_view::error($package->i18n('issue_tracker_message_fill_all_fields'));
    } else {
        $message = new Message();
        $message->setSenderId($currentUser->getId());
        $message->setRecipientId($recipientId);
        $message->setSubject($subject);
        $message->setMessage($messageText);
        
        if ($message->save()) {
            echo rex_view::success($package->i18n('issue_tracker_message_sent'));
            echo '<script>location.href = "' . rex_url::backendPage('issue_tracker/messages/sent', [], false) . '";</script>';
            return;
        } else {
            echo rex_view::error($package->i18n('issue_tracker_message_send_error'));
        }
    }
}

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('allUsers', $allUsers);
$fragment->setVar('prefilledSubject', $prefilledSubject);
$fragment->setVar('prefilledRecipient', $prefilledRecipient);
$fragment->setVar('replyToMessage', $replyToMessage);
echo $fragment->parse('issue_tracker_message_form.php');
