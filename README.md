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
- ✅ **Private Issues**: Issues können als privat markiert werden (nur für Ersteller und Admins sichtbar)
- ✅ **Kommentare**: Diskussion und Feedback zu jedem Issue
- ✅ **Interne Kommentare**: Kommentare können als intern markiert werden (nur für Admins sichtbar)
- ✅ **Zuweisungen**: Issues können Benutzern und AddOns zugeordnet werden
- ✅ **Versionsverwaltung**: Issues können einer Version zugeordnet werden
- ✅ **Fälligkeitsdatum**: Issues können mit Deadlines versehen werden, überfällige Issues werden markiert
- ✅ **Aktivitätsverlauf**: Vollständiges Tracking aller Änderungen an Issues
- ✅ **Dateianhänge**: Upload von Bildern, Dokumenten und anderen Dateien zu Issues
- ✅ **Gespeicherte Filter**: Häufig verwendete Filtereinstellungen speichern und als Standard festlegen
- ✅ **Erweiterte Filter**: Nach Status, Kategorie, Tags, Ersteller und Text durchsuchbar
- ✅ **Sortierbare Listen**: Alle Spalten können aufsteigend/absteigend sortiert werden

### E-Mail-Benachrichtigungen
- ✅ Automatische Benachrichtigungen bei neuen Issues
- ✅ Benachrichtigungen bei neuen Kommentaren
- ✅ Benachrichtigungen bei Status-Änderungen
- ✅ Benachrichtigungen bei Zuweisungen
- ✅ **HTML E-Mail Templates** mit professionellem Design
- ✅ **Deep Links** mit One-Time-Token (30 Tage gültig)
- ✅ Mehrsprachige Templates (Deutsch/Englisch)
- ✅ Broadcast-Nachrichten an alle Benutzer (nur Admins)
- ✅ Individuelle Benachrichtigungseinstellungen pro Benutzer

### Berechtigungen
- **issue_tracker[]**: Basis-Berechtigung für Zugriff auf den Issue Tracker
- **issue_tracker[issuer]**: Erweiterte Berechtigung für das Erstellen und Kommentieren von Issues
- **admin**: Vollzugriff inkl. Einstellungen, Löschen, Private Issues erstellen und Broadcast-Nachrichten

## Installation

1. Addon-Ordner nach `/redaxo/src/addons/issue_tracker/` kopieren
2. Im REDAXO-Backend unter "AddOns" das AddOn installieren und aktivieren
3. **Media Manager Typen erstellen** (wichtig für Dateianhänge!)
4. Berechtigungen für Benutzer einrichten (siehe unten)

### Media Manager Typen einrichten

Das Issue Tracker AddOn benötigt zwei Media Manager Typen für die Anzeige von Dateianhängen:

#### 1. `issue_tracker_attachment` (Vollansicht)

Gehe zu "Media Manager" → "Medientypen" → "Typ hinzufügen":
- **Name**: `issue_tracker_attachment`
- **Effekt hinzufügen**: "Issue Tracker Attachment"
- Optional weitere Effekte wie "resize" oder "compress" hinzufügen

#### 2. `issue_tracker_thumbnail` (Vorschaubilder)

Gehe zu "Media Manager" → "Medientypen" → "Typ hinzufügen":
- **Name**: `issue_tracker_thumbnail`
- **Effekt 1**: "Issue Tracker Attachment"
- **Effekt 2**: "resize" (Breite: 300px, Höhe: 300px, Modus: fit)

**Hinweis**: Der Effekt "Issue Tracker Attachment" wird automatisch beim Installieren des AddOns registriert.

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
   - **Privat** (nur Admins): Markiere das Issue als privat, sodass es nur du und andere Admins sehen können
3. Klicke auf "Speichern"

### Private Issues (nur Admins)

Admins können Issues als privat markieren. Private Issues sind:
- Nur für den Ersteller sichtbar
- Nur für Admins sichtbar
- In der Liste und Detail-Ansicht geschützt
- Ideal für sensible Themen oder interne Notizen

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
- Nach Status filtern (Offen, In Arbeit, Geplant, etc.)
- Nach Kategorie filtern
- Nach Tags filtern
- Nach Ersteller filtern ("Erstellt von")
- Nach Titel oder Beschreibung suchen
- Filter kombinieren und speichern
- Gespeicherte Filter als Standard festlegen

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


## Lizenz

MIT License - siehe [LICENSE.md](LICENSE.md)

## Autor

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO

## Projektleitung

[Thomas Skerbis](https://github.com/skerbis)
