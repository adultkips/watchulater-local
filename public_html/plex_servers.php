<?php
require __DIR__ . '/../db/connect.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$stmt = $pdo->query("SELECT plex_token, plex_client_id FROM settings WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $row['plex_token'] ?? '';
$clientId = $row['plex_client_id'] ?? '';

if (!$clientId) {
    $clientId = bin2hex(random_bytes(16));
    $upd = $pdo->prepare("UPDATE settings SET plex_client_id = ?, updated_at = datetime('now') WHERE id = 1");
    $upd->execute([$clientId]);
}

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_token']);
    exit;
}

$ca = $cfg['ca_cert'] ?? '';
$caReal = $ca ? realpath($ca) : false;
$insecure = !empty($cfg['tls_insecure']);

$url = 'https://plex.tv/api/v2/resources?includeHttps=1&includeRelay=1&X-Plex-Token=' . rawurlencode($token);
$ch = curl_init($url);
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

curl_setopt_array($ch, $opts);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false || $http >= 400) {
    http_response_code(502);
    echo json_encode(['error' => 'server_fetch_failed', 'detail' => $err ?: $resp]);
    exit;
}

$data = json_decode($resp, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['error' => 'invalid_response']);
    exit;
}

$servers = [];
foreach ($data as $item) {
    if (($item['provides'] ?? '') !== 'server') continue;
    $connections = $item['connections'] ?? [];
    $best = null;
    foreach ($connections as $c) {
        if (!empty($c['uri'])) { $best = $c['uri']; break; }
    }
    $servers[] = [
        'name' => $item['name'] ?? 'Plex Server',
        'id' => $item['clientIdentifier'] ?? '',
        'uri' => $best,
        'owned' => $item['owned'] ?? false,
    ];
}

echo json_encode(['servers' => $servers], JSON_UNESCAPED_UNICODE);
