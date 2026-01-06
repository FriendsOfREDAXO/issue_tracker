<?php


$addon = rex_addon::get('issue_tracker');

echo rex_view::title($addon->i18n('issue_tracker_issues'));

rex_be_controller::includeCurrentPageSubPath();
