<?php
/**
 * Einstellungen Fragment – einfache Sprache, Hinweise unter jedem Feld, seltene Optionen unter „Erweitert“
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');
$t = static fn (string $key): string => rex_i18n::rawMsg($key);

$menuTitle = $this->getVar('menuTitle', '');
$categories = $this->getVar('categories', []);
$emailEnabled = $this->getVar('emailEnabled', 1);
$emailFromAddress = $this->getVar('emailFromAddress', '');
$emailFromName = $this->getVar('emailFromName', 'REDAXO Issue Tracker');
$reminderCooldownHours = $this->getVar('reminderCooldownHours', 24);
$installationName = $this->getVar('installationName', '');
$apiToken = $this->getVar('apiToken', '');
$allTags = $this->getVar('allTags', []);
$editTag = $this->getVar('editTag', null);
$formErrors = $this->getVar('formErrors', []);

$phpmailerFrom = (string) (new rex_mailer())->From;

// „Erweitert“ öffnet sich bei abweichenden Werten oder einem Fehler darin
$advancedOpen = '' !== (string) $emailFromAddress
    || 24 !== (int) $reminderCooldownHours
    || '' !== (string) $installationName
    || '' !== (string) $apiToken
    || isset($formErrors['email-from-address']) || isset($formErrors['reminder-cooldown']);

// Kategorien (Aufbau kompatibel mit issue_tracker.js: #issue-tracker-categories, .category-row, .remove-category)
$catRows = '';
foreach ($categories as $category) {
    $catRows .= '<div class="form-group category-row"><div class="input-group">'
        . '<input type="text" class="form-control" name="categories[]" value="' . rex_escape($category) . '" required aria-label="' . rex_escape($t('issue_tracker_category')) . '">'
        . '<span class="input-group-btn"><button type="button" class="btn btn-danger remove-category" title="' . rex_escape($t('issue_tracker_delete')) . '" aria-label="' . rex_escape($t('issue_tracker_delete')) . '">'
        . '<i class="rex-icon fa-trash" aria-hidden="true"></i></button></span></div></div>';
}
$categoriesControl = '<div id="issue-tracker-categories" class="it-cats">' . $catRows . '</div>'
    . '<div><button type="button" class="btn btn-default btn-sm" id="add-category"><i class="rex-icon fa-plus" aria-hidden="true"></i> ' . rex_escape($t('issue_tracker_add_category')) . '</button></div>';

$tokenControl = '<div class="input-group">'
    . '<input type="text" class="form-control it-mono" id="api-token" value="' . rex_escape($apiToken) . '" readonly placeholder="' . rex_escape($t('issue_tracker_no_api_token')) . '">'
    . '<span class="input-group-btn">'
    . '<button type="button" class="btn btn-default" id="copy-api-token" title="' . rex_escape($t('issue_tracker_copy')) . '" aria-label="' . rex_escape($t('issue_tracker_copy')) . '"' . ($apiToken ? '' : ' disabled') . '><i class="rex-icon fa-copy" aria-hidden="true"></i></button>'
    . '<button type="button" class="btn btn-default" id="generate-api-token" data-url="' . rex_escape(rex_url::backendController(rex_api_issue_tracker_generate_token::getUrlParams(), false)) . '"><i class="rex-icon fa-refresh" aria-hidden="true"></i> ' . rex_escape($t('issue_tracker_generate_token')) . '</button>'
    . '</span></div>';

$advancedHtml = '<h3 class="it-group-title">' . rex_escape($t('issue_tracker_ui_g_email_advanced')) . '</h3><div class="it-group">'
    . UiHelper::field($t('issue_tracker_ui_f_from_address'),
        '<input type="email" class="form-control" id="email-from-address" name="email_from_address" value="' . rex_escape($emailFromAddress) . '" placeholder="' . rex_escape($phpmailerFrom) . '">',
        $t('issue_tracker_ui_f_from_address_help'), 'email-from-address', false, $formErrors['email-from-address'] ?? '')
    . UiHelper::field($t('issue_tracker_ui_f_cooldown'),
        '<input type="number" class="form-control it-num" id="reminder-cooldown" name="reminder_cooldown_hours" value="' . rex_escape((string) $reminderCooldownHours) . '" min="1" max="720" required>',
        $t('issue_tracker_ui_f_cooldown_help'), 'reminder-cooldown', true, $formErrors['reminder-cooldown'] ?? '')
    . '</div>'
    . '<h3 class="it-group-title">' . rex_escape($t('issue_tracker_ui_g_api')) . '</h3><div class="it-group">'
    . UiHelper::field($t('issue_tracker_ui_f_installation'),
        '<input type="text" class="form-control" id="installation-name" name="installation_name" value="' . rex_escape($installationName) . '" placeholder="' . rex_escape($t('issue_tracker_installation_name_placeholder')) . '">',
        $t('issue_tracker_ui_f_installation_help'), 'installation-name')
    . UiHelper::field($t('issue_tracker_ui_f_token'), $tokenControl, $t('issue_tracker_ui_f_token_help'), 'api-token', false, '', true)
    . ($apiToken ? UiHelper::field($t('issue_tracker_api_endpoint'), '', rex::getServer() . 'index.php?rex-api-call=issue_tracker_stats') : '')
    . '</div>';
?>

<div class="issue-tracker-settings">
    <?= UiHelper::errorSummary($formErrors) ?>

    <form method="post" action="<?= rex_url::currentBackendPage() ?>" novalidate>
        <input type="hidden" name="save_settings" value="1" />
        <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>

        <h3 class="it-group-title"><?= rex_escape($t('issue_tracker_ui_g_general')) ?></h3>
        <div class="it-group">
            <?= UiHelper::field($t('issue_tracker_ui_f_menu_title'),
                '<input type="text" class="form-control" id="menu-title" name="menu_title" value="' . rex_escape($menuTitle) . '" placeholder="' . rex_escape($t('issue_tracker_menu_title_placeholder')) . '">',
                $t('issue_tracker_ui_f_menu_title_help'), 'menu-title') ?>
            <?= UiHelper::field($t('issue_tracker_ui_f_fullscreen'), UiHelper::switch('fullscreen', (bool) ($this->getVar('fullscreen', true)), 'it-fullscreen'), $t('issue_tracker_ui_f_fullscreen_help'), 'it-fullscreen') ?>
            <?= UiHelper::field($t('issue_tracker_ui_f_categories'), $categoriesControl, $t('issue_tracker_ui_f_categories_help'), 'issue-tracker-categories', true, $formErrors['issue-tracker-categories'] ?? '', true) ?>
        </div>

        <h3 class="it-group-title"><?= rex_escape($t('issue_tracker_ui_g_email')) ?></h3>
        <div class="it-group">
            <?= UiHelper::field($t('issue_tracker_ui_f_email_enabled'), UiHelper::switch('email_enabled', (bool) $emailEnabled, 'email-enabled'), $t('issue_tracker_ui_f_email_enabled_help'), 'email-enabled') ?>
            <?= UiHelper::field($t('issue_tracker_ui_f_from_name'),
                '<input type="text" class="form-control" id="email-from-name" name="email_from_name" value="' . rex_escape($emailFromName) . '" required>',
                $t('issue_tracker_ui_f_from_name_help'), 'email-from-name', true, $formErrors['email-from-name'] ?? '') ?>
        </div>

        <?= UiHelper::advanced($advancedHtml, $advancedOpen, $t('issue_tracker_ui_settings_advanced_hint')) ?>

        <div class="it-settings-actions">
            <a href="<?= rex_url::currentBackendPage() ?>" class="btn btn-default"><?= rex_escape($t('issue_tracker_ui_cancel')) ?></a>
            <button type="submit" class="btn btn-save"><?= rex_escape($t('issue_tracker_ui_btn_save')) ?></button>
        </div>
    </form>

    <h3 class="it-group-title" id="tag-form"><?= rex_escape($t('issue_tracker_tags')) ?> <span class="it-count">(<?= count($allTags) ?>)</span></h3>
    <p class="it-group-note it-group-note--top"><?= rex_escape($t('issue_tracker_ui_tags_help')) ?></p>
    <div class="it-group">
        <?php if (!empty($allTags)): ?>
            <?php foreach ($allTags as $tag): ?>
            <div class="it-item">
                <span class="it-dot" style="<?= UiHelper::colorVar($tag->getColor()) ?>"></span>
                <div class="it-item-body">
                    <div class="it-item-title"><strong><?= rex_escape($tag->getName()) ?></strong></div>
                </div>
                <div class="it-item-side">
                    <a href="<?= rex_url::currentBackendPage(['func' => 'edit_tag', 'tag_id' => $tag->getId()]) ?>#tag-form" class="btn btn-xs btn-default" title="<?= $package->i18n('issue_tracker_edit') ?>" aria-label="<?= $package->i18n('issue_tracker_edit') ?>: <?= rex_escape($tag->getName()) ?>">
                        <i class="rex-icon fa-pencil" aria-hidden="true"></i>
                    </a>
                    <a href="<?= rex_url::currentBackendPage(['func' => 'delete_tag', 'tag_id' => $tag->getId()] + \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getUrlParams()) ?>#tag-form" class="btn btn-xs btn-default" title="<?= $package->i18n('issue_tracker_delete') ?>" aria-label="<?= $package->i18n('issue_tracker_delete') ?>: <?= rex_escape($tag->getName()) ?>"
                       data-confirm="<?= rex_escape(rex_i18n::rawMsg('issue_tracker_ui_tag_delete_confirm', $tag->getName())) ?>">
                        <i class="rex-icon fa-trash" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="it-empty"><i class="rex-icon fa-tags" aria-hidden="true"></i><?= $package->i18n('issue_tracker_no_tags') ?></div>
        <?php endif; ?>
        <?php
        // Zufallsfarbe für neue Tags generieren
        $randomColors = ['#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#f39c12', '#1abc9c', '#e67e22', '#34495e', '#16a085', '#c0392b', '#8e44ad', '#27ae60'];
        $defaultColor = $editTag ? $editTag->getColor() : $randomColors[array_rand($randomColors)];
        ?>
        <div class="it-row">
            <form method="post" action="<?= rex_url::currentBackendPage() ?>#tag-form" class="it-tag-form">
                <input type="hidden" name="save_tag" value="1" />
                <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>
                <?php if ($editTag): ?>
                <input type="hidden" name="tag_id" value="<?= $editTag->getId() ?>" />
                <?php endif; ?>
                <label class="it-sr" for="tag-name"><?= rex_escape($t('issue_tracker_tag_name')) ?></label>
                <input type="text" class="form-control" id="tag-name" name="tag_name" value="<?= $editTag ? rex_escape($editTag->getName()) : '' ?>" placeholder="<?= rex_escape($t($editTag ? 'issue_tracker_tag_name' : 'issue_tracker_ui_new_tag')) ?>" required>
                <label class="it-sr" for="tag-color"><?= rex_escape($t('issue_tracker_tag_color')) ?></label>
                <input type="color" class="form-control" id="tag-color" name="tag_color" value="<?= rex_escape($defaultColor) ?>" title="<?= rex_escape($t('issue_tracker_tag_color')) ?>">
                <button type="submit" class="btn btn-default">
                    <i class="rex-icon fa-<?= $editTag ? 'check' : 'plus' ?>" aria-hidden="true"></i>
                    <?= rex_escape($t($editTag ? 'issue_tracker_ui_btn_save' : 'issue_tracker_add_tag')) ?>
                </button>
                <?php if ($editTag): ?>
                <a href="<?= rex_url::currentBackendPage() ?>#tag-form" class="btn btn-default"><?= rex_escape($t('issue_tracker_ui_cancel')) ?></a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
