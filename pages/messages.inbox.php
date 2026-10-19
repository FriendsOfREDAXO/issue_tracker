<?php

/**
 * Früheres Postfach „inbox“ – Nachrichten sind jetzt ein Bereich im gemeinsamen Postfach.
 *
 * @package issue_tracker
 */

$params = $_GET;
unset($params['page']);
$params['box'] = 'inbox';
rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', $params, false));
