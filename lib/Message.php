<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker;

use DateTime;
use rex;
use rex_sql;
use rex_user;

/**
 * Model für private Nachrichten
 *
 * @package issue_tracker
 */
class Message
{
    private int $id = 0;
    private int $senderId = 0;
    private int $recipientId = 0;
    private string $subject = '';
    private string $message = '';
    private bool $isRead = false;
    private ?DateTime $readAt = null;
    private bool $deletedBySender = false;
    private bool $deletedByRecipient = false;
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getter
    public function getId(): int
    {
        return $this->id;
    }

    public function getSenderId(): int
    {
        return $this->senderId;
    }

    public function getRecipientId(): int
    {
        return $this->recipientId;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function getReadAt(): ?DateTime
    {
        return $this->readAt;
    }

    public function isDeletedBySender(): bool
    {
        return $this->deletedBySender;
    }

    public function isDeletedByRecipient(): bool
    {
        return $this->deletedByRecipient;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    // Setter
    public function setSenderId(int $senderId): self
    {
        $this->senderId = $senderId;
        return $this;
    }

    public function setRecipientId(int $recipientId): self
    {
        $this->recipientId = $recipientId;
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Lädt eine Nachricht anhand der ID
     */
    public static function get(int $id): ?self
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_messages') . ' WHERE id = ?',
            [$id]
        );

        if ($sql->getRows() === 0) {
            return null;
        }

        return self::fromSql($sql);
    }

    /**
     * Gibt alle Nachrichten für einen User zurück (Posteingang)
     */
    public static function getInbox(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_messages') . ' 
             WHERE recipient_id = ? AND deleted_by_recipient = 0
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $limit, $offset]
        );

        $messages = [];
        foreach ($sql as $row) {
            $messages[] = self::fromSql($row);
        }

        return $messages;
    }

    /**
     * Gibt alle gesendeten Nachrichten eines Users zurück
     */
    public static function getSent(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_messages') . ' 
             WHERE sender_id = ? AND deleted_by_sender = 0
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $limit, $offset]
        );

        $messages = [];
        foreach ($sql as $row) {
            $messages[] = self::fromSql($row);
        }

        return $messages;
    }

    /**
     * Zählt ungelesene Nachrichten für einen User
     */
    public static function getUnreadCount(int $userId): int
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT COUNT(*) as cnt FROM ' . rex::getTable('issue_tracker_messages') . ' 
             WHERE recipient_id = ? AND is_read = 0 AND deleted_by_recipient = 0',
            [$userId]
        );

        return (int) $sql->getValue('cnt');
    }

    /**
     * Erstellt eine Nachricht aus SQL-Daten
     */
    private static function fromSql(rex_sql $sql): self
    {
        $message = new self();
        $message->id = (int) $sql->getValue('id');
        $message->senderId = (int) $sql->getValue('sender_id');
        $message->recipientId = (int) $sql->getValue('recipient_id');
        $message->subject = (string) $sql->getValue('subject');
        $message->message = (string) $sql->getValue('message');
        $message->isRead = (bool) $sql->getValue('is_read');
        $message->deletedBySender = (bool) $sql->getValue('deleted_by_sender');
        $message->deletedByRecipient = (bool) $sql->getValue('deleted_by_recipient');
        $message->createdAt = new DateTime((string) $sql->getValue('created_at'));

        if ($sql->getValue('read_at')) {
            $message->readAt = new DateTime((string) $sql->getValue('read_at'));
        }

        return $message;
    }

    /**
     * Speichert die Nachricht
     */
    public function save(): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_messages'));

        $sql->setValue('sender_id', $this->senderId);
        $sql->setValue('recipient_id', $this->recipientId);
        $sql->setValue('subject', $this->subject);
        $sql->setValue('message', $this->message);
        $sql->setValue('is_read', $this->isRead ? 1 : 0);
        $sql->setValue('read_at', $this->readAt ? $this->readAt->format('Y-m-d H:i:s') : null);
        $sql->setValue('deleted_by_sender', $this->deletedBySender ? 1 : 0);
        $sql->setValue('deleted_by_recipient', $this->deletedByRecipient ? 1 : 0);

        if ($this->id > 0) {
            $sql->setWhere(['id' => $this->id]);
            $sql->update();
        } else {
            $sql->setValue('created_at', $this->createdAt->format('Y-m-d H:i:s'));
            $sql->insert();
            $this->id = (int) $sql->getLastId();
            
            // E-Mail-Benachrichtigung senden
            $this->sendEmailNotification();
        }

        return true;
    }

    /**
     * Markiert die Nachricht als gelesen
     */
    public function markAsRead(): bool
    {
        if ($this->isRead) {
            return true;
        }

        $this->isRead = true;
        $this->readAt = new DateTime();

        return $this->save();
    }

    /**
     * Markiert die Nachricht als gelöscht (für Sender oder Empfänger)
     */
    public function delete(int $userId): bool
    {
        if ($userId === $this->senderId) {
            $this->deletedBySender = true;
        }
        if ($userId === $this->recipientId) {
            $this->deletedByRecipient = true;
        }

        // Wenn beide gelöscht haben, wirklich löschen
        if ($this->deletedBySender && $this->deletedByRecipient) {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('issue_tracker_messages') . ' WHERE id = ?',
                [$this->id]
            );
            return true;
        }

        return $this->save();
    }

    /**
     * Prüft ob ein User Zugriff auf diese Nachricht hat
     */
    public function hasAccess(int $userId): bool
    {
        if ($userId === $this->senderId && !$this->deletedBySender) {
            return true;
        }
        if ($userId === $this->recipientId && !$this->deletedByRecipient) {
            return true;
        }
        return false;
    }

    /**
     * Gibt den Absender als rex_user zurück
     */
    public function getSender(): ?rex_user
    {
        return rex_user::get($this->senderId);
    }

    /**
     * Gibt den Empfänger als rex_user zurück
     */
    public function getRecipient(): ?rex_user
    {
        return rex_user::get($this->recipientId);
    }

    /**
     * Gibt den Absender-Namen zurück
     */
    public function getSenderName(): string
    {
        $user = $this->getSender();
        if ($user) {
            return $user->getName() ?: $user->getLogin();
        }
        return 'Unbekannt';
    }

    /**
     * Gibt den Empfänger-Namen zurück
     */
    public function getRecipientName(): string
    {
        $user = $this->getRecipient();
        if ($user) {
            return $user->getName() ?: $user->getLogin();
        }
        return 'Unbekannt';
    }

    /**
     * Konversation zwischen zwei Usern laden
     */
    public static function getConversation(int $user1Id, int $user2Id, int $limit = 100): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable('issue_tracker_messages') . ' 
             WHERE ((sender_id = ? AND recipient_id = ? AND deleted_by_sender = 0) 
                OR (sender_id = ? AND recipient_id = ? AND deleted_by_recipient = 0))
             ORDER BY created_at ASC
             LIMIT ?',
            [$user1Id, $user2Id, $user2Id, $user1Id, $limit]
        );

        $messages = [];
        foreach ($sql as $row) {
            $messages[] = self::fromSql($row);
        }

        return $messages;
    }

    /**
     * Gibt alle Konversationspartner eines Users zurück
     */
    public static function getConversationPartners(int $userId): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT DISTINCT 
                CASE 
                    WHEN sender_id = ? THEN recipient_id 
                    ELSE sender_id 
                END as partner_id,
                MAX(created_at) as last_message_at
             FROM ' . rex::getTable('issue_tracker_messages') . ' 
             WHERE (sender_id = ? AND deleted_by_sender = 0) 
                OR (recipient_id = ? AND deleted_by_recipient = 0)
             GROUP BY partner_id
             ORDER BY last_message_at DESC',
            [$userId, $userId, $userId]
        );

        $partners = [];
        foreach ($sql as $row) {
            $partnerId = (int) $sql->getValue('partner_id');
            $user = rex_user::get($partnerId);
            if ($user) {
                // Ungelesene Nachrichten von diesem Partner zählen
                $unreadSql = rex_sql::factory();
                $unreadSql->setQuery(
                    'SELECT COUNT(*) as cnt FROM ' . rex::getTable('issue_tracker_messages') . ' 
                     WHERE sender_id = ? AND recipient_id = ? AND is_read = 0 AND deleted_by_recipient = 0',
                    [$partnerId, $userId]
                );
                
                $partners[] = [
                    'user_id' => $partnerId,
                    'name' => $user->getName() ?: $user->getLogin(),
                    'last_message_at' => new DateTime((string) $sql->getValue('last_message_at')),
                    'unread_count' => (int) $unreadSql->getValue('cnt'),
                ];
            }
        }

        return $partners;
    }

    /**
     * Sendet E-Mail-Benachrichtigung an den Empfänger
     */
    private function sendEmailNotification(): void
    {
        $recipient = $this->getRecipient();
        if (!$recipient || !$recipient->getValue('email')) {
            return;
        }

        // Prüfen ob E-Mail-Benachrichtigungen global aktiviert sind
        $settingsSql = rex_sql::factory();
        $settingsSql->setQuery(
            'SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = ?',
            ['email_enabled']
        );
        if ($settingsSql->getRows() === 0 || $settingsSql->getValue('setting_value') != '1') {
            return;
        }

        // Benutzereinstellungen laden
        $prefsSql = rex_sql::factory();
        $prefsSql->setQuery(
            'SELECT email_on_message, email_message_full_text FROM ' . rex::getTable('issue_tracker_notifications') . ' WHERE user_id = ?',
            [$this->recipientId]
        );

        $emailOnMessage = 1; // Standard: aktiviert
        $emailFullText = 0;  // Standard: deaktiviert

        if ($prefsSql->getRows() > 0) {
            $emailOnMessage = (int) $prefsSql->getValue('email_on_message');
            $emailFullText = (int) $prefsSql->getValue('email_message_full_text');
        }

        if (!$emailOnMessage) {
            return;
        }

        // Absender-Name laden
        $settingsSql->setQuery(
            'SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = ?',
            ['email_from_name']
        );
        $fromName = $settingsSql->getRows() > 0 ? $settingsSql->getValue('setting_value') : 'Issue Tracker';

        $package = rex_addon::get('issue_tracker');
        $sender = $this->getSender();
        $senderName = $sender ? ($sender->getName() ?: $sender->getLogin()) : 'Unbekannt';

        // E-Mail-Betreff
        $emailSubject = sprintf(
            $package->i18n('issue_tracker_email_new_message_subject'),
            $senderName,
            $this->subject
        );

        // E-Mail-Body erstellen
        $body = '<html><body>';
        $body .= '<h2>' . $package->i18n('issue_tracker_email_new_message_title') . '</h2>';
        $body .= '<table style="border-collapse: collapse; width: 100%;">';
        $body .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>' . $package->i18n('issue_tracker_from') . ':</strong></td>';
        $body .= '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . rex_escape($senderName) . '</td></tr>';
        $body .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>' . $package->i18n('issue_tracker_subject') . ':</strong></td>';
        $body .= '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . rex_escape($this->subject) . '</td></tr>';
        $body .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>' . $package->i18n('issue_tracker_date') . ':</strong></td>';
        $body .= '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . $this->createdAt->format('d.m.Y H:i') . '</td></tr>';
        $body .= '</table>';

        if ($emailFullText) {
            // Vollständiger Nachrichtentext
            $body .= '<h3>' . $package->i18n('issue_tracker_message') . ':</h3>';
            $body .= '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; border-left: 4px solid #337ab7;">';
            $body .= nl2br(rex_escape($this->message));
            $body .= '</div>';
        } else {
            // Nur Hinweis
            $body .= '<p style="margin-top: 20px;">';
            $body .= $package->i18n('issue_tracker_email_message_login_hint');
            $body .= '</p>';
        }

        // Link zum Backend
        $backendUrl = rex::getServer() . rex_url::backendPage('issue_tracker/messages/view', ['message_id' => $this->id]);
        $body .= '<p style="margin-top: 20px;">';
        $body .= '<a href="' . $backendUrl . '" style="display: inline-block; padding: 10px 20px; background: #337ab7; color: #fff; text-decoration: none; border-radius: 4px;">';
        $body .= $package->i18n('issue_tracker_email_view_message');
        $body .= '</a></p>';

        $body .= '</body></html>';

        // E-Mail senden
        try {
            $mail = new rex_mailer();
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $emailSubject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            $mail->setFrom($mail->From, $fromName);
            $mail->addAddress($recipient->getValue('email'), $recipient->getName() ?: $recipient->getLogin());
            $mail->send();
        } catch (\Exception $e) {
            // Fehler loggen, aber nicht werfen
            rex_logger::logException($e);
        }
    }
}
