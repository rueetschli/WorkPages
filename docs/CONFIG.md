# Konfiguration

Die Konfigurationsdatei liegt unter `config/config.php`. Sie wird vom Installer automatisch erstellt. Eine Vorlage finden Sie in `config/config.php.example`.

## Konfigurationsschluessel

### Datenbank

| Schluessel | Beschreibung | Standard |
|---|---|---|
| `DB_HOST` | Datenbank-Host | `localhost` |
| `DB_NAME` | Name der Datenbank | - |
| `DB_USER` | Datenbank-Benutzer | - |
| `DB_PASS` | Datenbank-Passwort | - |
| `DB_CHARSET` | Zeichensatz | `utf8mb4` |

### Applikation

| Schluessel | Beschreibung | Standard |
|---|---|---|
| `BASE_URL` | Basis-URL (z.B. `https://ihre-domain.ch`) | - |
| `APP_KEY` | Zufaelliger Schluessel (vom Installer generiert) | - |
| `APP_NAME` | Name der Applikation (Fallback fuer Branding) | `WorkPages` |
| `APP_ENV` | Umgebung | `production` |

### Debug

```php
'DEBUG' => false,
```

Wenn `true` und der Benutzer Admin ist, werden bei Fehlern Details angezeigt. In Produktion immer `false`. Fehler werden immer in `storage/logs/app.log` geloggt.

### Suchmodus

```php
'SEARCH_MODE' => 'like',
```

- `like` - SQL LIKE (funktioniert immer)
- `fulltext` - MySQL FULLTEXT (schneller, erfordert Indexes)
- `auto` - Versucht FULLTEXT, faellt auf LIKE zurueck

Fuer FULLTEXT muessen Indexes manuell erstellt werden:

```sql
ALTER TABLE pages ADD FULLTEXT INDEX ft_pages (title, content_md);
ALTER TABLE tasks ADD FULLTEXT INDEX ft_tasks (title, description_md);
```

### Installer-Sperre

```php
'INSTALL_UNLOCK' => false,
```

Um den Installer erneut auszufuehren:
1. `INSTALL_UNLOCK` auf `true` setzen
2. `storage/install.lock` loeschen
3. `?r=install` aufrufen
4. Danach wieder auf `false` setzen

### E-Mail

| Schluessel | Beschreibung | Standard |
|---|---|---|
| `MAIL_MODE` | Versandmethode | `mail` |
| `MAIL_FROM` | Absender-Adresse | - |
| `MAIL_FROM_NAME` | Absendername | APP_NAME |

#### SMTP-Einstellungen (wenn MAIL_MODE = smtp)

| Schluessel | Beschreibung | Standard |
|---|---|---|
| `SMTP_HOST` | SMTP-Server | `localhost` |
| `SMTP_PORT` | Port | `587` |
| `SMTP_USER` | Benutzername | - |
| `SMTP_PASS` | Passwort | - |
| `SMTP_SECURE` | Verschluesselung | `tls` |

### Datei-Uploads

| Schluessel | Beschreibung | Standard |
|---|---|---|
| `UPLOAD_MAX_MB` | Max. Dateigroesse in MB | `20` |
| `UPLOAD_MAX_PER_ENTITY` | Max. Dateien pro Objekt | `50` |
| `UPLOAD_ALLOWED_MIME` | Erlaubte MIME-Types (Array) | Siehe Vorlage |
| `UPLOAD_ALLOWED_EXT` | Erlaubte Extensions (Array) | Siehe Vorlage |

### Logging

| Schluessel | Beschreibung | Standard |
|---|---|---|
| `LOG_FILE` | Pfad zur Logdatei | `storage/logs/app.log` |

## Systemweite Einstellungen (Admin UI)

Zusaetzlich zur config.php gibt es systemweite Einstellungen, die ueber die Admin-Oberflaeche konfiguriert werden (`?r=admin_settings`):

- **Firmenname** - Wird im Header, Login und in E-Mails angezeigt
- **Logo** - PNG, JPG oder SVG, max. 1 MB
- **Farbschema** - Preset oder eigene HEX-Farben
- **Systemhinweise** - Wartungsbanner (Information, Warnung, Kritisch)

Diese Einstellungen werden in der Datenbank gespeichert (Tabelle `system_settings`) und ueberschreiben die Standardwerte aus config.php.
