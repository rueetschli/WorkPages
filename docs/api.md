# WorkPages REST API v1

## Uebersicht

Die WorkPages API ermoeglicht externen Anwendungen den programmatischen Zugriff auf Tasks, Pages, Kommentare, Anhaenge, Board-Spalten und Reports. Die API ist versioniert, JSON-basiert und mit API-Keys authentifiziert.

**Base URL:** `{BASE_URL}/api/v1/`

**Content-Type:** Alle Antworten verwenden `application/json; charset=utf-8`.

---

## Authentifizierung

### API Keys

Jeder API-Zugriff erfordert einen API-Key, der als Bearer Token im Authorization-Header gesendet wird.

```
Authorization: Bearer wp_xxxxxxxx_yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy
```

API-Keys werden pro Benutzer erstellt und sind an dessen Rolle und Team-Sichtbarkeit gebunden.

### Key erstellen

1. Einloggen in WorkPages
2. Navigation: API-Schluessel (Seitenleiste)
3. "Neuer Schluessel" klicken
4. Name und Scopes waehlen
5. Schluessel kopieren (wird nur einmal angezeigt)

### Scopes

Jeder API-Key hat eine Menge von Scopes, die den Zugriff einschraenken:

| Scope | Beschreibung |
|-------|-------------|
| `tasks:read` | Tasks lesen |
| `tasks:write` | Tasks erstellen, bearbeiten, loeschen |
| `pages:read` | Pages lesen |
| `pages:write` | Pages erstellen, bearbeiten, loeschen |
| `comments:read` | Kommentare lesen |
| `comments:write` | Kommentare erstellen, loeschen |
| `attachments:read` | Anhaenge lesen und herunterladen |
| `attachments:write` | Anhaenge hochladen und loeschen |
| `webhooks:manage` | Webhooks verwalten |
| `reports:read` | Reports lesen |

**Viewer-Rolle:** Benutzer mit der Viewer-Rolle koennen keine Write-Scopes verwenden.

### Key widerrufen

Widerrufene Keys werden sofort ungueltig. Erstellen Sie einen neuen Key als Ersatz.

---

## Fehlerformat

Alle Fehler verwenden ein einheitliches JSON-Format:

```json
{
  "error": {
    "code": "validation_error",
    "message": "Titel ist erforderlich.",
    "details": {}
  }
}
```

### HTTP-Statuscodes

| Code | Bedeutung |
|------|-----------|
| 200 | OK |
| 201 | Created |
| 204 | No Content (DELETE erfolgreich) |
| 400 | Bad Request |
| 401 | Unauthorized (fehlender/ungueltiger API-Key) |
| 403 | Forbidden (fehlender Scope oder keine Berechtigung) |
| 404 | Not Found |
| 409 | Conflict (Idempotency-Key Kollision) |
| 422 | Unprocessable Entity (Validierungsfehler) |
| 429 | Too Many Requests (Rate Limit ueberschritten) |
| 500 | Internal Server Error |

---

## Pagination

Listen-Endpoints verwenden Cursor-basierte Pagination:

**Query Parameter:**
- `limit` - Anzahl Ergebnisse (1-100, Standard: 50)
- `cursor` - Cursor fuer die naechste Seite

**Response Format:**
```json
{
  "data": [...],
  "next_cursor": "123"
}
```

Wenn `next_cursor` null ist, gibt es keine weiteren Seiten. Den Wert von `next_cursor` als `cursor`-Parameter an den naechsten Request uebergeben.

**Beispiel:**
```bash
# Erste Seite
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/tasks?limit=10"

# Naechste Seite
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/tasks?limit=10&cursor=42"
```

---

## Rate Limiting

Jeder API-Key ist auf **300 Requests pro 5 Minuten** limitiert.

**Response Headers:**
```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 287
X-RateLimit-Window: 300
```

Bei Ueberschreitung:
```
HTTP/1.1 429 Too Many Requests
Retry-After: 180
```

---

## Idempotenz

POST-Endpoints unterstuetzen den `Idempotency-Key` Header. Wird derselbe Key innerhalb von 24 Stunden erneut gesendet, wird die gespeicherte Antwort zurueckgegeben.

```bash
curl -X POST \
  -H "Authorization: Bearer wp_..." \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: unique-request-id-123" \
  -d '{"title": "Neuer Task"}' \
  "{BASE_URL}/api/v1/tasks"
```

Unterstuetzt fuer: `POST /tasks`, `POST /pages`, `POST /comments`, `POST /attachments`.

---

## Endpoints

### Tasks

#### GET /api/v1/tasks

Tasks auflisten (gefiltert, paginiert).

**Scope:** `tasks:read`

**Filter (Query Parameter):**

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `team_id` | int | Nach Team filtern |
| `owner_id` | int | Nach Besitzer filtern |
| `tag` | string | Nach Tag-Name filtern |
| `column_id` | int | Nach Board-Spalte filtern |
| `due_before` | date | Faelligkeitsdatum vor (YYYY-MM-DD) |
| `due_after` | date | Faelligkeitsdatum nach (YYYY-MM-DD) |
| `updated_after` | datetime | Aktualisiert nach (YYYY-MM-DD HH:MM:SS) |
| `limit` | int | Ergebnisse pro Seite (1-100) |
| `cursor` | string | Cursor fuer Pagination |

**Beispiel:**
```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/tasks?team_id=1&limit=20"
```

**Response:**
```json
{
  "data": [
    {
      "id": 42,
      "title": "Feature implementieren",
      "description_md": "Beschreibung in Markdown...",
      "column_id": 3,
      "column_name": "In Arbeit",
      "owner_id": 7,
      "owner_name": "Max Muster",
      "due_date": "2026-03-01",
      "team_id": 1,
      "tags": ["feature", "frontend"],
      "started_at": "2026-02-15T10:00:00",
      "done_at": null,
      "created_by": 7,
      "created_at": "2026-02-10T08:30:00",
      "updated_at": "2026-02-15T14:22:00"
    }
  ],
  "next_cursor": "41"
}
```

#### POST /api/v1/tasks

Neuen Task erstellen.

**Scope:** `tasks:write`

**Request Body:**
```json
{
  "title": "Bug fixen",
  "description_md": "Der Login-Button reagiert nicht.",
  "team_id": 1,
  "owner_id": 7,
  "due_date": "2026-03-15",
  "column_id": 2,
  "tags": ["bug", "urgent"],
  "page_ids": [10, 15]
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|-------------|
| `title` | string | Ja | Titel (max. 255 Zeichen) |
| `description_md` | string | Nein | Beschreibung in Markdown |
| `team_id` | int | Nein | Team-Zuordnung |
| `owner_id` | int | Nein | Besitzer (User-ID) |
| `due_date` | string | Nein | Faelligkeitsdatum (YYYY-MM-DD) |
| `column_id` | int | Nein | Board-Spalte (Standard: Default-Spalte) |
| `tags` | string[] | Nein | Tag-Namen |
| `page_ids` | int[] | Nein | Zu verknuepfende Page-IDs |

**Response:** 201 Created
```json
{
  "id": 43,
  "title": "Bug fixen",
  "..."
}
```

#### GET /api/v1/tasks/{id}

Einzelnen Task abrufen.

**Scope:** `tasks:read`

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/tasks/42"
```

**Response:** 200 OK (wie bei Listen, zusaetzlich mit `linked_pages`)

#### PATCH /api/v1/tasks/{id}

Task aktualisieren. Nur mitgesendete Felder werden geaendert.

**Scope:** `tasks:write`

```bash
curl -X PATCH \
  -H "Authorization: Bearer wp_..." \
  -H "Content-Type: application/json" \
  -d '{"column_id": 4, "tags": ["done", "release-1.0"]}' \
  "{BASE_URL}/api/v1/tasks/42"
```

| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `title` | string | Neuer Titel |
| `description_md` | string | Neue Beschreibung |
| `owner_id` | int/null | Neuer Besitzer (null = entfernen) |
| `due_date` | string/null | Neues Datum (null = entfernen) |
| `column_id` | int | Neue Board-Spalte (loest Flow-Dates aus) |
| `team_id` | int/null | Neues Team |
| `tags` | string[] | Komplettes Tag-Set (ersetzt alle bestehenden) |
| `add_page_ids` | int[] | Zusaetzlich zu verknuepfende Pages |
| `remove_page_ids` | int[] | Verknuepfungen entfernen |

**Hinweis:** Aendern von `column_id` loest TaskFlowService aus (started_at/done_at).

#### DELETE /api/v1/tasks/{id}

Task loeschen (Hard Delete).

**Scope:** `tasks:write`

```bash
curl -X DELETE \
  -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/tasks/42"
```

**Response:** 204 No Content

---

### Pages

#### GET /api/v1/pages

Pages auflisten.

**Scope:** `pages:read`

**Filter:**

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `team_id` | int | Nach Team filtern |
| `parent_id` | int/null | Nach Eltern-Page filtern (null = Root-Pages) |
| `updated_after` | datetime | Aktualisiert nach |
| `limit` | int | Ergebnisse pro Seite |
| `cursor` | string | Cursor |

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/pages?parent_id=null&limit=20"
```

**Response:**
```json
{
  "data": [
    {
      "id": 10,
      "title": "Projektdokumentation",
      "slug": "projektdokumentation",
      "parent_id": null,
      "content_md": "# Projektuebersicht\n...",
      "team_id": 1,
      "created_by": 3,
      "created_at": "2026-01-15T09:00:00",
      "updated_at": "2026-02-10T14:30:00"
    }
  ],
  "next_cursor": null
}
```

#### POST /api/v1/pages

Neue Page erstellen.

**Scope:** `pages:write`

```json
{
  "title": "Neue Seite",
  "content_md": "# Inhalt\n\nText hier...",
  "parent_id": 10,
  "team_id": 1
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|-------------|
| `title` | string | Ja | Titel (max. 255 Zeichen) |
| `content_md` | string | Nein | Inhalt in Markdown |
| `parent_id` | int | Nein | Eltern-Page-ID |
| `team_id` | int | Nein | Team-Zuordnung |

**Response:** 201 Created

#### GET /api/v1/pages/{id}

Einzelne Page abrufen.

**Scope:** `pages:read`

#### PATCH /api/v1/pages/{id}

Page aktualisieren.

**Scope:** `pages:write`

#### DELETE /api/v1/pages/{id}

Page loeschen (Soft Delete).

**Scope:** `pages:write`

**Response:** 204 No Content

#### GET /api/v1/pages/{id}/tasks

Verknuepfte Tasks einer Page abrufen.

**Scope:** `pages:read`

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/pages/10/tasks"
```

---

### Comments

#### GET /api/v1/comments

Kommentare auflisten (nach Entity gefiltert).

**Scope:** `comments:read`

**Pflicht-Filter:**

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `entity_type` | string | `page` oder `task` |
| `entity_id` | int | ID der Entity |

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/comments?entity_type=task&entity_id=42"
```

**Response:**
```json
{
  "data": [
    {
      "id": 100,
      "entity_type": "task",
      "entity_id": 42,
      "body_md": "Das sieht gut aus!",
      "created_by": 7,
      "author_name": "Max Muster",
      "created_at": "2026-02-18T10:00:00"
    }
  ],
  "next_cursor": null
}
```

#### POST /api/v1/comments

Kommentar erstellen.

**Scope:** `comments:write`

```json
{
  "entity_type": "task",
  "entity_id": 42,
  "body_md": "Sieht gut aus, bitte mergen."
}
```

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|-------------|
| `entity_type` | string | Ja | `page` oder `task` |
| `entity_id` | int | Ja | ID der Entity |
| `body_md` | string | Ja | Kommentartext (max. 10000 Zeichen) |

**Response:** 201 Created

#### DELETE /api/v1/comments/{id}

Kommentar loeschen (Soft Delete). Nur der Autor oder Admin.

**Scope:** `comments:write`

---

### Attachments

#### GET /api/v1/attachments

Anhaenge auflisten (nach Entity gefiltert).

**Scope:** `attachments:read`

**Pflicht-Filter:**

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `entity_type` | string | `page` oder `task` |
| `entity_id` | int | ID der Entity |

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/attachments?entity_type=task&entity_id=42"
```

#### POST /api/v1/attachments

Datei hochladen (multipart/form-data).

**Scope:** `attachments:write`

```bash
curl -X POST \
  -H "Authorization: Bearer wp_..." \
  -F "entity_type=task" \
  -F "entity_id=42" \
  -F "file=@/pfad/zur/datei.pdf" \
  "{BASE_URL}/api/v1/attachments"
```

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|-------------|
| `entity_type` | string | Ja | `page` oder `task` |
| `entity_id` | int | Ja | ID der Entity |
| `file` | file | Ja | Die hochzuladende Datei |

**Response:** 201 Created
```json
{
  "id": 55,
  "entity_type": "task",
  "entity_id": 42,
  "original_name": "screenshot.png",
  "mime_type": "image/png",
  "file_size": 245760,
  "uploaded_by": 7,
  "uploader_name": "Max Muster",
  "created_at": "2026-02-18T10:30:00"
}
```

#### GET /api/v1/attachments/{id}/download

Datei herunterladen.

**Scope:** `attachments:read`

```bash
curl -H "Authorization: Bearer wp_..." \
  -o datei.pdf \
  "{BASE_URL}/api/v1/attachments/55/download"
```

#### DELETE /api/v1/attachments/{id}

Anhang loeschen (Soft Delete).

**Scope:** `attachments:write`

---

### Board Columns

Read-only Endpoints fuer Board-Spalten.

#### GET /api/v1/board_columns

Alle Spalten auflisten.

**Scope:** `tasks:read`

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/board_columns"
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Backlog",
      "slug": "backlog",
      "color": "#6c757d",
      "category": "backlog",
      "position": 1000,
      "wip_limit": null,
      "is_default": true
    },
    {
      "id": 2,
      "name": "In Arbeit",
      "slug": "in-arbeit",
      "color": "#007bff",
      "category": "active",
      "position": 2000,
      "wip_limit": 5,
      "is_default": false
    }
  ]
}
```

#### GET /api/v1/board_columns/{id}

Einzelne Spalte abrufen.

**Scope:** `tasks:read`

---

### Reports

Read-only Endpoints fuer Metriken.

#### GET /api/v1/reports/flow

Flow-Metriken (Throughput, Cycle Time).

**Scope:** `reports:read`

**Filter (Query Parameter):**

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `team_id` | int | Team-Filter |
| `from` | date | Startdatum (YYYY-MM-DD) |
| `to` | date | Enddatum (YYYY-MM-DD) |
| `preset` | string | Zeitraum-Preset: `7d`, `30d`, `90d`, `quarter` |
| `owner_id` | int | Besitzer-Filter |
| `tag` | string | Tag-Filter |

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/reports/flow?preset=30d&team_id=1"
```

**Response:**
```json
{
  "data": {
    "period": {
      "from": "2026-01-19",
      "to": "2026-02-18"
    },
    "throughput": 24,
    "avg_cycle_time": 3.2,
    "cycle_time_summary": {
      "count": 24,
      "average": 3.2,
      "p50": 2.5,
      "p85": 5.1,
      "p95": 7.8
    },
    "throughput_per_week": [
      {"week_start": "2026-01-20", "throughput": 6},
      {"week_start": "2026-01-27", "throughput": 8}
    ]
  }
}
```

#### GET /api/v1/reports/aging

Aging-Metriken (offene Tasks, Ueberalterung).

**Scope:** `reports:read`

**Response:**
```json
{
  "data": {
    "wip_count": 15,
    "overdue_count": 3,
    "aging_buckets": [
      {"label": "0 - 2 Tage", "count": 5},
      {"label": "3 - 7 Tage", "count": 4},
      {"label": "8 - 14 Tage", "count": 3},
      {"label": "15 - 30 Tage", "count": 2},
      {"label": "31 - 60 Tage", "count": 1},
      {"label": "60+ Tage", "count": 0}
    ],
    "overdue_buckets": [
      {"label": "1 - 3 Tage", "count": 1},
      {"label": "4 - 7 Tage", "count": 1},
      {"label": "8 - 14 Tage", "count": 1},
      {"label": "15+ Tage", "count": 0}
    ],
    "top_aged_tasks": [
      {
        "id": 12,
        "title": "Alter Task",
        "column_name": "Backlog",
        "age_days": 45,
        "owner_name": "Max Muster"
      }
    ]
  }
}
```

---

## Webhooks

### Uebersicht

Webhooks senden automatisch HTTP-POST-Requests an konfigurierte URLs, wenn Ereignisse in WorkPages auftreten. Webhooks werden asynchron ueber eine Queue zugestellt und blockieren keine UI-Operationen.

### Events

| Event | Beschreibung |
|-------|-------------|
| `task.created` | Task wurde erstellt |
| `task.updated` | Task wurde aktualisiert |
| `task.assigned` | Task wurde zugewiesen |
| `task.moved` | Task wurde in andere Spalte verschoben |
| `task.done` | Task wurde als erledigt markiert |
| `task.deleted` | Task wurde geloescht |
| `comment.created` | Kommentar wurde erstellt |
| `attachment.added` | Anhang wurde hochgeladen |
| `page.created` | Page wurde erstellt |
| `page.updated` | Page wurde aktualisiert |
| `page.deleted` | Page wurde geloescht |

### Payload Format

```json
{
  "event": "task.moved",
  "delivery_id": 123,
  "occurred_at": "2026-02-18T10:22:00Z",
  "actor": {
    "id": 7,
    "name": "Max Muster"
  },
  "team_id": 3,
  "entity": {
    "type": "task",
    "id": 991,
    "url": "https://example.com/?r=task_view&id=991"
  },
  "data": {
    "old_column_id": 2,
    "new_column_id": 3,
    "new_column_name": "Review"
  }
}
```

### HTTP Headers

Jede Webhook-Zustellung sendet folgende Headers:

| Header | Beschreibung |
|--------|-------------|
| `Content-Type` | `application/json` |
| `X-WorkPages-Event` | Event-Name (z.B. `task.moved`) |
| `X-WorkPages-Delivery` | Eindeutige Delivery-ID |
| `X-WorkPages-Signature` | HMAC-SHA256 Signatur des Payloads |
| `User-Agent` | `WorkPages-Webhook/1.0` |

### Signatur-Verifikation

Die Signatur wird als HMAC-SHA256 des JSON-Payloads mit dem Webhook-Secret berechnet:

```
signature = HMAC-SHA256(payload_body, webhook_secret)
```

**Beispiel-Verifikation (PHP):**
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WORKPAGES_SIGNATURE'] ?? '';
$expected = hash_hmac('sha256', $payload, $webhookSecret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

**Beispiel-Verifikation (Python):**
```python
import hmac
import hashlib

payload = request.body
signature = request.headers.get('X-WorkPages-Signature', '')
expected = hmac.new(
    webhook_secret.encode(),
    payload,
    hashlib.sha256
).hexdigest()

if not hmac.compare_digest(expected, signature):
    abort(401)
```

### Retry Policy

Fehlgeschlagene Zustellungen werden mit exponentiellem Backoff wiederholt:

| Versuch | Wartezeit |
|---------|-----------|
| 1 | 1 Minute |
| 2 | 5 Minuten |
| 3 | 30 Minuten |
| 4 | 2 Stunden |
| 5 | 12 Stunden |
| 6-10 | 12 Stunden |

Nach 10 fehlgeschlagenen Versuchen wird der Eintrag als "Dead Letter" markiert. Admins koennen Dead-Letter-Eintraege manuell erneut in die Queue stellen.

### Timeouts

- Connect Timeout: 5 Sekunden
- Total Timeout: 10 Sekunden

Empfaenger sollten innerhalb von 10 Sekunden mit einem 2xx-Statuscode antworten.

### Webhook-Konfiguration

Webhooks werden im Admin-Bereich unter "Webhooks" konfiguriert:
- Name und Ziel-URL definieren
- Events auswaehlen
- Optional auf ein Team einschraenken
- Secret wird automatisch generiert

---

## Team-Sichtbarkeit

Die API erzwingt dieselben Team-Sichtbarkeitsregeln wie die Web-Oberflaeche:

- **Admin:** Sieht alle Entities
- **Member:** Sieht Entities ohne Team-Zuordnung und Entities aus eigenen Teams
- **Viewer:** Gleiche Sichtbarkeit wie Member, aber kein Schreibzugriff

---

## CORS

CORS ist standardmaessig deaktiviert. Zur Aktivierung in der Konfiguration:

```php
'API_CORS_ORIGINS' => ['https://meine-app.example.com'],
```

Oder fuer alle Origins (nicht empfohlen fuer Produktion):

```php
'API_CORS_ORIGINS' => '*',
```

---

## Beispiele

### Task erstellen und in Spalte verschieben

```bash
# Task erstellen
TASK=$(curl -s -X POST \
  -H "Authorization: Bearer wp_..." \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: create-task-001" \
  -d '{"title": "API-Test", "column_id": 1}' \
  "{BASE_URL}/api/v1/tasks")

TASK_ID=$(echo $TASK | jq -r '.id')

# In "In Arbeit" verschieben
curl -X PATCH \
  -H "Authorization: Bearer wp_..." \
  -H "Content-Type: application/json" \
  -d '{"column_id": 2}' \
  "{BASE_URL}/api/v1/tasks/$TASK_ID"
```

### Alle offenen Tasks eines Teams abrufen

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/tasks?team_id=1&limit=100"
```

### Kommentar zu einem Task hinzufuegen

```bash
curl -X POST \
  -H "Authorization: Bearer wp_..." \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "task", "entity_id": 42, "body_md": "Erledigt!"}' \
  "{BASE_URL}/api/v1/comments"
```

### Datei an einen Task anhaengen

```bash
curl -X POST \
  -H "Authorization: Bearer wp_..." \
  -F "entity_type=task" \
  -F "entity_id=42" \
  -F "file=@screenshot.png" \
  "{BASE_URL}/api/v1/attachments"
```

### Flow-Report abrufen

```bash
curl -H "Authorization: Bearer wp_..." \
  "{BASE_URL}/api/v1/reports/flow?preset=30d"
```
