# WorkPages

**Die schlanke, selbst hostbare Alternative zu Jira und Confluence.** Entwickelt für Shared Hosting. Kein Docker, keine Build-Tools, kein Cloud-Zwang.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.0-8892BF.svg)](#)

---

## 🛑 Warum WorkPages? (Der Anti-Cloud-Ansatz)

Bist du die komplexen Docker-Setups, endlosen Node.js-Abhängigkeiten und SaaS-Abos leid, die deine Daten in US-Clouds einsperren?

WorkPages ist eine integrierte Wiki- und Aufgabenplattform, die sich auf das Wesentliche konzentriert. Wissensmanagement (Pages) und Projektsteuerung (Tasks) fliessen in einer einzigen, pfeilschnellen Webanwendung zusammen.

- **Keine US-Cloud, keine Telemetrie:** Deine Daten gehören deiner Organisation. Es gibt kein Tracking, keine versteckten Datenabflüsse und keinen Vendor Lock-in.
- **Kein Docker notwendig:** WorkPages wurde speziell für klassische Shared-Hosting-Umgebungen entwickelt. Einfach die Dateien per FTP hochladen, den Web-Installer starten und loslegen.
- **Keine Build-Pipeline:** Komplett ohne npm, Webpack oder Frontend-Frameworks. Pures serverseitig gerendertes PHP und Vanilla CSS.

## 🎯 Für wen ist das gedacht?

- **KMU & Agenturen:** Kundenprojekte, Content-Planung und interne Dokumentation an einem zentralen Ort verwalten.
- **Datenschutzbewusste Organisationen:** Perfekt für Unternehmen mit strengen DSG-Anforderungen, die eine On-Premise-Lösung suchen.
- **Schulen & Vereine:** Kosteneffiziente interne Organisation und Wissensvermittlung.
- **Pragmatische Admins:** Alle, die die Einfachheit eines klassischen PHP + MySQL Stacks schätzen.

---

## ✨ Features

### 📝 Wissensdatenbank (Wiki)
- **Markdown Native:** Erstelle hierarchische Seiten mit einem leistungsstarken Markdown-Editor.
- **Struktur:** Parent-Child-Beziehungen, saubere URL-Slugs und Breadcrumb-Navigation.
- **Sharing:** Generiere kryptografisch sichere Read-Only-Links mit Ablaufdatum für externe Partner.
- **Export & Management:** Markdown-Exporte, Soft-Deletes und Funktionen zum Verschieben/Kopieren von Seiten.
- **Templates:** Nutze vordefinierte Seitenvorlagen für wiederkehrende Dokumentationen.

### 📋 Aufgabenverwaltung & Agile Boards
- **Kanban Boards:** Flexible Spalten, WIP-Limits und einfache Statuswechsel.
- **Sprints & Workflow:** Sprint-Planung, Burndown-Charts, Velocity-Reports und Zeitschätzungen.
- **Aufgaben-Hierarchie:** Strukturiere Arbeit via Epics > Features > Tasks.
- **Smarte Verknüpfungen:** Verbinde Aufgaben direkt mit Wiki-Seiten (Many-to-Many).
- **Flow Metrics:** Integrierte Reports für Lead Time, Cycle Time und Throughput.

### 🤝 Kollaboration & UI
- **Smart Text:** `@mentions` mit Autovervollständigung und `#tag`-Referenzen.
- **Activity Stream:** Automatische Aktivitätsprotokolle und formatierte Kommentare.
- **Benachrichtigungen:** In-App-Badges, E-Mail-Digests und ein flexibles Watcher-System.
- **Modernes Design:** Vollständig responsiv, nativer Dark Mode und anpassbares Branding (Farben, Logos).
- **Internationalisierung:** Komplett auf Deutsch und Englisch übersetzt.

### 🔒 Sicherheit, API & Administration
- **Enterprise-Grade Security:** Strikte PDO Prepared Statements, überall CSRF-Tokens und Session-Hardening (httponly, secure, samesite=Lax).
- **Rollenbasiert:** Admin-, Member- und Viewer-Rollen sowie teambasierte Sichtbarkeitssteuerung.
- **Developer Ready:** REST API v1 mit Bearer-Tokens, Rate Limiting und HMAC-SHA256 gesicherten Webhooks.
- **Systemdiagnostik:** Admin-Dashboard zur Überwachung von PHP/DB-Status, Speicherplatz und Migrationen.

---

## 🛠 Technischer Stack

| Komponente | Technologie |
| :--- | :--- |
| **Backend** | PHP 8.0+ (Vanilla, keine Frameworks wie Laravel oder Symfony) |
| **Datenbank** | MySQL 5.7+ oder MariaDB 10.3+ |
| **Frontend** | Serverseitiges Rendering, Vanilla CSS, minimales Vanilla JS (Kein React/Vue) |
| **Architektur** | MVC, Front Controller, PDO |
| **Build-Prozess** | **Keiner.** Kein npm, kein Webpack, kein Bundler. |
| **Markdown** | Parsedown (PHP), GitHub Markdown CSS, Easy Markdown Editor |

## 🚀 Installation & Hosting-Anforderungen

WorkPages ist optimal auf klassische Schweizer Shared-Hosting-Anbieter (z.B. Cyon, Hostpoint) abgestimmt.

**Anforderungen:**
- PHP >= 8.0 (mit Extensions: `PDO`, `pdo_mysql`, `mbstring`, `json`)
- MySQL 5.7+ oder MariaDB 10.3+
- Apache (mit `mod_rewrite`) oder Nginx

**Kurzanleitung:**
1. Lade die Repository-Dateien via FTP/SFTP auf deinen Server hoch.
2. Setze das Document Root deines Servers auf das `/public`-Verzeichnis.
3. Stelle sicher, dass Schreibrechte für `/storage/` und `/config/` vorhanden sind.
4. Rufe `https://deine-domain.ch/?r=install` im Browser auf.
5. Folge dem intuitiven Setup-Wizard.
6. Einloggen und loslegen!

*Detaillierte Anweisungen findest du unter [docs/INSTALL.md](docs/INSTALL.md).*

---

## 🔄 Updates & Betrieb

Updates für WorkPages sind genauso einfach wie die Installation:
1. Erstelle ein Backup deiner Datenbank sowie der Verzeichnisse `/storage/` und `/config/`.
2. Lade die Dateien des neuen Releases hoch (deine Config und Storage bleiben unangetastet).
3. Melde dich als Admin an und führe die Migrationen via `?r=admin_migrate` aus.
4. Prüfe den Systemstatus unter `?r=admin_system`.

---

## 🤝 Mitwirken

Beiträge (Contributions) sind herzlich willkommen. Bitte respektiere dabei die Kernphilosophie dieses Projekts:

- PHP 8+ Kompatibilität sicherstellen.
- **Keine Frameworks:** Keine externen Frameworks (Laravel, Symfony, etc.) einführen.
- **Keine Build-Tools:** Keine Node.js-Abhängigkeiten, npm oder SPAs hinzufügen.
- **Sicherheit zuerst:** Nutze PDO Prepared Statements für *alle* Abfragen. Schreibende Aktionen ausschliesslich via `POST` mit CSRF-Schutz.
- **Shared Hosting kompatibel:** Stelle sicher, dass Änderungen keinen Root-Zugriff oder spezielle Daemons erfordern.

## 📚 Dokumentation

- [Installationsanleitung](docs/INSTALL.md)
- [Konfigurationsreferenz](docs/CONFIG.md)
- [REST API v1 Dokumentation](docs/api.md)
- [Entwicklungs-Arbeitspakete (AP1-AP31)](docs/APs.md)

---

## 📄 Lizenz & Credits

WorkPages ist Open-Source-Software und lizenziert unter der [MIT License](LICENSE).  
Copyright (c) 2024-2026 WorkPages Contributors.

**Verwendete Open-Source-Bibliotheken:**
- [Parsedown](https://github.com/erusev/parsedown) (MIT)
- [GitHub Markdown CSS](https://github.com/sindresorhus/github-markdown-css) (MIT)
- [Easy Markdown Editor](https://github.com/Ionaru/easy-markdown-editor) (MIT)
