<?php
require __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/csrf.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_csrf();

function ensure_server_token_column(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'plex_server_token') {
            return;
        }
    }
    $pdo->exec("ALTER TABLE settings ADD COLUMN plex_server_token TEXT");
}

function ensure_client_id(PDO $pdo): string {
    $row = $pdo->query("SELECT plex_client_id FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $clientId = $row['plex_client_id'] ?? '';
    if (!$clientId) {
        $clientId = bin2hex(random_bytes(16));
        $upd = $pdo->prepare("UPDATE settings SET plex_client_id = ?, updated_at = datetime('now') WHERE id = 1");
        $upd->execute([$clientId]);
    }
    return $clientId;
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
    if ($isLocal) $score = 10;
    if ($isRelay) $score = 90;
    if ($isPlexDirect) $score = 60;

    if ($host && filter_var($host, FILTER_VALIDATE_IP)) {
        if (is_private_ip($host)) $score = 5;
    }

    if ($scheme === 'https') $score -= 1;
    return $score;
}

function test_sections(string $baseUrl, string $token, array $opts): bool {
    $base = rtrim($baseUrl, '/');
    $url = $base . '/library/sections?X-Plex-Token=' . rawurlencode($token);
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $resp !== false && $http > 0 && $http < 400;
}

ensure_server_token_column($pdo);

$input = json_decode(file_get_contents('php://input'), true);
$id = trim($input['id'] ?? '');
$uri = trim($input['uri'] ?? '');
$name = trim($input['name'] ?? '');

if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_id']);
    exit;
}

$row = $pdo->query("SELECT plex_token FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$token = $row['plex_token'] ?? '';
if ($token === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_token']);
    exit;
}

$clientId = ensure_client_id($pdo);

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
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 5,
];
if ($caReal && file_exists($caReal)) {
    $opts[CURLOPT_CAINFO] = $caReal;
}
if ($insecure) {
    $opts[CURLOPT_SSL_VERIFYPEER] = false;
    $opts[CURLOPT_SSL_VERIFYHOST] = 0;
}

$bestUri = $uri;
$serverToken = null;

$resourceUrl = 'https://plex.tv/api/v2/resources?includeHttps=1&includeRelay=1&X-Plex-Token=' . rawurlencode($token);
$rch = curl_init($resourceUrl);
curl_setopt_array($rch, $opts);
$rresp = curl_exec($rch);
$rhttp = curl_getinfo($rch, CURLINFO_HTTP_CODE);
$rerr  = curl_error($rch);
curl_close($rch);

if ($rresp !== false && $rhttp < 400) {
    $resources = json_decode($rresp, true);
    if (is_array($resources)) {
        foreach ($resources as $item) {
            if (($item['clientIdentifier'] ?? '') !== $id) continue;
            $serverToken = $item['accessToken'] ?? null;
            $connections = $item['connections'] ?? [];
            if (!is_array($connections) || empty($connections)) break;

            usort($connections, function ($a, $b) {
                return rank_connection($a) <=> rank_connection($b);
            });

            foreach ($connections as $conn) {
                $candidate = $conn['uri'] ?? '';
                if (!$candidate) continue;
                if ($bestUri === '') {
                    $bestUri = $candidate;
                }
                if ($serverToken && test_sections($candidate, $serverToken, $opts)) {
                    $bestUri = $candidate;
                    break;
                }
            }
            break;
        }
    }
}

$upd = $pdo->prepare("UPDATE settings SET plex_server_id = ?, plex_server_url = ?, plex_server_name = ?, plex_server_token = ?, plex_server_token_manual = 0, plex_server_url_manual = 0, onboarded = 1, updated_at = datetime('now') WHERE id = 1");
$upd->execute([$id, $bestUri, $name, $serverToken]);

echo json_encode([
    'status' => 'saved',
    'server_url' => $bestUri,
    'server_token' => $serverToken ? 'stored' : null,
    'resource_error' => $rerr ?: null
], JSON_UNESCAPED_UNICODE);
