<?php

/**
 * Notification Service für E-Mail-Benachrichtigungen
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_sql;
use rex_user;
use rex_mailer;
use rex_i18n;
use rex_url;

class NotificationService
{
    /**
     * Sendet E-Mail-Benachrichtigungen für ein neues Issue
     */
    public static function notifyNewIssue(Issue $issue): void
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        $users = self::getUsersForNotification('email_on_new');
        $creator = $issue->getCreator();
        
        foreach ($users as $user) {
            // Ersteller nicht benachrichtigen
            if ($user->getId() === $issue->getCreatedBy()) {
                continue;
            }

            self::sendMail(
                $user->getValue('email'),
                'Neues Issue: ' . $issue->getTitle(),
                self::getNewIssueTemplate($issue, $creator, $user)
            );
        }
    }

    /**
     * Sendet E-Mail-Benachrichtigungen für einen neuen Kommentar
     */
    public static function notifyNewComment(Comment $comment, Issue $issue): void
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        $users = self::getUsersForNotification('email_on_comment');
        $creator = $comment->getCreator();
        
        foreach ($users as $user) {
            // Kommentar-Autor nicht benachrichtigen
            if ($user->getId() === $comment->getCreatedBy()) {
                continue;
            }

            self::sendMail(
                $user->getValue('email'),
                'Neuer Kommentar zu Issue #' . $issue->getId() . ': ' . $issue->getTitle(),
                self::getNewCommentTemplate($comment, $issue, $creator, $user)
            );
        }
    }

    /**
     * Sendet E-Mail-Benachrichtigungen für Status-Änderung
     */
    public static function notifyStatusChange(Issue $issue, string $oldStatus, string $newStatus): void
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        $users = self::getUsersForNotification('email_on_status_change');
        
        foreach ($users as $user) {
            self::sendMail(
                $user->getValue('email'),
                'Status geändert: Issue #' . $issue->getId() . ': ' . $issue->getTitle(),
                self::getStatusChangeTemplate($issue, $oldStatus, $newStatus, $user)
            );
        }
    }

    /**
     * Sendet E-Mail-Benachrichtigungen für Zuweisung
     */
    public static function notifyAssignment(Issue $issue, rex_user $assignedUser): void
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT * FROM ' . rex::getTable('issue_tracker_notifications') . '
            WHERE user_id = ? AND email_on_assignment = 1
        ', [$assignedUser->getId()]);

        if ($sql->getRows() > 0) {
            self::sendMail(
                $assignedUser->getValue('email'),
                'Issue zugewiesen: #' . $issue->getId() . ': ' . $issue->getTitle(),
                self::getAssignmentTemplate($issue, $assignedUser)
            );
        }
    }

    /**
     * Sendet Broadcast-Nachricht an alle berechtigten User
     */
    public static function sendBroadcast(string $subject, string $message): int
    {
        $users = self::getAllIssueTrackerUsers();
        $count = 0;
        $currentUserId = \rex::getUser()->getId();

        foreach ($users as $user) {
            // Absender nicht benachrichtigen
            if ($user->getId() === $currentUserId) {
                continue;
            }
            
            if (self::sendMail($user->getValue('email'), $subject, $message)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Prüft ob E-Mail-Benachrichtigungen aktiviert sind
     */
    private static function isEmailEnabled(): bool
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . '
            WHERE setting_key = "email_enabled"
        ');

        return $sql->getRows() > 0 && $sql->getValue('setting_value') === '1';
    }

    /**
     * Gibt alle User zurück, die eine bestimmte Benachrichtigung aktiviert haben
     */
    private static function getUsersForNotification(string $notificationType): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT DISTINCT u.id 
            FROM ' . rex::getTable('user') . ' u
            LEFT JOIN ' . rex::getTable('issue_tracker_notifications') . ' n ON u.id = n.user_id
            WHERE (u.admin = 1 OR u.role LIKE "%|issue_tracker[]|%" OR u.role LIKE "%|issue_tracker[issuer]|%")
            AND (n.' . $notificationType . ' = 1 OR n.id IS NULL)
        ');

        $users = [];
        foreach ($sql as $row) {
            $user = rex_user::get((int) $row->getValue('id'));
            if ($user && $user->getValue('email')) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * Gibt alle berechtigten Issue-Tracker User zurück
     */
    private static function getAllIssueTrackerUsers(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT id FROM ' . rex::getTable('user') . '
            WHERE admin = 1 OR role LIKE "%|issue_tracker[]|%" OR role LIKE "%|issue_tracker[issuer]|%"
        ');

        $users = [];
        foreach ($sql as $row) {
            $user = rex_user::get((int) $row->getValue('id'));
            if ($user && $user->getValue('email')) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * Erstellt einen Token für Deep Links in E-Mails
     */
    private static function createEmailToken(int $issueId): string
    {
        $token = bin2hex(random_bytes(32));
        
        $sql = \rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_email_tokens'));
        $sql->setValue('token', $token);
        $sql->setValue('issue_id', $issueId);
        $sql->setValue('used', 0);
        $sql->setValue('created_at', date('Y-m-d H:i:s'));
        $sql->setValue('expires_at', date('Y-m-d H:i:s', strtotime('+30 days')));
        $sql->insert();
        
        return $token;
    }

    /**
     * Sendet eine E-Mail
     */
    private static function sendMail(string $to, string $subject, string $body): bool
    {
        try {
            $mail = new \rex_mailer();
            
            $sql = \rex_sql::factory();
            $sql->setQuery('
                SELECT setting_value FROM ' . \rex::getTable('issue_tracker_settings') . '
                WHERE setting_key = "email_from_name"
            ');
            
            $fromName = $sql->getRows() > 0 ? $sql->getValue('setting_value') : 'REDAXO Issue Tracker';
            
            $mail->setFrom(\rex::getProperty('server'), $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $result = $mail->send();
            
            if (!$result) {
                \rex_logger::factory()->log('info', 'Issue Tracker: E-Mail konnte nicht an ' . $to . ' gesendet werden: ' . $mail->ErrorInfo);
            }
            
            return $result;
        } catch (\Exception $e) {
            \rex_logger::logException($e);
            return false;
        }
    }

    /**
     * Template für neue Issues
     */
    private static function getNewIssueTemplate(Issue $issue, ?rex_user $creator, rex_user $recipient): string
    {
        $creatorName = $creator ? $creator->getValue('name') : 'Unbekannt';
        $token = self::createEmailToken($issue->getId());
        $url = rex::getServer() . 'index.php?rex-api-call=issue_tracker_link&token=' . $token;

        $template = self::getTemplate('new_issue', $recipient);
        
        return self::replaceTemplatePlaceholders($template, [
            'recipient_name' => $recipient->getValue('name'),
            'issue_id' => $issue->getId(),
            'issue_title' => $issue->getTitle(),
            'issue_category' => $issue->getCategory(),
            'issue_priority' => $issue->getPriority(),
            'issue_description' => $issue->getDescription(),
            'creator_name' => $creatorName,
            'issue_url' => $url
        ]);
    }

    /**
     * Template für neue Kommentare
     */
    private static function getNewCommentTemplate(Comment $comment, Issue $issue, ?rex_user $creator, rex_user $recipient): string
    {
        $creatorName = $creator ? $creator->getValue('name') : 'Unbekannt';
        $token = self::createEmailToken($issue->getId());
        $url = rex::getServer() . 'index.php?rex-api-call=issue_tracker_link&token=' . $token;

        $template = self::getTemplate('new_comment', $recipient);
        
        return self::replaceTemplatePlaceholders($template, [
            'recipient_name' => $recipient->getValue('name'),
            'issue_id' => $issue->getId(),
            'issue_title' => $issue->getTitle(),
            'creator_name' => $creatorName,
            'comment_text' => $comment->getComment(),
            'issue_url' => $url
        ]);
    }

    /**
     * Template für Status-Änderungen
     */
    private static function getStatusChangeTemplate(Issue $issue, string $oldStatus, string $newStatus, rex_user $recipient): string
    {
        $token = self::createEmailToken($issue->getId());
        $url = rex::getServer() . 'index.php?rex-api-call=issue_tracker_link&token=' . $token;

        $template = self::getTemplate('status_change', $recipient);
        
        return self::replaceTemplatePlaceholders($template, [
            'recipient_name' => $recipient->getValue('name'),
            'issue_id' => $issue->getId(),
            'issue_title' => $issue->getTitle(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'issue_url' => $url
        ]);
    }

    /**
     * Template für Zuweisungen
     */
    private static function getAssignmentTemplate(Issue $issue, rex_user $assignedUser): string
    {
        $token = self::createEmailToken($issue->getId());
        $url = rex::getServer() . 'index.php?rex-api-call=issue_tracker_link&token=' . $token;

        $template = self::getTemplate('assignment', $assignedUser);
        
        return self::replaceTemplatePlaceholders($template, [
            'recipient_name' => $assignedUser->getValue('name'),
            'issue_id' => $issue->getId(),
            'issue_title' => $issue->getTitle(),
            'issue_category' => $issue->getCategory(),
            'issue_priority' => $issue->getPriority(),
            'issue_description' => $issue->getDescription(),
            'issue_url' => $url
        ]);
    }
    
    /**
     * Lädt ein E-Mail-Template aus der Datenbank
     */
    private static function getTemplate(string $templateName, rex_user $user): string
    {
        // User-Sprache ermitteln
        $lang = 'de'; // Default
        if ($user->getValue('language')) {
            $userLang = $user->getValue('language');
            if (in_array($userLang, ['de_de', 'de'])) {
                $lang = 'de';
            } elseif (in_array($userLang, ['en_gb', 'en'])) {
                $lang = 'en';
            }
        }
        
        $sql = \rex_sql::factory();
        $sql->setQuery('
            SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . '
            WHERE setting_key = ?
        ', ['email_template_' . $templateName . '_' . $lang]);
        
        if ($sql->getRows() > 0) {
            return $sql->getValue('setting_value');
        }
        
        // Fallback auf deutsche Version
        $sql->setQuery('
            SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . '
            WHERE setting_key = ?
        ', ['email_template_' . $templateName . '_de']);
        
        if ($sql->getRows() > 0) {
            return $sql->getValue('setting_value');
        }
        
        // Hard-coded Fallback
        return self::getDefaultTemplate($templateName, $lang);
    }
    
    /**
     * Ersetzt Platzhalter im Template
     */
    private static function replaceTemplatePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }
    
    /**
     * Gibt Default-Template zurück als Fallback
     */
    private static function getDefaultTemplate(string $templateName, string $lang): string
    {
        $defaults = [
            'new_issue_de' => "Hallo {{recipient_name}},\n\nes wurde ein neues Issue erstellt:\n\nTitel: {{issue_title}}\nKategorie: {{issue_category}}\nPriorität: {{issue_priority}}\nErstellt von: {{creator_name}}\n\nBeschreibung:\n{{issue_description}}\n\nZum Issue: {{issue_url}}\n(Dieser Link ist nur einmal verwendbar und 30 Tage gültig)",
            'new_issue_en' => "Hello {{recipient_name}},\n\na new issue was created:\n\nTitle: {{issue_title}}\nCategory: {{issue_category}}\nPriority: {{issue_priority}}\nCreated by: {{creator_name}}\n\nDescription:\n{{issue_description}}\n\nView issue: {{issue_url}}\n(This link is valid for one-time use and 30 days)",
            'new_comment_de' => "Hallo {{recipient_name}},\n\nes wurde ein neuer Kommentar zu Issue #{{issue_id}} hinzugefügt:\n\nIssue: {{issue_title}}\nKommentar von: {{creator_name}}\n\n{{comment_text}}\n\nZum Issue: {{issue_url}}",
            'new_comment_en' => "Hello {{recipient_name}},\n\na new comment was added to issue #{{issue_id}}:\n\nIssue: {{issue_title}}\nComment by: {{creator_name}}\n\n{{comment_text}}\n\nView issue: {{issue_url}}",
            'status_change_de' => "Hallo {{recipient_name}},\n\nder Status von Issue #{{issue_id}} wurde geändert:\n\nIssue: {{issue_title}}\nAlter Status: {{old_status}}\nNeuer Status: {{new_status}}\n\nZum Issue: {{issue_url}}",
            'status_change_en' => "Hello {{recipient_name}},\n\nthe status of issue #{{issue_id}} was changed:\n\nIssue: {{issue_title}}\nOld status: {{old_status}}\nNew status: {{new_status}}\n\nView issue: {{issue_url}}",
            'assignment_de' => "Hallo {{recipient_name}},\n\nIhnen wurde ein Issue zugewiesen:\n\nIssue #{{issue_id}}: {{issue_title}}\nKategorie: {{issue_category}}\nPriorität: {{issue_priority}}\n\nBeschreibung:\n{{issue_description}}\n\nZum Issue: {{issue_url}}",
            'assignment_en' => "Hello {{recipient_name}},\n\nan issue was assigned to you:\n\nIssue #{{issue_id}}: {{issue_title}}\nCategory: {{issue_category}}\nPriority: {{issue_priority}}\n\nDescription:\n{{issue_description}}\n\nView issue: {{issue_url}}"
        ];
        
        $key = $templateName . '_' . $lang;
        return $defaults[$key] ?? $defaults[$templateName . '_de'] ?? '';
    }
}
