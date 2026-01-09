<?php

/**
 * Broadcast-Nachricht senden (nur Admins)
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\NotificationService;
use FriendsOfREDAXO\IssueTracker\PermissionService;

$package = rex_addon::get('issue_tracker');

// Nur Admins dürfen Broadcasts senden
if (!PermissionService::isAdmin()) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

// Broadcast senden
if (rex_post('send_broadcast', 'int', 0) === 1) {
    $subject = rex_post('broadcast_subject', 'string', '');
    $message = rex_post('broadcast_message', 'string', '');
    $method = rex_post('broadcast_method', 'string', 'message');
    $recipients = rex_post('broadcast_recipients', 'string', 'issue_tracker');
    
    // Bei "alle User" nur E-Mail erlauben
    if ($recipients === 'all') {
        $method = 'email';
    }
    
    if ($subject && $message) {
        $count = NotificationService::sendBroadcast($subject, $message, $method, $recipients);
        echo rex_view::success($package->i18n('issue_tracker_broadcast_sent', $count));
    } else {
        echo rex_view::error($package->i18n('issue_tracker_broadcast_error'));
    }
}

// Fragment ausgeben
$fragment = new rex_fragment();
echo $fragment->parse('issue_tracker_broadcast.php');
