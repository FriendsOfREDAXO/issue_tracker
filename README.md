# REDAXO Issue Tracker

Ein vollständiger Issue-Tracker für REDAXO CMS, der es Redakteuren ermöglicht, Wünsche zu äußern, Probleme zu melden und Vorschläge zu machen.

## Features

### Kernfunktionen
- ✅ **Issue-Verwaltung**: Erstellen, Bearbeiten, Löschen und Kommentieren von Issues
- ✅ **Dashboard**: Übersicht über den aktuellen Status aller Issues
- ✅ **Status-Tracking**: Offen, In Arbeit, Abgelehnt, Erledigt
- ✅ **Prioritäten**: Niedrig, Normal, Hoch, Kritisch
- ✅ **Kategorien**: Redaktion, Technik, AddOn, Support, Medien, Struktur (erweiterbar)
- ✅ **Tags**: Flexible Tag-Verwaltung mit Farbcodierung
- ✅ **Kommentare**: Diskussion und Feedback zu jedem Issue
- ✅ **Zuweisungen**: Issues können Benutzern und AddOns zugeordnet werden
- ✅ **Versionsverwaltung**: Issues können einer Version zugeordnet werden
- ✅ **Fälligkeitsdatum**: Issues können mit Deadlines versehen werden, überfällige Issues werden markiert
- ✅ **Aktivitätsverlauf**: Vollständiges Tracking aller Änderungen an Issues
- ✅ **Dateianhänge**: Upload von Bildern, Dokumenten und anderen Dateien zu Issues
- ✅ **Gespeicherte Filter**: Häufig verwendete Filtereinstellungen speichern und als Standard festlegen
- ✅ **Sortierbare Listen**: Alle Spalten können aufsteigend/absteigend sortiert werden

### E-Mail-Benachrichtigungen
- ✅ Automatische Benachrichtigungen bei neuen Issues
- ✅ Benachrichtigungen bei neuen Kommentaren
- ✅ Benachrichtigungen bei Status-Änderungen
- ✅ Benachrichtigungen bei Zuweisungen
- ✅ Broadcast-Nachrichten an alle Benutzer (nur Admins)
- ✅ Individuelle Benachrichtigungseinstellungen pro Benutzer

### Berechtigungen
- **issue_tracker[]**: Basis-Berechtigung für Zugriff auf den Issue Tracker
- **issue_tracker[issuer]**: Erweiterte Berechtigung für das Erstellen und Kommentieren von Issues
- **admin**: Vollzugriff inkl. Einstellungen, Löschen und Broadcast-Nachrichten

## Installation

1. Addon-Ordner nach `/redaxo/src/addons/issue_tracker/` kopieren
2. Im REDAXO-Backend unter "AddOns" das AddOn installieren und aktivieren
3. Berechtigungen für Benutzer einrichten (siehe unten)

## Konfiguration

### Berechtigungen einrichten

1. Gehe zu "Benutzer" → "Rollen"
2. Bearbeite eine Rolle oder erstelle eine neue
3. Aktiviere die Berechtigung "Issue Tracker [issue_tracker[]]"
4. Optional: Aktiviere "Issue Tracker Issuer [issue_tracker[issuer]]" für erweiterte Rechte

### Kategorien anpassen

1. Gehe zu "Issue Tracker" → "Einstellungen"
2. Bearbeite die Kategorien nach Bedarf
3. Füge neue Kategorien hinzu oder entferne bestehende
4. Speichern nicht vergessen!

### E-Mail-Einstellungen

1. Gehe zu "Issue Tracker" → "Einstellungen"
2. Aktiviere oder deaktiviere E-Mail-Benachrichtigungen
3. Passe den Absender-Namen an
4. Stelle sicher, dass PHPMailer korrekt konfiguriert ist

## Verwendung

### Neues Issue erstellen

1. Klicke auf "Issue Tracker" → "Issues" → "Neues Issue"
2. Fülle das Formular aus:
   - **Titel**: Kurze, prägnante Beschreibung
   - **Beschreibung**: Detaillierte Beschreibung des Problems/Wunsches (Markdown-Unterstützung)
   - **Kategorie**: Wähle die passende Kategorie
   - **Status**: Standardmäßig "Offen"
   - **Priorität**: Setze die Priorität
   - **Fälligkeit**: Optional Deadline setzen
   - **Zuweisungen**: Optional User oder AddOn zuweisen
   - **Tags**: Optional Tags hinzufügen
   - **Dateianhänge**: Optional Dateien hochladen (Bilder, PDFs, Dokumente)
3. Klicke auf "Speichern"

### Issue bearbeiten

1. Klicke in der Liste auf ein Issue oder auf den Bearbeiten-Button
2. Bearbeite die Felder nach Bedarf
3. Füge Kommentare hinzu
4. Ändere den Status
5. Speichern

### Kommentare hinzufügen

1. Öffne ein bestehendes Issue
2. Scrolle zum Kommentar-Bereich
3. Gib deinen Kommentar ein
4. Optional: Markiere den Kommentar als "Intern" (nur für Admins sichtbar)
5. Klicke auf "Kommentar hinzufügen"

### Broadcast-Nachricht senden (nur Admins)

1. Gehe zu "Issue Tracker" → "Einstellungen"
2. Scrolle zum Bereich "Broadcast-Nachricht"
3. Gib Betreff und Nachricht ein
4. Klicke auf "Broadcast senden"
5. Bestätige die Aktion

## Dashboard

Das Dashboard zeigt:
- Anzahl offener Issues
- Anzahl Issues in Arbeit
- Anzahl geplanter Issues
- Anzahl erledigter Issues (letzte 30 Tage)
- Issues nach Kategorie
- Die 10 neuesten Issues

## Filter und Suche

In der Issues-Liste kannst du:
- Nach Status filtern
- Nach Kategorie filtern
- Nach Titel oder Beschreibung suchen
- Filter kombinieren

## Benachrichtigungen

### Benachrichtigungstypen

Benutzer werden automatisch benachrichtigt bei:
- Neuen Issues
- Neuen Kommentaren
- Status-Änderungen
- Zuweisungen

### Benachrichtigungseinstellungen anpassen

Benachrichtigungseinstellungen können in der Datenbanktabelle `rex_issue_tracker_notifications` pro Benutzer angepasst werden.

## Technische Details

### Datenbank-Tabellen

- `rex_issue_tracker_issues`: Haupt-Tabelle für Issues
- `rex_issue_tracker_comments`: Kommentare zu Issues
- `rex_issue_tracker_tags`: Tag-Definitionen
- `rex_issue_tracker_issue_tags`: Zuordnung Issues ↔ Tags
- `rex_issue_tracker_notifications`: Benachrichtigungseinstellungen
- `rex_issue_tracker_settings`: Globale Einstellungen

### PHP-Klassen

- `FriendsOfREDAXO\IssueTracker\Issue`: Issue-Model
- `FriendsOfREDAXO\IssueTracker\Comment`: Comment-Model
- `FriendsOfREDAXO\IssueTracker\Tag`: Tag-Model
- `FriendsOfREDAXO\IssueTracker\NotificationService`: E-Mail-Benachrichtigungen

### Assets

- `assets/issue_tracker.css`: Styling
- `assets/issue_tracker.js`: JavaScript-Funktionalität

## Erweiterungen

### Eigene Kategorien hinzufügen

Kategorien können über die Einstellungen verwaltet werden.

### Eigene Status hinzufügen

Status sind aktuell fest im Code definiert. Für Erweiterungen die `install.php` anpassen.

### Eigene E-Mail-Templates

Templates befinden sich in `lib/NotificationService.php` in den Methoden:
- `getNewIssueTemplate()`
- `getNewCommentTemplate()`
- `getStatusChangeTemplate()`
- `getAssignmentTemplate()`

## Support

Bei Fragen oder Problemen:
- Issue im REDAXO Slack erstellen
- Issue auf GitHub erstellen: https://github.com/FriendsOfREDAXO/issue_tracker

## Changelog

### Version 1.0.0 (2026-01-07)
- Initial Release
- Vollständige Issue-Verwaltung mit verschachtelten Kommentaren
- E-Mail-Benachrichtigungen mit Deep-Links
- Personalisiertes Dashboard
- Tag-System mit Farbcodierung
- Kommentar-System mit Pin- und Lösungs-Markierung
- Kommentar-Antworten (Thread-System)
- Filter und Suche mit speicherbaren Filtern
- Broadcast-Funktion
- Aktivitätsverlauf
- Dateianhang-Verwaltung
- Backup/Export und Import-Funktion

## Credits

**Issue Tracker** wurde von **[Thomas Skerbis](https://github.com/skerbis)** für **REDAXO CMS** entwickelt.

## Lizenz

MIT License - siehe [LICENSE.md](LICENSE.md)

## Autor

**Thomas Skerbis**
- GitHub: [@skerbis](https://github.com/skerbis)
- REDAXO Slack: @skerbis
