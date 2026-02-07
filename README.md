# WorkPages

WorkPages

Integrierte Wiki- und Task-Plattform für agile KMU
PHP + MySQL, lauffähig auf Shared Hosting (z.B. Cyon)

⸻

1. Vision

WorkPages ist eine integrierte Web-Software für kleine und mittlere Unternehmen (KMU), insbesondere:
	•	Agenturen und Marketing-Teams
	•	Kleine Handwerks- und Dienstleistungsbetriebe

Ziel ist es, Confluence-ähnliche Wissensseiten und Jira-/Trello-ähnliche Aufgabenverwaltung in einer einzigen Anwendung zu vereinen.

Es gibt keine getrennten Tools.
Eine Seite ist der Ort, an dem Wissen, Aufgaben, Entscheidungen und Aktivitäten zusammenlaufen.

⸻

2. Zielgruppe

2.1 Agenturen
	•	Content-Planung
	•	Kampagnensteuerung
	•	Kundenprojekte
	•	Redaktionsplanung
	•	Review-Workflows

2.2 Handwerks- und Kleinbetriebe
	•	Projektübersicht
	•	Offene Aufgaben
	•	Baustellenkoordination
	•	interne Organisation
	•	Dokumentation

⸻

3. Technischer Rahmen

Hosting-Umgebung
	•	Shared Hosting (z.B. Cyon)
	•	PHP 8.x
	•	MySQL / MariaDB
	•	Apache oder Nginx
	•	Kein Docker
	•	Keine Node-Build-Pipeline erforderlich

Architekturprinzip
	•	Klassisches MVC in PHP
	•	Front Controller Pattern
	•	PDO mit Prepared Statements
	•	Kein Framework-Zwang
	•	Minimalistische Abhängigkeiten

⸻

4. Kernkonzept

4.1 Pages
	•	Markdown-basierte Inhalte
	•	Hierarchisch
	•	Zentraler Arbeitsraum
	•	Können Tasks enthalten

4.2 Tasks
	•	Leichtgewichtige Issues
	•	Status-basiert (Kanban)
	•	Owner
	•	Due Date
	•	Tags
	•	Mit Pages verknüpfbar

4.3 Integration

Eine Page kann:
	•	Aufgaben anzeigen
	•	Aufgaben erzeugen
	•	Mit Aufgaben verknüpft sein

Ein Task kann:
	•	Mehreren Pages zugeordnet sein
	•	Seine verknüpften Pages anzeigen

⸻

5. MVP-Funktionsumfang

Auth
	•	Login / Logout
	•	Rollen: admin, member, viewer

Pages
	•	CRUD
	•	Markdown Rendering
	•	Hierarchie

Tasks
	•	CRUD
	•	Status (Kanban)
	•	Owner
	•	Due Date
	•	Tags

Board
	•	Spalten nach Status
	•	Statuswechsel
	•	Filter

Search
	•	Volltextsuche über Pages und Tasks

Activity
	•	Statusänderungen
	•	Kommentare
	•	Änderungsprotokoll


##Architekturübersicht
'''
/public
    index.php
    /assets
/app
    /controllers
    /models
    /views
    /services
/config
/storage
'''

##Front Controller

public/index.php ist der einzige Einstiegspunkt.

Routing erfolgt über:
'''
index.php?r=home
index.php?r=login
index.php?r=pages
'''

Datenmodell (MVP)

users
	•	id
	•	email
	•	name
	•	password_hash
	•	role
	•	created_at

pages
	•	id
	•	title
	•	slug
	•	parent_id
	•	content_md
	•	created_by
	•	updated_by
	•	created_at
	•	updated_at

tasks
	•	id
	•	title
	•	description_md
	•	status
	•	owner_id
	•	due_date
	•	created_by
	•	updated_by
	•	created_at
	•	updated_at

tags
	•	id
	•	name

task_tags
	•	task_id
	•	tag_id

page_tasks
	•	page_id
	•	task_id

comments
	•	id
	•	entity_type
	•	entity_id
	•	body_md
	•	created_by
	•	created_at

activity
	•	id
	•	entity_type
	•	entity_id
	•	action
	•	meta_json
	•	created_by
	•	created_at

⸻

8. Sicherheitsanforderungen

Pflicht:
	•	Prepared Statements via PDO
	•	password_hash / password_verify
	•	CSRF Token bei POST Requests
	•	Session Cookie Flags:
	•	httponly
	•	samesite
	•	secure bei HTTPS
	•	Keine SQL direkt im Controller
	•	Keine direkte Ausgabe ungefilterter User-Inputs

⸻

9. UX-Prinzipien
	•	Klar
	•	Ruhig
	•	Viel Weissraum
	•	Keine überladenen Masken
	•	Inline-Editing wenn möglich
	•	Schnelle Navigation
	•	Kein Kontextwechsel zwischen Wiki und Tasks
