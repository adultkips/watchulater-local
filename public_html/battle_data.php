<?php
session_start();
require __DIR__ . '/battle_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$battleId = trim($_GET['id'] ?? '');
if ($battleId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM battle_presets WHERE id = ?");
$stmt->execute([$battleId]);
$preset = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$preset) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

function lower_str(string $value): string {
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

$type = $preset['type'] ?? 'movie';
$sections = array_values(array_filter(array_map('trim', explode(',', $preset['source_title'] ?? '')), fn($v) => $v !== ''));
$genreFilter = array_values(array_filter(array_map('trim', explode(',', $preset['genre_filter'] ?? '')), fn($v) => $v !== ''));
$genreAll = (int)($preset['genre_all'] ?? 0) === 1;
$ratingMin = $preset['rating_min'] !== null ? (float)$preset['rating_min'] : 0;
$ratingFilter = array_values(array_filter(array_map('trim', explode(',', $preset['rating_filter'] ?? '')), fn($v) => $v !== ''));
$recommendedOnly = (int)($preset['recommended_only'] ?? 0) === 1;

$stmt = $pdo->prepare("SELECT film_id, title, year, thumb, type, genre, rating, recommended, section_title FROM filmvalg WHERE status = 'watched' AND type = ?");
$stmt->execute([$type]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = [];
foreach ($rows as $row) {
    $sec = trim((string)($row['section_title'] ?? ''));
    if ($sections && !in_array($sec, $sections, true)) {
        continue;
    }
    if (!$genreAll && $genreFilter) {
        $itemGenres = array_map('trim', explode(',', (string)($row['genre'] ?? '')));
        $itemGenres = array_map('lower_str', array_filter($itemGenres, fn($v) => $v !== ''));
        $match = false;
        foreach ($genreFilter as $g) {
            if (in_array(lower_str($g), $itemGenres, true)) { $match = true; break; }
        }
        if (!$match) continue;
    }
    if ($recommendedOnly && (int)($row['recommended'] ?? 0) !== 1) {
        continue;
    }
    if ($ratingFilter) {
        $r = (float)($row['rating'] ?? 0);
        $match = false;
        foreach ($ratingFilter as $val) {
            if (abs($r - (float)$val) <= 0.01) { $match = true; break; }
        }
        if (!$match) continue;
    } elseif ($ratingMin > 0) {
        $r = (float)($row['rating'] ?? 0);
        if (abs($r - $ratingMin) > 0.01) continue;
    }
    $items[$row['film_id']] = $row;
}

$filmIds = array_keys($items);

$stmt = $pdo->prepare("SELECT winner_id, loser_id FROM battle_log WHERE preset_id = ?");
$stmt->execute([$battleId]);
$history = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $a = $r['winner_id'];
    $b = $r['loser_id'];
    $history["$a-$b"] = true;
    $history["$b-$a"] = true;
}

if (!isset($_SESSION['battle_session_history'])) {
    $_SESSION['battle_session_history'] = [];
}
if (!isset($_SESSION['battle_fixed_film'])) {
    $_SESSION['battle_fixed_film'] = [];
}
if (!isset($_SESSION['battle_last_winner_side'])) {
    $_SESSION['battle_last_winner_side'] = [];
}

if (!isset($_SESSION['battle_session_history'][$battleId])) {
    $_SESSION['battle_session_history'][$battleId] = [];
}

$sessionHistory =& $_SESSION['battle_session_history'][$battleId];

function findBattle(array $filmIds, array $items, array $history, array &$sessionHistory, string $battleId) {
    $possible = [];
    $count = count($filmIds);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $id1 = $filmIds[$i];
            $id2 = $filmIds[$j];
            $k1 = "$id1-$id2";
            $k2 = "$id2-$id1";
            if (!isset($history[$k1]) && !isset($sessionHistory[$k1]) && !isset($sessionHistory[$k2])) {
                $possible[] = [$id1, $id2];
            }
        }
    }
    if (!$possible) return null;
    shuffle($possible);
    $pick = $possible[0];
    $sessionHistory["{$pick[0]}-{$pick[1]}"] = true;
    $sessionHistory["{$pick[1]}-{$pick[0]}"] = true;
    $_SESSION['battle_fixed_film'][$battleId] = $pick[0];
    return [$items[$pick[0]], $items[$pick[1]]];
}

$battle = null;
$exhausted = false;

$fixed = $_SESSION['battle_fixed_film'][$battleId] ?? null;
if ($fixed && isset($items[$fixed])) {
    $lastSide = $_SESSION['battle_last_winner_side'][$battleId] ?? 'left';
    $opponents = array_filter($filmIds, fn($id) => $id !== $fixed);
    shuffle($opponents);
    foreach ($opponents as $oid) {
        $k1 = "$fixed-$oid";
        $k2 = "$oid-$fixed";
        if (!isset($history[$k1]) && !isset($sessionHistory[$k1]) && !isset($sessionHistory[$k2])) {
            $battle = ($lastSide === 'right')
                ? [$items[$oid], $items[$fixed]]
                : [$items[$fixed], $items[$oid]];
            $sessionHistory[$k1] = true;
            $sessionHistory[$k2] = true;
            break;
        }
    }
    if (!$battle) {
        $exhausted = true;
        unset($_SESSION['battle_fixed_film'][$battleId]);
        unset($_SESSION['battle_last_winner_side'][$battleId]);
    }
}

if (!$battle) {
    $battle = findBattle($filmIds, $items, $history, $sessionHistory, $battleId);
}

if (!$battle) {
    $_SESSION['battle_session_history'][$battleId] = [];
    unset($_SESSION['battle_fixed_film'][$battleId]);
    unset($_SESSION['battle_last_winner_side'][$battleId]);
    $battle = findBattle($filmIds, $items, $history, $_SESSION['battle_session_history'][$battleId], $battleId);
}

$serverId = $pdo->query("SELECT plex_server_id FROM settings WHERE id = 1")->fetchColumn() ?: '';
$rankStmt = $pdo->prepare("
    SELECT bs.film_id, bs.points, bs.battles_played, bs.battles_won, fv.title, fv.year, fv.thumb, fv.type
    FROM battle_stats bs
    JOIN filmvalg fv ON bs.film_id = fv.film_id
    WHERE bs.preset_id = ? AND bs.battles_played > 0
    ORDER BY bs.points DESC
");
$rankStmt->execute([$battleId]);
$rank = $rankStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rank as &$entry) {
    $ratingKey = str_replace('plex:', '', (string)($entry['film_id'] ?? ''));
    $entry['plex_url'] = $serverId && $ratingKey !== ''
        ? ('https://app.plex.tv/desktop/#!/server/' . rawurlencode($serverId) . '/details?key=' . rawurlencode('/library/metadata/' . $ratingKey))
        : '';
}

echo json_encode([
    'battle' => $battle,
    'ranklist' => $rank,
    'name' => $preset['name'] ?? '',
    'exhausted' => $exhausted
], JSON_UNESCAPED_UNICODE);
