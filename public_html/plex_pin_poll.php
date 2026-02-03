<?php
require __DIR__ . '/../db/connect.php';
$cfg = require __DIR__ . '/../db/config.php';

header('Content-Type: application/json; charset=utf-8');

$pinId = isset($_GET['pin_id']) ? trim($_GET['pin_id']) : '';
if ($pinId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_pin_id']);
    exit;
}

$stmt = $pdo->query("SELECT plex_client_id FROM settings WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$clientId = $row['plex_client_id'] ?? '';

if (!$clientId) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_client_id']);
    exit;
}

$url = 'https://plex.tv/api/v2/pins/' . rawurlencode($pinId);
$ca = $cfg['ca_cert'] ?? '';
$caReal = $ca ? realpath($ca) : false;
$insecure = !empty($cfg['tls_insecure']);

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
    http_response_code(502);
    echo json_encode([
        'error' => 'pin_poll_failed',
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
    http_response_code(502);
    echo json_encode(['error' => 'pin_poll_invalid_response', 'detail' => $resp]);
    exit;
}

$authToken = $data['authToken'] ?? '';
if ($authToken) {
    $upd = $pdo->prepare("UPDATE settings SET plex_token = ?, updated_at = datetime('now') WHERE id = 1");
    $upd->execute([$authToken]);
    echo json_encode(['status' => 'linked']);
    exit;
}

echo json_encode(['status' => 'pending']);
