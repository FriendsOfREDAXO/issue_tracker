<?php

/**
 * API-Funktion für externe Statistik-Abfragen
 * Ermöglicht Monitoring mehrerer Issue-Tracker-Installationen
 * 
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\Message;

class rex_api_issue_tracker_stats extends rex_api_function
{
    protected $published = true; // Öffentlich zugänglich (aber Token-geschützt)
    
    public function execute()
    {
        rex_response::cleanOutputBuffers();
        
        // CORS-Header für externe Zugriffe
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        
        // OPTIONS-Request für CORS Preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            rex_response::setStatus(rex_response::HTTP_OK);
            exit;
        }
        
        // Token aus Header oder Parameter
        $token = $this->getApiToken();
        
        if (!$token) {
            $this->sendError('API-Token fehlt. Sende Token als Authorization-Header oder api_token Parameter.', 401);
        }
        
        // Token validieren
        if (!$this->validateToken($token)) {
            $this->sendError('Ungültiger API-Token.', 403);
        }
        
        // Statistiken sammeln
        $stats = $this->getStats();
        
        rex_response::setStatus(rex_response::HTTP_OK);
        rex_response::sendJson([
            'success' => true,
            'timestamp' => date('c'),
            'installation' => [
                'name' => $this->getInstallationName(),
                'url' => rex::getServer(),
                'version' => rex_addon::get('issue_tracker')->getVersion(),
            ],
            'stats' => $stats,
        ]);
        exit;
    }
    
    /**
     * Holt den API-Token aus Header oder Parameter
     */
    private function getApiToken(): ?string
    {
        // Zuerst Authorization Header prüfen
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Fallback: Parameter
        $token = rex_request('api_token', 'string', '');
        
        return $token !== '' ? $token : null;
    }
    
    /**
     * Validiert den API-Token
     */
    private function validateToken(string $token): bool
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT setting_value 
            FROM ' . rex::getTable('issue_tracker_settings') . ' 
            WHERE setting_key = "api_token"
        ');
        
        if ($sql->getRows() === 0) {
            return false;
        }
        
        $storedToken = $sql->getValue('setting_value');
        
        return hash_equals($storedToken, $token);
    }
    
    /**
     * Holt den Installations-Namen aus den Einstellungen
     */
    private function getInstallationName(): string
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT setting_value 
            FROM ' . rex::getTable('issue_tracker_settings') . ' 
            WHERE setting_key = "installation_name"
        ');
        
        if ($sql->getRows() > 0 && $sql->getValue('setting_value')) {
            return $sql->getValue('setting_value');
        }
        
        // Fallback: Server-Name oder REDAXO-Name
        return rex::getServerName() ?: parse_url(rex::getServer(), PHP_URL_HOST) ?: 'REDAXO';
    }
    
    /**
     * Sammelt alle Statistiken
     */
    private function getStats(): array
    {
        $sql = rex_sql::factory();
        
        // Issue-Statistiken
        $sql->setQuery('
            SELECT 
                status,
                COUNT(*) as count
            FROM ' . rex::getTable('issue_tracker_issues') . '
            GROUP BY status
        ');
        
        $issuesByStatus = [];
        $totalIssues = 0;
        while ($sql->hasNext()) {
            $status = $sql->getValue('status');
            $count = (int) $sql->getValue('count');
            $issuesByStatus[$status] = $count;
            $totalIssues += $count;
            $sql->next();
        }
        
        // Offene Issues (alles außer closed)
        $openCount = 0;
        foreach ($issuesByStatus as $status => $count) {
            if ($status !== 'closed') {
                $openCount += $count;
            }
        }
        
        // Überfällige Issues
        $sql->setQuery('
            SELECT COUNT(*) as count
            FROM ' . rex::getTable('issue_tracker_issues') . '
            WHERE due_date < NOW()
            AND status NOT IN ("closed", "rejected")
        ');
        $overdueCount = (int) $sql->getValue('count');
        
        // Issues heute erstellt
        $sql->setQuery('
            SELECT COUNT(*) as count
            FROM ' . rex::getTable('issue_tracker_issues') . '
            WHERE DATE(created_at) = CURDATE()
        ');
        $createdToday = (int) $sql->getValue('count');
        
        // Ungelesene Nachrichten (gesamt)
        $sql->setQuery('
            SELECT COUNT(*) as count
            FROM ' . rex::getTable('issue_tracker_messages') . '
            WHERE is_read = 0
            AND deleted_by_recipient = 0
        ');
        $unreadMessages = (int) $sql->getValue('count');
        
        // Letzte 5 Issues
        $sql->setQuery('
            SELECT id, title, status, priority, created_at
            FROM ' . rex::getTable('issue_tracker_issues') . '
            ORDER BY created_at DESC
            LIMIT 5
        ');
        
        $recentIssues = [];
        while ($sql->hasNext()) {
            $recentIssues[] = [
                'id' => (int) $sql->getValue('id'),
                'title' => $sql->getValue('title'),
                'status' => $sql->getValue('status'),
                'priority' => $sql->getValue('priority'),
                'created_at' => $sql->getValue('created_at'),
            ];
            $sql->next();
        }
        
        return [
            'issues' => [
                'total' => $totalIssues,
                'open' => $openCount,
                'by_status' => $issuesByStatus,
                'overdue' => $overdueCount,
                'created_today' => $createdToday,
            ],
            'messages' => [
                'unread' => $unreadMessages,
            ],
            'recent_issues' => $recentIssues,
        ];
    }
    
    /**
     * Sendet einen Fehler als JSON
     */
    private function sendError(string $message, int $httpCode = 400): void
    {
        rex_response::setStatus($httpCode);
        rex_response::sendJson([
            'success' => false,
            'error' => $message,
        ]);
        exit;
    }
}
