# REDAXO Issue Tracker - Installationsanleitung

## Schnellstart

1. **Addon installieren**
   - Gehe zu "AddOns" im REDAXO-Backend
   - Klicke bei "Issue Tracker" auf "Installieren"
   - Klicke auf "Aktivieren"

2. **Berechtigungen einrichten**
   - Gehe zu "Benutzer" → "Rollen"
   - Bearbeite eine Rolle oder erstelle eine neue
   - Aktiviere "Issue Tracker [issue_tracker[]]"
   - Optional: Aktiviere "Issue Tracker Issuer [issue_tracker[issuer]]"
   - Speichern

3. **Erste Schritte**
   - Gehe zu "Issue Tracker" im Hauptmenü
   - Klicke auf "Neues Issue erstellen"
   - Fülle das Formular aus und speichere

## Datenbank-Tabellen

Bei der Installation werden folgende Tabellen erstellt:

- `rex_issue_tracker_issues` - Haupt-Tabelle für Issues
- `rex_issue_tracker_comments` - Kommentare
- `rex_issue_tracker_tags` - Tags
- `rex_issue_tracker_issue_tags` - Zuordnung Issues ↔ Tags
- `rex_issue_tracker_notifications` - Benachrichtigungseinstellungen
- `rex_issue_tracker_settings` - Globale Einstellungen

## Standard-Einstellungen

Nach der Installation sind folgende Einstellungen aktiv:

### Kategorien
- Redaktion
- Technik
- AddOn
- Support
- Medien
- Struktur

### Status
- Offen
- In Arbeit
- Geplant
- Abgelehnt
- Erledigt

### Prioritäten
- Niedrig
- Normal
- Hoch
- Dringend

### E-Mail
- Benachrichtigungen: Aktiviert
- Absender-Name: "REDAXO Issue Tracker"

## Berechtigungen

### issue_tracker[]
Basis-Berechtigung für den Zugriff auf den Issue Tracker.
Benutzer mit dieser Berechtigung können:
- Issues ansehen
- Dashboard nutzen
- Filter verwenden

### issue_tracker[issuer]
Erweiterte Berechtigung zum Erstellen und Kommentieren.
Benutzer mit dieser Berechtigung können zusätzlich:
- Issues erstellen
- Issues bearbeiten
- Kommentare hinzufügen
- Status ändern

### admin
Vollzugriff auf alle Funktionen.
Admins können zusätzlich:
- Issues löschen
- Einstellungen ändern
- Kategorien verwalten
- Broadcast-Nachrichten senden
- Interne Kommentare erstellen

## E-Mail-Konfiguration

Stelle sicher, dass das PHPMailer-AddOn korrekt konfiguriert ist:

1. Gehe zu "AddOns" → "PHPMailer"
2. Konfiguriere SMTP-Einstellungen
3. Teste die E-Mail-Versendung

Benachrichtigungen werden automatisch an alle berechtigten Benutzer gesendet bei:
- Neuen Issues
- Neuen Kommentaren
- Status-Änderungen
- Zuweisungen

## Anpassungen

### Kategorien anpassen
1. Gehe zu "Issue Tracker" → "Einstellungen"
2. Bearbeite, füge hinzu oder entferne Kategorien
3. Speichern

### E-Mail-Einstellungen
1. Gehe zu "Issue Tracker" → "Einstellungen"
2. Aktiviere/Deaktiviere Benachrichtigungen
3. Ändere Absender-Name
4. Speichern

### Tags erstellen
Tags können direkt in der Datenbank-Tabelle `rex_issue_tracker_tags` erstellt werden.
Alternativ kann ein Admin-Interface dafür entwickelt werden.

## Troubleshooting

### E-Mails werden nicht versendet
- Prüfe PHPMailer-Konfiguration
- Prüfe ob E-Mail-Benachrichtigungen aktiviert sind (Einstellungen)
- Prüfe Logs in `redaxo/data/core/error.log`

### Keine Berechtigung
- Stelle sicher, dass der Benutzer die Rolle "issue_tracker[]" hat
- Admins haben automatisch Zugriff

### Issues werden nicht angezeigt
- Prüfe Filter-Einstellungen
- Stelle sicher, dass Issues existieren
- Prüfe Datenbank-Verbindung

## Deinstallation

Bei der Deinstallation werden alle Datenbank-Tabellen entfernt.
**ACHTUNG:** Alle Issues, Kommentare und Tags werden gelöscht!

1. Gehe zu "AddOns"
2. Klicke bei "Issue Tracker" auf "Deaktivieren"
3. Klicke auf "Deinstallieren"
4. Bestätige die Aktion

## Support

Bei Fragen oder Problemen:
- Erstelle ein Issue im REDAXO Slack
- Kontaktiere den Entwickler
- Prüfe die README.md für weitere Informationen
