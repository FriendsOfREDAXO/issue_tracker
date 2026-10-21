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
    // Vollbild-App (eigener Rahmen über dem Backend), abschaltbar in den Einstellungen
    if (\FriendsOfREDAXO\IssueTracker\AppShell::isEnabled()) {
        rex_view::addCssFile($this->getAssetsUrl('css/it-app.css'));
        rex_view::addJsFile($this->getAssetsUrl('js/it-app.js'));
    }

    // Schriftgröße des Benutzers (90–140 %) als Klasse am body
    rex_extension::register('PAGE_BODY_ATTR', static function (rex_extension_point $ep) {
        $attr = $ep->getSubject();
        $userId = rex::requireUser()->getId();
        $scale = (int) rex_config::get('issue_tracker', 'font_scale_' . $userId, 100);
        if (in_array($scale, [90, 110, 120, 130, 140], true)) {
            $attr['class'][] = 'it-scale-' . $scale;
        }
        if (\FriendsOfREDAXO\IssueTracker\AppShell::isEnabled()) {
            $attr['class'][] = 'it-app-mode';
        }
        // Gespeicherte Spaltenbreiten (nur eigene CSS-Variablen)
        $cols = rex_config::get('issue_tracker', 'columns_' . $userId, []);
        if (is_array($cols) && isset($cols['mbx'], $cols['ml'])) {
            $attr['style'][] = '--it-col-mbx:' . (int) $cols['mbx'] . 'px;--it-col-ml:' . (int) $cols['ml'] . 'px;';
        }
        return $attr;
    });
}

// Vollbild-App: von jeder Backend-Seite aus öffnen (Hauptmenü + Knopf in der Kopfzeile), als Ebene über der Seite.
// Nur ein kleines Skript; die App selbst lädt in der Ebene. Nicht auf der Anmeldeseite, in Popups und im Issue Tracker selbst.
if (rex::isBackend() && rex::getUser() && \FriendsOfREDAXO\IssueTracker\AppShell::isEnabled()) {
    $itAddon = $this;
    rex_extension::register('PAGES_PREPARED', static function (rex_extension_point $ep) {
        $pages = $ep->getSubject();
        if (isset($pages['issue_tracker']) && $pages['issue_tracker'] instanceof rex_be_page) {
            // Klick im Hauptmenü öffnet die Ebene (it-launcher.js); ohne JavaScript führt der Link zur Seite
            $pages['issue_tracker']->setLinkAttr('data-it-app-open', '1');
        }
        return $pages;
    });
    rex_extension::register('PAGE_CHECKED', static function () use ($itAddon) {
        $current = rex_be_controller::getCurrentPageObject();
        if (!$current || 'issue_tracker' === rex_be_controller::getCurrentPagePart(1) || 'login' === rex_be_controller::getCurrentPage()
            || $current->isPopup() || !$current->hasLayout() || !$current->hasNavigation() || rex_request::isXmlHttpRequest()
            || !\FriendsOfREDAXO\IssueTracker\AppShell::userCanOpen()) {
            return;
        }
        rex_view::addCssFile($itAddon->getAssetsUrl('css/it-launcher.css'));
        rex_view::addJsFile($itAddon->getAssetsUrl('js/it-launcher.js'));
        rex_extension::register('META_NAVI', static function (rex_extension_point $ep) {
            $items = $ep->getSubject();
            array_unshift($items, \FriendsOfREDAXO\IssueTracker\AppShell::launcherItems());
            return $items;
        });
    });
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