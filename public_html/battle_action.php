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
$winnerId = trim($input['winner_id'] ?? '');
$loserId = trim($input['loser_id'] ?? '');
$winnerSide = $input['winner_side'] ?? 'left';

if ($battleId === '' || $winnerId === '' || $loserId === '' || $winnerId === $loserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_input']);
    exit;
}

$_SESSION['battle_fixed_film'][$battleId] = $winnerId;
$_SESSION['battle_last_winner_side'][$battleId] = $winnerSide;

$stmt = $pdo->prepare("
    SELECT id FROM battle_log
    WHERE preset_id = ? AND (
        (winner_id = ? AND loser_id = ?) OR
        (winner_id = ? AND loser_id = ?)
    )
");
$stmt->execute([$battleId, $winnerId, $loserId, $loserId, $winnerId]);
if ($stmt->fetchColumn()) {
    echo json_encode(['success' => false, 'error' => 'already_logged']);
    exit;
}

$ins = $pdo->prepare("INSERT INTO battle_log (preset_id, winner_id, loser_id) VALUES (?, ?, ?)");
$ins->execute([$battleId, $winnerId, $loserId]);

function updateStats(PDO $pdo, string $battleId, string $filmId, int $deltaPoints, int $deltaWon, string $opponentId, bool $won): void {
    $stmt = $pdo->prepare("SELECT points, battles_played, battles_won, opponents, won_against, lost_against FROM battle_stats WHERE preset_id = ? AND film_id = ?");
    $stmt->execute([$battleId, $filmId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $modIdStr = ',' . $opponentId . ',';

    if ($row) {
        $points = (int)$row['points'] + $deltaPoints;
        $played = (int)$row['battles_played'] + 1;
        $wonCount = (int)$row['battles_won'] + $deltaWon;

        $opponents = $row['opponents'] ?? '';
        if (strpos($opponents, $modIdStr) === false) $opponents .= $modIdStr;

        $wonAgainst = $row['won_against'] ?? '';
        $lostAgainst = $row['lost_against'] ?? '';
        if ($won) {
            if (strpos($wonAgainst, $modIdStr) === false) $wonAgainst .= $modIdStr;
        } else {
            if (strpos($lostAgainst, $modIdStr) === false) $lostAgainst .= $modIdStr;
        }

        $upd = $pdo->prepare("
            UPDATE battle_stats
            SET points = ?, battles_played = ?, battles_won = ?, opponents = ?, won_against = ?, lost_against = ?
            WHERE preset_id = ? AND film_id = ?
        ");
        $upd->execute([$points, $played, $wonCount, $opponents, $wonAgainst, $lostAgainst, $battleId, $filmId]);
    } else {
        $opponents = $modIdStr;
        $wonAgainst = $won ? $modIdStr : '';
        $lostAgainst = $won ? '' : $modIdStr;
        $ins = $pdo->prepare("
            INSERT INTO battle_stats (preset_id, film_id, points, battles_played, battles_won, opponents, won_against, lost_against)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$battleId, $filmId, $deltaPoints, 1, $deltaWon, $opponents, $wonAgainst, $lostAgainst]);
    }
}

updateStats($pdo, $battleId, $winnerId, 1, 1, $loserId, true);
updateStats($pdo, $battleId, $loserId, -1, 0, $winnerId, false);

echo json_encode(['success' => true]);
