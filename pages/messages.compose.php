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
$prefilledMessage = '';
$fieldErrors = [];
$prefilledSubject = '';
$prefilledRecipient = 0;

// Antwort auf vorhandene Nachricht
if ($replyToId > 0) {
    $replyToMessage = Message::get($replyToId);
    if ($replyToMessage && $replyToMessage->hasAccess($currentUser->getId())) {
        // Re: nur einmal - bestehende Re: Prefixe entfernen
        $originalSubject = $replyToMessage->getSubject();
        $cleanSubject = preg_replace('/^(Re:\s*)+/i', '', $originalSubject);
        $prefilledSubject = 'Re: ' . $cleanSubject;
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

// Weiterleiten (wie in Mail): Original nur für Absender/Empfänger, eigene Notiz oben, Original zitiert
$forwardMessage = null;
$forwardToken = rex_csrf_token::factory('issue_tracker_message_forward');
$forwardId = rex_request('forward', 'int', 0);
$prefilledRecipients = [];
if ($forwardId > 0) {
    $forwardMessage = Message::get($forwardId);
    if (!$forwardMessage || !$forwardMessage->hasAccess($currentUser->getId())) {
        $forwardMessage = null;
        echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    } else {
        $prefilledSubject = $package->i18n('issue_tracker_mail_fwd_prefix') . ' ' . preg_replace('/^((Re|AW|WG|Fwd|Fw):\s*)+/i', '', $forwardMessage->getSubject());
    }
}
if ($forwardMessage && rex_post('forward_send', 'int', 0) === 1) {
    $prefilledRecipients = array_map('intval', rex_post('recipient_ids', 'array', []));
    $prefilledSubject = rex_post('subject', 'string', $prefilledSubject);
    $prefilledMessage = rex_post('message', 'string', '');
    if (!$forwardToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif ($prefilledRecipients === [] || trim($prefilledSubject) === '') {
        $fieldErrors = ['recipient' => $prefilledRecipients === [], 'subject' => trim($prefilledSubject) === ''];
    } else {
        $created = Message::forward($forwardMessage->getId(), (int) $currentUser->getId(), $prefilledRecipients, $prefilledMessage, $prefilledSubject);
        if ($created !== []) {
            rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', ['box' => 'sent', 'message_id' => $created[0]->getId()], false));
        }
        $fieldErrors = ['recipient' => true];
    }
}

// Nachricht senden
if (rex_post('send', 'int', 0) === 1 && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $recipientId = rex_post('recipient_id', 'int', 0);
    $subject = rex_post('subject', 'string', '');
    $messageText = rex_post('message', 'string', '');
    
    if ($recipientId === 0 || empty($subject) || empty($messageText)) {
        // Eingaben behalten, Fehler am Feld anzeigen
        $prefilledRecipient = $recipientId;
        $prefilledSubject = $subject;
        $prefilledMessage = $messageText;
        $fieldErrors = ['recipient' => $recipientId === 0, 'subject' => empty($subject), 'message' => empty($messageText)];
    } else {
        $message = new Message();
        $message->setSenderId($currentUser->getId());
        $message->setRecipientId($recipientId);
        $message->setSubject($subject);
        $message->setMessage($messageText);
        
        if ($message->save()) {
            // Zurück zur Konversationsansicht mit der neuen Nachricht
            rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', ['box' => 'sent', 'message_id' => $message->getId()], false));
        } else {
            echo rex_view::error($package->i18n('issue_tracker_message_send_error'));
        }
    }
}

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('allUsers', $allUsers, false);
$fragment->setVar('prefilledSubject', $prefilledSubject, false);
$fragment->setVar('prefilledRecipient', $prefilledRecipient);
$fragment->setVar('replyToMessage', $replyToMessage);
$fragment->setVar('prefilledMessage', $prefilledMessage, false);
$fragment->setVar('fieldErrors', $fieldErrors);
$fragment->setVar('forwardMessage', $forwardMessage);
$fragment->setVar('forwardToken', $forwardToken);
$fragment->setVar('prefilledRecipients', $prefilledRecipients);

$sidebar = new rex_fragment();
$sidebar->setVar('mode', 'compose');
?>
<div class="it-mail it-mail--full it-mail--sel it-mail--messages it-mail--compose" data-it-mail>
    <?= $sidebar->parse('issue_tracker_filter.php') ?>
    <section class="it-rp-wrap it-rp-wrap--sheet">
        <?= $fragment->parse('issue_tracker_message_form.php') ?>
    </section>
</div>
<?php
