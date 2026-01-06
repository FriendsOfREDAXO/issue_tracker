<?php

/**
 * API-Funktion für Deep Links aus E-Mails
 * Ermöglicht direkten Zugriff auf Issues auch wenn User nicht eingeloggt ist
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Issue;

class rex_api_issue_tracker_link extends rex_api_function
{
    protected $published = false; // Öffentlich zugänglich
    
    public function execute()
    {
        rex_response::cleanOutputBuffers();
        
        $token = rex_request('token', 'string', '');
        
        if ($token === '') {
            rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list'));
            exit;
        }
        
        // Token validieren
        $sql = \rex_sql::factory();
        $sql->setQuery('
            SELECT issue_id, expires_at 
            FROM ' . rex::getTable('issue_tracker_email_tokens') . ' 
            WHERE token = ? 
            AND used = 0
        ', [$token]);
        
        if ($sql->getRows() === 0) {
            // Token ungültig oder bereits verwendet
            if (rex::getUser()) {
                rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list'));
            } else {
                rex_response::sendRedirect(rex_url::backendPage('login'));
            }
            exit;
        }
        
        $issueId = (int) $sql->getValue('issue_id');
        $expiresAt = $sql->getValue('expires_at');
        
        // Prüfen ob Token abgelaufen (30 Tage)
        if (strtotime($expiresAt) < time()) {
            if (rex::getUser()) {
                rex_response::sendRedirect(rex_url::backendPage('issue_tracker/issues/list'));
            } else {
                rex_response::sendRedirect(rex_url::backendPage('login'));
            }
            exit;
        }
        
        // Prüfen ob User eingeloggt ist
        if (!rex::getUser()) {
            // Token in Session speichern für nach dem Login
            $_SESSION['issue_tracker_token'] = $token;
            // Zur Login-Seite weiterleiten
            rex_response::sendRedirect(rex_url::backendPage('login'));
            exit;
        }
        
        // Token als verwendet markieren
        $updateSql = \rex_sql::factory();
        $updateSql->setTable(rex::getTable('issue_tracker_email_tokens'));
        $updateSql->setWhere(['token' => $token]);
        $updateSql->setValue('used', 1);
        $updateSql->setValue('used_at', date('Y-m-d H:i:s'));
        $updateSql->update();
        
        // Zum Issue weiterleiten - html_entity_decode verhindert &amp; Problem
        $redirectUrl = html_entity_decode(rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issueId]));
        rex_response::sendRedirect($redirectUrl);
        exit;
    }
}
