<?php
/**
 * Wissen / Knowledge Base Fragment
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\ContentRenderer;

$package = rex_addon::get('issue_tracker');

/** @var array<string, \FriendsOfREDAXO\IssueTracker\Issue[]> $grouped */
$grouped = $this->getVar('grouped', []);
$totalCount = (int) $this->getVar('totalCount', 0);
$categories = $this->getVar('categories', []);
$allTags = $this->getVar('allTags', []);
$searchTerm = $this->getVar('searchTerm', '');
$filterCategory = $this->getVar('filterCategory', '');
$filterTag = $this->getVar('filterTag', 0);
$canCreate = $this->getVar('canCreate', false);

$hasActiveFilter = $searchTerm !== '' || $filterCategory !== '' || $filterTag > 0;

function issueTrackerKnowledgeExcerpt(string $description, int $length = 160): string
{
    $plain = strip_tags(rex_markdown::factory()->parse(ContentRenderer::stripSpentCommand($description)));
    $plain = trim(preg_replace('/\s+/', ' ', $plain));
    if ($plain === '') {
        return '';
    }
    return mb_strlen($plain) > $length ? mb_substr($plain, 0, $length) . '…' : $plain;
}
?>

<div class="issue-tracker-knowledge">
    <!-- Intro & Suche -->
    <div class="panel panel-default">
        <div class="panel-body">
            <p class="text-muted" style="margin-bottom: 15px;"><?= $package->i18n('issue_tracker_knowledge_intro') ?></p>
            <form method="get" action="<?= rex_url::currentBackendPage() ?>">
                <input type="hidden" name="page" value="issue_tracker/knowledge" />
                <div class="row">
                    <div class="col-md-4 col-sm-6" style="margin-bottom: 6px;">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" value="<?= rex_escape($searchTerm) ?>" placeholder="<?= $package->i18n('issue_tracker_knowledge_search_placeholder') ?>" style="height: 34px;">
                            <span class="input-group-btn">
                                <button type="submit" class="btn btn-primary"><i class="rex-icon fa-search"></i></button>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 6px;">
                        <select name="filter_category" class="form-control selectpicker" data-width="100%" title="<?= $package->i18n('issue_tracker_category') ?>" onchange="this.form.submit()">
                            <option value=""><?= $package->i18n('issue_tracker_all') ?></option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= rex_escape($category) ?>" <?= $filterCategory === $category ? 'selected' : '' ?>><?= rex_escape($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 6px;">
                        <select name="filter_tag" class="form-control selectpicker" data-width="100%" title="<?= $package->i18n('issue_tracker_tags') ?>" onchange="this.form.submit()">
                            <option value="0"><?= $package->i18n('issue_tracker_all') ?></option>
                            <?php foreach ($allTags as $tag): ?>
                            <option value="<?= $tag->getId() ?>" <?= $filterTag === $tag->getId() ? 'selected' : '' ?>
                                    data-content="<span class='label' style='background-color: <?= rex_escape($tag->getColor()) ?>'><?= rex_escape($tag->getName()) ?></span>">
                                <?= rex_escape($tag->getName()) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6" style="margin-bottom: 6px;">
                        <?php if ($hasActiveFilter): ?>
                        <a href="<?= rex_url::backendPage('issue_tracker/knowledge') ?>" class="btn btn-default" style="width: 100%;">
                            <i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_filter_reset') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between;">
                <span class="text-muted"><?= $package->i18n('issue_tracker_knowledge_count', (string) $totalCount) ?></span>
                <?php if ($canCreate): ?>
                <a href="<?= rex_url::backendPage('issue_tracker/issues/create') ?>" class="btn btn-success btn-sm">
                    <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_knowledge_new_entry') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
    <div class="panel panel-default">
        <div class="panel-body text-center text-muted" style="padding: 40px 20px;">
            <i class="rex-icon fa-book" style="font-size: 32px; opacity: 0.4;"></i>
            <p style="margin-top: 10px;">
                <?= $package->i18n($hasActiveFilter ? 'issue_tracker_knowledge_empty_filtered' : 'issue_tracker_knowledge_empty') ?>
            </p>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($grouped as $category => $articles): ?>
        <div class="issue-tracker-knowledge-group">
            <h4 class="issue-tracker-knowledge-group-title">
                <i class="rex-icon fa-folder-open"></i>
                <?= $category !== '' ? rex_escape($category) : $package->i18n('issue_tracker_knowledge_uncategorized') ?>
                <span class="badge"><?= count($articles) ?></span>
            </h4>
            <div class="row">
                <?php foreach ($articles as $article): ?>
                <div class="col-sm-6 col-md-4" style="margin-bottom: 20px;">
                    <div class="panel panel-default issue-tracker-knowledge-card">
                        <div class="panel-body">
                            <h5 class="issue-tracker-knowledge-card-title">
                                <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $article->getId()]) ?>">
                                    <?php if ($article->getIsPrivate()): ?><i class="rex-icon fa-lock" style="font-size: 11px;"></i> <?php endif; ?>
                                    <?= rex_escape($article->getTitle()) ?>
                                </a>
                            </h5>
                            <?php $excerpt = issueTrackerKnowledgeExcerpt($article->getDescription()); ?>
                            <?php if ($excerpt !== ''): ?>
                            <p class="text-muted issue-tracker-knowledge-card-excerpt"><?= rex_escape($excerpt) ?></p>
                            <?php endif; ?>
                            <?php $tags = $article->getTags(); ?>
                            <?php if (!empty($tags)): ?>
                            <div style="margin-bottom: 6px;">
                                <?php foreach ($tags as $tag): ?>
                                <span class="label" style="background-color: <?= rex_escape($tag->getColor()) ?>; margin-right: 3px;"><?= rex_escape($tag->getName()) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <small class="text-muted">
                                <i class="rex-icon fa-clock-o"></i>
                                <?= $package->i18n('issue_tracker_knowledge_updated') ?>
                                <?= ($article->getUpdatedAt() ?? $article->getCreatedAt())?->format('d.m.Y') ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
