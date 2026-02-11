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
        
        $package = \rex_addon::get('issue_tracker');
        
        // Nur eingeloggte User
        if (!rex::getUser()) {
            rex_response::sendJson([
                'success' => false,
                'message' => $package->i18n('issue_tracker_board_not_logged_in'),
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
                'message' => $package->i18n('issue_tracker_board_invalid_params'),
            ]);
            exit;
        }

        $issue = Issue::get($issueId);
        if (!$issue) {
            rex_response::sendJson([
                'success' => false,
                'message' => $package->i18n('issue_tracker_board_issue_not_found'),
            ]);
            exit;
        }

        // Prüfen ob User Zugriff auf das Projekt hat
        if ($issue->getProjectId() !== $projectId) {
            rex_response::sendJson([
                'success' => false,
                'message' => $package->i18n('issue_tracker_board_issue_wrong_project'),
            ]);
            exit;
        }

        $project = Project::get($projectId);
        if (!$project || !$project->canWrite(rex::getUser()->getId())) {
            rex_response::sendJson([
                'success' => false,
                'message' => $package->i18n('issue_tracker_board_no_permission'),
            ]);
            exit;
        }

        $oldStatus = $issue->getStatus();
        
        // Status aktualisieren
        $issue->setStatus($newStatus);
        
        // Transaktionen für Atomizität verwenden
        // Note: Transaction isolation level should be configured at database connection level
        // Default READ COMMITTED is suitable for this use case
        $sql = rex_sql::factory();
        $sql->setQuery('START TRANSACTION');
        
        try {
            // Wenn Status geändert wurde, alle Issues in der neuen Spalte neu ordnen
            if ($oldStatus !== $newStatus) {
                // Alle Issues mit höherem sort_order in der neuen Spalte nach oben verschieben
                $sql->setQuery(
                    'UPDATE ' . rex::getTable('issue_tracker_issues') . 
                    ' SET sort_order = sort_order + 1 WHERE project_id = ? AND status = ? AND sort_order >= ?',
                    [$projectId, $newStatus, $newPosition]
                );
                
                // Issue auf neue Position setzen
                $issue->setSortOrder($newPosition);
                
                // Alte Spalte neu ordnen (Lücken schließen) - separate SQL Instanz verwenden
                $resultSql = rex_sql::factory();
                $resultSql->setQuery(
                    'SELECT id, sort_order FROM ' . rex::getTable('issue_tracker_issues') . 
                    ' WHERE project_id = ? AND status = ? ORDER BY sort_order ASC',
                    [$projectId, $oldStatus]
                );
                
                $position = 0;
                foreach ($resultSql as $row) {
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
                // Transaktion abschließen
                $sql->setQuery('COMMIT');
                
                rex_response::sendJson([
                    'success' => true,
                    'message' => $package->i18n('issue_tracker_board_position_saved'),
                    'issue_id' => $issueId,
                    'status' => $newStatus,
                    'position' => $newPosition,
                ]);
                exit;
            }
            
            // Rollback bei Fehler
            $sql->setQuery('ROLLBACK');
        } catch (\Exception $e) {
            // Rollback bei Exception
            $sql->setQuery('ROLLBACK');
            
            rex_response::sendJson([
                'success' => false,
                'message' => $package->i18n('issue_tracker_board_save_error') . ': ' . $e->getMessage(),
            ]);
            exit;
        }

        rex_response::sendJson([
            'success' => false,
            'message' => $package->i18n('issue_tracker_board_save_error'),
        ]);
        exit;
    }
}
