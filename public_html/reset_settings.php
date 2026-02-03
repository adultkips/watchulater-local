<?php
require __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'method_not_allowed']);
    exit;
}

require_csrf();

$pdo->exec('PRAGMA busy_timeout = 2000');

try {
    $pdo->beginTransaction();

    $pdo->exec("DELETE FROM filmvalg");
    $pdo->exec("DELETE FROM roulette_state");
    $pdo->exec("DELETE FROM roulettes");
    $pdo->exec("DELETE FROM battle_log");
    $pdo->exec("DELETE FROM battle_stats");
    $pdo->exec("DELETE FROM battle_presets");

    $pdo->exec("UPDATE settings
      SET onboarded = 0,
          plex_token = NULL,
          plex_server_id = NULL,
          plex_server_url = NULL,
          plex_server_name = NULL,
          plex_client_id = NULL,
          plex_server_token = NULL,
          plex_server_token_manual = 0,
          plex_server_url_manual = 0,
          updated_at = datetime('now')
      WHERE id = 1");

    $pdo->commit();

    $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'reset',
        'settings' => $row
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'db_locked',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
