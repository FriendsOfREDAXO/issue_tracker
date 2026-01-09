<?php
/**
 * Einstellungen Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$menuTitle = $this->getVar('menuTitle', '');
$categories = $this->getVar('categories', []);
$emailEnabled = $this->getVar('emailEnabled', 1);
$emailFromName = $this->getVar('emailFromName', 'REDAXO Issue Tracker');
$installationName = $this->getVar('installationName', '');
$apiToken = $this->getVar('apiToken', '');
$allTags = $this->getVar('allTags', []);
$editTag = $this->getVar('editTag', null);
?>

<div class="issue-tracker-settings">
    <!-- Allgemeine Einstellungen -->
    <form method="post" action="<?= rex_url::currentBackendPage() ?>">
        <input type="hidden" name="save_settings" value="1" />

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?= $package->i18n('issue_tracker_general_settings') ?></h3>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label for="menu-title"><?= $package->i18n('issue_tracker_menu_title') ?></label>
                    <input type="text" class="form-control" id="menu-title" name="menu_title" 
                           value="<?= rex_escape($menuTitle) ?>" 
                           placeholder="<?= $package->i18n('issue_tracker_menu_title_placeholder') ?>">
                    <p class="help-block"><?= $package->i18n('issue_tracker_menu_title_help') ?></p>
                </div>
            </div>
        </div>

        <!-- Kategorien verwalten -->
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

        <!-- API-Einstellungen -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?= $package->i18n('issue_tracker_api_settings') ?></h3>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label for="installation-name"><?= $package->i18n('issue_tracker_installation_name') ?></label>
                    <input type="text" class="form-control" id="installation-name" name="installation_name" 
                           value="<?= rex_escape($installationName) ?>" 
                           placeholder="<?= $package->i18n('issue_tracker_installation_name_placeholder') ?>">
                    <p class="help-block"><?= $package->i18n('issue_tracker_installation_name_help') ?></p>
                </div>

                <div class="form-group">
                    <label><?= $package->i18n('issue_tracker_api_token') ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="api-token" 
                               value="<?= rex_escape($apiToken) ?>" readonly 
                               placeholder="<?= $package->i18n('issue_tracker_no_api_token') ?>">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="copy-api-token" title="<?= $package->i18n('issue_tracker_copy') ?>" <?= $apiToken ? '' : 'disabled' ?>>
                                <i class="rex-icon fa-copy"></i>
                            </button>
                            <button type="button" class="btn btn-primary" id="generate-api-token">
                                <i class="rex-icon fa-refresh"></i> <?= $package->i18n('issue_tracker_generate_token') ?>
                            </button>
                        </span>
                    </div>
                    <p class="help-block"><?= $package->i18n('issue_tracker_api_token_help') ?></p>
                    <?php if ($apiToken): ?>
                    <p class="help-block">
                        <strong><?= $package->i18n('issue_tracker_api_endpoint') ?>:</strong><br>
                        <code><?= rex::getServer() ?>index.php?rex-api-call=issue_tracker_stats</code>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
        </button>
    </form>

    <!-- Tags verwalten -->
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
                                <a href="<?= rex_url::currentBackendPage(['func' => 'edit_tag', 'tag_id' => $tag->getId()]) ?>#tag-form" class="btn btn-xs btn-default">
                                    <i class="rex-icon fa-pencil"></i>
                                </a>
                                <a href="<?= rex_url::currentBackendPage(['func' => 'delete_tag', 'tag_id' => $tag->getId()]) ?>#tag-form" 
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
                <div id="tag-form"></div>
                <?php 
                // Zufallsfarbe für neue Tags generieren
                $randomColors = ['#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#f39c12', '#1abc9c', '#e67e22', '#34495e', '#16a085', '#c0392b', '#8e44ad', '#27ae60'];
                $defaultColor = $editTag ? $editTag->getColor() : $randomColors[array_rand($randomColors)];
                ?>
                <form method="post" action="<?= rex_url::currentBackendPage() ?>#tag-form" class="form-inline" style="margin-top: 20px;">
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
                               value="<?= rex_escape($defaultColor) ?>"
                               style="height: 34px; width: 60px; padding: 2px; cursor: pointer;">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="rex-icon fa-<?= $editTag ? 'save' : 'plus' ?>"></i> 
                        <?= $editTag ? $package->i18n('issue_tracker_save') : $package->i18n('issue_tracker_add_tag') ?>
                    </button>
                    
                    <?php if ($editTag): ?>
                    <a href="<?= rex_url::currentBackendPage() ?>#tag-form" class="btn btn-default">
                        <i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_cancel') ?>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
