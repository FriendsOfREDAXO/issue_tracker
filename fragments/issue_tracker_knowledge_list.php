<?php
/**
 * Wissen / Knowledge Base Fragment – gruppierte Liste mit Suchfeld (wie die Hilfe auf dem Mac)
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\ContentRenderer;
use FriendsOfREDAXO\IssueTracker\UiHelper;

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

$excerpt = static function (string $description, int $length = 160): string {
    $plain = strip_tags(rex_markdown::factory()->parse(ContentRenderer::stripSpentCommand($description)));
    $plain = trim((string) preg_replace('/\s+/', ' ', $plain));
    if ($plain === '') {
        return '';
    }
    return mb_strlen($plain) > $length ? mb_substr($plain, 0, $length) . '…' : $plain;
};
?>

<div class="issue-tracker-knowledge it-kb">
    <div class="it-hero">
        <div class="it-hero-main">
            <span class="it-icon it-icon--lg it-c-orange" aria-hidden="true"><i class="rex-icon fa-book"></i></span>
            <div>
                <h2><?= $package->i18n('issue_tracker_knowledge') ?></h2>
                <p><?= $package->i18n('issue_tracker_knowledge_intro') ?></p>
            </div>
        </div>
        <?php if ($canCreate): ?>
        <div class="it-hero-actions">
            <a href="<?= rex_url::backendPage('issue_tracker/issues/create') ?>" class="btn btn-primary it-btn-primary">
                <i class="rex-icon fa-plus" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_knowledge_new_entry') ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="it-toolbar">
        <form method="get" action="<?= rex_url::currentBackendPage() ?>" role="search">
            <input type="hidden" name="page" value="issue_tracker/knowledge" />
            <div class="it-search">
                <i class="rex-icon fa-search" aria-hidden="true"></i>
                <label class="it-sr" for="it-kb-search"><?= $package->i18n('issue_tracker_knowledge_search_placeholder') ?></label>
                <input type="search" id="it-kb-search" name="search" class="form-control" value="<?= rex_escape($searchTerm) ?>" placeholder="<?= $package->i18n('issue_tracker_knowledge_search_placeholder') ?>">
            </div>
            <label class="it-sr" for="it-kb-category"><?= $package->i18n('issue_tracker_category') ?></label>
            <select id="it-kb-category" name="filter_category" class="form-control it-select" data-it-autosubmit>
                <option value=""><?= $package->i18n('issue_tracker_ui_all_categories') ?></option>
                <?php foreach ($categories as $category): ?>
                <option value="<?= rex_escape($category) ?>" <?= $filterCategory === $category ? 'selected' : '' ?>><?= rex_escape($category) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($allTags)): ?>
            <label class="it-sr" for="it-kb-tag"><?= $package->i18n('issue_tracker_tags') ?></label>
            <select id="it-kb-tag" name="filter_tag" class="form-control it-select" data-it-autosubmit>
                <option value="0"><?= $package->i18n('issue_tracker_ui_all_tags') ?></option>
                <?php foreach ($allTags as $tag): ?>
                <option value="<?= $tag->getId() ?>" <?= $filterTag === $tag->getId() ? 'selected' : '' ?>><?= rex_escape($tag->getName()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($hasActiveFilter): ?>
            <a href="<?= rex_url::backendPage('issue_tracker/knowledge') ?>" class="btn btn-default">
                <i class="rex-icon fa-times" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_filter_reset') ?>
            </a>
            <?php endif; ?>
        </form>
        <div class="it-toolbar-end">
            <span class="it-toolbar-count"><?= $package->i18n('issue_tracker_knowledge_count', (string) $totalCount) ?></span>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
    <div class="it-group">
        <div class="it-empty">
            <i class="rex-icon fa-book" aria-hidden="true"></i>
            <?= $package->i18n($hasActiveFilter ? 'issue_tracker_knowledge_empty_filtered' : 'issue_tracker_knowledge_empty') ?>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($grouped as $category => $articles): ?>
        <h3 class="it-group-title">
            <?= $category !== '' ? rex_escape($category) : $package->i18n('issue_tracker_knowledge_uncategorized') ?>
            <span class="it-count">(<?= count($articles) ?>)</span>
        </h3>
        <div class="it-group">
            <ul class="it-list">
                <?php foreach ($articles as $article):
                    $text = $excerpt($article->getDescription());
                    $tags = $article->getTags();
                ?>
                <li>
                    <a class="it-item" href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $article->getId()]) ?>">
                        <span class="it-icon it-c-orange" aria-hidden="true"><i class="rex-icon <?= $article->getIsPrivate() ? 'fa-lock' : 'fa-file-text-o' ?>"></i></span>
                        <div class="it-item-body">
                            <div class="it-item-title">
                                <strong><?= rex_escape($article->getTitle()) ?></strong>
                                <span class="it-item-date"><?= $package->i18n('issue_tracker_knowledge_updated') ?> <?= ($article->getUpdatedAt() ?? $article->getCreatedAt())?->format('d.m.Y') ?></span>
                            </div>
                            <?php if ($text !== ''): ?>
                            <div class="it-kb-excerpt"><?= rex_escape($text) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($tags)): ?>
                            <div class="it-kb-tags">
                                <?php foreach ($tags as $tag): ?>
                                <span class="it-tag"><span class="it-dot" style="<?= UiHelper::colorVar($tag->getColor()) ?>"></span><?= rex_escape($tag->getName()) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
