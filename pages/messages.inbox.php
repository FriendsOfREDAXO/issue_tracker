<?php

/**
 * Posteingang – Postfach wie Mail (Liste + Lesebereich)
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Message;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

// Nachricht löschen
$func = rex_request('func', 'string');
if ($func === 'delete' && rex_request('message_id', 'int', 0) > 0) {
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

// Ausgewählte Unterhaltung im Lesebereich
$selectedMessageId = $func === 'delete' ? 0 : rex_request('message_id', 'int', 0);
$paneHtml = '';
if ($selectedMessageId > 0) {
    $renderPane = static function (): string {
        $itEmbedded = true;
        $itBox = 'inbox';
        ob_start();
        include __DIR__ . '/messages.view.php';
        return (string) ob_get_clean();
    };
    $paneHtml = $renderPane();
}

// Nachrichten laden (nach dem Lesebereich, damit „gelesen“ schon stimmt)
$messages = Message::getInbox($currentUser->getId());

// Fragment einbinden
$fragment = new rex_fragment();
$fragment->setVar('messages', $messages);
$fragment->setVar('type', 'inbox');
$fragment->setVar('selectedMessageId', $selectedMessageId);
$fragment->setVar('paneHtml', $paneHtml, false);
echo $fragment->parse('issue_tracker_message_list.php');
