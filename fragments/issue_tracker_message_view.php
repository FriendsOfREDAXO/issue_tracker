<?php
/**
 * Unterhaltung im Lesebereich (wie Mail): Symbolleiste, Nachrichten, Antwortfeld
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');

$message = $this->getVar('message');
$conversation = $this->getVar('conversation', []);
$partnerId = (int) $this->getVar('partnerId', 0);
$embedded = (bool) $this->getVar('embedded', false);
$box = (string) $this->getVar('box', 'inbox');
$currentUser = rex::getUser();
$uid = (int) $currentUser->getId();

$partner = rex_user::get($partnerId);
$partnerName = $partner ? ($partner->getName() ?: $partner->getLogin()) : 'Unbekannt';

// Sortierung aus Session oder Request
$sortOrder = rex_request('msg_sort', 'string', $_SESSION['issue_tracker_msg_sort'] ?? 'asc');
$sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';
$_SESSION['issue_tracker_msg_sort'] = $sortOrder;

// Konversation sortieren
if ($sortOrder === 'desc') {
    $conversation = array_reverse($conversation);
}

$box = $box === 'sent' ? 'sent' : 'inbox';
$listPage = 'issue_tracker/issues/list';

// Neueste empfangene Nachricht der Unterhaltung (für „Als ungelesen markieren“)
$lastReceived = null;
foreach ($conversation as $convMsg) {
    if ($convMsg->getRecipientId() === $uid && (!$lastReceived || $convMsg->getCreatedAt() >= $lastReceived->getCreatedAt())) {
        $lastReceived = $convMsg;
    }
}
$msgToken = rex_csrf_token::factory('issue_tracker_messages');
// Weitergeleitete Nachrichten: Original ab der Markierung als Zitat darstellen
$renderBody = static function (string $text): string {
    // Trennzeile sprachunabhängig erkennen ("---------- Weitergeleitete Nachricht ----------" / "Forwarded message" …)
    if (!preg_match('/^-{10} [^\n]{1,60} -{10}$/mu', $text, $m, PREG_OFFSET_CAPTURE)) {
        return nl2br(rex_escape($text));
    }
    $pos = (int) $m[0][1];
    $note = trim(substr($text, 0, $pos));
    $quote = substr($text, $pos + strlen($m[0][0]));
    $quote = (string) preg_replace('/^> ?/m', '', $quote);
    return ($note !== '' ? '<p>' . nl2br(rex_escape($note)) . '</p>' : '')
        . '<blockquote class="it-quote"><p class="it-quote-head">' . rex_escape(rex_i18n::msg('issue_tracker_mail_fwd_marker')) . '</p>' . nl2br(rex_escape(trim($quote))) . '</blockquote>';
};

// Re: nur einmal hinzufügen - bestehende Re: Prefixe entfernen
$replySubject = 'Re: ' . preg_replace('/^(Re:\s*)+/i', '', $message->getSubject());
?>
<article class="it-rp issue-tracker-message-view">
    <div class="it-rp-toolbar" role="toolbar" aria-label="<?= rex_escape($package->i18n('issue_tracker_actions')) ?>">
        <div class="it-tb-group">
            <a class="it-tb-back" href="<?= rex_url::backendPage($listPage, ['box' => $box, 'screen' => 'list']) ?>"><i class="rex-icon fa-angle-left" aria-hidden="true"></i> <?= $box === 'sent' ? $package->i18n('issue_tracker_messages_sent') : $package->i18n('issue_tracker_messages_inbox') ?></a>
            <a class="it-tb-btn" href="#it-reply" data-it-reply aria-keyshortcuts="R" title="<?= rex_escape($package->i18n('issue_tracker_reply') . ' (R)') ?>"><i class="rex-icon fa-reply" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_reply') ?></span></a>
            <a class="it-tb-btn" data-it-forward aria-keyshortcuts="F" href="<?= rex_url::backendPage('issue_tracker/messages/compose', ['forward' => $message->getId()]) ?>" title="<?= rex_escape($package->i18n('issue_tracker_mail_forward') . ' (F)') ?>"><i class="rex-icon fa-share" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_forward') ?></span></a>
            <?php if ($lastReceived): $isUnreadNow = !$lastReceived->isRead(); ?>
            <form method="post" action="<?= rex_url::backendPage($listPage, ['box' => $box]) ?>" class="it-tb-form" data-it-unread>
                <?= $msgToken->getHiddenField() ?>
                <input type="hidden" name="msg_action" value="toggle_read" />
                <input type="hidden" name="message_id" value="<?= $lastReceived->getId() ?>" />
                <button type="submit" class="it-tb-btn" title="<?= rex_escape($package->i18n($isUnreadNow ? 'issue_tracker_mail_mark_read' : 'issue_tracker_mail_mark_unread') . ' (U)') ?>"><i class="rex-icon <?= $isUnreadNow ? 'fa-envelope-open-o' : 'fa-envelope' ?>" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n($isUnreadNow ? 'issue_tracker_mail_mark_read' : 'issue_tracker_mail_mark_unread') ?></span></button>
            </form>
            <?php endif; ?>
            <a class="it-tb-btn" href="<?= rex_url::backendPage('issue_tracker/messages/compose', ['recipient_id' => $partnerId]) ?>" title="<?= rex_escape($package->i18n('issue_tracker_new_message_to', $partnerName)) ?>"><i class="rex-icon fa-pencil-square-o" aria-hidden="true"></i><span class="sr-only"><?= rex_escape($package->i18n('issue_tracker_new_message_to', $partnerName)) ?></span></a>
        </div>
        <div class="it-tb-group">
            <a class="it-tb-btn it-tb-btn--danger" href="<?= rex_url::backendPage($listPage, ['box' => $box, 'func' => 'delete', 'message_id' => $message->getId()] + \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getUrlParams()) ?>"
               data-confirm="<?= rex_escape($package->i18n('issue_tracker_mail_delete_message_confirm')) ?>"
               title="<?= rex_escape($package->i18n('issue_tracker_mail_delete_conversation')) ?>"><i class="rex-icon fa-trash" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_delete_conversation') ?></span></a>
        </div>
        <div class="it-tb-group it-tb-group--end">
            <a class="it-tb-btn" href="<?= rex_url::backendPage($listPage, ['box' => $box, 'message_id' => $message->getId(), 'msg_sort' => $sortOrder === 'asc' ? 'desc' : 'asc']) ?>"
               title="<?= rex_escape($package->i18n($sortOrder === 'asc' ? 'issue_tracker_mail_newest_first' : 'issue_tracker_mail_oldest_first')) ?>"><i class="rex-icon <?= $sortOrder === 'asc' ? 'fa-sort-amount-desc' : 'fa-sort-amount-asc' ?>" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n($sortOrder === 'asc' ? 'issue_tracker_mail_newest_first' : 'issue_tracker_mail_oldest_first') ?></span></a>

        </div>
    </div>

    <div class="it-rp-scroll">
        <header class="it-rp-head">
            <?= UiHelper::avatar($partnerId, $partnerName) ?>
            <div class="it-rp-headmain">
                <div class="it-rp-fromline"><span><span class="it-rp-label"><?= $package->i18n('issue_tracker_conversation_with') ?></span> <strong><?= rex_escape($partnerName) ?></strong></span>
                    <span class="it-rp-date"><?= count($conversation) ?> <?= $package->i18n('issue_tracker_messages') ?></span></div>
                <h2 class="it-rp-subject"><?= rex_escape($message->getSubject()) ?></h2>
            </div>
        </header>

        <section class="it-thread">
            <?php foreach ($conversation as $msg):
                $isMine = $msg->getSenderId() === $uid;
                $name = $isMine ? $package->i18n('issue_tracker_you') : $msg->getSenderName();
            ?>
            <article class="it-msg<?= $isMine ? ' it-msg--mine' : '' ?><?= $msg->getId() === $message->getId() ? ' is-current' : '' ?>" id="message-<?= $msg->getId() ?>">
                <header class="it-msg-head">
                    <?= UiHelper::avatar($msg->getSenderId(), $msg->getSenderName()) ?>
                    <div class="it-msg-who">
                        <strong class="it-msg-name"><?= rex_escape($name) ?></strong>
                        <span class="it-msg-date">
                            <?= $msg->getCreatedAt()->format('d.m.Y H:i') ?> · <?= rex_escape($msg->getSubject()) ?>
                            <?php if ($isMine && $msg->isRead()): ?> · <i class="rex-icon fa-check" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_read_at') ?> <?= $msg->getReadAt() ? $msg->getReadAt()->format('d.m.Y H:i') : '' ?><?php endif; ?>
                        </span>
                    </div>
                </header>
                <div class="it-msg-body"><?= $renderBody($msg->getMessage()) ?></div>
            </article>
            <?php endforeach; ?>
        </section>

        <section class="it-compose" id="it-reply">
            <h3 class="it-compose-title"><i class="rex-icon fa-reply" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_reply') ?> <span class="it-muted-text">– <?= $package->i18n('issue_tracker_to') ?> <?= rex_escape($partnerName) ?></span></h3>
            <form method="post" action="<?= rex_url::backendPage('issue_tracker/messages/compose') ?>" data-it-quickreply>
                <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>
                <input type="hidden" name="send" value="1" />
                <input type="hidden" name="recipient_id" value="<?= $partnerId ?>" />
                <input type="hidden" name="subject" value="<?= rex_escape($replySubject) ?>" />
                <label class="sr-only" for="replyMessage"><?= $package->i18n('issue_tracker_message') ?></label>
                <textarea class="form-control" id="replyMessage" name="message" rows="5" placeholder="<?= rex_escape($package->i18n('issue_tracker_message_placeholder')) ?>" required></textarea>
                <div class="it-compose-row">
                    <span class="it-compose-help"><?= $package->i18n('issue_tracker_subject') ?>: <?= rex_escape($replySubject) ?></span>
                    <button type="submit" class="btn btn-primary it-btn-primary it-compose-send"><?= $package->i18n('issue_tracker_mail_send_reply') ?></button>
                </div>
            </form>
        </section>
    </div>
</article>
