<?php
/**
 * Rundschreiben verfassen (nur Admins) – wie „Neue E-Mail“
 *
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');
?>
<div class="issue-tracker-broadcast it-sheet it-sheet--inline">
    <form method="post" action="<?= rex_url::currentBackendPage() ?>" data-it-form>
        <input type="hidden" name="send_broadcast" value="1" />

        <div class="it-sheet-head">
            <h2 class="it-sheet-title">
                <span class="it-icon it-c-orange"><i class="rex-icon fa-bullhorn" aria-hidden="true"></i></span>
                <?= $package->i18n('issue_tracker_broadcast') ?>
            </h2>
            <div class="it-sheet-actions">
                <a href="<?= rex_url::backendPage('issue_tracker/issues/list', ['box' => 'inbox']) ?>" class="btn btn-default"><?= $package->i18n('issue_tracker_cancel') ?></a>
            </div>
        </div>
        <p class="it-sheet-intro"><?= $package->i18n('issue_tracker_broadcast_info') ?></p>

        <div class="it-sheet-fields">
            <div class="it-field">
                <span class="it-field-label" id="broadcast-who"><?= $package->i18n('issue_tracker_to') ?></span>
                <div class="it-field-control">
                    <p class="it-field-help"><?= $package->i18n('issue_tracker_mail_broadcast_who') ?></p>
                    <div class="it-radio-list" role="radiogroup" aria-labelledby="broadcast-who">
                        <label><input type="radio" name="broadcast_recipients" value="issue_tracker" checked id="recipients-issue-tracker"> <?= $package->i18n('issue_tracker_broadcast_recipients_issue_tracker') ?></label>
                        <label><input type="radio" name="broadcast_recipients" value="all" id="recipients-all"> <?= $package->i18n('issue_tracker_broadcast_recipients_all') ?> <small class="it-muted-text">(<?= $package->i18n('issue_tracker_broadcast_email_only') ?>)</small></label>
                    </div>
                </div>
            </div>
            <div class="it-field" id="method-options">
                <span class="it-field-label" id="broadcast-how"><?= $package->i18n('issue_tracker_broadcast_method') ?></span>
                <div class="it-field-control">
                    <p class="it-field-help"><?= $package->i18n('issue_tracker_mail_broadcast_how') ?></p>
                    <div class="it-radio-list" role="radiogroup" aria-labelledby="broadcast-how">
                        <label><input type="radio" name="broadcast_method" value="message" checked id="method-message"> <?= $package->i18n('issue_tracker_broadcast_method_message') ?></label>
                        <label><input type="radio" name="broadcast_method" value="email" id="method-email"> <?= $package->i18n('issue_tracker_broadcast_method_email') ?></label>
                        <label><input type="radio" name="broadcast_method" value="both" id="method-both"> <?= $package->i18n('issue_tracker_broadcast_method_both') ?></label>
                    </div>
                </div>
            </div>
            <div class="it-field it-field--subject">
                <label class="it-field-label" for="broadcast-subject"><?= $package->i18n('issue_tracker_subject') ?> <span class="it-req"><?= $package->i18n('issue_tracker_mail_required') ?></span></label>
                <div class="it-field-control">
                    <input type="text" class="form-control" id="broadcast-subject" name="broadcast_subject" required maxlength="255">
                </div>
            </div>
        </div>

        <div class="it-sheet-editor">
            <label class="it-field-label" for="broadcast-message"><?= $package->i18n('issue_tracker_mail_message_text') ?> <span class="it-req"><?= $package->i18n('issue_tracker_mail_required') ?></span></label>
            <textarea class="form-control" id="broadcast-message" name="broadcast_message" rows="10" required></textarea>
        </div>

        <div class="it-sheet-foot">
            <a href="<?= rex_url::backendPage('issue_tracker/issues/list', ['box' => 'inbox']) ?>" class="btn btn-default"><?= $package->i18n('issue_tracker_cancel') ?></a>
            <button type="submit" class="btn btn-primary it-btn-primary" onclick="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_broadcast_confirm')) ?>"><?= $package->i18n('issue_tracker_mail_broadcast_send') ?></button>
        </div>
    </form>
</div>
