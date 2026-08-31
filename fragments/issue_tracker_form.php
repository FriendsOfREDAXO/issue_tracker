<?php
/**
 * Issue Formular Fragment
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\Attachment;
use FriendsOfREDAXO\IssueTracker\PermissionService;

$package = rex_addon::get('issue_tracker');

$issue = $this->getVar('issue');
$isNew = $this->getVar('isNew', true);
$categories = $this->getVar('categories', []);
$statuses = $this->getVar('statuses', []);
$priorities = $this->getVar('priorities', []);
$allTags = $this->getVar('allTags', []);
$users = $this->getVar('users', []);
$addons = $this->getVar('addons', []);
$projects = $this->getVar('projects', []);
$mentionUsers = $this->getVar('mentionUsers', []);

// Sicherstellen, dass $issue ein Objekt ist
if (!$issue) {
    throw new \RuntimeException('Issue object is required');
}

$issueTags = $issue->getTags();
$issueTagIds = array_map(fn($tag) => $tag->getId(), $issueTags);

// Status darf beim Neuanlegen nur von Admin/Issue-Manager frei gewählt werden;
// normale Melder starten immer mit "Offen" (Backend-Default), das Feld wird dann ausgeblendet.
$showStatusField = !$isNew || PermissionService::isAdminOrManager();

$hasDomains = rex_addon::exists('yrewrite') && rex_addon::get('yrewrite')->isAvailable();
$hasYformTables = rex_addon::exists('yform') && rex_addon::get('yform')->isAvailable();
$advancedHasValues = ($issue->getVersion() ?? '') !== '' || !empty($issue->getDomainIds()) || !empty($issue->getYformTables());
?>

<div class="issue-tracker-form"<?= !empty($mentionUsers) ? ' data-mention-users="' . rex_escape(json_encode(array_values($mentionUsers))) . '"' : '' ?>>
    <form method="post" action="<?= rex_url::currentBackendPage() ?>" enctype="multipart/form-data">
        <input type="hidden" name="save" value="1" />
        <?php if (!$isNew): ?>
        <input type="hidden" name="issue_id" value="<?= $issue->getId() ?>" />
        <input type="hidden" name="old_assigned_user_id" value="<?= $issue->getAssignedUserId() ?? 0 ?>" />
        <?php endif; ?>

        <!-- Abschnitt 1: Worum geht es? -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <?= $isNew ? $package->i18n('issue_tracker_create_new') : $package->i18n('issue_tracker_edit_issue') ?>
                    <?php if ($isNew): ?>
                    <button type="button" class="btn btn-info btn-xs pull-right" data-toggle="modal" data-target="#issue-help-modal" style="margin-top: -3px;">
                        <i class="rex-icon fa-question-circle"></i> <?= $package->i18n('issue_tracker_help') ?>
                    </button>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="panel-body">
                <!-- Titel -->
                <div class="form-group">
                    <label for="issue-title"><?= $package->i18n('issue_tracker_title') ?> *</label>
                    <input type="text" class="form-control" id="issue-title" name="title"
                           value="<?= rex_escape($issue->getTitle()) ?>" required maxlength="255">
                </div>

                <!-- Beschreibung -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="issue-description"><?= $package->i18n('issue_tracker_description') ?> *</label>
                    <textarea class="form-control" id="issue-description" name="description"
                              rows="8"><?= rex_escape($issue->getDescription()) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Abschnitt 2: Einordnung -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-tags"></i> <?= $package->i18n('issue_tracker_form_section_classification') ?></h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <!-- Kategorie -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-category"><?= $package->i18n('issue_tracker_category') ?> *</label>
                            <select class="form-control selectpicker" id="issue-category" name="category" data-live-search="true" required>
                                <option value=""><?= $package->i18n('issue_tracker_please_select') ?></option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= rex_escape($category) ?>" <?= $issue->getCategory() === $category ? 'selected' : '' ?>>
                                    <?= rex_escape($category) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Priorität -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-priority"><?= $package->i18n('issue_tracker_priority') ?></label>
                            <select class="form-control selectpicker" id="issue-priority" name="priority">
                                <?php foreach ($priorities as $priorityKey => $priorityLabel): ?>
                                <option value="<?= rex_escape($priorityKey) ?>" <?= $issue->getPriority() === $priorityKey ? 'selected' : '' ?>>
                                    <?= $package->i18n('issue_tracker_priority_' . $priorityKey) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Projekt -->
                <?php if (!empty($projects)): ?>
                <div class="form-group">
                    <label for="issue-project"><?= $package->i18n('issue_tracker_project') ?></label>
                    <select class="form-control selectpicker" id="issue-project" name="project_id" data-live-search="true">
                        <option value=""><?= $package->i18n('issue_tracker_no_project') ?></option>
                        <?php foreach ($projects as $projectId => $projectName): ?>
                        <option value="<?= $projectId ?>" <?= $issue->getProjectId() === $projectId ? 'selected' : '' ?>>
                            <?= rex_escape($projectName) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help-block"><?= $package->i18n('issue_tracker_project_help') ?></p>
                </div>
                <?php endif; ?>

                <!-- Tags -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="issue-tags"><?= $package->i18n('issue_tracker_tags') ?></label>
                    <select class="form-control selectpicker" id="issue-tags" name="tags[]" multiple data-live-search="true" title="<?= $package->i18n('issue_tracker_please_select') ?>">
                        <?php foreach ($allTags as $tag): ?>
                        <option value="<?= $tag->getId() ?>"
                                data-content="<span class='label' style='background-color: <?= rex_escape($tag->getColor()) ?>'><?= rex_escape($tag->getName()) ?></span>"
                                <?= in_array($tag->getId(), $issueTagIds, true) ? 'selected' : '' ?>>
                            <?= rex_escape($tag->getName()) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Abschnitt 3: Zuständigkeit -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-user"></i> <?= $package->i18n('issue_tracker_form_section_assignment') ?></h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <!-- Zugewiesener User -->
                    <div class="col-sm-<?= $showStatusField ? 6 : 12 ?>">
                        <div class="form-group">
                            <label for="issue-assigned-user"><?= $package->i18n('issue_tracker_assigned_user') ?> <span class="text-danger">*</span></label>
                            <select class="form-control selectpicker" id="issue-assigned-user" name="assigned_user_id" data-live-search="true" required>
                                <option value=""><?= $package->i18n('issue_tracker_please_select') ?></option>
                                <option value="all" <?= $issue->getAssignedUserId() === null ? 'selected' : '' ?>>
                                    <?= $package->i18n('issue_tracker_assigned_all_users') ?>
                                </option>
                                <?php foreach ($users as $userId => $userName): ?>
                                <option value="<?= $userId ?>" <?= $issue->getAssignedUserId() === $userId ? 'selected' : '' ?>>
                                    <?= rex_escape($userName) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Status (bei Neuanlage nur für Admin/Issue-Manager wählbar, sonst immer "Offen") -->
                    <?php if ($showStatusField): ?>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-status"><?= $package->i18n('issue_tracker_status') ?></label>
                            <select class="form-control selectpicker" id="issue-status" name="status">
                                <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                                <option value="<?= rex_escape($statusKey) ?>" <?= $issue->getStatus() === $statusKey ? 'selected' : '' ?>>
                                    <?= $package->i18n('issue_tracker_status_' . $statusKey) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <!-- Zugewiesenes AddOn -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-assigned-addon"><?= $package->i18n('issue_tracker_assigned_addon') ?></label>
                            <select class="form-control selectpicker" id="issue-assigned-addon" name="assigned_addon" data-live-search="true">
                                <option value=""><?= $package->i18n('issue_tracker_not_assigned') ?></option>
                                <?php foreach ($addons as $addonKey => $addonName): ?>
                                <option value="<?= rex_escape($addonKey) ?>" <?= $issue->getAssignedAddon() === $addonKey ? 'selected' : '' ?>>
                                    <?= rex_escape($addonName) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fälligkeit -->
                    <div class="col-sm-6">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="issue-due-date"><?= $package->i18n('issue_tracker_due_date') ?></label>
                            <input type="datetime-local" class="form-control" id="issue-due-date" name="due_date"
                                   value="<?= $issue->getDueDate() ? $issue->getDueDate()->format('Y-m-d\TH:i') : '' ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Abschnitt 4: Erweiterte Optionen (eingeklappt, außer bereits befüllt) -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <a href="#issue-advanced-collapse" data-toggle="collapse" class="issue-tracker-form-advanced-toggle<?= $advancedHasValues ? '' : ' collapsed' ?>" style="color: inherit; text-decoration: none;">
                        <i class="rex-icon fa-chevron-down"></i><i class="rex-icon fa-chevron-up"></i>
                        <?= $package->i18n('issue_tracker_form_section_advanced') ?>
                        <small class="text-muted">(<?= $package->i18n('issue_tracker_form_section_advanced_hint') ?>)</small>
                    </a>
                </h3>
            </div>
            <div id="issue-advanced-collapse" class="panel-collapse collapse<?= $advancedHasValues ? ' in' : '' ?>">
                <div class="panel-body">
                    <div class="row">
                        <!-- Version (meist die aktuelle Installation, Melder kennen sie i.d.R. nicht) -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="issue-version"><?= $package->i18n('issue_tracker_version') ?></label>
                                <input type="text" class="form-control" id="issue-version" name="version"
                                       value="<?= rex_escape($issue->getVersion() ?? '') ?>" maxlength="50">
                            </div>
                        </div>

                        <!-- YRewrite Domains (optional, multiple) -->
                        <?php if ($hasDomains): ?>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="issue-domains"><?= $package->i18n('issue_tracker_domain') ?></label>
                                <select class="form-control selectpicker" id="issue-domains" name="domain_ids[]" multiple="multiple" data-live-search="true" data-actions-box="true" data-selected-text-format="count > 2" title="<?= $package->i18n('issue_tracker_please_select') ?>">
                                    <?php
                                    $user = rex::getUser();
                                    $selectedDomainIds = $issue->getDomainIds();
                                    foreach (rex_yrewrite::getDomains() as $domainName => $domain):
                                        $domainId = method_exists($domain, 'getId') ? (int) $domain->getId() : null;
                                        if ($domainId === null) continue;

                                        // Rechteprüfung: Admin, Issue-Manager oder User hat Domain-Berechtigung
                                        $hasDomainAccess = false;
                                        if ($user) {
                                            if ($user->isAdmin() || $user->hasPerm('issue_tracker[issue_manager]')) {
                                                $hasDomainAccess = true;
                                            } else {
                                                $complexPerm = $user->getComplexPerm('structure_mountpoints');
                                                $hasDomainAccess = $complexPerm && $complexPerm->hasPerm($domain->getMountId());
                                            }
                                        }
                                        if (!$hasDomainAccess) continue;
                                    ?>
                                    <option value="<?= $domainId ?>" <?= in_array($domainId, $selectedDomainIds, true) ? 'selected' : '' ?>>
                                        <?= rex_escape($domainName) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- YForm Tabellen (entsprechend User-Rechte, multiple) -->
                        <?php if ($hasYformTables): ?>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="issue-yform-tables"><?= $package->i18n('issue_tracker_yform_table') ?></label>
                                <select class="form-control selectpicker" id="issue-yform-tables" name="yform_tables[]" multiple="multiple" data-live-search="true" data-actions-box="true" data-selected-text-format="count > 2" title="<?= $package->i18n('issue_tracker_please_select') ?>">
                                    <?php
                                    $user = rex::getUser();
                                    $selectedYformTables = $issue->getYformTables();
                                    foreach (rex_yform_manager_table::getAll() as $yTable):
                                        // Rechteprüfung: Admin oder User hat Tabellen-Berechtigung
                                        $tableName = $yTable->getTableName();
                                        $hasAccess = $user->isAdmin() || $user->getComplexPerm('yform_manager_table_edit')->hasPerm($tableName);
                                        if (!$hasAccess) continue;
                                    ?>
                                    <option value="<?= rex_escape($tableName) ?>" <?= in_array($tableName, $selectedYformTables, true) ? 'selected' : '' ?>>
                                        <?= rex_escape(rex_i18n::translate($yTable->getName())) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Abschnitt 5: Anhänge -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-paperclip"></i> <?= $package->i18n('issue_tracker_form_section_attachments') ?></h3>
            </div>
            <div class="panel-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="issue-attachments"><?= $package->i18n('issue_tracker_attachments') ?></label>
                    <input
                        type="file"
                        id="issue-attachments"
                        name="attachments[]"
                        multiple
                        accept="image/*,video/*,.pdf,.doc,.docx,.odt,.xls,.xlsx,.ods,.csv,.ppt,.pptx,.odp,.txt,.md,.zip,.rar"
                        class="form-control"
                    >
                    <p class="help-block"><?= $package->i18n('issue_tracker_attachments_help') ?></p>
                </div>
            </div>
        </div>

        <!-- Privat (nur für Admins) -->
        <?php if (PermissionService::canCreatePrivateIssues()): ?>
        <div class="checkbox">
            <label>
                <input type="checkbox" name="is_private" value="1" <?= $issue->getIsPrivate() ? 'checked' : '' ?>>
                <?= $package->i18n('issue_tracker_private_issue') ?>
                <span class="help-block" style="margin: 0;"><?= $package->i18n('issue_tracker_private_issue_help') ?></span>
            </label>
        </div>
        <?php endif; ?>

        <!-- Buttons -->
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
            </button>
            <a href="<?= rex_url::backendPage('issue_tracker/issues') ?>" class="btn btn-default">
                <i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_cancel') ?>
            </a>
        </div>
    </form>

    <?php if (!$isNew): ?>
    <!-- Bestehende Dateianhänge -->
    <?php
    $attachments = Attachment::getByIssue($issue->getId());
    if (!empty($attachments)):
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><?= $package->i18n('issue_tracker_attachments') ?></h3>
        </div>
        <div class="panel-body">
            <div class="row" data-lightbox-group="issue-<?= $issue->getId() ?>-edit">
                <?php foreach ($attachments as $attachment): ?>
                <div class="col-sm-6 col-md-4" style="margin-bottom: 15px;">
                    <?= $attachment->renderCard([
                        'maxHeight' => 150,
                        'deleteUrl' => rex_url::currentBackendPage(['issue_id' => $issue->getId(), 'delete_attachment' => $attachment->getId()]),
                    ]) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?> <!-- Ende: if (!empty($attachments)) -->
    <?php endif; ?> <!-- Ende: if (!$isNew) für Attachments -->

    <!-- Kommentare (nur bei bestehendem Issue anzeigen) -->
    <?php if (!$isNew): ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><?= $package->i18n('issue_tracker_comments') ?></h3>
        </div>
        <div class="panel-body">
            <?php
            $comments = $issue->getComments();
            if (empty($comments)):
            ?>
                <p class="text-muted"><?= $package->i18n('issue_tracker_no_comments') ?></p>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <?php $creator = $comment->getCreator(); ?>
                    <div class="issue-tracker-comment" style="border-left: 3px solid #ddd; padding-left: 15px; margin-bottom: 20px;">
                        <div>
                            <strong><?= $creator ? rex_escape($creator->getValue('name')) : 'Unbekannt' ?></strong>
                            <small class="text-muted">
                                <?= $comment->getCreatedAt() ? $comment->getCreatedAt()->format('d.m.Y H:i') : '' ?>
                            </small>
                            <?php if ($comment->isInternal()): ?>
                                <span class="label label-warning"><?= $package->i18n('issue_tracker_internal') ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 10px;">
                            <?= rex_markdown::factory()->parse($comment->getComment()) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Neuer Kommentar -->
            <form method="post" action="<?= rex_url::currentBackendPage(['issue_id' => $issue->getId()]) ?>" style="margin-top: 20px;">
                <input type="hidden" name="add_comment" value="1" />
                <input type="hidden" name="issue_id" value="<?= $issue->getId() ?>" />

                <div class="form-group">
                    <label for="comment-text"><?= $package->i18n('issue_tracker_add_comment') ?></label>
                    <textarea class="form-control" id="comment-text" name="comment" rows="4"></textarea>
                </div>

                <?php if (PermissionService::canCreateInternalComments()): ?>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="is_internal" value="1">
                        <?= $package->i18n('issue_tracker_internal_comment') ?>
                    </label>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">
                    <i class="rex-icon fa-comment"></i> <?= $package->i18n('issue_tracker_add_comment') ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Hilfe-Modal -->
<div class="modal fade" id="issue-help-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">
                    <i class="rex-icon fa-question-circle"></i> <?= $package->i18n('issue_tracker_help_title') ?>
                </h4>
            </div>
            <div class="modal-body">
                <h4><?= $package->i18n('issue_tracker_help_what_is') ?></h4>
                <p><?= $package->i18n('issue_tracker_help_what_is_text') ?></p>

                <h4><?= $package->i18n('issue_tracker_help_one_topic') ?></h4>
                <p><?= $package->i18n('issue_tracker_help_one_topic_text') ?></p>

                <div class="alert alert-success">
                    <strong><i class="rex-icon fa-check"></i> <?= $package->i18n('issue_tracker_help_good_example') ?></strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li><?= rex_i18n::rawMsg('issue_tracker_help_good_1') ?></li>
                        <li><?= rex_i18n::rawMsg('issue_tracker_help_good_2') ?></li>
                        <li><?= rex_i18n::rawMsg('issue_tracker_help_good_3') ?></li>
                    </ul>
                </div>

                <div class="alert alert-danger">
                    <strong><i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_help_bad_example') ?></strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li><?= rex_i18n::rawMsg('issue_tracker_help_bad_1') ?></li>
                        <li><?= rex_i18n::rawMsg('issue_tracker_help_bad_2') ?></li>
                    </ul>
                </div>

                <h4><?= $package->i18n('issue_tracker_help_important_info') ?></h4>
                <ul>
                    <li><?= rex_i18n::rawMsg('issue_tracker_help_tip_1') ?></li>
                    <li><?= rex_i18n::rawMsg('issue_tracker_help_tip_2') ?></li>
                    <li><?= rex_i18n::rawMsg('issue_tracker_help_tip_3') ?></li>
                    <li><?= rex_i18n::rawMsg('issue_tracker_help_tip_4') ?></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= $package->i18n('issue_tracker_close') ?></button>
            </div>
        </div>
    </div>
</div>
