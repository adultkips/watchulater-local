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
$action = $input['action'] ?? '';
$filmId = trim($input['film_id'] ?? '');
$title = trim($input['title'] ?? '');
$year = trim($input['year'] ?? '');
$type = trim($input['type'] ?? 'movie');
$genre = trim($input['genre'] ?? '');
$thumb = trim($input['thumb'] ?? '');
$imdbId = trim($input['imdb_id'] ?? '');
$sectionTitle = trim($input['section_title'] ?? '');
$rating = isset($input['rating']) && is_numeric($input['rating']) ? (float)$input['rating'] : null;
$recommended = !empty($input['recommended']) ? 1 : 0;

if ($action === '' || $filmId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_input']);
    exit;
}

if ($action === 'skip') {
    echo json_encode(['status' => 'skipped']);
    exit;
}

$status = '';
if ($action === 'watchlist') $status = 'watchlist';
if ($action === 'dismissed') $status = 'dismissed';
if ($action === 'watched') $status = 'watched';

if ($status === '') {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_action']);
    exit;
}

function ensure_section_title_column(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(filmvalg)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'section_title') return;
    }
    $pdo->exec("ALTER TABLE filmvalg ADD COLUMN section_title TEXT");
}

ensure_section_title_column($pdo);

$stmt = $pdo->prepare("
    INSERT INTO filmvalg (film_id, status, title, year, type, genre, thumb, imdb_id, rating, recommended, section_title, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ON CONFLICT(film_id) DO UPDATE SET
        status = excluded.status,
        title = COALESCE(NULLIF(excluded.title, ''), title),
        year = COALESCE(NULLIF(excluded.year, ''), year),
        type = COALESCE(NULLIF(excluded.type, ''), type),
        genre = COALESCE(NULLIF(excluded.genre, ''), genre),
        thumb = COALESCE(NULLIF(excluded.thumb, ''), thumb),
        imdb_id = COALESCE(NULLIF(excluded.imdb_id, ''), imdb_id),
        rating = COALESCE(excluded.rating, rating),
        recommended = COALESCE(excluded.recommended, recommended),
        section_title = COALESCE(NULLIF(excluded.section_title, ''), section_title),
        updated_at = datetime('now')
");

$stmt->execute([
    $filmId,
    $status,
    $title,
    $year,
    $type ?: 'movie',
    $genre,
    $thumb,
    $imdbId,
    $rating,
    $recommended,
    $sectionTitle
]);

echo json_encode(['status' => 'saved'], JSON_UNESCAPED_UNICODE);
