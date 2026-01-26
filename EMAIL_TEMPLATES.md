# E-Mail Templates für Issue Tracker

## Übersicht

Der Issue Tracker verwendet moderne HTML-E-Mail-Templates mit einem ansprechenden Design für alle E-Mail-Benachrichtigungen.

## Features

### 1. HTML-Design
- **Modernes, responsives Design** mit Gradient-Header
- **Strukturierte Informationsboxen** für bessere Lesbarkeit
- **Call-to-Action Buttons** für direkte Links zu Issues
- **Farbige Badges** für Status, Priorität und Kategorie
- **Automatische Generierung** von Plain-Text-Alternativen

### 2. Template-Typen

Es gibt 4 verschiedene E-Mail-Typen, jeweils in Deutsch und Englisch:

1. **Neues Issue** (`new_issue`)
   - Wird versendet, wenn ein neues Issue erstellt wird
   - Enthält: Titel, Kategorie, Priorität, Beschreibung, Ersteller

2. **Neuer Kommentar** (`new_comment`)
   - Wird versendet, wenn ein Kommentar hinzugefügt wird
   - Enthält: Issue-Titel, Kommentar-Text, Kommentator

3. **Status-Änderung** (`status_change`)
   - Wird versendet, wenn der Status geändert wird
   - Enthält: Issue-Titel, alter Status, neuer Status

4. **Zuweisung** (`assignment`)
   - Wird versendet, wenn ein Issue zugewiesen wird
   - Enthält: Issue-Details, Beschreibung

### 3. Template-Verwaltung

#### Bearbeiten von Templates
- Unter **Einstellungen → E-Mail-Templates** können alle Templates angepasst werden
- Tabs für Deutsch und Englisch
- Syntax-Highlighting für HTML (über Textarea)

#### Zurücksetzen auf Standard
- Über den **"Auf Standard zurücksetzen"**-Button können alle Templates auf die Standardwerte zurückgesetzt werden
- Bestätigungsdialog schützt vor versehentlichem Zurücksetzen
- Setzt **alle 8 Templates** (4 Typen × 2 Sprachen) zurück

## Platzhalter

Folgende Platzhalter stehen in den Templates zur Verfügung:

- `{{recipient_name}}` - Name des Empfängers
- `{{issue_id}}` - ID des Issues
- `{{issue_title}}` - Titel des Issues
- `{{issue_category}}` - Kategorie des Issues
- `{{issue_priority}}` - Priorität des Issues
- `{{issue_description}}` - Beschreibung des Issues
- `{{creator_name}}` - Name des Erstellers/Autors
- `{{comment_text}}` - Text des Kommentars
- `{{old_status}}` - Alter Status (bei Status-Änderungen)
- `{{new_status}}` - Neuer Status (bei Status-Änderungen)
- `{{issue_url}}` - Einmaliger Deep-Link zum Issue (30 Tage gültig)

## Technische Details

### Klassen

#### EmailTemplateService
Service-Klasse für Template-Verwaltung:

```php
use FriendsOfREDAXO\IssueTracker\EmailTemplateService;

// HTML-Wrapper um Content generieren
$html = EmailTemplateService::getHtmlWrapper($content, $title);

// Standard-Templates abrufen
$templates = EmailTemplateService::getDefaultHtmlTemplates();

// Alle Templates zurücksetzen
$count = EmailTemplateService::resetToDefaults();

// Template laden
$template = EmailTemplateService::getTemplate('email_template_new_issue_de');

// Template speichern
EmailTemplateService::saveTemplate('email_template_new_issue_de', $htmlContent);
```

### HTML-Wrapper

Der `getHtmlWrapper()` erstellt eine vollständige HTML-E-Mail mit:
- Responsive Layout (max-width: 600px)
- Inline CSS für maximale E-Mail-Client Kompatibilität
- Gradient-Header mit REDAXO-Branding
- Strukturierte Content-Bereiche
- Footer mit Hinweistext

### Automatisches Wrapping

Der NotificationService erkennt automatisch, ob ein Template bereits HTML enthält:
- Wenn `<!DOCTYPE html>` fehlt → Wrapping mit `getHtmlWrapper()`
- Wenn bereits vollständiges HTML → Direkte Verwendung

Dies ermöglicht sowohl:
- HTML-Snippets (nur Body-Content)
- Vollständige HTML-Dokumente

## Design-Elemente

### CSS-Klassen im Template

```html
<!-- Info-Box mit Border -->
<div class="info-box">
    <strong>Label:</strong> Value
</div>

<!-- Button/Call-to-Action -->
<a href="URL" class="button">Text →</a>

<!-- Badges -->
<span class="badge badge-danger">Hoch</span>
<span class="badge badge-warning">Mittel</span>
<span class="badge badge-info">Kategorie</span>
<span class="badge badge-success">Status</span>
<span class="badge badge-secondary">Sonstiges</span>

<!-- Beschreibungs-Box (code-style) -->
<div class="description">{{issue_description}}</div>

<!-- Warnungs-Box -->
<div class="warning">Warnung!</div>
```

### Farben

- **Primary Gradient**: #667eea → #764ba2
- **Hintergrund**: #f4f4f4
- **Content**: #ffffff
- **Borders**: #e9ecef
- **Text**: #333333
- **Muted**: #6c757d

## Migration von Plain-Text

Bei einem Update werden automatisch:
1. Alte Plain-Text-Templates durch HTML-Templates ersetzt
2. Templates in der Datenbank (`rex_issue_tracker_settings`) aktualisiert
3. Platzhalter bleiben kompatibel

## Best Practices

### Template-Anpassungen

1. **Inline-CSS verwenden** - Viele E-Mail-Clients unterstützen keine `<style>` Tags
2. **Responsive Breakpoints** - max-width: 600px für Desktop, 100% für Mobile
3. **Tabellen für Layout** - Wenn nötig (für sehr alte Clients)
4. **Farbkontraste beachten** - Mindestens 4.5:1 für Lesbarkeit
5. **Alt-Text bereitstellen** - Wird automatisch generiert via `strip_tags()`

### Testen

E-Mail-Templates sollten getestet werden mit:
- Gmail (Web/Mobile)
- Outlook (Desktop/Web)
- Apple Mail
- Thunderbird

### Personalisierung

Templates können angepasst werden für:
- Corporate Design/CI
- Andere Farbschemata
- Zusätzliche Informationen
- Andere Sprachen (über neue Template-Keys)

## Troubleshooting

### Templates werden nicht angezeigt
- Prüfen ob `email_enabled` in Settings aktiviert ist
- Datenbank-Tabelle `rex_issue_tracker_settings` prüfen
- Fallback-Templates in `NotificationService::getDefaultTemplate()` überprüfen

### HTML wird nicht gerendert
- PHPMailer muss `isHTML(true)` gesetzt haben
- Server-Mailkonfiguration prüfen
- MIME-Type muss `text/html` sein

### Platzhalter werden nicht ersetzt
- Doppelte geschweifte Klammern verwenden: `{{key}}`
- Keine Leerzeichen in Platzhaltern
- Case-sensitive: `{{issue_title}}` nicht `{{Issue_Title}}`

### Reset funktioniert nicht
- Berechtigungen prüfen (nur Admins)
- Datenbank-Schreibrechte prüfen
- PHP-Fehlerlog konsultieren

## Sicherheit

- **Keine Benutzereingaben in Templates** - Nur vordefinierte Platzhalter
- **HTML-Escaping** bei Platzhalter-Ersetzung automatisch
- **Deep-Links** mit Einmal-Tokens (30 Tage Gültigkeit)
- **Keine sensiblen Daten** in E-Mail-Logs

## Support

Bei Fragen oder Problemen:
- GitHub Issues: [FriendsOfREDAXO/issue_tracker](https://github.com/FriendsOfREDAXO/issue_tracker)
- REDAXO Slack: #addons
- Forum: https://www.redaxo.org/forum/
