<?php
declare(strict_types=1);

// Erhöht die maximale Laufzeit – verhindert Abbruch nach ca. 120 Sekunden
ini_set('max_execution_time', 0);  // kein Zeitlimit
set_time_limit(0);                 // unendlich lange erlaubt

// Hinweis: Dieses Skript kann z. B. über die Windows Aufgabenplanung alle 10 Minuten
// mit "php.exe C:\\xampp\\htdocs\\Gewinne2\\crawl_gewinnspiele.php" ausgeführt werden.

// Konfiguration für XAMPP (lokal anpassbar)
$dbHost = 'localhost';
$dbName = 'gewinnspiele_db';
$dbUser = 'root';
$dbPass = '';

$portale = [
    'einfach-sparsam'    => 'https://www.einfach-sparsam.de/gewinnspiele?page=1&id=76468&',
    'gewinnspiele-markt' => 'https://www.gewinnspiele-markt.de/gewinnspiel-gratis-gara-00.html',
    'gewinnspiel.de'     => 'https://www.gewinnspiel.de',
    '12gewinn'           => 'https://www.12gewinn.de',
    'supergewinne'       => 'https://www.supergewinne.de',
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
        'portal_links'           => 0,
        'valid_portal_contests'  => 0,
        'teilnahme_links_found'  => 0,
        'saved'                  => 0,
        'discarded'              => 0,
    ];

    foreach ($portale as $portalName => $portalUrl) {
        echo '<h3>Portal: ' . htmlspecialchars($portalName) . ' (' . htmlspecialchars($portalUrl) . ')</h3>';

        if ($portalName === 'supergewinne') {
            $listPages = getAllSupergewinneListPages($portalUrl);
        } else {
            $listPages = [$portalUrl];
        }

        $contestLinks = [];
        foreach ($listPages as $listPageUrl) {
            if ($portalName === 'supergewinne') {
                $contestLinks = array_merge($contestLinks, findSupergewinneDetailLinks($listPageUrl));
            } else {
                $contestLinks = array_merge($contestLinks, findContestLinksOnPortal($listPageUrl));
            }
        }
        $contestLinks = array_values(array_unique($contestLinks));
        $portalLinkCount = count($contestLinks);
        $totals['portal_links'] += $portalLinkCount;

        $validPortalCount = 0;
        $teilnahmeFoundCount = 0;
        $savedCount = 0;
        $discardedCount = 0;

        foreach ($contestLinks as $portalContestUrl) {
            if (isJunkUrl($portalContestUrl)) {
                continue;
            }

            $analysis = analyzePortalContestPage($portalContestUrl);
            if ($analysis === null) {
                continue;
            }

            $validPortalCount++;

            if ($portalName === 'supergewinne') {
                $finalUrl = findFinalContestUrlForSupergewinne($portalContestUrl);
            } elseif ($portalName === '12gewinn') {
                $finalUrl = findFinalContestUrlFor12Gewinn($portalContestUrl);
            } else {
                $finalUrl = findJetztTeilnehmenLink($portalContestUrl);
            }
            if ($finalUrl === null) {
                continue;
            }

            $teilnahmeFoundCount++;

            if (isJunkUrl($finalUrl)) {
                continue;
            }

            if (saveLinkIfNew($pdo, $finalUrl, $analysis)) {
                $savedCount++;
            }
        }

        $discardedCount = max(0, $portalLinkCount - $savedCount);

        $totals['valid_portal_contests'] += $validPortalCount;
        $totals['teilnahme_links_found'] += $teilnahmeFoundCount;
        $totals['saved'] += $savedCount;
        $totals['discarded'] += $discardedCount;

        echo '<p>Gefundene Portal-Gewinnspielseiten: ' . $portalLinkCount . '</p>';
        echo '<p>Davon als Gewinnspiele erkannt (mit Datum &amp; Gewinn): ' . $validPortalCount . '</p>';
        echo '<p>Ermittelte externe Teilnahme-Links: ' . $teilnahmeFoundCount . '</p>';
        echo '<p>Davon gespeichert: ' . $savedCount . '</p>';
        echo '<p>Verworfene Einträge: ' . $discardedCount . '</p>';
    }

    echo '<h2>Gesamtübersicht</h2>';
    echo '<p>Portal-Detailseiten gesamt: ' . $totals['portal_links'] . '</p>';
    echo '<p>Davon als Gewinnspiele erkannt: ' . $totals['valid_portal_contests'] . '</p>';
    echo '<p>"Jetzt teilnehmen"-Links gefunden: ' . $totals['teilnahme_links_found'] . '</p>';
    echo '<p>Gespeicherte Gewinnspiele (mit Enddatum &amp; Preis): ' . $totals['saved'] . '</p>';
    echo '<p>Verworfene Einträge gesamt: ' . $totals['discarded'] . '</p>';
    echo '<p>Fertig. Insgesamt ' . $totals['saved'] . ' neue Gewinnspiele gespeichert.</p>';
    echo '<h2>Scan abgeschlossen – alle Gewinnspiele wurden verarbeitet.</h2>';
    echo '<p>Dieses Skript ist bereit, per Cronjob / Aufgabenplanung alle 10 Minuten ausgeführt zu werden.</p>';
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

/**
 * Sammelt alle Gewinnspiel-Listen-Seiten auf supergewinne.de
 * (Startseite, Tabs, Paginierung).
 */
function getAllSupergewinneListPages(string $startUrl): array
{
    $toVisit = [$startUrl];
    $visited = [];
    $result  = [];

    while ($toVisit) {
        $url = array_pop($toVisit);
        if (isset($visited[$url])) {
            continue;
        }
        $visited[$url] = true;

        $html = fetchHtml($url);
        if ($html === null) {
            continue;
        }

        $result[] = $url;

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//a[@href]') as $a) {
            $href = $a->getAttribute('href');
            $fullUrl = normalizeUrl($href, $url);
            if (!$fullUrl) {
                continue;
            }

            $host = parse_url($fullUrl, PHP_URL_HOST);
            if (!$host || stripos($host, 'supergewinne.de') === false) {
                continue;
            }

            $text = trim($a->textContent ?? '');
            $haystack = mb_strtolower($fullUrl . ' ' . $text, 'UTF-8');

            if (
                mb_strpos($haystack, 'gewinnspiel') !== false ||
                mb_strpos($haystack, 'gewinnspiele') !== false ||
                mb_strpos($haystack, 'seite') !== false ||
                mb_strpos($haystack, 'weiter') !== false
            ) {
                if (!isset($visited[$fullUrl])) {
                    $toVisit[] = $fullUrl;
                }
            }
        }
    }

    return array_values(array_unique($result));
}

/**
 * Findet auf einer Supergewinne-Listen-Seite alle Detailseiten-Links ("mehr lesen").
 */
function findSupergewinneDetailLinks(string $listPageUrl): array
{
    $html = fetchHtml($listPageUrl);
    if ($html === null) {
        return [];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $links = [];

    foreach ($xpath->query('//a[@href]') as $a) {
        $text = trim($a->textContent ?? '');
        $textLower = mb_strtolower($text, 'UTF-8');

        if (mb_strpos($textLower, 'mehr lesen') !== false) {
            $href = $a->getAttribute('href');
            $fullUrl = normalizeUrl($href, $listPageUrl);
            if ($fullUrl) {
                $links[] = $fullUrl;
            }
        }
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

function analyzePortalContestPage(string $portalContestUrl): ?array
{
    $html = fetchHtml($portalContestUrl);
    if ($html === null) {
        return null;
    }

    $text = strip_tags($html);
    $textLower = mb_strtolower($text, 'UTF-8');

    $keywords = [
        'gewinn', 'gewinnen', 'gewinnspiel', 'verlosung',
        'preis', 'preise', 'hauptpreis',
        'zu gewinnen', 'wir verlosen', 'chance auf',
        'gutschein', 'reise', 'reisegutschein', 'auto', 'jackpot'
    ];

    $hasPrize = false;
    foreach ($keywords as $kw) {
        if (mb_strpos($textLower, $kw) !== false) {
            $hasPrize = true;
            break;
        }
    }

    if (!$hasPrize) {
        return null;
    }

    if (!preg_match('~(\d{1,2}\.\d{1,2}\.\d{2,4})~', $textLower, $m)) {
        return null;
    }

    $dateStr = $m[1];
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

/**
 * Supergewinne.de:
 * Findet auf einer Detailseite den Button/Link "Jetzt direkt mitmachen!"
 * und gibt dessen URL zurück.
 */
function findFinalContestUrlForSupergewinne(string $portalContestUrl): ?string
{
    $html = fetchHtml($portalContestUrl);
    if ($html === null) {
        return null;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//a[@href]') as $a) {
        $text = trim($a->textContent ?? '');
        $textLower = mb_strtolower($text, 'UTF-8');

        if (mb_strpos($textLower, 'jetzt direkt mitmachen') !== false) {
            $href = $a->getAttribute('href');
            $fullUrl = normalizeUrl($href, $portalContestUrl);
            if ($fullUrl) {
                return $fullUrl;
            }
        }
    }

    return null;
}

function findJetztTeilnehmenLink(string $portalContestUrl): ?string
{
    $html = fetchHtml($portalContestUrl);
    if ($html === null) {
        return null;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//a[@href]');
    if ($nodes === false) {
        return null;
    }

    foreach ($nodes as $a) {
        $href = $a->getAttribute('href');
        $text = trim($a->textContent ?? '');
        $textLower = mb_strtolower($text, 'UTF-8');

        if (
            mb_strpos($textLower, 'jetzt teilnehmen') !== false ||
            mb_strpos($textLower, 'zur teilnahme') !== false ||
            mb_strpos($textLower, 'teilnehmen') !== false
        ) {
            $fullUrl = normalizeUrl($href, $portalContestUrl);
            if ($fullUrl) {
                return $fullUrl;
            }
        }
    }

    return null;
}

/**
 * 12gewinn.de Spezial:
 * - Auf der Portal-Detailseite den ersten "Zum Gewinnspiel"-Link finden
 * - Diese URL laden (Gewinner-Mail-Popup / Zwischenseite)
 * - Dort erneut einen "zum Gewinnspiel"-Link finden
 * - Rückgabe: finale externe Gewinnspiel-URL oder null
 */
function findFinalContestUrlFor12Gewinn(string $portalContestUrl): ?string
{
    $html1 = fetchHtml($portalContestUrl);
    if ($html1 === null) {
        return null;
    }

    $dom1 = new DOMDocument();
    @$dom1->loadHTML($html1);
    $xpath1 = new DOMXPath($dom1);

    $firstUrl = null;

    foreach ($xpath1->query('//a[@href]') as $a) {
        $text = trim($a->textContent ?? '');
        $textLower = mb_strtolower($text, 'UTF-8');

        if (mb_strpos($textLower, 'zum gewinnspiel') !== false) {
            $href = $a->getAttribute('href');
            $firstUrl = normalizeUrl($href, $portalContestUrl);
            if ($firstUrl) {
                break;
            }
        }
    }

    if ($firstUrl === null) {
        return null;
    }

    $html2 = fetchHtml($firstUrl);
    if ($html2 === null) {
        return $firstUrl;
    }

    $dom2 = new DOMDocument();
    @$dom2->loadHTML($html2);
    $xpath2 = new DOMXPath($dom2);

    $finalUrl = null;

    foreach ($xpath2->query('//a[@href]') as $a) {
        $text = trim($a->textContent ?? '');
        $textLower = mb_strtolower($text, 'UTF-8');

        if (mb_strpos($textLower, 'zum gewinnspiel') !== false) {
            $href = $a->getAttribute('href');
            $candidate = normalizeUrl($href, $firstUrl);
            if ($candidate) {
                $finalUrl = $candidate;
                break;
            }
        }
    }

    return $finalUrl ?? $firstUrl;
}
