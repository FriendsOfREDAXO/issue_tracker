<?php
/**
 * Projektformular Fragment – einfache Sprache, Hinweise unter jedem Feld, seltene Optionen unter „Erweitert“
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');

$project = $this->getVar('project');
$isNew = $this->getVar('isNew', true);
$allUsers = $this->getVar('allUsers', []);
$currentMembers = $this->getVar('currentMembers', []);
$formErrors = $this->getVar('formErrors', []);

$currentUser = rex::getUser();
$t = static fn (string $key): string => rex_i18n::rawMsg($key);

$projectStatuses = [
    'active' => $t('issue_tracker_project_status_active'),
    'completed' => $t('issue_tracker_project_status_completed'),
    'archived' => $t('issue_tracker_project_status_archived'),
];

$colors = [
    '#007bff' => $t('issue_tracker_color_blue'),
    '#28a745' => $t('issue_tracker_color_green'),
    '#dc3545' => $t('issue_tracker_color_red'),
    '#ffc107' => $t('issue_tracker_color_yellow'),
    '#17a2b8' => $t('issue_tracker_color_cyan'),
    '#6f42c1' => $t('issue_tracker_color_purple'),
    '#fd7e14' => $t('issue_tracker_color_orange'),
    '#20c997' => $t('issue_tracker_color_turquoise'),
    '#6c757d' => $t('issue_tracker_color_gray'),
    '#e83e8c' => $t('issue_tracker_color_pink'),
];
$currentColor = (string) $project->getColor();
if ('' === $currentColor) {
    $currentColor = '#007bff';
}
if (!isset($colors[$currentColor]) && preg_match('/^#[0-9a-f]{3,8}$/i', $currentColor)) {
    // Eigene Farbe aus älteren Versionen erhalten
    $colors = [$currentColor => $t('issue_tracker_ui_color_current')] + $colors;
}

$status = $project->getStatus() ?: 'active';
$dueDate = $project->getDueDate();
// „Erweitert“ öffnet sich, wenn dort etwas vom Standard abweicht
$advancedOpen = 'active' !== $status || null !== $dueDate || $project->getIsPrivate();

$title = $isNew ? $t('issue_tracker_project_create') : $t('issue_tracker_project_edit');
$heading = !$isNew && '' !== trim($project->getName()) ? $project->getName() : $title;

// Felder
$nameField = UiHelper::field(
    $t('issue_tracker_ui_f_project_name'),
    '<input type="text" class="form-control" id="project-name" name="name" value="' . rex_escape($project->getName()) . '" required maxlength="255">',
    $t('issue_tracker_ui_f_project_name_help'),
    'project-name',
    true,
    $formErrors['project-name'] ?? ''
);
$descField = UiHelper::field(
    $t('issue_tracker_ui_f_project_desc'),
    '<textarea class="form-control" id="project-description" name="description" rows="4">' . rex_escape($project->getDescription()) . '</textarea>',
    $t('issue_tracker_ui_f_project_desc_help'),
    'project-description',
    false,
    '',
    true
);
$swatches = '<div class="it-swatches" role="radiogroup" id="project-color" aria-label="' . rex_escape($t('issue_tracker_ui_f_project_color')) . '">';
foreach ($colors as $colorCode => $colorName) {
    $swatches .= '<label class="it-swatch" title="' . rex_escape($colorName) . '"><input type="radio" name="color" value="' . rex_escape($colorCode) . '" aria-label="' . rex_escape($colorName) . '"'
        . ($currentColor === $colorCode ? ' checked' : '') . '><span style="' . UiHelper::colorVar($colorCode) . '"></span></label>';
}
$swatches .= '</div>';
$colorField = UiHelper::field($t('issue_tracker_ui_f_project_color'), $swatches, $t('issue_tracker_ui_f_project_color_help'), 'project-color');

$memberOptions = '';
foreach ($allUsers as $userId => $userName) {
    if ($userId == $currentUser->getId() && $isNew) {
        continue; // Ersteller wird automatisch hinzugefügt
    }
    $memberOptions .= '<option value="' . (int) $userId . '"' . (in_array($userId, $currentMembers) ? ' selected' : '') . '>' . rex_escape($userName) . '</option>';
}
$membersField = UiHelper::field(
    $t('issue_tracker_ui_f_project_members'),
    '<select class="form-control selectpicker" id="project-members" name="members[]" multiple="multiple" data-live-search="true" data-actions-box="true" data-selected-text-format="count > 3" data-width="100%" title="'
        . rex_escape($t('issue_tracker_ui_f_project_members_none')) . '">' . $memberOptions . '</select>',
    $t($isNew ? 'issue_tracker_ui_f_project_members_help_new' : 'issue_tracker_ui_f_project_members_help'),
    'project-members',
    false,
    '',
    true
);

$statusField = UiHelper::field(
    $t('issue_tracker_ui_f_project_status'),
    UiHelper::segmentedRadio('status', $projectStatuses, $status, 'project-status', $t('issue_tracker_ui_f_project_status')),
    $t('issue_tracker_ui_f_project_status_help'),
    'project-status'
);
$dueField = UiHelper::field(
    $t('issue_tracker_ui_f_project_due'),
    '<input type="date" class="form-control" id="project-due-date" name="due_date" value="' . ($dueDate ? $dueDate->format('Y-m-d') : '') . '">',
    $t('issue_tracker_ui_f_project_due_help'),
    'project-due-date'
);
$privateField = UiHelper::field(
    $t('issue_tracker_ui_f_project_private'),
    UiHelper::switch('is_private', $project->getIsPrivate(), 'project-private'),
    $t('issue_tracker_ui_f_project_private_help'),
    'project-private'
);
?>

<div class="issue-tracker-project-form">
    <form method="post" action="<?= rex_url::currentBackendPage() ?>" novalidate>
        <input type="hidden" name="save" value="1" />
        <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>
        <?php if (!$isNew): ?>
        <input type="hidden" name="project_id" value="<?= $project->getId() ?>" />
        <?php endif; ?>

        <div class="it-settings-head">
            <span class="it-icon it-icon--lg it-icon--color" style="<?= UiHelper::colorVar($currentColor) ?>" aria-hidden="true"><i class="rex-icon fa-folder"></i></span>
            <div>
                <h2><?= rex_escape($heading) ?></h2>
                <p><?= rex_escape($t($isNew ? 'issue_tracker_ui_project_form_intro' : 'issue_tracker_ui_project_form_intro_edit')) ?></p>
            </div>
        </div>

        <?= UiHelper::errorSummary($formErrors) ?>

        <div class="it-group">
            <?= $nameField ?>
            <?= $descField ?>
            <?= $colorField ?>
            <?= $membersField ?>
        </div>

        <?= UiHelper::advanced('<div class="it-group">' . $statusField . $dueField . $privateField . '</div>', $advancedOpen, $t('issue_tracker_ui_f_project_advanced_hint')) ?>

        <div class="it-settings-actions">
            <a href="<?= $isNew ? rex_url::backendPage('issue_tracker/issues/list') : rex_url::backendPage('issue_tracker/issues/list', ['filter_status' => '', 'filter_project' => $project->getId()]) ?>" class="btn btn-default"><?= rex_escape($t('issue_tracker_ui_cancel')) ?></a>
            <button type="submit" class="btn btn-save"><?= rex_escape($t($isNew ? 'issue_tracker_ui_btn_create_project' : 'issue_tracker_ui_btn_save')) ?></button>
        </div>
    </form>
</div>
