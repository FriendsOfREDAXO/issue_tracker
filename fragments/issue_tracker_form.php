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
$moreHasValues = $advancedHasValues || $issue->getIsPrivate();
$colorVar = static fn (string $c): string => preg_match('/^#[0-9a-f]{3,8}$/i', $c) ? '--c:' . $c : '--c:#7c7c82';

// Betroffene Seite: Artikel aus der Struktur (Linkmap-Widget) oder freie Adresse
$hasLinkWidget = class_exists('rex_var_link') && rex_addon::get('structure')->isAvailable();
$clangs = $hasLinkWidget ? array_values(rex_clang::getAll()) : [];
$selectedArticle = (int) ($issue->getArticleId() ?? 0);
$selectedClang = (int) ($issue->getClangId() ?? rex_clang::getCurrentId());
$selectedUrl = (string) ($issue->getAffectedUrl() ?? '');
$urlError = $isRetry && !\FriendsOfREDAXO\IssueTracker\Issue::isValidAffectedUrl(rex_post('affected_url', 'string', ''));
if ($urlError) {
    $selectedUrl = rex_post('affected_url', 'string', '');
}
$pageLabel = '';
if ($selectedArticle > 0) {
    $affectedArticle = $issue->getAffectedArticle();
    $pageLabel = $affectedArticle ? trim($affectedArticle->getName()) . ' [' . $selectedArticle . ']' : '#' . $selectedArticle;
} elseif ($selectedUrl !== '') {
    $pageLabel = $selectedUrl;
}
// Neues Tag direkt in der Leiste anlegen (nur wer Tags verwalten darf)
$tagApiUrl = PermissionService::canManageTags() ? rex_url::backendPage('issue_tracker/issues/list', rex_api_issue_tracker_tags::getUrlParams(), false) : '';
?>
<?php $itTbLabels = ['bold' => $package->i18n('issue_tracker_mail_tb_bold'), 'italic' => $package->i18n('issue_tracker_mail_tb_italic'), 'unordered-list' => $package->i18n('issue_tracker_mail_tb_list'), 'ordered-list' => $package->i18n('issue_tracker_mail_tb_olist'), 'link' => $package->i18n('issue_tracker_mail_tb_link'), 'image' => $package->i18n('issue_tracker_mail_tb_image'), 'preview' => $package->i18n('issue_tracker_mail_tb_preview'), 'rex-link' => $package->i18n('issue_tracker_mail_tb_page')]; ?>
<div class="issue-tracker-form it-sheet" data-it-tb-labels="<?= rex_escape(json_encode($itTbLabels)) ?>"<?= !empty($mentionUsers) ? ' data-mention-users="' . rex_escape(json_encode(array_values($mentionUsers))) . '"' : '' ?>>
    <form method="post" action="<?= rex_url::currentBackendPage() ?>" enctype="multipart/form-data" data-it-form data-it-unsaved="<?= rex_escape(rex_i18n::rawMsg('issue_tracker_unsaved_confirm')) ?>">
        <input type="hidden" name="save" value="1" />
        <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>
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
                <a href="<?= $cancelUrl ?>" class="btn btn-default" data-it-cancel><?= $package->i18n('issue_tracker_cancel') ?></a>
                <button type="submit" class="btn btn-primary it-btn-primary"><?= $submitLabel ?></button>
            </div>
        </div>

        <?php if ($assignError || $titleError || $urlError): ?>
        <div class="it-form-summary" role="alert">
            <strong><?= $package->i18n('issue_tracker_mail_form_check') ?></strong>
            <ul>
                <?php if ($titleError): ?><li><a href="#issue-title"><?= $package->i18n('issue_tracker_mail_err_title') ?></a></li><?php endif; ?>
                <?php if ($assignError): ?><li><a href="#issue-assigned-user"><?= $package->i18n('issue_tracker_mail_err_assign') ?></a></li><?php endif; ?>
                <?php if ($urlError): ?><li><a href="#issue-affected-url"><?= $package->i18n('issue_tracker_affected_url_invalid') ?></a></li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Eigenschaften-Leiste: wie die Chips im Lesebereich – Werte gelten sofort beim neuen Thema -->
        <div class="it-qbar-row<?= $assignError || $urlError ? ' has-error' : '' ?>">
            <div class="it-qbar" role="group" aria-label="<?= rex_escape($package->i18n('issue_tracker_qbar_label')) ?>"<?= $tagApiUrl !== '' ? ' data-it-tag-api="' . rex_escape($tagApiUrl) . '"' : '' ?> data-it-qbar-labels="<?= rex_escape(json_encode(['new' => $package->i18n('issue_tracker_quicktag_new', '{0}'), 'error' => $package->i18n('issue_tracker_quicktag_error'), 'assign' => $package->i18n('issue_tracker_mail_err_assign'), 'url' => rex_i18n::rawMsg('issue_tracker_affected_url_invalid')])) ?>">
                <!-- Zuweisen (Pflicht) -->
                <details class="it-menu it-qchip<?= $assignError ? ' has-error' : '' ?>" data-it-qchip="single" data-it-qchip-required>
                    <summary class="it-qchip-btn" id="issue-assigned-user" aria-describedby="issue-qbar-help">
                        <i class="rex-icon fa-user" aria-hidden="true"></i>
                        <span class="it-qchip-label"><?= $package->i18n('issue_tracker_mail_assign_to') ?><span class="it-req-mark" aria-hidden="true">*</span><span class="sr-only"> (<?= $package->i18n('issue_tracker_mail_required') ?>)</span></span>
                        <span class="it-qchip-value" data-it-qchip-value data-empty="<?= rex_escape($package->i18n('issue_tracker_please_select')) ?>"><?= $selectedAssigned === 'all' ? rex_escape($package->i18n('issue_tracker_assigned_all_users')) : (isset($users[(int) $selectedAssigned]) ? rex_escape($users[(int) $selectedAssigned]) : rex_escape($package->i18n('issue_tracker_please_select'))) ?></span>
                    </summary>
                    <div class="it-menu-pop it-qchip-pop it-qchip-pop--scroll" role="radiogroup" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_assign_to')) ?>">
                        <?php if (count($users) > 6): ?><input type="search" class="it-qchip-search" data-it-qchip-search placeholder="<?= rex_escape($package->i18n('issue_tracker_qbar_search')) ?>" aria-label="<?= rex_escape($package->i18n('issue_tracker_qbar_search')) ?>"><?php endif; ?>
                        <label class="it-menu-item it-qchip-opt"><input type="radio" name="assigned_user_id" value="all"<?= $selectedAssigned === 'all' ? ' checked' : '' ?>><i class="rex-icon fa-check" aria-hidden="true"></i><span data-it-qchip-text><i class="rex-icon fa-users" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_assigned_all_users') ?></span></label>
                        <?php foreach ($users as $userId => $userName): ?>
                        <label class="it-menu-item it-qchip-opt"><input type="radio" name="assigned_user_id" value="<?= (int) $userId ?>"<?= $selectedAssigned === (string) $userId ? ' checked' : '' ?>><i class="rex-icon fa-check" aria-hidden="true"></i><span data-it-qchip-text><?= rex_escape($userName) ?></span></label>
                        <?php endforeach; ?>
                        <p class="it-qchip-empty" hidden><?= $package->i18n('issue_tracker_qbar_no_match') ?></p>
                    </div>
                </details>

                <!-- Priorität -->
                <?php if ($showStatusField): $selectedStatus = (string) ($issue->getStatus() ?: 'open'); ?>
                <details class="it-menu it-qchip" data-it-qchip="single">
                    <summary class="it-qchip-btn">
                        <span class="it-qchip-label"><?= $package->i18n('issue_tracker_status') ?></span>
                        <span class="it-qchip-value" data-it-qchip-value><span class="it-dot it-dot--sm it-dot--<?= rex_escape($selectedStatus) ?>" aria-hidden="true"></span> <?= $package->i18n('issue_tracker_status_' . $selectedStatus) ?></span>
                    </summary>
                    <div class="it-menu-pop it-qchip-pop" role="radiogroup" aria-label="<?= rex_escape($package->i18n('issue_tracker_status')) ?>">
                        <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                        <label class="it-menu-item it-qchip-opt"><input type="radio" name="status" value="<?= rex_escape($statusKey) ?>"<?= $selectedStatus === $statusKey ? ' checked' : '' ?>><i class="rex-icon fa-check" aria-hidden="true"></i><span data-it-qchip-text><span class="it-dot it-dot--sm it-dot--<?= rex_escape($statusKey) ?>" aria-hidden="true"></span> <?= $package->i18n('issue_tracker_status_' . $statusKey) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endif; ?>

                <details class="it-menu it-qchip" data-it-qchip="single">
                    <summary class="it-qchip-btn">
                        <span class="it-qchip-label"><?= $package->i18n('issue_tracker_priority') ?></span>
                        <span class="it-qchip-value" data-it-qchip-value><i class="rex-icon fa-flag it-prio-<?= rex_escape($selectedPriority) ?>" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_priority_' . $selectedPriority) ?></span>
                    </summary>
                    <div class="it-menu-pop it-qchip-pop" role="radiogroup" aria-label="<?= rex_escape($package->i18n('issue_tracker_priority')) ?>">
                        <?php foreach ($priorities as $priorityKey => $priorityLabel): ?>
                        <label class="it-menu-item it-qchip-opt"><input type="radio" name="priority" value="<?= rex_escape($priorityKey) ?>"<?= $selectedPriority === $priorityKey ? ' checked' : '' ?>><i class="rex-icon fa-check" aria-hidden="true"></i><span data-it-qchip-text><i class="rex-icon fa-flag it-prio-<?= rex_escape($priorityKey) ?>" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_priority_' . $priorityKey) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </details>

                <!-- Kategorie -->
                <?php if (!empty($categories)): ?>
                <details class="it-menu it-qchip" data-it-qchip="single">
                    <summary class="it-qchip-btn">
                        <i class="rex-icon fa-folder-o" aria-hidden="true"></i>
                        <span class="it-qchip-label"><?= $package->i18n('issue_tracker_category') ?></span>
                        <span class="it-qchip-value" data-it-qchip-value><?= rex_escape($selectedCategory) ?></span>
                    </summary>
                    <div class="it-menu-pop it-qchip-pop it-qchip-pop--scroll" role="radiogroup" aria-label="<?= rex_escape($package->i18n('issue_tracker_category')) ?>">
                        <?php if (count($categories) > 6): ?><input type="search" class="it-qchip-search" data-it-qchip-search placeholder="<?= rex_escape($package->i18n('issue_tracker_qbar_search')) ?>" aria-label="<?= rex_escape($package->i18n('issue_tracker_qbar_search')) ?>"><?php endif; ?>
                        <?php foreach ($categories as $category): ?>
                        <label class="it-menu-item it-qchip-opt"><input type="radio" name="category" value="<?= rex_escape($category) ?>"<?= $selectedCategory === $category ? ' checked' : '' ?>><i class="rex-icon fa-check" aria-hidden="true"></i><span data-it-qchip-text><?= rex_escape($category) ?></span></label>
                        <?php endforeach; ?>
                        <p class="it-qchip-empty" hidden><?= $package->i18n('issue_tracker_qbar_no_match') ?></p>
                    </div>
                </details>
                <?php else: ?>
                <input type="hidden" name="category" value="">
                <?php endif; ?>

                <!-- Projekt -->
                <?php if (!empty($projects)): ?>
                <details class="it-menu it-qchip" data-it-qchip="single">
                    <summary class="it-qchip-btn">
                        <i class="rex-icon fa-briefcase" aria-hidden="true"></i>
                        <span class="it-qchip-label"><?= $package->i18n('issue_tracker_project') ?></span>
                        <span class="it-qchip-value" data-it-qchip-value data-empty="<?= rex_escape($package->i18n('issue_tracker_qbar_none')) ?>"><?= isset($projects[$selectedProject]) ? rex_escape($projects[$selectedProject]) : rex_escape($package->i18n('issue_tracker_qbar_none')) ?></span>
                    </summary>
                    <div class="it-menu-pop it-qchip-pop it-qchip-pop--scroll" role="radiogroup" aria-label="<?= rex_escape($package->i18n('issue_tracker_project')) ?>">
                        <?php if (count($projects) > 6): ?><input type="search" class="it-qchip-search" data-it-qchip-search placeholder="<?= rex_escape($package->i18n('issue_tracker_qbar_search')) ?>" aria-label="<?= rex_escape($package->i18n('issue_tracker_qbar_search')) ?>"><?php endif; ?>
                        <label class="it-menu-item it-qchip-opt"><input type="radio" name="project_id" value=""<?= !isset($projects[$selectedProject]) ? ' checked' : '' ?>><i class="rex-icon fa-check" aria-hidden="true"></i><span data-it-qchip-text data-it-qchip-none><?= $package->i18n('issue_tracker_no_project') ?></span></label>
                        <?php foreach ($projects as $projectId => $projectName): ?>
                        <label class="it-menu-item it-qchip-opt"><input type="radio" name="project_id" value="<?= (int) $projectId ?>"<?= $selectedProject === $projectId ? ' checked' : '' ?>><i class="rex-icon fa-check" aria-hidden="true"></i><span data-it-qchip-text><?= rex_escape($projectName) ?></span></label>
                        <?php endforeach; ?>
                        <p class="it-qchip-empty" hidden><?= $package->i18n('issue_tracker_qbar_no_match') ?></p>
                    </div>
                </details>
                <?php endif; ?>

                <!-- Fälligkeit -->
                <details class="it-menu it-qchip" data-it-qchip="due">
                    <summary class="it-qchip-btn">
                        <i class="rex-icon fa-calendar" aria-hidden="true"></i>
                        <span class="it-qchip-label"><?= $package->i18n('issue_tracker_due_date') ?></span>
                        <span class="it-qchip-value" data-it-qchip-value data-empty="<?= rex_escape($package->i18n('issue_tracker_qbar_none')) ?>"><?= $selectedDue !== '' ? rex_escape(date('d.m.Y H:i', (int) strtotime($selectedDue))) : rex_escape($package->i18n('issue_tracker_qbar_none')) ?></span>
                    </summary>
                    <div class="it-menu-pop it-qchip-pop it-qchip-pop--form">
                        <label class="it-menu-field" for="issue-due-date"><span><?= $package->i18n('issue_tracker_due_date') ?></span></label>
                        <input type="datetime-local" class="form-control" id="issue-due-date" name="due_date" value="<?= rex_escape($selectedDue) ?>" aria-describedby="issue-due-help">
                        <p class="it-field-help" id="issue-due-help"><?= $package->i18n('issue_tracker_mail_due_help') ?></p>
                        <div class="it-menu-actions">
                            <button type="button" class="btn btn-default btn-sm" data-it-qchip-clear><?= $package->i18n('issue_tracker_qbar_remove') ?></button>
                            <button type="button" class="btn btn-primary it-btn-primary btn-sm" data-it-qchip-close><?= $package->i18n('issue_tracker_qbar_done') ?></button>
                        </div>
                    </div>
                </details>

                <!-- Tags -->
                <details class="it-menu it-qchip" data-it-qchip="multi">
                    <summary class="it-qchip-btn">
                        <i class="rex-icon fa-tags" aria-hidden="true"></i>
                        <span class="it-qchip-label"><?= $package->i18n('issue_tracker_tags') ?></span>
                        <span class="it-qchip-value" data-it-qchip-value data-empty="<?= rex_escape($package->i18n('issue_tracker_qbar_none')) ?>"><?php
                            $tagNames = [];
                            foreach ($allTags as $tag) {
                                if (in_array($tag->getId(), $selectedTags, true)) {
                                    $tagNames[] = rex_escape($tag->getName());
                                }
                            }
                            echo $tagNames === [] ? rex_escape($package->i18n('issue_tracker_qbar_none')) : implode(', ', $tagNames);
                        ?></span>
                    </summary>
                    <div class="it-menu-pop it-qchip-pop it-qchip-pop--scroll" role="group" aria-label="<?= rex_escape($package->i18n('issue_tracker_tags')) ?>">
                        <input type="search" class="it-qchip-search" data-it-qchip-search placeholder="<?= rex_escape($package->i18n('issue_tracker_qbar_search')) ?>" aria-label="<?= rex_escape($package->i18n('issue_tracker_qbar_search')) ?>">
                        <div data-it-qchip-list>
                        <?php foreach ($allTags as $tag): ?>
                        <label class="it-menu-item it-qchip-opt"><input type="checkbox" name="tags[]" value="<?= $tag->getId() ?>"<?= in_array($tag->getId(), $selectedTags, true) ? ' checked' : '' ?>><i class="rex-icon fa-check" aria-hidden="true"></i><span data-it-qchip-text><span class="it-dot it-dot--custom it-dot--sm" style="<?= rex_escape($colorVar($tag->getColor())) ?>" aria-hidden="true"></span> <?= rex_escape($tag->getName()) ?></span></label>
                        <?php endforeach; ?>
                        </div>
                        <p class="it-qchip-empty" hidden><?= $package->i18n('issue_tracker_qbar_no_match') ?></p>
                        <?php if ($tagApiUrl !== ''): ?>
                        <button type="button" class="it-menu-item it-qchip-new" data-it-qchip-new hidden><i class="rex-icon fa-plus" aria-hidden="true"></i> <span></span></button>
                        <?php endif; ?>
                    </div>
                </details>

                <!-- Betroffene Seite -->
                <details class="it-menu it-qchip<?= $urlError ? ' has-error' : '' ?>" data-it-qchip="page"<?= $urlError ? ' open' : '' ?>>
                    <summary class="it-qchip-btn" id="issue-affected-page">
                        <i class="rex-icon fa-file-text-o" aria-hidden="true"></i>
                        <span class="it-qchip-label"><?= $package->i18n('issue_tracker_affected_page') ?></span>
                        <span class="it-qchip-value" data-it-qchip-value data-empty="<?= rex_escape($package->i18n('issue_tracker_qbar_none')) ?>"><?= $pageLabel !== '' ? rex_escape($pageLabel) : rex_escape($package->i18n('issue_tracker_qbar_none')) ?></span>
                    </summary>
                    <div class="it-menu-pop it-qchip-pop it-qchip-pop--form it-qchip-pop--wide">
                        <p class="it-field-help it-qchip-intro"><?= $package->i18n('issue_tracker_affected_page_help') ?></p>
                        <?php if ($hasLinkWidget): ?>
                        <div class="it-menu-field">
                            <span id="issue-affected-structure-label"><?= $package->i18n('issue_tracker_affected_from_structure') ?></span>
                            <div class="it-qchip-link" data-it-page-widget role="group" aria-labelledby="issue-affected-structure-label"><?= rex_var_link::getWidget('it_affected', 'article_id', $selectedArticle > 0 ? $selectedArticle : '') ?></div>
                        </div>
                        <?php if (count($clangs) > 1): ?>
                        <label class="it-menu-field" for="issue-affected-clang"><span><?= $package->i18n('issue_tracker_affected_language') ?></span></label>
                        <select class="form-control" id="issue-affected-clang" name="clang_id">
                            <?php foreach ($clangs as $clang): ?>
                            <option value="<?= $clang->getId() ?>"<?= $selectedClang === $clang->getId() ? ' selected' : '' ?>><?= rex_escape($clang->getName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="hidden" name="clang_id" value="<?= (int) $selectedClang ?>">
                        <?php endif; ?>
                        <?php endif; ?>
                        <label class="it-menu-field it-qchip-urlfield" for="issue-affected-url"><span><?= $package->i18n($hasLinkWidget ? 'issue_tracker_affected_enter_url' : 'issue_tracker_affected_page') ?></span></label>
                        <input type="text" inputmode="url" class="form-control" id="issue-affected-url" name="affected_url" maxlength="500" value="<?= rex_escape($selectedUrl) ?>" placeholder="<?= rex_escape($package->i18n('issue_tracker_affected_url_placeholder')) ?>"<?= $urlError ? ' aria-invalid="true" aria-describedby="issue-affected-url-error"' : '' ?>>
                        <?php if ($urlError): ?><p class="it-field-help" id="issue-affected-url-error"><span class="it-field-error"><?= $package->i18n('issue_tracker_affected_url_invalid') ?></span></p><?php endif; ?>
                        <div class="it-menu-actions">
                            <button type="button" class="btn btn-default btn-sm" data-it-page-clear<?= $pageLabel === '' && $selectedUrl === '' ? ' hidden' : '' ?>><?= $package->i18n('issue_tracker_affected_clear') ?></button>
                            <button type="button" class="btn btn-primary it-btn-primary btn-sm" data-it-qchip-close><?= $package->i18n('issue_tracker_qbar_done') ?></button>
                        </div>
                    </div>
                </details>
            </div>
            <p class="it-field-help it-qbar-help" id="issue-qbar-help" aria-live="polite"><?= $assignError ? '<span class="it-field-error">' . $package->i18n('issue_tracker_mail_err_assign') . '</span>' : $package->i18n('issue_tracker_qbar_help') ?></p>
        </div>

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
            <a href="<?= $cancelUrl ?>" class="btn btn-default" data-it-cancel><?= $package->i18n('issue_tracker_cancel') ?></a>
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
                        'deleteUrl' => rex_url::currentBackendPage(['issue_id' => $issue->getId(), 'delete_attachment' => $attachment->getId()] + \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getUrlParams()),
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
                <?= \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getHiddenField() ?>
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
