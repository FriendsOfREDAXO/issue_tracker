<?php
/**
 * Nachrichten-Postfach wie Mail: Postfächer | Unterhaltungen | Lesebereich
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\Message;
use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');

$messages = $this->getVar('messages', []);
$type = $this->getVar('type', 'inbox'); // inbox oder sent
$selectedMessageId = (int) $this->getVar('selectedMessageId', 0);
$paneHtml = (string) $this->getVar('paneHtml', '');
$currentUser = rex::getUser();
$uid = (int) $currentUser->getId();

$isInbox = $type === 'inbox';

// Konversationen gruppieren - nach Gesprächspartner
$conversations = [];
foreach ($messages as $message) {
    $partnerId = $isInbox ? $message->getSenderId() : $message->getRecipientId();

    if (!isset($conversations[$partnerId])) {
        $conversations[$partnerId] = [
            'partner_id' => $partnerId,
            'partner_name' => $isInbox ? $message->getSenderName() : $message->getRecipientName(),
            'latest_message' => $message,
            'unread_count' => 0,
            'message_count' => 0,
        ];
    }

    $conversations[$partnerId]['message_count']++;

    if ($isInbox && !$message->isRead()) {
        $conversations[$partnerId]['unread_count']++;
    }

    // Neueste Nachricht aktualisieren
    if ($message->getCreatedAt() > $conversations[$partnerId]['latest_message']->getCreatedAt()) {
        $conversations[$partnerId]['latest_message'] = $message;
    }
}

// Nach letzter Nachricht sortieren
usort($conversations, fn ($a, $b) => $b['latest_message']->getCreatedAt() <=> $a['latest_message']->getCreatedAt());

// Gesprächspartner der ausgewählten Nachricht
$selectedPartner = 0;
if ($selectedMessageId > 0) {
    $sel = Message::get($selectedMessageId);
    if ($sel && $sel->hasAccess($uid)) {
        $selectedPartner = $sel->getSenderId() === $uid ? $sel->getRecipientId() : $sel->getSenderId();
    }
}
$page = 'issue_tracker/messages/' . ($isInbox ? 'inbox' : 'sent');
$title = $isInbox ? $package->i18n('issue_tracker_messages_inbox') : $package->i18n('issue_tracker_messages_sent');

$sidebar = new rex_fragment();
$sidebar->setVar('active', $isInbox ? 'inbox' : 'sent');
?>
<div class="it-mail it-mail--messages<?= $selectedPartner > 0 ? ' it-mail--sel' : '' ?>" data-it-mail>
    <?= $sidebar->parse('issue_tracker_message_sidebar.php') ?>
    <section class="it-ml" aria-label="<?= rex_escape($title) ?>">
        <header class="it-ml-bar">
            <div class="it-ml-head">
                <div class="it-ml-heading">
                    <h2 class="it-ml-title"><?= rex_escape($title) ?></h2>
                    <span class="it-ml-total"><?= $package->i18n('issue_tracker_mail_conversations', count($conversations)) ?></span>
                </div>
                <div class="it-ml-head-actions">
                    <a class="it-tb-btn it-tb-btn--accent" href="<?= rex_url::backendPage('issue_tracker/messages/compose') ?>" title="<?= rex_escape($package->i18n('issue_tracker_mail_new_message')) ?>"><i class="rex-icon fa-pencil-square-o" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_new_message') ?></span></a>
                </div>
            </div>
        </header>
        <?php if (empty($conversations)): ?>
        <div class="it-empty">
            <i class="rex-icon fa-envelope-o" aria-hidden="true"></i>
            <?= $isInbox ? $package->i18n('issue_tracker_no_messages_inbox') : $package->i18n('issue_tracker_no_messages_sent') ?>
        </div>
        <?php else: ?>
        <ul class="it-ml-items" data-it-list>
            <?php foreach ($conversations as $conv):
                $message = $conv['latest_message'];
                $hasUnread = $conv['unread_count'] > 0;
                $isSel = $selectedPartner > 0 && (int) $conv['partner_id'] === $selectedPartner;
                $lastFromMe = $message->getSenderId() === $uid;
                $preview = trim((string) preg_replace('/\s+/u', ' ', strip_tags($message->getMessage())));
                if (mb_strlen($preview) > 200) {
                    $preview = mb_substr($preview, 0, 200) . '…';
                }
            ?>
            <li>
                <a class="it-ml-row<?= $isSel ? ' is-selected' : '' ?><?= $hasUnread ? ' is-unread' : '' ?>" href="<?= rex_url::backendPage($page, ['message_id' => $message->getId()]) ?>"
                   data-view-href="<?= rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $message->getId()]) ?>"<?= $isSel ? ' aria-current="true"' : '' ?>>
                    <span class="it-ml-mark">
                        <?php if ($hasUnread): ?>
                        <span class="it-dot it-dot--unread" title="<?= rex_escape($package->i18n('issue_tracker_unread')) ?>"></span><span class="sr-only"><?= $conv['unread_count'] ?> <?= $package->i18n('issue_tracker_unread') ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="it-ml-main">
                        <span class="it-ml-line1">
                            <span class="it-ml-subject"><?= rex_escape($conv['partner_name']) ?></span>
                            <time class="it-ml-date" datetime="<?= $message->getCreatedAt()->format('c') ?>"><?= rex_escape(UiHelper::relDate($message->getCreatedAt())) ?></time>
                        </span>
                        <span class="it-ml-line2"><span class="it-ml-who"><?= rex_escape($message->getSubject()) ?></span></span>
                        <span class="it-ml-preview"><?php if ($lastFromMe): ?><?= $package->i18n('issue_tracker_you') ?>: <?php endif; ?><?= rex_escape($preview) ?></span>
                        <span class="it-ml-meta">
                            <span class="it-ml-icon" title="<?= rex_escape($package->i18n('issue_tracker_messages')) ?>"><i class="rex-icon fa-comments-o" aria-hidden="true"></i> <?= (int) $conv['message_count'] ?></span>
                            <?php if ($hasUnread): ?><span class="it-badge it-badge--info"><?= (int) $conv['unread_count'] ?> <?= $package->i18n('issue_tracker_unread') ?></span><?php endif; ?>
                        </span>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <section class="it-rp-wrap" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_reading_pane')) ?>" data-it-pane>
        <?php if ($paneHtml !== ''): ?>
            <?= $paneHtml ?>
        <?php else: ?>
        <div class="it-rp-empty">
            <i class="rex-icon fa-envelope-open-o" aria-hidden="true"></i>
            <p><?= $package->i18n('issue_tracker_mail_no_message_selected') ?></p>
            <small><?= $package->i18n('issue_tracker_mail_no_message_hint') ?></small>
        </div>
        <?php endif; ?>
    </section>
</div>
