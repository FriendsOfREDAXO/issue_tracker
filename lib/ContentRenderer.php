<?php

/**
 * ContentRenderer – rendert Markdown-Inhalte mit erweiterten Features:
 * - Interaktive Checklisten (- [ ] / - [x])
 * - Issue-Referenzen (#42 → Link)
 * - /spent Zeiterfassung (Hilfs-Methoden)
 *
 * @package issue_tracker
 */

namespace FriendsOfREDAXO\IssueTracker;

class ContentRenderer
{
    /**
     * Rendert Markdown-Text mit interaktiven Checklisten und #Issue-Links.
     *
     * @param string $text       Roh-Markdown
     * @param int    $issueId    ID des aktuellen Issues (für Checklist-Toggle-URL)
     * @param int|null $commentId ID des Kommentars (null = Beschreibung)
     */
    public static function render(string $text, int $issueId, ?int $commentId = null): string
    {
        // /spent-Befehl aus der Anzeige entfernen
        $displayText = self::stripSpentCommand($text);

        $html = \rex_markdown::factory()->parse($displayText);

        // 1. #Issue-Referenzen verlinken (außerhalb von <code>/<pre>/<a>)
        $html = self::linkIssueRefs($html);

        // 2. Deaktivierte Checkboxen aus ParsedownExtra interaktiv machen
        $html = self::makeCheckboxesInteractive($html, $issueId, $commentId);

        return $html;
    }

    /**
     * Ersetzt #42 außerhalb von Code-Blöcken und Links mit Backend-Links.
     */
    private static function linkIssueRefs(string $html): string
    {
        // Trenne HTML-String in geschützte (code/pre/a) und freie Segmente
        $parts = preg_split(
            '#(<(?:code|pre|a)[^>]*>.*?</(?:code|pre|a)>)#si',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $result = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                // Freies Segment: #42 ersetzen – nur wenn von Nicht-Wortzeichen umgeben
                // (verhindert Treffer in foo#1 oder #1bar, aber matcht "fixes #42." oder "siehe #7")
                $result .= preg_replace_callback(
                    '/(?<!\w)#(\d+)(?!\w)/',
                    static function (array $m): string {
                        $refId = (int) $m[1];
                        // false = rohe URL (mit &), rex_escape kodiert dann korrekt zu &amp; im href-Attribut
                        $url = \rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $refId], false);
                        return '<a href="' . \rex_escape($url) . '" class="it-issue-ref" title="Issue #' . $refId . '">#' . $refId . '</a>';
                    },
                    $part
                );
            } else {
                // Geschütztes Segment: unverändert
                $result .= $part;
            }
        }

        return $result;
    }

    /**
     * Ersetzt Checklist-Items aus ParsedownExtra (rendert `- [ ]` als `<li>[ ] ...</li>`)
     * mit interaktiven Formularen zum Umschalten.
     */
    private static function makeCheckboxesInteractive(string $html, int $issueId, ?int $commentId): string
    {
        $source = $commentId !== null ? 'comment_' . $commentId : 'description';
        // rex_url::backendPage() gibt bereits HTML-kodierte URL zurück (mit &amp;) – kein rex_escape() nötig
        $url = \rex_url::backendPage('issue_tracker/issues/view', ['issue_id' => $issueId], false);
        $checklistIndex = 0;

        // ParsedownExtra rendert `- [ ] Task` als `<li>[ ] Task</li>` und `- [x] Done` als `<li>[x] Done</li>`
        return preg_replace_callback(
            '/<li>\s*\[([x ]?)\](.*?)<\/li>/si',
            static function (array $m) use ($url, $source, &$checklistIndex): string {
                $checked = strtolower(trim($m[1])) === 'x';
                $labelHtml = trim($m[2]);
                $index = $checklistIndex++;

                return sprintf(
                    '<li class="it-task-item">'
                    . '<form method="post" action="%s" style="display:inline;margin:0 4px 0 0;">'
                    . '<input type="hidden" name="toggle_checklist" value="1">'
                    . '<input type="hidden" name="cl_source" value="%s">'
                    . '<input type="hidden" name="cl_index" value="%d">'
                    . '<input type="checkbox" %s onchange="this.form.submit()" style="cursor:pointer;vertical-align:middle;"> %s'
                    . '</form>'
                    . '</li>',
                    \rex_escape($url),
                    \rex_escape($source),
                    $index,
                    $checked ? 'checked' : '',
                    $labelHtml
                );
            },
            $html
        ) ?? $html;
    }

    /**
     * Toggled ein Checklisten-Item per Index im Roh-Markdown.
     *
     * @param string $text  Roh-Markdown
     * @param int    $index 0-basierter Index des Items
     */
    public static function toggleChecklistItem(string $text, int $index): string
    {
        $count = -1;

        return preg_replace_callback(
            '/^(- \[)([x ])(\])/m',
            static function (array $m) use ($index, &$count): string {
                ++$count;
                if ($count === $index) {
                    $newState = $m[2] === 'x' ? ' ' : 'x';
                    return $m[1] . $newState . $m[3];
                }
                return $m[0];
            },
            $text
        ) ?? $text;
    }

    /**
     * Gibt Checklisten-Fortschritt zurück: ['total' => N, 'checked' => N]
     *
     * @return array{total: int, checked: int}
     */
    public static function getChecklistProgress(string $text): array
    {
        preg_match_all('/^- \[.\]/m', $text, $all);
        preg_match_all('/^- \[x\]/mi', $text, $checked);
        return [
            'total'   => count($all[0]),
            'checked' => count($checked[0]),
        ];
    }

    /**
     * Extrahiert die Zeitangabe aus einem /spent-Befehl in Minuten.
     * Unterstützt: /spent 2h, /spent 30m, /spent 1h 30m, /spent 1h30m, /spent 90min
     */
    public static function extractSpentMinutes(string $text): int
    {
        if (!preg_match('/\/spent\s+(.+?)(?:\n|$)/i', $text, $m)) {
            return 0;
        }

        $spentStr = trim($m[1]);
        $hours = 0;
        $mins = 0;

        if (preg_match('/(\d+)\s*(?:h|hr|hrs|std|hour|hours)/i', $spentStr, $hm)) {
            $hours = (int) $hm[1];
        }
        if (preg_match('/(\d+)\s*(?:m|min|mins|minute|minutes|minuten?)/i', $spentStr, $mm)) {
            $mins = (int) $mm[1];
        }

        // Fallback: reine Zahl ohne Einheit → Minuten
        if ($hours === 0 && $mins === 0 && preg_match('/^(\d+)$/', $spentStr, $nm)) {
            $mins = (int) $nm[1];
        }

        return $hours * 60 + $mins;
    }

    /**
     * Entfernt den /spent-Befehl aus dem Text (für Anzeige).
     */
    public static function stripSpentCommand(string $text): string
    {
        return trim(preg_replace('/^\/spent\s+[^\n]*\n?/m', '', $text));
    }

    /**
     * Formatiert Minuten leserlich als "1h 30m", "45m" oder "2h".
     */
    public static function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }
        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;
        if ($hours > 0 && $mins > 0) {
            return $hours . 'h ' . $mins . 'm';
        }
        if ($hours > 0) {
            return $hours . 'h';
        }
        return $mins . 'm';
    }
}
