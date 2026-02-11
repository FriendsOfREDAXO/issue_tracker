<?php

/**
 * Benutzereinstellungen für E-Mail-Benachrichtigungen
 * 
 * @package issue_tracker
 */

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

// Flag für erfolgreiche E-Mail-Aktualisierung
$emailUpdated = false;

// E-Mail aktualisieren
if (rex_post('update_email', 'boolean')) {
    $newEmail = rex_post('user_email', 'string', '');
    
    if (empty($newEmail)) {
        echo rex_view::error($package->i18n('issue_tracker_email_invalid'));
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        echo rex_view::error($package->i18n('issue_tracker_email_invalid'));
    } else {
        try {
            $sql = rex_sql::factory();
            $sql->setQuery('UPDATE ' . rex::getTable('user') . ' SET email = ? WHERE id = ?', [$newEmail, $currentUser->getId()]);
            echo rex_view::success($package->i18n('issue_tracker_email_updated'));
            $emailUpdated = true;
        } catch (rex_sql_exception $e) {
            echo rex_view::error($package->i18n('issue_tracker_email_update_error') . ': ' . $e->getMessage());
        }
    }
}

// Prüfung ob E-Mail-Adresse gesetzt ist
$userEmail = $currentUser->getValue('email');
if (empty($userEmail) && !$emailUpdated) {
    echo rex_view::warning($package->i18n('issue_tracker_no_email_warning'));
    
    // Formular für E-Mail-Eingabe
    $formElements = [];
    $n = [];
    $n['label'] = '<label>' . $package->i18n('issue_tracker_email_address') . '</label>';
    $n['field'] = '<input type="email" name="user_email" class="form-control" placeholder="' . $package->i18n('issue_tracker_email_address_placeholder') . '" required />';
    $formElements[] = $n;
    
    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content = $fragment->parse('core/form/form.php');
    
    // Hinweis hinzufügen
    $content .= '<div class="alert alert-info"><small>' . $package->i18n('issue_tracker_email_hint') . '</small></div>';
    
    $formElements = [];
    $n = [];
    $n['field'] = '<button type="submit" name="update_email" value="1" class="btn btn-primary">' . $package->i18n('issue_tracker_update_email') . '</button>';
    $formElements[] = $n;
    
    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');
    
    $fragment = new rex_fragment();
    $fragment->setVar('body', '<form method="post">' . $content . $buttons . '</form>', false);
    echo $fragment->parse('core/page/section.php');
} else if (!empty($userEmail)) {
    // Info-Block mit aktueller E-Mail-Adresse anzeigen
    $profileUrl = rex_url::backendPage('users/users', ['user_id' => $currentUser->getId(), 'function' => 'edit']);
    $infoContent = '<div class="form-group">';
    $infoContent .= '<label>' . $package->i18n('issue_tracker_email_current') . '</label>';
    $infoContent .= '<div class="well well-sm" style="margin-bottom: 0;">';
    $infoContent .= '<strong>' . rex_escape($userEmail) . '</strong>';
    $infoContent .= ' <a href="' . $profileUrl . '" class="btn btn-xs btn-default">' . $package->i18n('issue_tracker_email_change_in_profile') . '</a>';
    $infoContent .= '</div>';
    $infoContent .= '</div>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('body', $infoContent, false);
    echo $fragment->parse('core/page/section.php');
}

// Formular speichern
if (rex_post('save_preferences', 'boolean')) {
    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('issue_tracker_notifications'));
    
    $sql->setValue('user_id', $currentUser->getId());
    $sql->setValue('email_on_new', rex_post('email_on_new', 'int', 0));
    $sql->setValue('email_on_comment', rex_post('email_on_comment', 'int', 0));
    $sql->setValue('email_on_status_change', rex_post('email_on_status_change', 'int', 0));
    $sql->setValue('email_on_assignment', rex_post('email_on_assignment', 'int', 0));
    $sql->setValue('email_on_message', rex_post('email_on_message', 'int', 0));
    $sql->setValue('email_message_full_text', rex_post('email_message_full_text', 'int', 0));
    
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
$emailOnMessage = 1;
$emailMessageFullText = 0;

if ($sql->getRows() > 0) {
    $emailOnNew = $sql->getValue('email_on_new');
    $emailOnComment = $sql->getValue('email_on_comment');
    $emailOnStatusChange = $sql->getValue('email_on_status_change');
    $emailOnAssignment = $sql->getValue('email_on_assignment');
    $emailOnMessage = $sql->getValue('email_on_message') ?? 1;
    $emailMessageFullText = $sql->getValue('email_message_full_text') ?? 0;
}

// Formular
$content = '';

// Info-Hinweis zum Beteiligungsmodell
$content .= '<div class="alert alert-info" style="margin-bottom: 20px;">';
$content .= '<i class="rex-icon fa-info-circle"></i> ';
$content .= '<strong>' . $package->i18n('issue_tracker_notification_info_title') . '</strong><br>';
$content .= $package->i18n('issue_tracker_notification_info_text');
$content .= '</div>';

$formElements = [];

$selectYesNo = static function (string $name, int $currentValue) use ($package): string {
    $yes = $package->i18n('issue_tracker_yes');
    $no = $package->i18n('issue_tracker_no');
    return '<select name="' . $name . '" class="form-control selectpicker" data-width="200px" data-style="btn-default">' 
        . '<option value="1"' . ($currentValue ? ' selected' : '') . ' data-content="<span class=\'label label-success\'><i class=\'rex-icon fa-check\'></i> ' . $yes . '</span>">' . $yes . '</option>'
        . '<option value="0"' . (!$currentValue ? ' selected' : '') . ' data-content="<span class=\'label label-default\'><i class=\'rex-icon fa-times\'></i> ' . $no . '</span>">' . $no . '</option>'
        . '</select>';
};

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_new') . '</label>';
$n['field'] = $selectYesNo('email_on_new', (int) $emailOnNew) . '<p class="help-block">' . $package->i18n('issue_tracker_email_on_new_desc') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_comment') . '</label>';
$n['field'] = $selectYesNo('email_on_comment', (int) $emailOnComment) . '<p class="help-block">' . $package->i18n('issue_tracker_email_on_comment_desc') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_status_change') . '</label>';
$n['field'] = $selectYesNo('email_on_status_change', (int) $emailOnStatusChange) . '<p class="help-block">' . $package->i18n('issue_tracker_email_on_status_change_desc') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_assignment') . '</label>';
$n['field'] = $selectYesNo('email_on_assignment', (int) $emailOnAssignment) . '<p class="help-block">' . $package->i18n('issue_tracker_email_on_assignment_desc') . '</p>';
$formElements[] = $n;

// Trennlinie für Nachrichten-Bereich
$n = [];
$n['label'] = '<label><strong>' . $package->i18n('issue_tracker_messages') . '</strong></label>';
$n['field'] = '<hr style="margin: 5px 0;">';
$formElements[] = $n;

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_on_message') . '</label>';
$n['field'] = $selectYesNo('email_on_message', (int) $emailOnMessage) . '<p class="help-block">' . $package->i18n('issue_tracker_email_on_message_desc') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label>' . $package->i18n('issue_tracker_email_message_full_text') . '</label>';
$n['field'] = $selectYesNo('email_message_full_text', (int) $emailMessageFullText) . '<p class="help-block">' . $package->i18n('issue_tracker_email_message_full_text_desc') . '</p>';
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
