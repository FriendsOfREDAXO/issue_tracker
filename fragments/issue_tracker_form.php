<?php
/**
 * Issue Formular Fragment
 * 
 * @var rex_fragment $this
 */

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

// Sicherstellen, dass $issue ein Objekt ist
if (!$issue) {
    throw new \RuntimeException('Issue object is required');
}

$issueTags = $issue->getTags();
$issueTagIds = array_map(fn($tag) => $tag->getId(), $issueTags);
?>

<div class="issue-tracker-form">
    <form method="post" action="<?= rex_url::currentBackendPage() ?>" enctype="multipart/form-data">
        <input type="hidden" name="save" value="1" />
        <?php if (!$isNew): ?>
        <input type="hidden" name="issue_id" value="<?= $issue->getId() ?>" />
        <input type="hidden" name="old_assigned_user_id" value="<?= $issue->getAssignedUserId() ?? 0 ?>" />
        <?php endif; ?>

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
                <div class="form-group">
                    <label for="issue-description"><?= $package->i18n('issue_tracker_description') ?> *</label>
                    <textarea class="form-control" id="issue-description" name="description" 
                              rows="8"><?= rex_escape($issue->getDescription()) ?></textarea>
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

                    <!-- Status -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-status"><?= $package->i18n('issue_tracker_status') ?></label>
                            <select class="form-control selectpicker" id="issue-status" name="status">
                                <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                                <option value="<?= rex_escape($statusKey) ?>" <?= $issue->getStatus() === $statusKey ? 'selected' : '' ?>>
                                    <?= rex_escape($statusLabel) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Priorität -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-priority"><?= $package->i18n('issue_tracker_priority') ?></label>
                            <select class="form-control selectpicker" id="issue-priority" name="priority">
                                <?php foreach ($priorities as $priorityKey => $priorityLabel): ?>
                                <option value="<?= rex_escape($priorityKey) ?>" <?= $issue->getPriority() === $priorityKey ? 'selected' : '' ?>>
                                    <?= rex_escape($priorityLabel) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Version -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-version"><?= $package->i18n('issue_tracker_version') ?></label>
                            <input type="text" class="form-control" id="issue-version" name="version" 
                                   value="<?= rex_escape($issue->getVersion() ?? '') ?>" maxlength="50">
                        </div>
                    </div>

                    <!-- Fälligkeit -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-due-date"><?= $package->i18n('issue_tracker_due_date') ?></label>
                            <input type="datetime-local" class="form-control" id="issue-due-date" name="due_date" 
                                   value="<?= $issue->getDueDate() ? $issue->getDueDate()->format('Y-m-d\TH:i') : '' ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Zugewiesener User -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="issue-assigned-user"><?= $package->i18n('issue_tracker_assigned_user') ?></label>
                            <select class="form-control selectpicker" id="issue-assigned-user" name="assigned_user_id" data-live-search="true">
                                <option value=""><?= $package->i18n('issue_tracker_not_assigned') ?></option>
                                <?php foreach ($users as $userId => $userName): ?>
                                <option value="<?= $userId ?>" <?= $issue->getAssignedUserId() === $userId ? 'selected' : '' ?>>
                                    <?= rex_escape($userName) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

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
                </div>

                <div class="row">
                    <!-- YRewrite Domains (optional, multiple) -->
                    <?php if (rex_addon::exists('yrewrite') && rex_addon::get('yrewrite')->isAvailable()): ?>
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
                                    
                                    // Rechteprüfung: Admin oder User hat Domain-Berechtigung
                                    $hasDomainAccess = $user->isAdmin() || $user->getComplexPerm('structure_mountpoints')->hasPerm($domain->getMountId());
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
                    <?php if (rex_addon::exists('yform') && rex_addon::get('yform')->isAvailable()): ?>
                    <div class="col-sm-6">
                        <div class="form-group">
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

                <script>
                $(document).ready(function() {
                    // Selectpicker für multiple Felder neu initialisieren
                    $('#issue-domains, #issue-yform-tables').selectpicker('refresh');
                });
                </script>

                <!-- Tags -->
                <div class="form-group">
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

                <!-- Dateianhänge -->
                <div class="form-group">
                    <label for="issue-attachments"><?= $package->i18n('issue_tracker_attachments') ?></label>
                    <input 
                        type="file" 
                        id="issue-attachments" 
                        name="attachments[]" 
                        multiple 
                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.md,.zip"
                        class="form-control"
                    >
                    <p class="help-block"><?= $package->i18n('issue_tracker_attachments_help') ?></p>
                </div>

                <!-- Privat (nur für Admins) -->
                <?php if (rex::getUser()->isAdmin()): ?>
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
            </div>
        </div>
    </form>

    <?php if (!$isNew): ?>
    <!-- Dateianhänge -->
    <?php
    $attachments = \FriendsOfREDAXO\IssueTracker\Attachment::getByIssue($issue->getId());
    if (!empty($attachments)):
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><?= $package->i18n('issue_tracker_attachments') ?></h3>
        </div>
        <div class="panel-body">
            <div class="row">
                <?php foreach ($attachments as $attachment): ?>
                <div class="col-sm-6 col-md-4" style="margin-bottom: 15px;">
                    <div class="thumbnail">
                        <?php if ($attachment->isImage()): ?>
                            <img src="<?= $attachment->getThumbnailUrl() ?>" alt="<?= rex_escape($attachment->getOriginalFilename()) ?>" style="max-height: 150px; width: auto;">
                        <?php elseif ($attachment->isVideo()): ?>
                            <div style="padding: 30px; text-align: center; background: #f5f5f5;">
                                <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 48px; color: #999;"></i>
                            </div>
                        <?php else: ?>
                            <div style="padding: 30px; text-align: center; background: #f5f5f5;">
                                <i class="rex-icon <?= $attachment->getFileIcon() ?>" style="font-size: 48px; color: #999;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="caption">
                            <h5 style="margin-top: 5px; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                title="<?= rex_escape($attachment->getOriginalFilename()) ?>">
                                <?= rex_escape($attachment->getOriginalFilename()) ?>
                            </h5>
                            <p class="text-muted" style="font-size: 12px; margin-bottom: 10px;">
                                <?= $attachment->getFormattedFilesize() ?>
                            </p>
                            <div class="btn-group btn-group-xs">
                                <a href="<?= $attachment->getUrl() ?>" class="btn btn-default" target="_blank">
                                    <i class="rex-icon fa-download"></i> <?= $package->i18n('issue_tracker_download') ?>
                                </a>
                                <a href="<?= rex_url::currentBackendPage(['issue_id' => $issue->getId(), 'delete_attachment' => $attachment->getId()]) ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('<?= $package->i18n('issue_tracker_delete_attachment_confirm') ?>')">
                                    <i class="rex-icon fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
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

                <?php if (rex::getUser()->isAdmin()): ?>
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
