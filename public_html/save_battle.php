<?php
require __DIR__ . '/battle_bootstrap.php';
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
$type = trim($input['type'] ?? '');
$sourceKey = trim($input['source_key'] ?? '');
$sourceTitle = trim($input['source_title'] ?? '');
$genreFilter = trim($input['genre_filter'] ?? '');
$genreAll = (int)($input['genre_all'] ?? 0);
$ratingMin = isset($input['rating_min']) && $input['rating_min'] !== '' ? (float)$input['rating_min'] : null;
$ratingFilter = trim($input['rating_filter'] ?? '');
$recommendedOnly = (int)($input['recommended_only'] ?? 0);

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_name']);
    exit;
}

if (!in_array($type, ['movie','show'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_type']);
    exit;
}

if ($sourceKey === '' || $sourceTitle === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_sources']);
    exit;
}

if ($ratingFilter !== '') {
    $ratingMin = null;
}

if ($id !== '') {
    $upd = $pdo->prepare("
        UPDATE battle_presets
        SET name = ?, type = ?, source_key = ?, source_title = ?, cover_image = ?, genre_filter = ?, genre_all = ?, rating_min = ?, rating_filter = ?, recommended_only = ?, updated_at = datetime('now')
        WHERE id = ?
    ");
    $upd->execute([$name, $type, $sourceKey, $sourceTitle, $cover, $genreFilter, $genreAll, $ratingMin, $ratingFilter, $recommendedOnly, $id]);
    echo json_encode(['success' => true, 'id' => $id]);
    exit;
}

$ins = $pdo->prepare("
    INSERT INTO battle_presets (name, type, source_key, source_title, cover_image, genre_filter, genre_all, rating_min, rating_filter, recommended_only)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$ins->execute([$name, $type, $sourceKey, $sourceTitle, $cover, $genreFilter, $genreAll, $ratingMin, $ratingFilter, $recommendedOnly]);
echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
