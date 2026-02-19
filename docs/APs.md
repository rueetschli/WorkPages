# Arbeitspakete (AP1-AP20)

Uebersicht aller Arbeitspakete von WorkPages.

## AP1-AP3: Authentifizierung und Rollen

Grundlegende Benutzerauthentifizierung mit Session-Hardening. Drei Rollen (Admin, Member, Viewer) mit serverseitiger Rechtesteuerung. Login, Logout und Setup-Flow.

## AP4: Pages (Wissensseiten)

CRUD fuer hierarchische Wissensseiten mit Markdown-Rendering, URL-Slugs, Breadcrumb-Navigation und Soft Delete.

## AP5-AP6: Tasks (Aufgaben)

CRUD fuer Aufgaben mit Status-Workflow (Backlog, Ready, Doing, Review, Done), Owner-Zuweisung, Faelligkeitsdatum, Tags und Verknuepfung mit Pages.

## AP7: Kanban Board

Visuelles Board mit Spalten nach Status. Statuswechsel per Formular, Positionierung innerhalb der Spalten, Filteroptionen.

## AP8: Suche, Kommentare, Activity Log

Volltextsuche ueber Pages und Tasks (LIKE/FULLTEXT). Kommentarsystem fuer Pages und Tasks. Automatisches Aktivitaetsprotokoll fuer alle Aenderungen.

## AP9: Sharing und Benutzerverwaltung

Kryptografisch sichere Share-Links fuer Seiten (Nur-Lesen, ohne Login). Admin-Benutzerverwaltung mit Self-Lockout-Schutz.

## AP10: Installer, Migrationen, System Info, Exports

Browser-basierter Installations-Wizard. Migrations-UI fuer Schema-Updates. System-Informationsseite. CSV- und Markdown-Export.

## AP11: UX Redesign und Design-System

Vollstaendiges CSS-Design-System mit Design Tokens, Custom Properties und komponentenbasierter Architektur. Responsives Layout.

## AP12: Mobile Optimierung

Touch-optimierte Oberflaeche. Hamburger-Menu, Mobile-Sidebar, responsive Tabellen und Formulare.

## AP13: Flexible Kanban-Spalten

Konfigurierbare Board-Spalten mit WIP-Limits, Farben und Kategorien. Spalten erstellen, bearbeiten, loeschen und sortieren.

## AP14: Smart Text Commands

@Mentions mit Autocomplete fuer Benutzer. Tag-Referenzen (#tag) mit Verlinkung. Inline-Rendering in Kommentaren und Beschreibungen.

## AP15: Benachrichtigungen, Watcher, E-Mail

In-App Benachrichtigungen mit Echtzeit-Badge und Dropdown. E-Mail-Benachrichtigungen (einzeln und Digest). Watcher-System zum Beobachten von Seiten und Aufgaben. Konfigurierbare Einstellungen pro Benutzer. E-Mail Queue mit Admin-Verwaltung.

## AP16: Teams und Zugriffskontrolle

Team-basierte Sichtbarkeit fuer Pages und Tasks. Team-Rollen (Team-Admin, Team-Member, Team-Viewer). Team-Switcher im Header. Teamzuweisung und teamuebergreifende Filterung.

## AP17: Dateianhänge

Upload und Download von Dateien an Pages und Tasks. MIME-Type- und Extension-Validierung. Konfigurierbare Limits. Team-basierte Sichtbarkeit. Sichere Speicherung mit Checksummen.

## AP18: Reporting und Flow Metrics

Uebersichtsberichte mit KPIs (offene Tasks, Durchsatz, Alterung). Flow-Metriken (Lead Time, Cycle Time, Throughput). Aging-Analyse. CSV-Export. Report-Caching fuer Performance.

## AP19: API und Webhooks

REST API v1 mit Bearer-Token-Authentifizierung und Scopes. Rate Limiting (Token Bucket). Idempotency Keys. Webhooks mit HMAC-SHA256-Signaturen und Retry-Logik. API-Schluessel-Verwaltung. Webhook Queue mit Admin-UI.

## AP20: Finalisierung, Personalisierung, Open-Source-Release

Systemweite Personalisierung: Firmenname, Logo, Farbschema (8 Presets oder eigene HEX-Farben). Wartungs- und Systemhinweise als Banner. Footer mit Version, Lizenz und Repository-Link. Admin-Einstellungsseite mit Tabs. CSS-Variablen fuer konsistentes Theming. MIT-Lizenz, vollstaendige README und Dokumentation.
