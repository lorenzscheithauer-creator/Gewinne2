<?php
// Hinweis: Dieses Skript kann z. B. über die Windows Aufgabenplanung alle 10 Minuten
// mit "php.exe C:\\xampp\\htdocs\\Gewinne2\\crawl_gewinnspiele.php" ausgeführt werden.

declare(strict_types=1);

// Konfiguration für XAMPP (lokal anpassbar)
$dbHost = 'localhost';
$dbName = 'gewinnspiele_db';
$dbUser = 'root';
$dbPass = '';

$seiten = [
    'https://12gewinne.de',
    'https://supergewinne.de',
    'https://www.gewinn-portal.de',
];

main($dbHost, $dbName, $dbUser, $dbPass, $seiten);

function main(string $host, string $dbName, string $user, string $pass, array $seiten): void
{
    echo "<!DOCTYPE html><html lang=\"de\"><head><meta charset=\"utf-8\"><title>Gewinnspiel-Crawler</title></head><body><pre>";
    echo "Starte Scan...\n\n";

    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        echo 'Datenbankverbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage()) . "\n";
        echo '</pre></body></html>';
        return;
    }

    $totalInserted = 0;

    foreach ($seiten as $seite) {
        echo 'Seite: ' . $seite . "\n";
        $html = fetchHtml($seite);

        if ($html === null) {
            echo "  Fehler beim Abrufen. Überspringe die Seite.\n\n";
            continue;
        }

        $links = extractLinks($html, $seite);
        $foundCount = count($links);
        $newCount = 0;

        foreach ($links as $link) {
            if (saveLinkIfNew($pdo, $link)) {
                $newCount++;
            }
        }

        echo sprintf("  %d Links gefunden, %d neu eingefügt\n\n", $foundCount, $newCount);
        $totalInserted += $newCount;
    }

    echo 'Fertig. Insgesamt ' . $totalInserted . ' neue Links gespeichert.' . "\n";
    echo '</pre></body></html>';
}

function fetchHtml(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Gewinne2Crawler/1.0',
                'Accept: text/html,application/xhtml+xml',
            ],
            'timeout' => 15,
        ],
        'https' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Gewinne2Crawler/1.0',
                'Accept: text/html,application/xhtml+xml',
            ],
            'timeout' => 15,
        ],
    ]);

    $html = @file_get_contents($url, false, $context);

    if ($html === false) {
        $error = error_get_last()['message'] ?? 'Unbekannter Fehler';
        echo '  HTTP-Fehler: ' . $error . "\n";
        return null;
    }

    global $http_response_header;
    if (isset($http_response_header[0]) && preg_match('/HTTP\/(?:1\.[01]|2) (\d{3})/', $http_response_header[0], $matches)) {
        $status = (int) $matches[1];
        if ($status >= 400) {
            echo '  HTTP-Status ' . $status . ' – Seite wird übersprungen.' . "\n";
            return null;
        }
    }

    return $html;
}

function extractLinks(string $html, string $baseUrl): array
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $loaded = $doc->loadHTML($html);
    libxml_clear_errors();

    if (!$loaded) {
        echo '  HTML konnte nicht verarbeitet werden.' . "\n";
        return [];
    }

    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query('//a[@href]');
    if ($nodes === false) {
        return [];
    }

    $links = [];

    foreach ($nodes as $node) {
        $href = $node->getAttribute('href');
        $normalized = normalizeUrl($href, $baseUrl);

        if ($normalized === null) {
            continue;
        }

        if (!containsKeyword($normalized)) {
            continue;
        }

        $links[] = $normalized;
    }

    return array_values(array_unique($links));
}

function normalizeUrl(string $href, string $baseUrl): ?string
{
    $href = trim($href);

    if ($href === '' || $href === '#') {
        return null;
    }

    if (preg_match('/^(mailto:|tel:|javascript:)/i', $href)) {
        return null;
    }

    $fragmentPos = strpos($href, '#');
    if ($fragmentPos !== false) {
        $href = substr($href, 0, $fragmentPos);
    }

    if (preg_match('#^https?://#i', $href)) {
        return filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
    }

    if (str_starts_with($href, '//')) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $candidate = $scheme . ':' . $href;
        return filter_var($candidate, FILTER_VALIDATE_URL) ? $candidate : null;
    }

    $baseParts = parse_url($baseUrl);
    if ($baseParts === false || !isset($baseParts['scheme'], $baseParts['host'])) {
        return null;
    }

    $scheme = $baseParts['scheme'];
    $host = $baseParts['host'];
    $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
    $basePath = $baseParts['path'] ?? '/';

    if (str_starts_with($href, '/')) {
        $path = $href;
    } elseif (str_starts_with($href, '?')) {
        $path = ($basePath ?: '/') . $href;
    } else {
        $dir = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';
        $path = $dir . $href;
    }

    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    $normalizedPath = '/' . implode('/', $segments);
    if ($normalizedPath === '/') {
        $normalizedPath = '/';
    }

    $candidate = $scheme . '://' . $host . $port . $normalizedPath;
    return filter_var($candidate, FILTER_VALIDATE_URL) ? $candidate : null;
}

function containsKeyword(string $url): bool
{
    return (bool) preg_match('/(gewinn|gewinnspiel|gewinnspiele|aktion)/i', $url);
}

function saveLinkIfNew(PDO $pdo, string $link): bool
{
    static $selectStmt = null;
    static $insertStmt = null;

    if ($selectStmt === null) {
        $selectStmt = $pdo->prepare('SELECT id FROM gewinnspiele WHERE link_zur_webseite = :link');
    }

    if ($insertStmt === null) {
        $insertStmt = $pdo->prepare('INSERT INTO gewinnspiele (link_zur_webseite, beschreibung) VALUES (:link, NULL)');
    }

    $selectStmt->execute([':link' => $link]);
    if ($selectStmt->fetch()) {
        return false;
    }

    $insertStmt->execute([':link' => $link]);
    return true;
}
