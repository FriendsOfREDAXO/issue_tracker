<?php
/**
 * Nachrichtenliste Fragment
 * 
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\Message;

$package = rex_addon::get('issue_tracker');

$messages = $this->getVar('messages', []);
$type = $this->getVar('type', 'inbox'); // inbox oder sent
$currentUser = rex::getUser();

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
usort($conversations, fn($a, $b) => $b['latest_message']->getCreatedAt() <=> $a['latest_message']->getCreatedAt());
?>

<div class="issue-tracker-messages">
    <!-- Toolbar -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <a href="<?= rex_url::backendPage('issue_tracker/messages/compose') ?>" class="btn btn-primary">
                <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_messages_compose') ?>
            </a>
        </div>
    </div>

    <?php if (empty($conversations)): ?>
    <div class="panel panel-default">
        <div class="panel-body text-center text-muted" style="padding: 40px;">
            <i class="rex-icon fa-envelope-o" style="font-size: 48px;"></i>
            <p style="margin-top: 15px;">
                <?= $isInbox 
                    ? $package->i18n('issue_tracker_no_messages_inbox') 
                    : $package->i18n('issue_tracker_no_messages_sent') ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="panel panel-default">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th style="width: 30px;"></th>
                    <th style="width: 180px;"><?= $isInbox ? $package->i18n('issue_tracker_from') : $package->i18n('issue_tracker_to') ?></th>
                    <th><?= $package->i18n('issue_tracker_subject') ?></th>
                    <th style="width: 200px;"><?= $package->i18n('issue_tracker_last_reply') ?></th>
                    <th style="width: 100px;"><?= $package->i18n('issue_tracker_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conv): 
                    $message = $conv['latest_message'];
                    $hasUnread = $conv['unread_count'] > 0;
                    $rowClass = $hasUnread ? 'font-weight: bold;' : '';
                    
                    // Wer hat die letzte Nachricht geschrieben?
                    $lastReplySender = $message->getSenderId() === $currentUser->getId() 
                        ? $package->i18n('issue_tracker_you') 
                        : $message->getSenderName();
                ?>
                <tr style="<?= $rowClass ?>">
                    <td>
                        <?php if ($hasUnread): ?>
                        <span class="label label-primary" title="<?= $conv['unread_count'] ?> <?= $package->i18n('issue_tracker_unread') ?>">
                            <?= $conv['unread_count'] ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <i class="rex-icon fa-user-o"></i>
                        <?= rex_escape($conv['partner_name']) ?>
                        <?php if ($conv['message_count'] > 1): ?>
                        <small class="text-muted">(<?= $conv['message_count'] ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $message->getId()]) ?>">
                            <?= rex_escape($message->getSubject()) ?>
                        </a>
                        <br>
                        <small class="text-muted">
                            <?= rex_escape(mb_substr(strip_tags($message->getMessage()), 0, 60)) ?>...
                        </small>
                    </td>
                    <td>
                        <small>
                            <i class="rex-icon fa-reply"></i> <?= rex_escape($lastReplySender) ?>
                            <br>
                            <span class="text-muted"><?= $message->getCreatedAt()->format('d.m.Y H:i') ?></span>
                        </small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-xs">
                            <a href="<?= rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $message->getId()]) ?>" 
                               class="btn btn-default" title="<?= $package->i18n('issue_tracker_view') ?>">
                                <i class="rex-icon fa-eye"></i>
                            </a>
                            <a href="<?= rex_url::backendPage('issue_tracker/messages/compose', ['reply_to' => $message->getId()]) ?>" 
                               class="btn btn-default" title="<?= $package->i18n('issue_tracker_reply') ?>">
                                <i class="rex-icon fa-reply"></i>
                            </a>
                            <a href="<?= rex_url::backendPage('issue_tracker/messages/compose', ['recipient_id' => $conv['partner_id']]) ?>" 
                               class="btn btn-default" title="<?= $package->i18n('issue_tracker_new_message') ?>">
                                <i class="rex-icon fa-plus"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
