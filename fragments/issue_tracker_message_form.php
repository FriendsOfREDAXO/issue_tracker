<?php
/**
 * Nachricht verfassen Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$allUsers = $this->getVar('allUsers', []);
$prefilledSubject = $this->getVar('prefilledSubject', '');
$prefilledRecipient = $this->getVar('prefilledRecipient', 0);
$replyToMessage = $this->getVar('replyToMessage', null);
?>

<div class="issue-tracker-message-form">
    <!-- Toolbar -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>" class="btn btn-default">
                <i class="rex-icon fa-arrow-left"></i> <?= $package->i18n('issue_tracker_back') ?>
            </a>
        </div>
    </div>

    <form method="post" action="<?= rex_url::currentBackendPage() ?>">
        <input type="hidden" name="send" value="1" />
        <?php if ($replyToMessage): ?>
        <input type="hidden" name="reply_to" value="<?= $replyToMessage->getId() ?>" />
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="rex-icon fa-pencil"></i>
                    <?= $replyToMessage ? $package->i18n('issue_tracker_reply_to_message') : $package->i18n('issue_tracker_new_message') ?>
                </h3>
            </div>
            <div class="panel-body">
                <!-- Empfänger -->
                <div class="form-group">
                    <label for="message-recipient"><?= $package->i18n('issue_tracker_to') ?> *</label>
                    <select class="form-control selectpicker" id="message-recipient" name="recipient_id" 
                            data-live-search="true" required>
                        <option value=""><?= $package->i18n('issue_tracker_please_select') ?></option>
                        <?php foreach ($allUsers as $userId => $userName): ?>
                        <option value="<?= $userId ?>" <?= $prefilledRecipient === $userId ? 'selected' : '' ?>>
                            <?= rex_escape($userName) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Betreff -->
                <div class="form-group">
                    <label for="message-subject"><?= $package->i18n('issue_tracker_subject') ?> *</label>
                    <input type="text" class="form-control" id="message-subject" name="subject" 
                           value="<?= rex_escape($prefilledSubject) ?>" required maxlength="255">
                </div>

                <!-- Nachricht -->
                <div class="form-group">
                    <label for="message-text"><?= $package->i18n('issue_tracker_message') ?> *</label>
                    <textarea class="form-control" id="message-text" name="message" rows="10" 
                              placeholder="<?= $package->i18n('issue_tracker_message_placeholder') ?>" required></textarea>
                </div>

                <?php if ($replyToMessage): ?>
                <!-- Original-Nachricht anzeigen -->
                <div class="form-group">
                    <label><?= $package->i18n('issue_tracker_original_message') ?>:</label>
                    <div class="well well-sm" style="background-color: #f9f9f9;">
                        <p><strong><?= $package->i18n('issue_tracker_from') ?>:</strong> <?= rex_escape($replyToMessage->getSenderName()) ?></p>
                        <p><strong><?= $package->i18n('issue_tracker_date') ?>:</strong> <?= $replyToMessage->getCreatedAt()->format('d.m.Y H:i') ?></p>
                        <hr>
                        <?= nl2br(rex_escape($replyToMessage->getMessage())) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Buttons -->
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="rex-icon fa-paper-plane"></i> <?= $package->i18n('issue_tracker_send') ?>
                    </button>
                    <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>" class="btn btn-default">
                        <i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_cancel') ?>
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('.selectpicker').selectpicker('refresh');
});
</script>
