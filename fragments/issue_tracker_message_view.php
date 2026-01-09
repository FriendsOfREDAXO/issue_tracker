<?php
/**
 * Nachricht anzeigen Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$message = $this->getVar('message');
$conversation = $this->getVar('conversation', []);
$partnerId = $this->getVar('partnerId', 0);
$currentUser = rex::getUser();

$partner = rex_user::get($partnerId);
$partnerName = $partner ? ($partner->getName() ?: $partner->getLogin()) : 'Unbekannt';
?>

<div class="issue-tracker-message-view">
    <!-- Toolbar -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>" class="btn btn-default">
                <i class="rex-icon fa-arrow-left"></i> <?= $package->i18n('issue_tracker_back') ?>
            </a>
            <a href="<?= rex_url::backendPage('issue_tracker/messages/compose', ['reply_to' => $message->getId()]) ?>" class="btn btn-primary">
                <i class="rex-icon fa-reply"></i> <?= $package->i18n('issue_tracker_reply') ?>
            </a>
            <a href="<?= rex_url::backendPage('issue_tracker/messages/compose', ['recipient_id' => $partnerId]) ?>" class="btn btn-default">
                <i class="rex-icon fa-pencil"></i> <?= $package->i18n('issue_tracker_new_message_to', $partnerName) ?>
            </a>
        </div>
    </div>

    <!-- Konversationsverlauf -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="rex-icon fa-comments"></i> 
                <?= $package->i18n('issue_tracker_conversation_with') ?> <?= rex_escape($partnerName) ?>
            </h3>
        </div>
        <div class="panel-body" style="max-height: 600px; overflow-y: auto;">
            <?php foreach ($conversation as $msg): 
                $isMine = $msg->getSenderId() === $currentUser->getId();
                $isCurrentMessage = $msg->getId() === $message->getId();
                $bgClass = $isMine ? 'panel-info' : 'panel-default';
                $alignment = $isMine ? 'margin-left: 50px;' : 'margin-right: 50px;';
            ?>
            <div class="panel <?= $bgClass ?>" style="<?= $alignment ?> <?= $isCurrentMessage ? 'border: 2px solid #337ab7;' : '' ?>">
                <div class="panel-heading" style="padding: 8px 15px;">
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>
                                <?php if ($isMine): ?>
                                <i class="rex-icon fa-user"></i> <?= $package->i18n('issue_tracker_you') ?>
                                <?php else: ?>
                                <i class="rex-icon fa-user-o"></i> <?= rex_escape($msg->getSenderName()) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="col-sm-6 text-right">
                            <small class="text-muted">
                                <?= $msg->getCreatedAt()->format('d.m.Y H:i') ?>
                                <?php if (!$isMine && $msg->isRead()): ?>
                                <i class="rex-icon fa-check" title="<?= $package->i18n('issue_tracker_read_at') ?> <?= $msg->getReadAt() ? $msg->getReadAt()->format('d.m.Y H:i') : '' ?>"></i>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div><strong><?= rex_escape($msg->getSubject()) ?></strong></div>
                </div>
                <div class="panel-body">
                    <?= nl2br(rex_escape($msg->getMessage())) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Schnellantwort -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="rex-icon fa-reply"></i> <?= $package->i18n('issue_tracker_quick_reply') ?></h3>
        </div>
        <div class="panel-body">
            <form method="post" action="<?= rex_url::backendPage('issue_tracker/messages/compose') ?>">
                <input type="hidden" name="send" value="1" />
                <input type="hidden" name="recipient_id" value="<?= $partnerId ?>" />
                <input type="hidden" name="subject" value="Re: <?= rex_escape($message->getSubject()) ?>" />
                
                <div class="form-group">
                    <textarea class="form-control" name="message" rows="4" 
                              placeholder="<?= $package->i18n('issue_tracker_message_placeholder') ?>" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="rex-icon fa-paper-plane"></i> <?= $package->i18n('issue_tracker_send') ?>
                </button>
            </form>
        </div>
    </div>
</div>
