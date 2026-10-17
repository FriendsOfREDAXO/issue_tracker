<?php

/**
 * Issue Tracker AddOn for REDAXO
 * 
 * @package issue_tracker
 */

// Media Manager Effect registrieren
if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::addEffect('rex_effect_issue_attachment');
}

// Permissions registrieren
if (rex::isBackend() && rex::getUser()) {
    rex_perm::register('issue_tracker[]', null, rex_perm::OPTIONS);
    rex_perm::register('issue_tracker[issuer]', null, rex_perm::OPTIONS);
    rex_perm::register('issue_tracker[issue_manager]', null, rex_perm::OPTIONS);
}

// Assets nur auf den Seiten des Issue Trackers einbinden
if (rex::isBackend() && rex::getUser() && 'issue_tracker' === rex_be_controller::getCurrentPagePart(1)) {
    rex_view::addCssFile($this->getAssetsUrl('easymde.min.css'));
    rex_view::addCssFile($this->getAssetsUrl('issue_tracker.css'));
    rex_view::addCssFile($this->getAssetsUrl('css/it-base.css'));
    rex_view::addCssFile($this->getAssetsUrl('css/it-ui.css'));
    rex_view::addJsFile($this->getAssetsUrl('easymde.min.js'));
    rex_view::addJsFile($this->getAssetsUrl('issue_tracker.js'));
    rex_view::addJsFile($this->getAssetsUrl('js/it-ui.js'));
    rex_view::addCssFile($this->getAssetsUrl('css/it-mail.css'));
    rex_view::addJsFile($this->getAssetsUrl('js/it-mail.js'));
}

// Benutzerdefinierten Menü-Titel setzen
if (rex::isBackend()) {
    rex_extension::register('PACKAGES_INCLUDED', static function () {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "menu_title"');
        
        if ($sql->getRows() > 0) {
            $customTitle = trim($sql->getValue('setting_value'));
            if ($customTitle !== '') {
                $addon = rex_addon::get('issue_tracker');
                $page = $addon->getProperty('page');
                if ($page) {
                    $page['title'] = $customTitle;
                    $addon->setProperty('page', $page);
                }
            }
        }
    }, rex_extension::EARLY);
}

// Nach erfolgreichem Login prüfen ob Token in der Session ist
if (rex::isBackend() && rex::getUser() && isset($_SESSION['issue_tracker_token'])) {
    $token = $_SESSION['issue_tracker_token'];
    unset($_SESSION['issue_tracker_token']);
    
    // Zum API-Call weiterleiten der den Token verarbeitet
    rex_response::sendRedirect(rex::getServer() . 'index.php?rex-api-call=issue_tracker_link&token=' . $token);
    exit;
}

// Ungelesene Nachrichten Badge in Navigation anzeigen
if (rex::isBackend() && rex::getUser()) {
    rex_extension::register('PAGE_TITLE', static function (rex_extension_point $ep) {
        // Das Badge sitzt in der Unternavigation des Issue Trackers – nur dort ausgeben (dort ist auch das Skript geladen)
        if ('issue_tracker' !== rex_be_controller::getCurrentPagePart(1)) {
            return;
        }
        $unreadCount = \FriendsOfREDAXO\IssueTracker\Message::getUnreadCount(rex::getUser()->getId());
        if ($unreadCount > 0) {
            // Badge per zentraler JS-Funktion hinzufügen (CSS ist in issue_tracker.css)
            $script = '<script nonce="' . rex_response::getNonce() . '">issueTrackerAddMessageBadge(' . (int) $unreadCount . ');</script>';
            $ep->setSubject($ep->getSubject() . $script);
        }
    });
}
// API-Funktionen registrieren
rex_api_function::register('issue_tracker_export', 'FriendsOfREDAXO\IssueTracker\rex_api_issue_tracker_export');