<?php

/**
 * Notification Service für E-Mail-Benachrichtigungen.
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use Exception;
use rex;
use rex_logger;
use rex_mailer;
use rex_sql;
use rex_url;
use rex_user;

use function in_array;
use function sprintf;

class NotificationService
{
    /**
     * Sendet E-Mail-Benachrichtigungen für ein neues Issue.
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
                self::getNewIssueTemplate($issue, $creator, $user),
            );
        }
    }

    /**
     * Sendet E-Mail-Benachrichtigungen für einen neuen Kommentar.
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
                self::getNewCommentTemplate($comment, $issue, $creator, $user),
            );
        }
    }

    /**
     * Sendet E-Mail-Benachrichtigungen für Status-Änderung.
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
                self::getStatusChangeTemplate($issue, $oldStatus, $newStatus, $user),
            );
        }
    }

    /**
     * Sendet E-Mail-Benachrichtigungen für Zuweisung.
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
                self::getAssignmentTemplate($issue, $assignedUser),
            );
        }
    }

    /**
     * Sendet E-Mail-Benachrichtigung wenn Issue als Duplikat markiert wurde.
     */
    public static function sendDuplicateMarked(Issue $duplicate, Issue $original): void
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        // Benachrichtige Ersteller des Duplikats
        $duplicateCreator = $duplicate->getCreator();
        if ($duplicateCreator && $duplicateCreator->getValue('email')) {
            $subject = 'Issue #' . $duplicate->getId() . ' als Duplikat markiert';
            $message = sprintf(
                "Hallo %s,\n\nIhr Issue #%d \"%s\" wurde als Duplikat von #%d \"%s\" markiert und automatisch geschlossen.\n\nWeitere Informationen finden Sie im Original-Issue:\n%s\n\n---\nDiese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.",
                $duplicateCreator->getName(),
                $duplicate->getId(),
                $duplicate->getTitle(),
                $original->getId(),
                $original->getTitle(),
                rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $original->getId()], false),
            );

            self::sendMail($duplicateCreator->getValue('email'), $subject, $message);
        }

        // Benachrichtige Ersteller des Originals
        $originalCreator = $original->getCreator();
        if ($originalCreator && $originalCreator->getValue('email') && $originalCreator->getId() !== $duplicateCreator?->getId()) {
            $subject = 'Duplikat zu Issue #' . $original->getId() . ' gefunden';
            $message = sprintf(
                "Hallo %s,\n\nIssue #%d \"%s\" wurde als Duplikat Ihres Issues #%d \"%s\" markiert.\n\nZum Issue:\n%s\n\n---\nDiese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.",
                $originalCreator->getName(),
                $duplicate->getId(),
                $duplicate->getTitle(),
                $original->getId(),
                $original->getTitle(),
                rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $original->getId()], false),
            );

            self::sendMail($originalCreator->getValue('email'), $subject, $message);
        }
    }

    /**
     * Sendet Broadcast-Nachricht an User.
     *
     * @param string $subject Betreff der Nachricht
     * @param string $message Nachrichtentext
     * @param string $method Versandart: 'message' (Nachrichtensystem), 'email' (nur E-Mail), 'both' (beides)
     * @param string $recipients Empfänger: 'issue_tracker' (nur berechtigte User) oder 'all' (alle REDAXO User)
     * @return int Anzahl der gesendeten Nachrichten
     */
    public static function sendBroadcast(string $subject, string $message, string $method = 'message', string $recipients = 'issue_tracker'): int
    {
        // Bei E-Mail-Versand nur User mit E-Mail holen
        $requireEmail = ('email' === $method || 'both' === $method);

        // User holen je nach Empfängergruppe
        if ('all' === $recipients) {
            $users = self::getAllRedaxoUsers($requireEmail);
            // Bei "alle User" nur E-Mail erlauben (Sicherheit)
            $method = 'email';
        } else {
            $users = self::getAllIssueTrackerUsers($requireEmail);
        }

        $count = 0;
        $currentUserId = rex::getUser()->getId();

        foreach ($users as $user) {
            // Absender nicht benachrichtigen
            if ($user->getId() === $currentUserId) {
                continue;
            }

            $sent = false;

            // Nachricht über das interne Nachrichtensystem senden
            if ('message' === $method || 'both' === $method) {
                $msg = new Message();
                $msg->setSenderId($currentUserId);
                $msg->setRecipientId($user->getId());
                $msg->setSubject($subject);
                $msg->setMessage($message);
                if ($msg->save()) {
                    $sent = true;
                }
            }

            // E-Mail senden
            if ('email' === $method || 'both' === $method) {
                $email = $user->getValue('email');
                if ($email && self::sendMail($email, $subject, $message)) {
                    $sent = true;
                }
            }

            if ($sent) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Prüft ob E-Mail-Benachrichtigungen aktiviert sind.
     */
    private static function isEmailEnabled(): bool
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . '
            WHERE setting_key = "email_enabled"
        ');

        return $sql->getRows() > 0 && '1' === $sql->getValue('setting_value');
    }

    /**
     * Gibt alle User zurück, die eine bestimmte Benachrichtigung aktiviert haben.
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
     * Gibt alle berechtigten Issue-Tracker User zurück.
     *
     * @param bool $requireEmail Wenn true, werden nur User mit E-Mail zurückgegeben
     */
    private static function getAllIssueTrackerUsers(bool $requireEmail = false): array
    {
        // Alle aktiven User laden
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE status = 1');

        $users = [];
        foreach ($sql as $row) {
            $user = rex_user::get((int) $row->getValue('id'));
            if ($user) {
                // Prüfen ob User Issue-Tracker Berechtigung hat (Admin oder issue_tracker[])
                if (!$user->isAdmin() && !$user->hasPerm('issue_tracker[]')) {
                    continue;
                }

                // Wenn E-Mail erforderlich, nur User mit E-Mail hinzufügen
                if ($requireEmail && !$user->getValue('email')) {
                    continue;
                }
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * Gibt alle aktiven REDAXO User zurück (unabhängig von Berechtigungen).
     *
     * @param bool $requireEmail Wenn true, werden nur User mit E-Mail zurückgegeben
     */
    private static function getAllRedaxoUsers(bool $requireEmail = true): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE status = 1');

        $users = [];
        foreach ($sql as $row) {
            $user = rex_user::get((int) $row->getValue('id'));
            if ($user) {
                // Wenn E-Mail erforderlich, nur User mit E-Mail hinzufügen
                if ($requireEmail && !$user->getValue('email')) {
                    continue;
                }
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * Erstellt einen Token für Deep Links zu Issues in E-Mails.
     */
    private static function createEmailToken(int $issueId): string
    {
        $token = bin2hex(random_bytes(32));

        $sql = rex_sql::factory();
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
     * Erstellt einen Token für Deep Links zu Nachrichten in E-Mails.
     */
    public static function createMessageEmailToken(int $messageId): string
    {
        $token = bin2hex(random_bytes(32));

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_email_tokens'));
        $sql->setValue('token', $token);
        $sql->setValue('message_id', $messageId);
        $sql->setValue('used', 0);
        $sql->setValue('created_at', date('Y-m-d H:i:s'));
        $sql->setValue('expires_at', date('Y-m-d H:i:s', strtotime('+30 days')));
        $sql->insert();

        return $token;
    }

    /**
     * Sendet eine E-Mail.
     */
    private static function sendMail(string $to, string $subject, string $body): bool
    {
        try {
            $mail = new rex_mailer();

            $sql = rex_sql::factory();
            $sql->setQuery('
                SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . '
                WHERE setting_key = "email_from_name"
            ');

            $fromName = $sql->getRows() > 0 && $sql->getValue('setting_value') !== '' ? $sql->getValue('setting_value') : $mail->FromName;

            // Absender-E-Mail aus Addon-Einstellungen laden, Fallback auf PHPMailer-Config
            $sql->setQuery('
                SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . '
                WHERE setting_key = "email_from_address"
            ');
            $fromAddress = $sql->getRows() > 0 && $sql->getValue('setting_value') !== '' ? $sql->getValue('setting_value') : $mail->From;

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);

            // Wrap body content in HTML template if not already wrapped
            if (!str_contains($body, '<!DOCTYPE html>')) {
                $body = EmailTemplateService::getHtmlWrapper($body, $subject);
            }

            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $result = $mail->send();

            if (!$result) {
                rex_logger::factory()->log('info', 'Issue Tracker: E-Mail konnte nicht an ' . $to . ' gesendet werden: ' . $mail->ErrorInfo);
            }

            return $result;
        } catch (Exception $e) {
            rex_logger::logException($e);
            return false;
        }
    }

    /**
     * Template für neue Issues.
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
            'issue_url' => $url,
        ]);
    }

    /**
     * Template für neue Kommentare.
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
            'issue_url' => $url,
        ]);
    }

    /**
     * Template für Status-Änderungen.
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
            'issue_url' => $url,
        ]);
    }

    /**
     * Template für Zuweisungen.
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
            'issue_url' => $url,
        ]);
    }

    /**
     * Lädt ein E-Mail-Template aus der Datenbank.
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

        $sql = rex_sql::factory();
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
     * Ersetzt Platzhalter im Template.
     */
    private static function replaceTemplatePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    /**
     * Gibt Default-Template zurück als Fallback.
     */
    private static function getDefaultTemplate(string $templateName, string $lang): string
    {
        $defaults = [
            'new_issue_de' => '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #0056b3;">Neues Issue erstellt</h2>
        <p style="margin: 0; color: #666;">Hallo {{recipient_name}},</p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #333;">{{issue_title}}</h3>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold; width: 120px;">Kategorie:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{issue_category}}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Priorität:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{issue_priority}}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Erstellt von:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{creator_name}}</td>
            </tr>
        </table>
        
        <div style="background: #f9f9f9; padding: 15px; border-radius: 3px; margin-bottom: 15px;">
            <strong style="display: block; margin-bottom: 5px;">Beschreibung:</strong>
            <p style="margin: 0; white-space: pre-wrap;">{{issue_description}}</p>
        </div>
        
        <a href="{{issue_url}}" style="display: inline-block; background: #0056b3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; font-weight: bold;">Issue anzeigen</a>
        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">(Dieser Link ist nur einmal verwendbar und 30 Tage gültig)</p>
    </div>
    
    <div style="text-align: center; padding: 15px; color: #999; font-size: 12px; border-top: 1px solid #ddd;">
        Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.
    </div>
</body>
</html>',
            'new_issue_en' => '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #0056b3;">New Issue Created</h2>
        <p style="margin: 0; color: #666;">Hello {{recipient_name}},</p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #333;">{{issue_title}}</h3>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold; width: 120px;">Category:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{issue_category}}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Priority:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{issue_priority}}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Created by:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{creator_name}}</td>
            </tr>
        </table>
        
        <div style="background: #f9f9f9; padding: 15px; border-radius: 3px; margin-bottom: 15px;">
            <strong style="display: block; margin-bottom: 5px;">Description:</strong>
            <p style="margin: 0; white-space: pre-wrap;">{{issue_description}}</p>
        </div>
        
        <a href="{{issue_url}}" style="display: inline-block; background: #0056b3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; font-weight: bold;">View Issue</a>
        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">(This link is valid for one-time use and 30 days)</p>
    </div>
    
    <div style="text-align: center; padding: 15px; color: #999; font-size: 12px; border-top: 1px solid #ddd;">
        This email was automatically generated by REDAXO Issue Tracker.
    </div>
</body>
</html>',
            'new_comment_de' => '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #0056b3;">Neuer Kommentar</h2>
        <p style="margin: 0; color: #666;">Hallo {{recipient_name}},</p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin: 0 0 15px 0; color: #666;">Es wurde ein neuer Kommentar zu Issue #{{issue_id}} hinzugefügt:</p>
        <h3 style="margin: 0 0 15px 0; color: #333;">{{issue_title}}</h3>
        
        <div style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0056b3; margin-bottom: 15px;">
            <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">Kommentar von {{creator_name}}:</p>
            <p style="margin: 0; white-space: pre-wrap;">{{comment_text}}</p>
        </div>
        
        <a href="{{issue_url}}" style="display: inline-block; background: #0056b3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; font-weight: bold;">Issue anzeigen</a>
    </div>
    
    <div style="text-align: center; padding: 15px; color: #999; font-size: 12px; border-top: 1px solid #ddd;">
        Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.
    </div>
</body>
</html>',
            'new_comment_en' => '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #0056b3;">New Comment</h2>
        <p style="margin: 0; color: #666;">Hello {{recipient_name}},</p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin: 0 0 15px 0; color: #666;">A new comment was added to issue #{{issue_id}}:</p>
        <h3 style="margin: 0 0 15px 0; color: #333;">{{issue_title}}</h3>
        
        <div style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0056b3; margin-bottom: 15px;">
            <p style="margin: 0 0 5px 0; font-size: 12px; color: #666;">Comment by {{creator_name}}:</p>
            <p style="margin: 0; white-space: pre-wrap;">{{comment_text}}</p>
        </div>
        
        <a href="{{issue_url}}" style="display: inline-block; background: #0056b3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; font-weight: bold;">View Issue</a>
    </div>
    
    <div style="text-align: center; padding: 15px; color: #999; font-size: 12px; border-top: 1px solid #ddd;">
        This email was automatically generated by REDAXO Issue Tracker.
    </div>
</body>
</html>',
            'status_change_de' => '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #0056b3;">Status geändert</h2>
        <p style="margin: 0; color: #666;">Hallo {{recipient_name}},</p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin: 0 0 15px 0; color: #666;">Der Status von Issue #{{issue_id}} wurde geändert:</p>
        <h3 style="margin: 0 0 15px 0; color: #333;">{{issue_title}}</h3>
        
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <span style="padding: 8px 16px; background: #f0f0f0; border-radius: 3px; margin-right: 10px;">{{old_status}}</span>
            <span style="color: #666;">→</span>
            <span style="padding: 8px 16px; background: #e7f3ff; border: 1px solid #0056b3; border-radius: 3px; margin-left: 10px; color: #0056b3; font-weight: bold;">{{new_status}}</span>
        </div>
        
        <a href="{{issue_url}}" style="display: inline-block; background: #0056b3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; font-weight: bold;">Issue anzeigen</a>
        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">(Dieser Link ist nur einmal verwendbar und 30 Tage gültig)</p>
    </div>
    
    <div style="text-align: center; padding: 15px; color: #999; font-size: 12px; border-top: 1px solid #ddd;">
        Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.
    </div>
</body>
</html>',
            'status_change_en' => '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #0056b3;">Status Changed</h2>
        <p style="margin: 0; color: #666;">Hello {{recipient_name}},</p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin: 0 0 15px 0; color: #666;">The status of issue #{{issue_id}} was changed:</p>
        <h3 style="margin: 0 0 15px 0; color: #333;">{{issue_title}}</h3>
        
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <span style="padding: 8px 16px; background: #f0f0f0; border-radius: 3px; margin-right: 10px;">{{old_status}}</span>
            <span style="color: #666;">→</span>
            <span style="padding: 8px 16px; background: #e7f3ff; border: 1px solid #0056b3; border-radius: 3px; margin-left: 10px; color: #0056b3; font-weight: bold;">{{new_status}}</span>
        </div>
        
        <a href="{{issue_url}}" style="display: inline-block; background: #0056b3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; font-weight: bold;">View Issue</a>
        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">(This link is valid for one-time use and 30 days)</p>
    </div>
    
    <div style="text-align: center; padding: 15px; color: #999; font-size: 12px; border-top: 1px solid #ddd;">
        This email was automatically generated by REDAXO Issue Tracker.
    </div>
</body>
</html>',
            'assignment_de' => '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #0056b3;">Issue zugewiesen</h2>
        <p style="margin: 0; color: #666;">Hallo {{recipient_name}},</p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin: 0 0 15px 0; color: #666;">Ihnen wurde ein Issue zugewiesen:</p>
        <h3 style="margin: 0 0 15px 0; color: #333;">Issue #{{issue_id}}: {{issue_title}}</h3>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold; width: 120px;">Kategorie:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{issue_category}}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Priorität:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{issue_priority}}</td>
            </tr>
        </table>
        
        <div style="background: #f9f9f9; padding: 15px; border-radius: 3px; margin-bottom: 15px;">
            <strong style="display: block; margin-bottom: 5px;">Beschreibung:</strong>
            <p style="margin: 0; white-space: pre-wrap;">{{issue_description}}</p>
        </div>
        
        <a href="{{issue_url}}" style="display: inline-block; background: #0056b3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; font-weight: bold;">Issue anzeigen</a>
        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">(Dieser Link ist nur einmal verwendbar und 30 Tage gültig)</p>
    </div>
    
    <div style="text-align: center; padding: 15px; color: #999; font-size: 12px; border-top: 1px solid #ddd;">
        Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.
    </div>
</body>
</html>',
            'assignment_en' => '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; color: #0056b3;">Issue Assigned</h2>
        <p style="margin: 0; color: #666;">Hello {{recipient_name}},</p>
    </div>
    
    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin: 0 0 15px 0; color: #666;">An issue was assigned to you:</p>
        <h3 style="margin: 0 0 15px 0; color: #333;">Issue #{{issue_id}}: {{issue_title}}</h3>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold; width: 120px;">Category:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{issue_category}}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">Priority:</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">{{issue_priority}}</td>
            </tr>
        </table>
        
        <div style="background: #f9f9f9; padding: 15px; border-radius: 3px; margin-bottom: 15px;">
            <strong style="display: block; margin-bottom: 5px;">Description:</strong>
            <p style="margin: 0; white-space: pre-wrap;">{{issue_description}}</p>
        </div>
        
        <a href="{{issue_url}}" style="display: inline-block; background: #0056b3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; font-weight: bold;">View Issue</a>
        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">(This link is valid for one-time use and 30 days)</p>
    </div>
    
    <div style="text-align: center; padding: 15px; color: #999; font-size: 12px; border-top: 1px solid #ddd;">
        This email was automatically generated by REDAXO Issue Tracker.
    </div>
</body>
</html>',
        ];

        $key = $templateName . '_' . $lang;
        return $defaults[$key] ?? $defaults[$templateName . '_de'] ?? '';
    }
}
