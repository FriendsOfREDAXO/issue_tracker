<?php
/**
 * Einstellungen Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$categories = $this->getVar('categories', []);
$emailEnabled = $this->getVar('emailEnabled', 1);
$emailFromName = $this->getVar('emailFromName', 'REDAXO Issue Tracker');
$allTags = $this->getVar('allTags', []);
$editTag = $this->getVar('editTag', null);
?>

<div class="issue-tracker-settings">
    <!-- Kategorien verwalten -->
    <form method="post" action="<?= rex_url::currentBackendPage() ?>">
        <input type="hidden" name="save_settings" value="1" />

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?= $package->i18n('issue_tracker_categories') ?></h3>
            </div>
            <div class="panel-body">
                <div id="issue-tracker-categories">
                    <?php foreach ($categories as $index => $category): ?>
                    <div class="form-group category-row">
                        <div class="input-group">
                            <input type="text" class="form-control" name="categories[]" value="<?= rex_escape($category) ?>" required>
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-danger remove-category">
                                    <i class="rex-icon fa-trash"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-default" id="add-category">
                    <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_add_category') ?>
                </button>
            </div>
        </div>

        <!-- E-Mail-Einstellungen -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?= $package->i18n('issue_tracker_email_settings') ?></h3>
            </div>
            <div class="panel-body">
                <div class="checkbox">
                    <label>
                        <input type="hidden" name="email_enabled" value="0">
                        <input type="checkbox" name="email_enabled" value="1" <?= $emailEnabled ? 'checked' : '' ?>>
                        <?= $package->i18n('issue_tracker_email_enabled') ?>
                    </label>
                </div>

                <div class="form-group">
                    <label for="email-from-name"><?= $package->i18n('issue_tracker_email_from_name') ?></label>
                    <input type="text" class="form-control" id="email-from-name" name="email_from_name" 
                           value="<?= rex_escape($emailFromName) ?>" required>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
        </button>
    </forTags verwalten -->
    <div style="margin-top: 30px;">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?= $package->i18n('issue_tracker_tags') ?></h3>
            </div>
            <div class="panel-body">
                <?php if (!empty($allTags)): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?= $package->i18n('issue_tracker_tag_name') ?></th>
                            <th><?= $package->i18n('issue_tracker_tag_color') ?></th>
                            <th><?= $package->i18n('issue_tracker_tag_preview') ?></th>
                            <th style="width: 150px;"><?= $package->i18n('issue_tracker_actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allTags as $tag): ?>
                        <tr>
                            <td><?= rex_escape($tag->getName()) ?></td>
                            <td><?= rex_escape($tag->getColor()) ?></td>
                            <td>
                                <span class="label" style="background-color: <?= rex_escape($tag->getColor()) ?>">
                                    <?= rex_escape($tag->getName()) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= rex_url::currentBackendPage(['func' => 'edit_tag', 'tag_id' => $tag->getId()]) ?>" class="btn btn-xs btn-default">
                                    <i class="rex-icon fa-pencil"></i>
                                </a>
                                <a href="<?= rex_url::currentBackendPage(['func' => 'delete_tag', 'tag_id' => $tag->getId()]) ?>" 
                                   class="btn btn-xs btn-danger" 
                                   onclick="return confirm('<?= $package->i18n('issue_tracker_tag_delete_confirm') ?>')">
                                    <i class="rex-icon fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted"><?= $package->i18n('issue_tracker_no_tags') ?></p>
                <?php endif; ?>

                <!-- Tag hinzufügen/bearbeiten Formular -->
                <form method="post" action="<?= rex_url::currentBackendPage() ?>" class="form-inline" style="margin-top: 20px;">
                    <input type="hidden" name="save_tag" value="1" />
                    <?php if ($editTag): ?>
                    <input type="hidden" name="tag_id" value="<?= $editTag->getId() ?>" />
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="tag-name"><?= $package->i18n('issue_tracker_tag_name') ?></label>
                        <input type="text" class="form-control" id="tag-name" name="tag_name" 
                               value="<?= $editTag ? rex_escape($editTag->getName()) : '' ?>" 
                               placeholder="<?= $package->i18n('issue_tracker_tag_name') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="tag-color"><?= $package->i18n('issue_tracker_tag_color') ?></label>
                        <input type="color" class="form-control" id="tag-color" name="tag_color" 
                               value="<?= $editTag ? rex_escape($editTag->getColor()) : '#007bff' ?>"
                               style="height: 34px; padding: 3px;">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="rex-icon fa-<?= $editTag ? 'save' : 'plus' ?>"></i> 
                        <?= $editTag ? $package->i18n('issue_tracker_save') : $package->i18n('issue_tracker_add_tag') ?>
                    </button>
                    
                    <?php if ($editTag): ?>
                    <a href="<?= rex_url::currentBackendPage() ?>" class="btn btn-default">
                        <i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_cancel') ?>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- m>

    <!-- Broadcast-Nachricht -->
    <form method="post" action="<?= rex_url::currentBackendPage() ?>" style="margin-top: 30px;">
        <input type="hidden" name="send_broadcast" value="1" />

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?= $package->i18n('issue_tracker_broadcast') ?></h3>
            </div>
            <div class="panel-body">
                <p class="text-muted"><?= $package->i18n('issue_tracker_broadcast_info') ?></p>

                <div class="form-group">
                    <label for="broadcast-subject"><?= $package->i18n('issue_tracker_subject') ?></label>
                    <input type="text" class="form-control" id="broadcast-subject" name="broadcast_subject" required>
                </div>

                <div class="form-group">
                    <label for="broadcast-message"><?= $package->i18n('issue_tracker_message') ?></label>
                    <textarea class="form-control" id="broadcast-message" name="broadcast_message" rows="6" required></textarea>
                </div>

                <button type="submit" class="btn btn-warning" onclick="return confirm('<?= $package->i18n('issue_tracker_broadcast_confirm') ?>')">
                    <i class="rex-icon fa-send"></i> <?= $package->i18n('issue_tracker_send_broadcast') ?>
                </button>
            </div>
        </div>
    </form>
</div>
