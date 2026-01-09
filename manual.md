# Issue Tracker - Benutzerhandbuch

Dieses Handbuch erklärt die Verwendung des Issue Trackers für Redakteure und Administratoren.

## Inhaltsverzeichnis

1. [Dashboard](#dashboard)
2. [Issues verwalten](#issues-verwalten)
3. [Projekte](#projekte)
4. [Private Nachrichten](#private-nachrichten)
5. [Filter und Suche](#filter-und-suche)
6. [Benachrichtigungseinstellungen](#benachrichtigungseinstellungen)
7. [Einstellungen (nur Admins)](#einstellungen-nur-admins)

---

## Dashboard

Das Dashboard bietet eine Übersicht über den aktuellen Stand:

- **Offene Issues**: Anzahl der noch nicht bearbeiteten Issues
- **In Arbeit**: Issues, die gerade bearbeitet werden
- **Geplant**: Issues, die für später eingeplant sind
- **Erledigt (30 Tage)**: Kürzlich abgeschlossene Issues
- **Ungelesene Nachrichten**: Private Nachrichten, die noch nicht gelesen wurden
- **Aktuelle Issues**: Die 10 neuesten Issues
- **Projekte**: Übersicht der Projekte mit Fortschrittsanzeige

---

## Issues verwalten

### Neues Issue erstellen

1. Navigiere zu **Issues** → **Neues Issue**
2. Fülle das Formular aus:
   - **Titel**: Kurze, prägnante Beschreibung des Problems oder Wunsches
   - **Beschreibung**: Detaillierte Beschreibung (Markdown wird unterstützt)
   - **Kategorie**: Wähle die passende Kategorie
   - **Priorität**: Niedrig, Normal, Hoch oder Dringend
   - **Fälligkeit**: Optional eine Deadline setzen
   - **Tags**: Optional Tags zur Kategorisierung hinzufügen
   - **Dateianhänge**: Bilder, PDFs oder andere Dateien hochladen
3. Klicke auf **Speichern**

### Issue bearbeiten

1. Öffne ein Issue durch Klicken auf den Titel
2. Nutze die **Bearbeiten**-Funktion für Änderungen
3. Ändere den **Status** über das Dropdown-Menü
4. Füge **Kommentare** hinzu um mit anderen zu kommunizieren

### Kommentare

- Scrolle zum Kommentar-Bereich am Ende eines Issues
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
| Offen | Neues Issue, noch nicht bearbeitet |
| In Arbeit | Issue wird gerade bearbeitet |
| Geplant | Issue ist für später eingeplant |
| Abgelehnt | Issue wurde abgelehnt |
| Erledigt | Issue wurde abgeschlossen |

---

## Projekte

Projekte ermöglichen die Gruppierung von Issues.

### Projekt erstellen (Admin)

1. Navigiere zu **Projekte** → **Neues Projekt**
2. Gib einen **Namen** und optional eine **Beschreibung** ein
3. Wähle die **Mitglieder** aus
4. Klicke auf **Speichern**

### Projekt-Mitgliedschaft

- **Wichtig**: Du siehst nur Projekte, denen du als Mitglied zugeordnet bist
- Admins sehen alle Projekte
- Issues können einem Projekt zugeordnet werden

### Projekt-Übersicht

Die Projektliste zeigt:
- Projektname und Beschreibung
- Anzahl offener / geschlossener Issues
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

---

## Filter und Suche

### Issues filtern

Die Filter-Leiste bietet:
- **Status**: Alle aktiven, Alle Issues, Nur Geschlossene, oder einzelner Status
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
- Wenn du ein Issue öffnest und zur Liste zurückkehrst, sind die Filter noch aktiv
- Mit **Reset** werden alle Filter zurückgesetzt

---

## Benachrichtigungseinstellungen

Jeder Benutzer kann seine E-Mail-Benachrichtigungen individuell konfigurieren.

### Einstellungen anpassen

1. Navigiere zu **Benachrichtigungen**
2. Aktiviere oder deaktiviere:
   - E-Mail bei neuen Issues
   - E-Mail bei neuen Kommentaren
   - E-Mail bei Status-Änderungen
   - E-Mail bei Zuweisungen
   - E-Mail bei privaten Nachrichten
3. Optional: **Vollständiger Nachrichtentext** in E-Mails (bei privaten Nachrichten)
4. Klicke auf **Speichern**

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

Admins können Nachrichten an alle Benutzer senden:

1. Navigiere zu **Einstellungen** → **Allgemein**
2. Scrolle zum Bereich **Broadcast-Nachricht**
3. Gib **Betreff** und **Nachricht** ein
4. Klicke auf **Broadcast senden**

### Backup & Export

- **Export**: Exportiert alle Issues als JSON-Datei
- **Import**: Importiert Issues aus einer JSON-Datei

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
| issue_tracker[issue_manager] | Alle Issues bearbeiten |
| Admin | Vollzugriff inkl. Einstellungen |

---

## Support

Bei Fragen oder Problemen:
- REDAXO Slack: https://redaxo.org/slack
- GitHub: https://github.com/FriendsOfREDAXO/issue_tracker
