<?php
require __DIR__ . '/../db/connect.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function ensure_server_token_columns(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_ASSOC);
    $hasToken = false;
    $hasManual = false;
    $hasUrlManual = false;
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'plex_server_token') $hasToken = true;
        if (($c['name'] ?? '') === 'plex_server_token_manual') $hasManual = true;
        if (($c['name'] ?? '') === 'plex_server_url_manual') $hasUrlManual = true;
    }
    if (!$hasToken) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN plex_server_token TEXT");
    }
    if (!$hasManual) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN plex_server_token_manual INTEGER NOT NULL DEFAULT 0");
    }
    if (!$hasUrlManual) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN plex_server_url_manual INTEGER NOT NULL DEFAULT 0");
    }
}

function is_private_ip(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return false;
    $long = ip2long($ip);
    return ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255'))
        || ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255'))
        || ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255'));
}

function rank_connection(array $conn): int {
    $uri = $conn['uri'] ?? '';
    $host = parse_url($uri, PHP_URL_HOST) ?: '';
    $isRelay = !empty($conn['relay']);
    $isLocal = !empty($conn['local']);
    $isPlexDirect = stripos($host, 'plex.direct') !== false;
    $scheme = parse_url($uri, PHP_URL_SCHEME) ?: '';

    if ($host && preg_match('/^(\\d{1,3})-(\\d{1,3})-(\\d{1,3})-(\\d{1,3})\\./', $host)) {
        $isPlexDirect = true;
    }

    $score = 50;
    if ($isLocal) $score = 80;
    if ($isRelay) $score = 90;
    if ($isPlexDirect) $score = 40;

    if ($host && filter_var($host, FILTER_VALIDATE_IP)) {
        if (is_private_ip($host)) {
            $score = 80;
        } else {
            $score = 20;
        }
    }

    if ($scheme === 'https') $score -= 1;
    return $score;
}

function hostport_from_uri(string $uri): string {
    $parts = parse_url($uri);
    $host = $parts['host'] ?? '';
    $port = $parts['port'] ?? '';
    if ($host && preg_match('/^(\\d{1,3})-(\\d{1,3})-(\\d{1,3})-(\\d{1,3})\\./', $host, $m)) {
        $host = $m[1] . '.' . $m[2] . '.' . $m[3] . '.' . $m[4];
    }
    if (!$port) $port = '32400';
    return $host ? ($host . ':' . $port) : '';
}

ensure_server_token_columns($pdo);

$row = $pdo->query("SELECT plex_token, plex_client_id, plex_server_id, plex_server_url, plex_server_name, plex_server_token, plex_server_token_manual, plex_server_url_manual FROM settings WHERE id = 1")
    ->fetch(PDO::FETCH_ASSOC) ?: [];

$token = $row['plex_token'] ?? '';
$clientId = $row['plex_client_id'] ?? '';
$serverId = $row['plex_server_id'] ?? '';
$serverUrl = $row['plex_server_url'] ?? '';
$serverToken = $row['plex_server_token'] ?? '';
$serverTokenManual = !empty($row['plex_server_token_manual']);
$serverUrlManual = !empty($row['plex_server_url_manual']);

// Treat explicit IP URLs as manual overrides even if flag is missing
$serverHost = $serverUrl ? (parse_url($serverUrl, PHP_URL_HOST) ?: '') : '';
$serverUrlManualEffective = $serverUrlManual || ($serverHost && filter_var($serverHost, FILTER_VALIDATE_IP));

if (!$token || !$serverId) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_config']);
    exit;
}

$ca = $cfg['ca_cert'] ?? '';
$caReal = $ca ? realpath($ca) : false;
$insecure = !empty($cfg['tls_insecure']);

$opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-Plex-Product: Watchulater Local',
        'X-Plex-Client-Identifier: ' . $clientId,
        'X-Plex-Platform: Web',
        'X-Plex-Device: Browser',
        'X-Plex-Version: 1.0'
    ],
    CURLOPT_TIMEOUT => 15,
];
if ($caReal && file_exists($caReal)) {
    $opts[CURLOPT_CAINFO] = $caReal;
}
if ($insecure) {
    $opts[CURLOPT_SSL_VERIFYPEER] = false;
    $opts[CURLOPT_SSL_VERIFYHOST] = 0;
}

$resourceUrl = 'https://plex.tv/api/v2/resources?includeHttps=1&includeRelay=1&X-Plex-Token=' . rawurlencode($token);
$rch = curl_init($resourceUrl);
curl_setopt_array($rch, $opts);
$rresp = curl_exec($rch);
$rhttp = curl_getinfo($rch, CURLINFO_HTTP_CODE);
curl_close($rch);

$displayHostport = $serverUrl ? hostport_from_uri($serverUrl) : '';

if ($rresp !== false && $rhttp < 400) {
    $resources = json_decode($rresp, true);
    if (is_array($resources)) {
        foreach ($resources as $item) {
            if (($item['clientIdentifier'] ?? '') !== $serverId) continue;
            $connections = $item['connections'] ?? [];
            if (is_array($connections) && !empty($connections)) {
                if (!$serverUrlManualEffective) {
                    $bestPublic = null;
                    $bestAny = null;
                    foreach ($connections as $conn) {
                        $uri = $conn['uri'] ?? '';
                        if (!$uri) continue;
                        if ($bestAny === null) $bestAny = $uri;
                        $host = parse_url($uri, PHP_URL_HOST) ?: '';
                        if ($host && preg_match('/^(\\d{1,3})-(\\d{1,3})-(\\d{1,3})-(\\d{1,3})\\./', $host, $m)) {
                            $ip = $m[1] . '.' . $m[2] . '.' . $m[3] . '.' . $m[4];
                            if (!is_private_ip($ip)) {
                                $bestPublic = $uri;
                                break;
                            }
                        }
                    }

                    if ($bestPublic) {
                        $displayHostport = hostport_from_uri($bestPublic);
                    } elseif ($bestAny) {
                        $displayHostport = hostport_from_uri($bestAny);
                    }
                }

                if (!empty($item['accessToken']) && !$serverTokenManual) {
                    $serverToken = $item['accessToken'];
                    $upd = $pdo->prepare("UPDATE settings SET plex_server_token = ?, updated_at = datetime('now') WHERE id = 1");
                    $upd->execute([$serverToken]);
                }
            }
            break;
        }
    }
}

echo json_encode([
    'server_hostport' => $displayHostport,
    'server_token' => $serverToken,
    'server_token_manual' => $serverTokenManual,
    'server_url_manual' => $serverUrlManual,
    'server_id' => $serverId
], JSON_UNESCAPED_UNICODE);
