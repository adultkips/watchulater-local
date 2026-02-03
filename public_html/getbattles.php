<?php
require __DIR__ . '/battle_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$rows = $pdo->query("
    SELECT id, name, type, source_title, source_key, cover_image, genre_filter, genre_all, rating_min, recommended_only, created_at, updated_at
    FROM battle_presets
    ORDER BY updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
