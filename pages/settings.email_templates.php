<?php

/**
 * E-Mail-Templates Einstellungen.
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\EmailTemplateService;
use FriendsOfREDAXO\IssueTracker\PermissionService;
use FriendsOfREDAXO\IssueTracker\NotificationService;

$package = rex_addon::get('issue_tracker');

// Nur Admins dürfen Einstellungen ändern
if (!PermissionService::canManageSettings()) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

// Test-E-Mail senden
if (rex_post('test_template', 'string', '') !== '') {
    $testUser = rex::getUser();
    $testEmail = $testUser->getValue('email');
    
    if (!$testEmail) {
        echo rex_view::error($package->i18n('issue_tracker_test_email_no_address'));
    } else {
        $templateKey = rex_post('test_template', 'string', '');
        $templateType = str_replace(['email_template_', '_de', '_en'], '', $templateKey);
        $lang = strpos($templateKey, '_de') !== false ? 'de' : 'en';
        
        // Template laden
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = ?', [$templateKey]);
        
        if ($sql->getRows() > 0) {
            $templateContent = $sql->getValue('setting_value');
            
            // Test-Daten für Platzhalter
            $testData = [
                'recipient_name' => $testUser->getValue('name') ?: 'Test User',
                'issue_id' => '42',
                'issue_title' => 'Test Issue: Login-Problem beheben',
                'issue_category' => 'Bug',
                'issue_priority' => 'high',
                'issue_status' => 'Offen',
                'issue_description' => "Dies ist eine Test-Beschreibung.\n\nMit mehreren Zeilen und **Markdown** Formatierung.",
                'creator_name' => 'Max Mustermann',
                'sent_by_name' => 'Max Mustermann',
                'due_date' => '<br><strong>Fällig am:</strong> <span class="badge badge-danger">⚠ 01.02.2026</span>',
                'comment_text' => 'Das ist ein Test-Kommentar mit wichtigen Informationen.',
                'old_status' => 'open',
                'new_status' => 'in_progress',
                'issue_url' => rex::getServer() . rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => 42]),
            ];
            
            // Platzhalter ersetzen
            foreach ($testData as $key => $value) {
                $templateContent = str_replace('{{' . $key . '}}', $value, $templateContent);
            }
            
            // HTML-Wrapper hinzufügen falls nötig
            if (false === strpos($templateContent, '<!DOCTYPE html>')) {
                $templateContent = EmailTemplateService::getHtmlWrapper($templateContent, 'Test: ' . $package->i18n('issue_tracker_template_' . $templateType));
            }
            
            // E-Mail senden
            try {
                $mail = new rex_mailer();
                $mail->setFrom(rex::getProperty('server'), 'REDAXO Issue Tracker (Test)');
                $mail->addAddress($testEmail);
                $mail->Subject = '[TEST] ' . $package->i18n('issue_tracker_template_' . $templateType);
                $mail->isHTML(true);
                $mail->Body = $templateContent;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $templateContent));
                
                if ($mail->send()) {
                    echo rex_view::success($package->i18n('issue_tracker_test_email_sent', $testEmail));
                } else {
                    echo rex_view::error($package->i18n('issue_tracker_test_email_failed') . ': ' . $mail->ErrorInfo);
                }
            } catch (Exception $e) {
                echo rex_view::error($package->i18n('issue_tracker_test_email_failed') . ': ' . $e->getMessage());
            }
        } else {
            echo rex_view::error($package->i18n('issue_tracker_template_not_found'));
        }
    }
}

// Templates auf Standard zurücksetzen
if (1 === rex_post('reset_templates', 'int', 0)) {
    $count = EmailTemplateService::resetToDefaults();
    echo rex_view::success($package->i18n('issue_tracker_templates_reset_success', $count));
}

// Templates speichern
if (1 === rex_post('save_templates', 'int', 0)) {
    $languages = ['de', 'en'];
    $templates = ['new_issue', 'new_comment', 'status_change', 'assignment', 'reminder'];

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
                <?= $package->i18n('issue_tracker_available_placeholders') ?>:
                <code>{{recipient_name}}</code>, <code>{{issue_id}}</code>, <code>{{issue_title}}</code>,
                <code>{{issue_category}}</code>, <code>{{issue_priority}}</code>, <code>{{issue_description}}</code>,
                <code>{{creator_name}}</code>, <code>{{comment_text}}</code>, <code>{{old_status}}</code>,
                <code>{{new_status}}</code>, <code>{{issue_url}}</code>, <code>{{sent_by_name}}</code>,
                <code>{{issue_status}}</code>, <code>{{due_date}}</code>
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
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_new_issue_de')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_new_comment') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_new_comment_de" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_new_comment_de']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_new_comment_de')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_status_change') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_status_change_de" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_status_change_de']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_status_change_de')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_assignment') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_assignment_de" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_assignment_de']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_assignment_de')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_reminder') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_reminder_de" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_reminder_de']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_reminder_de')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                </div>
                
                <!-- Englische Templates -->
                <div id="tab-en" class="tab-pane">
                    <h4><?= $package->i18n('issue_tracker_template_new_issue') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_new_issue_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_new_issue_en']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_new_issue_en')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_new_comment') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_new_comment_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_new_comment_en']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_new_comment_en')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_status_change') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_status_change_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_status_change_en']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_status_change_en')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_assignment') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_assignment_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_assignment_en']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_assignment_en')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
                    </div>
                    
                    <h4><?= $package->i18n('issue_tracker_template_reminder') ?></h4>
                    <div class="form-group">
                        <textarea name="email_template_reminder_en" class="form-control" rows="10"><?= htmlspecialchars($templates['email_template_reminder_en']) ?></textarea>
                        <button type="button" class="btn btn-xs btn-info" style="margin-top: 5px;" onclick="testTemplate('email_template_reminder_en')">
                            <i class="rex-icon fa-envelope"></i> <?= $package->i18n('issue_tracker_send_test_email') ?>
                        </button>
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

<!-- Verstecktes Form für Test-E-Mails -->
<form method="post" id="test-email-form" style="display: none;">
    <input type="hidden" name="test_template" id="test-template-key" value="" />
</form>

<script>
function testTemplate(templateKey) {
    if (!confirm('<?= $package->i18n('issue_tracker_test_email_confirm') ?>')) {
        return;
    }
    
    document.getElementById('test-template-key').value = templateKey;
    document.getElementById('test-email-form').submit();
}
</script>

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
