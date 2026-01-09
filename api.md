# Issue Tracker API Dokumentation

Diese Dokumentation beschreibt die REST-API des Issue Trackers für die externe Integration, z.B. in Monitoring-Dashboards oder PWAs.

## Authentifizierung

Alle API-Anfragen müssen mit einem API-Token authentifiziert werden. Der Token kann auf zwei Arten übermittelt werden:

### 1. Authorization Header (empfohlen)

```
Authorization: Bearer YOUR_API_TOKEN
```

### 2. Query Parameter

```
?api_token=YOUR_API_TOKEN
```

## Token generieren

1. Navigiere zu **Issue Tracker** → **Einstellungen** → **Allgemein**
2. Scrolle zum Abschnitt **API-Einstellungen**
3. Klicke auf **Token generieren**
4. Kopiere und speichere den Token sicher

> ⚠️ **Wichtig**: Der Token wird nur einmal angezeigt. Bei Neugenerierung wird der alte Token ungültig.

---

## Endpoints

### GET /index.php?rex-api-call=issue_tracker_stats

Liefert Statistiken über Issues und Nachrichten der Installation.

#### Request

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "https://example.com/redaxo/index.php?rex-api-call=issue_tracker_stats"
```

#### Response (200 OK)

```json
{
    "success": true,
    "timestamp": "2026-01-09T14:30:00+01:00",
    "installation": {
        "name": "Kunde XY Website",
        "url": "https://example.com/",
        "version": "1.0.0-beta1"
    },
    "stats": {
        "issues": {
            "total": 42,
            "open": 15,
            "by_status": {
                "open": 8,
                "in_progress": 5,
                "planned": 2,
                "rejected": 3,
                "closed": 24
            },
            "overdue": 2,
            "created_today": 1
        },
        "messages": {
            "unread": 3
        },
        "recent_issues": [
            {
                "id": 42,
                "title": "Kontaktformular funktioniert nicht",
                "status": "open",
                "priority": "high",
                "created_at": "2026-01-09 10:15:00"
            },
            {
                "id": 41,
                "title": "Newsletter-Anmeldung überprüfen",
                "status": "in_progress",
                "priority": "normal",
                "created_at": "2026-01-08 16:30:00"
            }
        ]
    }
}
```

#### Fehler-Responses

**401 Unauthorized** - Token fehlt
```json
{
    "success": false,
    "error": "API-Token fehlt. Sende Token als Authorization-Header oder api_token Parameter."
}
```

**403 Forbidden** - Token ungültig
```json
{
    "success": false,
    "error": "Ungültiger API-Token."
}
```

---

## Response-Felder

### installation

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `name` | string | Konfigurierbarer Name der Installation |
| `url` | string | Server-URL der REDAXO-Installation |
| `version` | string | Version des Issue Tracker AddOns |

### stats.issues

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `total` | integer | Gesamtanzahl aller Issues |
| `open` | integer | Anzahl nicht geschlossener Issues |
| `by_status` | object | Anzahl Issues pro Status |
| `overdue` | integer | Überfällige Issues (Deadline überschritten) |
| `created_today` | integer | Heute erstellte Issues |

### stats.messages

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `unread` | integer | Gesamtanzahl ungelesener Nachrichten |

### recent_issues

Array der 5 neuesten Issues mit:

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | integer | Issue-ID |
| `title` | string | Titel des Issues |
| `status` | string | Aktueller Status |
| `priority` | string | Priorität (low, normal, high, urgent) |
| `created_at` | string | Erstellungsdatum (Y-m-d H:i:s) |

---

## CORS

Die API unterstützt CORS und erlaubt Anfragen von allen Origins:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, OPTIONS
Access-Control-Allow-Headers: Authorization, Content-Type
```

---

## Beispiele

### JavaScript (Fetch API)

```javascript
async function getIssueTrackerStats(url, token) {
    const response = await fetch(`${url}/index.php?rex-api-call=issue_tracker_stats`, {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    
    if (!response.ok) {
        throw new Error('API request failed');
    }
    
    return response.json();
}

// Verwendung
const stats = await getIssueTrackerStats('https://example.com/redaxo', 'your-token');
console.log(`Offene Issues: ${stats.stats.issues.open}`);
```

### PHP

```php
$url = 'https://example.com/redaxo/index.php?rex-api-call=issue_tracker_stats';
$token = 'your-api-token';

$context = stream_context_create([
    'http' => [
        'header' => "Authorization: Bearer $token\r\n"
    ]
]);

$response = file_get_contents($url, false, $context);
$data = json_decode($response, true);

echo "Offene Issues: " . $data['stats']['issues']['open'];
```

### Python

```python
import requests

url = 'https://example.com/redaxo/index.php'
token = 'your-api-token'

response = requests.get(
    url,
    params={'rex-api-call': 'issue_tracker_stats'},
    headers={'Authorization': f'Bearer {token}'}
)

data = response.json()
print(f"Offene Issues: {data['stats']['issues']['open']}")
```

---

## Rate Limiting

Aktuell gibt es kein Rate Limiting. Für Polling-Anwendungen empfehlen wir ein Intervall von mindestens 30 Sekunden.

---

## Sicherheit

- Tokens werden mit 256-Bit Zufallsdaten generiert
- Tokens werden als SHA-256 Hash gespeichert (nicht im Klartext)
- Verwende HTTPS für alle API-Anfragen
- Tokens können jederzeit neu generiert werden (alter Token wird ungültig)
- Teile Tokens niemals öffentlich

---

## Changelog

### v1.0.0
- Initial API release
- Stats Endpoint für Issues und Nachrichten
