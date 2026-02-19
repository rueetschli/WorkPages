# WorkPages

Selbst hostbare Alternative zu Jira und Confluence. Open Source, PHP + MySQL, lauffaehig auf Shared Hosting.

**Lizenz:** MIT | **Repository:** [github.com/rueetschli/WorkPages](https://github.com/rueetschli/WorkPages)

---

## Was ist WorkPages?

WorkPages ist eine integrierte Wiki- und Aufgabenplattform. Wissensseiten (Pages) und Aufgabenverwaltung (Tasks) laufen in einer einzigen Webanwendung zusammen - ohne externe Abhaengigkeiten, ohne Cloud-Zwang, ohne Tracking.

WorkPages ist **kein SaaS-Produkt**. Es gibt kein Abo, kein Vendor Lock-in, keine Telemetrie. Die Daten gehoeren der Organisation, die es betreibt.

## Fuer wen ist WorkPages gedacht?

- **KMU mit 5-200 Mitarbeitenden** - Projektdokumentation und Aufgabenverwaltung an einem Ort
- **Agenturen und Marketing-Teams** - Content-Planung, Kampagnensteuerung, Kundenprojekte
- **Vereine und Schulen** - Interne Organisation und Wissensmanagement
- **Handwerk und Dienstleister** - Projektuebersicht und Aufgabenzuweisung
- **Organisationen mit Datenschutzanforderungen** - Volle Kontrolle ueber eigene Daten
- **Technisch affine Admins** - Einfaches Setup mit PHP und MySQL

## Was WorkPages bewusst nicht ist

- Kein SaaS, kein Cloud-Dienst
- Kein Enterprise-Tool fuer Grosskonzerne
- Kein Framework-Monolith (kein Laravel, kein Symfony)
- Kein SPA (kein React, kein Vue)
- Kein Node-Build, kein npm, kein Webpack
- Kein Docker-Zwang
- Kein Tracking, keine Telemetrie, keine Werbung

## Features

### Authentifizierung und Rollen (AP1-AP3)
- Login/Logout mit Session-Hardening (httponly, secure, samesite)
- Drei Rollen: Admin, Member, Viewer
- Serverseitige Rechtesteuerung
- Benutzerverwaltung mit Self-Lockout-Schutz

### Pages - Wissensseiten (AP4)
- CRUD mit Markdown-Rendering
- Hierarchische Seitenstruktur (Parent-Child)
- URL-Slugs und Breadcrumb-Navigation
- Soft Delete
- Markdown-Export einzelner Seiten

### Tasks - Aufgaben (AP5-AP6)
- CRUD mit Status (Backlog, Ready, Doing, Review, Done)
- Owner-Zuweisung, Faelligkeitsdatum, Tags
- Verknuepfung mit Pages (Many-to-Many)
- CSV-Export aller Aufgaben

### Kanban Board (AP7, AP13)
- Flexible, konfigurierbare Spalten
- Drag-and-Drop-aehnliche Statuswechsel
- WIP-Limits pro Spalte
- Farbige Spaltenmarkierungen
- Filteroptionen

### Suche (AP8)
- Ueber Pages und Tasks
- Modi: LIKE, FULLTEXT, Auto
- Snippet-Anzeige mit Hervorhebung

### Kommentare und Activity Log (AP8)
- Kommentare auf Pages und Tasks
- Automatisches Aktivitaetsprotokoll
- Formatierte Anzeige mit Benutzer und Zeitstempel

### Sharing (AP9)
- Kryptografisch sichere Share-Links fuer Seiten
- Nur-Lesen-Zugriff ohne Login
- Widerruf und Ablaufdatum

### Administration (AP9-AP10)
- Benutzerverwaltung mit Rollensteuerung
- Datenbank-Migrationen ueber Browser-UI
- System-Informationen

### Installer (AP10)
- Browser-basierter Installations-Wizard
- Umgebungspruefung, DB-Konfiguration, Schema-Erstellung, Admin-Anlage
- Automatische Sperre nach Installation

### Design-System und Mobile (AP11-AP12)
- Responsives Layout fuer Desktop und Mobile
- Dark Mode
- Reines CSS ohne Build-Tools

### Smart Text Commands (AP14)
- @Mentions mit Autocomplete
- Tag-Referenzen (#tag)
- Inline-Formatierung in Kommentaren

### Benachrichtigungen (AP15)
- In-App Benachrichtigungen mit Echtzeit-Badge
- E-Mail-Benachrichtigungen (einzeln und als Digest)
- Watcher-System (Seiten und Aufgaben beobachten)
- Konfigurierbare Benachrichtigungseinstellungen pro Benutzer
- E-Mail Queue mit Admin-Verwaltung

### Teams und Zugriffskontrolle (AP16)
- Team-basierte Sichtbarkeit
- Team-Rollen (Team-Admin, Team-Member, Team-Viewer)
- Team-Switcher im Header
- Teamzuweisung fuer Pages und Tasks

### Dateianhänge (AP17)
- Upload und Download von Dateien
- MIME-Type und Extension-Validierung
- Team-basierte Sichtbarkeit
- Konfigurierbare Limits (Groesse, Anzahl, erlaubte Typen)

### Reporting und Flow Metrics (AP18)
- Uebersichtsberichte mit KPIs
- Flow-Metriken (Lead Time, Cycle Time, Throughput)
- Aging-Analyse fuer offene Aufgaben
- CSV-Export von Reports
- Report-Caching fuer Performance

### API und Webhooks (AP19)
- REST API v1 mit Bearer-Token-Authentifizierung
- API-Schluessel pro Benutzer mit Scopes
- Rate Limiting (Token Bucket)
- Idempotency Keys
- Webhooks mit HMAC-SHA256-Signaturen
- Webhook Queue mit Retry-Logik

### Personalisierung und Branding (AP20)
- Firmenname und Logo konfigurierbar
- Farbschema: 8 Presets oder eigene Farben (HEX)
- Wartungs- und Systemhinweise (Banner)
- Systemweite CSS-Variablen fuer konsistentes Theming
- Footer mit Version, Lizenz und Repository-Link

## Technischer Stack

| Komponente | Technologie |
|---|---|
| Backend | PHP 8.0+ (kein Framework) |
| Datenbank | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | Serverseitiges Rendering, Vanilla CSS, minimales Vanilla JS |
| Architektur | MVC, Front Controller, PDO |
| Build | Keiner. Kein npm, kein Webpack, kein Bundler |
| Abhaengigkeiten | Keine externen PHP- oder JS-Libraries |

## Hosting-Anforderungen

WorkPages ist fuer Schweizer Shared-Hosting-Anbieter optimiert:

- **PHP** >= 8.0
- **MySQL** 5.7+ oder MariaDB 10.3+
- **PHP-Extensions:** PDO, pdo_mysql, mbstring, json
- **Schreibrechte** auf `/storage/`, `/config/`
- **Document Root** zeigt auf `/public`
- Apache mit mod_rewrite oder Nginx

Getestet mit: Cyon, Hostpoint und vergleichbaren Schweizer Anbietern.

## Installation

Siehe [docs/INSTALL.md](docs/INSTALL.md) fuer die vollstaendige Installationsanleitung.

### Kurzanleitung

1. Dateien per FTP/SFTP hochladen
2. Document Root auf `/public` setzen
3. Schreibrechte setzen (storage/, config/)
4. `https://ihre-domain.ch/?r=install` aufrufen
5. Installer-Wizard durchlaufen
6. Anmelden unter `https://ihre-domain.ch/?r=login`

## Update und Migration

1. Backup erstellen (Datenbank, storage/uploads/, config/config.php)
2. Neue Dateien hochladen (config.php und storage/ bleiben erhalten)
3. Als Admin anmelden
4. Migrationen ausfuehren unter `?r=admin_migrate`
5. System-Info pruefen unter `?r=admin_system`

## Konfiguration

Siehe [docs/CONFIG.md](docs/CONFIG.md) fuer alle Konfigurationsoptionen.

Die Konfigurationsdatei `config/config.php` wird vom Installer automatisch erstellt. Eine Vorlage liegt unter `config/config.php.example`.

## Sicherheit und Datenschutz

- Alle Datenbankabfragen verwenden PDO Prepared Statements
- CSRF-Token-Validierung bei allen POST-Requests
- Session-Hardening: httponly, secure, samesite=Lax
- Output-Escaping mit htmlspecialchars
- Passwort-Hashing mit password_hash / password_verify
- Rollen und Berechtigungen serverseitig durchgesetzt
- Fehler ins Log, nicht in den Browser
- config.php liegt ausserhalb des Document Root
- Kein Tracking, keine Telemetrie, keine externen Requests

## Lizenz

WorkPages ist Open Source unter der [MIT License](LICENSE).

Copyright (c) 2024-2026 WorkPages Contributors

## Mitwirken

Beitraege sind willkommen. Bitte beachten Sie:

- PHP 8+ kompatibel
- Kein Framework-Wechsel
- Kein SPA-Umbau
- Kein Node-Build
- PDO Prepared Statements ueberall
- Schreibende Aktionen nur via POST mit CSRF-Schutz
- Rollen serverseitig erzwingen
- Fehler ins Log, nicht in den Browser
- Shared-Hosting-kompatibel

## Dokumentation

- [Installation](docs/INSTALL.md)
- [Konfiguration](docs/CONFIG.md)
- [Arbeitspakete AP1-AP20](docs/APs.md)
- [REST API v1](docs/api.md)
