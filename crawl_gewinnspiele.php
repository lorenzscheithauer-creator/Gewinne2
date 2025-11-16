<?php
// Hinweis: Dieses Skript kann z. B. über die Windows Aufgabenplanung alle 10 Minuten
// mit "php.exe C:\\xampp\\htdocs\\Gewinne2\\crawl_gewinnspiele.php" ausgeführt werden.

declare(strict_types=1);

// Konfiguration für XAMPP (lokal anpassbar)
$dbHost = 'localhost';
$dbName = 'gewinnspiele_db';
$dbUser = 'root';
$dbPass = '';

$portale = [
    'einfach-sparsam'    => 'https://www.einfach-sparsam.de/gewinnspiele?page=1&id=76468&',
    'gewinnspiele-markt' => 'https://www.gewinnspiele-markt.de/gewinnspiel-gratis-gara-00.html',
    'gewinnspiel.de'     => 'https://www.gewinnspiel.de',
];

main($dbHost, $dbName, $dbUser, $dbPass, $portale);

function main(string $host, string $dbName, string $user, string $pass, array $portale): void
{
    echo "<!DOCTYPE html><html lang=\"de\"><head><meta charset=\"utf-8\"><title>Gewinnspiel-Crawler</title></head><body>";
    echo '<h1>Gewinnspiel-Crawler</h1>';
    echo '<p>Starte Scan...</p>';

    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        echo '<p>Datenbankverbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</body></html>';
        return;
    }

    $totals = [
        'portal_links'   => 0,
        'external_pages' => 0,
        'saved'          => 0,
        'invalid'        => 0,
    ];

    foreach ($portale as $portalName => $portalUrl) {
        echo '<h3>Portal: ' . htmlspecialchars($portalName) . ' (' . htmlspecialchars($portalUrl) . ')</h3>';

        $contestLinks = findContestLinksOnPortal($portalUrl);
        $portalLinkCount = count($contestLinks);
        $totals['portal_links'] += $portalLinkCount;

        $finalPages = [];
        foreach ($contestLinks as $portalContestUrl) {
            $finalUrl = findExternalContestPage($portalContestUrl) ?? $portalContestUrl;
            if ($finalUrl !== null) {
                $finalPages[] = $finalUrl;
            }
        }

        $uniqueFinalPages = array_values(array_unique($finalPages));
        $externalCount = count($uniqueFinalPages);
        $totals['external_pages'] += $externalCount;

        $savedCount = 0;
        $invalidCount = 0;

        foreach ($uniqueFinalPages as $finalUrl) {
            if (isJunkUrl($finalUrl)) {
                $invalidCount++;
                continue;
            }

            $analysis = analyzeContestPage($finalUrl);
            if ($analysis === null) {
                $invalidCount++;
                continue;
            }

            if (saveLinkIfNew($pdo, $finalUrl, $analysis)) {
                $savedCount++;
            }
        }

        $totals['saved'] += $savedCount;
        $totals['invalid'] += $invalidCount;

        echo '<p>Portal-Gewinnspiel-Links: ' . $portalLinkCount . '</p>';
        echo '<p>Direkte Gewinnspiel-Seiten geprüft: ' . $externalCount . '</p>';
        echo '<p>Davon gespeichert (mit Enddatum &amp; Preis): ' . $savedCount . '</p>';
        echo '<p>Verworfen: ' . $invalidCount . '</p>';
    }

    echo '<h2>Gesamtübersicht</h2>';
    echo '<p>Fertig. Insgesamt ' . $totals['saved'] . ' neue Gewinnspiele gespeichert.</p>';
    echo '<p>Portal-Gewinnspiel-Links gesamt: ' . $totals['portal_links'] . '</p>';
    echo '<p>Direkte Gewinnspiel-Seiten geprüft: ' . $totals['external_pages'] . '</p>';
    echo '<p>Gespeicherte Gewinnspiele (mit Enddatum &amp; Preis): ' . $totals['saved'] . '</p>';
    echo '<p>Verworfen (kein Datum/Gewinn oder Junk): ' . $totals['invalid'] . '</p>';
    echo '</body></html>';
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
        echo '<p>HTTP-Fehler: ' . htmlspecialchars($error) . '</p>';
        return null;
    }

    global $http_response_header;
    if (isset($http_response_header[0]) && preg_match('/HTTP\/(?:1\.[01]|2) (\d{3})/', $http_response_header[0], $matches)) {
        $status = (int) $matches[1];
        if ($status >= 400) {
            echo '<p>HTTP-Status ' . $status . ' – Seite wird übersprungen.</p>';
            return null;
        }
    }

    return $html;
}

function findContestLinksOnPortal(string $portalUrl): array
{
    $html = fetchHtml($portalUrl);
    if ($html === null) {
        return [];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//a[@href]');
    if ($nodes === false) {
        return [];
    }

    $links = [];

    foreach ($nodes as $node) {
        $href = $node->getAttribute('href');
        $fullUrl = normalizeUrl($href, $portalUrl);
        if ($fullUrl === null) {
            continue;
        }

        if (rtrim($fullUrl, '/') === rtrim($portalUrl, '/')) {
            continue;
        }

        $text = trim($node->textContent ?? '');
        $haystack = mb_strtolower($fullUrl . ' ' . $text, 'UTF-8');

        if (
            mb_strpos($haystack, 'gewinn') === false &&
            mb_strpos($haystack, 'gewinnspiel') === false &&
            mb_strpos($haystack, 'verlosung') === false
        ) {
            continue;
        }

        $links[] = $fullUrl;
    }

    return array_values(array_unique($links));
}

function findExternalContestPage(string $portalPageUrl): ?string
{
    $html = fetchHtml($portalPageUrl);
    if ($html === null) {
        return null;
    }

    $portalHost = parse_url($portalPageUrl, PHP_URL_HOST);

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//a[@href]');
    if ($nodes === false) {
        return null;
    }

    foreach ($nodes as $node) {
        $href = $node->getAttribute('href');
        $fullUrl = normalizeUrl($href, $portalPageUrl);
        if ($fullUrl === null) {
            continue;
        }

        $host = parse_url($fullUrl, PHP_URL_HOST);
        if ($host && mb_strtolower($host, 'UTF-8') !== mb_strtolower((string) $portalHost, 'UTF-8')) {
            return $fullUrl;
        }
    }

    return null;
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

function isJunkUrl(string $url): bool
{
    $urlLower = mb_strtolower($url, 'UTF-8');

    if (mb_strpos($urlLower, 'gewinn-portal.de') !== false) {
        return true;
    }

    if (preg_match('~^https?://(www\.)?supergewinne\.de/?$~', $urlLower)) {
        return true;
    }

    if (preg_match('~^https?://(www\.)?x\.com/supergewinne~', $urlLower)) {
        return true;
    }

    if (str_contains($urlLower, '/gewinnspiele/autogewinnspiele')) {
        return true;
    }

    $badParts = [
        '/user/', 'user=',
        'profil', 'profile',
        'login', 'anmeldung',
        'register', 'registrieren',
        'impressum', 'datenschutz', 'privacy',
        'kontakt', 'contact',
    ];

    foreach ($badParts as $part) {
        if (mb_strpos($urlLower, $part) !== false) {
            return true;
        }
    }

    return false;
}

function saveLinkIfNew(PDO $pdo, string $link, array $analysis): bool
{
    static $selectStmt = null;
    static $insertStmt = null;

    if ($selectStmt === null) {
        $selectStmt = $pdo->prepare('SELECT id FROM gewinnspiele WHERE link_zur_webseite = :link');
    }

    if ($insertStmt === null) {
        $insertStmt = $pdo->prepare('
            INSERT INTO gewinnspiele (link_zur_webseite, beschreibung, status, endet_am)
            VALUES (:link, NULL, :status, :endet_am)
        ');
    }

    $selectStmt->execute([':link' => $link]);
    if ($selectStmt->fetch()) {
        return false;
    }

    $insertStmt->execute([
        ':link' => $link,
        ':status' => $analysis['status'],
        ':endet_am' => $analysis['end_date'],
    ]);
    return true;
}

function analyzeContestPage(string $url): ?array
{
    $html = fetchHtml($url);
    if ($html === null) {
        return null;
    }

    $text = strip_tags($html);
    $textLower = mb_strtolower($text, 'UTF-8');

    $keywords = [
        'gewinn', 'gewinnen', 'gewinnspiel', 'verlosung',
        'preis', 'preise', 'hauptpreis',
        'zu gewinnen', 'wir verlosen', 'chance auf',
        'gutschein', 'reise', 'auto', 'jackpot',
        'teilnahmeschluss', 'einsendeschluss'
    ];

    $hasPrize = false;
    foreach ($keywords as $keyword) {
        if (mb_strpos($textLower, $keyword) !== false) {
            $hasPrize = true;
            break;
        }
    }

    if (!$hasPrize) {
        return null;
    }

    if (!preg_match('~(\d{1,2}\.\d{1,2}\.\d{2,4})~', $textLower, $matches)) {
        return null;
    }

    $dateStr = $matches[1];
    $date = DateTime::createFromFormat('d.m.Y', $dateStr)
        ?: DateTime::createFromFormat('d.m.y', $dateStr);

    if (!$date) {
        return null;
    }

    $endDate = $date->setTime(23, 59, 59);
    $today = new DateTime('today');
    $status = ($endDate < $today) ? 'Expired' : 'Active';

    return [
        'status'   => $status,
        'end_date' => $endDate->format('Y-m-d'),
    ];
}
