# Media Manager Integration

## Automatische Installation von Media Manager Typen

Der Issue Tracker erstellt bei der Installation automatisch zwei Media Manager Typen, wenn der Media Manager verfügbar ist:

### 1. `issue_tracker_attachment`
**Beschreibung:** Issue Tracker Attachments - Original

Dieser Typ lädt die Original-Dateien aus dem Issue Tracker Attachment-Verzeichnis.

**Effekte:**
- `issue_attachment` - Lädt Dateien aus dem Issue Tracker Datenverzeichnis

**Verwendung:**
```php
$url = rex_media_manager::getUrl('issue_tracker_attachment', $filename);
```

### 2. `issue_tracker_thumbnail`
**Beschreibung:** Issue Tracker Attachments - Thumbnail 200x200

Dieser Typ erstellt Thumbnails von Bild-Attachments.

**Effekte:**
1. `issue_attachment` - Lädt Dateien aus dem Issue Tracker Datenverzeichnis
2. `resize` - Skaliert Bilder auf maximal 200×200px (ohne Vergrößerung)

**Verwendung:**
```php
$url = rex_media_manager::getUrl('issue_tracker_thumbnail', $filename);
```

## Update-Verhalten

Die `update.php` prüft bei jedem AddOn-Update, ob die Media Manager Typen vorhanden sind und installiert sie nach, falls sie fehlen oder gelöscht wurden.

## Deinstallation

Bei der Deinstallation des Issue Tracker werden die Media Manager Typen automatisch entfernt:
- Cache der Typen wird geleert
- Effekte werden gelöscht
- Typen werden aus der Datenbank entfernt

## Voraussetzungen

- Der Media Manager muss installiert und verfügbar sein
- Der Custom Effect `rex_effect_issue_attachment` muss registriert sein (siehe `lib/effect_issue_attachment.php`)

## Technische Details

### Registrierung des Custom Effects

Der Custom Effect `rex_effect_issue_attachment` wird in der `boot.php` registriert:

```php
if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::addEffect('rex_effect_issue_attachment');
}
```

### Effect-Klasse

Die Klasse `rex_effect_issue_attachment` befindet sich in `lib/effect_issue_attachment.php` und lädt Dateien aus dem Issue Tracker Datenverzeichnis:

```php
$filepath = rex_path::addonData('issue_tracker', 'attachments/' . $filename);
```

### Sicherheit

- Dateien werden nicht aus dem öffentlichen Verzeichnis geladen, sondern aus `rex_path::addonData()`
- Die Ausgabe erfolgt über den Media Manager, der Caching und sichere Dateiauslieferung übernimmt
- Berechtigungen müssen in der Anwendung geprüft werden (nicht durch den Media Manager)

## Fehlerbehebung

### Media Manager Typen fehlen

Wenn die Media Manager Typen fehlen, können sie durch Re-Installation des AddOns oder durch Ausführen der `update.php` wiederhergestellt werden.

### Cache-Probleme

Bei Problemen mit der Darstellung der Attachments kann der Media Manager Cache geleert werden:
```php
rex_media_manager::deleteCacheByType('issue_tracker_attachment');
rex_media_manager::deleteCacheByType('issue_tracker_thumbnail');
```

Oder manuell im Media Manager Backend unter "Typen" → "Cache löschen".
