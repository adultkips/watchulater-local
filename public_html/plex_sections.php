<?php
require __DIR__ . '/../db/connect.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function ensure_server_token_column(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'plex_server_token') {
            return;
        }
    }
    $pdo->exec("ALTER TABLE settings ADD COLUMN plex_server_token TEXT");
}

ensure_server_token_column($pdo);

// Ensure manual URL flag exists
$cols = $pdo->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_ASSOC);
$hasUrlManual = false;
foreach ($cols as $c) {
    if (($c['name'] ?? '') === 'plex_server_url_manual') {
        $hasUrlManual = true;
        break;
    }
}
if (!$hasUrlManual) {
    $pdo->exec("ALTER TABLE settings ADD COLUMN plex_server_url_manual INTEGER NOT NULL DEFAULT 0");
}

$stmt = $pdo->query("SELECT plex_token, plex_client_id, plex_server_id, plex_server_url, plex_server_name, plex_server_token, plex_server_url_manual FROM settings WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $row['plex_token'] ?? '';
$clientId = $row['plex_client_id'] ?? '';
$serverId = $row['plex_server_id'] ?? '';
$serverUrl = $row['plex_server_url'] ?? '';
$serverName = $row['plex_server_name'] ?? '';
$storedServerToken = $row['plex_server_token'] ?? '';
$serverUrlManual = !empty($row['plex_server_url_manual']);

if (!$token || !$serverUrl) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_config']);
    exit;
}

if (!$clientId) {
    $clientId = bin2hex(random_bytes(16));
    $upd = $pdo->prepare("UPDATE settings SET plex_client_id = ?, updated_at = datetime('now') WHERE id = 1");
    $upd->execute([$clientId]);
}

function build_section_urls(string $baseUrl, string $token): array {
    $base = rtrim($baseUrl, '/');
    $url = $base . '/library/sections?X-Plex-Token=' . rawurlencode($token);
    $urls = [$url];
    $host = parse_url($baseUrl, PHP_URL_HOST);
    if ($host) {
        if (preg_match('/^(\\d{1,3}(?:\\.\\d{1,3}){3})\\./', $host, $m)) {
            $ip = $m[1];
            $ipUrl = preg_replace('/https?:\\/\\/[^\\/]+/', 'https://' . $ip . ':32400', $url);
            $urls[] = $ipUrl;
        } elseif (preg_match('/^(\\d{1,3})-(\\d{1,3})-(\\d{1,3})-(\\d{1,3})\\./', $host, $m)) {
            $ip = $m[1] . '.' . $m[2] . '.' . $m[3] . '.' . $m[4];
            $ipUrl = preg_replace('/https?:\\/\\/[^\\/]+/', 'https://' . $ip . ':32400', $url);
            $urls[] = $ipUrl;
        }
    }
    return $urls;
}

$ca = $cfg['ca_cert'] ?? '';
$caReal = $ca ? realpath($ca) : false;
$insecure = !empty($cfg['tls_insecure']);

$serverToken = $storedServerToken ?: $token;
// Prefer server-specific accessToken from Plex resources if available
if ($serverId && !$storedServerToken) {
    $resourceUrl = 'https://plex.tv/api/v2/resources?includeHttps=1&includeRelay=1&X-Plex-Token=' . rawurlencode($token);
    $rch = curl_init($resourceUrl);
    $ropts = [
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
        $ropts[CURLOPT_CAINFO] = $caReal;
    }
    if ($insecure) {
        $ropts[CURLOPT_SSL_VERIFYPEER] = false;
        $ropts[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    curl_setopt_array($rch, $ropts);
    $rresp = curl_exec($rch);
    $rhttp = curl_getinfo($rch, CURLINFO_HTTP_CODE);
    curl_close($rch);
    if ($rresp !== false && $rhttp < 400) {
        $resources = json_decode($rresp, true);
        if (is_array($resources)) {
            foreach ($resources as $item) {
                if (($item['clientIdentifier'] ?? '') !== $serverId) continue;
                if (!empty($item['accessToken'])) {
                    $serverToken = $item['accessToken'];
                }
                break;
            }
        }
    }
}

$urls = build_section_urls($serverUrl, $serverToken);

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
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
];
if ($caReal && file_exists($caReal)) {
    $opts[CURLOPT_CAINFO] = $caReal;
}
if ($insecure) {
    $opts[CURLOPT_SSL_VERIFYPEER] = false;
    $opts[CURLOPT_SSL_VERIFYHOST] = 0;
}

$resp = false;
$http = 0;
$err = '';
$lastUrl = '';

foreach ($urls as $u) {
    $lastUrl = $u;
    $ch = curl_init($u);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp !== false && $http < 400) {
        break;
    }
    if (stripos($u, 'https://') === 0) {
        $httpUrl = 'http://' . substr($u, 8);
        $lastUrl = $httpUrl;
        $ch = curl_init($httpUrl);
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp !== false && $http < 400) {
            break;
        }
    }
}

if ($resp === false || $http >= 400) {
    // Fallback: re-fetch server connections from plex.tv and try each URI
    if ($serverId) {
        $resourceUrl = 'https://plex.tv/api/v2/resources?includeHttps=1&includeRelay=1&X-Plex-Token=' . rawurlencode($token);
        $rch = curl_init($resourceUrl);
        $ropts = $opts;
        curl_setopt_array($rch, $ropts);
        $rresp = curl_exec($rch);
        $rhttp = curl_getinfo($rch, CURLINFO_HTTP_CODE);
        $rerr  = curl_error($rch);
        curl_close($rch);
        if ($rresp !== false && $rhttp < 400) {
            $resources = json_decode($rresp, true);
            if (is_array($resources)) {
                foreach ($resources as $item) {
                    if (($item['clientIdentifier'] ?? '') !== $serverId) continue;
                    if (!empty($item['accessToken'])) {
                        $serverToken = $item['accessToken'];
                    }
                    $connections = $item['connections'] ?? [];
                    foreach ($connections as $c) {
                        $uri = $c['uri'] ?? '';
                        if (!$uri) continue;
                        foreach (build_section_urls($uri, $serverToken) as $u2) {
                            $lastUrl = $u2;
                            $ch = curl_init($u2);
                            curl_setopt_array($ch, $opts);
                            $resp = curl_exec($ch);
                            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $err  = curl_error($ch);
                            curl_close($ch);
                            if ($resp !== false && $http < 400) {
                                // Save working URI (and server token if available) for next time
                                if (!$serverUrlManual) {
                                    $baseUrl = preg_replace('/\\/library\\/sections.*/', '', $u2);
                                    $upd = $pdo->prepare("UPDATE settings SET plex_server_url = ?, plex_server_token = ?, updated_at = datetime('now') WHERE id = 1");
                                    $upd->execute([$baseUrl, $serverToken ?: null]);
                                }
                                break 3;
                            }
                        }
                    }
                }
            } else {
                $err = $rerr ?: 'resources_parse_failed';
            }
        } else {
            $err = $rerr ?: 'resources_fetch_failed';
        }
    }
}

if ($resp === false || $http >= 400) {
    http_response_code(502);
    echo json_encode([
        'error' => 'server_unreachable',
        'detail' => $err ?: $resp,
        'attempted' => $lastUrl
    ]);
    exit;
}

$sections = [];
$data = json_decode($resp, true);
if (is_array($data)) {
    $dirs = $data['MediaContainer']['Directory'] ?? [];
    if (is_array($dirs)) {
        foreach ($dirs as $d) {
            $sections[] = [
                'key' => $d['key'] ?? '',
                'title' => $d['title'] ?? '',
                'type' => $d['type'] ?? ''
            ];
        }
    }
} else {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($resp);
    if ($xml === false) {
        http_response_code(502);
        echo json_encode([
            'error' => 'invalid_response',
            'detail' => substr($resp, 0, 200)
        ]);
        exit;
    }
    foreach ($xml->Directory as $dir) {
        $attrs = $dir->attributes();
        $sections[] = [
            'key' => (string)($attrs['key'] ?? ''),
            'title' => (string)($attrs['title'] ?? ''),
            'type' => (string)($attrs['type'] ?? '')
        ];
    }
}

echo json_encode([
    'connected' => true,
    'server_name' => $serverName,
    'sections' => $sections
], JSON_UNESCAPED_UNICODE);
