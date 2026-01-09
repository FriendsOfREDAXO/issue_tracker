<?php
/**
 * Nachricht anzeigen Fragment
 * 
 * @var rex_fragment $this
 */

$package = rex_addon::get('issue_tracker');

$message = $this->getVar('message');
$conversation = $this->getVar('conversation', []);
$partnerId = $this->getVar('partnerId', 0);
$currentUser = rex::getUser();

$partner = rex_user::get($partnerId);
$partnerName = $partner ? ($partner->getName() ?: $partner->getLogin()) : 'Unbekannt';

// Sortierung aus Session oder Request
$sortOrder = rex_request('msg_sort', 'string', $_SESSION['issue_tracker_msg_sort'] ?? 'asc');
$_SESSION['issue_tracker_msg_sort'] = $sortOrder;

// Konversation sortieren
if ($sortOrder === 'desc') {
    $conversation = array_reverse($conversation);
}
?>

<div class="issue-tracker-message-view">
    <!-- Toolbar -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-8">
            <a href="<?= rex_url::backendPage('issue_tracker/messages/inbox') ?>" class="btn btn-default">
                <i class="rex-icon fa-arrow-left"></i> <?= $package->i18n('issue_tracker_back') ?>
            </a>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#quickReplyModal">
                <i class="rex-icon fa-reply"></i> <?= $package->i18n('issue_tracker_reply') ?>
            </button>
            <a href="<?= rex_url::backendPage('issue_tracker/messages/compose', ['recipient_id' => $partnerId]) ?>" class="btn btn-default">
                <i class="rex-icon fa-plus"></i> <?= $package->i18n('issue_tracker_new_message_to', $partnerName) ?>
            </a>
        </div>
        <div class="col-sm-4 text-right">
            <!-- Sortierung umschalten -->
            <?php if ($sortOrder === 'asc'): ?>
            <a href="<?= rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $message->getId(), 'msg_sort' => 'desc']) ?>" class="btn btn-default btn-sm" title="Neueste zuerst">
                <i class="rex-icon fa-sort-amount-desc"></i> Neueste zuerst
            </a>
            <?php else: ?>
            <a href="<?= rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $message->getId(), 'msg_sort' => 'asc']) ?>" class="btn btn-default btn-sm" title="Chronologisch">
                <i class="rex-icon fa-sort-amount-asc"></i> Chronologisch
            </a>
            <?php endif; ?>
            
            <!-- Zur neuesten Nachricht springen -->
            <button type="button" class="btn btn-info btn-sm" onclick="document.getElementById('latest-message').scrollIntoView({behavior: 'smooth'});" title="Zur neuesten Nachricht">
                <i class="rex-icon fa-arrow-down"></i>
            </button>
        </div>
    </div>

    <!-- Konversationsverlauf -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="rex-icon fa-comments"></i> 
                <?= $package->i18n('issue_tracker_conversation_with') ?> <?= rex_escape($partnerName) ?>
                <small class="text-muted">(<?= count($conversation) ?> Nachrichten)</small>
            </h3>
        </div>
        <div class="panel-body" id="conversation-container">
            <?php 
            $messageCount = count($conversation);
            $index = 0;
            foreach ($conversation as $msg): 
                $index++;
                $isMine = $msg->getSenderId() === $currentUser->getId();
                $isCurrentMessage = $msg->getId() === $message->getId();
                $isLatest = ($sortOrder === 'asc' && $index === $messageCount) || ($sortOrder === 'desc' && $index === 1);
                $bgClass = $isMine ? 'panel-info' : 'panel-default';
                $alignment = $isMine ? 'margin-left: 50px;' : 'margin-right: 50px;';
            ?>
            <div class="panel <?= $bgClass ?>" 
                 style="<?= $alignment ?> <?= $isCurrentMessage ? 'border: 2px solid #337ab7;' : '' ?>"
                 <?= $isLatest ? 'id="latest-message"' : '' ?>>
                <div class="panel-heading" style="padding: 8px 15px;">
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>
                                <?php if ($isMine): ?>
                                <i class="rex-icon fa-user"></i> <?= $package->i18n('issue_tracker_you') ?>
                                <?php else: ?>
                                <i class="rex-icon fa-user-o"></i> <?= rex_escape($msg->getSenderName()) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="col-sm-6 text-right">
                            <small class="text-muted">
                                <?= $msg->getCreatedAt()->format('d.m.Y H:i') ?>
                                <?php if (!$isMine && $msg->isRead()): ?>
                                <i class="rex-icon fa-check" title="<?= $package->i18n('issue_tracker_read_at') ?> <?= $msg->getReadAt() ? $msg->getReadAt()->format('d.m.Y H:i') : '' ?>"></i>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div><strong><?= rex_escape($msg->getSubject()) ?></strong></div>
                </div>
                <div class="panel-body">
                    <?= nl2br(rex_escape($msg->getMessage())) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Schnellantwort Modal -->
<?php 
// Re: nur einmal hinzufügen - bestehende Re: Prefixe entfernen
$originalSubject = $message->getSubject();
$replySubject = preg_replace('/^(Re:\s*)+/i', '', $originalSubject);
$replySubject = 'Re: ' . $replySubject;
?>
<div class="modal fade" id="quickReplyModal" tabindex="-1" role="dialog" aria-labelledby="quickReplyModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="quickReplyModalLabel">
                    <i class="rex-icon fa-reply"></i> <?= $package->i18n('issue_tracker_quick_reply') ?>
                </h4>
            </div>
            <form method="post" action="<?= rex_url::backendPage('issue_tracker/messages/compose') ?>">
                <div class="modal-body">
                    <input type="hidden" name="send" value="1" />
                    <input type="hidden" name="recipient_id" value="<?= $partnerId ?>" />
                    
                    <div class="form-group">
                        <label for="replySubject"><?= $package->i18n('issue_tracker_subject') ?></label>
                        <input type="text" class="form-control" id="replySubject" name="subject" 
                               value="<?= rex_escape($replySubject) ?>" />
                    </div>
                    
                    <div class="form-group">
                        <label for="replyMessage"><?= $package->i18n('issue_tracker_message') ?></label>
                        <textarea class="form-control" id="replyMessage" name="message" rows="8" 
                                  placeholder="<?= $package->i18n('issue_tracker_message_placeholder') ?>" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <i class="rex-icon fa-times"></i> <?= $package->i18n('issue_tracker_cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="rex-icon fa-paper-plane"></i> <?= $package->i18n('issue_tracker_send') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

<script>
// Modal öffnen wenn Keyboard-Shortcut 'r' gedrückt wird
jQuery(document).ready(function($) {
    $(document).on('keydown', function(e) {
        // 'r' für Reply, aber nicht wenn in einem Input/Textarea
        if (e.key === 'r' && !$(e.target).is('input, textarea')) {
            e.preventDefault();
            $('#quickReplyModal').modal('show');
        }
    });
    
    // Fokus auf Textarea wenn Modal geöffnet wird
    $('#quickReplyModal').on('shown.bs.modal', function () {
        $('#replyMessage').focus();
    });
});
</script>
