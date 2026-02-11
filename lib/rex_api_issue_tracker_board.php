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
        $oldPosition = $issue->getSortOrder();
        
        // Update issue object
        $issue->setStatus($newStatus);
        $issue->setSortOrder($newPosition);
        
        // Save the issue first
        if (!$issue->save()) {
            rex_response::sendJson([
                'success' => false,
                'message' => $package->i18n('issue_tracker_board_save_error'),
            ]);
            exit;
        }
        
        try {
            // Wenn Status geändert wurde, reorder both columns
            if ($oldStatus !== $newStatus) {
                // Alte Spalte neu ordnen (Lücken schließen)
                // Use PHP-based reordering for reliable behavior across DB configurations
                $reorderSql = rex_sql::factory();
                $reorderSql->setQuery(
                    'SELECT id FROM ' . rex::getTable('issue_tracker_issues') . 
                    ' WHERE project_id = ? AND status = ? AND id <> ? ' .
                    'ORDER BY sort_order ASC, id ASC',
                    [$projectId, $oldStatus, $issueId]
                );
                
                $position = 0;
                $updateSql = rex_sql::factory();
                foreach ($reorderSql as $row) {
                    $updateSql->setTable(rex::getTable('issue_tracker_issues'));
                    $updateSql->setWhere(['id' => $row->getValue('id')]);
                    $updateSql->setValue('sort_order', $position);
                    $updateSql->update();
                    $position++;
                }
                
                // Neue Spalte neu ordnen - alle Issues mit höherem oder gleichem sort_order nach oben verschieben
                // (außer das gerade bewegte Issue)
                $newColSql = rex_sql::factory();
                $newColSql->setQuery(
                    'SELECT id FROM ' . rex::getTable('issue_tracker_issues') . 
                    ' WHERE project_id = ? AND status = ? AND id <> ? ' .
                    'ORDER BY sort_order ASC, id ASC',
                    [$projectId, $newStatus, $issueId]
                );
                
                $position = 0;
                $updateSql2 = rex_sql::factory();
                foreach ($newColSql as $row) {
                    // Skip the position where our moved issue should be
                    if ($position == $newPosition) {
                        $position++;
                    }
                    $updateSql2->setTable(rex::getTable('issue_tracker_issues'));
                    $updateSql2->setWhere(['id' => $row->getValue('id')]);
                    $updateSql2->setValue('sort_order', $position);
                    $updateSql2->update();
                    $position++;
                }
                
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
                if ($oldPosition !== $newPosition) {
                    // Reorder all issues in the same column
                    $reorderSql = rex_sql::factory();
                    $reorderSql->setQuery(
                        'SELECT id FROM ' . rex::getTable('issue_tracker_issues') . 
                        ' WHERE project_id = ? AND status = ? AND id <> ? ' .
                        'ORDER BY sort_order ASC, id ASC',
                        [$projectId, $newStatus, $issueId]
                    );
                    
                    $position = 0;
                    $updateSql = rex_sql::factory();
                    foreach ($reorderSql as $row) {
                        // Skip the position where our moved issue should be
                        if ($position == $newPosition) {
                            $position++;
                        }
                        $updateSql->setTable(rex::getTable('issue_tracker_issues'));
                        $updateSql->setWhere(['id' => $row->getValue('id')]);
                        $updateSql->setValue('sort_order', $position);
                        $updateSql->update();
                        $position++;
                    }
                }
            }
            
            rex_response::sendJson([
                'success' => true,
                'message' => $package->i18n('issue_tracker_board_position_saved'),
                'issue_id' => $issueId,
                'status' => $newStatus,
                'position' => $newPosition,
            ]);
            exit;
            
        } catch (\Exception $e) {
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
