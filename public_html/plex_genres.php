<?php
require __DIR__ . '/../db/connect.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$input = json_decode(file_get_contents('php://input'), true);
$type = trim($input['type'] ?? '');
$keys = $input['keys'] ?? [];

if (!in_array($type, ['movie','show'], true) || !is_array($keys) || count($keys) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_input']);
    exit;
}

$keys = array_values(array_filter(array_map('trim', $keys), fn($v) => $v !== ''));
if (count($keys) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_keys']);
    exit;
}

$row = $pdo->query("SELECT plex_server_url, plex_server_token, plex_token, plex_client_id FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$serverUrl = rtrim($row['plex_server_url'] ?? '', '/');
$serverToken = $row['plex_server_token'] ?: ($row['plex_token'] ?? '');
$clientId = $row['plex_client_id'] ?? '';
if (!$clientId) {
    $clientId = bin2hex(random_bytes(16));
    $upd = $pdo->prepare("UPDATE settings SET plex_client_id = ?, updated_at = datetime('now') WHERE id = 1");
    $upd->execute([$clientId]);
}

if ($serverUrl === '' || $serverToken === '') {
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

function plex_fetch(string $url, array $opts) {
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $http >= 400) {
        return ['error' => $err ?: $resp];
    }
    $data = json_decode($resp, true);
    if (is_array($data)) return ['data' => $data];
    $xml = @simplexml_load_string($resp);
    if ($xml === false) return ['error' => 'invalid_response'];
    return ['xml' => $xml];
}

$genres = [];
$errors = [];
foreach ($keys as $key) {
    $base = $serverUrl;
    $url = $base . '/library/sections/' . rawurlencode($key) . '/genre?X-Plex-Token=' . rawurlencode($serverToken);
    $resp = plex_fetch($url, $opts);
    if (!empty($resp['error']) && stripos($base, 'https://') === 0) {
        $base = 'http://' . substr($base, 8);
        $url = $base . '/library/sections/' . rawurlencode($key) . '/genre?X-Plex-Token=' . rawurlencode($serverToken);
        $resp = plex_fetch($url, $opts);
    }
    if (!empty($resp['error'])) {
        $errors[] = $resp['error'];
        continue;
    }
    if (!empty($resp['data'])) {
        $dirs = $resp['data']['MediaContainer']['Directory'] ?? [];
        foreach ($dirs as $d) {
            $t = $d['title'] ?? '';
            if ($t !== '') $genres[$t] = true;
        }
    } elseif (!empty($resp['xml'])) {
        foreach ($resp['xml']->Directory as $dir) {
            $attrs = $dir->attributes();
            $t = (string)($attrs['title'] ?? '');
            if ($t !== '') $genres[$t] = true;
        }
    }
}

$list = array_keys($genres);
sort($list, SORT_NATURAL | SORT_FLAG_CASE);

echo json_encode([
    'genres' => $list,
    'errors' => $errors ? array_values(array_unique($errors)) : null
], JSON_UNESCAPED_UNICODE);
