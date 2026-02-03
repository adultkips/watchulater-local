<?php
require __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$allowedStatuses = ['watchlist', 'watched', 'dismissed'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? '';
    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_status']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM filmvalg WHERE status = ? ORDER BY updated_at DESC");
    $stmt->execute([$status]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['items' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'upsert';

    if ($action === 'delete') {
        $filmId = trim($input['film_id'] ?? '');
        if ($filmId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'missing_film_id']);
            exit;
        }
        $del = $pdo->prepare("DELETE FROM filmvalg WHERE film_id = ?");
        $del->execute([$filmId]);
        echo json_encode(['status' => 'deleted']);
        exit;
    }

    if ($action === 'delete_many') {
        $ids = $input['film_ids'] ?? [];
        if (!is_array($ids) || count($ids) === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'missing_film_ids']);
            exit;
        }
        $ids = array_values(array_filter(array_map('trim', $ids), fn($v) => $v !== ''));
        if (count($ids) === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'missing_film_ids']);
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM filmvalg WHERE film_id IN ($placeholders)");
        $stmt->execute($ids);
        echo json_encode(['status' => 'deleted', 'count' => count($ids)]);
        exit;
    }

    if ($action === 'update_meta') {
        $filmId = trim($input['film_id'] ?? '');
        if ($filmId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'missing_film_id']);
            exit;
        }
        $rating = array_key_exists('rating', $input) ? ($input['rating'] === null ? null : (is_numeric($input['rating']) ? (float)$input['rating'] : null)) : null;
        $recommended = array_key_exists('recommended', $input) ? (!empty($input['recommended']) ? 1 : 0) : null;
        $upd = $pdo->prepare("
            UPDATE filmvalg
            SET rating = ?, recommended = ?, updated_at = datetime('now')
            WHERE film_id = ?
        ");
        $upd->execute([$rating, $recommended, $filmId]);
        echo json_encode(['status' => 'updated']);
        exit;
    }

    $filmId = trim($input['film_id'] ?? '');
    $status = $input['status'] ?? '';
    $title = trim($input['title'] ?? '');
    $year = trim($input['year'] ?? '');
    $type = trim($input['type'] ?? '');
    $genre = trim($input['genre'] ?? '');
    $thumb = trim($input['thumb'] ?? '');
    $imdbId = trim($input['imdb_id'] ?? '');
    $rating = array_key_exists('rating', $input) ? ($input['rating'] === null ? null : (is_numeric($input['rating']) ? (float)$input['rating'] : null)) : null;
    $recommended = array_key_exists('recommended', $input) ? ($input['recommended'] === null ? null : (!empty($input['recommended']) ? 1 : 0)) : null;

    if ($filmId === '' || !in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_input']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO filmvalg (film_id, status, title, year, type, genre, thumb, imdb_id, rating, recommended, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ON CONFLICT(film_id) DO UPDATE SET
            status = excluded.status,
            title = COALESCE(NULLIF(excluded.title, ''), title),
            year = COALESCE(NULLIF(excluded.year, ''), year),
            type = COALESCE(NULLIF(excluded.type, ''), type),
            genre = COALESCE(NULLIF(excluded.genre, ''), genre),
            thumb = COALESCE(NULLIF(excluded.thumb, ''), thumb),
            imdb_id = COALESCE(NULLIF(excluded.imdb_id, ''), imdb_id),
        rating = excluded.rating,
        recommended = excluded.recommended,
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
        $recommended
    ]);

    echo json_encode(['status' => 'saved'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
