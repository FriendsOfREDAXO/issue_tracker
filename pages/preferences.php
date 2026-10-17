<?php

/**
 * Benutzereinstellungen für E-Mail-Benachrichtigungen
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');
$currentUser = rex::getUser();

echo UiHelper::settingsStart('preferences');

// Flag für erfolgreiche E-Mail-Aktualisierung
$emailUpdated = false;
$emailErrors = [];

// E-Mail aktualisieren
if (rex_post('update_email', 'boolean')) {
    $newEmail = rex_post('user_email', 'string', '');

    if (empty($newEmail)) {
        $emailErrors['it-user-email'] = rex_i18n::rawMsg('issue_tracker_ui_error_email_required');
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $emailErrors['it-user-email'] = rex_i18n::rawMsg('issue_tracker_ui_error_email');
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

// Formular speichern
if (rex_post('save_preferences', 'boolean')) {
    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('issue_tracker_notifications'));

    $sql->setValue('user_id', $currentUser->getId());
    $sql->setValue('email_on_new', rex_post('email_on_new', 'int', 1));
    $sql->setValue('email_on_comment', rex_post('email_on_comment', 'int', 1));
    $sql->setValue('email_on_status_change', rex_post('email_on_status_change', 'int', 1));
    $sql->setValue('email_on_assignment', rex_post('email_on_assignment', 'int', 1));
    $sql->setValue('email_on_message', rex_post('email_on_message', 'int', 1));
    $sql->setValue('email_on_mention', rex_post('email_on_mention', 'int', 1));
    $sql->setValue('email_message_full_text', rex_post('email_message_full_text', 'int', 0));

    try {
        $sql->insertOrUpdate();
        echo rex_view::success($package->i18n('issue_tracker_preferences_saved'));
    } catch (rex_sql_exception $e) {
        echo rex_view::error($package->i18n('issue_tracker_preferences_error') . ': ' . $e->getMessage());
    }
}

// ---------- E-Mail-Adresse ----------
$userEmail = $currentUser->getValue('email');
if ($emailUpdated) {
    $userEmail = rex_post('user_email', 'string', '');
}

echo '<h3 class="it-group-title">' . $package->i18n('issue_tracker_ui_pref_address') . '</h3>';
if (empty($userEmail)) {
    echo UiHelper::errorSummary($emailErrors);
    if (!$emailErrors) {
        echo rex_view::warning(rex_escape(rex_i18n::rawMsg('issue_tracker_ui_pref_no_email')));
    }
    echo '<form method="post" novalidate><div class="it-group">'
        . UiHelper::field(
            rex_i18n::rawMsg('issue_tracker_email_address'),
            '<input type="email" id="it-user-email" name="user_email" class="form-control" value="' . rex_escape(rex_post('user_email', 'string', '')) . '" placeholder="' . $package->i18n('issue_tracker_email_address_placeholder') . '" required />'
            . '<button type="submit" name="update_email" value="1" class="btn btn-save">' . rex_escape(rex_i18n::rawMsg('issue_tracker_ui_btn_save')) . '</button>',
            rex_i18n::rawMsg('issue_tracker_ui_pref_address_help'),
            'it-user-email',
            true,
            $emailErrors['it-user-email'] ?? ''
        )
        . '</div></form>';
} else {
    $profileUrl = rex_url::backendPage('users/users', ['user_id' => $currentUser->getId(), 'function' => 'edit']);
    echo '<div class="it-group">'
        . UiHelper::field(
            rex_i18n::rawMsg('issue_tracker_ui_pref_address_current'),
            '<a href="' . $profileUrl . '" class="btn btn-default btn-sm">' . $package->i18n('issue_tracker_email_change_in_profile') . '</a>',
            $userEmail
        )
        . '</div>';
}

// ---------- Aktuelle Einstellungen laden ----------
$sql = rex_sql::factory();
$sql->setQuery('SELECT * FROM ' . rex::getTable('issue_tracker_notifications') . ' WHERE user_id = ?', [$currentUser->getId()]);

$values = [
    'email_on_new' => 1,
    'email_on_comment' => 1,
    'email_on_status_change' => 1,
    'email_on_assignment' => 1,
    'email_on_message' => 1,
    'email_on_mention' => 1,
    'email_message_full_text' => 0,
];
if ($sql->getRows() > 0) {
    foreach ($values as $key => $default) {
        $values[$key] = (int) ($sql->getValue($key) ?? $default);
    }
}

$switchRow = static function (string $key) use ($values): string {
    return UiHelper::field(
        rex_i18n::rawMsg('issue_tracker_ui_pref_' . $key),
        UiHelper::switch($key, (bool) $values[$key], 'it-pref-' . $key),
        rex_i18n::rawMsg('issue_tracker_ui_pref_' . $key . '_help'),
        'it-pref-' . $key
    );
};

// ---------- Formular ----------
?>
<form method="post">
    <h3 class="it-group-title"><?= rex_escape(rex_i18n::rawMsg('issue_tracker_ui_pref_issues')) ?></h3>
    <div class="it-group">
        <?= $switchRow('email_on_new') ?>
        <?= $switchRow('email_on_comment') ?>
        <?= $switchRow('email_on_status_change') ?>
        <?= $switchRow('email_on_assignment') ?>
        <?= $switchRow('email_on_mention') ?>
    </div>
    <p class="it-group-note"><?= rex_escape(rex_i18n::rawMsg('issue_tracker_ui_pref_info')) ?></p>

    <h3 class="it-group-title"><?= rex_escape(rex_i18n::rawMsg('issue_tracker_ui_pref_messages')) ?></h3>
    <div class="it-group">
        <?= $switchRow('email_on_message') ?>
        <?= $switchRow('email_message_full_text') ?>
    </div>

    <div class="it-settings-actions">
        <a href="<?= rex_url::currentBackendPage() ?>" class="btn btn-default"><?= rex_escape(rex_i18n::rawMsg('issue_tracker_ui_cancel')) ?></a>
        <button type="submit" name="save_preferences" value="1" class="btn btn-save"><?= rex_escape(rex_i18n::rawMsg('issue_tracker_ui_btn_save')) ?></button>
    </div>
</form>
<?= UiHelper::settingsEnd() ?>
