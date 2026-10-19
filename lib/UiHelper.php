<?php

namespace FriendsOfREDAXO\IssueTracker;

use DateTimeInterface;
use rex_formatter;
use rex_i18n;
use rex_user;

/**
 * Kleine Bausteine für die Oberfläche (Avatare, Datum wie in Mail, Kennzeichen für Status und Priorität).
 * Alle Texte werden hier escaped.
 */
final class UiHelper
{
    /** Avatar-Farben mit ausreichendem Kontrast zu weißer Schrift */
    private const AVATAR_COLORS = ['blue', 'green', 'red', 'purple', 'indigo', 'pink', 'teal'];

    public static function initials(string $name): string
    {
        $parts = preg_split('/[\s._-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $parts) {
            return '?';
        }
        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($first . $last);
    }

    public static function userName(?rex_user $user, string $fallback = ''): string
    {
        if (!$user) {
            return $fallback;
        }
        return (string) ($user->getValue('name') ?: $user->getLogin());
    }

    /** Runder Avatar mit Initialen; Farbe ist pro Benutzer fest */
    public static function avatar(int $userId, string $name, bool $small = false): string
    {
        $color = self::AVATAR_COLORS[abs($userId) % count(self::AVATAR_COLORS)];
        return '<span class="it-avatar' . ($small ? ' it-avatar--sm' : '') . ' it-c-' . $color . '" title="' . rex_escape($name) . '" aria-hidden="true">'
            . rex_escape(self::initials($name)) . '</span>';
    }

    /** Datum wie in Mail: heute Uhrzeit, gestern, Wochentag, sonst Datum */
    public static function relDate(?DateTimeInterface $date): string
    {
        if (!$date) {
            return '';
        }
        $ts = $date->getTimestamp();
        $today = strtotime('today');
        if ($ts >= $today) {
            return date('H:i', $ts);
        }
        if ($ts >= $today - 86400) {
            return rex_i18n::rawMsg('issue_tracker_ui_yesterday');
        }
        if ($ts >= $today - 6 * 86400) {
            return rex_formatter::intlDate($ts, 'EEEE');
        }
        return date('d.m.y', $ts);
    }

    public static function statusVariant(string $status): string
    {
        return match ($status) {
            'open' => 'info',
            'in_progress' => 'warn',
            'closed' => 'ok',
            'planned', 'info' => 'info',
            default => 'muted',
        };
    }

    public static function priorityVariant(string $priority): string
    {
        return match ($priority) {
            'critical', 'urgent' => 'danger',
            'high' => 'warn',
            default => 'muted',
        };
    }

    public static function badge(string $text, string $variant = 'muted'): string
    {
        return '<span class="it-badge' . ('muted' !== $variant ? ' it-badge--' . rex_escape($variant) : '') . '">' . rex_escape($text) . '</span>';
    }

    public static function statusBadge(string $status): string
    {
        return self::badge(rex_i18n::rawMsg('issue_tracker_status_' . $status), self::statusVariant($status));
    }

    public static function priorityBadge(string $priority): string
    {
        return self::badge(rex_i18n::rawMsg('issue_tracker_priority_' . $priority), self::priorityVariant($priority));
    }

    /**
     * Kopf der Einstellungsseiten (Symbol, Titel, eine Zeile Einleitung).
     * Keine eigene Seitenleiste: REDAXO zeigt die Unterseiten bereits als Navigation.
     *
     * @return array<string, array{icon: string, color: string, title: string, desc: string}>
     */
    public static function settingsSections(): array
    {
        return [
            'general' => ['icon' => 'fa-sliders', 'color' => 'gray', 'title' => 'issue_tracker_settings_general', 'desc' => 'issue_tracker_ui_settings_general_desc'],
            'email_templates' => ['icon' => 'fa-envelope', 'color' => 'blue', 'title' => 'issue_tracker_email_templates', 'desc' => 'issue_tracker_ui_settings_templates_desc'],
            'backup' => ['icon' => 'fa-archive', 'color' => 'green', 'title' => 'issue_tracker_backup', 'desc' => 'issue_tracker_ui_settings_backup_desc'],
            'preferences' => ['icon' => 'fa-bell', 'color' => 'red', 'title' => 'issue_tracker_preferences', 'desc' => 'issue_tracker_ui_settings_preferences_desc'],
        ];
    }

    /** Öffnet eine Einstellungsseite mit Kopf. Gegenstück: settingsEnd() */
    public static function settingsStart(string $active): string
    {
        $cur = self::settingsSections()[$active];
        return '<div class="it-settings"><div class="it-settings-head"><span class="it-icon it-icon--lg it-c-' . $cur['color'] . '" aria-hidden="true"><i class="rex-icon ' . $cur['icon'] . '"></i></span>'
            . '<div><h2>' . rex_escape(rex_i18n::rawMsg($cur['title'])) . '</h2><p>' . rex_escape(rex_i18n::rawMsg($cur['desc'])) . '</p></div></div>';
    }

    public static function settingsEnd(): string
    {
        return '</div>';
    }

    /** Eine Zeile: Beschriftung links, Bedienelement rechts ($controlHtml ist fertiges HTML, $help Rohtext) */
    public static function row(string $label, string $controlHtml, string $help = '', string $forId = '', bool $stack = false): string
    {
        $labelTag = '' !== $forId
            ? '<label for="' . rex_escape($forId) . '">' . rex_escape($label) . '</label>'
            : '<strong>' . rex_escape($label) . '</strong>';
        return '<div class="it-row' . ($stack ? ' it-row--stack' : '') . '"><div class="it-row-label">' . $labelTag
            . ('' !== $help ? '<small>' . rex_escape($help) . '</small>' : '') . '</div>'
            . '<div class="it-row-control">' . $controlHtml . '</div></div>';
    }

    /** Schalter mit verstecktem 0-Wert, damit ausgeschaltet auch gespeichert wird */
    public static function switch(string $name, bool $checked, string $id, string $ariaLabel = ''): string
    {
        return '<span class="it-switch"><input type="hidden" name="' . rex_escape($name) . '" value="0">'
            . '<input type="checkbox" role="switch" id="' . rex_escape($id) . '" name="' . rex_escape($name) . '" value="1"' . ($checked ? ' checked' : '')
            . ('' !== $ariaLabel ? ' aria-label="' . rex_escape($ariaLabel) . '"' : '') . '><span></span></span>';
    }

    /**
     * Formularzeile mit Beschriftung, Hinweis, Pflichtfeld-Kennzeichen und Fehlermeldung direkt am Feld.
     * $controlHtml ist fertiges HTML; das Feld mit der ID $forId bekommt aria-describedby/aria-invalid.
     */
    public static function field(string $label, string $controlHtml, string $help = '', string $forId = '', bool $required = false, string $error = '', bool $stack = false): string
    {
        $labelHtml = rex_escape($label) . ($required ? ' <span class="it-req">' . rex_escape(rex_i18n::rawMsg('issue_tracker_ui_required')) . '</span>' : '');
        $labelTag = '' !== $forId ? '<label for="' . rex_escape($forId) . '">' . $labelHtml . '</label>' : '<strong>' . $labelHtml . '</strong>';
        $describedBy = [];
        if ('' !== $help && '' !== $forId) {
            $describedBy[] = $forId . '-help';
        }
        if ('' !== $error && '' !== $forId) {
            $describedBy[] = $forId . '-error';
        }
        if ($describedBy) {
            $attr = ' aria-describedby="' . rex_escape(implode(' ', $describedBy)) . '"' . ('' !== $error ? ' aria-invalid="true"' : '');
            $controlHtml = preg_replace('/ id="' . preg_quote($forId, '/') . '"/', ' id="' . $forId . '"' . $attr, $controlHtml, 1) ?? $controlHtml;
        }
        return '<div class="it-row' . ($stack ? ' it-row--stack' : '') . ('' !== $error ? ' it-row--error' : '') . '"><div class="it-row-label">' . $labelTag
            . ('' !== $help ? '<small' . ('' !== $forId ? ' id="' . rex_escape($forId) . '-help"' : '') . '>' . rex_escape($help) . '</small>' : '')
            . ('' !== $error ? '<small class="it-field-error"' . ('' !== $forId ? ' id="' . rex_escape($forId) . '-error"' : '') . '><i class="rex-icon fa-exclamation-circle" aria-hidden="true"></i> ' . rex_escape($error) . '</small>' : '')
            . '</div><div class="it-row-control">' . $controlHtml . '</div></div>';
    }

    /**
     * Auswahl mit wenigen Möglichkeiten als Segment-Leiste (Radio-Buttons mit dem bisherigen Feldnamen).
     *
     * @param array<string, string> $options Wert => Beschriftung (Rohtext)
     */
    public static function segmentedRadio(string $name, array $options, string $current, string $id, string $ariaLabel): string
    {
        $html = '<div class="it-seg" role="radiogroup" id="' . rex_escape($id) . '" aria-label="' . rex_escape($ariaLabel) . '">';
        $i = 0;
        foreach ($options as $value => $label) {
            $oid = $id . '-' . $i++;
            $html .= '<input type="radio" name="' . rex_escape($name) . '" id="' . rex_escape($oid) . '" value="' . rex_escape((string) $value) . '"' . ((string) $value === $current ? ' checked' : '') . '>'
                . '<label for="' . rex_escape($oid) . '">' . rex_escape($label) . '</label>';
        }
        return $html . '</div>';
    }

    /** Aufklappbarer Bereich „Erweitert“ für selten genutzte Einstellungen ($innerHtml ist fertiges HTML) */
    public static function advanced(string $innerHtml, bool $open, string $hint = ''): string
    {
        return '<details class="it-advanced"' . ($open ? ' open' : '') . '><summary><span class="it-advanced-title">'
            . rex_escape(rex_i18n::rawMsg('issue_tracker_ui_advanced')) . '</span>'
            . ('' !== $hint ? '<small>' . rex_escape($hint) . '</small>' : '') . '</summary>'
            . '<div class="it-advanced-body">' . $innerHtml . '</div></details>';
    }

    /**
     * Zusammenfassung der Fehler oben im Formular, mit Sprungmarken zu den Feldern.
     *
     * @param array<string, string> $errors Feld-ID => Meldung (Rohtext)
     */
    public static function errorSummary(array $errors): string
    {
        if (!$errors) {
            return '';
        }
        $items = '';
        foreach ($errors as $fieldId => $message) {
            $items .= '<li><a href="#' . rex_escape($fieldId) . '">' . rex_escape($message) . '</a></li>';
        }
        return '<div class="alert alert-danger it-error-summary" role="alert"><strong>'
            . rex_escape(rex_i18n::rawMsg('issue_tracker_ui_error_summary')) . '</strong><ul>' . $items . '</ul></div>';
    }

    /** Projektfarbe als CSS-Variable (nur gültige Hex-Werte) */
    public static function colorVar(string $color): string
    {
        return preg_match('/^#[0-9a-f]{3,8}$/i', $color) ? '--c:' . $color : '--c:#7c7c82';
    }
}
