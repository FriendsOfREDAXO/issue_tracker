<?php
/**
 * Nachricht verfassen – wie „Neue E-Mail“: An, Betreff, Text
 *
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$allUsers = $this->getVar('allUsers', []);
$prefilledSubject = $this->getVar('prefilledSubject', '');
$prefilledRecipient = (int) $this->getVar('prefilledRecipient', 0);
$prefilledMessage = (string) $this->getVar('prefilledMessage', '');
$replyToMessage = $this->getVar('replyToMessage', null);
$errors = $this->getVar('fieldErrors', []);
$hasErrors = in_array(true, $errors, true);
$err = static fn (string $k): bool => !empty($errors[$k]);
?>
<div class="issue-tracker-message-form it-sheet it-sheet--inline">
    <form method="post" action="<?= rex_url::currentBackendPage() ?>" data-it-form>
        <input type="hidden" name="send" value="1" />
        <?php if ($replyToMessage): ?>
        <input type="hidden" name="reply_to" value="<?= $replyToMessage->getId() ?>" />
        <?php endif; ?>

        <div class="it-sheet-head">
            <h2 class="it-sheet-title">
                <span class="it-icon it-c-blue"><i class="rex-icon <?= $replyToMessage ? 'fa-reply' : 'fa-pencil-square-o' ?>" aria-hidden="true"></i></span>
                <?= $replyToMessage ? $package->i18n('issue_tracker_reply_to_message') : $package->i18n('issue_tracker_mail_new_message') ?>
            </h2>
            <div class="it-sheet-actions">
                <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>" class="btn btn-default"><?= $package->i18n('issue_tracker_cancel') ?></a>
                <button type="submit" class="btn btn-primary it-btn-primary"><?= $package->i18n('issue_tracker_mail_send_message') ?></button>
            </div>
        </div>

        <?php if ($hasErrors): ?>
        <div class="it-form-summary" role="alert">
            <strong><?= $package->i18n('issue_tracker_mail_form_check') ?></strong>
            <?= $package->i18n('issue_tracker_mail_err_message') ?>
        </div>
        <?php endif; ?>

        <div class="it-sheet-fields">
            <div class="it-field<?= $err('recipient') ? ' has-error' : '' ?>">
                <label class="it-field-label" for="message-recipient"><?= $package->i18n('issue_tracker_to') ?> <span class="it-req"><?= $package->i18n('issue_tracker_mail_required') ?></span></label>
                <div class="it-field-control">
                    <select class="form-control selectpicker" id="message-recipient" name="recipient_id" data-live-search="true" data-width="100%" required aria-describedby="message-recipient-help">
                        <option value=""><?= $package->i18n('issue_tracker_please_select') ?></option>
                        <?php foreach ($allUsers as $userId => $userName): ?>
                        <option value="<?= $userId ?>" <?= $prefilledRecipient === $userId ? 'selected' : '' ?>><?= rex_escape($userName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="it-field-help" id="message-recipient-help"><?= $package->i18n('issue_tracker_mail_to_help') ?></p>
                </div>
            </div>
            <div class="it-field it-field--subject<?= $err('subject') ? ' has-error' : '' ?>">
                <label class="it-field-label" for="message-subject"><?= $package->i18n('issue_tracker_subject') ?> <span class="it-req"><?= $package->i18n('issue_tracker_mail_required') ?></span></label>
                <div class="it-field-control">
                    <input type="text" class="form-control" id="message-subject" name="subject" value="<?= rex_escape($prefilledSubject) ?>" required maxlength="255">
                </div>
            </div>
        </div>

        <div class="it-sheet-editor<?= $err('message') ? ' has-error' : '' ?>">
            <label class="it-field-label" for="message-text"><?= $package->i18n('issue_tracker_mail_message_text') ?> <span class="it-req"><?= $package->i18n('issue_tracker_mail_required') ?></span></label>
            <textarea class="form-control" id="message-text" name="message" rows="12" placeholder="<?= rex_escape($package->i18n('issue_tracker_message_placeholder')) ?>" required><?= rex_escape($prefilledMessage) ?></textarea>
        </div>

        <?php if ($replyToMessage): ?>
        <blockquote class="it-sheet-quote">
            <p><?= $package->i18n('issue_tracker_from') ?>: <?= rex_escape($replyToMessage->getSenderName()) ?> · <?= $replyToMessage->getCreatedAt()->format('d.m.Y H:i') ?></p>
            <?= nl2br(rex_escape($replyToMessage->getMessage())) ?>
        </blockquote>
        <?php endif; ?>

        <div class="it-sheet-foot">
            <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>" class="btn btn-default"><?= $package->i18n('issue_tracker_cancel') ?></a>
            <button type="submit" class="btn btn-primary it-btn-primary"><?= $package->i18n('issue_tracker_mail_send_message') ?></button>
        </div>
    </form>
</div>
