<?php
require __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_csrf();

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

ensure_server_token_columns($pdo);

$input = json_decode(file_get_contents('php://input'), true);
$serverUrl = trim($input['server_url'] ?? '');
$serverToken = trim($input['server_token'] ?? '');
$serverName = trim($input['server_name'] ?? '');

if ($serverUrl === '' && $serverToken === '' && $serverName === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_fields']);
    exit;
}

if ($serverUrl !== '') {
    $parts = parse_url($serverUrl);
    $host = $parts['host'] ?? '';
    $scheme = $parts['scheme'] ?? '';
    if (!$host || ($scheme !== 'http' && $scheme !== 'https')) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_server_url']);
        exit;
    }
}

$fields = [];
$values = [];
if ($serverUrl !== '') {
    $fields[] = 'plex_server_url = ?';
    $values[] = $serverUrl;
    $fields[] = 'plex_server_url_manual = 1';
}
if ($serverToken !== '') {
    $fields[] = 'plex_server_token = ?';
    $values[] = $serverToken;
    $fields[] = 'plex_server_token_manual = 1';
}
if ($serverName !== '') { $fields[] = 'plex_server_name = ?'; $values[] = $serverName; }

$fields[] = "updated_at = datetime('now')";
$sql = "UPDATE settings SET " . implode(', ', $fields) . " WHERE id = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute($values);

echo json_encode(['status' => 'saved'], JSON_UNESCAPED_UNICODE);
