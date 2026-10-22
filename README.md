# REDAXO Issue Tracker

Ein vollständiger Issue-Tracker für REDAXO CMS, der es Redakteuren ermöglicht, Wünsche zu äußern, Probleme zu melden und Vorschläge zu machen. Mit integriertem Projektmanagement und privatem Nachrichtensystem.

**Ab Version 2.0 so vertraut wie Mail auf dem Mac:** Links die Postfächer (Mir zugewiesen, Offen, Projekte, Tags …), in der Mitte die Themenliste mit Vorschau, rechts das Lesefenster mit dem Gesprächsverlauf. Mit ↑/↓ blättern, mit R antworten, mit T Tags direkt in der Liste setzen, mit ? alle Tastenkürzel anzeigen. Am Handy wie am iPhone. Wer lieber die klassische Tabelle mag, schaltet oben auf „Tabelle“ um. Beim Anlegen stehen Zuständigkeit, Priorität, Kategorie, Tags und die betroffene Seite gleich oben als Leiste bereit, alles Weitere liegt aufgeräumt unter „Weitere Angaben“. Hell und dunkel.

## Features

### Kernfunktionen
- **Issue-Verwaltung**: Erstellen, Bearbeiten, Löschen und Kommentieren von Issues
- **Postfach wie Mail**: Postfächer (Mir zugewiesen, Beobachtet, Erwähnt, Überfällig, Kürzlich erledigt, Status, Projekte, Wissen, Tags, gespeicherte Filter), Liste mit Vorschau und Lesebereich; alternativ als Tabelle
- **Eigenschaften direkt im Lesebereich**: Status und Priorität über die Pill, Zuweisen, Beobachten, Tags, Erledigen – ohne das Formular zu öffnen
- **Wissen**: Anleitungen und Hinweise als eigene Einträge (Status „Info“), nach Kategorie
- **Status-Tracking**: Offen, In Arbeit, Geplant, Info, Abgelehnt, Erledigt
- **Prioritäten**: Niedrig, Normal, Hoch, Dringend
- **Kategorien**: Frei konfigurierbare Kategorien in den Einstellungen
- **Tags**: Flexible Tag-Verwaltung mit Farbcodierung und Zufallsfarben für neue Tags
- **Tags direkt setzen**: im Kopf des Lesebereichs und mit „Quick-Tag“ (Taste T) direkt in der Liste
- **Standard-Tags**: Bug, Feature, Docs und Good Idea werden bei Installation automatisch angelegt
- **Private Issues**: Admins können Issues als privat markieren (siehe unten)
- **Kommentare**: Diskussion und Feedback zu jedem Issue mit Thread-System
- **Interne Kommentare**: Admins können Kommentare als intern markieren (nur für Admins und Manager sichtbar)
- **@mentions**: User per `@login` in Kommentaren erwähnen – Autocomplete-Dropdown direkt im Editor, E-Mail-Benachrichtigung an Erwähnte
- **Antworten und erledigen**: Thema mit der Antwort als erledigt markieren – ein Schritt, eine kombinierte E-Mail
- **Interaktive Checklisten**: `- [ ] Offene Aufgabe` und `- [x] Erledigte Aufgabe` in Beschreibungen und Kommentaren – per Klick abhaken ohne Seite zu verlassen, Fortschrittsbalken in Liste und Detailansicht
- **Zeiterfassung**: `/spent 2h 30m` in einem Kommentar loggt Zeit für das Issue – unterstützt `h`, `m` und `min`, Gesamtzeit im Details-Panel der Detailansicht sichtbar
- **#Issue-Referenzen**: `#42` in Kommentaren wird zu klickbarem Link, das referenzierte Issue erhält automatisch einen Cross-Reference-Eintrag im Aktivitätsverlauf
- **Zuweisungen**: Issues können Benutzern und AddOns zugeordnet werden
- **Versionsverwaltung**: Issues können einer Version zugeordnet werden
- **Fälligkeitsdatum**: Issues können mit Deadlines versehen werden, überfällige Issues werden markiert
- **Aktivitätsverlauf**: Vollständiges Tracking aller Änderungen an Issues
- **Dateianhänge**: Upload von Bildern, Dokumenten und anderen Dateien zu Issues
- **Lightbox**: Bilder und Videos direkt in einer Lightbox ansehen
- **Gespeicherte Filter**: Häufig verwendete Filtereinstellungen speichern und als Standard festlegen
- **Session-Filter**: Filter bleiben während der Sitzung erhalten
- **Erweiterte Filter**: Nach Status, Kategorie, Tags, Ersteller, Zuständig, Projekt und Text
- **Sortierbare Listen**: im Postfach über das Sortier-Menü, in der Tabelle über die Spaltenköpfe
- **Automatisches closed_at**: Beim Schließen eines Issues wird das Datum automatisch gesetzt

### Projektmanagement
- **Projekte**: Issues können Projekten zugeordnet werden
- **Projekt-Mitglieder**: Benutzer können Projekten zugeordnet werden
- **Projektbasierte Sichtbarkeit**: Benutzer sehen nur Projekte, denen sie zugeordnet sind
- **Projektordner**: jedes Projekt als Ordner in der Seitenleiste; bearbeiten, Mitglieder, archivieren und löschen
- **Issue-Gruppierung**: Issues können nach Projekten gefiltert werden

### Private Nachrichten
- **Nachrichtensystem**: Benutzer können sich gegenseitig private Nachrichten senden
- **Posteingang & Gesendet**: Übersichtliche Trennung von empfangenen und gesendeten Nachrichten
- **Konversationsansicht**: Nachrichten werden als Konversation mit demselben Partner gruppiert
- **Weiterleiten, ungelesen markieren, alle als gelesen**: wie in Mail (Tasten F und U)
- **Ungelesen-Zahl**: Ungelesene Nachrichten stehen mit Zahl in der Seitenleiste und im Menü
- **E-Mail-Benachrichtigung**: Optional werden Benutzer per E-Mail über neue Nachrichten informiert
- **Volltext-Option**: Nachrichten können optional vollständig in der E-Mail enthalten sein
- **Antwort-Funktion**: Direktes Antworten auf Nachrichten möglich

### E-Mail-Benachrichtigungen
- Automatische Benachrichtigungen bei neuen Issues
- Benachrichtigungen bei neuen Kommentaren
- Benachrichtigungen bei Status-Änderungen
- Benachrichtigungen bei Zuweisungen
- **@mention-Benachrichtigungen**: E-Mail an erwähnte User (individual abschaltbar)
- Benachrichtigungen bei privaten Nachrichten (optional)
- **Erinnerungs-Funktion**: Zugewiesene Benutzer per Klick an offene Issues erinnern (mit konfigurierbarem Cooldown)
- **Kombinierte Benachrichtigung** beim Schließen via Kommentar: eine E-Mail statt zwei
- **HTML E-Mail Templates** mit professionellem Design – anpassbar über die Einstellungen
- **Markdown in E-Mails**: Beschreibungen und Kommentare unterstützen `**fett**`, `*kursiv*`, Links und automatische URL-Erkennung
- **Deep Links** mit One-Time-Token für Issues und Nachrichten (30 Tage gültig, einmalige Verwendung)
- **Konfigurierbare Absender-Adresse**: Eigene Adresse oder Fallback auf PHPMailer-Konfiguration
- Mehrsprachige Templates (Deutsch/Englisch)
- Broadcast-Nachrichten an alle Benutzer (nur Admins)
- Individuelle Benachrichtigungseinstellungen pro Benutzer

### Vollbild-App
- Öffnet sich wie MediaPlace als eigene Ebene über dem Backend – mit eigener Kopfleiste und „Schließen“ (oder Esc), das zur vorherigen Seite zurückführt
- Aufruf von jeder Backend-Seite: Menüpunkt sowie Knöpfe „Issue Tracker“ und „Neues Thema“ in der Kopfzeile
- „Neues Thema“ übernimmt die aktuelle Backend-Seite („Gemeldet von Seite“); beim Bearbeiten eines Artikels ist er als betroffene Seite vorbelegt
- Postfach in voller Fensterhöhe: Postfächer, Liste und Lesebereich scrollen einzeln, die Seite dahinter nie
- Abschaltbar in den Einstellungen („Als Vollbild öffnen“); dann wie bisher innerhalb der REDAXO-Oberfläche

### Anpassungen
- **Menü-Titel anpassbar**: Der Menüpunkt kann in den Einstellungen umbenannt werden (z.B. "Support", "Tickets", "Anfragen")
- **Tag-Duplikate verhindern**: Doppelte Tag-Namen werden erkannt und abgelehnt
- **Dark Mode kompatibel**: Styling funktioniert im hellen und dunklen REDAXO-Theme

### Berechtigungen
- **issue_tracker[]**: Basis-Berechtigung für Zugriff auf den Issue Tracker (nur lesen)
- **issue_tracker[issuer]**: Erweiterte Berechtigung für das Erstellen und Kommentieren von Issues
- **issue_tracker[issue_manager]**: Issue Manager – kann alle Themen bearbeiten und löschen, Wissen pflegen und Projekte anlegen (fast wie Admin, ohne Einstellungen)
- **admin**: Vollzugriff inkl. Einstellungen, Löschen, Private Issues erstellen und Broadcast-Nachrichten

### YRewrite & YForm Integration
- **Domain-Auswahl**: Issues können optional einer YRewrite-Domain zugeordnet werden (nur sichtbar wenn YRewrite installiert ist)
- **YForm Tabellen-Auswahl**: Issues können einer YForm-Tabelle zugeordnet werden (gefiltert nach Benutzerrechten)

## Installation

1. Addon-Ordner nach `/redaxo/src/addons/issue_tracker/` kopieren
2. Im REDAXO-Backend unter "AddOns" das AddOn installieren und aktivieren
3. Die **Media Manager Typen werden automatisch erstellt** (falls Media Manager verfügbar ist)
4. Berechtigungen für Benutzer einrichten (siehe unten)

### Media Manager Integration

Das Issue Tracker AddOn erstellt bei der Installation automatisch zwei Media Manager Typen für die Anzeige von Dateianhängen:

#### Automatisch erstellte Typen:

1. **`issue_tracker_attachment`** (Vollansicht)
   - Lädt Originalanhänge aus dem Issue Tracker Datenverzeichnis
   - Wird für Downloads und vollständige Anzeige verwendet

2. **`issue_tracker_thumbnail`** (Vorschaubilder)
   - Erstellt 200×200px Thumbnails von Bildern
   - Wird für Vorschauen in Listen verwendet

**Hinweis**: 
- Die Media Manager Typen werden bei der Installation automatisch erstellt
- Bei Updates werden fehlende Typen nachinstalliert
- Bei Deinstallation werden die Typen automatisch entfernt
- Der Effekt "Issue Tracker Attachment" wird automatisch registriert

Weitere Details siehe [MEDIA_MANAGER.md](MEDIA_MANAGER.md)

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
3. Passe den Absender-Namen und optional die Absender-Adresse an (Fallback: PHPMailer-Konfiguration)
4. Stelle den Erinnerungs-Cooldown ein (Standard: 24 Stunden)
5. Stelle sicher, dass PHPMailer korrekt konfiguriert ist
6. Unter "E-Mail-Templates" können alle Vorlagen (Neues Issue, Kommentar, Status, Zuweisung, Erinnerung) individuell angepasst werden

## Verwendung

Eine ausführliche Anleitung für Redakteure steht im Backend unter **Hilfe** (Datei [manual.md](manual.md)). Kurz:

### Neues Thema erstellen

1. **Neu** in der Seitenleiste oder **Neues Thema** in der Kopfzeile des Backends (übernimmt die aktuelle Backend-Seite)
2. In der **Eigenschaften-Leiste** oben: Zuweisen an (Pflicht), Status, Priorität, Kategorie, Projekt, Fälligkeit, Tags und **Betroffene Seite** (Artikel per Linkmap oder freie Adresse)
3. **Titel** und **Beschreibung** (Markdown), optional **Dateianhänge** (auch per Ziehen)
4. Unter **Weitere Angaben**: Privat (nur Admins), AddOn, Version, Domains, YForm-Tabellen
5. **Thema erstellen**

### Private Themen (nur Admins)

Admins können Themen als privat markieren. In den Listen erscheinen private Themen nur für den Ersteller und Admins; öffnen (und exportieren) können sie außerdem die zugewiesene Person und Manager.

### Im Lesebereich arbeiten

- Status und Priorität über die Pill im Kopf, Tags über „+ Tag“
- Symbolleiste: Antworten (R), Zuweisen, Priorität, Beobachten, Bearbeiten, Als erledigt markieren/Wieder öffnen, Löschen, Link kopieren, Vollansicht
- Bereiche für Beobachter, Erinnerung, verwandtes Thema und Aktivitätsverlauf
- Kommentare: Antworten, Bearbeiten, Anheften, Als Lösung markieren, Löschen; „Antworten und erledigen“ schließt das Thema mit der Antwort; interne Kommentare nur für Admins und Manager sichtbar

### Interaktive Checklisten

In Beschreibungen und Kommentaren können Aufgabenlisten als Markdown-Checklisten angelegt werden:

```markdown
- [ ] Offene Aufgabe
- [x] Bereits erledigte Aufgabe
- [ ] Weitere Aufgabe
```

- Checklisten-Items können per Klick direkt in der Ansicht ab- oder angehakt werden – ohne Bearbeitungsmodus
- In der Issue-Detailansicht wird unterhalb der Beschreibung ein **Fortschrittsbalken** mit Zähler (`x/y Aufgaben erledigt`) angezeigt
- In der **Issue-Liste** erscheint ebenfalls ein kompakter Fortschrittsbalken (4px Höhe) sofern Checklisten vorhanden sind
- Änderungen werden sofort gespeichert und im Aktivitätsverlauf festgehalten

### Zeiterfassung

Zeit kann direkt über Kommentare erfasst werden. Schreibe `/spent` gefolgt von einer Zeitangabe:

```
/spent 2h          → 2 Stunden
/spent 30m         → 30 Minuten
/spent 1h 30m      → 1 Stunde 30 Minuten
/spent 90min       → 90 Minuten
```

- Der `/spent`-Befehl wird **nicht** im angezeigten Kommentar ausgegeben
- Die erfasste Zeit wird in der Tabelle `rex_issue_tracker_time_entries` gespeichert
- Im **Details-Panel** der Issue-Detailansicht wird die Gesamtzeit aller Zeiteinträge angezeigt
- Der Zeiteintrag erscheint im **Aktivitätsverlauf** des Issues

### #Issue-Referenzen

In Kommentaren kann auf andere Issues verwiesen werden, indem die Issue-Nummer mit `#` vorangestellt wird:

```
Dieses Problem hängt mit #42 zusammen. Siehe auch #7.
```

- `#42` wird automatisch zu einem klickbaren Link auf das referenzierte Issue
- Das **referenzierte** Issue (`#42`) erhält automatisch einen Cross-Reference-Eintrag in seinem Aktivitätsverlauf mit Verweis auf das aktuelle Issue
- Links innerhalb von Code-Blöcken (` ``` ` oder `\``) werden **nicht** ersetzt

### Projekte

- Projekte sind Ordner in der Seitenleiste. **Neues Projekt** (Admins und Manager) unter „Projekte“ oder über den Pfeil neben **Neu**.
- Über **…** am Ordner: Bearbeiten, Mitglieder (Rollen Owner, Mitglied, Beobachter), Archivieren. Löschen unten auf der Seite „Projekt bearbeiten“; die Themen bleiben erhalten.
- Benutzer sehen nur Projekte, denen sie als Mitglied zugeordnet sind. Admins sehen alle Projekte.

### Nachrichten

- **Neue Nachricht** über **Neu** im Bereich Nachrichten; Posteingang und Gesendet gruppieren als Gespräch.
- Antworten direkt unter der Nachricht, **Weiterleiten** (F), **Als ungelesen markieren** (U), **Alle als gelesen markieren**, Löschen (nur für sich selbst).

### Rundschreiben (nur Admins)

1. Pfeil neben **Neu** → **Rundschreiben senden**
2. Empfänger: **Nur Issue-Tracker-Benutzer** (Standard) oder **Alle REDAXO-Benutzer** (nur per E-Mail)
3. Versandart: Nachricht im Posteingang, nur E-Mail oder beides
4. Betreff und Text eingeben, absenden und bestätigen

## Filter und Suche

- Suche in Titel und Beschreibung, Sortierung und Filter (Status, Kategorie, Tag, Ersteller, Zuständig, Projekt) oben in der Liste; **Zurücksetzen** hebt alle Filter auf
- Filter bleiben beim Öffnen eines Themas erhalten und lassen sich unter **Gespeicherte Filter** speichern, als Standard festlegen und löschen
- CSV- und PDF-Export (PDF mit dem AddOn PdfOut) berücksichtigen die aktuellen Filter

## Benachrichtigungen

### Benachrichtigungstypen

Benutzer werden automatisch benachrichtigt bei:
- Neuen Issues
- Neuen Kommentaren
- Status-Änderungen
- Zuweisungen
- Privaten Nachrichten (optional)

### Benachrichtigungseinstellungen anpassen

Jeder Benutzer kann seine Benachrichtigungen im Zahnrad-Menü unter „Meine Benachrichtigungen“ individuell konfigurieren:
- E-Mail bei neuen Issues
- E-Mail bei neuen Kommentaren
- E-Mail bei Status-Änderungen
- E-Mail bei Zuweisungen
- E-Mail bei Erwähnungen
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
- `rex_issue_tracker_email_tokens`: One-Time-Token für E-Mail-Deep-Links
- `rex_issue_tracker_reminders`: Erinnerungs-Tracking (Cooldown-Verwaltung)
- `rex_issue_tracker_watchers`: Beobachter je Thema
- `rex_issue_tracker_mentions`: Erwähnungen
- `rex_issue_tracker_time_entries`: Zeiteinträge (`/spent`)

### PHP-Klassen

- `FriendsOfREDAXO\IssueTracker\Issue`: Issue-Model
- `FriendsOfREDAXO\IssueTracker\Comment`: Comment-Model
- `FriendsOfREDAXO\IssueTracker\Tag`: Tag-Model
- `FriendsOfREDAXO\IssueTracker\Project`: Projekt-Model
- `FriendsOfREDAXO\IssueTracker\Message`: Nachrichten-Model
- `FriendsOfREDAXO\IssueTracker\Attachment`: Attachment-Model
- `FriendsOfREDAXO\IssueTracker\NotificationService`: E-Mail-Benachrichtigungen inkl. Token-Generierung und Reminder
- `FriendsOfREDAXO\IssueTracker\EmailTemplateService`: Verwaltung und Rendering der E-Mail-Templates mit Markdown-Formatierung
- `FriendsOfREDAXO\IssueTracker\HistoryService`: Aktivitätsverlauf
- `FriendsOfREDAXO\IssueTracker\SavedFilterService`: Gespeicherte Filter
- `FriendsOfREDAXO\IssueTracker\PermissionService`: Zentralisierte Berechtigungsprüfungen
- `FriendsOfREDAXO\IssueTracker\AppShell`: Rahmen der Vollbild-App und Prüfung von Backend-Adressen
- `FriendsOfREDAXO\IssueTracker\ContentRenderer`: Markdown mit Checklisten und #-Verweisen
- `rex_api_issue_tracker_link`: API-Klasse für Deep-Link-Token-Verarbeitung
- `rex_api_issue_tracker_tags`, `rex_api_issue_tracker_export`, `rex_api_issue_tracker_stats`, `rex_api_issue_tracker_generate_token`: Quick-Tags, CSV-Export, Schnittstelle (siehe [api.md](api.md)), API-Token

### Assets

- `assets/issue_tracker.css`, `assets/css/it-*.css`: Stile (eigene Klassen mit Präfix `it-`, Dark Mode)
- `assets/issue_tracker.js`: Editoren, Kommentare, Checklisten, Lightbox
- `assets/js/it-mail.js`, `assets/js/it-ui.js`: Postfach, Lesebereich, Quick-Tags, Eigenschaften-Leiste
- `assets/js/it-app.js`: Vollbild-App (PJAX in der Ebene); `assets/js/it-launcher.js`, `assets/css/it-launcher.css`: kleiner Starter auf den übrigen Backend-Seiten

## Erweiterungen

### Eigene Kategorien hinzufügen

Kategorien können über die Einstellungen verwaltet werden.

### Eigene Status hinzufügen

Status sind aktuell fest im Code definiert. Für Erweiterungen die `install.php` anpassen.

### Eigene E-Mail-Templates

E-Mail-Templates können direkt im Backend unter **Einstellungen → E-Mail-Templates** angepasst werden.

Verfügbare Templates: Neues Issue, Neuer Kommentar, Status-Änderung, Zuweisung, Erinnerung – jeweils in Deutsch und Englisch.

Verfügbare Platzhalter: `{{recipient_name}}`, `{{issue_id}}`, `{{issue_title}}`, `{{issue_category}}`, `{{issue_priority}}`, `{{issue_description}}`, `{{creator_name}}`, `{{comment_text}}`, `{{old_status}}`, `{{new_status}}`, `{{issue_url}}`, `{{sent_by_name}}`, `{{issue_status}}`, `{{due_date}}`, `{{affected_page}}`

`{{affected_page}}` ergibt eine komplette Zeile „Betroffene Seite: …“ (mit Zeilenumbruch davor) – Titel und absolute Frontend-Adresse des Artikels bzw. die eingegebene Adresse; Empfänger mit Rechten auf Kategorie und Sprache bekommen zusätzlich den Link „Im Backend bearbeiten“. Ist keine Seite gesetzt, bleibt der Platzhalter leer, es entsteht also keine leere Beschriftung. Die mitgelieferten Vorlagen enthalten ihn im Infokasten; beim Update werden unveränderte Standardvorlagen automatisch ergänzt, selbst angepasste Vorlagen bleiben unangetastet (dort bei Bedarf `{{affected_page}}` von Hand einfügen).

In Beschreibungen und Kommentaren wird einfaches Markdown unterstützt:
- `**fett**`, `*kursiv*`
- `[Linktext](https://example.com)`
- URLs und E-Mail-Adressen werden automatisch verlinkt

## Support

Bei Fragen oder Problemen:
- Issue im REDAXO Slack erstellen
- Issue auf GitHub erstellen: https://github.com/FriendsOfREDAXO/issue_tracker

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für eine vollständige Liste aller Änderungen.


## Lizenz

MIT License - siehe [LICENSE.md](LICENSE.md)

## Autor

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO

## Projektleitung

[Thomas Skerbis](https://github.com/skerbis)
