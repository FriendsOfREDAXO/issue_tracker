<?php
/**
 * Issue Formular Fragment – wie das Fenster „Neue E-Mail“: Kopfzeilen als Felder, Betreff, großer Editor
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
$advancedHasValues = ($issue->getVersion() ?? '') !== '' || !empty($issue->getDomainIds()) || !empty($issue->getYformTables()) || ($issue->getAssignedAddon() ?? '') !== '';

$cancelUrl = $isNew ? rex_url::backendPage('issue_tracker/issues') : rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()]);
$submitLabel = $isNew ? $package->i18n('issue_tracker_mail_create') : $package->i18n('issue_tracker_mail_save_changes');
?>

<?php
// Nach einem fehlgeschlagenen Speichern: eingegebene Werte erhalten, Fehler am Feld zeigen
$isRetry = rex_post('save', 'int', 0) === 1;
$postedAssigned = rex_post('assigned_user_id', 'string', '');
$assignError = $isRetry && $postedAssigned === '';
$titleError = $isRetry && trim(rex_post('title', 'string', '')) === '';
$selectedProject = $isRetry ? rex_post('project_id', 'int', 0) : (int) ($issue->getProjectId() ?? 0);
$selectedTags = $isRetry ? array_map('intval', rex_post('tags', 'array', [])) : $issueTagIds;
$selectedDue = $isRetry ? rex_post('due_date', 'string', '') : ($issue->getDueDate() ? $issue->getDueDate()->format('Y-m-d\TH:i') : '');
$selectedAssigned = $isRetry ? $postedAssigned : ($isNew ? '' : ($issue->getAssignedUserId() === null ? 'all' : (string) $issue->getAssignedUserId()));
$selectedCategory = $issue->getCategory();
if ($isNew && !$isRetry && $selectedCategory === '' && $categories !== []) {
    $selectedCategory = (string) reset($categories); // sinnvolle Vorbelegung
}
$selectedPriority = $issue->getPriority() ?: 'normal';
$moreHasValues = $advancedHasValues || !empty($selectedTags) || $selectedDue !== '' || $issue->getIsPrivate()
    || (!$isNew && $issue->getStatus() !== 'open') || ($selectedCategory !== '' && $selectedCategory !== (string) reset($categories));
$prioColors = ['low' => 'gray', 'normal' => 'blue', 'high' => 'orange', 'urgent' => 'red', 'critical' => 'red'];
?>
<?php $itTbLabels = ['bold' => $package->i18n('issue_tracker_mail_tb_bold'), 'italic' => $package->i18n('issue_tracker_mail_tb_italic'), 'unordered-list' => $package->i18n('issue_tracker_mail_tb_list'), 'ordered-list' => $package->i18n('issue_tracker_mail_tb_olist'), 'link' => $package->i18n('issue_tracker_mail_tb_link'), 'image' => $package->i18n('issue_tracker_mail_tb_image'), 'preview' => $package->i18n('issue_tracker_mail_tb_preview'), 'rex-link' => $package->i18n('issue_tracker_mail_tb_page')]; ?>
<div class="issue-tracker-form it-sheet" data-it-tb-labels="<?= rex_escape(json_encode($itTbLabels)) ?>"<?= !empty($mentionUsers) ? ' data-mention-users="' . rex_escape(json_encode(array_values($mentionUsers))) . '"' : '' ?>>
    <form method="post" action="<?= rex_url::currentBackendPage() ?>" enctype="multipart/form-data" data-it-form>
        <input type="hidden" name="save" value="1" />
        <?php if (!$isNew): ?>
        <input type="hidden" name="issue_id" value="<?= $issue->getId() ?>" />
        <input type="hidden" name="old_assigned_user_id" value="<?= $issue->getAssignedUserId() ?? 0 ?>" />
        <?php endif; ?>

        <div class="it-sheet-head">
            <h2 class="it-sheet-title">
                <span class="it-icon it-c-blue"><i class="rex-icon <?= $isNew ? 'fa-pencil-square-o' : 'fa-pencil' ?>" aria-hidden="true"></i></span>
                <?= $isNew ? $package->i18n('issue_tracker_mail_new_issue') : $package->i18n('issue_tracker_edit_issue') . ' #' . $issue->getId() ?>
            </h2>
            <div class="it-sheet-actions">
                <?php if ($isNew): ?>
                <button type="button" class="it-tb-btn" data-toggle="modal" data-target="#issue-help-modal" title="<?= rex_escape($package->i18n('issue_tracker_help')) ?>"><i class="rex-icon fa-question-circle" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_help') ?></span></button>
                <?php endif; ?>
                <a href="<?= $cancelUrl ?>" class="btn btn-default"><?= $package->i18n('issue_tracker_cancel') ?></a>
                <button type="submit" class="btn btn-primary it-btn-primary"><?= $submitLabel ?></button>
            </div>
        </div>

        <?php if ($assignError || $titleError): ?>
        <div class="it-form-summary" role="alert">
            <strong><?= $package->i18n('issue_tracker_mail_form_check') ?></strong>
            <ul>
                <?php if ($titleError): ?><li><a href="#issue-title"><?= $package->i18n('issue_tracker_mail_err_title') ?></a></li><?php endif; ?>
                <?php if ($assignError): ?><li><a href="#issue-assigned-user"><?= $package->i18n('issue_tracker_mail_err_assign') ?></a></li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="it-sheet-fields">
            <!-- Betreff -->
            <div class="it-field it-field--subject<?= $titleError ? ' has-error' : '' ?>">
                <label class="it-field-label" for="issue-title"><?= $package->i18n('issue_tracker_mail_subject') ?> <span class="it-req"><?= $package->i18n('issue_tracker_mail_required') ?></span></label>
                <div class="it-field-control">
                    <input type="text" class="form-control" id="issue-title" name="title" aria-describedby="issue-title-help"
                           value="<?= rex_escape($issue->getTitle()) ?>" required maxlength="255" placeholder="<?= rex_escape($package->i18n('issue_tracker_mail_subject_placeholder')) ?>">
                    <p class="it-field-help" id="issue-title-help"><?= $titleError ? '<span class="it-field-error">' . $package->i18n('issue_tracker_mail_err_title') . '</span>' : $package->i18n('issue_tracker_mail_subject_help') ?></p>
                </div>
            </div>

            <!-- Projekt -->
            <?php if (!empty($projects)): ?>
            <div class="it-field">
                <label class="it-field-label" for="issue-project"><?= $package->i18n('issue_tracker_project') ?></label>
                <div class="it-field-control">
                    <select class="form-control selectpicker" id="issue-project" name="project_id" data-live-search="true" data-width="100%" aria-describedby="issue-project-help">
                        <option value=""><?= $package->i18n('issue_tracker_no_project') ?></option>
                        <?php foreach ($projects as $projectId => $projectName): ?>
                        <option value="<?= $projectId ?>" <?= $selectedProject === $projectId ? 'selected' : '' ?>><?= rex_escape($projectName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="it-field-help" id="issue-project-help"><?= $package->i18n('issue_tracker_mail_project_help') ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Zuweisen -->
            <div class="it-field<?= $assignError ? ' has-error' : '' ?>">
                <label class="it-field-label" for="issue-assigned-user"><?= $package->i18n('issue_tracker_mail_assign_to') ?> <span class="it-req"><?= $package->i18n('issue_tracker_mail_required') ?></span></label>
                <div class="it-field-control">
                    <select class="form-control selectpicker" id="issue-assigned-user" name="assigned_user_id" data-live-search="true" data-width="100%" required aria-describedby="issue-assigned-help">
                        <option value=""><?= $package->i18n('issue_tracker_please_select') ?></option>
                        <option value="all" <?= $selectedAssigned === 'all' ? 'selected' : '' ?>><?= $package->i18n('issue_tracker_assigned_all_users') ?></option>
                        <?php foreach ($users as $userId => $userName): ?>
                        <option value="<?= $userId ?>" <?= $selectedAssigned === (string) $userId ? 'selected' : '' ?>><?= rex_escape($userName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="it-field-help" id="issue-assigned-help"><?= $assignError ? '<span class="it-field-error">' . $package->i18n('issue_tracker_mail_err_assign') . '</span>' : $package->i18n('issue_tracker_mail_assign_help') ?></p>
                </div>
            </div>

            <!-- Priorität -->
            <div class="it-field">
                <span class="it-field-label" id="issue-priority-label"><?= $package->i18n('issue_tracker_priority') ?></span>
                <div class="it-field-control">
                    <div class="it-segpick" role="radiogroup" aria-labelledby="issue-priority-label">
                        <?php foreach ($priorities as $priorityKey => $priorityLabel): ?>
                        <label class="it-segpick-opt it-segpick--<?= $prioColors[$priorityKey] ?? 'gray' ?>">
                            <input type="radio" name="priority" value="<?= rex_escape($priorityKey) ?>" <?= $selectedPriority === $priorityKey ? 'checked' : '' ?>>
                            <span><?= $package->i18n('issue_tracker_priority_' . $priorityKey) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Beschreibung -->
        <div class="it-sheet-editor">
            <label class="it-field-label" for="issue-description"><?= $package->i18n('issue_tracker_description') ?></label>
            <p class="it-field-help"><?= $package->i18n('issue_tracker_mail_description_help') ?></p>
            <textarea class="form-control" id="issue-description" name="description" rows="12"><?= rex_escape($issue->getDescription()) ?></textarea>
            <p class="it-field-help it-dnd-hint" hidden><i class="rex-icon fa-lightbulb-o" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_dnd_hint') ?></p>
        </div>

        <!-- Anhänge -->
        <div class="it-sheet-section">
            <div class="it-compose-row">
                <label class="it-file" for="issue-attachments">
                    <i class="rex-icon fa-paperclip" aria-hidden="true"></i>
                    <span><?= $package->i18n('issue_tracker_mail_add_files') ?></span>
                    <input type="file" id="issue-attachments" name="attachments[]" multiple
                           accept="image/*,video/*,.pdf,.doc,.docx,.odt,.xls,.xlsx,.ods,.csv,.ppt,.pptx,.odp,.txt,.md,.zip,.rar">
                </label>
                <span class="it-field-help"><?= $package->i18n('issue_tracker_attachments_help') ?></span>
            </div>
        </div>

        <!-- Weitere Angaben -->
        <details class="it-disclosure"<?= $moreHasValues ? ' open' : '' ?> data-it-more>
            <summary><i class="rex-icon fa-sliders" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_more_details') ?> <small class="it-muted-text"><?= $package->i18n('issue_tracker_mail_more_details_hint') ?></small></summary>
            <div class="it-disclosure-body">
                <div class="it-field">
                    <label class="it-field-label" for="issue-category"><?= $package->i18n('issue_tracker_category') ?></label>
                    <div class="it-field-control">
                        <select class="form-control selectpicker" id="issue-category" name="category" data-live-search="true" data-width="100%" aria-describedby="issue-category-help">
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= rex_escape($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= rex_escape($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="it-field-help" id="issue-category-help"><?= $package->i18n('issue_tracker_mail_category_help') ?></p>
                    </div>
                </div>

                <?php if ($showStatusField): ?>
                <div class="it-field">
                    <span class="it-field-label" id="issue-status-label"><?= $package->i18n('issue_tracker_status') ?></span>
                    <div class="it-field-control">
                        <div class="it-segpick it-segpick--wrap" role="radiogroup" aria-labelledby="issue-status-label">
                            <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                            <label class="it-segpick-opt">
                                <input type="radio" name="status" value="<?= rex_escape($statusKey) ?>" <?= ($issue->getStatus() ?: 'open') === $statusKey ? 'checked' : '' ?>>
                                <span><span class="it-dot it-dot--<?= rex_escape($statusKey) ?>" aria-hidden="true"></span> <?= $package->i18n('issue_tracker_status_' . $statusKey) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="it-field">
                    <label class="it-field-label" for="issue-tags"><?= $package->i18n('issue_tracker_tags') ?></label>
                    <div class="it-field-control">
                        <select class="form-control selectpicker" id="issue-tags" name="tags[]" multiple data-live-search="true" data-width="100%" title="<?= rex_escape($package->i18n('issue_tracker_mail_none')) ?>" aria-describedby="issue-tags-help">
                            <?php foreach ($allTags as $tag): ?>
                            <option value="<?= $tag->getId() ?>" <?= in_array($tag->getId(), $selectedTags, true) ? 'selected' : '' ?>><?= rex_escape($tag->getName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="it-field-help" id="issue-tags-help"><?= $package->i18n('issue_tracker_mail_tags_help') ?></p>
                    </div>
                </div>

                <div class="it-field">
                    <label class="it-field-label" for="issue-due-date"><?= $package->i18n('issue_tracker_due_date') ?></label>
                    <div class="it-field-control">
                        <input type="datetime-local" class="form-control" id="issue-due-date" name="due_date" value="<?= rex_escape($selectedDue) ?>" aria-describedby="issue-due-help">
                        <p class="it-field-help" id="issue-due-help"><?= $package->i18n('issue_tracker_mail_due_help') ?></p>
                    </div>
                </div>

                <?php if (PermissionService::canCreatePrivateIssues()): ?>
                <div class="it-field">
                    <span class="it-field-label" id="issue-private-label"><?= $package->i18n('issue_tracker_private') ?></span>
                    <div class="it-field-control">
                        <label class="it-check">
                            <span class="it-switch"><input type="checkbox" name="is_private" value="1" aria-labelledby="issue-private-label" aria-describedby="issue-private-help" <?= $issue->getIsPrivate() ? 'checked' : '' ?>><span></span></span>
                            <span class="it-field-help" id="issue-private-help"><?= $package->i18n('issue_tracker_private_issue_help') ?></span>
                        </label>
                    </div>
                </div>
                <?php endif; ?>

                <div class="it-field">
                    <label class="it-field-label" for="issue-assigned-addon"><?= $package->i18n('issue_tracker_assigned_addon') ?></label>
                    <div class="it-field-control">
                        <select class="form-control selectpicker" id="issue-assigned-addon" name="assigned_addon" data-live-search="true" data-width="100%" aria-describedby="issue-addon-help">
                            <option value=""><?= $package->i18n('issue_tracker_not_assigned') ?></option>
                            <?php foreach ($addons as $addonKey => $addonName): ?>
                            <option value="<?= rex_escape($addonKey) ?>" <?= $issue->getAssignedAddon() === $addonKey ? 'selected' : '' ?>><?= rex_escape($addonName) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="it-field-help" id="issue-addon-help"><?= $package->i18n('issue_tracker_mail_addon_help') ?></p>
                    </div>
                </div>
                <div class="it-field">
                    <label class="it-field-label" for="issue-version"><?= $package->i18n('issue_tracker_version') ?></label>
                    <div class="it-field-control">
                        <input type="text" class="form-control" id="issue-version" name="version" value="<?= rex_escape($issue->getVersion() ?? '') ?>" maxlength="50" aria-describedby="issue-version-help">
                        <p class="it-field-help" id="issue-version-help"><?= $package->i18n('issue_tracker_mail_version_help') ?></p>
                    </div>
                </div>
                <?php if ($hasDomains): ?>
                <div class="it-field">
                    <label class="it-field-label" for="issue-domains"><?= $package->i18n('issue_tracker_domain') ?></label>
                    <div class="it-field-control">
                        <select class="form-control selectpicker" id="issue-domains" name="domain_ids[]" multiple="multiple" data-live-search="true" data-actions-box="true" data-selected-text-format="count > 2" data-width="100%" title="<?= rex_escape($package->i18n('issue_tracker_please_select')) ?>">
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
                            <option value="<?= $domainId ?>" <?= in_array($domainId, $selectedDomainIds, true) ? 'selected' : '' ?>><?= rex_escape($domainName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($hasYformTables): ?>
                <div class="it-field">
                    <label class="it-field-label" for="issue-yform-tables"><?= $package->i18n('issue_tracker_yform_table') ?></label>
                    <div class="it-field-control">
                        <select class="form-control selectpicker" id="issue-yform-tables" name="yform_tables[]" multiple="multiple" data-live-search="true" data-actions-box="true" data-selected-text-format="count > 2" data-width="100%" title="<?= rex_escape($package->i18n('issue_tracker_please_select')) ?>">
                            <?php
                            $user = rex::getUser();
                            $selectedYformTables = $issue->getYformTables();
                            foreach (rex_yform_manager_table::getAll() as $yTable):
                                // Rechteprüfung: Admin oder User hat Tabellen-Berechtigung
                                $tableName = $yTable->getTableName();
                                $hasAccess = $user->isAdmin() || $user->getComplexPerm('yform_manager_table_edit')->hasPerm($tableName);
                                if (!$hasAccess) continue;
                            ?>
                            <option value="<?= rex_escape($tableName) ?>" <?= in_array($tableName, $selectedYformTables, true) ? 'selected' : '' ?>><?= rex_escape(rex_i18n::translate($yTable->getName())) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </details>

        <div class="it-sheet-foot">
            <a href="<?= $cancelUrl ?>" class="btn btn-default"><?= $package->i18n('issue_tracker_cancel') ?></a>
            <button type="submit" class="btn btn-primary it-btn-primary"><?= $submitLabel ?></button>
        </div>
    </form>

    <?php if (!$isNew): ?>
    <?php $attachments = Attachment::getByIssue($issue->getId()); ?>
    <?php if (!empty($attachments)): ?>
    <details class="it-disclosure" open>
        <summary><i class="rex-icon fa-paperclip" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_attachments') ?> <span class="it-disclosure-count"><?= count($attachments) ?></span></summary>
        <div class="it-disclosure-body">
            <div class="row" data-lightbox-group="issue-<?= $issue->getId() ?>-edit">
                <?php foreach ($attachments as $attachment): ?>
                <div class="col-sm-6 col-md-4">
                    <?= $attachment->renderCard([
                        'maxHeight' => 150,
                        'deleteUrl' => rex_url::currentBackendPage(['issue_id' => $issue->getId(), 'delete_attachment' => $attachment->getId()]),
                    ]) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php endif; ?>

    <?php $comments = $issue->getComments(); ?>
    <details class="it-disclosure">
        <summary><i class="rex-icon fa-comments-o" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_comments') ?> <span class="it-disclosure-count"><?= count($comments) ?></span></summary>
        <div class="it-disclosure-body">
            <?php if (empty($comments)): ?>
                <p class="it-muted-text"><?= $package->i18n('issue_tracker_no_comments') ?></p>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <?php $creator = $comment->getCreator(); ?>
                    <article class="it-msg">
                        <div class="it-msg-who">
                            <strong class="it-msg-name"><?= $creator ? rex_escape($creator->getValue('name')) : 'Unbekannt' ?></strong>
                            <?php if ($comment->isInternal()): ?>
                                <span class="it-badge it-badge--warn"><?= $package->i18n('issue_tracker_internal') ?></span>
                            <?php endif; ?>
                            <span class="it-msg-date"><?= $comment->getCreatedAt() ? $comment->getCreatedAt()->format('d.m.Y H:i') : '' ?></span>
                        </div>
                        <div class="it-rp-body"><?= rex_markdown::factory()->parse($comment->getComment()) ?></div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Neuer Kommentar -->
            <form method="post" action="<?= rex_url::currentBackendPage(['issue_id' => $issue->getId()]) ?>" class="it-compose">
                <input type="hidden" name="add_comment" value="1" />
                <input type="hidden" name="issue_id" value="<?= $issue->getId() ?>" />
                <label class="it-compose-title" for="comment-text"><?= $package->i18n('issue_tracker_add_comment') ?></label>
                <textarea class="form-control" id="comment-text" name="comment" rows="4"></textarea>
                <div class="it-compose-row">
                    <?php if (PermissionService::canCreateInternalComments()): ?>
                    <label class="it-check"><input type="checkbox" name="is_internal" value="1"> <span><?= $package->i18n('issue_tracker_internal_comment') ?></span></label>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary it-btn-primary it-compose-send"><?= $package->i18n('issue_tracker_mail_send_reply') ?></button>
                </div>
            </form>
        </div>
    </details>
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
                    <ul>
                        <li><?= rex_i18n::rawMsg('issue_tracker_help_good_1') ?></li>
                        <li><?= rex_i18n::rawMsg('issue_tracker_help_good_2') ?></li>
                        <li><?= rex_i18n::rawMsg('issue_tracker_help_good_3') ?></li>
                    </ul>
                </div>

                <div class="alert alert-danger">
                    <strong><i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_help_bad_example') ?></strong>
                    <ul>
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
