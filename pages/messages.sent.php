<?php

/**
 * Früheres Postfach „sent“ – Nachrichten sind jetzt ein Bereich im gemeinsamen Postfach.
 *
 * @package issue_tracker
 */

$params = $_GET;
unset($params['page']);
$params['box'] = 'sent';
rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', $params, false));
