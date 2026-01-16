<?php
/**
 * Projektformular Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$project = $this->getVar('project');
$isNew = $this->getVar('isNew', true);
$allUsers = $this->getVar('allUsers', []);
$currentMembers = $this->getVar('currentMembers', []);

$currentUser = rex::getUser();

$projectStatuses = [
    'active' => $package->i18n('issue_tracker_project_status_active'),
    'completed' => $package->i18n('issue_tracker_project_status_completed'),
    'archived' => $package->i18n('issue_tracker_project_status_archived'),
];

$colors = [
    '#007bff' => $package->i18n('issue_tracker_color_blue'),
    '#28a745' => $package->i18n('issue_tracker_color_green'),
    '#dc3545' => $package->i18n('issue_tracker_color_red'),
    '#ffc107' => $package->i18n('issue_tracker_color_yellow'),
    '#17a2b8' => $package->i18n('issue_tracker_color_cyan'),
    '#6f42c1' => $package->i18n('issue_tracker_color_purple'),
    '#fd7e14' => $package->i18n('issue_tracker_color_orange'),
    '#20c997' => $package->i18n('issue_tracker_color_turquoise'),
    '#6c757d' => $package->i18n('issue_tracker_color_gray'),
    '#e83e8c' => $package->i18n('issue_tracker_color_pink'),
];
?>

<div class="issue-tracker-project-form">
    <form method="post" action="<?= rex_url::currentBackendPage() ?>">
        <input type="hidden" name="save" value="1" />
        <?php if (!$isNew): ?>
        <input type="hidden" name="project_id" value="<?= $project->getId() ?>" />
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <?= $isNew ? $package->i18n('issue_tracker_project_create') : $package->i18n('issue_tracker_project_edit') ?>
                </h3>
            </div>
            <div class="panel-body">
                <!-- Name -->
                <div class="form-group">
                    <label for="project-name"><?= $package->i18n('issue_tracker_project_name') ?> *</label>
                    <input type="text" class="form-control" id="project-name" name="name" 
                           value="<?= rex_escape($project->getName()) ?>" required maxlength="255">
                </div>

                <!-- Beschreibung -->
                <div class="form-group">
                    <label for="project-description"><?= $package->i18n('issue_tracker_description') ?></label>
                    <textarea class="form-control" id="project-description" name="description" 
                              rows="5"><?= rex_escape($project->getDescription()) ?></textarea>
                    <p class="help-block"><?= $package->i18n('issue_tracker_markdown_supported') ?></p>
                </div>

                <div class="row">
                    <!-- Status -->
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label for="project-status"><?= $package->i18n('issue_tracker_status') ?></label>
                            <select class="form-control selectpicker" id="project-status" name="status">
                                <?php foreach ($projectStatuses as $statusKey => $statusLabel): ?>
                                <option value="<?= rex_escape($statusKey) ?>" <?= $project->getStatus() === $statusKey ? 'selected' : '' ?>>
                                    <?= rex_escape($statusLabel) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Farbe -->
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label for="project-color"><?= $package->i18n('issue_tracker_project_color') ?></label>
                            <select class="form-control selectpicker" id="project-color" name="color">
                                <?php foreach ($colors as $colorCode => $colorName): ?>
                                <option value="<?= $colorCode ?>" 
                                        data-content="<span class='label' style='background-color: <?= $colorCode ?>'>&nbsp;&nbsp;&nbsp;</span> <?= $colorName ?>"
                                        <?= $project->getColor() === $colorCode ? 'selected' : '' ?>>
                                    <?= $colorName ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Due Date -->
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label for="project-due-date"><?= $package->i18n('issue_tracker_due_date') ?></label>
                            <input type="date" class="form-control" id="project-due-date" name="due_date" 
                                   value="<?= $project->getDueDate() ? $project->getDueDate()->format('Y-m-d') : '' ?>">
                        </div>
                    </div>
                </div>

                <!-- Mitglieder -->
                <div class="form-group">
                    <label for="project-members"><?= $package->i18n('issue_tracker_project_members') ?></label>
                    <select class="form-control selectpicker" id="project-members" name="members[]" multiple="multiple" data-live-search="true" 
                            data-actions-box="true" data-selected-text-format="count > 2" title="<?= $package->i18n('issue_tracker_please_select') ?>">
                        <?php foreach ($allUsers as $userId => $userName): 
                            if ($userId == $currentUser->getId() && $isNew) continue; // Ersteller wird automatisch hinzugefügt
                            $isSelected = in_array($userId, $currentMembers);
                        ?>
                        <option value="<?= $userId ?>" <?= $isSelected ? 'selected' : '' ?>><?= rex_escape($userName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help-block"><?= $package->i18n('issue_tracker_project_members_help') ?></p>
                </div>

                <!-- Privat -->
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="is_private" value="1" <?= $project->getIsPrivate() ? 'checked' : '' ?>>
                        <strong><?= $package->i18n('issue_tracker_project_private') ?></strong>
                        <span class="help-block" style="margin: 0;"><?= $package->i18n('issue_tracker_project_private_help') ?></span>
                    </label>
                </div>

                <!-- Buttons -->
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
                    </button>
                    <a href="<?= rex_url::backendPage('issue_tracker/projects/list') ?>" class="btn btn-default">
                        <i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_cancel') ?>
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

