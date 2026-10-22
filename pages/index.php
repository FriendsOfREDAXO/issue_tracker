<?php

use FriendsOfREDAXO\IssueTracker\AppShell;

$addon = rex_addon::get('issue_tracker');

// Vollbild: eigener App-Rahmen über dem Backend (Einstellung „Als Vollbild öffnen“)
if (AppShell::isEnabled()) {
    echo AppShell::open();
    rex_be_controller::includeCurrentPageSubPath();
    echo AppShell::close();
    return;
}

echo rex_view::title($addon->i18n('issue_tracker_mail_window'));

rex_be_controller::includeCurrentPageSubPath();
