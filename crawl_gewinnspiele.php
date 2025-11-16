<?php
// Hinweis: Dieses Skript kann z. B. über die Windows Aufgabenplanung alle 10 Minuten
// mit "php.exe C:\\xampp\\htdocs\\Gewinne2\\crawl_gewinnspiele.php" ausgeführt werden.

declare(strict_types=1);

// Konfiguration für XAMPP (lokal anpassbar)
$dbHost = 'localhost';
$dbName = 'gewinnspiele_db';
$dbUser = 'root';
$dbPass = '';

$quellen = [
    'Alverde'                 => 'https://www.dm.de/alverde',
    'Amazon'                  => 'https://www.amazon.de',
    'BayWa'                   => 'https://www.baywa.de',
    'Billigflieger'           => 'https://www.billigfluege.de',
    'BMW'                     => 'https://www.bmw.de',
    'Cosmopolitan'            => 'https://www.cosmopolitan.de',
    'DM'                      => 'https://www.dm.de',
    'Ferrero'                 => 'https://www.ferrero.de',
    'Freiberger Pils'         => 'https://www.freiberger.de',
    'Für Sie'                 => 'https://www.fuersie.de',
    'Kinder Bueno'            => 'https://www.ferrero.de/marken/kinder-bueno',
    'Krombacher'              => 'https://www.krombacher.de',
    'Lego'                    => 'https://www.lego.com/de-de',
    'Mercedes'                => 'https://www.mercedes-benz.de',
    'Mini'                    => 'https://www.mini.de',
    'Müller'                  => 'https://www.mueller.de',
    'Netto'                   => 'https://www.netto-online.de',
    'Netto Online'            => 'https://www.netto-online.de',
    'PayPal'                  => 'https://www.paypal.com',
    'Rewe'                    => 'https://www.rewe.de',
    'Rossmann'                => 'https://www.rossmann.de',
    'Staropramen'             => 'https://www.staropramen.com',
    'TUI'                     => 'https://www.tui.com',
    'VW'                      => 'https://www.volkswagen.de',
    'ZDF'                     => 'https://www.zdf.de',
    'Schweizer Käse'          => 'https://www.schweizerkaese.de',
    'Sport1'                  => 'https://www.sport1.de',
    'GewinnArena'             => 'https://www.gewinnarena.de',
    'Elbenwald'               => 'https://www.elbenwald.de',
    'Schöner Wohnen Farbe'    => 'https://www.schoener-wohnen-farbe.de',
    'Hardwaredealz'           => 'https://www.hardwaredealz.com',
    'Fruchtzwerge'            => 'https://www.fruchtzwerge.de',
    'Pixum'                   => 'https://www.pixum.de',
    'Facebook'                => 'https://www.facebook.com',
    'X (Twitter)'             => 'https://www.x.com',
    'Bluesky'                 => 'https://bsky.app',
    'Lidl'                    => 'https://www.lidl.de',
    'Sportschau'              => 'https://www.sportschau.de',
    'Aldi'                    => 'https://www.aldi.de',
    'Rei in der Tube'         => 'https://www.rei.de',
    'Elle'                    => 'https://www.elle.de',
    'Crodino'                 => 'https://www.crodino.com',
    'Edeka'                   => 'https://www.edeka.de',
    'Bergwelten'              => 'https://www.bergwelten.com',
    'EatSmarter'              => 'https://www.eatsmarter.de',
    'Glamour'                 => 'https://www.glamour.de',
    'GQ'                      => 'https://www.gq-magazin.de',
    'Men’s Health'            => 'https://www.menshealth.de',
    'Myself'                  => 'https://www.myself.de',
    'Playmobil'               => 'https://www.playmobil.de',
    'Selbst'                  => 'https://www.selbst.de',
    'Wunderweib'              => 'https://www.wunderweib.de',
    'Grazia'                  => 'https://www.grazia-magazin.de',
    'Baur'                    => 'https://www.baur.de',
    'DocMorris'               => 'https://www.docmorris.de',
    'Temu'                    => 'https://www.temu.com',
    'Cyberport'               => 'https://www.cyberport.de',
    'Allnatura'               => 'https://www.allnatura.de',
    'Erwin Müller'            => 'https://www.erwinmueller.de',
    'ATU'                     => 'https://www.atu.de',
    'CBD Vital'               => 'https://www.cbd-vital.de',
    'Sheego'                  => 'https://www.sheego.de',
    'Heine'                   => 'https://www.heine.de',
    'Tchibo'                  => 'https://www.tchibo.de',
    'Apotheke.com'            => 'https://www.apotheke.com',
    'SHZ'                     => 'https://www.shz.de',
    'Rocketbeans'             => 'https://rocketbeans.tv',
    'Texel'                   => 'https://www.texel.de',
    'BABOR'                   => 'https://www.babor.de',
    'LENTHO'                  => 'https://www.lentho.de',
    'Hanfgarten'              => 'https://www.hanfgarten.at',
    'BurdaDirect'             => 'https://www.burdadirect.de',
    'Hagen Grote'             => 'https://www.hagengrote.de',
    'Visit Czechia'           => 'https://www.visitczechia.com',
    'Notebooksbilliger'       => 'https://www.notebooksbilliger.de',
    'BLICK'                   => 'https://www.blick.ch',
    'Lotto24'                 => 'https://www.lotto24.de',
    'dm Glückskind'           => 'https://www.dm.de/glueckskind',
    'Bebivita'                => 'https://www.bebivita.de',
    'PETA'                    => 'https://www.peta.de',
    'BMBF Forscher'           => 'https://www.forscher-online.de',
    'Vileda'                  => 'https://www.vileda.de',
    'Bens Original'           => 'https://www.bensoriginal.de',
    'Sensodyne'               => 'https://www.sensodyne.de',
    'REWE Testesser'          => 'https://www.rewe.de',
    'trnd'                    => 'https://www.trnd.com',
    'True Motion'             => 'https://www.truemotion.run',
];

main($dbHost, $dbName, $dbUser, $dbPass, $quellen);

function main(string $host, string $dbName, string $user, string $pass, array $quellen): void
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

    $totalInserted = 0;
    $totalJunk = 0;
    $totalNoPrizeText = 0;
    $totalNoDate = 0;
    $totalChecked = 0;

    foreach ($quellen as $name => $startUrl) {
        echo '<h3>Quelle: ' . htmlspecialchars($name) . ' (' . htmlspecialchars($startUrl) . ')</h3>';
        $html = fetchHtml($startUrl);

        if ($html === null) {
            echo '<p>Fehler beim Abrufen. Quelle wird übersprungen.</p>';
            continue;
        }

        $links = extractLinks($html, $startUrl);
        $foundCount = count($links);
        $newCount = 0;
        $junkCount = 0;
        $noPrizeTextCount = 0;
        $noDateCount = 0;

        foreach ($links as $link) {
            if (isJunkUrl($link)) {
                $junkCount++;
                continue;
            }

            $failureReason = null;
            $analysis = analyzeContestPage($link, $failureReason);
            if ($analysis === null) {
                if ($failureReason === 'no_prize_text') {
                    $noPrizeTextCount++;
                } elseif ($failureReason === 'no_date') {
                    $noDateCount++;
                }
                continue;
            }

            if (saveLinkIfNew($pdo, $link, $analysis)) {
                $newCount++;
            }
        }

        $totalJunk += $junkCount;
        $totalNoPrizeText += $noPrizeTextCount;
        $totalNoDate += $noDateCount;
        $totalChecked += $foundCount;

        $discarded = $junkCount + $noPrizeTextCount + $noDateCount;

        echo sprintf(
            '<p>Geprüfte Links: %d, gültige Gewinnspiele mit Enddatum: %d, verworfen: %d (Junk: %d, kein Gewinnspiel-Text: %d, kein Datum: %d)</p>',
            $foundCount,
            $newCount,
            $discarded,
            $junkCount,
            $noPrizeTextCount,
            $noDateCount
        );

        $totalInserted += $newCount;
    }

    $totalDiscarded = $totalJunk + $totalNoPrizeText + $totalNoDate;

    echo '<h2>Gesamtübersicht</h2>';
    echo '<p>Fertig. Insgesamt ' . $totalInserted . ' neue Links gespeichert.</p>';
    echo sprintf(
        '<p>Geprüfte Links gesamt: %d, verworfen: %d (Junk: %d, kein Gewinnspiel-Text: %d, kein Datum: %d)</p>',
        $totalChecked,
        $totalDiscarded,
        $totalJunk,
        $totalNoPrizeText,
        $totalNoDate
    );
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

function extractLinks(string $html, string $baseUrl): array
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $loaded = $doc->loadHTML($html);
    libxml_clear_errors();

    if (!$loaded) {
        echo '<p>HTML konnte nicht verarbeitet werden.</p>';
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

function analyzeContestPage(string $url, ?string &$failureReason = null): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Gewinne2Crawler/1.0',
        ]
    ]);

    $html = @file_get_contents($url, false, $context);
    if ($html === false || trim($html) === '') {
        $failureReason = 'fetch_failed';
        return null;
    }

    $text = strip_tags($html);
    $text = mb_strtolower($text, 'UTF-8');

    $keywords = [
        'gewinn', 'gewinnen', 'gewinnspiel', 'verlosung',
        'preis', 'preise', 'hauptpreis',
        'zu gewinnen', 'wir verlosen', 'wir verlosen', 'chance auf',
        'gutschein', 'reise', 'auto', 'jackpot',
        'teilnahmeschluss', 'einsendeschluss'
    ];

    $hasPrizeWords = false;
    foreach ($keywords as $kw) {
        if (mb_strpos($text, $kw) !== false) {
            $hasPrizeWords = true;
            break;
        }
    }

    if (!$hasPrizeWords) {
        $failureReason = 'no_prize_text';
        return null;
    }

    if (!preg_match('~(\d{1,2}\.\d{1,2}\.\d{2,4})~', $text, $matches)) {
        $failureReason = 'no_date';
        return null;
    }

    $dateStr = $matches[1];
    $date = DateTime::createFromFormat('d.m.Y', $dateStr)
        ?: DateTime::createFromFormat('d.m.y', $dateStr);

    if (!$date) {
        $failureReason = 'no_date';
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
