# Sprachen verwalten / Managing Languages

WorkPages unterstuetzt mehrere UI-Sprachen. Benutzerinhalte (Pages, Tasks, Kommentare) sind davon nicht betroffen und bleiben einsprachig.

---

## 1. Wo liegen die Sprachdateien?

Es gibt zwei Verzeichnisse:

| Verzeichnis       | Zweck                                       | Update-sicher? |
|-------------------|---------------------------------------------|----------------|
| `/app/lang/`      | Mitgelieferte Sprachen (versioniert, Git)    | Nein           |
| `/storage/lang/`  | Eigene oder angepasste Sprachen              | Ja             |

Dateien in `/storage/lang/` ueberschreiben oder ergaenzen gleichnamige Dateien aus `/app/lang/`.
Fuer eigene Uebersetzungen oder Anpassungen immer `/storage/lang/` verwenden.

---

## 2. Referenzsprache

Die Datei `de.json` (Deutsch) ist die Referenz. Alle anderen Sprachen werden gegen diese Datei verglichen.

Fehlende Eintraege in einer Sprache werden automatisch aus `de.json` uebernommen (Fallback).

---

## 3. Neue Sprache hinzufuegen (Schritt fuer Schritt)

### Schritt 1: Referenzdatei kopieren

```
cp /app/lang/de.json /storage/lang/fr.json
```

Ersetze `fr` durch den gewuenschten Sprachcode (ISO 639-1, z.B. `fr`, `it`, `es`, `pt`, `nl`, `pl`).

### Schritt 2: Datei uebersetzen

Oeffne die Kopie (z.B. `fr.json`) in einem Texteditor und uebersetze alle Werte.
Die Keys (linke Seite) duerfen **nicht** veraendert werden.

**Vorher:**
```json
{
  "actions.save": "Speichern",
  "actions.cancel": "Abbrechen"
}
```

**Nachher:**
```json
{
  "actions.save": "Enregistrer",
  "actions.cancel": "Annuler"
}
```

**Tipp: KI-Uebersetzung**

Die gesamte Datei kann mit einem KI-Dienst uebersetzt werden. Beispiel-Prompt:

> Uebersetze die Werte (nicht die Keys) in der folgenden JSON-Datei ins Franzoesische.
> Behalte die {platzhalter} exakt bei. Gib nur gueltiges JSON zurueck.

Danach die Ausgabe als `fr.json` in `/storage/lang/` speichern.

### Schritt 3: Datei ablegen

Die fertige Datei in `/storage/lang/` ablegen:

```
/storage/lang/fr.json
```

Die Sprache wird automatisch erkannt. Kein Neustart und keine Code-Aenderung noetig.

### Schritt 4: Uebersetzungsstatus pruefen

Im Admin-Bereich unter **Administration > Sprachen** (/?r=admin_languages) wird fuer jede Sprache angezeigt:

- Uebersetzungsgrad in Prozent
- Anzahl fehlender Keys
- Liste der fehlenden Keys (aufklappbar)

Fehlende Keys werden automatisch aus Deutsch uebernommen.

### Schritt 5: Sprache verwenden

Benutzer koennen ihre UI-Sprache an zwei Stellen waehlen:

- **Login-Screen:** Dezente Sprachumschaltung unter dem Login-Formular
- **Im eingeloggten Zustand:** Im Benutzermenu oben rechts (Dropdown "Sprache")

Administratoren koennen die Standard-Sprache unter **Administration > Sprachen** festlegen.

---

## 4. Bestehende Sprache anpassen

Um einzelne Uebersetzungen anzupassen, ohne die mitgelieferte Datei zu veraendern:

1. Erstelle eine Datei mit demselben Namen in `/storage/lang/` (z.B. `de.json`)
2. Fuege nur die Keys ein, die geaendert werden sollen:

```json
{
  "app.name": "Meine Firma"
}
```

Alle anderen Keys werden weiterhin aus `/app/lang/de.json` geladen.

---

## 5. Format der Sprachdateien

- Flache Key-Value-Struktur (keine Verschachtelung)
- Dateiname = Sprachcode + `.json` (z.B. `fr.json`)
- Sprachcode: 2 Buchstaben klein, optional `_XX` fuer Region (z.B. `pt_BR`)
- Encoding: UTF-8
- Platzhalter: `{name}` Syntax

```json
{
  "messages.password_min_length": "Passwort muss mindestens {min} Zeichen lang sein."
}
```

---

## 6. Typische Fehler

| Problem                        | Ursache                                          | Loesung                                    |
|--------------------------------|--------------------------------------------------|--------------------------------------------|
| Sprache erscheint nicht        | Dateiname ungueltig (z.B. `french.json`)          | Datei in `fr.json` umbenennen              |
| Sprache erscheint nicht        | Datei liegt im falschen Verzeichnis               | In `/storage/lang/` ablegen                |
| Fehlende Texte                 | Keys fehlen in der Sprachdatei                    | Fehlende Keys ergaenzen (Fallback: Deutsch)|
| JSON-Fehler                    | Ungueltiges JSON (fehlendes Komma, etc.)          | JSON-Validator verwenden                   |
| Platzhalter werden nicht ersetzt| `{name}` wurde veraendert oder entfernt          | Platzhalter exakt beibehalten              |
| Sonderzeichen falsch           | Datei nicht in UTF-8 gespeichert                  | Editor auf UTF-8 einstellen                |

---

## 7. Wichtiger Hinweis

Die Mehrsprachigkeit betrifft ausschliesslich **System- und UI-Texte**:

- Navigation, Buttons, Labels
- Fehlermeldungen, Platzhalter
- Admin-Oberflaeche

**Nicht betroffen** sind Benutzerinhalte:

- Page-Titel und -Inhalte
- Task-Titel und -Beschreibungen
- Kommentare

Ein Page-Titel existiert genau in der Sprache, in der der Benutzer ihn erstellt hat.
Es gibt keine Sprachvarianten von Benutzerinhalten.

---

## 8. Technische Details fuer Entwickler

- Service: `App\Services\I18nService`
- Globale Funktion: `t('key', ['param' => 'value'])`
- Fallback-Kette: Aktive Sprache → Deutsch (`de`) → Key-Name
- Caching: Pro Request (statische Variablen)
- Key-Schema: `bereich.name` (z.B. `actions.save`, `errors.required`, `admin.users_title`)
