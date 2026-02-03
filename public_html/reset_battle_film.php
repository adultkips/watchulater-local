<?php
session_start();
require __DIR__ . '/battle_bootstrap.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

require_csrf();

$input = json_decode(file_get_contents('php://input'), true);
$battleId = trim($input['battle_id'] ?? '');
$filmId = trim($input['film_id'] ?? '');
if ($battleId === '' || $filmId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_input']);
    exit;
}

$pdo->prepare("DELETE FROM battle_stats WHERE preset_id = ? AND film_id = ?")->execute([$battleId, $filmId]);
$pdo->prepare("DELETE FROM battle_log WHERE preset_id = ? AND (winner_id = ? OR loser_id = ?)")->execute([$battleId, $filmId, $filmId]);

unset($_SESSION['battle_session_history'][$battleId]);
unset($_SESSION['battle_fixed_film'][$battleId]);
unset($_SESSION['battle_last_winner_side'][$battleId]);

echo json_encode(['success' => true]);
