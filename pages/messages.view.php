<?php

/**
 * Nachricht anzeigen – Unterhaltung im Lesebereich (eingebettet in Eingang/Gesendet oder als Vollansicht)
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Message;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

$itEmbedded = isset($itEmbedded) && true === $itEmbedded;
$itBox = isset($itBox) && 'sent' === $itBox ? 'sent' : 'inbox';

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
$fragment->setVar('embedded', $itEmbedded);
$fragment->setVar('box', $itBox);

if ($itEmbedded) {
    echo $fragment->parse('issue_tracker_message_view.php');
    return;
}

$sidebar = new rex_fragment();
$sidebar->setVar('active', $message->getSenderId() === $currentUser->getId() ? 'sent' : 'inbox');
?>
<div class="it-mail it-mail--full it-mail--sel it-mail--messages" data-it-mail>
    <?= $sidebar->parse('issue_tracker_message_sidebar.php') ?>
    <section class="it-rp-wrap" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_reading_pane')) ?>" data-it-pane>
        <?= $fragment->parse('issue_tracker_message_view.php') ?>
    </section>
</div>
