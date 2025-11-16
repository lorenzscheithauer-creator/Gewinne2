# Gewinne2

Dieses Repository enthält ein PHP-Skript, das regelmäßig Gewinnspiel-Links von ausgewählten Webseiten einsammelt und in die MySQL-Tabelle `gewinnspiele` schreibt.

## Lokales Ausführen des Crawlers

1. Installiere PHP 8.2 (oder neuer) mit den Erweiterungen `curl` sowie `dom`.
2. Setze die folgenden Umgebungsvariablen für die Datenbankverbindung:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
3. Starte den Crawler mit:

```bash
php crawl_gewinnspiele.php
```

## Automatischer Crawl über GitHub Actions

Der Workflow `.github/workflows/crawl_gewinnspiele.yml` führt das Skript alle 10 Minuten aus. Dafür müssen im GitHub-Repository die folgenden Secrets gesetzt sein:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Die Secrets werden als Umgebungsvariablen in das Skript injiziert und erlauben dem Workflow den Zugriff auf die Datenbank.
