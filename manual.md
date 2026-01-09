# Issue Tracker - Benutzerhandbuch

Dieses Handbuch erklärt die Verwendung des Issue Trackers für Redakteure und Administratoren.

## Inhaltsverzeichnis

1. [Übersicht](#übersicht)
2. [Themen verwalten](#themen-verwalten)
3. [Projekte](#projekte)
4. [Private Nachrichten](#private-nachrichten)
5. [Filter und Suche](#filter-und-suche)
6. [Benachrichtigungseinstellungen](#benachrichtigungseinstellungen)
7. [Einstellungen (nur Admins)](#einstellungen-nur-admins)

---

## Übersicht

Die Übersicht bietet einen Überblick über den aktuellen Stand:

- **Offene Themen**: Anzahl der noch nicht bearbeiteten Themen
- **In Arbeit**: Themen, die gerade bearbeitet werden
- **Geplant**: Themen, die für später eingeplant sind
- **Erledigt (30 Tage)**: Kürzlich abgeschlossene Themen
- **Ungelesene Nachrichten**: Private Nachrichten, die noch nicht gelesen wurden
- **Aktuelle Themen**: Die 10 neuesten Themen
- **Projekte**: Übersicht der Projekte mit Fortschrittsanzeige

---

## Themen verwalten

### Neues Thema erstellen

1. Navigiere zu **Themen** → **Neues Thema**
2. Fülle das Formular aus:
   - **Titel**: Kurze, prägnante Beschreibung des Problems oder Wunsches
   - **Beschreibung**: Detaillierte Beschreibung (Markdown wird unterstützt)
   - **Kategorie**: Wähle die passende Kategorie
   - **Priorität**: Niedrig, Normal, Hoch oder Dringend
   - **Fälligkeit**: Optional eine Deadline setzen
   - **Tags**: Optional Tags zur Kategorisierung hinzufügen
   - **Dateianhänge**: Bilder, PDFs oder andere Dateien hochladen
3. Klicke auf **Speichern**

### Thema bearbeiten

1. Öffne ein Thema durch Klicken auf den Titel
2. Nutze die **Bearbeiten**-Funktion für Änderungen
3. Ändere den **Status** über das Dropdown-Menü
4. Füge **Kommentare** hinzu um mit anderen zu kommunizieren

### Kommentare

- Scrolle zum Kommentar-Bereich am Ende eines Themas
- Gib deinen Kommentar ein (Markdown wird unterstützt)
- Optional: Markiere den Kommentar als **Intern** (nur für Admins sichtbar)
- Klicke auf **Kommentar hinzufügen**

Kommentare können:
- Als **Lösung** markiert werden
- **Angepinnt** werden (wichtige Informationen oben anzeigen)
- Bearbeitet oder gelöscht werden (Ersteller oder Admin)

### Status-Übersicht

| Status | Bedeutung |
|--------|-----------|
| Offen | Neues Thema, noch nicht bearbeitet |
| In Arbeit | Thema wird gerade bearbeitet |
| Geplant | Thema ist für später eingeplant |
| Abgelehnt | Thema wurde abgelehnt |
| Erledigt | Thema wurde abgeschlossen |

---

## Projekte

Projekte ermöglichen die Gruppierung von Themen.

### Projekt erstellen (Admin)

1. Navigiere zu **Projekte** → **Neues Projekt**
2. Gib einen **Namen** und optional eine **Beschreibung** ein
3. Wähle die **Mitglieder** aus
4. Klicke auf **Speichern**

### Projekt-Mitgliedschaft

- **Wichtig**: Du siehst nur Projekte, denen du als Mitglied zugeordnet bist
- Admins sehen alle Projekte
- Themen können einem Projekt zugeordnet werden

### Projekt-Übersicht

Die Projektliste zeigt:
- Projektname und Beschreibung
- Anzahl offener / geschlossener Themen
- Fortschrittsbalken

---

## Private Nachrichten

Das Nachrichtensystem ermöglicht die direkte Kommunikation zwischen Benutzern.

### Nachricht senden

1. Navigiere zu **Nachrichten** → **Neue Nachricht**
2. Wähle den **Empfänger** aus
3. Gib einen **Betreff** und die **Nachricht** ein
4. Klicke auf **Senden**

### Posteingang

- Zeigt alle empfangenen Nachrichten
- Nachrichten werden als **Konversation** gruppiert
- Ungelesene Nachrichten werden hervorgehoben
- Klicke auf eine Konversation um alle Nachrichten zu sehen

### Gesendete Nachrichten

- Zeigt alle von dir gesendeten Nachrichten
- Ebenfalls als Konversation gruppiert

### Ungelesen-Badge

- Die Navigation zeigt die Anzahl ungelesener Nachrichten
- Auch das Dashboard zeigt ungelesene Nachrichten an

### E-Mail-Benachrichtigungen

Wenn du E-Mail-Benachrichtigungen für private Nachrichten aktiviert hast:

- Du erhältst eine E-Mail bei jeder neuen Nachricht
- Optional kann der vollständige Nachrichtentext in der E-Mail enthalten sein
- Der **Link in der E-Mail ist nur einmalig gültig** (aus Sicherheitsgründen)
- Nach dem Klick wirst du zur Nachricht weitergeleitet (ggf. nach Login)
- Der Link ist 30 Tage lang gültig

---

## Filter und Suche

### Themen filtern

Die Filter-Leiste bietet:
- **Status**: Alle aktiven, Alle Themen, Nur Geschlossene, oder einzelner Status
- **Kategorie**: Nach Kategorie filtern
- **Tags**: Nach Tags filtern
- **Erstellt von**: Nach Ersteller filtern
- **Suche**: Volltextsuche in Titel und Beschreibung

### Filter speichern

1. Stelle die gewünschten Filter ein
2. Klicke auf **Filter speichern**
3. Gib einen Namen ein
4. Optional: Als **Standard** festlegen

Gespeicherte Filter erscheinen als Buttons über der Filter-Leiste.

### Session-Speicherung

- Filter bleiben während deiner Sitzung erhalten
- Wenn du ein Thema öffnest und zur Liste zurückkehrst, sind die Filter noch aktiv
- Mit **Reset** werden alle Filter zurückgesetzt

---

## Benachrichtigungseinstellungen

Jeder Benutzer kann seine E-Mail-Benachrichtigungen individuell konfigurieren.

### Einstellungen anpassen

1. Navigiere zu **Benachrichtigungen**
2. Aktiviere oder deaktiviere:
   - E-Mail bei neuen Themen
   - E-Mail bei neuen Kommentaren
   - E-Mail bei Status-Änderungen
   - E-Mail bei Zuweisungen
   - E-Mail bei privaten Nachrichten
3. Optional: **Vollständiger Nachrichtentext** in E-Mails (bei privaten Nachrichten)
4. Klicke auf **Speichern**

### Wichtig: E-Mail-Links

Die Links in den E-Mail-Benachrichtigungen sind aus Sicherheitsgründen:
- **Nur einmalig verwendbar** (One-Time-Token)
- **30 Tage gültig**
- Falls du nicht eingeloggt bist, wirst du zuerst zur Anmeldung geleitet
- Nach der Anmeldung erfolgt automatisch die Weiterleitung zum Thema bzw. zur Nachricht

---

## Einstellungen (nur Admins)

### Allgemeine Einstellungen

- **Menü-Titel**: Passe den Namen des Menüpunkts an (z.B. "Support", "Tickets")
- **Kategorien**: Füge Kategorien hinzu oder entferne sie
- **E-Mail-Benachrichtigungen**: Aktiviere oder deaktiviere globale E-Mail-Funktion
- **Absender-Name**: Name des E-Mail-Absenders

### Tags verwalten

1. Navigiere zu **Einstellungen** → **Allgemein**
2. Scrolle zum Bereich **Tags**
3. Füge neue Tags mit **Name** und **Farbe** hinzu
4. Bearbeite oder lösche bestehende Tags

**Hinweis**: Doppelte Tag-Namen werden automatisch erkannt und abgelehnt.

### Broadcast-Nachricht

Admins können Nachrichten an alle berechtigten Benutzer senden:

1. Navigiere zu **Nachrichten** → **Broadcast**
2. Gib **Betreff** und **Nachricht** ein
3. Wähle die **Empfänger**:
   - **Nur Issue-Tracker User**: Benutzer mit Issue-Tracker-Berechtigung
   - **Alle REDAXO User**: Alle aktiven Backend-Benutzer (nur per E-Mail)
4. Wähle die **Versandart**:
   - **Internes Nachrichtensystem**: Nachricht erscheint im Posteingang
   - **Nur per E-Mail**: Nachricht wird per E-Mail versendet
   - **Nachrichtensystem und E-Mail**: Beide Kanäle gleichzeitig
5. Klicke auf **Broadcast senden** und bestätige die Aktion

**Hinweis**: Bei "Alle REDAXO User" ist nur E-Mail-Versand möglich, da diese User keinen Zugang zum internen Nachrichtensystem haben.

### Backup & Export

- **Export**: Exportiert alle Themen als JSON-Datei
- **Import**: Importiert Themen aus einer JSON-Datei

---

## Tastenkürzel und Tipps

- **Markdown**: In Beschreibungen und Kommentaren kannst du Markdown verwenden
- **@-Mentions**: Erwähne andere Benutzer mit @username
- **Dateianhänge**: Unterstützt Bilder, PDFs, Dokumente und mehr
- **Dark Mode**: Das AddOn unterstützt den dunklen REDAXO-Modus

---

## Berechtigungen

| Rolle | Rechte |
|-------|--------|
| issue_tracker[] | Nur Lesen |
| issue_tracker[issuer] | Erstellen und Kommentieren |
| issue_tracker[issue_manager] | Alle Themen bearbeiten |
| Admin | Vollzugriff inkl. Einstellungen |

---

## Support

Bei Fragen oder Problemen:
- REDAXO Slack: https://redaxo.org/slack
- GitHub: https://github.com/FriendsOfREDAXO/issue_tracker
