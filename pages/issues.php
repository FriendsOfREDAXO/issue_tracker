<?php

/**
 * Index-Seite für Issues
 * 
 * @package issue_tracker
 */

$package = rex_addon::get('issue_tracker');

echo rex_view::title($package->i18n('issue_tracker_mail_window'));

rex_be_controller::includeCurrentPageSubPath();
