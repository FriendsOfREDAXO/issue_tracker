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
            'new_issue_de' => "Hallo {{recipient_name}},

es wurde ein neues Issue erstellt:

Titel: {{issue_title}}
Kategorie: {{issue_category}}
Priorität: {{issue_priority}}
Erstellt von: {{creator_name}}

Beschreibung:
{{issue_description}}

Zum Issue: {{issue_url}}
(Dieser Link ist nur einmal verwendbar und 30 Tage gültig)

---
Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.",
            'new_issue_en' => "Hello {{recipient_name}},

a new issue was created:

Title: {{issue_title}}
Category: {{issue_category}}
Priority: {{issue_priority}}
Created by: {{creator_name}}

Description:
{{issue_description}}

View issue: {{issue_url}}
(This link is valid for one-time use and 30 days)

---
This email was automatically generated by REDAXO Issue Tracker.",
            'new_comment_de' => "Hallo {{recipient_name}},

es wurde ein neuer Kommentar zu Issue #{{issue_id}} hinzugefügt:

Issue: {{issue_title}}
Kommentar von: {{creator_name}}

{{comment_text}}

Zum Issue: {{issue_url}}

---
Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.",
            'new_comment_en' => "Hello {{recipient_name}},

a new comment was added to issue #{{issue_id}}:

Issue: {{issue_title}}
Comment by: {{creator_name}}

{{comment_text}}

View issue: {{issue_url}}

---
This email was automatically generated by REDAXO Issue Tracker.",
            'status_change_de' => "Hallo {{recipient_name}},

der Status von Issue #{{issue_id}} wurde geändert:

Issue: {{issue_title}}
Alter Status: {{old_status}}
Neuer Status: {{new_status}}

Zum Issue: {{issue_url}}

---
Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.",
            'status_change_en' => "Hello {{recipient_name}},

the status of issue #{{issue_id}} was changed:

Issue: {{issue_title}}
Old status: {{old_status}}
New status: {{new_status}}

View issue: {{issue_url}}

---
This email was automatically generated by REDAXO Issue Tracker.",
            'assignment_de' => "Hallo {{recipient_name}},

Ihnen wurde ein Issue zugewiesen:

Issue #{{issue_id}}: {{issue_title}}
Kategorie: {{issue_category}}
Priorität: {{issue_priority}}

Beschreibung:
{{issue_description}}

Zum Issue: {{issue_url}}

---
Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.",
            'assignment_en' => "Hello {{recipient_name}},

an issue was assigned to you:

Issue #{{issue_id}}: {{issue_title}}
Category: {{issue_category}}
Priority: {{issue_priority}}

Description:
{{issue_description}}

View issue: {{issue_url}}

---
This email was automatically generated by REDAXO Issue Tracker."
        ];
        
        $key = $templateName . '_' . $lang;
        return $defaults[$key] ?? $defaults[$templateName . '_de'] ?? '';
    }
}
