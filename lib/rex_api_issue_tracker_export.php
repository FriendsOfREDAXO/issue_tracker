<?php

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_api_function;
use rex_response;
use rex_sql;
use rex_user;

/**
 * API für Issue-Export (CSV, PDF, etc.)
 * 
 * @package issue_tracker
 */
class rex_api_issue_tracker_export extends rex_api_function
{
    protected $published = true;

    public function execute(): void
    {
        // Permission Check
        if (!PermissionService::isLoggedIn()) {
            $this->sendError('Keine Berechtigung');
            return;
        }

        $exportFormat = rex_request('format', 'string', 'csv');
        
        if ($exportFormat === 'csv') {
            $this->exportCsv();
        } else {
            $this->sendError('Unbekanntes Export-Format');
        }
    }

    private function exportCsv(): void
    {
        rex_response::cleanOutputBuffers();
        
        // Header für CSV Download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="issues_' . date('Y-m-d_H-i-s') . '.csv"');
        
        // UTF-8 BOM für Excel
        echo "\xEF\xBB\xBF";
        
        // CSV Header
        $headers = [
            '#',
            'Titel',
            'Status',
            'Priorität',
            'Kategorie',
            'Tags',
            'Zugewiesen',
            'Ersteller',
            'Fälligkeitsdatum',
            'Domain',
        ];
        
        echo implode(',', array_map([$this, 'escapeCsv'], $headers)) . "\n";
        
        // Issues aus Session-Filtern laden
        $issues = $this->getFilteredIssues();
        
        foreach ($issues as $issue) {
            $assigned = '';
            if ($issue->getAssignedUserId()) {
                $assignedUser = rex_user::get($issue->getAssignedUserId());
                $assigned = $assignedUser ? $assignedUser->getValue('name') : '';
            }
            
            $creator = '';
            if ($issue->getCreatedBy()) {
                $creatorUser = rex_user::get($issue->getCreatedBy());
                $creator = $creatorUser ? $creatorUser->getValue('name') : '';
            }
            
            // Tags zusammensammeln
            $tags = [];
            foreach ($issue->getTags() as $tag) {
                $tags[] = $tag->getName();
            }
            $tagsString = implode('; ', $tags);
            
            // yrewrite Domain aus dem Issue auslesen
            $domainStr = '';
            $domainIds = $issue->getDomainIds();
            if (!empty($domainIds) && \rex_addon::exists('yrewrite') && \rex_addon::get('yrewrite')->isAvailable()) {
                $domainNames = [];
                foreach (\rex_yrewrite::getDomains() as $domainName => $domain) {
                    $domainId = method_exists($domain, 'getId') ? (int) $domain->getId() : null;
                    if ($domainId !== null && in_array($domainId, $domainIds, true)) {
                        $domainNames[] = \rex_escape($domainName);
                    }
                }
                $domainStr = implode('; ', $domainNames);
            }
            
            $row = [
                $issue->getId(),
                $issue->getTitle(),
                $issue->getStatus(),
                $issue->getPriority(),
                $issue->getCategory(),
                $tagsString,
                $assigned,
                $creator,
                $issue->getDueDate() ? $issue->getDueDate()->format('d.m.Y') : '',
                $domainStr,
            ];
            
            echo implode(',', array_map([$this, 'escapeCsv'], $row)) . "\n";
        }
        
        exit;
    }

    private function getFilteredIssues(): array
    {
        $sql = rex_sql::factory();
        $currentUser = rex::getUser();
        $userId = $currentUser->getId();
        
        // Filter aus Request
        $filterStatus = rex_request('filter_status', 'string', '');
        $filterCategory = rex_request('filter_category', 'string', '');
        $filterTag = rex_request('filter_tag', 'int', 0);
        $filterCreatedBy = rex_request('filter_created_by', 'int', 0);
        $search = rex_request('search', 'string', '');
        $sortColumn = rex_request('sort', 'string', 'created_at');
        $sortOrder = rex_request('order', 'string', 'desc');
        
        // Erlaubte Sortier-Spalten
        $allowedSortColumns = ['id', 'title', 'category', 'status', 'priority', 'assigned_user_id', 'due_date', 'created_at'];
        if (!in_array($sortColumn, $allowedSortColumns)) {
            $sortColumn = 'created_at';
        }
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        
        // Query aufbauen
        $where = [];
        $joins = '';
        
        // Status Filter
        if ($filterStatus && $filterStatus !== '_all_') {
            $where[] = 'status = ' . $sql->escape($filterStatus);
        }
        
        // Kategorie Filter
        if ($filterCategory) {
            $where[] = 'category = ' . $sql->escape($filterCategory);
        }
        
        // Tag Filter
        if ($filterTag > 0) {
            $joins .= ' LEFT JOIN ' . rex::getTable('issue_tracker_issue_tags') . ' itt ON i.id = itt.issue_id';
            $where[] = 'itt.tag_id = ' . (int)$filterTag;
        }
        
        // Ersteller Filter
        if ($filterCreatedBy > 0) {
            $where[] = 'created_by = ' . (int)$filterCreatedBy;
        }
        
        // Text-Suche
        if ($search) {
            $search = $sql->escape('%' . $search . '%');
            $where[] = '(title LIKE ' . $search . ' OR description LIKE ' . $search . ')';
        }
        
        // Sichtbarkeits-Filter (private Issues)
        if (!PermissionService::isAdmin()) {
            $where[] = '(private_issue = 0 OR created_by = ' . $userId . ' OR assigned_user_id = ' . $userId . ')';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = '
            SELECT DISTINCT i.* 
            FROM ' . rex::getTable('issue_tracker_issues') . ' i
            ' . $joins . '
            ' . $whereClause . '
            ORDER BY ' . $sortColumn . ' ' . $sortOrder . '
        ';
        
        $sql->setQuery($query);
        
        $issues = [];
        foreach ($sql as $row) {
            $issues[] = Issue::get((int)$row->getValue('id'));
        }
        
        return $issues;
    }

    private function escapeCsv(string $value): string
    {
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    private function sendError(string $message): void
    {
        rex_response::cleanOutputBuffers();
        rex_response::sendJson(['error' => $message]);
        exit;
    }
}
