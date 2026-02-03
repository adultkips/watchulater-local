<?php
require __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/csrf.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_csrf();

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
if ($token === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_token']);
    exit;
}

// Ensure we have a stable client identifier
$stmt = $pdo->query("SELECT plex_client_id FROM settings WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$clientId = $row['plex_client_id'] ?? '';
if (!$clientId) {
    $clientId = bin2hex(random_bytes(16));
    $upd = $pdo->prepare("UPDATE settings SET plex_client_id = ?, updated_at = datetime('now') WHERE id = 1");
    $upd->execute([$clientId]);
}

// Validate token by calling Plex resources endpoint
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
$errNo = curl_errno($ch);
$err  = curl_error($ch);
$verify = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
curl_close($ch);

if ($resp === false && $errNo === 60 && !$insecure) {
    $ch = curl_init($url);
    $retryOpts = $opts;
    unset($retryOpts[CURLOPT_CAINFO]);
    curl_setopt_array($ch, $retryOpts);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo = curl_errno($ch);
    $err  = curl_error($ch);
    $verify = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
    curl_close($ch);
}

if ($resp === false || $http >= 400) {
    http_response_code(400);
    echo json_encode([
        'error' => 'invalid_token',
        'detail' => $err ?: $resp,
        'curl_errno' => $errNo,
        'ssl_verify_result' => $verify,
        'ca_used' => $caReal ?: null,
        'ca_size' => $caReal && file_exists($caReal) ? filesize($caReal) : 0
    ]);
    exit;
}

$data = json_decode($resp, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_token_response']);
    exit;
}

// Save token
$upd = $pdo->prepare("UPDATE settings SET plex_token = ?, updated_at = datetime('now') WHERE id = 1");
$upd->execute([$token]);

echo json_encode(['status' => 'saved']);
