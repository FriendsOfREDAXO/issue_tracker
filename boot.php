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

// Assets einbinden
if (rex::isBackend() && rex::getUser()) {
    rex_view::addCssFile($this->getAssetsUrl('easymde.min.css'));
    rex_view::addCssFile($this->getAssetsUrl('issue_tracker.css'));
    rex_view::addJsFile($this->getAssetsUrl('easymde.min.js'));
    rex_view::addJsFile($this->getAssetsUrl('issue_tracker.js'));
}

// Nach erfolgreichem Login prüfen ob Token in der Session ist
if (rex::isBackend() && rex::getUser() && isset($_SESSION['issue_tracker_token'])) {
    $token = $_SESSION['issue_tracker_token'];
    unset($_SESSION['issue_tracker_token']);
    
    // Zum API-Call weiterleiten der den Token verarbeitet
    rex_response::sendRedirect(rex::getServer() . 'index.php?rex-api-call=issue_tracker_link&token=' . $token);
    exit;
}
