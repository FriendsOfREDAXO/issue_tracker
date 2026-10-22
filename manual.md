# Issue Tracker – Benutzerhandbuch

Dieses Handbuch erklärt die Bedienung des Issue Trackers für Redakteure und Administratoren.

## Inhaltsverzeichnis

1. [Überblick](#überblick)
   - [Öffnen und Schließen](#öffnen-und-schließen)
   - [Das Postfach](#das-postfach)
2. [Themen](#themen)
3. [Wissen](#wissen)
4. [Projekte](#projekte)
5. [Nachrichten](#nachrichten)
6. [Filter, Suche und Export](#filter-suche-und-export)
7. [Ansicht anpassen](#ansicht-anpassen)
8. [Benachrichtigungen](#benachrichtigungen)
9. [Einstellungen (nur Admins)](#einstellungen-nur-admins)
10. [Tastenkürzel und Tipps](#tastenkürzel-und-tipps)
11. [Berechtigungen](#berechtigungen)

---

## Überblick

Der Issue Tracker funktioniert wie ein Mail-Programm: links die **Postfächer**, in der Mitte die **Liste** mit Vorschau, rechts der **Lesebereich** mit dem Gesprächsverlauf. Themen, Nachrichten, Projekte und Wissen liegen alle im selben Fenster.

### Öffnen und Schließen

Der Issue Tracker öffnet sich als eigene Ebene über dem Backend:

- **Von jeder Backend-Seite aus**: über den Menüpunkt „Issue Tracker“ oder die Knöpfe „Issue Tracker“ und „Neues Thema“ oben in der Kopfzeile.
- **Schließen**: Knopf „Schließen“ oben rechts oder die Taste Esc (wenn gerade kein Eingabefeld, Menü oder Fenster offen ist). Sie kehren genau zu der Seite zurück, auf der Sie waren.
- **Adresse und Zurück**: Solange der Issue Tracker offen ist, steht seine Adresse in der Adresszeile – Sie können sie kopieren, als Lesezeichen speichern oder die Seite neu laden. Die Zurück- und Vor-Taste des Browsers blättern innerhalb des Issue Trackers; Zurück vor die erste Ansicht schließt ihn wieder.
- **Fenster oder Vollbild**: Der Knopf neben „Schließen“ vergrößert das Fenster auf die ganze Fläche und zurück; der Browser merkt sich die Wahl.
- **Neues Thema zur aktuellen Seite**: „Neues Thema“ merkt sich die Backend-Seite, auf der Sie gerade waren („Gemeldet von der Backend-Seite …“). Bearbeiten Sie gerade einen Artikel, ist er als betroffene Seite vorbelegt. Mit dem Kreuz neben dem Hinweis nehmen Sie die Seite wieder heraus.
- Admins können die Ebene unter **Einstellungen → Allgemein** („Als Vollbild öffnen“) ausschalten. Dann erscheint der Issue Tracker innerhalb der REDAXO-Oberfläche.

### Das Postfach

Die Seitenleiste enthält:

- **Themen**: Alle aktiven, Mir zugewiesen, Von mir erstellt, Beobachtet, Erwähnt, Überfällig und **Kürzlich erledigt** (in den letzten 7 Tagen erledigte Themen, mit grüner Zahl), darunter die Themen nach Status.
- **Nachrichten**: Posteingang und Gesendet.
- **Projekte**: ein Ordner je Projekt; über **…** bearbeiten, Mitglieder verwalten oder archivieren. Archivierte Projekte stehen unter „Archiviert“.
- **Wissen**: Wissenseinträge, nach Kategorie.
- **Tags** und **Gespeicherte Filter**.

Bereiche der Seitenleiste lassen sich auf- und zuklappen; der Issue Tracker merkt sich den Zustand und das zuletzt geöffnete Postfach. Der Knopf **Neu** legt an, was gerade passt (Thema, Thema im Projekt, Nachricht); über den Pfeil daneben erreichen Sie die weiteren Einträge (Neue Nachricht, Neuer Wissenseintrag, Rundschreiben senden, Neues Projekt – je nach Berechtigung). Das Zahnrad-Menü enthält Meine Benachrichtigungen, Einstellungen, Hilfe, Tastaturkürzel und die Schriftgröße.

Oben in der Liste wechseln Sie zwischen **Postfach** und **Tabelle**; die Wahl wird gespeichert. Auf schmalen Bildschirmen führt die Ansicht Schritt für Schritt: Postfächer, Liste, Thema.

---

## Themen

### Neues Thema erstellen

1. Klicken Sie auf **Neu** (bzw. **Neues Thema** in der Kopfzeile des Backends).
2. Setzen Sie oben in der **Eigenschaften-Leiste** die wichtigsten Werte:
   - **Zuweisen an** (Pflicht): eine Person oder „Alle“
   - **Status** und **Priorität**
   - **Kategorie** und **Projekt**
   - **Fälligkeit**: optional ein Termin
   - **Tags**: ankreuzen, mit Suchfeld; Admins können direkt ein neues Tag anlegen
   - **Betroffene Seite**: optional einen Artikel über die Linkmap aus der Struktur wählen oder eine Adresse eingeben (https://… oder /pfad)
3. Füllen Sie **Titel** und **Beschreibung** aus (Markdown wird unterstützt) und hängen Sie bei Bedarf Dateien an – auch per Ziehen in das Fenster.
4. Privat, AddOn, Version, Domains und YForm-Tabellen liegen unter **Weitere Angaben**.
5. Klicken Sie auf **Thema erstellen**.

Ungültige Adressen werden direkt am Feld gemeldet; **Keine Seite** entfernt Artikel und Adresse wieder. Im Lesebereich erscheint die betroffene Seite mit den Links **Seite ansehen** (Frontend) und **Bearbeiten** (Inhalt im Backend, bei entsprechenden Rechten). Auch E-Mail-Benachrichtigungen, der PDF- und der CSV-Export nennen die betroffene Seite.

Wer das Formular mit ungespeicherten Eingaben verlässt (z. B. über **Abbrechen** oder **Schließen**), wird vorher gefragt. Nach dem Speichern bestätigt eine kurze Einblendung, dass das Thema angelegt bzw. geändert wurde.

### Im Lesebereich arbeiten

Ein Klick auf ein Thema in der Liste öffnet es im Lesebereich. Dort ändern Sie die meisten Angaben direkt, ohne das Formular zu öffnen:

- **Status** und **Priorität**: Ein Klick auf die farbige Pill im Kopf des Themas öffnet die Auswahl. Die Priorität lässt sich auch über das Fähnchen in der Symbolleiste setzen.
- **Tags**: mit **+ Tag** hinzufügen, mit dem Kreuz an der Tag-Pill entfernen. In der Liste öffnet das Tag-Symbol einer Zeile (oder die Taste **T**) ein kleines Fenster zum schnellen Setzen.
- **Symbolleiste**: Antworten (**R**), Zuweisen, Priorität, Beobachten, Bearbeiten, Als erledigt markieren bzw. Wieder öffnen, Löschen (Admins und Manager), Link kopieren und Vollansicht.
- **Beobachter**: Wer ein Thema beobachtet, wird über Änderungen benachrichtigt. Ersteller, Manager und Admins können weitere Personen einladen oder entfernen.
- **Erinnerung senden**: erinnert die zugewiesene Person per E-Mail (mit Wartezeit zwischen zwei Erinnerungen).
- **Mit verwandtem Thema verknüpfen** (Admins): markiert das Thema als Duplikat eines anderen und erledigt es; die Verknüpfung lässt sich wieder lösen.
- **Aktivitätsverlauf**: alle Änderungen am Thema (Admins und Manager).

### Antworten und Kommentare

- Schreiben Sie Ihre Antwort unten im Lesebereich oder über **Antworten** in der Symbolleiste. Markdown, Dateianhänge und @-Erwähnungen werden unterstützt.
- **Antworten und erledigen** markiert das Thema mit dieser Antwort als erledigt (eine gemeinsame E-Mail).
- **Interner Kommentar** (nur Admins): nur für Admins und Manager sichtbar.
- An einem Kommentar: **Antworten** (Antwort darunter), **Bearbeiten** (eigene Kommentare, Admins alle), **Anheften** und **Als Lösung markieren** (Ersteller des Themas und Admins), **Löschen** (Admins, mit Rückfrage).

### Checklisten, Zeiterfassung, Verweise

- `- [ ] Aufgabe` und `- [x] Erledigt` in Beschreibung oder Kommentar werden zu Kästchen, die sich per Klick abhaken lassen – sofern Sie die Beschreibung bzw. den Kommentar bearbeiten dürfen; sonst werden sie nur angezeigt. Ein Fortschrittsbalken zeigt den Stand.
- `/spent 1h 30m` in einem Kommentar erfasst Zeit für das Thema; die Summe steht bei den Angaben.
- `#42` wird zu einem Link auf Thema 42; dort erscheint ein Verweis im Aktivitätsverlauf.

### Status-Übersicht

| Status | Bedeutung |
|--------|-----------|
| Offen | Neues Thema, noch nicht bearbeitet |
| In Arbeit | Thema wird gerade bearbeitet |
| Geplant | Thema ist für später eingeplant |
| Info | Wissenseintrag (siehe [Wissen](#wissen)) |
| Abgelehnt | Thema wurde abgelehnt |
| Erledigt | Thema wurde abgeschlossen |

---

## Wissen

Wissenseinträge sind Anleitungen und Hinweise für alle Benutzer (Status „Info“). Sie stehen in der Seitenleiste unter **Wissen**, sortiert nach Kategorie.

- **Neuer Wissenseintrag**: über den Pfeil neben **Neu** (Admins und Manager).
- **Bearbeiten**: Stift in der Symbolleiste oder Taste **E**.
- **Aus Wissen entfernen**: macht aus dem Eintrag ein erledigtes Thema (mit Rückfrage).

---

## Projekte

Projekte bündeln Themen. Jedes Projekt ist ein Ordner in der Seitenleiste.

- **Neues Projekt** (Admins und Manager): in der Seitenleiste unter Projekte oder über den Pfeil neben **Neu**. Geben Sie Name, Farbe, optional Beschreibung, Fälligkeit und Mitglieder an.
- **Bearbeiten und Mitglieder**: über **…** am Projektordner. Mitglieder erhalten eine Rolle (Owner, Mitglied, Beobachter).
- **Archivieren**: über **…** am Projektordner (mit Rückfrage). Archivierte Projekte bleiben erhalten und stehen unter „Archiviert“.
- **Löschen**: unten auf der Seite „Projekt bearbeiten“ (mit Rückfrage). Die Themen bleiben erhalten und verlieren nur die Zuordnung.
- Sie sehen nur Projekte, denen Sie als Mitglied zugeordnet sind; Admins sehen alle. Bearbeiten, archivieren und löschen dürfen Owner des Projekts und Admins.

---

## Nachrichten

Mit Nachrichten schreiben sich Benutzer direkt, ohne Thema.

- **Neue Nachricht**: über **Neu** im Bereich Nachrichten oder über den Pfeil daneben. Empfänger, Betreff und Text angeben, **Senden**.
- **Posteingang** und **Gesendet** gruppieren die Nachrichten als Gespräch mit derselben Person; ungelesene sind hervorgehoben und stehen mit Zahl in der Seitenleiste.
- **Antworten**: direkt im Antwortfeld unter der Nachricht (Taste **R**).
- **Weiterleiten** (Taste **F**): an eine oder mehrere Personen, mit eigener Notiz und dem Original als Zitat.
- **Als ungelesen markieren** (Taste **U**, schaltet um) und **Alle als gelesen markieren** im Posteingang.
- **Löschen**: über den Papierkorb in der Symbolleiste (mit Rückfrage); die Nachricht verschwindet nur bei Ihnen.

### Rundschreiben (nur Admins)

1. Pfeil neben **Neu** → **Rundschreiben senden**.
2. Empfänger wählen: **Nur Issue-Tracker-Benutzer** oder **Alle REDAXO-Benutzer** (nur per E-Mail, da diese keinen Zugang zu den Nachrichten haben).
3. Versandart wählen: Nachricht im Posteingang, nur E-Mail oder beides.
4. Betreff und Text eingeben, absenden und bestätigen.

---

## Filter, Suche und Export

- **Suche** oben in der Liste: Titel und Beschreibung.
- **Sortieren** und **Filtern** über die Symbole daneben: Status, Kategorie, Tag, Ersteller, Zuständig, Projekt. **Zurücksetzen** hebt alle Filter auf.
- Filter bleiben erhalten, wenn Sie ein Thema öffnen und zurückkehren.
- **Filter speichern**: in der Seitenleiste unter **Gespeicherte Filter** → **Filter speichern**, Namen eingeben, optional als Standard. Über **…** am gespeicherten Filter legen Sie ihn als Standard fest oder löschen ihn (mit Rückfrage).
- **Lange Listen**: Das Postfach zeigt zunächst 50 Einträge; **Weitere laden** hängt die nächsten 50 an, Auswahl und Scrollposition bleiben erhalten.
- **Export** (Download-Symbol): **Als CSV exportieren** und – wenn das AddOn PdfOut installiert ist – **Als PDF exportieren**. Beide berücksichtigen die aktuellen Filter und enthalten nur Themen, die Sie sehen dürfen.

---

## Ansicht anpassen

- **Schriftgröße**: im Zahnrad-Menü mit A− / 100 % / A+ (90–140 %). Gilt pro Benutzer auf allen Geräten.
- **Spaltenbreite**: Die Trennlinien zwischen Postfächern, Liste und Lesebereich lassen sich ziehen (auch mit den Pfeiltasten); Doppelklick stellt die Standardbreite wieder her. Wird pro Benutzer gespeichert.
- **Hell und dunkel**: folgt der Einstellung im REDAXO-Profil bzw. des Systems.

---

## Benachrichtigungen

Unter Zahnrad-Menü → **Meine Benachrichtigungen** legt jeder Benutzer fest, wofür er E-Mails erhält:

- neue Themen, neue Kommentare, Status-Änderungen, Zuweisungen, Erwähnungen, private Nachrichten
- optional den vollständigen Nachrichtentext in der E-Mail

Ist in Ihrem REDAXO-Profil keine E-Mail-Adresse hinterlegt, können Sie sie dort direkt eintragen.

Die Links in den E-Mails sind aus Sicherheitsgründen **nur einmal verwendbar** und **30 Tage gültig**. Sind Sie nicht angemeldet, werden Sie nach der Anmeldung automatisch zum Thema bzw. zur Nachricht geleitet.

---

## Einstellungen (nur Admins)

### Allgemein

- **Name im Menü**: z. B. „Support“ oder „Tickets“.
- **Als Vollbild öffnen**: Issue Tracker als Ebene über dem Backend (Standard) oder innerhalb der REDAXO-Oberfläche.
- **Kategorien**: hinzufügen oder entfernen (mindestens eine).
- **E-Mails verschicken** und **Absender-Name**; unter „Erweitert“ Absender-Adresse, Wartezeit für Erinnerungen, Name der Installation und der **API-Token** für die Schnittstelle.
- **Tags**: mit Name und Farbe anlegen, bearbeiten oder löschen. Doppelte Namen werden abgelehnt.

### E-Mail-Templates

Alle Vorlagen (Neues Thema, Kommentar, Status, Zuweisung, Erinnerung …) lassen sich anpassen. Platzhalter fügen Sie per Klick ein, die Vorschau zeigt das Ergebnis. **Test an mich senden** schickt eine Probe an Ihre Adresse, **Zurücksetzen** stellt die mitgelieferten Vorlagen wieder her (mit Rückfrage).

### Backup/Export

- **Export**: alle Projekte, Themen (mit Kommentaren und Tags) und Nachrichten als JSON-Datei.
- **Import**: spielt eine solche Datei ein und legt die Einträge zusätzlich an (mit Rückfrage).

---

## Tastenkürzel und Tipps

Eine Übersicht aller Tastenkürzel öffnet die Taste **?** oder das Zahnrad-Menü → **Tastaturkürzel**. Die Tasten wirken, solange der Cursor nicht in einem Eingabefeld steht.

- **↑/↓**: in der Liste zum vorherigen/nächsten Eintrag
- **Enter**: ausgewähltes Thema in der Vollansicht öffnen
- **T**: Tags des ausgewählten Themas in der Liste bearbeiten
- **R**: Antworten
- **E**: Wissenseintrag bearbeiten · **F**: Nachricht weiterleiten · **U**: Nachricht gelesen/ungelesen
- **Esc**: Menü oder Fenster schließen, sonst den Issue Tracker
- **Strg/Cmd + S**: Formular speichern
- **@-Erwähnungen**: Erwähnen Sie andere Benutzer mit @login.
- **Dateianhänge**: Bilder, PDFs, Dokumente und mehr; Bilder und Videos öffnen in einer Lightbox.

---

## Berechtigungen

| Recht | Möglichkeiten |
|-------|---------------|
| issue_tracker[] | Issue Tracker öffnen, Themen lesen, Nachrichten |
| issue_tracker[issuer] | Themen erstellen und kommentieren, eigene und zugewiesene Themen bearbeiten |
| issue_tracker[issue_manager] | Alle Themen bearbeiten und löschen, Wissen pflegen, Projekte anlegen, interne Kommentare und Verlauf sehen |
| Admin | Vollzugriff inkl. Einstellungen, private Themen, Rundschreiben |

---

## Support

Bei Fragen oder Problemen:

- REDAXO Slack: https://redaxo.org/slack
- GitHub: https://github.com/FriendsOfREDAXO/issue_tracker
