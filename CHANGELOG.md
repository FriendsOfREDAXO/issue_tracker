# Changelog

Alle nennenswerten Änderungen am Issue Tracker AddOn werden hier dokumentiert.

## [2.0.0] – unveröffentlicht

Version 2.0 ist eine komplett neue Oberfläche für den Issue Tracker. Alle Funktionen, Rechte, Daten und Links aus E-Mails bleiben erhalten. Die Einzelheiten stehen in den Abschnitten der Vorabversionen (2.0.0-beta1 bis beta11) weiter unten.

### Was 2.0 gegenüber 1.x bringt

- **Wie Mail auf dem Mac**: Links die Postfächer (Mir zugewiesen, Beobachtet, Erwähnt, Überfällig, Status, Projekte, Wissen, Tags, gespeicherte Filter), in der Mitte die Liste mit Vorschau, rechts der Lesebereich mit dem Gesprächsverlauf. Die klassische Tabelle bleibt umschaltbar. Tastatur wie in Mail (↑/↓, R, T, F, U, ? für alle Kürzel), am Handy Schritt für Schritt wie am iPhone, hell und dunkel. Das Dashboard und die einzelnen Unterseiten sind im Postfach aufgegangen.
- **Eigenes Fenster über dem Backend**: Der Issue Tracker öffnet sich von jeder Backend-Seite aus (Menüpunkt, Knöpfe „Issue Tracker“ und „Neues Thema“ in der Kopfzeile) als Ebene, wahlweise als Fenster oder Vollbild. Seitenwechsel laufen per PJAX ohne Neuladen; Adresse, Zurück/Vor und Neuladen funktionieren, „Schließen“ oder Esc führen zur vorherigen Seite zurück.
- **Nachrichten** im selben Postfach: Posteingang, Gesendet, Antworten direkt unter der Nachricht, Weiterleiten, als ungelesen markieren, alle als gelesen markieren, Rundschreiben.
- **Kürzlich erledigt**: die in den letzten 7 Tagen erledigten Themen mit eigener Zahl.
- **Eigenschaften-Leiste** beim Anlegen und Bearbeiten (Zuweisen, Status, Priorität, Kategorie, Projekt, Fälligkeit, Tags, betroffene Seite); im Lesebereich Status und Priorität direkt an der Pill ändern.
- **Quick-Tags**: Tags direkt in der Liste setzen (Taste T), Admins legen neue Tags gleich dort an.
- **Betroffene Seite**: Artikel aus der Struktur (Linkmap) oder Adresse; „Neues Thema“ übernimmt die Backend-Seite, von der aus es angelegt wird. Auch in E-Mails, CSV- und PDF-Export.
- **Schriftgröße** (90–140 %) und **Spaltenbreiten** pro Benutzer einstellbar.
- **Sicherheit**: Alle Aktionen sind gegen CSRF geschützt (Formulare, Aktions-Links, API-Aufrufe), riskante Aktionen fragen vorher nach.

### Hinweise zum Update von 1.x

- Neue Tabellen und Spalten (u. a. betroffene Seite, Herkunftsseite) werden beim Update automatisch angelegt; Daten bleiben erhalten.
- Unveränderte Standard-Mailvorlagen werden automatisch um den neuen Platzhalter `{{affected_page}}` ergänzt. **Selbst angepasste Vorlagen bleiben unangetastet** – dort `{{affected_page}}` bei Bedarf von Hand einfügen (Einstellungen → E-Mail-Templates).
- Die Ebene über dem Backend ist standardmäßig an und lässt sich unter Einstellungen → Allgemein mit **„Als Vollbild öffnen“** abschalten; dann erscheint der Issue Tracker wie bisher innerhalb der REDAXO-Oberfläche.
- Eigene Stile oder Skripte, die sich auf die Klassen der 1.x-Oberfläche bezogen haben, müssen angepasst werden (neue Klassen mit Präfix `it-`).

### Korrekturen seit 2.0.0-beta11 (Prüfung aller Aktionen)

- Fix: Rückfragen vor riskanten Aktionen wurden beim direkten Aufruf einer Seite (Link aus einer E-Mail, Neuladen) übergangen – „Abbrechen“ führte die Aktion trotzdem aus. Betroffen waren u. a. Kommentar löschen, Backup einspielen, Mailvorlagen zurücksetzen, Projekt archivieren, gespeicherten Filter löschen, Erinnerung senden und „Aus Wissen entfernen“. Die Ebene fängt Formulare jetzt erst nach der Rückfrage ab.
- Fix: „Filter speichern“ verließ die Ebene und lud die ganze Seite neu; das Fenster bleibt jetzt in der Ebene.
- Fix: CSV- und PDF-Export brachen für Benutzer ohne Admin-Rechte mit einem Datenbankfehler ab (falscher Spaltenname). Exportiert werden jetzt die Themen, die der Benutzer sehen darf (wie in der Ansicht).
- Fix: Backup: Antworten auf Kommentare wurden ohne Bezug exportiert, und beim Import scheiterten Kommentare (falscher Spaltenname) – das Thema wurde ohne Kommentare angelegt. Ältere Sicherungen bleiben lesbar.
- Fix: Projekte ließen sich nicht mehr löschen (keine Schaltfläche, die alte Adresse ohne CSRF-Schutz). Neu: „Projekt löschen“ unten auf der Seite „Projekt bearbeiten“, mit Rückfrage und CSRF-Token; die alte Lösch-Adresse ist entfernt.
- Fix: „Projekt anlegen“ und „Projekt bearbeiten“ waren für Manager (`issue_tracker[issue_manager]`) gesperrt, obwohl das Menü sie anbot (falsches Recht in `package.yml`).
- Fix: Checklisten in Beschreibungen und Kommentaren, die der Benutzer nicht bearbeiten darf, wirkten anklickbar, die Änderung ging aber verloren. Sie werden jetzt nur angezeigt.
- Fix: Beim Löschen eines Kommentars blieben seine Antworten unsichtbar in der Datenbank und seine Anhänge als Dateien zurück; Antworten und Anhänge werden jetzt mitgelöscht.
- Fix: Im Lesebereich zeigten Verweise wie `#50` in Thema 5 auf Thema 5 (die Adresse wurde beim Umschreiben der Checklisten-Ziele mit ersetzt).
- Fix: Enthielt ein Kommentar oder eine Beschreibung den Text `_csrf_token`, fehlte dem zugehörigen Formular der Token („CSRF-Token ungültig“).
- Handbuch (Hilfe), README und Installationsanleitung auf die neue Oberfläche aktualisiert.

## [2.0.0-beta11] – 2026-10-22

- Fix: Antworten auf eine Nachricht (Antwortfeld unter der Nachricht) scheiterte mit „CSRF-Token ungültig“ – das Formular schickt das Token jetzt mit.

## [2.0.0-beta10] – 2026-10-22

- Das Doppelpfeil-Symbol für den Status in der Symbolleiste ist entfernt (wurde leicht mit „Weiterleiten“ verwechselt). Der Status wird an der Status-Pill im Kopf des Themas geändert.

## [2.0.0-beta9] – 2026-10-22

- Fix: „Bearbeiten“ (Stift) und „Antworten“ an einem Kommentar zeigten in manchen Backends nur ein leeres Feld – das Formular wurde per `style.display` statt über das `hidden`-Attribut eingeblendet und blieb bei Stilen mit festem `[hidden]` unsichtbar.
- **Status und Priorität direkt an der Pill ändern**: Ein Klick auf die Status- bzw. Prioritäts-Pill im Lesebereich öffnet das Auswahlmenü (bisher nur über die Symbole in der Symbolleiste).

## [2.0.0-beta8] – 2026-10-22

- **Vollbild-App ohne iframe**: Die Ebene liegt jetzt direkt in der Backend-Seite; Seitenwechsel im Issue Tracker laufen per PJAX nach REDAXO-Konvention. Anfragen mit `X-PJAX: true` und dem Container `#it-app` rendert REDAXO ohne Backend-Layout (`rex_be_controller`), der Server liefert nur den App-Rahmen samt Inhalt – keine Navigation, kein Seitenkopf, keine erneut ausgeführten Skripte. Nach jedem Wechsel wird wie bei REDAXOs eigenem PJAX `rex:ready` auf dem neuen Inhalt ausgelöst (Auswahllisten, Editoren, Formulare usw. werden darüber neu aufgebaut).
- **Schneller**: Die Ebene erscheint beim Klick sofort mit einem Platzhalter (Kopfleiste und Spalten), der Inhalt folgt. Beim Zeigen auf den Menüpunkt oder die Knöpfe in der Kopfzeile werden Inhalt und Dateien der App vorab geladen, nach dem Laden einer Backend-Seite holt der Browser die Dateien im Leerlauf in den Cache (versionierte Adressen wie im REDAXO-Seitenkopf, ein gemeinsamer Cache mit den Seiten des Issue Trackers). Lokal gemessen (Median, Klick bis Inhalt sichtbar, beta7 → beta8): Öffnen 94 → 61 ms, mit Vorabladen 17 ms; Postfach wechseln 90 → 56 ms; Quick-Tag 40 → 7 ms. Lesebereich weiterhin nur als Teil nachgeladen. Kleine Aktionen zeigen das Ergebnis sofort (Tags in der Liste, Status-Menü, Beobachten, gelesen/ungelesen) und werden vom Server bestätigt bzw. bei Fehlern zurückgenommen. Statt eines Ladebildschirms erscheint bei längeren Wechseln (ab 150 ms) eine dezente Leiste oben im Fenster. Scrollpositionen von Seitenleiste und Liste sowie Spaltenbreiten bleiben bei Wechseln erhalten.
- **Adresse und Verlauf**: Solange die Ebene offen ist, steht die Adresse des Issue Trackers in der Adresszeile. Zurück/Vor blättern in der App (Lesebereich ohne Neuladen der Liste), Zurück vor die erste Ansicht schließt die Ebene, Vor öffnet sie wieder. „Schließen“ und Esc stellen die Adresse der Seite darunter wieder her, ohne sie neu zu laden; der Fokus kehrt zum Auslöser zurück. Neuladen und direkte Links (z. B. aus E-Mails) zeigen den Issue Tracker gleich im Fenster über dem Backend; „Schließen“ führt dann zur zuletzt besuchten Backend-Seite (sonst zur Startseite).
- **Formulare in der Ebene**: Filter (GET), Aktionen (POST mit CSRF-Token), Uploads mit Anhängen (FormData), Weiterleitungen nach dem Speichern und Fehlermeldungen landen in der Ebene. Downloads (CSV, Backup) laden normal herunter, Links zu anderen Backend-Seiten öffnen im ganzen Fenster, `target="_blank"` bleibt unberührt. Linkmap und Medienpool schreiben direkt in die Felder der Ebene (gleiches Dokument). Die Warnung bei ungespeicherten Änderungen gilt jetzt auch beim Schließen der Ebene, bei Wechseln in der App und bei Zurück.
- **Aufräumen**: Beim Wechsel und beim Schließen werden Editoren (EasyMDE samt Listener), Beobachter, Timer, Tag-Fenster und an `<body>` gehängte Fenster abgebaut; globale Ereignisse sind nur einmal gebunden (keine doppelten Bindungen nach PJAX-Wechseln).
- **Ohne Inline-Skripte**: `onclick`/`onsubmit`/`onchange`-Attribute und das Inline-Skript für das Nachrichten-Badge sind ersetzt – Rückfragen über REDAXOs `data-confirm`, Antworten/Bearbeiten über `data-it-toggle-reply`/`data-it-toggle-edit`, Sofort-Absenden über `data-it-autosubmit` (jetzt per `requestSubmit`), Badge über `data-it-unread-badge`. Doppelte Rückfragen (Inline-Handler und `data-confirm`) entfallen.
- `issue_tracker.js` aufgeteilt in einmalige, delegierte Bindungen und eine Initialisierung je Inhalt (`rex:ready` mit Container); neue Hilfe `window.ITEditors` (Editoren anlegen und abbauen). `it-mail.js`, `it-ui.js` initialisieren je Container. „REDAXO-Seite verlinken“ jetzt auch im Antwortfeld des nachgeladenen Lesebereichs.
- Entfernt: iframe-Rahmen und Brücke zum Elternfenster (`ITLauncher`, `it-app-framed`, `it-app-lock`, `it-ov-frame`, Ladebildschirm); Body-Klasse `it-app-mode`.
- `AppShell`: neue Methoden `isPjax()`, `overlayStart()`, `overlayEnd()`, `assetUrl()`, `cssFiles()`, `jsFiles()`; der App-Rahmen trägt Schriftgröße und Spaltenbreiten als `data-it-scale`/`data-it-cols`. Das Startskript in der Kopfzeile (`it-launcher.js`) bleibt klein; Inhalt und Dateien lädt es erst beim Öffnen.
- Fix: Ein Betreff nur aus Leerzeichen wird auch serverseitig abgelehnt.

## [2.0.0-beta7] – 2026-10-21

- **Status als Pill**: In der Liste steht der Status als farbige Pill; beim Anlegen und Bearbeiten ist der Status ein eigener Chip in der Eigenschaften-Leiste.
- **Vollbild-App (wie MediaPlace)**: Der Issue Tracker öffnet sich als eigene Ebene über dem Backend – mit eigener Kopfleiste (Symbol, „Issue Tracker“, aktueller Bereich, Postfach/Einstellungen/Hilfe und „Schließen“). Im Postfach ist das Fenster so hoch wie der Bildschirm: Postfächer, Liste und Lesebereich scrollen jeweils für sich, die Seite dahinter scrollt nie (kein Durchscrollen). Die Symbolleiste des Lesebereichs und der Listenkopf bleiben oben, „Antworten“ über die Symbolleiste (Taste R). Formulare, Einstellungen und Hilfe haben einen Scrollbereich. Bis 1199 px bleibt die schrittweise Navigation (Postfächer → Liste → Thema), die Kopfleiste mit „Schließen“ steht immer oben.
- **Von jeder Backend-Seite aus**: Der Menüpunkt „Issue Tracker“ und zwei neue Knöpfe in der Kopfzeile des Backends („Issue Tracker“, „Neues Thema“) öffnen die App als Ebene über der aktuellen Seite. „Schließen“ oder Esc (wenn kein Eingabefeld, Menü oder Fenster offen ist) führt ohne Neuladen dorthin zurück; der Fokus kehrt zum Auslöser zurück. Direkte Links (z. B. aus E-Mails) öffnen die App ebenfalls; „Schließen“ führt dann zur zuletzt besuchten Backend-Seite (sonst zur Startseite). Links zu anderen Backend-Seiten (z. B. „Bearbeiten“ eines Artikels) öffnen im ganzen Fenster. Ohne JavaScript führen Menüpunkt und Knöpfe einfach zur Seite. Nicht auf der Anmeldeseite und in Popups (Linkmap, Medienpool).
- **Neues Thema zur aktuellen Seite**: „Neues Thema“ (Kopfzeile oder in der App) übernimmt die Backend-Seite, von der aus der Issue Tracker geöffnet wurde: Hinweis „Gemeldet von der Backend-Seite …“ im Formular (abwählbar); wird gerade ein Artikel bearbeitet, ist er als „Betroffene Seite“ vorbelegt. Gespeichert und im Lesebereich unter „Gemeldet von Seite“ verlinkt. Nur Backend-Adressen dieser Installation werden übernommen (Prüfung auf dem Server; keine API-Aufrufe, Abmelde-Links oder CSRF-Token).
- **Einstellung „Als Vollbild öffnen“** (Allgemein, Standard: an). Ausgeschaltet erscheint der Issue Tracker wie bisher innerhalb der REDAXO-Oberfläche: keine festen Bereiche, keine eigenen Scrollbereiche, die Seite scrollt; keine Knöpfe in der Kopfzeile.
- Neue Klasse `AppShell` (Rahmen, Prüfung von Backend-Adressen), neue Assets `css/it-app.css`, `js/it-app.js` (nur auf Seiten des Issue Trackers) und `css/it-launcher.css`, `js/it-launcher.js` (klein, auf den übrigen Backend-Seiten für Benutzer mit Zugriff). Die Seite dahinter wird über eine eigene Klasse am `<html>` gesperrt; REDAXO-Stile bleiben unberührt.
- Datenbank: neue Spalten `source_url` (varchar 500, NULL) und `source_title` (varchar 255, NULL) in `rex_issue_tracker_issues`; neue Methoden `Issue::getSourceUrl()`, `getSourceTitle()`, `setSource()`. Auch im Backup-Export/-Import enthalten (beim Import erneut geprüft).
- **Fenster oder Vollbild** (wie MediaPlace): Der Issue Tracker öffnet als großes Fenster über der abgedunkelten Backend-Seite; der Knopf neben „Schließen“ vergrößert auf die ganze Fläche und zurück. Die Wahl merkt sich der Browser. Auf schmalen Bildschirmen immer ganze Fläche.

## [2.0.0-beta6] – 2026-10-21

- **Kürzlich erledigt**: neues Postfach unter „Themen“ mit den in den letzten 7 Tagen erledigten Themen und einer auffälligen grünen Zahl. Die Liste zeigt das Datum des Erledigens und sortiert die zuletzt erledigten nach oben.
- Postfach-Spalte standardmäßig etwas breiter, damit die Beschriftungen nicht abgeschnitten werden.
- **Fenster ohne feste Höhe**: Das Postfach folgt dem normalen Seitenfluss und die Seite scrollt wie jede REDAXO-Seite. Postfächer und Liste bleiben unter dem REDAXO-Kopf stehen (sticky) und scrollen nur selbst, wenn sie höher als das Fenster sind; die Symbolleiste des Lesebereichs bleibt oben, „Antworten“ bleibt am unteren Fensterrand erreichbar. Spaltenhintergrund und Trennlinien laufen neben langen Themen durch.
- **Eigenschaften-Leiste beim Anlegen und Bearbeiten**: Zuweisen an, Priorität, Kategorie, Projekt, Fälligkeit, Tags und betroffene Seite stehen als Chips oben im Formular (wie im Lesebereich) und werden mit dem neuen Thema gespeichert. Die bisherigen Einzelfelder dafür entfallen (keine doppelten Felder); Status, Privat, AddOn, Version usw. bleiben unter „Weitere Angaben“. Pflichtfeld „Zuweisen an“ wird vor dem Absenden geprüft, die Prüfung auf dem Server bleibt. Admins können in der Leiste direkt ein neues Tag anlegen.
- **Schnelles Tagging in der Liste**: Tag-Symbol in jeder Zeile (bei Zeigen, Fokus und in der ausgewählten Zeile) und in der Tabelle sowie Taste **T** öffnen ein kleines Fenster mit Suche und ankreuzbaren Tags; Änderungen gelten sofort, die Tag-Pillen der Zeile und der Lesebereich werden aktualisiert. „Neues Tag anlegen“ nur für Benutzer, die Tags verwalten dürfen. Neue API-Funktion `issue_tracker_tags` (nur Backend, CSRF-Token, Rechte wie beim Bearbeiten des Themas).
- **Betroffene Seite**: Optional einen Artikel aus der Struktur über das REDAXO-Link-Widget (Linkmap) wählen oder eine Adresse eingeben (https://… oder Pfad ab „/“, andere Schemata werden abgelehnt). Im Lesebereich und in der Vollansicht als Chip und unter den Angaben mit „Seite ansehen“ (Frontend) und „Bearbeiten“ (nur mit Rechten auf Kategorie und Sprache). Auch im Backup-Export/-Import enthalten.
- Datenbank: neue Spalten `article_id`, `clang_id` (int, NULL) und `affected_url` (varchar 500, NULL) in `rex_issue_tracker_issues` (per `rex_sql_table::ensureColumn`, läuft beim Update automatisch).
- Neue Methoden in `Issue`: `getArticleId()`, `getClangId()`, `setAffectedArticle()`, `getAffectedUrl()`, `setAffectedUrl()`, `getAffectedArticle()`, `isValidAffectedUrl()`.
- **Sicherheit**: Alle Aktionen der Themenansicht (Kommentare, Status, Tags, Beobachter, Checklisten, Verknüpfungen) sind jetzt gegen CSRF geschützt; bisher hatten nur die Schnellbearbeitung von Priorität und Zuweisung einen Token.
- **Betroffene Seite in E-Mails und Exporten**: Neuer Platzhalter `{{affected_page}}` für die E-Mail-Vorlagen – eine komplette Zeile „Betroffene Seite: …“ mit Titel und absoluter Frontend-Adresse (bzw. der eingegebenen Adresse), für Empfänger mit Rechten auf Kategorie und Sprache zusätzlich „Im Backend bearbeiten“. Ohne betroffene Seite bleibt er leer (keine leere Beschriftung). Die Standardvorlagen enthalten ihn; beim Update werden unveränderte Standardvorlagen automatisch ergänzt, angepasste bleiben unangetastet. Auch die Text-Mails (Erwähnung, Antworten und erledigen) nennen die Seite. Im PDF-Export steht sie als Zeile unter dem Titel, im CSV-Export als eigene Spalte. In der Vorlagen-Seite als anklickbarer Platzhalter mit Vorschau.
- CSV-Export brach bei Themen mit leeren Feldern (z. B. ohne Kategorie) mit einem Fehler ab – behoben.
- Neue Methoden: `Issue::toAbsoluteUrl()`, `Issue::getAffectedPageSummary()`, `NotificationService::buildAffectedPageHtml()` / `buildAffectedPageText()`.
- **Weitere laden** ohne Neuladen der Seite: Die nächsten 50 Einträge werden angehängt; Auswahl, Lesebereich, Scrollposition und Tastaturbedienung bleiben erhalten, der Fokus springt auf den ersten neuen Eintrag, ↑/↓ blättert von dort weiter, die Adresse merkt sich den Umfang. Anzeige „50 von 120“ und Ansage für Screenreader. Ohne JavaScript bleibt die Auswahl beim Nachladen ebenfalls erhalten.
- **Sicherheit (CSRF)**: Gespeicherte Filter (speichern, als Standard festlegen – bisher ein GET-Link –, löschen) sowie alle übrigen Formulare und Aktions-Links des AddOns prüfen jetzt einen Token: Thema anlegen/bearbeiten, Anhang löschen (zusätzlich nur mit Bearbeitungsrecht für das Thema), Thema und Nachricht löschen, Nachricht und Rundschreiben senden, Projekt anlegen/bearbeiten, Mitglieder hinzufügen/entfernen, eigene Einstellungen, Einstellungen, Tags, E-Mail-Vorlagen (speichern, testen, zurücksetzen), Backup-Import und API-Token erzeugen (jetzt POST).
- **Bedienung**:
  - Warnung vor dem Verlassen mit ungespeicherten Eingaben (Abbrechen, andere Links, Fenster schließen) beim Anlegen/Bearbeiten von Themen und beim Schreiben von Nachrichten.
  - Kurze Einblendung nach dem Speichern („Thema #12 wurde angelegt.“, „Ihre Antwort wurde gespeichert.“), verschwindet nach einigen Sekunden, bleibt bei Maus oder Fokus stehen.
  - Betroffene Seite: ungültige Adresse wird schon beim Verlassen des Feldes gemeldet (gleiche Regel wie auf dem Server), das Absenden öffnet dann das Feld; neuer Knopf **Keine Seite** entfernt Artikel und Adresse.
  - Schnell-Tags: leerer Zustand mit Hinweis („Noch keine Tags vorhanden …“), ohne Tags und ohne Recht zum Anlegen kein leeres Suchfeld.
  - **Tastaturkürzel-Übersicht** über die Taste **?** oder das Zahnrad-Menü; Kürzel stehen auch in den Tooltips und als `aria-keyshortcuts`. ↑/↓ blättert vom fokussierten Eintrag aus weiter.
  - Menüs der Seitenleiste (z. B. … bei Projekten) öffnen sich bei wenig Platz nach oben statt unten abgeschnitten zu werden (z. B. 1280 × 720).
  - Leere Postfächer erklären, warum sie leer sind (Erwähnt, Beobachtet, Überfällig, Kürzlich erledigt, Mir zugewiesen, Von mir erstellt); im Wissen ohne Recht kein „Neu“-Knopf.
  - Einheitliche Begriffe: „Thema“ statt „Issue“ und „erledigt“ statt „geschlossen“ in der deutschen Oberfläche (z. B. „Antworten und erledigen“, „Mit verwandtem Thema verknüpfen“), Anrede überall „Sie“; Hinweis der Eigenschaften-Leiste klarer formuliert.
- API `issue_tracker_comment_action` (Pinnen/Lösung): nur noch per POST mit CSRF-Token (`rex_csrf_token::factory('issue_tracker_comment_action')`) und mit denselben Rechten wie in der Themenansicht.

## [2.0.0-beta5] – 2026-10-19

- **Spaltenbreite ziehen**: Die Trennlinien zwischen Postfächern, Liste und Lesebereich lassen sich ziehen (auch per Pfeiltasten), begrenzt auf sinnvolle Breiten; Doppelklick stellt die Standardbreite wieder her. Die Breite wird pro Benutzer gespeichert.
- Überschriften im Postfach übernehmen keine Projekt-Schrift mehr für h1–h6 (z. B. Serifenschrift aus eigenen Backend-Stilen).

## [2.0.0-beta4] – 2026-10-19

- **Größere Schrift**: Texte auf REDAXO-Niveau (14–15 px statt 12–13 px), Nebenangaben mindestens 13 px; der Listentitel bricht um statt abgeschnitten zu werden.
- **Schriftgröße einstellbar**: Im Zahnrad-Menü „Schriftgröße“ mit A− / 100 % / A+ (90–140 %). Die Wahl gilt pro Benutzer auf allen Seiten des Issue Trackers und auf jedem Gerät.

## [2.0.0-beta3] – 2026-10-17

- **Nachrichten weiterleiten** (Taste F): an eine oder mehrere Personen, Betreff „WG: …“, eigene Notiz oben, darunter das Original deutlich als Zitat (Von, An, Datum, Betreff, Text). Nur Absender oder Empfänger der Nachricht dürfen weiterleiten; Empfänger werden wie bei neuen Nachrichten benachrichtigt. Nachrichten haben keine Dateianhänge, daher wird nur der Text übernommen.
- **Als ungelesen markieren** (Taste U, schaltet zwischen gelesen und ungelesen um) und **Alle als gelesen markieren** im Posteingang. Zähler in Seitenleiste und Reiter passen sich an.
- Neue Methoden in `Message`: `forward()`, `markAsUnread()`, `markAllAsRead()`, `buildForwardText()`. Keine Datenbankänderung.

## [2.0.0-beta2] – 2026-10-17

- **Alles in einem Mail-Fenster („Postfach“):** Themen, Nachrichten, Projekte und Wissen sind Postfächer in der Seitenleiste; das Dashboard entfällt. Reiter nur noch Postfach, Einstellungen, Hilfe. Alte Adressen (auch Links aus E-Mails) leiten auf das passende Postfach weiter.
- **Oberfläche passt sich dem Bereich an:** „Neu“ legt an, was gerade passt (Thema, Thema im Projekt, Nachricht, Wissenseintrag); Werkzeugleiste, Listenzeilen, Suche, Sortierung, Kopfzeile und leere Postfächer je Bereich. Projekte mit Projektkarte (Fortschritt, Mitglieder, Bearbeiten).
- **Zuklappbare Bereiche** in Seitenleiste, Lesefenster und Projektkarte; Zustand und zuletzt geöffnetes Postfach werden je Benutzer gemerkt.
- Lupe im Suchfeld sitzt jetzt mittig.

## [2.0.0-beta1] – 2026-10-17

Neues Aussehen in der Designsprache von Apple: Themen und Nachrichten funktionieren jetzt wie Mail auf dem Mac, alle anderen Seiten wie die macOS-Systemeinstellungen. Formulare sind aufgeräumt und für Nicht-Techniker verständlich. Alle Funktionen, Rechte und Links bleiben erhalten.

### Themen und Nachrichten wie Mail
- Drei Bereiche: links Postfächer (Alle aktiven, Mir zugewiesen, Von mir erstellt, Beobachtet, Erwähnt, Status, Projekte, Tags, Kategorien, gespeicherte Filter), in der Mitte die Liste mit Vorschau, rechts das Lesefenster mit Werkzeugleiste und Gesprächsverlauf.
- Tastatur: ↑/↓ blättert, Enter öffnet die Vollansicht, R springt zur Antwort. Ohne JavaScript funktioniert alles weiterhin über normale Links.
- Priorität und Zuständigkeit direkt aus dem Lesefenster ändern (mit Verlauf und Benachrichtigung).
- Am Handy wie am iPhone: Liste, dann Detail mit „‹ Themen“.
- Die bisherige Tabelle bleibt als Ansicht „Tabelle“ umschaltbar; die Wahl wird je Benutzer gemerkt.
- Links aus Benachrichtigungs-Mails (`issues/view&issue_id=…`) funktionieren unverändert.
- Nachrichten im selben Aufbau (Eingang, Gesendet, Neue Nachricht, Rundschreiben); Antworten direkt im Lesefenster.

### Aufgeräumte Formulare
- Neues Thema, Bearbeiten, Antworten, Nachrichten, Projekte und Einstellungen: Beschriftungen in Klartext mit kurzem Hinweis, nur das Wichtige sichtbar, seltene Angaben unter „Weitere Angaben“ bzw. „Erweitert“ (klappt automatisch auf, wenn dort etwas gesetzt ist).
- Pflichtfelder sind als solche beschriftet; Fehler stehen direkt am Feld und oben zusammengefasst, Eingaben bleiben erhalten.
- Priorität und Status als farbige Schaltflächen statt Auswahllisten; Projektfarbe als Farbfelder.
- E-Mail-Vorlagen: Platzhalter als anklickbare Bausteine mit verständlichen Namen und Live-Vorschau.
- Riskante Aktionen (Löschen, Zurücksetzen, Backup einspielen) stehen getrennt und fragen in Klartext nach.

### Übrige Seiten
- Übersicht mit Kacheln und „Zuletzt aktiv“, Projekte mit Farbe, Fortschritt und Mitgliedern, Wissensdatenbank, Einstellungen und persönliche Einstellungen mit Schaltern.
- Dunkles Theme (Profil-Einstellung und Systemeinstellung), Kontrast mindestens 4,5:1 bei eigenen Elementen, ohne seitliches Scrollen am Handy.

### Technik
- Styles und Skripte werden nur noch auf den Seiten des Issue Trackers geladen; eigene Klassen mit Präfix `it-`, REDAXO-Stile werden nicht überschrieben.
- CSV- und PDF-Export berücksichtigen jetzt auch die Filter „Zuständig“ und „Projekt“ und die in der Sitzung gespeicherten Filter.
- Neue Filter-Parameter `filter_assigned` und `filter_project` (auch in gespeicherten Filtern).

### Bugfixes
- „Bei Erwähnungen benachrichtigen“ ließ sich nicht ausschalten.
- Beim Speichern der E-Mail-Vorlagen wurden Zeilenumbrüche als CR/LF gespeichert.
- Einige Werte wurden in Fragmenten doppelt escaped.
- Das Nachrichten-Badge verursachte auf Seiten außerhalb des Issue Trackers einen JavaScript-Fehler.

## [1.8.0] – 2026-08-31

### Neue Funktionen
- **Neuer Menüpunkt „Wissen“**: Themen mit Status „Info“ verschwinden nicht mehr zwischen offenen Tickets in der normalen Liste, sondern werden unter „Wissen“ gesammelt, durchsuchbar (Titel/Beschreibung) und nach Kategorie gruppiert als Karten dargestellt. Filter nach Kategorie/Tag, respektiert private Themen wie gewohnt. Klick auf einen Eintrag öffnet die normale, bereits bestehende Detailansicht (inkl. Kommentare, Anhänge, Lightbox).

### Usability & Workflow (Projektbereich)
- **Suche/Filter/Pagination in der Projekt-Themenliste**: Die Issue-Tabelle innerhalb einer Projektansicht ist jetzt nach Titel/Beschreibung durchsuchbar, nach Status filterbar und blättert bei mehr als 20 Themen seitenweise, statt alles unsortiert auf einer Seite zu zeigen.
- **Projektliste filterbar**: Die Projektübersicht lässt sich jetzt nach Status (Aktiv/Abgeschlossen/Archiviert) filtern und nach Name/Beschreibung durchsuchen.
- **Tote Tab-Leiste in der Projektansicht entfernt**: Ein Rest einer 2026 wieder entfernten Kanban-Board-Funktion (Tab mit fehlender Übersetzung, führte ins Leere) wurde aufgeräumt.

### Usability & Workflow
- **Anhänge einheitlich per Lightbox ansehen**: Bilder, Videos und jetzt auch PDFs lassen sich überall (Issue-Ansicht, Kommentare, Antworten und jetzt auch im Bearbeiten-Formular) direkt in einer Lightbox öffnen, ohne die Datei herunterzuladen. PDFs werden inline als Vorschau eingebettet.
- **Galerie-Navigation in der Lightbox**: Bei mehreren Anhängen am selben Issue/Kommentar kann jetzt mit Pfeil-Buttons oder den Tastatur-Pfeiltasten durchgeblättert werden, statt die Lightbox für jede Datei neu öffnen zu müssen.
- **Anhänge im Bearbeiten-Formular jetzt ebenfalls per Lightbox nutzbar**: Bisher gab es dort nur ein statisches Vorschaubild ohne Lightbox-Anbindung – jetzt identisch zur Detailansicht.
- **Formular „Issue erstellen/bearbeiten“ übersichtlicher gegliedert**: Statt einer langen Feldliste ist das Formular jetzt in klare Abschnitte unterteilt („Worum geht es?“, „Einordnung“, „Zuständigkeit“, „Anhänge“). Selten benötigte technische Felder (Domains, YForm-Tabellen) sind in einem eigenen, einklappbaren „Erweiterte Optionen“-Bereich zusammengefasst.
- **Status beim Neuanlegen vereinheitlicht**: Neue Issues starten für normale Melder immer mit Status „Offen“ (Feld ist beim Erstellen ausgeblendet); Admins und Issue-Manager können weiterhin auch beim Anlegen direkt einen abweichenden Status setzen, z. B. um bereits erledigte Punkte zu dokumentieren. Serverseitig abgesichert, nicht nur im Formular ausgeblendet.
- **Feld „Version“ in die Erweiterten Optionen verschoben**: Betrifft meist die aktuelle Installation und ist den wenigsten Meldern bekannt – steht jetzt nicht mehr prominent neben Kategorie/Priorität, sondern im einklappbaren Bereich (klappt automatisch auf, wenn bereits ein Wert gesetzt ist).

### Bugfixes
- **Kaputtes Markup bei Video-Anhängen in hervorgehobenen Kommentaren behoben**: Ein doppelt eingefügter, nicht geschlossener HTML-Block sorgte dort für fehlerhaftes Rendering.

### Intern
- Anhänge-Vorschaukarten (Bild/Video/PDF/Icon + Lightbox-Link + Download/Löschen) sind jetzt über `Attachment::renderCard()` zentralisiert statt an fünf Stellen dupliziert – Grundlage für den behobenen Markup-Bug.

## [1.7.1] – 2026-08-08

### Bugfixes
- **Leere Einträge bei „Zugewiesener User“ behoben**: In der Create/Edit-Maske wurden Benutzer bisher ausschließlich über das Feld `name` angezeigt. Leere oder whitespace-only Namen führten zu unsichtbaren Optionen im Select-Dropdown. Die Anzeige verwendet jetzt eine robuste Fallback-Kette (`name` → `login` → `User #ID`) und trimmt Eingabewerte vor der Ausgabe.
- **@mention-Namensanzeige stabilisiert**: Dieselbe Fallback-Logik wird nun auch für die übergebenen Mention-Userdaten verwendet, damit Erwähnungslisten keine leeren Namen enthalten.
- **Benutzerliste bei Zuweisung auf Berechtigte eingeschränkt**: In der Maske `issue_tracker/issues/create` werden im Feld „Zugewiesener User“ jetzt nur noch aktive Benutzer mit Issue-Tracker-Zugriff angezeigt (Admin, `issue_tracker[]`, `issue_tracker[issuer]`, `issue_tracker[issue_manager]`).
- **Neue Option „Alle (gemeinsame Zuständigkeit)“**: Issues können nun ohne feste Einzelzuweisung mit gemeinsamer Zuständigkeit gespeichert werden. Die Option ist im Assignee-Select verfügbar und wird in Liste/Detailansicht entsprechend angezeigt.
- **Benachrichtigungen bei gemeinsamer Zuständigkeit**: Bei „Alle (gemeinsame Zuständigkeit)“ werden beim Erstellen eines neuen Issues alle berechtigten Issue-Tracker-Benutzer benachrichtigt, sofern deren Einstellung „Neue Issues per E-Mail“ aktiv ist. Der Ersteller selbst wird dabei weiterhin nicht benachrichtigt.

## [1.7.0] – 2026-03-29

### Neue Funktionen

## Neue Funktionen

- **@mention-System**: User können in Kommentaren per `@login` erwähnt werden
  - Autocomplete-Dropdown direkt im CodeMirror/EasyMDE-Editor (erscheint nach `@`-Eingabe, Navigation mit Pfeiltasten)
  - Erwähnungen werden in der neuen Tabelle `issue_tracker_mentions` gespeichert
  - Erwähnte User erhalten eine E-Mail-Benachrichtigung (individual abschaltbar über Präferenzen)
  - Neue Option in den Benachrichtigungs-Präferenzen: „Bei Erwähnungen"
- **Issue per Kommentar schließen** (GitHub-Stil): Checkbox „Schließen & kommentieren" im Kommentarformular
  - Schließt das Issue und speichert den Kommentar in einem Schritt
  - Sendet eine kombinierte E-Mail statt zwei separater Benachrichtigungen
  - Kommentar wird nur angezeigt, wenn das Issue noch nicht geschlossen/abgelehnt ist
- **Tags direkt in der Issue-Ansicht bearbeiten**: Tags können in der Sidebar der Detailansicht ohne den Edit-Modus verwaltet werden
  - Bestehende Tags zeigen ein ×-Symbol zum Entfernen (nur für berechtigte User)
  - Kleines Dropdown zum Hinzufügen weiterer Tags (zeigt nur noch nicht vergebene Tags)
- **Standard-Tags bei Installation/Update**: Die Tags Bug, Feature, Docs und Good Idea werden beim Installieren und Updaten automatisch angelegt (falls noch nicht vorhanden)
  
- **Interaktive Checklisten**: Aufgabenlisten in Beschreibungen und Kommentaren können per Klick direkt abgehakt werden
  - Markdown-Syntax: `- [ ] Offene Aufgabe` und `- [x] Erledigte Aufgabe`
  - Fortschrittsbalken mit Zähler (`x/y Aufgaben erledigt`) in der Detailansicht
  - Kompakter Fortschrittsbalken (4px) in der Issue-Liste wenn Checklisten vorhanden sind
  - Klick auf Checkbox speichert sofort via Server-Request, kein Seitenneulade
- **Zeiterfassung**: Zeit kann über den `/spent`-Befehl in Kommentaren erfasst werden
  - Unterstützte Formate: `2h`, `30m`, `1h 30m`, `90min`
  - `/spent` wird im angezeigten Kommentar ausgeblendet
  - Gesamtzeit aller Einträge im Details-Panel der Detailansicht sichtbar
  - Zeiteinträge werden im Aktivitätsprotokoll festgehalten
  - Neue Datenbank-Tabelle `rex_issue_tracker_time_entries` (id, issue_id, comment_id, user_id, minutes, note, created_at)
- **#Issue-Referenzen**: Verweise auf andere Issues in Kommentaren werden automatisch verlinkt
  - `#42` wird zu einem klickbaren Backend-Link auf Issue #42 – Syntax: Leerzeichen oder Satzzeichen vor/nach `#`, nicht direkt an Wortzeichen (`foo#42` wird nicht ersetzt)
  - Das referenzierte Issue erhält einen Cross-Reference-Eintrag im Aktivitätsverlauf
  - Referenzen innerhalb von Code-Blöcken werden nicht ersetzt

### Bugfixes
- **Doppeltes HTML-Encoding in Links**: `rex_url::backendPage()` liefert bereits HTML-kodierte URLs (`&amp;`) – wird jetzt mit `false` als drittem Parameter aufgerufen um eine rohe URL (`&`) zu erhalten, die dann korrekt einmalig durch `rex_escape()` im HTML-Attribut kodiert wird. Betrifft Checklisten-Formular-Action und `#Issue-Referenz`-Links.
- **#Issue-Referenz-Regex zu weit**: Regex `/#(\d+)/` durch `/(?<!\w)#(\d+)(?!\w)/` ersetzt um Treffer wie `foo#42` oder `#42px` zu vermeiden.

### Neue Klasse
- `FriendsOfREDAXO\IssueTracker\ContentRenderer`: Zentrale Render-Klasse für Beschreibungen und Kommentare
  - `render()`: Markdown-Rendering mit Checklisten, #Issue-Links und `/spent`-Filterung
  - `getChecklistProgress()`: Zählt offene/erledigte Checklisten-Items im Rohtext
  - `toggleChecklistItem()`: Togglet ein einzelnes Checklisten-Item per Index
  - `extractSpentMinutes()` / `stripSpentCommand()`: Parst und entfernt `/spent`-Befehle
  - `formatMinutes()`: Formatiert Minuten als lesbare Zeitangabe (`1h 30m`, `45m`)

### Neue Sprachschlüssel
- `issue_tracker_checklist_items` – „Aufgaben erledigt" / „tasks done"
- `issue_tracker_time_spent` – „Aufgewendete Zeit" / „Time spent"
- `issue_tracker_time_logged` – „Zeit erfasst" / „Time logged"
- `issue_tracker_referenced_in` – „In Issue erwähnt" / „Referenced in issue"

### Neue Datenbank-Tabelle
- `rex_issue_tracker_time_entries`: Speichert Zeiteinträge mit `issue_id`, `comment_id` (nullable), `user_id`, `minutes`, `note`, `created_at`

## [1.6.0] – 2026-03-28

### Neue Funktionen
- **@mention-System**: User können in Kommentaren per `@login` erwähnt werden
  - Autocomplete-Dropdown direkt im CodeMirror/EasyMDE-Editor (erscheint nach `@`-Eingabe, Navigation mit Pfeiltasten)
  - Erwähnungen werden in der neuen Tabelle `issue_tracker_mentions` gespeichert
  - Erwähnte User erhalten eine E-Mail-Benachrichtigung (individual abschaltbar über Präferenzen)
  - Neue Option in den Benachrichtigungs-Präferenzen: „Bei Erwähnungen"
- **Issue per Kommentar schließen** (GitHub-Stil): Checkbox „Schließen & kommentieren" im Kommentarformular
  - Schließt das Issue und speichert den Kommentar in einem Schritt
  - Sendet eine kombinierte E-Mail statt zwei separater Benachrichtigungen
  - Kommentar wird nur angezeigt, wenn das Issue noch nicht geschlossen/abgelehnt ist
- **Tags direkt in der Issue-Ansicht bearbeiten**: Tags können in der Sidebar der Detailansicht ohne den Edit-Modus verwaltet werden
  - Bestehende Tags zeigen ein ×-Symbol zum Entfernen (nur für berechtigte User)
  - Kleines Dropdown zum Hinzufügen weiterer Tags (zeigt nur noch nicht vergebene Tags)
- **Standard-Tags bei Installation/Update**: Die Tags Bug, Feature, Docs und Good Idea werden beim Installieren und Updaten automatisch angelegt (falls noch nicht vorhanden)

### Verbesserungen
- **Einheitliche Status-Label-Farben**: Liste und Detailansicht verwenden jetzt durchgehend dieselben Farben
  - `open → rot (danger)`, `in_progress → orange (warning)`, `planned → blau (info)`, `closed → grün (success)`, `rejected → grau (default)`, `info → dunkelblau (primary)`
- **Prioritäts-Label übersetzt**: Priorität wird jetzt als übersetztes Label angezeigt statt als roher DB-Schlüssel
- **Kombinierte Benachrichtigung**: `NotificationService::notifyCommentWithClose()` sendet eine E-Mail an alle Beteiligten wenn ein Issue via Kommentar geschlossen wird
- **Effizientere User-Abfrage**: Alle berechtigten User werden einmalig geladen und für Watcher-Panel wie @mention-Autocomplete genutzt

### Neue Datenbank-Tabelle
- `rex_issue_tracker_mentions`: Speichert @mention-Einträge mit `issue_id`, `comment_id`, `mentioned_user_id`, `created_by`, `created_at`

### Neue Datenbankspalte
- `rex_issue_tracker_notifications.email_on_mention` (tinyint, Standard: 1): Steuert @mention-E-Mail-Benachrichtigung pro User

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
