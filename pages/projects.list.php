<?php

/**
 * Frühere Projektliste – Projekte sind jetzt Ordner im Postfach.
 * Löschen geht über „Projekt bearbeiten“ (POST mit CSRF-Token), hier nur noch die Weiterleitung.
 *
 * @package issue_tracker
 */

rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list', [], false));
