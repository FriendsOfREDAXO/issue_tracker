<?php

/**
 * API für Kanban Board Drag & Drop
 * 
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_api_function;
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
        $newStatusRaw = rex_request('status', 'string', '');
        $newStatus = trim($newStatusRaw);
        $newPosition = rex_request('position', 'int', 0);
        // Clamp position to >= 0 to avoid negative indices / unsigned DB issues
        if ($newPosition < 0) {
            $newPosition = 0;
        }
        $projectId = rex_request('project_id', 'int', 0);

        // Basic validation: ensure required params are set and status has an allowed format
        if (
            $issueId === 0
            || $projectId === 0
            || $newStatus === ''
            || !preg_match('/^[A-Za-z0-9_\-]+$/', $newStatus)
        ) {
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
        
        // Use database transactions to ensure atomicity and prevent race conditions
        // Note: For high-concurrency scenarios, consider implementing optimistic locking
        // by adding a version column to track concurrent modifications
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
                
                // Alte Spalte neu ordnen (Lücken schließen) - set-basiertes Update verwenden
                // Exclude the moved issue from reordering
                $table = rex::getTable('issue_tracker_issues');
                $reorderSql = rex_sql::factory();
                
                // Initialize counter variable
                $reorderSql->setQuery('SET @pos := -1');
                
                // Reindex all issues in the old column in one statement
                $reorderSql->setQuery(
                    'UPDATE ' . $table . ' AS t ' .
                    'JOIN ( ' .
                    '    SELECT id, (@pos := @pos + 1) AS new_sort_order ' .
                    '    FROM ' . $table . ' ' .
                    '    WHERE project_id = ? AND status = ? AND id <> ? ' .
                    '    ORDER BY sort_order ASC, id ASC ' .
                    ') AS seq ON seq.id = t.id ' .
                    'SET t.sort_order = seq.new_sort_order',
                    [$projectId, $oldStatus, $issueId]
                );
                
                // History-Eintrag für Statusänderung
                HistoryService::add(
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
            
            // Log the exception server-side for debugging
            \rex_logger::logException($e);
            
            rex_response::sendJson([
                'success' => false,
                'message' => $package->i18n('issue_tracker_board_save_error'),
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
