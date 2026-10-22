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

echo \FriendsOfREDAXO\IssueTracker\UiHelper::settingsStart('email_templates');

// Test-E-Mail senden
if (rex_post('test_template', 'string', '') !== '' && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
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
                'affected_page' => '<br><strong>' . rex_i18n::rawMsgInLocale('issue_tracker_affected_page', 'en' === $lang ? 'en_gb' : 'de_de') . ':</strong> <a href="' . rex_escape(rex::getServer()) . '">' . rex_escape((string) rex::getServerName()) . '</a> ' . rex_escape(rex::getServer()),
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
if (1 === rex_post('reset_templates', 'int', 0) && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $count = EmailTemplateService::resetToDefaults();
    echo rex_view::success($package->i18n('issue_tracker_templates_reset_success', $count));
}

// Templates speichern
if (1 === rex_post('save_templates', 'int', 0) && \FriendsOfREDAXO\IssueTracker\UiHelper::csrfCheck()) {
    $languages = ['de', 'en'];
    $templates = ['new_issue', 'new_comment', 'status_change', 'assignment', 'reminder'];

    foreach ($languages as $lang) {
        foreach ($templates as $template) {
            $key = 'email_template_' . $template . '_' . $lang;
            $value = str_replace(["\r\n", "\r"], "\n", rex_post($key, 'string', ''));

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


$t = static fn (string $key): string => rex_i18n::rawMsg($key);
$tplTypes = [
    'new_issue' => ['fa-plus-circle', 'blue'],
    'new_comment' => ['fa-comment', 'green'],
    'status_change' => ['fa-exchange', 'orange'],
    'assignment' => ['fa-user', 'purple'],
    'reminder' => ['fa-clock-o', 'red'],
];
$tplLangs = ['de' => 'Deutsch', 'en' => 'English'];
// Platzhalter mit verständlicher Beschreibung und Beispielwert für die Vorschau
$placeholders = [
    'recipient_name' => (string) (rex::getUser()->getValue('name') ?: rex::getUser()->getLogin()),
    'issue_title' => 'Login-Problem beheben',
    'issue_id' => '42',
    'issue_url' => '#',
    'issue_status' => rex_i18n::rawMsg('issue_tracker_status_open'),
    'issue_priority' => rex_i18n::rawMsg('issue_tracker_priority_high'),
    'issue_category' => 'Technik',
    'issue_description' => 'Nach dem Update können sich einige Redakteure nicht mehr anmelden.',
    'creator_name' => 'Max Mustermann',
    'sent_by_name' => 'Max Mustermann',
    'comment_text' => 'Ich habe mir das angesehen und melde mich morgen.',
    'old_status' => rex_i18n::rawMsg('issue_tracker_status_open'),
    'new_status' => rex_i18n::rawMsg('issue_tracker_status_in_progress'),
    'due_date' => '<br><strong>Fällig am:</strong> 01.02.2026',
    'affected_page' => '<br><strong>' . rex_i18n::rawMsg('issue_tracker_affected_page') . ':</strong> <a href="#">Kontakt</a> https://www.example.org/kontakt/',
];
?>

<form method="post" class="it-tpl-form">
    <input type="hidden" name="save_templates" value="1" />
    <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>

    <div class="it-tpl" data-it-tpl data-it-sample="<?= rex_escape(json_encode($placeholders, JSON_UNESCAPED_UNICODE)) ?>">
        <div class="it-tpl-side">
            <h3 class="it-group-title"><?= rex_escape($t('issue_tracker_ui_tpl_choose')) ?></h3>
            <div class="it-segmented it-tpl-lang" role="group" aria-label="<?= rex_escape($t('issue_tracker_ui_language')) ?>">
                <?php foreach ($tplLangs as $langKey => $langName): ?>
                <button type="button" data-it-tpl-lang="<?= $langKey ?>" aria-pressed="<?= 'de' === $langKey ? 'true' : 'false' ?>"><?= $langName ?></button>
                <?php endforeach; ?>
            </div>
            <div class="it-group it-tpl-list" role="tablist" aria-orientation="vertical">
                <?php foreach ($tplTypes as $type => [$fa, $color]): ?>
                <button type="button" role="tab" data-it-tpl-type="<?= $type ?>" aria-selected="<?= 'new_issue' === $type ? 'true' : 'false' ?>">
                    <span class="it-icon it-c-<?= $color ?>" aria-hidden="true"><i class="rex-icon <?= $fa ?>"></i></span>
                    <span><span class="it-tpl-name"><?= rex_escape($t('issue_tracker_template_' . $type)) ?></span><small><?= rex_escape($t('issue_tracker_ui_tpl_when_' . $type)) ?></small></span>
                </button>
                <?php endforeach; ?>
            </div>
            <h3 class="it-group-title"><?= rex_escape($t('issue_tracker_ui_tpl_placeholders')) ?></h3>
            <div class="it-group it-chips-box">
                <p class="it-chips-help"><?= rex_escape($t('issue_tracker_ui_tpl_placeholders_help')) ?></p>
                <div class="it-chips">
                    <?php foreach (array_keys($placeholders) as $ph): ?>
                    <button type="button" class="it-chip" data-it-insert="{{<?= $ph ?>}}" title="{{<?= $ph ?>}}"><?= rex_escape($t('issue_tracker_ui_ph_' . $ph)) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="it-tpl-main">
            <?php foreach ($tplLangs as $langKey => $langName): ?>
                <?php foreach ($tplTypes as $type => $_):
                    $key = 'email_template_' . $type . '_' . $langKey;
                ?>
                <div class="it-group it-tpl-pane" data-lang="<?= $langKey ?>" data-type="<?= $type ?>" role="tabpanel">
                    <div class="it-tpl-pane-head">
                        <h3><label for="tpl-<?= $key ?>"><?= rex_escape($t('issue_tracker_template_' . $type)) ?> <span class="it-badge"><?= $langName ?></span></label></h3>
                        <button type="submit" form="test-email-form" name="test_template" value="<?= $key ?>" class="btn btn-default btn-xs"
                                data-confirm="<?= rex_escape($t('issue_tracker_ui_tpl_test_confirm')) ?>">
                            <i class="rex-icon fa-paper-plane" aria-hidden="true"></i> <?= rex_escape($t('issue_tracker_ui_tpl_test')) ?>
                        </button>
                    </div>
                    <div class="it-tpl-pane-body">
                        <small class="it-tpl-hint" id="tpl-<?= $key ?>-help"><?= rex_escape($t('issue_tracker_ui_tpl_editor_help')) ?></small>
                        <textarea id="tpl-<?= $key ?>" name="<?= $key ?>" class="form-control it-mono" rows="14" aria-describedby="tpl-<?= $key ?>-help"><?= htmlspecialchars($templates[$key]) ?></textarea>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <div class="it-group it-tpl-preview" hidden>
                <div class="it-tpl-pane-head"><h3><?= rex_escape($t('issue_tracker_ui_tpl_preview')) ?></h3><small class="it-tpl-hint"><?= rex_escape($t('issue_tracker_ui_tpl_preview_help')) ?></small></div>
                <iframe class="it-tpl-frame" title="<?= rex_escape($t('issue_tracker_ui_tpl_preview')) ?>" sandbox=""></iframe>
            </div>
            <div class="it-settings-actions">
                <a href="<?= rex_url::currentBackendPage() ?>" class="btn btn-default"><?= rex_escape($t('issue_tracker_ui_cancel')) ?></a>
                <button type="submit" class="btn btn-save"><?= rex_escape($t('issue_tracker_ui_btn_save')) ?></button>
            </div>
        </div>
    </div>
</form>

<!-- Verstecktes Formular für Test-E-Mails (die Knöpfe oben senden test_template über form="…") -->
<form method="post" id="test-email-form" hidden><?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?></form>

<h3 class="it-group-title it-danger-title"><?= rex_escape($t('issue_tracker_ui_danger_zone')) ?></h3>
<div class="it-group it-danger-zone">
    <div class="it-row">
        <div class="it-row-label">
            <strong><?= rex_escape($t('issue_tracker_ui_tpl_reset')) ?></strong>
            <small><?= rex_escape($t('issue_tracker_ui_tpl_reset_help')) ?></small>
        </div>
        <div class="it-row-control">
            <form method="post" class="it-inline-form" data-confirm="<?= rex_escape($t('issue_tracker_ui_tpl_reset_confirm')) ?>">
                <input type="hidden" name="reset_templates" value="1" />
                <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>
                <button type="submit" class="btn btn-default it-btn-danger">
                    <i class="rex-icon fa-undo" aria-hidden="true"></i> <?= rex_escape($t('issue_tracker_ui_tpl_reset_button')) ?>
                </button>
            </form>
        </div>
    </div>
</div>
<?= \FriendsOfREDAXO\IssueTracker\UiHelper::settingsEnd() ?>
