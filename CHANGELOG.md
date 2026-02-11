# Changelog

Alle nennenswerten Änderungen am Issue Tracker AddOn werden hier dokumentiert.

## [1.5.1] – 2026-02-11

### Verbesserungen
- **Sidebar komplett neu gestaltet**: Issue-Detailansicht mit 5 klar getrennten Panels statt einer unübersichtlichen Einzelliste
  - Panel 1: Status & Priorität (inkl. Fälligkeitsdatum, Tags)
  - Panel 2: Personen (Zugewiesener, Ersteller)
  - Panel 3: Details (Kategorie, AddOn, Version, Daten, Domain, YForm, Projekt, verwandte Issues)
  - Panel 4: Aktionen (Statusänderung, Erinnerung) – nur für Berechtigte sichtbar
  - Panel 5: Beobachter (Watch/Unwatch, Liste, Einladung)
- **Einstellungen-Seite aktualisiert**: Benachrichtigungsbeschreibungen spiegeln jetzt das beteiligungsbasierte Modell wider
- **Info-Box in Einstellungen**: Erklärt das Benachrichtigungsmodell (Ersteller, Zugewiesener, Kommentierer, Beobachter)
- **Neue Sprachschlüssel**: Panel-Überschriften für Personen, Details und Aktionen (DE + EN)

## [1.5.0] – 2026-02-11

### Neue Funktionen
- **Beteiligungsbasierte Benachrichtigungen**: E-Mails werden nur noch an beteiligte User gesendet (Ersteller, Zugewiesener, Kommentierer, Beobachter) statt an alle berechtigten User
  - Neues Issue → nur der zugewiesene User wird informiert
  - Kommentare → alle Beteiligten (außer Kommentar-Autor)
  - Statusänderungen → alle Beteiligten (außer dem Ändernden)
- **Beobachter-System (Watchers)**: User können Issues beobachten und erhalten Benachrichtigungen
  - Watch/Unwatch-Button in der Issue-Detailansicht
  - Beobachterliste mit Anzahl in der Sidebar
  - Multi-Select zum Einladen weiterer Beobachter (für Ersteller, Manager, Admins)
  - Eingeladene Beobachter erhalten eine einmalige E-Mail-Benachrichtigung
  - Beobachter können von Managern/Admins wieder entfernt werden
- **Dashboard: Beobachtete Issues**: Neues Panel in der rechten Spalte zeigt alle Issues, die der User beobachtet (max. 10, mit Status-Farbe, Überf\u00e4llig-Warnung)\n- **Zugewiesener User als Pflichtfeld**: Jedes Issue muss einem Benutzer zugewiesen werden
  - Validierung sowohl client- als auch serverseitig
  - Aussagekräftige Fehlermeldung bei fehlendem Zugewiesenem

### Verbesserungen
- **Neue DB-Tabelle `issue_tracker_watchers`**: Speichert Issue-Beobachter mit Unique-Index
- **`getInvolvedUsers()`**: Neue Methode im NotificationService sammelt beteiligte User
- **`hasNotificationEnabled()`**: Prüft einzelne Benachrichtigungsspräferenzen pro User
- **Statusänderung**: Der ändernde User wird jetzt korrekt von der Benachrichtigung ausgeschlossen
- **Update-Script**: Legt Standard-Benachrichtigungseinträge für alle berechtigten User an

## [1.4.1] – 2026-02-11

### Bugfixes
- **E-Mail-Benachrichtigungen nur für Admins**: Nicht-Admin-Benutzer erhielten keine E-Mails, da die Berechtigungsprüfung per `LIKE` auf der `role`-Spalte nach Permission-Strings suchte statt nach Rollen-IDs. Berechtigungen werden jetzt korrekt über `rex_user::hasPerm()` geprüft.
- **issue_tracker[issue_manager]** wird jetzt bei Benachrichtigungen berücksichtigt (fehlte vorher)
- **Deaktivierte Benutzer** werden bei Benachrichtigungen korrekt ausgeschlossen (`status = 1`)
- **SQL-Injection-Schutz**: Whitelist-Validierung für Notification-Typ hinzugefügt

## [1.4.0] – 2026-02-11

### Neue Funktionen
- **Erinnerungs-Funktion**: Zugewiesene Benutzer können per Klick an offene Issues erinnert werden
  - Konfigurierbarer Cooldown (Standard: 24 Stunden) zwischen Erinnerungen pro Issue
  - Erinnerungs-Button in der Issue-Detailansicht (rot hervorgehoben)
  - Reminder-Tracking in eigener Datenbank-Tabelle mit History-Eintrag
- **Anpassbare Erinnerungs-Templates**: Reminder E-Mail-Vorlage ist über die Einstellungen anpassbar (DE + EN)
- **Sichere Markdown-Formatierung in E-Mails**: Beschreibungen und Kommentare in E-Mail-Templates unterstützen jetzt:
  - `**fett**` und `*kursiv*`
  - `[Linktext](URL)` für Links
  - Automatische Verlinkung von URLs und E-Mail-Adressen
  - Zeilenumbrüche werden zu `<br>` konvertiert
- **Neue Platzhalter**: `{{sent_by_name}}`, `{{issue_status}}`, `{{due_date}}` für E-Mail-Templates
- **Konfigurierbare Absender-Adresse**: Eigene E-Mail-Absenderadresse in den Einstellungen, mit PHPMailer-Konfiguration als Fallback

### Verbesserungen
- **Tabellendefinitionen zentralisiert**: Alle 14 DB-Tabellen in gemeinsame `table_setup.php` ausgelagert (von `install.php` und `update.php` genutzt)
- **Update-sicheres Template-Management**: Fehlende E-Mail-Templates werden bei Updates automatisch nachgerüstet, ohne bestehende zu überschreiben
- **E-Mail-Darstellung**: `white-space: pre-wrap` durch `nl2br()` ersetzt für saubere Zeilenumbrüche
- **Reminder Cooldown**: Einstellbar über Einstellungen → E-Mail mit Minimum 1 und Maximum 720 Stunden

### Bugfixes
- **E-Mail From-Adresse**: Absenderadresse wird korrekt aus PHPMailer-Konfiguration gelesen statt aus `rex::getProperty('server')` (URL statt E-Mail)

## [1.3.0] – 2026-02-09

### Neue Funktionen
- **Lightbox**: Bilder und Videos direkt in einer Lightbox ansehen
- **Status "Info"**: Neuer Status für informative Issues

### Verbesserungen
- Fehlende Übersetzungen ergänzt

## [1.2.0] – 2026-01-26

### Neue Funktionen
- **Verwandte Issues**: Issues können als verwandt markiert werden (vormals "Duplikat-Markierung")
- **Media Manager Integration**: Automatische Installation der Media Manager Typen bei Erstinstallation

### Verbesserungen
- E-Mail-Templates: Farben und Design verbessert
- Sprachdateien korrigiert und ergänzt
- SQL-Fehler behoben

## [1.1.0] – 2026-01-18

### Neue Funktionen
- **Export-Funktion**: Issues können exportiert werden
- **Gespeicherte Filter**: Häufig verwendete Filtereinstellungen speichern und als Standard festlegen

### Verbesserungen
- Zugriffsrechte korrigiert
- Nachrichtenansicht verbessert
- Fehlende Übersetzungen ergänzt
- Logische Button-Anordnung verbessert

## [1.0.1] – 2026-01-16

### Verbesserungen
- Komfort-Änderungen in der Bedienung
- Farbgebung und Sprachdateien korrigiert
- Seiten-Konfiguration angepasst

## [1.0.0] – 2026-01-09

### Initiales Release
- Vollständige Issue-Verwaltung mit Status-Tracking, Prioritäten und Kategorien
- Verschachtelte Kommentare mit Pin- und Lösungs-Markierung
- Projektmanagement mit Mitglieder-Zuordnung
- Privates Nachrichtensystem zwischen Benutzern
- E-Mail-Benachrichtigungen mit HTML-Templates und Deep-Links (One-Time-Token)
- Dashboard mit Statistiken und Widgets
- Tag-System mit Farbcodierung
- Dateianhang-Verwaltung
- Gespeicherte Filter mit Session-Speicherung
- Broadcast-Funktion für Admin-Nachrichten
- Vollständiger Aktivitätsverlauf
- Private Issues (nur für Ersteller und Admins)
- Anpassbarer Menü-Titel
- Dark Mode Unterstützung
- YRewrite & YForm Integration
