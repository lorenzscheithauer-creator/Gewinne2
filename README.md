# Gewinne2

Dieses Repository enthält ein PHP-Skript, das Gewinnspiel-Links von ausgewählten Webseiten einsammelt und in die MySQL-Tabelle `gewinnspiele` schreibt.

## Lokales Ausführen mit XAMPP

1. Installiere [XAMPP](https://www.apachefriends.org/) und starte Apache sowie MySQL.
2. Klone oder kopiere dieses Repository in dein `htdocs`-Verzeichnis, z. B. `C:\xampp\htdocs\Gewinne2`.
3. Öffne phpMyAdmin (http://localhost/phpmyadmin) und führe die Datei `gewinnspiele.sql` aus. Dadurch werden Datenbank und Tabelle erstellt.
4. Passe bei Bedarf die Variablen `$dbHost`, `$dbName`, `$dbUser` und `$dbPass` in `crawl_gewinnspiele.php` an (Standardwerte funktionieren in einer frischen XAMPP-Installation).
5. Rufe den Crawler im Browser auf: http://localhost/Gewinne2/crawl_gewinnspiele.php. Die Seite zeigt an, wie viele Links gefunden und neu gespeichert wurden.

## Optional: Automatischer Crawl über GitHub Actions

Der Workflow `.github/workflows/crawl_gewinnspiele.yml` führt das Skript alle 10 Minuten aus. Dafür müssen im GitHub-Repository die folgenden Secrets gesetzt sein:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Die Secrets werden als Umgebungsvariablen in das Skript injiziert und erlauben dem Workflow den Zugriff auf die Datenbank. Dieser Weg ist optional – primär wird der Crawler derzeit lokal über XAMPP betrieben.
