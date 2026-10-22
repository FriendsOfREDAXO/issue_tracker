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
   - Öffne den Issue Tracker über den Menüpunkt oder den Knopf „Issue Tracker“ in der Kopfzeile
   - Klicke auf „Neu“ (oder „Neues Thema“ in der Kopfzeile)
   - Fülle das Formular aus und speichere

4. **Update von 1.x**
   - Neue Tabellen und Spalten legt das Update automatisch an
   - Eigene E-Mail-Vorlagen bleiben unverändert; den Platzhalter `{{affected_page}}` bei Bedarf von Hand ergänzen
   - Die Ebene über dem Backend lässt sich unter Einstellungen → Allgemein („Als Vollbild öffnen“) abschalten

## Datenbank-Tabellen

Bei der Installation werden folgende Tabellen erstellt:

- `rex_issue_tracker_issues` - Haupt-Tabelle für Issues
- `rex_issue_tracker_comments` - Kommentare
- `rex_issue_tracker_tags` - Tags
- `rex_issue_tracker_issue_tags` - Zuordnung Issues ↔ Tags
- `rex_issue_tracker_notifications` - Benachrichtigungseinstellungen
- `rex_issue_tracker_settings` - Globale Einstellungen
- weitere Tabellen für Anhänge, Verlauf, gespeicherte Filter, Projekte, Nachrichten, E-Mail-Token, Erinnerungen, Beobachter, Erwähnungen und Zeiteinträge (siehe README)

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
- Info (Wissenseinträge)
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
- Postfach, Filter und Nachrichten nutzen

### issue_tracker[issuer]
Erweiterte Berechtigung zum Erstellen und Kommentieren.
Benutzer mit dieser Berechtigung können zusätzlich:
- Issues erstellen
- Issues bearbeiten
- Kommentare hinzufügen
- Status ändern

### issue_tracker[issue_manager]
Manager: alle Themen bearbeiten und löschen, Wissenseinträge pflegen, Projekte anlegen und verwalten, interne Kommentare und Aktivitätsverlauf sehen.

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
