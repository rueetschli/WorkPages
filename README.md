# WorkPages

Integrierte Wiki- und Aufgabenplattform fuer kleine und mittlere Unternehmen.
PHP + MySQL, lauffaehig auf Shared Hosting (z.B. Cyon). Kein Docker, kein Node-Build, keine externen Abhaengigkeiten.

## Was ist WorkPages?

WorkPages vereint Confluence-aehnliche Wissensseiten und Jira-/Trello-aehnliche Aufgabenverwaltung in einer einzigen Webanwendung. Seiten, Aufgaben, Kommentare und Aktivitaeten laufen an einem Ort zusammen.

## Zielpublikum und Use Cases

**Agenturen und Marketing-Teams**
- Content-Planung und Redaktionskalender
- Kampagnensteuerung mit verknuepften Aufgaben
- Kundenprojekte dokumentieren und nachverfolgen
- Review-Workflows im Kanban-Board

**Handwerk, Dienstleister und Kleinbetriebe**
- Projektuebersicht mit Wissensseiten
- Offene Aufgaben verwalten und zuweisen
- Interne Organisation und Dokumentation
- Einfache Installation ohne IT-Abteilung

## Features

**Authentifizierung und Rollen**
- Login / Logout mit Session-Hardening
- Drei Rollen: admin, member, viewer
- Serverseitige Rechtesteuerung (viewer = nur lesen, member = lesen/schreiben, admin = alles)
- Benutzerverwaltung (erstellen, bearbeiten, deaktivieren)
- Schutz gegen Self-Lockout (letzter Admin kann nicht degradiert werden)

**Pages (Wissensseiten)**
- CRUD mit Markdown-Rendering
- Hierarchische Seitenstruktur (Parent-Child)
- URL-Slugs und Breadcrumb-Navigation
- Soft Delete
- Markdown-Export einzelner Seiten

**Tasks (Aufgaben)**
- CRUD mit Status (Backlog, Ready, Doing, Review, Done)
- Owner-Zuweisung, Faelligkeitsdatum, Tags
- Verknuepfung mit Pages (Many-to-Many)
- Statuswechsel direkt auf der Seite
- CSV-Export aller Aufgaben

**Kanban Board**
- Spalten nach Status
- Statuswechsel per Formular
- Positionierung innerhalb der Spalten
- Filteroptionen

**Suche**
- Suche ueber Pages und Tasks
- Modi: LIKE (immer), FULLTEXT (optional), Auto (Fallback)
- Snippet-Anzeige mit Hervorhebung

**Kommentare und Activity Log**
- Kommentare auf Pages und Tasks
- Automatisches Aktivitaetsprotokoll fuer alle Aenderungen
- Formatierte Anzeige mit Benutzer und Zeitstempel

**Sharing (optional)**
- Kryptografisch sichere Share-Links fuer Seiten
- Nur-Lesen-Zugriff ohne Login
- Widerruf und Ablaufdatum

**Administration**
- Benutzerverwaltung mit Rollensteuerung
- Datenbank-Migrationen ueber Browser-UI
- System-Informationen (PHP, MySQL, Schreibrechte, Konfiguration)

**Exports**
- Tasks als CSV (Semikolon-getrennt, UTF-8 mit BOM fuer Excel)
- Pages als Markdown-Datei

**Installer**
- Browser-basierter Installations-Wizard
- Umgebungspruefung, DB-Konfiguration, Schema-Erstellung, Admin-Anlage
- Automatische Lock-Datei nach Installation

## Systemanforderungen

- PHP >= 8.0
- MySQL 5.7+ oder MariaDB 10.3+
- PHP-Extensions: PDO, pdo_mysql, mbstring, json
- Schreibrechte auf `/storage/logs`, `/storage/uploads`, `/storage`, `/config`
- Document Root zeigt auf `/public`
- Apache mit mod_rewrite oder Nginx

## Installation auf Shared Hosting (z.B. Cyon)

### 1. Dateien hochladen

Laden Sie alle Projektdateien per FTP/SFTP auf Ihren Webserver hoch. Die Ordnerstruktur muss erhalten bleiben:

```
/
  app/
  config/
  database/
  public/
  storage/
```

### 2. Document Root setzen

Konfigurieren Sie Ihren Hoster so, dass der Document Root auf den Ordner `/public` zeigt.
Bei Cyon koennen Sie dies im Control Panel unter "Websites" einstellen.

### 3. Schreibrechte setzen

Stellen Sie sicher, dass folgende Verzeichnisse beschreibbar sind (chmod 755 oder 775):

- `storage/` (inkl. Unterordner `logs`, `uploads`, `cache`)
- `config/`

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

Nach der Installation:

```
https://ihre-domain.ch/?r=login
```

Melden Sie sich mit den im Installer erstellten Admin-Zugangsdaten an.

## Konfiguration

Die Konfigurationsdatei liegt unter `config/config.php`. Sie wird vom Installer automatisch erstellt. Eine Vorlage finden Sie in `config/config.php.example`.

### Konfigurationsschluessel

**Datenbank**
- `DB_HOST` - Datenbank-Host (z.B. `localhost`)
- `DB_NAME` - Name der Datenbank
- `DB_USER` - Datenbank-Benutzer
- `DB_PASS` - Datenbank-Passwort
- `DB_CHARSET` - Zeichensatz (Standard: `utf8mb4`)

**Applikation**
- `BASE_URL` - Basis-URL der Applikation (z.B. `https://ihre-domain.ch`)
- `APP_KEY` - Zufaelliger Schluessel (wird vom Installer generiert)
- `APP_NAME` - Name der Applikation (wird im Header angezeigt)
- `APP_ENV` - `production` oder `development`

**DEBUG**

```php
'DEBUG' => false,
```

Wenn `DEBUG` auf `true` gesetzt ist und der angemeldete Benutzer die Rolle `admin` hat, werden bei Fehlern detaillierte Informationen angezeigt (Fehlermeldung, Datei, Zeile, Stacktrace). In Production sollte dies immer `false` sein. Fehler werden unabhaengig von dieser Einstellung in `/storage/logs/app.log` geloggt.

**SEARCH_MODE**

```php
'SEARCH_MODE' => 'like',
```

- `like` - Suche mit SQL LIKE (funktioniert immer, etwas langsamer bei grossen Datenmengen)
- `fulltext` - Suche mit MySQL FULLTEXT Indexes (schneller, erfordert FULLTEXT Indexes auf den Tabellen)
- `auto` - Versucht FULLTEXT, faellt auf LIKE zurueck

Fuer FULLTEXT muessen die Indexes manuell erstellt werden:

```sql
ALTER TABLE pages ADD FULLTEXT INDEX ft_pages (title, content_md);
ALTER TABLE tasks ADD FULLTEXT INDEX ft_tasks (title, description_md);
```

**INSTALL_UNLOCK**

```php
'INSTALL_UNLOCK' => false,
```

Nach der Installation sperrt sich der Installer automatisch (Datei `storage/install.lock`). Um den Installer erneut auszufuehren:

1. Setzen Sie `INSTALL_UNLOCK` auf `true`
2. Loeschen Sie `storage/install.lock`
3. Rufen Sie `?r=install` auf
4. Setzen Sie `INSTALL_UNLOCK` danach wieder auf `false`

## Migration / Update-Prozess

### Vorgehen bei Updates

1. **Backup erstellen** (siehe Abschnitt Backup)
2. **Neue Dateien hochladen** - Ersetzen Sie die bestehenden Dateien. `config/config.php` und `storage/` werden nicht ueberschrieben (config.php ist in .gitignore, storage-Inhalte ebenfalls).
3. **Migrationen ausfuehren** - Melden Sie sich als Admin an und navigieren Sie zu:

```
?r=admin_migrate
```

Die Seite zeigt die aktuelle Schema-Version und eine Liste ausstehender Migrationen. Klicken Sie auf "Migrationen ausfuehren" um alle ausstehenden Migrationen anzuwenden.

4. **System-Info pruefen** - Unter `?r=admin_system` koennen Sie die aktuelle App-Version, Schema-Version, PHP- und MySQL-Version sowie Schreibrechte pruefen.

### Migrationen

Migrationsdateien liegen unter `app/migrations/` im Format `NNN_beschreibung.sql` (z.B. `001_init.sql`, `002_add_feature.sql`). Die aktuelle Schema-Version wird in der Tabelle `app_meta` gespeichert. Migrationen werden sequenziell ausgefuehrt und im Activity Log protokolliert.

## Backup

Sichern Sie regelmaessig folgende Bestandteile:

- **Datenbank** - SQL-Export (z.B. via phpMyAdmin oder mysqldump)
- **Uploads** - Ordner `storage/uploads/`
- **Konfiguration** - Datei `config/config.php`

## Security

- Alle Datenbankabfragen verwenden PDO Prepared Statements
- CSRF-Token-Validierung bei allen POST-Requests (hash_equals)
- Session-Hardening: httponly, secure, samesite=Lax
- Output-Escaping mit htmlspecialchars (Security::esc)
- Passwort-Hashing mit password_hash / password_verify
- Rollen und Berechtigungen werden serverseitig durchgesetzt
- Installer sperrt sich nach erfolgreicher Installation
- Keine Stacktraces oder SQL-Fehler im Browser (nur im Log)
- config.php liegt ausserhalb des Document Root

## Projektstruktur

```
WorkPages/
  public/                    Document Root
    index.php                Front Controller (einziger Einstiegspunkt)
    .htaccess                Apache Rewrite Rules
    assets/
      app.css                Stylesheet
  app/
    controllers/
      HomeController.php     Dashboard
      AuthController.php     Login / Logout / Setup
      PageController.php     Pages CRUD
      TaskController.php     Tasks CRUD
      BoardController.php    Kanban Board
      CommentController.php  Kommentare
      SearchController.php   Suche
      AdminController.php    Benutzerverwaltung, Migrationen, System Info
      ExportController.php   CSV- und Markdown-Export
      InstallController.php  Installations-Wizard
      ShareController.php    Freigabelinks
    models/
      User.php               Benutzer-Modell
      Page.php               Seiten-Modell
      Task.php               Aufgaben-Modell
      Comment.php            Kommentar-Modell
      PageTask.php           Page-Task-Verknuepfung
      Activity.php           Aktivitaetslog-Modell
      PageShare.php          Freigabelink-Modell
    services/
      DB.php                 PDO-Singleton mit Prepared-Statement-Helpers
      Security.php           Session, CSRF, Escaping, Auth-Helpers
      Logger.php             Dateibasiertes Logging
      Authz.php              Rollenbasierte Zugriffskontrolle
      ActivityService.php    Aktivitaetsprotokoll
      SearchService.php      Suchlogik (LIKE / FULLTEXT)
      Markdown.php           Markdown-zu-HTML-Rendering
    views/
      layout.php             Hauptlayout (Header, Sidebar, Content)
      login.php              Login-Seite
      setup.php              Ersteinrichtung
      home.php               Dashboard
      403.php, 404.php, 500.php  Fehlerseiten
      pages/                 Seiten-Views
      tasks/                 Aufgaben-Views
      board/                 Kanban-Board
      search/                Suchergebnisse
      admin/
        users/               Benutzerverwaltung
        migrate/             Migrations-UI
        system/              System-Informationen
      install/               Installer-Views
      share/                 Freigabe-Views
      partials/              Wiederverwendbare Komponenten
    migrations/
      001_init.sql           Initiales Schema (alle Tabellen)
  config/
    config.php.example       Konfigurationsvorlage
  database/
    001-010_*.sql            Einzelne Migrationsdateien (Entwicklungshistorie)
    seed_admin.sql           Test-Admin-Seed
  storage/
    logs/                    Logdateien
    uploads/                 Hochgeladene Dateien
    cache/                   Cache
    install.lock             Installer-Sperre (nach Installation)
```

## Troubleshooting

**Datenbankverbindung schlaegt fehl**
- Pruefen Sie die Zugangsdaten in `config/config.php`
- Stellen Sie sicher, dass die Datenbank existiert
- Testen Sie die Verbindung mit einem MySQL-Client

**500-Fehler im Browser**
- Pruefen Sie das Log: `storage/logs/app.log`
- Setzen Sie `DEBUG` auf `true` in `config/config.php` (als Admin angemeldet werden detaillierte Fehler angezeigt)
- Pruefen Sie PHP-Fehler im Hosting-Error-Log

**Schreibrechte-Probleme**
- `storage/logs/`, `storage/uploads/`, `storage/`, `config/` muessen beschreibbar sein
- Setzen Sie die Rechte per FTP auf 755 oder 775
- Pruefen Sie die Rechte unter `?r=admin_system`

**Installer zeigt "gesperrt"**
- Loeschen Sie `storage/install.lock`
- Setzen Sie `INSTALL_UNLOCK` auf `true` in `config/config.php`

**Leere Seite nach Update**
- Fuehren Sie die Migrationen aus: `?r=admin_migrate`
- Pruefen Sie das Log auf Fehler

**Logdatei-Pfad**
- Standard: `storage/logs/app.log`
- Konfigurierbar ueber `LOG_FILE` in `config/config.php`

## Routen-Uebersicht

| Route | Beschreibung |
|---|---|
| `?r=home` | Dashboard |
| `?r=login` | Anmeldung |
| `?r=logout` | Abmeldung |
| `?r=install` | Installer |
| `?r=pages` | Seitenliste |
| `?r=page_view&slug=...` | Seite anzeigen |
| `?r=page_create` | Seite erstellen |
| `?r=page_edit&slug=...` | Seite bearbeiten |
| `?r=tasks` | Aufgabenliste |
| `?r=task_view&id=...` | Aufgabe anzeigen |
| `?r=task_create` | Aufgabe erstellen |
| `?r=board` | Kanban Board |
| `?r=search&q=...` | Suche |
| `?r=admin_users` | Benutzerverwaltung (admin) |
| `?r=admin_migrate` | Datenbank-Migrationen (admin) |
| `?r=admin_system` | System-Informationen (admin) |
| `?r=export_tasks_csv` | Tasks CSV-Export |
| `?r=export_page_md&slug=...` | Seite als Markdown exportieren |

## Lizenz und Status

Privates Projekt. Lizenz: TBD.

App-Version: 1.0.0 (gespeichert in `app_meta` Tabelle nach Installation)
