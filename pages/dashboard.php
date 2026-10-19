<?php

/**
 * Früheres Dashboard – alles liegt jetzt im Postfach. Alte Links leiten dorthin weiter.
 *
 * @package issue_tracker
 */

$params = $_GET;
unset($params['page']);
rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', $params, false));
