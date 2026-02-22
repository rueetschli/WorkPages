# WorkPages – Operator Guide

Dieses Handbuch richtet sich an Betreiber, die WorkPages selbst hosten. Es beschreibt Installation, Update, Backup, Restore und den laufenden Betrieb.

---

## Inhaltsverzeichnis

1. [Ueberblick](#1-ueberblick)
2. [Systemanforderungen](#2-systemanforderungen)
3. [Installation](#3-installation)
4. [Update-Prozess](#4-update-prozess)
5. [Backup](#5-backup)
6. [Restore / Disaster Recovery](#6-restore--disaster-recovery)
7. [E-Mail & Notifications Betrieb](#7-e-mail--notifications-betrieb)
8. [Sprachen](#8-sprachen)
9. [Troubleshooting](#9-troubleshooting)
10. [Security Basics](#10-security-basics)
11. [English Quick Start](#11-english-quick-start)

---

## 1. Ueberblick

### Was ist WorkPages?

WorkPages ist eine selbst gehostete Alternative zu Jira und Confluence fuer KMUs. Es bietet Task-Boards, Wikis (Pages), Teams, Reporting und Benachrichtigungen in einer einzigen Applikation – ohne Cloud-Abhaengigkeit.

### Was bedeutet Betreiber-Verantwortung?

Als Self-Hosting-Betreiber sind Sie verantwortlich fuer:

- **Hosting**: Webserver, PHP, Datenbank bereitstellen und pflegen
- **Backups**: Regelmaessige Sicherung von Datenbank und Dateien
- **Updates**: Neue Versionen einspielen und Migrationen ausfuehren
- **Sicherheit**: HTTPS, sichere Passwoerter, Dateirechte, Zugangskontrolle
- **Monitoring**: Logfiles pruefen, Systemstatus ueberwachen

WorkPages liefert unter `?r=admin_health` einen Systemstatus-Check, der die wichtigsten Aspekte automatisch prueft.

---

## 2. Systemanforderungen

### PHP

- **Version**: PHP 8.0 oder hoeher
- **Pflicht-Extensions**: `PDO`, `pdo_mysql`, `mbstring`, `json`
- **Empfohlen**: `fileinfo` (fuer Upload-MIME-Erkennung), `openssl` (fuer SMTP TLS)

### Datenbank

- MySQL 5.7+ oder MariaDB 10.3+
- Zeichensatz: `utf8mb4` / `utf8mb4_unicode_ci`

### Webserver

- Apache mit `mod_rewrite` oder Nginx
- Document Root muss auf `/public` zeigen

### Dateirechte

| Verzeichnis / Datei | Rechte | Beschreibung |
|---|---|---|
| `storage/` | 755 | Logs, Uploads, Cache, Sprach-Overrides |
| `storage/logs/` | 755 | Applikations-Logfile |
| `storage/uploads/` | 755 | Datei-Anhaenge |
| `storage/cache/` | 755 | Temporaere Dateien |
| `storage/lang/` | 755 | Sprachdatei-Overrides |
| `config/` | 755 | Konfigurationsdatei |

### Empfohlenes Setup fuer Shared Hosting (z.B. Cyon, Hostpoint)

1. Dateien per FTP/SFTP hochladen
2. Document Root im Hosting-Panel auf `/public` setzen
3. PHP-Version im Panel auf 8.0+ stellen
4. MySQL-Datenbank ueber das Panel anlegen
5. Installer aufrufen: `https://ihre-domain.ch/?r=install`

---

## 3. Installation

### 3.1 Dateien hochladen

Laden Sie alle Projektdateien per FTP/SFTP auf Ihren Webserver. Die Ordnerstruktur muss erhalten bleiben:

```
/
  app/
  config/
  database/
  docs/
  public/          <-- Document Root
  storage/
```

### 3.2 Document Root setzen

Konfigurieren Sie Ihren Hoster so, dass der Document Root auf den Ordner `/public` zeigt.

- **Cyon**: Control Panel > Websites > Document Root aendern
- **Hostpoint**: Control Panel > Hosting > Einstellungen > Document Root

### 3.3 Schreibrechte setzen

Per FTP-Client oder SSH:

```bash
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/uploads/
chmod 755 storage/cache/
chmod 755 storage/lang/
chmod 755 config/
```

### 3.4 Installer ausfuehren

Oeffnen Sie im Browser:

```
https://ihre-domain.ch/?r=install
```

Der Installer fuehrt Sie durch folgende Schritte:

1. **Umgebungspruefung** – PHP-Version, Extensions, Schreibrechte
2. **Datenbank konfigurieren** – Host, Name, Benutzer, Passwort, Base URL eingeben. Die Verbindung wird getestet und `config/config.php` wird automatisch erstellt.
3. **Schema erstellen** – Alle Datenbanktabellen werden angelegt (Migrationen laufen automatisch bei Erstinstallation).
4. **Admin-Benutzer anlegen** – Name, E-Mail und Passwort (min. 10 Zeichen) fuer den ersten Administrator.
5. **Fertig** – Der Installer sperrt sich automatisch (Datei `storage/install.lock`).

### 3.5 Anmelden

```
https://ihre-domain.ch/?r=login
```

Melden Sie sich mit den im Installer erstellten Admin-Zugangsdaten an.

### 3.6 Erstkonfiguration

Nach der Anmeldung empfohlen:

- **Branding einrichten**: `?r=admin_settings` (Firmenname, Logo, Farbschema)
- **Sprache setzen**: `?r=admin_settings` (Standard-Sprache)
- **E-Mail konfigurieren**: `config/config.php` (MAIL_MODE, SMTP-Daten)
- **Systemstatus pruefen**: `?r=admin_health`

---

## 4. Update-Prozess

### Vor dem Update

> **Pflicht**: Erstellen Sie vor jedem Update ein vollstaendiges Backup (Datenbank + Storage + Config). Siehe Kapitel 5.

### Schritt fuer Schritt

1. **Backup erstellen**
   - Datenbank-Dump (siehe Kapitel 5.2)
   - `storage/` sichern
   - `config/config.php` sichern

2. **Neue Dateien hochladen**
   - Ersetzen Sie `app/`, `public/`, `database/`, `docs/` mit den neuen Dateien
   - **Nicht ueberschreiben**: `config/config.php`, `storage/` (Ihre Daten!)

3. **Migrationen ausfuehren**
   - Oeffnen Sie `?r=admin_migrate` und fuehren Sie ausstehende Migrationen aus
   - Alternativ: Migrationen werden bei Bedarf automatisch erkannt

4. **Cache leeren** (falls vorhanden)
   - Loeschen Sie den Inhalt von `storage/cache/` (nicht den Ordner selbst)

5. **Smoke Tests durchfuehren**
   - [ ] Login funktioniert
   - [ ] Dashboard / Home laedt
   - [ ] Board-Ansicht laedt
   - [ ] Eine Seite (Page) oeffnen
   - [ ] Einen Task oeffnen
   - [ ] Systemstatus pruefen: `?r=admin_health` (alles gruen)
   - [ ] Migrationen pruefen: `?r=admin_migrate` (keine ausstehend)

---

## 5. Backup

### 5.1 Was sichern?

| Was | Pfad / Ort | Inhalt |
|---|---|---|
| **Datenbank** | MySQL/MariaDB | Alle Tabellen, Daten, Benutzer, Tasks, Pages |
| **Uploads** | `storage/uploads/` | Datei-Anhaenge (Bilder, PDFs, Dokumente) |
| **Logs** | `storage/logs/` | Applikations-Logfile (optional, aber hilfreich) |
| **Sprach-Overrides** | `storage/lang/` | Eigene Uebersetzungen (falls vorhanden) |
| **Konfiguration** | `config/config.php` | Datenbank-Zugangsdaten, App-Einstellungen |
| **Branding** | `public/assets/branding/` | Hochgeladenes Logo (falls vorhanden) |

### 5.2 Wie sichern?

#### Datenbank: Per Kommandozeile (SSH)

```bash
mysqldump -u DBUSER -p DBNAME > workpages_backup_$(date +%Y%m%d).sql
```

Ersetzen Sie `DBUSER` und `DBNAME` mit Ihren Werten aus `config/config.php`.

#### Datenbank: Per phpMyAdmin (Shared Hosting)

1. Oeffnen Sie phpMyAdmin ueber das Hosting-Panel
2. Waehlen Sie die WorkPages-Datenbank
3. Klicken Sie auf **Exportieren**
4. Format: **SQL**
5. Methode: **Schnell** (alle Tabellen)
6. Klicken Sie auf **OK** und speichern Sie die Datei

#### Dateien: Per Kommandozeile (SSH)

```bash
tar -czf workpages_files_$(date +%Y%m%d).tar.gz storage/ config/config.php public/assets/branding/
```

#### Dateien: Per FTP (Shared Hosting)

1. Verbinden Sie sich per FTP/SFTP
2. Laden Sie folgende Ordner/Dateien herunter:
   - `storage/uploads/`
   - `storage/lang/`
   - `config/config.php`
   - `public/assets/branding/` (falls Logo vorhanden)

### 5.3 Backup-Frequenz Empfehlung

| Nutzungsintensitaet | Empfohlene Frequenz |
|---|---|
| Wenige Benutzer, geringe Aktivitaet | Woechentlich |
| Taegliche Nutzung, mittleres Team | Taeglich |
| Hohe Nutzung, viele Aenderungen | Taeglich + vor jedem Update |

**Grundregel**: Sichern Sie immer vor Updates und nach groesseren Dateneingaben.

### 5.4 Backup-Erinnerung

Unter `?r=admin_backup` koennen Sie das Datum des letzten Backups manuell erfassen. Dies dient als Erinnerung fuer Sie und andere Administratoren – es wird kein automatisches Backup durchgefuehrt.

---

## 6. Restore / Disaster Recovery

### 6.1 Voraussetzung

Sie benoetigen:
- Einen funktionierenden Webserver mit PHP und MySQL (siehe Kapitel 2)
- Ihre letzte Backup-Datei (SQL-Dump)
- Ihre gesicherten Dateien (storage, config)

### 6.2 Schritt fuer Schritt

#### 1. Datenbank wiederherstellen

Per Kommandozeile (SSH):
```bash
mysql -u DBUSER -p DBNAME < workpages_backup_20240101.sql
```

Per phpMyAdmin:
1. Oeffnen Sie phpMyAdmin
2. Waehlen oder erstellen Sie die Datenbank
3. Klicken Sie auf **Importieren**
4. Waehlen Sie die Backup-SQL-Datei
5. Klicken Sie auf **OK**

#### 2. Dateien wiederherstellen

Laden Sie die gesicherten Dateien an die richtigen Stellen hoch:
- `storage/uploads/` → Datei-Anhaenge
- `storage/lang/` → Sprach-Overrides
- `config/config.php` → Konfiguration
- `public/assets/branding/` → Logo

#### 3. Konfiguration pruefen

Oeffnen Sie `config/config.php` und pruefen Sie:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` – stimmen die Werte fuer die neue Umgebung?
- `BASE_URL` – stimmt die URL des neuen Servers?
- `LOG_FILE`, `UPLOAD_DIR` – stimmen die Pfade?

#### 4. Migrationen pruefen

Oeffnen Sie `?r=admin_migrate` und fuehren Sie fehlende Migrationen aus, falls die Backup-Datenbank aelter ist als der aktuelle Code.

#### 5. Testlogin

- Melden Sie sich mit einem Admin-Konto an
- Pruefen Sie: Dashboard, Boards, Pages, Tasks
- Pruefen Sie: `?r=admin_health` (Systemstatus)

---

## 7. E-Mail & Notifications Betrieb

### Voraussetzungen

WorkPages versendet E-Mails fuer:
- Benachrichtigungen (Task-Zuweisungen, Kommentare, Erwaehungen)
- Tages- und Wochen-Digests
- Test-Mails (Admin-Funktion)

Die E-Mail-Konfiguration erfolgt in `config/config.php`:

```php
'MAIL_MODE'      => 'smtp',        // 'mail' (PHP mail()) oder 'smtp'
'MAIL_FROM'      => 'noreply@ihre-domain.ch',
'MAIL_FROM_NAME' => 'WorkPages',

// Nur bei MAIL_MODE = 'smtp':
'SMTP_HOST'      => 'smtp.ihre-domain.ch',
'SMTP_PORT'      => 587,
'SMTP_USER'      => 'noreply@ihre-domain.ch',
'SMTP_PASS'      => 'ihr-smtp-passwort',
'SMTP_SECURE'    => 'tls',         // 'tls' oder 'ssl'
```

### Test-Mail senden

1. Oeffnen Sie `?r=admin_health`
2. Im Abschnitt **E-Mail** klicken Sie auf **Test-Mail senden**
3. Eine Test-Mail wird an Ihre Admin-E-Mail-Adresse gesendet

### E-Mail-Queue verarbeiten

E-Mails werden in eine Warteschlange geschrieben. Verarbeitung:
- Unter `?r=admin_health`: Klicken Sie auf **E-Mail-Queue jetzt senden**
- Oder unter `?r=admin_email_queue`: Detaillierte Queue-Verwaltung

### Haeufige Fehlerbilder

| Problem | Ursache | Loesung |
|---|---|---|
| Test-Mail kommt nicht an | SMTP-Zugangsdaten falsch | `config/config.php` pruefen, Zugangsdaten beim Hoster nachschlagen |
| E-Mails landen im Spam | Kein SPF/DKIM fuer Domain | SPF- und DKIM-Eintraege im DNS setzen |
| Fehler "Connection refused" | SMTP-Port blockiert | Port 587 (TLS) oder 465 (SSL) pruefen, Hoster kontaktieren |
| Fehler "Authentication failed" | SMTP-Benutzername oder Passwort falsch | Zugangsdaten in `config/config.php` korrigieren |
| Queue waechst, E-Mails werden nicht versendet | Queue wird nicht verarbeitet | `?r=admin_health` → **E-Mail-Queue jetzt senden** |

---

## 8. Sprachen

### Verfuegbare Sprachen

WorkPages wird mit Deutsch (`de`) und Englisch (`en`) ausgeliefert. Die Sprachdateien liegen unter:

```
app/lang/de.json    (Deutsch – Systemstandard)
app/lang/en.json    (English)
```

### Eigene Uebersetzungen / Overrides

Sprachdateien koennen unter `storage/lang/` ueberschrieben werden:

```
storage/lang/de.json    (Overrides fuer Deutsch)
storage/lang/en.json    (Overrides fuer Englisch)
storage/lang/fr.json    (Neue Sprache: Franzoesisch)
```

Dateien in `storage/lang/` haben Vorrang vor `app/lang/`. Sie muessen nicht alle Keys enthalten – fehlende Keys fallen auf die Systemdatei zurueck.

### Standard-Sprache setzen

Unter `?r=admin_settings` kann die Standard-Sprache fuer neue Benutzer festgelegt werden.

### Sprachstatus pruefen

Unter `?r=admin_languages` sehen Sie:
- Alle verfuegbaren Sprachen
- Vollstaendigkeit der Uebersetzungen
- Fehlende Keys

### Fallback-Logik

1. Benutzersprache (aus Session/Profil)
2. Standard-Sprache (aus Systemeinstellungen)
3. Deutsch (`de`) als letzte Fallback-Sprache
4. Fehlender Key wird als Key-String zurueckgegeben (z.B. `health.page_title`)

---

## 9. Troubleshooting

### 500er Fehler (Internal Server Error)

**Checks:**
1. Log pruefen: `storage/logs/app.log` (letzte Eintraege)
2. PHP-Fehlerlog des Servers pruefen (Pfad je nach Hoster)
3. Schreibrechte pruefen: `storage/logs/` muss beschreibbar sein
4. Temporaer Debug aktivieren: In `config/config.php` → `'DEBUG' => true` (als Admin eingeloggt werden Details angezeigt)
5. **Nach Behebung**: `'DEBUG' => false` zuruecksetzen

### Leere Seite (White Screen)

**Checks:**
1. PHP-Fehlerlog pruefen
2. `config/config.php` vorhanden? Falls nicht → Installer aufrufen: `?r=install`
3. PHP-Version pruefen (mind. 8.0)
4. Extensions pruefen: `PDO`, `pdo_mysql`, `mbstring`, `json`
5. Dateirechte pruefen (siehe Kapitel 2)

### Migrationen fehlen

**Symptome:** Fehler wie "Table not found", fehlende Spalten, defekte Ansichten

**Checks:**
1. Oeffnen Sie `?r=admin_migrate`
2. Fuehren Sie alle ausstehenden Migrationen aus
3. Pruefen Sie `?r=admin_health` danach

### E-Mail kommt nicht an

Siehe Kapitel 7 → Haeufige Fehlerbilder.

### Login funktioniert nicht

**Checks:**
1. Passwort korrekt? (Gross-/Kleinschreibung beachten)
2. Benutzer aktiv? (Admin prueft unter `?r=admin_users`)
3. Session-Probleme? Browser-Cookies loeschen, neuen Tab oeffnen
4. HTTPS korrekt konfiguriert? Session-Cookies werden nur ueber HTTPS gesendet, wenn der Server HTTPS nutzt

### Board oder Seite zeigt keine Daten

**Checks:**
1. Team-Filter pruefen: Ist das richtige Team ausgewaehlt? (Team-Switcher oben rechts)
2. Berechtigungen pruefen: Hat der Benutzer die richtige Rolle?
3. Daten vorhanden? Gibt es Tasks/Pages fuer das aktive Team?

### Uploads funktionieren nicht

**Checks:**
1. Schreibrechte: `storage/uploads/` beschreibbar? (755)
2. PHP-Upload-Limit: `upload_max_filesize` und `post_max_size` in `php.ini` pruefen
3. Erlaubte Dateitypen: `UPLOAD_ALLOWED_MIME` und `UPLOAD_ALLOWED_EXT` in `config/config.php`

---

## 10. Security Basics

### Admin-Passwoerter

- Verwenden Sie starke Passwoerter (mind. 10 Zeichen, Buchstaben + Zahlen + Sonderzeichen)
- Der Installer erzwingt bereits eine Mindestlaenge von 10 Zeichen
- Aendern Sie Standard-Passwoerter nach der Installation

### HTTPS Pflicht

- Betreiben Sie WorkPages **ausschliesslich ueber HTTPS**
- Session-Cookies sind mit `httponly` und `samesite=Lax` geschuetzt
- Ohne HTTPS koennen Login-Daten abgefangen werden

### Dateirechte minimal halten

- `config/config.php`: 644 (lesbar fuer PHP, nicht oeffentlich zugaenglich)
- `storage/`: 755 (beschreibbar fuer PHP)
- Stellen Sie sicher, dass der Webserver keine Verzeichnisliste anzeigt
- `/storage/` und `/config/` sollten nicht direkt ueber den Browser erreichbar sein (Document Root = `/public`)

### Kein Commit von Secrets

Falls Sie WorkPages-Dateien in einem eigenen Git-Repository verwalten:
- `config/config.php` darf **nicht** committet werden (enthaelt DB-Passwort, SMTP-Passwort)
- Nutzen Sie `config/config.php.example` als Vorlage
- Pruefen Sie `.gitignore` – `config/config.php` muss dort eingetragen sein

### Weitere Empfehlungen

- Halten Sie PHP und MySQL/MariaDB auf dem aktuellen Stand
- Deaktivieren Sie nicht benoetigte PHP-Funktionen in der `php.ini`
- Pruefen Sie regelmaessig `?r=admin_health` auf Warnungen
- Entfernen Sie den Installer-Zugang nach der Einrichtung (automatisch via `storage/install.lock`)

---

## 11. English Quick Start

### Installation

1. Upload all project files via FTP/SFTP. Set the document root to `/public`.
2. Ensure PHP 8.0+, MySQL 5.7+, and extensions `PDO`, `pdo_mysql`, `mbstring`, `json`.
3. Set write permissions: `chmod 755 storage/ config/` (and subdirectories).
4. Open `https://your-domain.com/?r=install` and follow the installer wizard.
5. Log in at `?r=login` with the admin credentials created during installation.
6. Configure branding and email under `?r=admin_settings` and `config/config.php`.

### Backup Essentials

- **Database**: Export via `mysqldump` (SSH) or phpMyAdmin (Export > SQL).
- **Files**: Download `storage/uploads/`, `storage/lang/`, `config/config.php`, and `public/assets/branding/`.
- **Frequency**: At least weekly; daily for active teams. Always before updates.

### Update Essentials

1. Create a full backup (database + files).
2. Replace `app/`, `public/`, `database/`, `docs/` with the new release. Keep `config/config.php` and `storage/`.
3. Run pending migrations at `?r=admin_migrate`.
4. Verify the system at `?r=admin_health`.

### Restore

1. Import the SQL dump into MySQL.
2. Upload backed-up files (`storage/`, `config/config.php`, `public/assets/branding/`).
3. Verify `config/config.php` settings match the new server.
4. Run `?r=admin_migrate` if needed. Log in and check `?r=admin_health`.
