<?php

/**
 * E-Mail-Templates Einstellungen.
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\EmailTemplateService;
use FriendsOfREDAXO\IssueTracker\PermissionService;

$package = rex_addon::get('issue_tracker');

// Nur Admins dürfen Einstellungen ändern
if (!PermissionService::canManageSettings()) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

// Templates auf Standard zurücksetzen
if (1 === rex_post('reset_templates', 'int', 0)) {
    $count = EmailTemplateService::resetToDefaults();
    echo rex_view::success($package->i18n('issue_tracker_templates_reset_success', $count));
}

// Templates speichern
if (1 === rex_post('save_templates', 'int', 0)) {
    $languages = ['de', 'en'];
    $templates = ['new_issue', 'new_comment', 'status_change', 'assignment'];

    foreach ($languages as $lang) {
        foreach ($templates as $template) {
            $key = 'email_template_' . $template . '_' . $lang;
            $value = rex_post($key, 'string', '');

            rex_sql::factory()
                ->setTable(rex::getTable('issue_tracker_settings'))
                ->setValue('setting_key', $key)
                ->setValue('setting_value', $value)
                ->insertOrUpdate();
        }
    }

    echo rex_view::success($package->i18n('issue_tracker_settings_saved'));
}

// HTML-Templates aus Service laden
$defaultTemplates = EmailTemplateService::getDefaultHtmlTemplates();

// Templates laden
$sql = rex_sql::factory();
$templates = [];

foreach ($defaultTemplates as $key => $defaultValue) {
    $sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = ?', [$key]);
    $templates[$key] = $sql->getRows() > 0 ? $sql->getValue('setting_value') : $defaultValue;
}

?>

<form method="post">
    <input type="hidden" name="save_templates" value="1" />
    
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><?= $package->i18n('issue_tracker_email_templates') ?></h3>
        </div>
        <div class="panel-body">
            <p class="help-block">
                <?= $package->i18n('issue_tracker_email_templates_help') ?><br>
                <strong><?= $package->i18n('issue_tracker_available_placeholders') ?>:</strong>
                <code>{{recipient_name}}</code>, <code>{{issue_id}}</code>, <code>{{issue_title}}</code>,
                <code>{{issue_category}}</code>, <code>{{issue_priority}}</code>, <code>{{issue_description}}</code>,
                <code>{{creator_name}}</code>, <code>{{comment_text}}</code>, <code>{{old_status}}</code>,
                <code>{{new_status}}</code>, <code>{{issue_url}}</code>
            </p>
            
            <ul class="nav nav-tabs" role="tablist">
                <li class="active"><a href="#tab-de" role="tab" data-toggle="tab">Deutsch</a></li>
                <li><a href="#tab-en" role="tab" data-toggle="tab">English</a></li>
            </ul>
            
            <div class="tab-content" style="padding-top: 20px;">
                <!-- Deutsche Templates -->
                <div id="tab-de" class="tab-pane active">
                    <h4><?= $package->i18n('issue_tracker_template_new_issue') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_new_issue_de" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_new_issue_de']) ?></textarea>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_new_comment') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_new_comment_de" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_new_comment_de']) ?></textarea>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_status_change') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_status_change_de" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_status_change_de']) ?></textarea>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_assignment') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_assignment_de" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_assignment_de']) ?></textarea>
                    </div>
                </div>
                
                <!-- Englische Templates -->
                <div id="tab-en" class="tab-pane">
                    <h4><?= $package->i18n('issue_tracker_template_new_issue') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_new_issue_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_new_issue_en']) ?></textarea>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_new_comment') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_new_comment_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_new_comment_en']) ?></textarea>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_status_change') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_status_change_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_status_change_en']) ?></textarea>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_assignment') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_assignment_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_assignment_en']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" class="btn btn-primary">
                <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
            </button>
        </div>
    </div>
</form>

<!-- Reset Button -->
<form method="post" onsubmit="return confirm('<?= $package->i18n('issue_tracker_templates_reset_confirm') ?>');">
    <input type="hidden" name="reset_templates" value="1" />
    
    <div class="panel panel-warning">
        <div class="panel-heading">
            <h3 class="panel-title"><?= $package->i18n('issue_tracker_templates_reset') ?></h3>
        </div>
        <div class="panel-body">
            <p><?= $package->i18n('issue_tracker_templates_reset_help') ?></p>
        </div>
        <div class="panel-footer">
            <button type="submit" class="btn btn-warning">
                <i class="rex-icon fa-refresh"></i> <?= $package->i18n('issue_tracker_templates_reset_button') ?>
            </button>
        </div>
    </div>
</form>
