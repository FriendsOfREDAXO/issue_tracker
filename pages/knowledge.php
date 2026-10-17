<?php

/**
 * Frühere Wissensseite – das Wissen ist jetzt ein Ordner im Postfach (Themen mit Status „Info“).
 * Alte Links leiten dorthin weiter, Suche/Kategorie/Tag bleiben erhalten.
 *
 * @package issue_tracker
 */

$params = array_intersect_key($_GET, array_flip(['search', 'filter_category', 'filter_tag', 'issue_id']));
$params = ['filter_status' => 'info'] + $params;
rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', $params, false));
