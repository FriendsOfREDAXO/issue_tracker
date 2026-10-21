<?php
/**
 * Lesebereich eines Themas (wie Mail): Symbolleiste, Kopf, Inhalt, Unterhaltung, Antwort
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\Attachment;
use FriendsOfREDAXO\IssueTracker\ContentRenderer;
use FriendsOfREDAXO\IssueTracker\HistoryService;
use FriendsOfREDAXO\IssueTracker\PermissionService;
use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');

$issue = $this->getVar('issue');
$comments = $this->getVar('comments', []);
$attachments = $this->getVar('attachments', []);
$statuses = $this->getVar('statuses', []);
$priorities = $this->getVar('priorities', []);
$allUsers = $this->getVar('allUsers', []);
$allTags = $this->getVar('allTags', []);
$currentTags = $this->getVar('currentTags', []);
$currentTagIds = $this->getVar('currentTagIds', []);
$totalTimeMinutes = (int) $this->getVar('totalTimeMinutes', 0);
$history = $this->getVar('history', []);
$embedded = (bool) $this->getVar('embedded', false);
$selfUrl = (string) $this->getVar('selfUrl', rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue ? $issue->getId() : 0], false));
$quickToken = $this->getVar('quickToken');
$canChangeStatus = (bool) $this->getVar('canChangeStatus', false);
$isWatching = (bool) $this->getVar('isWatching', false);
$watcherCount = (int) $this->getVar('watcherCount', 0);
$watcherUsers = $this->getVar('watcherUsers', []);
$availableUsers = $this->getVar('availableWatcherUsers', []);
$canSendReminder = (bool) $this->getVar('canSendReminder', false);
$lastReminderAt = $this->getVar('lastReminderAt', null);

if (!$issue) {
    throw new \RuntimeException('Issue object is required');
}

$currentUser = rex::getUser();
$uid = (int) $currentUser->getId();
$createdBy = $issue->getCreator();
$assignedUser = $issue->getAssignedUser();
$tags = $currentTags ?: $issue->getTags();
$project = $issue->getProject();
$status = $issue->getStatus();
$isDone = in_array($status, ['closed', 'rejected'], true);

$canEdit = $currentUser->isAdmin() || $issue->getCreatedBy() === $uid;
$canQuickEdit = PermissionService::canEdit($issue);
$canEditTags = $currentUser->isAdmin() || $canQuickEdit;
$canManageWatchers = $currentUser->isAdmin() || $currentUser->hasPerm('issue_tracker[issue_manager]') || $issue->getCreatedBy() === $uid;
$canViewHistory = PermissionService::canViewHistory();
$canModerate = $currentUser->isAdmin() || $issue->getCreatedBy() === $uid;

$selfAction = rex_escape($selfUrl);
$mode = (string) $this->getVar('mode', 'issues');
$isKnowledge = $mode === 'knowledge';
// Zusatzbereiche (Beobachter, Verlauf …) sind standardmäßig zu; Zustand pro Benutzer gemerkt
$uiState = rex_config::get('issue_tracker', 'sidebar_state_' . (int) rex::getUser()->getId(), []);
$uiOpen = static fn (string $key): string => is_array($uiState) && !empty($uiState[$key]) ? ' open' : '';
$viewUrl = rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issue->getId()], false);
$listUrl = rex_url::backendPage('issue_tracker/issues/list', ['screen' => 'list']);

// Checklisten im Lesebereich an die aktuelle Seite zurücksenden
$render = static function (string $text, ?int $commentId = null) use ($issue, $embedded, $viewUrl, $selfUrl): string {
    $html = ContentRenderer::render($text, $issue->getId(), $commentId);
    return $embedded ? str_replace(rex_escape($viewUrl), rex_escape($selfUrl), $html) : $html;
};

$creatorName = UiHelper::userName($createdBy, '–');
$assigneeName = $issue->getAssignedUserId() === null ? $package->i18n('issue_tracker_assigned_all_users') : UiHelper::userName($assignedUser, '–');
$colorVar = static fn (string $c): string => preg_match('/^#[0-9a-f]{3,8}$/i', $c) ? '--c:' . $c : '--c:#7c7c82';

/** @param Attachment[] $list */
$renderAttachments = static function (array $list, string $group) use ($package): string {
    if ($list === []) {
        return '';
    }
    $out = '<ul class="it-att-list" data-lightbox-group="' . rex_escape($group) . '">';
    foreach ($list as $att) {
        $name = rex_escape($att->getOriginalFilename());
        $url = $att->getUrl();
        $type = $att->isImage() ? 'image' : ($att->isVideo() ? 'video' : ($att->isPdf() ? 'pdf' : ''));
        $thumb = $att->isImage() && $att->getThumbnailUrl()
            ? '<img class="it-att-thumb" src="' . $att->getThumbnailUrl() . '" alt="">'
            : '<span class="it-att-icon"><i class="rex-icon ' . rex_escape($att->getFileIcon()) . '" aria-hidden="true"></i></span>';
        $out .= '<li class="it-att">';
        if ('' !== $type) {
            $out .= '<a class="it-att-open issue-attachment-lightbox" data-type="' . $type . '" href="' . $url . '" title="' . $name . '">';
        } else {
            $out .= '<a class="it-att-open" href="' . $url . '" target="_blank" title="' . $name . '">';
        }
        $out .= $thumb . '<span class="it-att-text"><span class="it-att-name">' . $name . '</span><span class="it-att-size">' . rex_escape($att->getFormattedFilesize()) . '</span></span></a>';
        $out .= '<a class="it-att-dl" href="' . $url . '" target="_blank" title="' . rex_escape($package->i18n('issue_tracker_download')) . '"><i class="rex-icon fa-download" aria-hidden="true"></i><span class="sr-only">' . rex_escape($package->i18n('issue_tracker_download')) . ': ' . $name . '</span></a>';
        $out .= '</li>';
    }
    return $out . '</ul>';
};

/**
 * Eine Nachricht der Unterhaltung (Kommentar oder Antwort).
 */
$renderComment = static function ($comment, bool $isReply) use (&$renderComment, $package, $issue, $currentUser, $canModerate, $selfAction, $render, $renderAttachments): string {
    $cid = (int) $comment->getId();
    $user = $comment->getCreator();
    $name = UiHelper::userName($user, 'Unknown');
    $canEditComment = $currentUser->isAdmin() || $comment->getCreatedBy() === (int) $currentUser->getId();
    $classes = 'it-msg' . ($isReply ? ' it-msg--reply' : '') . ($comment->isSolution() ? ' is-solution' : ($comment->isPinned() ? ' is-pinned' : ''));

    ob_start(); ?>
    <article class="<?= $classes ?>" id="comment-<?= $cid ?>">
        <header class="it-msg-head">
            <?= UiHelper::avatar((int) $comment->getCreatedBy(), $name, $isReply) ?>
            <div class="it-msg-who">
                <strong class="it-msg-name"><?= rex_escape($name) ?></strong>
                <?php if ($comment->isSolution()): ?>
                    <span class="it-badge it-badge--ok"><i class="rex-icon fa-check-circle" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_solution') ?></span>
                <?php elseif ($comment->isPinned()): ?>
                    <span class="it-badge it-badge--info"><i class="rex-icon fa-thumb-tack" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_pinned') ?></span>
                <?php endif; ?>
                <span class="it-msg-date">
                    <?= $comment->getCreatedAt() ? $comment->getCreatedAt()->format('d.m.Y H:i') : '-' ?>
                    <?php if ($comment->getUpdatedAt()): ?> · <?= $package->i18n('issue_tracker_edited') ?> <?= $comment->getUpdatedAt()->format('d.m.Y H:i') ?><?php endif; ?>
                </span>
            </div>
            <div class="it-msg-actions">
                <?php if (!$isReply): ?>
                <button type="button" class="it-tb-btn it-tb-btn--sm" onclick="toggleReplyForm(<?= $cid ?>)" title="<?= rex_escape($package->i18n('issue_tracker_reply')) ?>"><i class="rex-icon fa-reply" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_reply') ?></span></button>
                <?php endif; ?>
                <?php if (!$isReply && $canModerate): ?>
                <form method="post" action="<?= $selfAction ?>">
                    <input type="hidden" name="toggle_pin" value="<?= $cid ?>" />
                    <button type="submit" class="it-tb-btn it-tb-btn--sm<?= $comment->isPinned() ? ' is-on' : '' ?>" title="<?= rex_escape($package->i18n($comment->isPinned() ? 'issue_tracker_mail_unpin' : 'issue_tracker_mail_pin')) ?>"><i class="rex-icon fa-thumb-tack" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n($comment->isPinned() ? 'issue_tracker_mail_unpin' : 'issue_tracker_mail_pin') ?></span></button>
                </form>
                <form method="post" action="<?= $selfAction ?>">
                    <input type="hidden" name="toggle_solution" value="<?= $cid ?>" />
                    <button type="submit" class="it-tb-btn it-tb-btn--sm<?= $comment->isSolution() ? ' is-on' : '' ?>" title="<?= rex_escape($package->i18n($comment->isSolution() ? 'issue_tracker_mail_unmark_solution' : 'issue_tracker_mail_mark_solution')) ?>"><i class="rex-icon fa-check-circle" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n($comment->isSolution() ? 'issue_tracker_mail_unmark_solution' : 'issue_tracker_mail_mark_solution') ?></span></button>
                </form>
                <?php endif; ?>
                <?php if (!$isReply && $canEditComment): ?>
                <button type="button" class="it-tb-btn it-tb-btn--sm" onclick="toggleEditForm(<?= $cid ?>)" title="<?= rex_escape($package->i18n('issue_tracker_edit')) ?>"><i class="rex-icon fa-pencil" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_edit') ?></span></button>
                <?php endif; ?>
                <?php if (!$isReply && $currentUser->isAdmin()): ?>
                <form method="post" action="<?= $selfAction ?>" onsubmit="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_delete_comment_confirm')) ?>">
                    <input type="hidden" name="delete_comment" value="<?= $cid ?>" />
                    <button type="submit" class="it-tb-btn it-tb-btn--sm it-tb-btn--danger" title="<?= rex_escape($package->i18n('issue_tracker_delete')) ?>"><i class="rex-icon fa-trash" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_delete') ?></span></button>
                </form>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!$isReply && $canEditComment): ?>
        <div class="it-msg-edit" id="edit-form-<?= $cid ?>" hidden>
            <form method="post" action="<?= $selfAction ?>">
                <input type="hidden" name="edit_comment" value="<?= $cid ?>" />
                <textarea name="comment_text" class="form-control" rows="4" aria-label="<?= rex_escape($package->i18n('issue_tracker_edit')) ?>"><?= rex_escape($comment->getComment()) ?></textarea>
                <div class="it-form-actions">
                    <button type="button" class="btn btn-default btn-sm" onclick="toggleEditForm(<?= $cid ?>)"><?= $package->i18n('issue_tracker_cancel') ?></button>
                    <button type="submit" class="btn btn-primary it-btn-primary btn-sm"><?= $package->i18n('issue_tracker_save') ?></button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="it-msg-body issue-tracker-comment-content" id="comment-content-<?= $cid ?>"><?= $render($comment->getComment(), $cid) ?></div>
        <?= $renderAttachments(Attachment::getByComment($cid), 'comment-' . $cid) ?>

        <?php if (!$isReply):
            $replies = $comment->getReplies(); ?>
        <div id="reply-form-<?= $cid ?>" class="it-msg-replyform" hidden>
            <form method="post" action="<?= $selfAction ?>">
                <input type="hidden" name="add_comment" value="1" />
                <input type="hidden" name="parent_comment_id" value="<?= $cid ?>" />
                <textarea name="comment" class="form-control" rows="3" placeholder="<?= rex_escape($package->i18n('issue_tracker_reply_placeholder')) ?>" aria-label="<?= rex_escape($package->i18n('issue_tracker_reply')) ?>"></textarea>
                <div class="it-form-actions">
                    <button type="button" class="btn btn-default btn-sm" onclick="toggleReplyForm(<?= $cid ?>)"><?= $package->i18n('issue_tracker_cancel') ?></button>
                    <button type="submit" class="btn btn-primary it-btn-primary btn-sm"><i class="rex-icon fa-reply" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_send_reply') ?></button>
                </div>
            </form>
        </div>
        <?php if (!empty($replies)): ?>
        <div class="it-msg-replies">
            <?php foreach ($replies as $reply) { echo $renderComment($reply, true); } ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </article>
    <?php
    return (string) ob_get_clean();
};

$topLevelComments = array_filter($comments, static fn ($c) => $c->getParentCommentId() === null);
$pinnedComments = array_filter($comments, static fn ($c) => $c->isPinned() || $c->isSolution());
$clProgress = ContentRenderer::getChecklistProgress($issue->getDescription());
$relatedTo = $issue->getDuplicateOf();
$relatedIssue = $relatedTo !== null ? $issue->getDuplicateIssue() : null;
$relatedIssues = $issue->getDuplicates();
?>
<?php $itTbLabels = ['bold' => $package->i18n('issue_tracker_mail_tb_bold'), 'italic' => $package->i18n('issue_tracker_mail_tb_italic'), 'unordered-list' => $package->i18n('issue_tracker_mail_tb_list'), 'ordered-list' => $package->i18n('issue_tracker_mail_tb_olist'), 'link' => $package->i18n('issue_tracker_mail_tb_link'), 'image' => $package->i18n('issue_tracker_mail_tb_image'), 'preview' => $package->i18n('issue_tracker_mail_tb_preview'), 'rex-link' => $package->i18n('issue_tracker_mail_tb_page')]; ?>
<article class="it-rp issue-tracker-view" data-issue-id="<?= $issue->getId() ?>" data-it-tb-labels="<?= rex_escape(json_encode($itTbLabels)) ?>">
    <div class="it-rp-toolbar" role="toolbar" aria-label="<?= rex_escape($package->i18n('issue_tracker_actions')) ?>">
        <div class="it-tb-group">
            <?php if ($embedded): ?>
            <a class="it-tb-back" href="<?= $listUrl ?>" data-it-back><i class="rex-icon fa-angle-left" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_back_to_issues') ?></a>
            <?php else: ?>
            <a class="it-tb-back it-tb-back--always" href="<?= $listUrl ?>"><i class="rex-icon fa-angle-left" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_back_to_list') ?></a>
            <?php endif; ?>
            <?php if (!$isKnowledge): ?>
            <a class="it-tb-btn" href="#it-reply" data-it-reply aria-keyshortcuts="R" title="<?= rex_escape($package->i18n('issue_tracker_mail_reply_title') . ' (R)') ?>"><i class="rex-icon fa-reply" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_reply_title') ?></span></a>

            <?php if ($canChangeStatus): ?>
            <details class="it-menu">
                <summary class="it-tb-btn" title="<?= rex_escape($package->i18n('issue_tracker_change_status')) ?>"><i class="rex-icon fa-exchange" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_change_status') ?></span></summary>
                <form class="it-menu-pop" method="post" action="<?= $selfAction ?>">
                    <input type="hidden" name="change_status" value="1" />
                    <div class="it-menu-label"><?= $package->i18n('issue_tracker_status') ?></div>
                    <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                    <button type="submit" name="status" value="<?= rex_escape($statusKey) ?>" class="it-menu-item<?= $status === $statusKey ? ' is-checked' : '' ?>">
                        <span class="it-dot it-dot--<?= rex_escape($statusKey) ?>" aria-hidden="true"></span> <?= $package->i18n('issue_tracker_status_' . $statusKey) ?>
                    </button>
                    <?php endforeach; ?>
                </form>
            </details>
            <?php endif; ?>

            <?php if ($canQuickEdit && $quickToken): ?>
            <details class="it-menu">
                <summary class="it-tb-btn" title="<?= rex_escape($package->i18n('issue_tracker_mail_assign')) ?>"><i class="rex-icon fa-user-plus" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_assign') ?></span></summary>
                <form class="it-menu-pop it-menu-pop--scroll" method="post" action="<?= $selfAction ?>">
                    <?= $quickToken->getHiddenField() ?>
                    <input type="hidden" name="change_assignee" value="1" />
                    <div class="it-menu-label"><?= $package->i18n('issue_tracker_assigned') ?></div>
                    <button type="submit" name="assigned_user_id" value="all" class="it-menu-item<?= $issue->getAssignedUserId() === null ? ' is-checked' : '' ?>"><i class="rex-icon fa-users" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_assigned_all_users') ?></button>
                    <?php foreach ($allUsers as $aUid => $aUser): ?>
                    <button type="submit" name="assigned_user_id" value="<?= (int) $aUid ?>" class="it-menu-item<?= $issue->getAssignedUserId() === (int) $aUid ? ' is-checked' : '' ?>"><?= UiHelper::avatar((int) $aUid, (string) $aUser['name'], true) ?> <?= rex_escape((string) $aUser['name']) ?></button>
                    <?php endforeach; ?>
                </form>
            </details>
            <details class="it-menu">
                <summary class="it-tb-btn" title="<?= rex_escape($package->i18n('issue_tracker_priority')) ?>"><i class="rex-icon fa-flag" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_priority') ?></span></summary>
                <form class="it-menu-pop" method="post" action="<?= $selfAction ?>">
                    <?= $quickToken->getHiddenField() ?>
                    <input type="hidden" name="change_priority" value="1" />
                    <div class="it-menu-label"><?= $package->i18n('issue_tracker_priority') ?></div>
                    <?php foreach ($priorities as $prioKey => $prioLabel): ?>
                    <button type="submit" name="priority" value="<?= rex_escape($prioKey) ?>" class="it-menu-item<?= $issue->getPriority() === $prioKey ? ' is-checked' : '' ?>"><i class="rex-icon fa-flag it-prio-<?= rex_escape($prioKey) ?>" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_priority_' . $prioKey) ?></button>
                    <?php endforeach; ?>
                </form>
            </details>
            <?php endif; ?>

            <form method="post" action="<?= $selfAction ?>" class="it-tb-form">
                <input type="hidden" name="toggle_watch" value="1" />
                <button type="submit" class="it-tb-btn<?= $isWatching ? ' is-on' : '' ?>" title="<?= rex_escape($package->i18n($isWatching ? 'issue_tracker_unwatch' : 'issue_tracker_watch')) ?>" aria-pressed="<?= $isWatching ? 'true' : 'false' ?>"><i class="rex-icon <?= $isWatching ? 'fa-eye-slash' : 'fa-eye' ?>" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n($isWatching ? 'issue_tracker_unwatch' : 'issue_tracker_watch') ?></span></button>
            </form>
            <?php endif; ?>
        </div>

        <div class="it-tb-group">
            <?php if ($canEdit): ?>
            <a class="it-tb-btn" data-it-edit href="<?= rex_url::backendPage('issue_tracker/issues/edit', ['issue_id' => $issue->getId()]) ?>"<?= $isKnowledge ? ' aria-keyshortcuts="E"' : '' ?> title="<?= rex_escape($package->i18n('issue_tracker_edit') . ($isKnowledge ? ' (E)' : '')) ?>"><i class="rex-icon fa-pencil" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_edit') ?></span></a>
            <?php endif; ?>
            <?php if ($canChangeStatus && $isKnowledge): ?>
            <form method="post" action="<?= $selfAction ?>" class="it-tb-form" onsubmit="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_mail_knowledge_remove_confirm')) ?>">
                <input type="hidden" name="change_status" value="1" />
                <input type="hidden" name="status" value="closed" />
                <button type="submit" class="it-tb-btn it-tb-btn--text" title="<?= rex_escape($package->i18n('issue_tracker_mail_knowledge_remove_help')) ?>"><i class="rex-icon fa-book" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_knowledge_remove') ?></button>
            </form>
            <?php elseif ($canChangeStatus): ?>
            <form method="post" action="<?= $selfAction ?>" class="it-tb-form">
                <input type="hidden" name="change_status" value="1" />
                <?php if ($status !== 'closed'): ?>
                <input type="hidden" name="status" value="closed" />
                <button type="submit" class="it-tb-btn" title="<?= rex_escape($package->i18n('issue_tracker_mail_close_issue')) ?>"><i class="rex-icon fa-archive" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_close_issue') ?></span></button>
                <?php else: ?>
                <input type="hidden" name="status" value="open" />
                <button type="submit" class="it-tb-btn" title="<?= rex_escape($package->i18n('issue_tracker_mail_reopen')) ?>"><i class="rex-icon fa-undo" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_reopen') ?></span></button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
            <?php if (PermissionService::canDelete() && !$isKnowledge): ?>
            <a class="it-tb-btn it-tb-btn--danger" href="<?= rex_url::backendPage('issue_tracker/issues/list', ['func' => 'delete', 'issue_id' => $issue->getId()] + \FriendsOfREDAXO\IssueTracker\UiHelper::csrf()->getUrlParams()) ?>"
               onclick="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_delete_confirm')) ?>"
               title="<?= rex_escape($package->i18n('issue_tracker_delete')) ?>"><i class="rex-icon fa-trash" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_delete') ?></span></a>
            <?php endif; ?>
        </div>

        <div class="it-tb-group it-tb-group--end">
            <a class="it-tb-btn" href="<?= rex_escape($viewUrl) ?>" data-it-copy data-copied="<?= rex_escape($package->i18n('issue_tracker_mail_link_copied')) ?>" title="<?= rex_escape($package->i18n('issue_tracker_mail_copy_link')) ?>"><i class="rex-icon fa-link" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_copy_link') ?></span></a>
            <?php if ($embedded): ?>
            <a class="it-tb-btn" href="<?= rex_escape($viewUrl) ?>" title="<?= rex_escape($package->i18n('issue_tracker_mail_open_full')) ?>"><i class="rex-icon fa-expand" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_mail_open_full') ?></span></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="it-rp-scroll">
        <header class="it-rp-head">
            <?= UiHelper::avatar((int) $issue->getCreatedBy(), $creatorName) ?>
            <div class="it-rp-headmain">
                <div class="it-rp-fromline">
                    <span><span class="it-rp-label"><?= $package->i18n('issue_tracker_from') ?>:</span> <strong><?= rex_escape($creatorName) ?></strong></span>
                    <time class="it-rp-date" datetime="<?= $issue->getCreatedAt() ? $issue->getCreatedAt()->format('c') : '' ?>"><?= $issue->getCreatedAt() ? $issue->getCreatedAt()->format('d.m.Y H:i') : '' ?></time>
                </div>
                <div class="it-rp-toline"><span class="it-rp-label"><?= $package->i18n('issue_tracker_to') ?>:</span> <?= rex_escape($assigneeName) ?></div>
                <h2 class="it-rp-subject"><span class="it-rp-id">#<?= $issue->getId() ?></span> <?= rex_escape($issue->getTitle()) ?></h2>
                <div class="it-rp-badges">
                    <?= UiHelper::statusBadge($status) ?>
                    <?= UiHelper::badge($package->i18n('issue_tracker_priority_' . $issue->getPriority()), UiHelper::priorityVariant($issue->getPriority())) ?>
                    <?php if ($project): ?>
                    <a class="it-chip it-chip--link" href="<?= rex_url::backendPage('issue_tracker/issues/list', ['filter_status' => '', 'filter_project' => $project->getId()]) ?>"><span class="it-dot it-dot--custom it-dot--sm" style="<?= rex_escape($colorVar($project->getColor())) ?>" aria-hidden="true"></span><?php if ($project->getIsPrivate()): ?><i class="rex-icon fa-lock" aria-hidden="true"></i> <?php endif; ?><?= rex_escape($project->getName()) ?></a>
                    <?php endif; ?>
                    <?php if ($issue->getCategory() !== ''): ?>
                    <span class="it-chip"><i class="rex-icon fa-folder-o" aria-hidden="true"></i> <?= rex_escape($issue->getCategory()) ?></span>
                    <?php endif; ?>
                    <?php
                    $chipArticle = $issue->getArticleId() ? $issue->getAffectedArticle() : null;
                    if ($chipArticle): ?>
                    <a class="it-chip it-chip--link" href="#it-affected" title="<?= rex_escape($package->i18n('issue_tracker_affected_page')) ?>"><i class="rex-icon fa-file-text-o" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_affected_page') ?>:</span> <?= rex_escape($chipArticle->getName()) ?></a>
                    <?php elseif ($issue->getAffectedUrl()): ?>
                    <a class="it-chip it-chip--link" href="#it-affected" title="<?= rex_escape($package->i18n('issue_tracker_affected_page')) ?>"><i class="rex-icon fa-globe" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_affected_page') ?>:</span> <?= rex_escape(mb_strimwidth((string) preg_replace('#^https?://#i', '', (string) $issue->getAffectedUrl()), 0, 40, '…')) ?></a>
                    <?php endif; ?>
                    <?php if ($issue->getIsPrivate()): ?>
                    <span class="it-badge it-badge--warn"><i class="rex-icon fa-lock" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_private') ?></span>
                    <?php endif; ?>
                    <?php if ($issue->getDueDate()): ?>
                    <span class="it-badge<?= $issue->isOverdue() ? ' it-badge--danger' : '' ?>" title="<?= rex_escape($package->i18n('issue_tracker_due_date')) ?>"><i class="rex-icon fa-calendar" aria-hidden="true"></i> <?= $issue->getDueDate()->format('d.m.Y H:i') ?></span>
                    <?php endif; ?>
                    <?php foreach ($tags as $tag): ?>
                    <span class="it-chip"><span class="it-dot it-dot--custom it-dot--sm" style="<?= rex_escape($colorVar($tag->getColor())) ?>" aria-hidden="true"></span><?= rex_escape($tag->getName()) ?><?php if ($canEditTags): ?><form method="post" action="<?= $selfAction ?>" class="it-chip-form"><input type="hidden" name="remove_tag" value="<?= $tag->getId() ?>" /><button type="submit" class="it-chip-x" title="<?= rex_escape($package->i18n('issue_tracker_tag_remove')) ?>"><span aria-hidden="true">&times;</span><span class="sr-only"><?= $package->i18n('issue_tracker_tag_remove') ?>: <?= rex_escape($tag->getName()) ?></span></button></form><?php endif; ?></span>
                    <?php endforeach; ?>
                    <?php if ($canEditTags):
                        $addableTags = array_filter($allTags, static fn ($t) => !in_array($t->getId(), $currentTagIds, true));
                        if (!empty($addableTags)): ?>
                    <form method="post" action="<?= $selfAction ?>" class="it-chip-form">
                        <select name="add_tag" onchange="this.form.submit()" class="it-chip-select" aria-label="<?= rex_escape($package->i18n('issue_tracker_tags')) ?>">
                            <option value="">+ Tag</option>
                            <?php foreach ($addableTags as $aTag): ?>
                            <option value="<?= $aTag->getId() ?>"><?= rex_escape($aTag->getName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="it-chip-x">+</button></noscript>
                    </form>
                    <?php endif; endif; ?>
                </div>
            </div>
        </header>

        <?php if ($relatedTo !== null && $relatedIssue): ?>
        <div class="it-rp-note">
            <i class="rex-icon fa-link" aria-hidden="true"></i>
            <?= $package->i18n('issue_tracker_related_to') ?>:
            <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $relatedIssue->getId()]) ?>">#<?= $relatedIssue->getId() ?> – <?= rex_escape($relatedIssue->getTitle()) ?></a>
        </div>
        <?php endif; ?>

        <div class="it-rp-body" id="description">
            <?php if ($clProgress['total'] > 0): ?>
            <div class="it-rp-progress">
                <span><i class="rex-icon fa-check-square-o" aria-hidden="true"></i> <?= $clProgress['checked'] ?>/<?= $clProgress['total'] ?> <?= $package->i18n('issue_tracker_checklist_items') ?></span>
                <progress class="it-progress" max="<?= (int) $clProgress['total'] ?>" value="<?= (int) $clProgress['checked'] ?>"></progress>
            </div>
            <?php endif; ?>
            <?= $render($issue->getDescription()) ?>
        </div>

        <?= $renderAttachments($attachments, 'issue-' . $issue->getId()) ?>

        <dl class="it-rp-facts">
            <?php
            // Betroffene Seite: Artikel aus der Struktur (Ansehen/Bearbeiten) oder freie Adresse
            $affectedArticleId = $issue->getArticleId();
            $affectedUrl = $issue->getAffectedUrl();
            if ($affectedArticleId || $affectedUrl):
                $affectedArticle = $affectedArticleId ? $issue->getAffectedArticle() : null;
            ?>
            <div class="it-rp-fact--wide" id="it-affected"><dt><?= $package->i18n('issue_tracker_affected_page') ?></dt><dd class="it-affected">
                <?php if ($affectedArticleId && $affectedArticle):
                    $affClang = $affectedArticle->getClangId();
                    $affCanEdit = $currentUser->getComplexPerm('clang')->hasPerm($affClang)
                        && $currentUser->getComplexPerm('structure')->hasCategoryPerm($affectedArticle->getCategoryId());
                ?>
                <span class="it-affected-name"><i class="rex-icon fa-file-text-o" aria-hidden="true"></i> <?= rex_escape($affectedArticle->getName()) ?> <span class="it-muted-text">[<?= (int) $affectedArticleId ?>]<?= rex_clang::count() > 1 && rex_clang::get($affClang) ? ' · ' . rex_escape(rex_clang::get($affClang)->getName()) : '' ?></span></span>
                <a class="it-affected-link" href="<?= rex_escape(rex_getUrl($affectedArticleId, $affClang)) ?>" target="_blank" rel="noopener"><i class="rex-icon fa-external-link" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_affected_view') ?></a>
                <?php if ($affCanEdit): ?>
                <a class="it-affected-link" href="<?= rex_url::backendPage('content/edit', ['article_id' => $affectedArticleId, 'clang' => $affClang, 'mode' => 'edit']) ?>"><i class="rex-icon fa-pencil" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_affected_edit') ?></a>
                <?php endif; ?>
                <?php elseif ($affectedArticleId): ?>
                <span class="it-muted-text"><?= $package->i18n('issue_tracker_affected_missing', (int) $affectedArticleId) ?></span>
                <?php endif; ?>
                <?php if ($affectedUrl && !\FriendsOfREDAXO\IssueTracker\Issue::isValidAffectedUrl($affectedUrl)): ?>
                <span class="it-affected-url"><?= rex_escape($affectedUrl) ?></span>
                <?php elseif ($affectedUrl): ?>
                <a class="it-affected-link it-affected-url" href="<?= rex_escape($affectedUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="rex-icon fa-external-link" aria-hidden="true"></i> <?= rex_escape($affectedUrl) ?></a>
                <?php endif; ?>
            </dd></div>
            <?php endif; ?>
            <?php if ($issue->getAssignedAddon()): ?>
            <div><dt><?= $package->i18n('issue_tracker_addon') ?></dt><dd><?= rex_escape($issue->getAssignedAddon()) ?></dd></div>
            <?php endif; ?>
            <?php if ($issue->getVersion()): ?>
            <div><dt><?= $package->i18n('issue_tracker_version') ?></dt><dd><?= rex_escape($issue->getVersion()) ?></dd></div>
            <?php endif; ?>
            <?php if ($issue->getUpdatedAt()): ?>
            <div><dt><?= $package->i18n('issue_tracker_updated_at') ?></dt><dd><?= $issue->getUpdatedAt()->format('d.m.Y H:i') ?></dd></div>
            <?php endif; ?>
            <?php if ($issue->getClosedAt()): ?>
            <div><dt><?= $package->i18n('issue_tracker_closed') ?></dt><dd><?= $issue->getClosedAt()->format('d.m.Y H:i') ?></dd></div>
            <?php endif; ?>
            <?php if ($totalTimeMinutes > 0): ?>
            <div><dt><?= $package->i18n('issue_tracker_time_spent') ?></dt><dd><?= rex_escape(ContentRenderer::formatMinutes($totalTimeMinutes)) ?></dd></div>
            <?php endif; ?>
            <?php
            $domainIds = $issue->getDomainIds();
            if (!empty($domainIds) && rex_addon::exists('yrewrite') && rex_addon::get('yrewrite')->isAvailable()):
                $domainNames = [];
                foreach (rex_yrewrite::getDomains() as $domainName => $domain) {
                    $domainId = method_exists($domain, 'getId') ? (int) $domain->getId() : null;
                    if ($domainId !== null && in_array($domainId, $domainIds, true)) {
                        $domainNames[] = rex_escape($domainName);
                    }
                }
            ?>
            <div><dt><?= $package->i18n('issue_tracker_domain') ?></dt><dd><?= implode(', ', $domainNames) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($issue->getYformTables())): ?>
            <div><dt><?= $package->i18n('issue_tracker_yform_table') ?></dt><dd><?= rex_escape(implode(', ', $issue->getYformTables())) ?></dd></div>
            <?php endif; ?>
            <?php if ($relatedTo !== null && !$relatedIssue): ?>
            <div><dt><?= $package->i18n('issue_tracker_related_to') ?></dt><dd>#<?= (int) $relatedTo ?> (<?= $package->i18n('issue_tracker_related_not_found') ?>)</dd></div>
            <?php endif; ?>
            <?php if (!empty($relatedIssues)): ?>
            <div><dt><?= $package->i18n('issue_tracker_related_issues') ?></dt><dd>
                <?php foreach ($relatedIssues as $related): ?>
                <a href="<?= rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $related->getId()]) ?>">#<?= $related->getId() ?> – <?= rex_escape($related->getTitle()) ?></a><br>
                <?php endforeach; ?>
            </dd></div>
            <?php endif; ?>
        </dl>

        <div class="it-rp-extras">
            <details class="it-disclosure" data-it-sec="pane_watchers"<?= $uiOpen('pane_watchers') ?>>
                <summary><i class="rex-icon fa-eye" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_watchers') ?> <span class="it-disclosure-count"><?= $watcherCount ?></span></summary>
                <div class="it-disclosure-body">
                    <?php if ($watcherCount > 0): ?>
                    <ul class="it-people">
                        <?php foreach ($watcherUsers as $watcher): ?>
                        <li>
                            <?= UiHelper::avatar((int) $watcher['id'], (string) $watcher['name'], true) ?>
                            <span><?= rex_escape($watcher['name']) ?></span>
                            <?php if ($canManageWatchers && (int) $watcher['id'] !== $uid): ?>
                            <form method="post" action="<?= $selfAction ?>">
                                <input type="hidden" name="remove_watcher" value="<?= (int) $watcher['id'] ?>" />
                                <button type="submit" class="it-tb-btn it-tb-btn--sm" title="<?= rex_escape($package->i18n('issue_tracker_watcher_remove')) ?>"><i class="rex-icon fa-times" aria-hidden="true"></i><span class="sr-only"><?= $package->i18n('issue_tracker_watcher_remove') ?>: <?= rex_escape($watcher['name']) ?></span></button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <?php if ($canManageWatchers && !empty($availableUsers)): ?>
                    <form method="post" action="<?= $selfAction ?>" class="it-inline-form">
                        <input type="hidden" name="add_watchers" value="1" />
                        <select name="watcher_user_ids[]" class="form-control selectpicker" multiple="multiple" data-live-search="true" data-actions-box="true"
                                data-selected-text-format="count > 2" data-size="8" data-width="100%" title="<?= rex_escape($package->i18n('issue_tracker_watcher_add')) ?>" aria-label="<?= rex_escape($package->i18n('issue_tracker_watcher_add')) ?>">
                            <?php foreach ($availableUsers as $wUid => $wName): ?>
                            <option value="<?= (int) $wUid ?>"><?= rex_escape($wName) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-default btn-sm"><i class="rex-icon fa-user-plus" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_watcher_invite') ?></button>
                    </form>
                    <?php endif; ?>
                </div>
            </details>

            <?php if ($canChangeStatus && $assignedUser && !$isDone): ?>
            <details class="it-disclosure" data-it-sec="pane_reminder"<?= $uiOpen('pane_reminder') ?>>
                <summary><i class="rex-icon fa-bell" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_reminder_send') ?></summary>
                <div class="it-disclosure-body">
                    <form method="post" action="<?= $selfAction ?>" onsubmit="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= rex_escape($package->i18n('issue_tracker_reminder_confirm')) ?>" class="it-inline-form">
                        <input type="hidden" name="send_reminder" value="1" />
                        <button type="submit" class="btn btn-default btn-sm" <?= $canSendReminder ? '' : 'disabled' ?>><i class="rex-icon fa-bell" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_reminder_send') ?></button>
                        <?php if ($lastReminderAt): ?>
                        <small class="it-muted-text"><?= $package->i18n('issue_tracker_reminder_last_sent', (new DateTime($lastReminderAt))->format('d.m.Y H:i')) ?></small>
                        <?php endif; ?>
                    </form>
                </div>
            </details>
            <?php endif; ?>

            <?php if ($currentUser->isAdmin()): ?>
            <details class="it-disclosure" data-it-sec="pane_related"<?= $uiOpen('pane_related') ?>>
                <summary><i class="rex-icon fa-link" aria-hidden="true"></i> <?= $package->i18n($relatedTo === null ? 'issue_tracker_mark_as_related' : 'issue_tracker_related_info') ?></summary>
                <div class="it-disclosure-body">
                    <?php if ($relatedTo === null): ?>
                    <form method="post" action="<?= $selfAction ?>" class="it-inline-form">
                        <input type="hidden" name="func" value="mark_related" />
                        <p class="it-muted-text"><?= $package->i18n('issue_tracker_related_help_text') ?></p>
                        <select name="related_to" class="form-control selectpicker" data-live-search="true" data-width="100%" data-size="10" required aria-label="<?= rex_escape($package->i18n('issue_tracker_select_related_issue')) ?>">
                            <option value="">-- <?= $package->i18n('issue_tracker_select_open_issue') ?> --</option>
                            <?php
                            $sqlIssues = rex_sql::factory();
                            $sqlIssues->setQuery('SELECT id, title, category, status FROM ' . rex::getTable('issue_tracker_issues') . ' WHERE id != ? AND status IN ("open", "in_progress", "planned") ORDER BY id DESC LIMIT 100', [$issue->getId()]);
                            foreach ($sqlIssues as $issueOption) {
                                echo '<option value="' . (int) $issueOption->getValue('id') . '">#' . (int) $issueOption->getValue('id') . ' - ' . rex_escape($issueOption->getValue('title'))
                                    . ' (' . rex_escape($package->i18n('issue_tracker_status_' . $issueOption->getValue('status'))) . ', ' . rex_escape($issueOption->getValue('category')) . ')</option>';
                            }
                            ?>
                        </select>
                        <button type="submit" class="btn btn-default btn-sm"><i class="rex-icon fa-link" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_link_action') ?></button>
                    </form>
                    <?php else: ?>
                    <form method="post" action="<?= $selfAction ?>" class="it-inline-form">
                        <input type="hidden" name="func" value="unmark_related" />
                        <button type="submit" class="btn btn-default btn-sm"><i class="rex-icon fa-unlink" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_unmark_as_related') ?></button>
                    </form>
                    <?php endif; ?>
                </div>
            </details>
            <?php endif; ?>

            <?php if (!empty($history) && $canViewHistory): ?>
            <details class="it-disclosure" data-it-sec="pane_history"<?= $uiOpen('pane_history') ?>>
                <summary><i class="rex-icon fa-history" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_history') ?> <span class="it-disclosure-count"><?= count($history) ?></span></summary>
                <ol class="it-history">
                    <?php foreach ($history as $entry): ?>
                    <li>
                        <time><?= $entry['created_at']->format('d.m.Y H:i') ?></time>
                        <strong><?= $entry['user'] ? rex_escape($entry['user']->getValue('name')) : 'System' ?></strong>
                        <span><?= HistoryService::formatEntry($entry) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </details>
            <?php endif; ?>
        </div>

        <section class="it-thread" aria-label="<?= rex_escape($package->i18n('issue_tracker_comments')) ?>">
            <h3 class="it-thread-title"><?= $package->i18n('issue_tracker_comments') ?> <span class="it-disclosure-count"><?= count($comments) ?></span></h3>
            <?php if (!empty($pinnedComments)): ?>
            <div class="it-thread-pinned">
                <span class="it-rp-label"><i class="rex-icon fa-star" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_highlighted_answers') ?>:</span>
                <?php foreach ($pinnedComments as $pc): ?>
                <a href="#comment-<?= $pc->getId() ?>"><?= rex_escape(UiHelper::userName($pc->getCreator(), 'Unknown')) ?> (<?= $package->i18n($pc->isSolution() ? 'issue_tracker_solution' : 'issue_tracker_pinned') ?>)</a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (empty($comments)): ?>
            <p class="it-muted-text"><?= $package->i18n('issue_tracker_no_comments') ?></p>
            <?php else: ?>
                <?php foreach ($topLevelComments as $comment) { echo $renderComment($comment, false); } ?>
            <?php endif; ?>
        </section>

        <section class="it-compose" id="it-reply">
            <div id="issue-tracker-comment-area" data-mention-users="<?= rex_escape(json_encode(array_values($allUsers))) ?>">
                <h3 class="it-compose-title"><i class="rex-icon fa-reply" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_reply_title') ?></h3>
                <form method="post" action="<?= $selfAction ?>" enctype="multipart/form-data">
                    <input type="hidden" name="add_comment" value="1" />
                    <textarea name="comment" id="new-comment-text" class="form-control" rows="4" aria-label="<?= rex_escape($package->i18n('issue_tracker_add_comment')) ?>"
                              placeholder="<?= rex_escape($package->i18n('issue_tracker_comment_placeholder')) ?>"></textarea>
                    <div class="it-compose-row">
                        <label class="it-file">
                            <i class="rex-icon fa-paperclip" aria-hidden="true"></i>
                            <span><?= $package->i18n('issue_tracker_mail_add_files') ?></span>
                            <input type="file" name="comment_attachments[]" multiple
                                   accept="image/*,video/*,.pdf,.doc,.docx,.odt,.xls,.xlsx,.ods,.csv,.ppt,.pptx,.odp,.txt,.md,.zip,.rar">
                        </label>
                        <?php if ($canQuickEdit && !$isDone): ?>
                        <label class="it-check">
                            <input type="checkbox" name="close_and_comment" value="1">
                            <span><?= $package->i18n('issue_tracker_close_and_comment') ?> <small class="it-muted-text">– <?= $package->i18n('issue_tracker_close_and_comment_help') ?></small></span>
                        </label>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary it-btn-primary it-compose-send"><?= $package->i18n('issue_tracker_mail_send_reply') ?></button>
                    </div>
                    <p class="it-compose-help"><?= $package->i18n('issue_tracker_attachments_help') ?></p>
                    <p class="it-compose-help it-dnd-hint" hidden><?= $package->i18n('issue_tracker_mail_dnd_hint') ?></p>
                </form>
            </div>
        </section>
    </div>
    <?php if (!$isKnowledge): ?><a class="it-rp-sticky" href="#it-reply" data-it-reply><i class="rex-icon fa-reply" aria-hidden="true"></i> <?= $package->i18n('issue_tracker_mail_reply_title') ?></a><?php endif; ?>
</article>
