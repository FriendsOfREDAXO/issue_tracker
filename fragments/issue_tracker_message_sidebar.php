<?php
/**
 * Postfächer für Nachrichten (wie Mail): Eingang, Gesendet, Neue Nachricht, Rundschreiben
 *
 * @var rex_fragment $this
 */

use FriendsOfREDAXO\IssueTracker\Message;
use FriendsOfREDAXO\IssueTracker\PermissionService;

$package = rex_addon::get('issue_tracker');
$active = (string) $this->getVar('active', 'inbox');
$unread = Message::getUnreadCount(PermissionService::getUserId());

$items = [
    'inbox' => ['issue_tracker/messages/inbox', 'fa-inbox', $package->i18n('issue_tracker_messages_inbox'), $unread],
    'sent' => ['issue_tracker/messages/sent', 'fa-paper-plane-o', $package->i18n('issue_tracker_messages_sent'), 0],
    'compose' => ['issue_tracker/messages/compose', 'fa-pencil-square-o', $package->i18n('issue_tracker_mail_new_message'), 0],
];
if (PermissionService::isAdmin()) {
    $items['broadcast'] = ['issue_tracker/messages/broadcast', 'fa-bullhorn', $package->i18n('issue_tracker_broadcast'), 0];
}
?>
<nav class="it-mbx" aria-label="<?= rex_escape($package->i18n('issue_tracker_mail_mailboxes')) ?>">
    <div class="it-mbx-section">
        <h3 class="it-mbx-title"><?= $package->i18n('issue_tracker_messages') ?></h3>
        <ul class="it-mbx-list">
            <?php foreach ($items as $key => [$page, $icon, $label, $count]): ?>
            <li><a class="it-mbx-item<?= $active === $key ? ' is-active' : '' ?>" href="<?= rex_url::backendPage($page) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                <i class="it-mbx-icon rex-icon <?= $icon ?>" aria-hidden="true"></i>
                <span class="it-mbx-label"><?= rex_escape($label) ?></span>
                <?php if ($count > 0): ?><span class="it-mbx-count"><?= (int) $count ?></span><?php endif; ?>
            </a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
