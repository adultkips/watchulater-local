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

// Clear Plex connection data, keep user data
$stmt = $pdo->prepare("UPDATE settings SET
  onboarded = 0,
  plex_token = NULL,
  plex_server_id = NULL,
  plex_server_url = NULL,
  plex_server_name = NULL,
  plex_server_token = NULL,
  plex_server_token_manual = 0,
  plex_server_url_manual = 0,
  updated_at = datetime('now')
WHERE id = 1");
$stmt->execute();

echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
