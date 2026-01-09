<?php
/**
 * Broadcast-Formular Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');
?>

<div class="issue-tracker-broadcast">
    <!-- Toolbar -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>" class="btn btn-default">
                <i class="rex-icon fa-arrow-left"></i> <?= $package->i18n('issue_tracker_back') ?>
            </a>
        </div>
    </div>

    <form method="post" action="<?= rex_url::currentBackendPage() ?>">
        <input type="hidden" name="send_broadcast" value="1" />

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="rex-icon fa-bullhorn"></i> <?= $package->i18n('issue_tracker_broadcast') ?>
                </h3>
            </div>
            <div class="panel-body">
                <p class="text-muted"><?= $package->i18n('issue_tracker_broadcast_info') ?></p>

                <div class="form-group">
                    <label for="broadcast-subject"><?= $package->i18n('issue_tracker_subject') ?> *</label>
                    <input type="text" class="form-control" id="broadcast-subject" name="broadcast_subject" required>
                </div>

                <div class="form-group">
                    <label for="broadcast-message"><?= $package->i18n('issue_tracker_message') ?> *</label>
                    <textarea class="form-control" id="broadcast-message" name="broadcast_message" rows="8" required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?= $package->i18n('issue_tracker_broadcast_recipients') ?></label>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="broadcast_recipients" value="issue_tracker" checked id="recipients-issue-tracker">
                                    <i class="rex-icon fa-users"></i> <?= $package->i18n('issue_tracker_broadcast_recipients_issue_tracker') ?>
                                </label>
                            </div>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="broadcast_recipients" value="all" id="recipients-all">
                                    <i class="rex-icon fa-globe"></i> <?= $package->i18n('issue_tracker_broadcast_recipients_all') ?>
                                    <small class="text-muted">(<?= $package->i18n('issue_tracker_broadcast_email_only') ?>)</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" id="method-options">
                        <div class="form-group">
                            <label><?= $package->i18n('issue_tracker_broadcast_method') ?></label>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="broadcast_method" value="message" checked id="method-message">
                                    <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_broadcast_method_message') ?>
                                </label>
                            </div>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="broadcast_method" value="email" id="method-email">
                                    <i class="rex-icon fa-at"></i> <?= $package->i18n('issue_tracker_broadcast_method_email') ?>
                                </label>
                            </div>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="broadcast_method" value="both" id="method-both">
                                    <i class="rex-icon fa-share-alt"></i> <?= $package->i18n('issue_tracker_broadcast_method_both') ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <button type="submit" class="btn btn-warning btn-lg" onclick="return confirm('<?= $package->i18n('issue_tracker_broadcast_confirm') ?>')">
                    <i class="rex-icon fa-bullhorn"></i> <?= $package->i18n('issue_tracker_send_broadcast') ?>
                </button>
            </div>
        </div>
    </form>
</div>
