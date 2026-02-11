<?php
/**
 * Issues Filter Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$categories = $this->getVar('categories', []);
$statuses = $this->getVar('statuses', []);
$allTags = $this->getVar('allTags', []);
$users = $this->getVar('users', []);
$filterStatus = $this->getVar('filterStatus', '');
$filterCategory = $this->getVar('filterCategory', '');
$filterTag = $this->getVar('filterTag', 0);
$filterCreatedBy = $this->getVar('filterCreatedBy', 0);
$filterWatched = $this->getVar('filterWatched', 0);
$searchTerm = $this->getVar('searchTerm', '');
?>

<!-- Filter -->
<div class="panel panel-default" style="margin-bottom: 15px;">
    <?php 
    $savedFilters = \FriendsOfREDAXO\IssueTracker\SavedFilterService::getByUser(rex::getUser()->getId());
    
    // Aktive Filter zählen für Badge
    $activeFilterCount = 0;
    if ($filterStatus !== '') $activeFilterCount++;
    if ($filterCategory !== '') $activeFilterCount++;
    if ($filterTag > 0) $activeFilterCount++;
    if ($filterCreatedBy > 0) $activeFilterCount++;
    if ($filterWatched === 1) $activeFilterCount++;
    if ($searchTerm !== '') $activeFilterCount++;
    ?>
    <div class="panel-heading" style="padding: 8px 15px;">
        <div class="pull-left" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-right: 15px; line-height: 30px;">
            <span style="font-weight: bold; font-size: 13px;">
                <i class="rex-icon fa-filter"></i> <?= $package->i18n('issue_tracker_filter') ?>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge" style="background: #337ab7; font-size: 10px;"><?= $activeFilterCount ?></span>
                <?php endif; ?>
            </span>
            <?php if (!empty($savedFilters)): ?>
                <span style="color: #ccc;">|</span>
                <?php foreach ($savedFilters as $savedFilter): ?>
                    <div class="btn-group btn-group-xs">
                        <a href="<?= rex_url::currentBackendPage(array_merge(['page' => 'issue_tracker/issues/list'], $savedFilter['filters'])) ?>" 
                           class="btn <?= $savedFilter['is_default'] ? 'btn-primary' : 'btn-default' ?>" style="font-size: 11px;"
                           title="<?= $savedFilter['is_default'] ? $package->i18n('issue_tracker_is_default') : '' ?>">
                            <?php if ($savedFilter['is_default']): ?><i class="rex-icon fa-star"></i> <?php endif; ?>
                            <?= rex_escape($savedFilter['name']) ?>
                        </a>
                        <button type="button" class="btn <?= $savedFilter['is_default'] ? 'btn-primary' : 'btn-default' ?> dropdown-toggle" data-toggle="dropdown" style="font-size: 11px;">
                            <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu">
                            <?php if (!$savedFilter['is_default']): ?>
                            <li><a href="<?= rex_url::currentBackendPage(['set_default_filter' => $savedFilter['id']]) ?>"><i class="rex-icon fa-star"></i> <?= $package->i18n('issue_tracker_set_as_default') ?></a></li>
                            <?php endif; ?>
                            <li><a href="<?= rex_url::currentBackendPage(['delete_filter' => $savedFilter['id']]) ?>" onclick="return confirm('<?= $package->i18n('issue_tracker_delete_filter') ?>?')"><i class="rex-icon fa-trash"></i> <?= $package->i18n('issue_tracker_delete') ?></a></li>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="<?= rex_url::backendPage('issue_tracker/issues/create') ?>" class="btn btn-primary btn-xs pull-right" style="margin-top: 3px;">
            <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_create_new') ?>
        </a>
        <div class="clearfix"></div>
    </div>
    <div class="panel-body" style="padding: 12px 15px;">
        <form method="get" action="<?= rex_url::currentBackendPage() ?>">
            <input type="hidden" name="page" value="issue_tracker/issues/list" />
            <div class="row" style="margin-bottom: 8px;">
                <div class="col-md-2 col-sm-4" style="margin-bottom: 6px;">
                    <select name="filter_status" class="form-control selectpicker" data-width="100%" title="<?= $package->i18n('issue_tracker_status') ?>">
                        <option value=""><?= $package->i18n('issue_tracker_all_active') ?></option>
                        <option value="_all_" <?= $filterStatus === '_all_' ? 'selected' : '' ?>><?= $package->i18n('issue_tracker_all_issues') ?></option>
                        <option value="closed" <?= $filterStatus === 'closed' ? 'selected' : '' ?>><?= $package->i18n('issue_tracker_status_closed') ?></option>
                        <option data-divider="true"></option>
                        <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                        <?php if ($statusKey !== 'closed'): ?>
                        <option value="<?= rex_escape($statusKey) ?>" <?= $filterStatus === $statusKey ? 'selected' : '' ?>
                                data-content="<span class='label label-<?= ['open' => 'warning', 'in_progress' => 'info', 'planned' => 'primary', 'rejected' => 'danger', 'closed' => 'success'][$statusKey] ?? 'default' ?>'><?= $package->i18n('issue_tracker_status_' . $statusKey) ?></span>">
                            <?= $package->i18n('issue_tracker_status_' . $statusKey) ?>
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4" style="margin-bottom: 6px;">
                    <select name="filter_category" class="form-control selectpicker" data-width="100%" title="<?= $package->i18n('issue_tracker_category') ?>">
                        <option value=""><?= $package->i18n('issue_tracker_all') ?></option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= rex_escape($category) ?>" <?= $filterCategory === $category ? 'selected' : '' ?>><?= rex_escape($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4" style="margin-bottom: 6px;">
                    <select name="filter_tag" class="form-control selectpicker" data-width="100%" title="<?= $package->i18n('issue_tracker_tags') ?>">
                        <option value="0"><?= $package->i18n('issue_tracker_all') ?></option>
                        <?php foreach ($allTags as $tag): ?>
                        <option value="<?= $tag->getId() ?>" <?= $filterTag === $tag->getId() ? 'selected' : '' ?>
                                data-content="<span class='label' style='background-color: <?= rex_escape($tag->getColor()) ?>'><?= rex_escape($tag->getName()) ?></span>">
                            <?= rex_escape($tag->getName()) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4" style="margin-bottom: 6px;">
                    <select name="filter_created_by" class="form-control selectpicker" data-width="100%" data-live-search="true" title="<?= $package->i18n('issue_tracker_created_by') ?>">
                        <option value="0"><?= $package->i18n('issue_tracker_all') ?></option>
                        <?php foreach ($users as $userId => $userName): ?>
                        <option value="<?= $userId ?>" <?= $filterCreatedBy === $userId ? 'selected' : '' ?>><?= rex_escape($userName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 col-sm-4" style="margin-bottom: 6px;">
                    <select name="filter_watched" class="form-control selectpicker" data-width="100%" title="<?= $package->i18n('issue_tracker_filter_watched') ?>">
                        <option value="0"><?= $package->i18n('issue_tracker_all') ?></option>
                        <option value="1" <?= $filterWatched === 1 ? 'selected' : '' ?> data-content="<i class='rex-icon fa-eye' style='color: #337ab7;'></i> <?= $package->i18n('issue_tracker_filter_watched_only') ?>"><?= $package->i18n('issue_tracker_filter_watched_only') ?></option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-8" style="margin-bottom: 6px;">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" value="<?= rex_escape($searchTerm) ?>" placeholder="<?= $package->i18n('issue_tracker_search_placeholder') ?>" style="height: 34px;">
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-primary" title="<?= $package->i18n('issue_tracker_filter_apply') ?>">
                                <i class="rex-icon fa-search"></i>
                            </button>
                            <?php if ($activeFilterCount > 0): ?>
                            <a href="<?= rex_url::backendPage('issue_tracker/issues/list', ['reset_filter' => 1]) ?>" class="btn btn-default" title="<?= $package->i18n('issue_tracker_filter_reset') ?>">
                                <i class="rex-icon fa-times"></i>
                            </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#save-filter-modal" title="<?= $package->i18n('issue_tracker_save_filter') ?>">
                                <i class="rex-icon fa-save"></i>
                            </button>
                        </span>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal zum Filter speichern -->
<div class="modal fade" id="save-filter-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= rex_url::currentBackendPage() ?>">
                <input type="hidden" name="save_filter" value="1" />
                <input type="hidden" name="filter_status" value="<?= rex_escape($filterStatus) ?>" />
                <input type="hidden" name="filter_category" value="<?= rex_escape($filterCategory) ?>" />
                <input type="hidden" name="filter_tag" value="<?= $filterTag ?>" />
                <input type="hidden" name="filter_created_by" value="<?= $filterCreatedBy ?>" />
                <input type="hidden" name="filter_watched" value="<?= $filterWatched ?>" />
                <input type="hidden" name="search" value="<?= rex_escape($searchTerm) ?>" />
                
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><?= $package->i18n('issue_tracker_save_filter') ?></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="filter_name"><?= $package->i18n('issue_tracker_filter_name') ?> *</label>
                        <input type="text" class="form-control" id="filter_name" name="filter_name" required maxlength="100">
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="is_default" value="1">
                            <?= $package->i18n('issue_tracker_set_as_default') ?>
                        </label>
                    </div>
                    <div class="alert alert-info">
                        <p><strong><?= $package->i18n('issue_tracker_filter') ?>:</strong></p>
                        <ul style="margin: 0;">
                            <?php if ($filterStatus): ?>
                                <li><?= $package->i18n('issue_tracker_status') ?>: <?= $package->i18n('issue_tracker_status_' . $filterStatus) ?></li>
                            <?php endif; ?>
                            <?php if ($filterCategory): ?>
                                <li><?= $package->i18n('issue_tracker_category') ?>: <?= rex_escape($filterCategory) ?></li>
                            <?php endif; ?>
                            <?php if ($filterTag > 0): ?>
                                <?php 
                                $selectedTag = null;
                                foreach ($allTags as $tag) {
                                    if ($tag->getId() === $filterTag) {
                                        $selectedTag = $tag;
                                        break;
                                    }
                                }
                                if ($selectedTag):
                                ?>
                                <li><?= $package->i18n('issue_tracker_tags') ?>: <span class="label" style="background-color: <?= rex_escape($selectedTag->getColor()) ?>"><?= rex_escape($selectedTag->getName()) ?></span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($filterCreatedBy > 0 && isset($users[$filterCreatedBy])): ?>
                                <li><?= $package->i18n('issue_tracker_created_by') ?>: <?= rex_escape($users[$filterCreatedBy]) ?></li>
                            <?php endif; ?>
                            <?php if ($searchTerm): ?>
                                <li><?= $package->i18n('issue_tracker_search') ?>: "<?= rex_escape($searchTerm) ?>"</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?= $package->i18n('issue_tracker_cancel') ?></button>
                    <button type="submit" class="btn btn-success">
                        <i class="rex-icon fa-save"></i> <?= $package->i18n('issue_tracker_save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
