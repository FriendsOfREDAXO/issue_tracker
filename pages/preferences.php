<?php

/**
 * Benutzereinstellungen für E-Mail-Benachrichtigungen
 * 
 * @package issue_tracker
 */

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

// Formular speichern
if (rex_post('save_preferences', 'boolean')) {
    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('issue_tracker_notifications'));
    
    $sql->setValue('user_id', $currentUser->getId());
    $sql->setValue('email_on_new', rex_post('email_on_new', 'int', 1));
    $sql->setValue('email_on_comment', rex_post('email_on_comment', 'int', 1));
    $sql->setValue('email_on_status_change', rex_post('email_on_status_change', 'int', 1));
    $sql->setValue('email_on_assignment', rex_post('email_on_assignment', 'int', 1));
    
    try {
        $sql->insertOrUpdate();
        echo rex_view::success($package->i18n('issue_tracker_preferences_saved'));
    } catch (rex_sql_exception $e) {
        echo rex_view::error($package->i18n('issue_tracker_preferences_error') . ': ' . $e->getMessage());
    }
}

// Aktuelle Einstellungen laden
$sql = rex_sql::factory();
$sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_notifications') . ' WHERE user_id = ?', [$currentUser->getId()]);

$emailOnNew = 1;
$emailOnComment = 1;
$emailOnStatusChange = 1;
$emailOnAssignment = 1;

if ($sql->getRows() > 0) {
    $emailOnNew = $sql->getValue('email_on_new');
    $emailOnComment = $sql->getValue('email_on_comment');
    $emailOnStatusChange = $sql->getValue('email_on_status_change');
    $emailOnAssignment = $sql->getValue('email_on_assignment');
}

// Formular
$content = '';

$formElements = [];

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_new') . '</label>';
$n['field'] = '<input type="checkbox" name="email_on_new" value="1" ' . ($emailOnNew ? 'checked' : '') . ' /> ' . $package->i18n('issue_tracker_email_on_new_desc');
$formElements[] = $n;

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_comment') . '</label>';
$n['field'] = '<input type="checkbox" name="email_on_comment" value="1" ' . ($emailOnComment ? 'checked' : '') . ' /> ' . $package->i18n('issue_tracker_email_on_comment_desc');
$formElements[] = $n;

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_status_change') . '</label>';
$n['field'] = '<input type="checkbox" name="email_on_status_change" value="1" ' . ($emailOnStatusChange ? 'checked' : '') . ' /> ' . $package->i18n('issue_tracker_email_on_status_change_desc');
$formElements[] = $n;

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_assignment') . '</label>';
$n['field'] = '<input type="checkbox" name="email_on_assignment" value="1" ' . ($emailOnAssignment ? 'checked' : '') . ' /> ' . $package->i18n('issue_tracker_email_on_assignment_desc');
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// Submit-Button
$formElements = [];
$n = [];
$n['field'] = '<button type="submit" name="save_preferences" value="1" class="btn btn-primary">' . $package->i18n('issue_tracker_save_preferences') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

// Panel
$fragment = new rex_fragment();
$fragment->setVar('title', $package->i18n('issue_tracker_email_notifications'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo '<form method="post">' . $content . '</form>';
