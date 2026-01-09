<?php

/**
 * API-Funktion zum Generieren eines neuen API-Tokens
 * Nur für Admins zugänglich
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\PermissionService;

class rex_api_issue_tracker_generate_token extends rex_api_function
{
    protected $published = false; // Nur für eingeloggte Backend-User
    
    public function execute()
    {
        rex_response::cleanOutputBuffers();
        
        // Nur Admins dürfen Token generieren
        if (!PermissionService::canManageSettings()) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson([
                'success' => false,
                'error' => 'Keine Berechtigung.',
            ]);
            exit;
        }
        
        // Neuen Token generieren (64 Zeichen hex = 32 Bytes)
        $token = bin2hex(random_bytes(32));
        
        // Token in Datenbank speichern
        rex_sql::factory()
            ->setTable(rex::getTable('issue_tracker_settings'))
            ->setValue('setting_key', 'api_token')
            ->setValue('setting_value', $token)
            ->insertOrUpdate();
        
        rex_response::setStatus(rex_response::HTTP_OK);
        rex_response::sendJson([
            'success' => true,
            'token' => $token,
        ]);
        exit;
    }
}
