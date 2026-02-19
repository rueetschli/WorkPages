# Installation

## Systemanforderungen

- PHP >= 8.0
- MySQL 5.7+ oder MariaDB 10.3+
- PHP-Extensions: PDO, pdo_mysql, mbstring, json
- Schreibrechte auf `/storage/` und `/config/`
- Document Root zeigt auf `/public`
- Apache mit mod_rewrite oder Nginx

## Schritt-fuer-Schritt-Anleitung

### 1. Dateien hochladen

Laden Sie alle Projektdateien per FTP/SFTP auf Ihren Webserver hoch. Die Ordnerstruktur muss erhalten bleiben:

```
/
  app/
  config/
  docs/
  public/
  storage/
```

### 2. Document Root setzen

Konfigurieren Sie Ihren Hoster so, dass der Document Root auf den Ordner `/public` zeigt.

**Cyon:** Control Panel > Websites > Document Root aendern
**Hostpoint:** Control Panel > Hosting > Einstellungen > Document Root

### 3. Schreibrechte setzen

Folgende Verzeichnisse muessen beschreibbar sein (chmod 755 oder 775):

- `storage/` (inkl. Unterordner `logs`, `uploads`, `cache`, `branding`)
- `config/`

Per FTP:
```
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/uploads/
chmod 755 storage/cache/
chmod 755 storage/branding/
chmod 755 config/
```

### 4. Installer aufrufen

Oeffnen Sie im Browser:

```
https://ihre-domain.ch/?r=install
```

Der Installer fuehrt Sie durch folgende Schritte:

1. **Umgebungspruefung** - PHP-Version, Extensions, Schreibrechte
2. **Datenbank konfigurieren** - Host, Name, Benutzer, Passwort, Base URL eingeben. Die Verbindung wird getestet und `config/config.php` automatisch erstellt.
3. **Schema erstellen** - Alle Datenbanktabellen werden angelegt.
4. **Admin-Benutzer anlegen** - Name, E-Mail und Passwort (min. 10 Zeichen) fuer den ersten Administrator.
5. **Fertig** - Der Installer sperrt sich automatisch.

### 5. Anmelden

```
https://ihre-domain.ch/?r=login
```

Melden Sie sich mit den im Installer erstellten Admin-Zugangsdaten an.

### 6. Migrationen ausfuehren

Falls Sie eine bestehende Datenbank aktualisieren:

```
https://ihre-domain.ch/?r=admin_migrate
```

### 7. Branding einrichten (optional)

Unter `?r=admin_settings` koennen Sie:
- Firmenname setzen
- Logo hochladen
- Farbschema waehlen

## Update-Prozess

1. **Backup erstellen** - Datenbank (SQL-Export), `storage/uploads/`, `config/config.php`
2. **Neue Dateien hochladen** - Bestehende Dateien ersetzen. `config/config.php` und `storage/` bleiben erhalten.
3. **Migrationen ausfuehren** - `?r=admin_migrate`
4. **System-Info pruefen** - `?r=admin_system`

## Nginx-Konfiguration

Falls Sie Nginx statt Apache verwenden:

```nginx
server {
    listen 80;
    server_name ihre-domain.ch;
    root /pfad/zu/workpages/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

## Troubleshooting

**Datenbankverbindung schlaegt fehl**
- Pruefen Sie die Zugangsdaten in `config/config.php`
- Stellen Sie sicher, dass die Datenbank existiert

**500-Fehler im Browser**
- Pruefen Sie `storage/logs/app.log`
- Setzen Sie `DEBUG` auf `true` in `config/config.php` (nur temporaer)

**Schreibrechte-Probleme**
- Pruefen Sie unter `?r=admin_system`
- Setzen Sie Rechte per FTP auf 755

**Installer gesperrt**
- Loeschen Sie `storage/install.lock`
- Setzen Sie `INSTALL_UNLOCK` auf `true` in `config/config.php`

**Leere Seite nach Update**
- Migrationen ausfuehren: `?r=admin_migrate`
- Log pruefen: `storage/logs/app.log`
