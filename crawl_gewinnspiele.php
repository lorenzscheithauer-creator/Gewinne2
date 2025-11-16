<?php
/**
 * Einfacher Crawler, der Gewinnspiel-Links aus definierten Webseiten ermittelt
 * und in die MySQL-Tabelle "gewinnspiele" einträgt.
 */

declare(strict_types=1);

/**
 * Einstiegspunkt des Skripts.
 */
function main(): void
{
    echo "Starte Crawl...\n";

    $dsn = buildDsn();

    try {
        $pdo = new PDO(
            $dsn,
            getenv('DB_USER') ?: '',
            getenv('DB_PASS') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        fwrite(STDERR, "[Fehler] DB-Verbindung fehlgeschlagen: {$e->getMessage()}\n");
        return;
    }

    $targetUrls = [
        'https://12gewinne.de',
        'https://supergewinne.de',
        'https://www.gewinn-portal.de',
    ];

    $selectStmt = $pdo->prepare('SELECT id FROM gewinnspiele WHERE link_zur_webseite = :link LIMIT 1');
    $insertStmt = $pdo->prepare('INSERT INTO gewinnspiele (link_zur_webseite, beschreibung) VALUES (:link, NULL)');

    $insertedCount = 0;

    foreach ($targetUrls as $url) {
        echo "Rufe {$url} ab...\n";
        $html = fetchHtml($url);

        if ($html === null) {
            continue;
        }

        $links = parsePrizeLinks($html, $url);
        $links = array_values(array_unique($links));

        if (empty($links)) {
            echo "Keine relevanten Links auf {$url} gefunden.\n";
            continue;
        }

        foreach ($links as $link) {
            if (!linkExists($selectStmt, $link)) {
                try {
                    $insertStmt->execute([':link' => $link]);
                    $insertedCount++;
                    echo "Neuer Link eingefügt: {$link}\n";
                } catch (PDOException $e) {
                    fwrite(STDERR, "[Fehler] Konnte Link {$link} nicht speichern: {$e->getMessage()}\n");
                }
            }
        }
    }

    if ($insertedCount > 0) {
        echo "{$insertedCount} neue Links eingefügt.\n";
    } else {
        echo "Keine neuen Links gefunden.\n";
    }
}

/**
 * Erstellt den PDO-DSN aus Umgebungsvariablen.
 */
function buildDsn(): string
{
    $host = getenv('DB_HOST');
    $dbName = getenv('DB_NAME');

    if (!$host || !$dbName) {
        throw new RuntimeException('DB_HOST und DB_NAME müssen gesetzt sein.');
    }

    return sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);
}

/**
 * Lädt HTML-Inhalt per cURL.
 */
function fetchHtml(string $url): ?string
{
    $ch = curl_init($url);

    if ($ch === false) {
        fwrite(STDERR, "[Warnung] Konnte cURL für {$url} nicht initialisieren.\n");
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Gewinne2Crawler/1.0',
    ]);

    $result = curl_exec($ch);

    if ($result === false) {
        fwrite(STDERR, sprintf('[Warnung] HTTP-Request zu %s fehlgeschlagen: %s%s', $url, curl_error($ch), PHP_EOL));
        curl_close($ch);
        return null;
    }

    $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($statusCode >= 400) {
        fwrite(STDERR, "[Warnung] {$url} lieferte HTTP-Status {$statusCode}.\n");
        return null;
    }

    return $result;
}

/**
 * Sucht Gewinnspiel-Links in einem HTML-Dokument.
 */
function parsePrizeLinks(string $html, string $baseUrl): array
{
    $links = [];
    $doc = new DOMDocument();

    libxml_use_internal_errors(true);
    if (!$doc->loadHTML($html)) {
        fwrite(STDERR, "[Warnung] Konnte HTML von {$baseUrl} nicht parsen.\n");
        libxml_clear_errors();
        return $links;
    }
    libxml_clear_errors();

    foreach ($doc->getElementsByTagName('a') as $anchor) {
        $href = $anchor->getAttribute('href');
        if ($href === '') {
            continue;
        }

        $absoluteUrl = toAbsoluteUrl($href, $baseUrl);

        if ($absoluteUrl === null) {
            continue;
        }

        if (isPrizeLink($absoluteUrl)) {
            $links[] = $absoluteUrl;
        }
    }

    return $links;
}

/**
 * Prüft, ob ein Link bereits existiert.
 */
function linkExists(PDOStatement $statement, string $link): bool
{
    $statement->execute([':link' => $link]);
    return (bool) $statement->fetchColumn();
}

/**
 * Konvertiert relative URLs in absolute URLs.
 */
function toAbsoluteUrl(string $href, string $baseUrl): ?string
{
    if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
        return normalizeUrl($href);
    }

    if (str_starts_with($href, '//')) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        return normalizeUrl($scheme . ':' . $href);
    }

    if (str_starts_with($href, '#')) {
        return null;
    }

    $baseParts = parse_url($baseUrl);
    if ($baseParts === false || !isset($baseParts['scheme'], $baseParts['host'])) {
        return null;
    }

    $path = $baseParts['path'] ?? '/';
    $path = preg_replace('#/[^/]*$#', '/', $path);
    $absolutePath = $path . ltrim($href, '/');
    $url = sprintf('%s://%s%s', $baseParts['scheme'], $baseParts['host'], '/' . ltrim($absolutePath, '/'));

    return normalizeUrl($url);
}

/**
 * Normalisiert URLs.
 */
function normalizeUrl(string $url): ?string
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return null;
    }

    $parsed = parse_url($trimmed);
    if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
        return null;
    }

    if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
        return null;
    }

    return $trimmed;
}

/**
 * Prüft, ob eine URL wahrscheinlich ein Gewinnspiel enthält.
 */
function isPrizeLink(string $url): bool
{
    $pattern = '/(gewinn|gewinnspiel|gewinnspiele|aktion|gewinner|lotterie)/i';
    return (bool) preg_match($pattern, $url);
}

main();
