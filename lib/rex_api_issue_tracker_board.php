<?php

/**
 * API für Kanban Board Drag & Drop
 * 
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;
use rex_sql;

class rex_api_issue_tracker_board extends rex_api_function
{
    protected $published = false;

    public function execute()
    {
        rex_response::cleanOutputBuffers();
        
        // Nur eingeloggte User
        if (!rex::getUser()) {
            rex_response::sendJson([
                'success' => false,
                'message' => 'Nicht eingeloggt',
            ]);
            exit;
        }

        $issueId = rex_request('issue_id', 'int', 0);
        $newStatus = rex_request('status', 'string', '');
        $newPosition = rex_request('position', 'int', 0);
        $projectId = rex_request('project_id', 'int', 0);

        if ($issueId === 0 || $newStatus === '' || $projectId === 0) {
            rex_response::sendJson([
                'success' => false,
                'message' => 'Ungültige Parameter',
            ]);
            exit;
        }

        $issue = Issue::get($issueId);
        if (!$issue) {
            rex_response::sendJson([
                'success' => false,
                'message' => 'Issue nicht gefunden',
            ]);
            exit;
        }

        // Prüfen ob User Zugriff auf das Projekt hat
        if ($issue->getProjectId() !== $projectId) {
            rex_response::sendJson([
                'success' => false,
                'message' => 'Issue gehört nicht zu diesem Projekt',
            ]);
            exit;
        }

        $project = Project::get($projectId);
        if (!$project || !$project->canWrite(rex::getUser()->getId())) {
            rex_response::sendJson([
                'success' => false,
                'message' => 'Keine Berechtigung',
            ]);
            exit;
        }

        $oldStatus = $issue->getStatus();
        
        // Status aktualisieren
        $issue->setStatus($newStatus);
        
        // Wenn Status geändert wurde, alle Issues in der neuen Spalte neu ordnen
        if ($oldStatus !== $newStatus) {
            // Alle Issues mit höherem sort_order in der neuen Spalte nach oben verschieben
            $sql = rex_sql::factory();
            $sql->setQuery(
                'UPDATE ' . rex::getTable('issue_tracker_issues') . 
                ' SET sort_order = sort_order + 1 WHERE project_id = ? AND status = ? AND sort_order >= ?',
                [$projectId, $newStatus, $newPosition]
            );
            
            // Issue auf neue Position setzen
            $issue->setSortOrder($newPosition);
            
            // Alte Spalte neu ordnen (Lücken schließen)
            $sql->setQuery(
                'SELECT id, sort_order FROM ' . rex::getTable('issue_tracker_issues') . 
                ' WHERE project_id = ? AND status = ? ORDER BY sort_order ASC',
                [$projectId, $oldStatus]
            );
            
            $position = 0;
            foreach ($sql as $row) {
                $updateSql = rex_sql::factory();
                $updateSql->setTable(rex::getTable('issue_tracker_issues'));
                $updateSql->setWhere(['id' => $row->getValue('id')]);
                $updateSql->setValue('sort_order', $position);
                $updateSql->update();
                $position++;
            }
            
            // History-Eintrag für Statusänderung
            HistoryService::logChange(
                $issueId,
                rex::getUser()->getId(),
                'status_changed',
                'status',
                $oldStatus,
                $newStatus
            );
        } else {
            // Nur Position innerhalb der gleichen Spalte geändert
            $oldPosition = $issue->getSortOrder();
            
            if ($oldPosition !== $newPosition) {
                $sql = rex_sql::factory();
                
                if ($newPosition < $oldPosition) {
                    // Nach oben verschoben - alle dazwischenliegenden nach unten
                    $sql->setQuery(
                        'UPDATE ' . rex::getTable('issue_tracker_issues') . 
                        ' SET sort_order = sort_order + 1 WHERE project_id = ? AND status = ? AND sort_order >= ? AND sort_order < ?',
                        [$projectId, $newStatus, $newPosition, $oldPosition]
                    );
                } else {
                    // Nach unten verschoben - alle dazwischenliegenden nach oben
                    $sql->setQuery(
                        'UPDATE ' . rex::getTable('issue_tracker_issues') . 
                        ' SET sort_order = sort_order - 1 WHERE project_id = ? AND status = ? AND sort_order > ? AND sort_order <= ?',
                        [$projectId, $newStatus, $oldPosition, $newPosition]
                    );
                }
                
                $issue->setSortOrder($newPosition);
            }
        }

        if ($issue->save()) {
            rex_response::sendJson([
                'success' => true,
                'message' => 'Position gespeichert',
                'issue_id' => $issueId,
                'status' => $newStatus,
                'position' => $newPosition,
            ]);
            exit;
        }

        rex_response::sendJson([
            'success' => false,
            'message' => 'Fehler beim Speichern',
        ]);
        exit;
    }
}
