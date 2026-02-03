<?php
require __DIR__ . '/../db/connect.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure we have a stable client identifier
$stmt = $pdo->query("SELECT plex_client_id FROM settings WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$clientId = $row['plex_client_id'] ?? '';

if (!$clientId) {
    $clientId = bin2hex(random_bytes(16));
    $upd = $pdo->prepare("UPDATE settings SET plex_client_id = ?, updated_at = datetime('now') WHERE id = 1");
    $upd->execute([$clientId]);
}

$baseUrl = 'https://plex.tv/api/v2/pins?strong=true';
$ca = $cfg['ca_cert'] ?? '';
$caReal = $ca ? realpath($ca) : false;
$insecure = !empty($cfg['tls_insecure']);

$ch = curl_init($baseUrl);
$opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
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
    $ch = curl_init($baseUrl);
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
    http_response_code(502);
    echo json_encode([
        'error' => 'pin_create_failed',
        'detail' => $err ?: $resp,
        'curl_errno' => $errNo,
        'ssl_verify_result' => $verify,
        'ca_used' => $caReal ?: null,
        'ca_size' => $caReal && file_exists($caReal) ? filesize($caReal) : 0
    ]);
    exit;
}

$data = json_decode($resp, true);
if (!is_array($data) || empty($data['id']) || empty($data['code'])) {
    http_response_code(502);
    echo json_encode(['error' => 'pin_create_invalid_response', 'detail' => $resp]);
    exit;
}

$pinId = $data['id'];
$code = $data['code'];

$authUrl = 'https://app.plex.tv/auth#?clientID=' . rawurlencode($clientId)
    . '&code=' . rawurlencode($code)
    . '&context%5Bdevice%5D%5Bproduct%5D=' . rawurlencode('Watchulater Local');

echo json_encode([
    'pin_id' => $pinId,
    'code' => $code,
    'client_id' => $clientId,
    'auth_url' => $authUrl,
], JSON_UNESCAPED_UNICODE);
