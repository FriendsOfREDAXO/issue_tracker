<?php

/**
 * E-Mail Template Service für Issue Tracker
 * Verwaltet HTML-E-Mail-Vorlagen.
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_sql;

class EmailTemplateService
{
    /** @var list<string> Felder mit User-Content, die Markdown-Parsing erhalten */
    private const RICH_TEXT_FIELDS = ['issue_description', 'comment_text'];

    /**
     * Konvertiert einfaches Markdown in sicheres HTML für E-Mails.
     *
     * Erlaubt: **fett**, *kursiv*, [Link](url), URLs, E-Mail-Adressen, Zeilenumbrüche.
     * Alles andere wird escaped (XSS-sicher).
     */
    public static function formatContentForEmail(string $text): string
    {
        // 1. HTML-Tags strippen (Sicherheit)
        $text = strip_tags($text);

        // 2. HTML-Entities escapen
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // 3. Markdown-Links: [text](url) — nur http(s) und mailto erlaubt
        $text = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/',
            '<a href="$2" style="color: #4b9ad9;">$1</a>',
            $text,
        );
        $text = preg_replace(
            '/\[([^\]]+)\]\((mailto:[^\s\)]+)\)/',
            '<a href="$2" style="color: #4b9ad9;">$1</a>',
            $text,
        );

        // 4. Fett: **text**
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

        // 5. Kursiv: *text* (aber nicht innerhalb von **)
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text);

        // 6. Auto-Link: E-Mail-Adressen (die nicht schon in einem href sind)
        $text = preg_replace(
            '/(?<!href="|mailto:)([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/',
            '<a href="mailto:$1" style="color: #4b9ad9;">$1</a>',
            $text,
        );

        // 7. Auto-Link: URLs (die nicht schon in einem href/src sind)
        $text = preg_replace(
            '/(?<!href="|src=")(https?:\/\/[^\s<\)]+)/',
            '<a href="$1" style="color: #4b9ad9;">$1</a>',
            $text,
        );

        // 8. Zeilenumbrüche → <br>
        $text = nl2br($text);

        return $text;
    }

    /**
     * Wendet formatContentForEmail() auf bekannte Rich-Text-Felder an.
     *
     * @param array<string, string|int> $data Platzhalter-Daten
     * @return array<string, string|int> Daten mit formatierten Rich-Text-Feldern
     */
    public static function formatRichTextFields(array $data): array
    {
        foreach (self::RICH_TEXT_FIELDS as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = self::formatContentForEmail($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Gibt das HTML-Basis-Template zurück.
     *
     * @param string $content Der Hauptinhalt der E-Mail
     * @param string $title Der Titel/Betreff
     * @return string Das vollständige HTML
     */
    public static function getHtmlWrapper(string $content, string $title = ''): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="de">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$title}</title>
                <style>
                    body {
                        margin: 0;
                        padding: 0;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                        font-size: 14px;
                        line-height: 1.6;
                        color: #333;
                        background-color: #f4f4f4;
                    }
                    .email-container {
                        max-width: 600px;
                        margin: 20px auto;
                        background-color: #ffffff;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    }
                    .email-header {
                        background: linear-gradient(135deg, #4b9ad9 0%, #324050 100%);
                        background-color: #4b9ad9;
                        color: #ffffff;
                        padding: 30px;
                        text-align: center;
                    }
                    .email-header h1 {
                        margin: 0;
                        font-size: 24px;
                        font-weight: 600;
                    }
                    .email-body {
                        padding: 30px;
                    }
                    .email-body h2 {
                        color: #4b9ad9;
                        font-size: 20px;
                        margin-top: 0;
                        margin-bottom: 20px;
                    }
                    .info-box {
                        background-color: #f8f9fa;
                        border-left: 4px solid #4b9ad9;
                        padding: 15px;
                        margin: 20px 0;
                        border-radius: 4px;
                    }
                    .info-box strong {
                        color: #4b9ad9;
                    }
                    .button {
                        display: inline-block;
                        padding: 12px 24px;
                        background: linear-gradient(135deg, #4b9ad9 0%, #324050 100%);
                        background-color: #4b9ad9;
                        color: #ffffff !important;
                        text-decoration: none;
                        border-radius: 6px;
                        font-weight: 600;
                        margin: 20px 0;
                        text-align: center;
                    }
                    .button:hover {
                        opacity: 0.9;
                    }
                    .badge {
                        display: inline-block;
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-size: 12px;
                        font-weight: 600;
                        margin: 2px;
                    }
                    .badge-danger { background-color: #dc3545; color: #fff; }
                    .badge-warning { background-color: #ffc107; color: #000; }
                    .badge-info { background-color: #17a2b8; color: #fff; }
                    .badge-success { background-color: #28a745; color: #fff; }
                    .badge-secondary { background-color: #6c757d; color: #fff; }
                    .email-footer {
                        background-color: #f8f9fa;
                        padding: 20px;
                        text-align: center;
                        font-size: 12px;
                        color: #6c757d;
                        border-top: 1px solid #e9ecef;
                    }
                    .description {
                        background-color: #f8f9fa;
                        padding: 15px;
                        border-radius: 4px;
                        margin: 15px 0;
                        font-size: 13px;
                    }
                    .meta-info {
                        margin: 15px 0;
                        font-size: 13px;
                    }
                    .meta-info dt {
                        display: inline-block;
                        font-weight: 600;
                        color: #4b9ad9;
                        margin-right: 5px;
                    }
                    .meta-info dd {
                        display: inline;
                        margin: 0;
                    }
                    .warning {
                        background-color: #fff3cd;
                        border-left: 4px solid #ffc107;
                        padding: 15px;
                        margin: 20px 0;
                        border-radius: 4px;
                    }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="email-header">
                        <h1>🎫 REDAXO Issue Tracker</h1>
                    </div>
                    <div class="email-body">
                        {$content}
                    </div>
                    <div class="email-footer">
                        <p>Diese E-Mail wurde automatisch vom REDAXO Issue Tracker generiert.</p>
                        <p style="margin-top: 10px; font-size: 11px;">
                            <em>Bitte antworten Sie nicht direkt auf diese E-Mail.</em>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            HTML;
    }

    /**
     * Gibt die Standard-HTML-Templates zurück.
     *
     * @return array<string, string>
     */
    public static function getDefaultHtmlTemplates(): array
    {
        return [
            // Neue Issue - Deutsch
            'email_template_new_issue_de' => <<<'HTML'
                <h2>Neues Issue erstellt</h2>
                <p>Hallo {{recipient_name}},</p>
                <p>es wurde ein neues Issue erstellt:</p>

                <div class="info-box">
                    <strong>Titel:</strong> {{issue_title}}<br>
                    <strong>Kategorie:</strong> <span class="badge badge-info">{{issue_category}}</span><br>
                    <strong>Priorität:</strong> <span class="badge badge-warning">{{issue_priority}}</span><br>
                    <strong>Erstellt von:</strong> {{creator_name}}
                </div>

                <h3>Beschreibung:</h3>
                <div class="description">{{issue_description}}</div>

                <a href="{{issue_url}}" class="button">Issue ansehen →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Hinweis: Dieser Link ist nur einmal verwendbar und 30 Tage gültig.</em>
                </p>
                HTML,

            // Neue Issue - Englisch
            'email_template_new_issue_en' => <<<'HTML'
                <h2>New Issue Created</h2>
                <p>Hello {{recipient_name}},</p>
                <p>a new issue was created:</p>

                <div class="info-box">
                    <strong>Title:</strong> {{issue_title}}<br>
                    <strong>Category:</strong> <span class="badge badge-info">{{issue_category}}</span><br>
                    <strong>Priority:</strong> <span class="badge badge-warning">{{issue_priority}}</span><br>
                    <strong>Created by:</strong> {{creator_name}}
                </div>

                <h3>Description:</h3>
                <div class="description">{{issue_description}}</div>

                <a href="{{issue_url}}" class="button">View Issue →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Note: This link is valid for one-time use and 30 days.</em>
                </p>
                HTML,

            // Neuer Kommentar - Deutsch
            'email_template_new_comment_de' => <<<'HTML'
                <h2>Neuer Kommentar</h2>
                <p>Hallo {{recipient_name}},</p>
                <p>es wurde ein neuer Kommentar zu Issue <strong>#{{issue_id}}</strong> hinzugefügt:</p>

                <div class="info-box">
                    <strong>Issue:</strong> {{issue_title}}<br>
                    <strong>Kommentar von:</strong> {{creator_name}}
                </div>

                <h3>Kommentar:</h3>
                <div class="description">{{comment_text}}</div>

                <a href="{{issue_url}}" class="button">Zum Issue →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Hinweis: Dieser Link ist nur einmal verwendbar und 30 Tage gültig.</em>
                </p>
                HTML,

            // Neuer Kommentar - Englisch
            'email_template_new_comment_en' => <<<'HTML'
                <h2>New Comment</h2>
                <p>Hello {{recipient_name}},</p>
                <p>a new comment was added to issue <strong>#{{issue_id}}</strong>:</p>

                <div class="info-box">
                    <strong>Issue:</strong> {{issue_title}}<br>
                    <strong>Comment by:</strong> {{creator_name}}
                </div>

                <h3>Comment:</h3>
                <div class="description">{{comment_text}}</div>

                <a href="{{issue_url}}" class="button">View Issue →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Note: This link is valid for one-time use and 30 days.</em>
                </p>
                HTML,

            // Status-Änderung - Deutsch
            'email_template_status_change_de' => <<<'HTML'
                <h2>Status geändert</h2>
                <p>Hallo {{recipient_name}},</p>
                <p>der Status von Issue <strong>#{{issue_id}}</strong> wurde geändert:</p>

                <div class="info-box">
                    <strong>Issue:</strong> {{issue_title}}<br>
                    <strong>Alter Status:</strong> <span class="badge badge-secondary">{{old_status}}</span><br>
                    <strong>Neuer Status:</strong> <span class="badge badge-success">{{new_status}}</span>
                </div>

                <a href="{{issue_url}}" class="button">Zum Issue →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Hinweis: Dieser Link ist nur einmal verwendbar und 30 Tage gültig.</em>
                </p>
                HTML,

            // Status-Änderung - Englisch
            'email_template_status_change_en' => <<<'HTML'
                <h2>Status Changed</h2>
                <p>Hello {{recipient_name}},</p>
                <p>the status of issue <strong>#{{issue_id}}</strong> was changed:</p>

                <div class="info-box">
                    <strong>Issue:</strong> {{issue_title}}<br>
                    <strong>Old status:</strong> <span class="badge badge-secondary">{{old_status}}</span><br>
                    <strong>New status:</strong> <span class="badge badge-success">{{new_status}}</span>
                </div>

                <a href="{{issue_url}}" class="button">View Issue →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Note: This link is valid for one-time use and 30 days.</em>
                </p>
                HTML,

            // Zuweisung - Deutsch
            'email_template_assignment_de' => <<<'HTML'
                <h2>Issue zugewiesen</h2>
                <p>Hallo {{recipient_name}},</p>
                <p>Ihnen wurde ein Issue zugewiesen:</p>

                <div class="info-box">
                    <strong>Issue:</strong> #{{issue_id}} - {{issue_title}}<br>
                    <strong>Kategorie:</strong> <span class="badge badge-info">{{issue_category}}</span><br>
                    <strong>Priorität:</strong> <span class="badge badge-warning">{{issue_priority}}</span>
                </div>

                <h3>Beschreibung:</h3>
                <div class="description">{{issue_description}}</div>

                <a href="{{issue_url}}" class="button">Issue ansehen →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Hinweis: Dieser Link ist nur einmal verwendbar und 30 Tage gültig.</em>
                </p>
                HTML,

            // Zuweisung - Englisch
            'email_template_assignment_en' => <<<'HTML'
                <h2>Issue Assigned</h2>
                <p>Hello {{recipient_name}},</p>
                <p>an issue was assigned to you:</p>

                <div class="info-box">
                    <strong>Issue:</strong> #{{issue_id}} - {{issue_title}}<br>
                    <strong>Category:</strong> <span class="badge badge-info">{{issue_category}}</span><br>
                    <strong>Priority:</strong> <span class="badge badge-warning">{{issue_priority}}</span>
                </div>

                <h3>Description:</h3>
                <div class="description">{{issue_description}}</div>

                <a href="{{issue_url}}" class="button">View Issue →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Note: This link is valid for one-time use and 30 days.</em>
                </p>
                HTML,

            // Erinnerung - Deutsch
            'email_template_reminder_de' => <<<'HTML'
                <h2 style="color: #dc3545;">⏰ Erinnerung</h2>
                <p>Hallo {{recipient_name}},</p>
                <p>Sie werden an folgendes Issue erinnert, das Ihnen zugewiesen ist:</p>
                <p style="font-size: 13px; color: #666;">Gesendet von <strong>{{sent_by_name}}</strong></p>

                <div class="info-box" style="border-left-color: #dc3545;">
                    <strong>Issue:</strong> #{{issue_id}} - {{issue_title}}<br>
                    <strong>Status:</strong> <span class="badge badge-danger">{{issue_status}}</span><br>
                    <strong>Kategorie:</strong> <span class="badge badge-info">{{issue_category}}</span><br>
                    <strong>Priorität:</strong> <span class="badge badge-warning">{{issue_priority}}</span>
                    {{due_date}}
                </div>

                <h3>Beschreibung:</h3>
                <div class="description">{{issue_description}}</div>

                <a href="{{issue_url}}" class="button" style="background: #dc3545; background-color: #dc3545;">Issue ansehen →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Hinweis: Dieser Link ist nur einmal verwendbar und 30 Tage gültig.</em>
                </p>
                HTML,

            // Erinnerung - Englisch
            'email_template_reminder_en' => <<<'HTML'
                <h2 style="color: #dc3545;">⏰ Reminder</h2>
                <p>Hello {{recipient_name}},</p>
                <p>You are being reminded about an issue assigned to you:</p>
                <p style="font-size: 13px; color: #666;">Sent by <strong>{{sent_by_name}}</strong></p>

                <div class="info-box" style="border-left-color: #dc3545;">
                    <strong>Issue:</strong> #{{issue_id}} - {{issue_title}}<br>
                    <strong>Status:</strong> <span class="badge badge-danger">{{issue_status}}</span><br>
                    <strong>Category:</strong> <span class="badge badge-info">{{issue_category}}</span><br>
                    <strong>Priority:</strong> <span class="badge badge-warning">{{issue_priority}}</span>
                    {{due_date}}
                </div>

                <h3>Description:</h3>
                <div class="description">{{issue_description}}</div>

                <a href="{{issue_url}}" class="button" style="background: #dc3545; background-color: #dc3545;">View Issue →</a>

                <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                    <em>Note: This link is valid for one-time use and 30 days.</em>
                </p>
                HTML,
        ];
    }

    /**
     * Setzt alle E-Mail-Templates auf die Standardwerte zurück.
     *
     * @return int Anzahl der zurückgesetzten Templates
     */
    public static function resetToDefaults(): int
    {
        $templates = self::getDefaultHtmlTemplates();
        $count = 0;

        foreach ($templates as $key => $value) {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('issue_tracker_settings'));
            $sql->setWhere(['setting_key' => $key]);
            $sql->setValue('setting_value', $value);

            if ($sql->update()) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Holt ein E-Mail-Template aus der Datenbank.
     *
     * @param string $key Der Template-Key
     * @return string|null Das Template oder null
     */
    public static function getTemplate(string $key): ?string
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = ?', [$key]);

        if ($sql->getRows() > 0) {
            return (string) $sql->getValue('setting_value');
        }

        return null;
    }

    /**
     * Speichert ein E-Mail-Template.
     *
     * @param string $key Der Template-Key
     * @param string $value Der Template-Inhalt
     * @return bool True bei Erfolg
     */
    public static function saveTemplate(string $key, string $value): bool
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('issue_tracker_settings'));
        $sql->setWhere(['setting_key' => $key]);
        $sql->setValue('setting_value', $value);

        return $sql->update() > 0;
    }
}
