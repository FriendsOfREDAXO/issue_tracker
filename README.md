# REDAXO Issue Tracker

Ein vollständiger Issue-Tracker für REDAXO CMS, der es Redakteuren ermöglicht, Wünsche zu äußern, Probleme zu melden und Vorschläge zu machen. Mit integriertem Projektmanagement und privatem Nachrichtensystem.

## Features

### Kernfunktionen
- ✅ **Issue-Verwaltung**: Erstellen, Bearbeiten, Löschen und Kommentieren von Issues
- ✅ **Dashboard**: Übersicht über den aktuellen Status aller Issues mit Statistiken und Widgets
- ✅ **Status-Tracking**: Offen, In Arbeit, Geplant, Abgelehnt, Erledigt
- ✅ **Prioritäten**: Niedrig, Normal, Hoch, Dringend
- ✅ **Kategorien**: Frei konfigurierbare Kategorien in den Einstellungen
- ✅ **Tags**: Flexible Tag-Verwaltung mit Farbcodierung und Zufallsfarben für neue Tags
- ✅ **Private Issues**: Issues können als privat markiert werden (nur für Ersteller und Admins sichtbar)
- ✅ **Kommentare**: Diskussion und Feedback zu jedem Issue mit Thread-System
- ✅ **Interne Kommentare**: Kommentare können als intern markiert werden (nur für Admins sichtbar)
- ✅ **Zuweisungen**: Issues können Benutzern und AddOns zugeordnet werden
- ✅ **Versionsverwaltung**: Issues können einer Version zugeordnet werden
- ✅ **Fälligkeitsdatum**: Issues können mit Deadlines versehen werden, überfällige Issues werden markiert
- ✅ **Aktivitätsverlauf**: Vollständiges Tracking aller Änderungen an Issues
- ✅ **Dateianhänge**: Upload von Bildern, Dokumenten und anderen Dateien zu Issues
- ✅ **Gespeicherte Filter**: Häufig verwendete Filtereinstellungen speichern und als Standard festlegen
- ✅ **Session-Filter**: Filter bleiben während der Sitzung erhalten
- ✅ **Erweiterte Filter**: Nach Status, Kategorie, Tags, Ersteller und Text durchsuchbar (inkl. "Alle Issues" und "Nur Geschlossene")
- ✅ **Sortierbare Listen**: Alle Spalten können aufsteigend/absteigend sortiert werden
- ✅ **Automatisches closed_at**: Beim Schließen eines Issues wird das Datum automatisch gesetzt

### Projektmanagement
- ✅ **Projekte**: Issues können Projekten zugeordnet werden
- ✅ **Projekt-Mitglieder**: Benutzer können Projekten zugeordnet werden
- ✅ **Projektbasierte Sichtbarkeit**: Benutzer sehen nur Projekte, denen sie zugeordnet sind
- ✅ **Projekt-Übersicht**: Dashboard zeigt Projekte mit Fortschrittsanzeige
- ✅ **Issue-Gruppierung**: Issues können nach Projekten gefiltert werden

### Private Nachrichten
- ✅ **Nachrichtensystem**: Benutzer können sich gegenseitig private Nachrichten senden
- ✅ **Posteingang & Gesendet**: Übersichtliche Trennung von empfangenen und gesendeten Nachrichten
- ✅ **Konversationsansicht**: Nachrichten werden als Konversation mit demselben Partner gruppiert
- ✅ **Ungelesen-Badge**: Navigation zeigt Anzahl ungelesener Nachrichten
- ✅ **Dashboard-Widget**: Ungelesene Nachrichten werden auf dem Dashboard angezeigt
- ✅ **E-Mail-Benachrichtigung**: Optional werden Benutzer per E-Mail über neue Nachrichten informiert
- ✅ **Volltext-Option**: Nachrichten können optional vollständig in der E-Mail enthalten sein
- ✅ **Antwort-Funktion**: Direktes Antworten auf Nachrichten möglich

### E-Mail-Benachrichtigungen
- ✅ Automatische Benachrichtigungen bei neuen Issues
- ✅ Benachrichtigungen bei neuen Kommentaren
- ✅ Benachrichtigungen bei Status-Änderungen
- ✅ Benachrichtigungen bei Zuweisungen
- ✅ Benachrichtigungen bei privaten Nachrichten (optional)
- ✅ **HTML E-Mail Templates** mit professionellem Design
- ✅ **Deep Links** mit One-Time-Token (30 Tage gültig)
- ✅ Mehrsprachige Templates (Deutsch/Englisch)
- ✅ Broadcast-Nachrichten an alle Benutzer (nur Admins)
- ✅ Individuelle Benachrichtigungseinstellungen pro Benutzer

### Anpassungen
- ✅ **Menü-Titel anpassbar**: Der Menüpunkt kann in den Einstellungen umbenannt werden (z.B. "Support", "Tickets", "Anfragen")
- ✅ **Tag-Duplikate verhindern**: Doppelte Tag-Namen werden erkannt und abgelehnt
- ✅ **Dark Mode kompatibel**: Styling funktioniert im hellen und dunklen REDAXO-Theme

### Berechtigungen
- **issue_tracker[]**: Basis-Berechtigung für Zugriff auf den Issue Tracker (nur lesen)
- **issue_tracker[issuer]**: Erweiterte Berechtigung für das Erstellen und Kommentieren von Issues
- **issue_tracker[issue_manager]**: Issue Manager - kann alle Issues bearbeiten (fast wie Admin, ohne Einstellungen)
- **admin**: Vollzugriff inkl. Einstellungen, Löschen, Private Issues erstellen und Broadcast-Nachrichten

### YRewrite & YForm Integration
- **Domain-Auswahl**: Issues können optional einer YRewrite-Domain zugeordnet werden (nur sichtbar wenn YRewrite installiert ist)
- **YForm Tabellen-Auswahl**: Issues können einer YForm-Tabelle zugeordnet werden (gefiltert nach Benutzerrechten)

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

### Projekte verwalten

1. Gehe zu "Issue Tracker" → "Projekte"
2. Klicke auf "Neues Projekt"
3. Gib einen Namen und optional eine Beschreibung ein
4. Wähle die Projekt-Mitglieder aus (wichtig: nur zugewiesene Benutzer sehen das Projekt!)
5. Issues können anschließend einem Projekt zugeordnet werden

**Hinweis**: Benutzer sehen nur Projekte, denen sie als Mitglied zugeordnet sind. Admins sehen alle Projekte.

### Private Nachrichten senden

1. Gehe zu "Issue Tracker" → "Nachrichten"
2. Klicke auf "Neue Nachricht"
3. Wähle den Empfänger aus
4. Gib Betreff und Nachricht ein
5. Klicke auf "Senden"

Nachrichten werden als Konversation gruppiert. In der Inbox siehst du:
- Den Kommunikationspartner
- Anzahl ungelesener Nachrichten
- Die letzte Nachricht in der Konversation
- Wer zuletzt geantwortet hat

### Broadcast-Nachricht senden (nur Admins)

1. Gehe zu "Issue Tracker" → "Nachrichten" → "Broadcast"
2. Gib Betreff und Nachricht ein
3. Wähle die Empfänger:
   - **Nur Issue-Tracker User** (Standard): Benutzer mit Issue-Tracker-Berechtigung
   - **Alle REDAXO User**: Alle aktiven Backend-Benutzer (nur per E-Mail)
4. Wähle die Versandart:
   - **Internes Nachrichtensystem**: Nachricht erscheint im Posteingang
   - **Nur per E-Mail**: Nachricht wird per E-Mail versendet
   - **Nachrichtensystem und E-Mail**: Beides gleichzeitig
5. Klicke auf "Broadcast senden"
6. Bestätige die Aktion

## Dashboard

Das Dashboard zeigt:
- Anzahl offener Issues
- Anzahl Issues in Arbeit
- Anzahl geplanter Issues
- Anzahl erledigter Issues (letzte 30 Tage)
- Issues nach Kategorie
- Die 10 neuesten Issues
- Ungelesene Nachrichten (Widget)
- Projekte mit Fortschrittsanzeige

## Filter und Suche

In der Issues-Liste kannst du:
- Nach Status filtern (Alle aktiven, Alle Issues, Nur Geschlossene, oder einzelner Status)
- Nach Kategorie filtern
- Nach Tags filtern
- Nach Ersteller filtern ("Erstellt von")
- Nach Titel oder Beschreibung suchen
- Filter kombinieren und speichern
- Gespeicherte Filter als Standard festlegen

**Wichtig**: Filter werden in der Session gespeichert und bleiben erhalten, auch wenn du ein Issue öffnest und zurückkehrst. Mit dem "Reset"-Button werden alle Filter zurückgesetzt.

## Benachrichtigungen

### Benachrichtigungstypen

Benutzer werden automatisch benachrichtigt bei:
- Neuen Issues
- Neuen Kommentaren
- Status-Änderungen
- Zuweisungen
- Privaten Nachrichten (optional)

### Benachrichtigungseinstellungen anpassen

Jeder Benutzer kann seine Benachrichtigungen unter "Issue Tracker" → "Benachrichtigungen" individuell konfigurieren:
- E-Mail bei neuen Issues
- E-Mail bei neuen Kommentaren
- E-Mail bei Status-Änderungen
- E-Mail bei Zuweisungen
- E-Mail bei privaten Nachrichten
- Optional: Vollständiger Nachrichtentext in E-Mails

## Technische Details

### Datenbank-Tabellen

- `rex_issue_tracker_issues`: Haupt-Tabelle für Issues
- `rex_issue_tracker_comments`: Kommentare zu Issues
- `rex_issue_tracker_tags`: Tag-Definitionen
- `rex_issue_tracker_issue_tags`: Zuordnung Issues ↔ Tags
- `rex_issue_tracker_notifications`: Benachrichtigungseinstellungen pro Benutzer
- `rex_issue_tracker_settings`: Globale Einstellungen
- `rex_issue_tracker_attachments`: Dateianhänge zu Issues
- `rex_issue_tracker_history`: Aktivitätsverlauf
- `rex_issue_tracker_saved_filters`: Gespeicherte Filter pro Benutzer
- `rex_issue_tracker_projects`: Projekte
- `rex_issue_tracker_project_users`: Projekt-Mitgliedschaften
- `rex_issue_tracker_messages`: Private Nachrichten

### PHP-Klassen

- `FriendsOfREDAXO\IssueTracker\Issue`: Issue-Model
- `FriendsOfREDAXO\IssueTracker\Comment`: Comment-Model
- `FriendsOfREDAXO\IssueTracker\Tag`: Tag-Model
- `FriendsOfREDAXO\IssueTracker\Project`: Projekt-Model
- `FriendsOfREDAXO\IssueTracker\Message`: Nachrichten-Model
- `FriendsOfREDAXO\IssueTracker\Attachment`: Attachment-Model
- `FriendsOfREDAXO\IssueTracker\NotificationService`: E-Mail-Benachrichtigungen
- `FriendsOfREDAXO\IssueTracker\HistoryService`: Aktivitätsverlauf
- `FriendsOfREDAXO\IssueTracker\SavedFilterService`: Gespeicherte Filter

### Assets

- `assets/issue_tracker.css`: Styling (Dark Mode kompatibel)
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

### Version 1.0.0-beta1 (2026-01-09)
- Initial Release
- Vollständige Issue-Verwaltung mit verschachtelten Kommentaren
- Projektmanagement mit Mitglieder-Zuordnung
- Privates Nachrichtensystem zwischen Benutzern
- E-Mail-Benachrichtigungen mit Deep-Links und HTML-Templates
- Personalisiertes Dashboard mit Statistiken und Widgets
- Tag-System mit Farbcodierung und Duplikat-Erkennung
- Kommentar-System mit Pin- und Lösungs-Markierung
- Kommentar-Antworten (Thread-System)
- Filter und Suche mit Session-Speicherung und speicherbaren Filtern
- Broadcast-Funktion für Admin-Nachrichten
- Vollständiger Aktivitätsverlauf
- Dateianhang-Verwaltung
- Backup/Export und Import-Funktion
- Anpassbarer Menü-Titel
- Dark Mode Unterstützung


## Lizenz

MIT License - siehe [LICENSE.md](LICENSE.md)

## Autor

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO

## Projektleitung

[Thomas Skerbis](https://github.com/skerbis)
