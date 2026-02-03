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

$input = json_decode(file_get_contents('php://input'), true);
$id = trim($input['id'] ?? '');
$name = trim($input['name'] ?? '');
$cover = trim($input['cover_image'] ?? '');
$movieSource = trim($input['movie_source'] ?? '');
$movieTitle = trim($input['movie_title'] ?? '');
$showSource = trim($input['show_source'] ?? '');
$showTitle = trim($input['show_title'] ?? '');
$genreFilter = trim($input['genre_filter'] ?? '');
$genreAll = (int)($input['genre_all'] ?? 0);

function ensure_genre_filter_column(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(roulettes)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'genre_filter') return;
    }
    $pdo->exec("ALTER TABLE roulettes ADD COLUMN genre_filter TEXT");
}

ensure_genre_filter_column($pdo);

function ensure_genre_all_column(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(roulettes)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'genre_all') return;
    }
    $pdo->exec("ALTER TABLE roulettes ADD COLUMN genre_all INTEGER NOT NULL DEFAULT 0");
}

ensure_genre_all_column($pdo);

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_name']);
    exit;
}

$hasMovies = $movieSource !== '';
$hasShows = $showSource !== '';
if (!$hasMovies && !$hasShows) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_sources']);
    exit;
}
if ($hasMovies && $hasShows) {
    http_response_code(400);
    echo json_encode(['error' => 'mixed_sources']);
    exit;
}

if ($id !== '') {
    $upd = $pdo->prepare("UPDATE roulettes SET name = ?, cover_image = ?, movie_source = ?, movie_title = ?, show_source = ?, show_title = ?, genre_filter = ?, genre_all = ?, updated_at = datetime('now') WHERE id = ?");
    $upd->execute([$name, $cover, $movieSource, $movieTitle, $showSource, $showTitle, $genreFilter, $genreAll, $id]);
    echo json_encode(['success' => true, 'id' => $id]);
    exit;
}

$ins = $pdo->prepare("INSERT INTO roulettes (name, cover_image, movie_source, movie_title, show_source, show_title, genre_filter, genre_all) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$ins->execute([$name, $cover, $movieSource, $movieTitle, $showSource, $showTitle, $genreFilter, $genreAll]);
echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
