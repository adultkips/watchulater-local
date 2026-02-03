<?php
require __DIR__ . '/../db/connect.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$stmt = $pdo->query("SELECT plex_token, plex_client_id, plex_server_url, plex_server_token FROM settings WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$token = $row['plex_token'] ?? '';
$clientId = $row['plex_client_id'] ?? '';
$serverUrl = $row['plex_server_url'] ?? '';
$serverToken = $row['plex_server_token'] ?: $token;

if (!$serverUrl || !$serverToken) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_config']);
    exit;
}

if (!$clientId) {
    $clientId = bin2hex(random_bytes(16));
    $upd = $pdo->prepare("UPDATE settings SET plex_client_id = ?, updated_at = datetime('now') WHERE id = 1");
    $upd->execute([$clientId]);
}

$base = rtrim($serverUrl, '/');
$ca = $cfg['ca_cert'] ?? '';
$caReal = $ca ? realpath($ca) : false;
$insecure = !empty($cfg['tls_insecure']);

function curl_json(string $url, array $opts): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $http >= 400) {
        return ['error' => $err ?: $resp, 'http' => $http];
    }
    $data = json_decode($resp, true);
    if (!is_array($data)) {
        return ['error' => 'invalid_response', 'http' => $http];
    }
    return ['data' => $data];
}

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
    CURLOPT_TIMEOUT => 20,
];
if ($caReal && file_exists($caReal)) {
    $opts[CURLOPT_CAINFO] = $caReal;
}
if ($insecure) {
    $opts[CURLOPT_SSL_VERIFYPEER] = false;
    $opts[CURLOPT_SSL_VERIFYHOST] = 0;
}

$sectionsUrl = $base . '/library/sections?X-Plex-Token=' . rawurlencode($serverToken);
$sectionsResp = curl_json($sectionsUrl, $opts);
if (!empty($sectionsResp['error']) && stripos($base, 'https://') === 0) {
    $httpBase = 'http://' . substr($base, 8);
    $sectionsUrl = $httpBase . '/library/sections?X-Plex-Token=' . rawurlencode($serverToken);
    $sectionsResp = curl_json($sectionsUrl, $opts);
    $base = $httpBase;
}

if (!empty($sectionsResp['error'])) {
    http_response_code(502);
    echo json_encode(['error' => 'server_unreachable', 'detail' => $sectionsResp['error']]);
    exit;
}

$dirs = $sectionsResp['data']['MediaContainer']['Directory'] ?? [];
$movieTotal = 0;
$showTotal = 0;
foreach ($dirs as $d) {
    $key = $d['key'] ?? '';
    $type = $d['type'] ?? '';
    if ($key === '' || ($type !== 'movie' && $type !== 'show')) continue;
    $plexType = $type === 'movie' ? 1 : 2;
    $countUrl = $base . '/library/sections/' . rawurlencode($key) . '/all?type=' . $plexType
        . '&X-Plex-Container-Start=0&X-Plex-Container-Size=0'
        . '&X-Plex-Token=' . rawurlencode($serverToken);
    $countResp = curl_json($countUrl, $opts);
    if (!empty($countResp['error'])) {
        continue;
    }
    $totalSize = (int)($countResp['data']['MediaContainer']['totalSize'] ?? 0);
    if ($type === 'movie') $movieTotal += $totalSize;
    if ($type === 'show') $showTotal += $totalSize;
}

echo json_encode([
    'total' => $movieTotal + $showTotal,
    'movies' => $movieTotal,
    'shows' => $showTotal
], JSON_UNESCAPED_UNICODE);
