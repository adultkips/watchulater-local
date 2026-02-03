<?php
require __DIR__ . '/../db/connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$rouletteId = trim($_GET['id'] ?? '');
if ($rouletteId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_id']);
    exit;
}

$rouletteStmt = $pdo->prepare("SELECT * FROM roulettes WHERE id = ?");
$rouletteStmt->execute([$rouletteId]);
$roulette = $rouletteStmt->fetch(PDO::FETCH_ASSOC);
if (!$roulette) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$settings = $pdo->query("SELECT plex_server_url, plex_server_token, plex_token, plex_server_id FROM settings WHERE id = 1")
    ->fetch(PDO::FETCH_ASSOC) ?: [];
$serverUrl = rtrim($settings['plex_server_url'] ?? '', '/');
$serverToken = $settings['plex_server_token'] ?: ($settings['plex_token'] ?? '');
$serverId = $settings['plex_server_id'] ?? '';

if ($serverUrl === '' || $serverToken === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_config']);
    exit;
}

$movieSource = trim($roulette['movie_source'] ?? '');
$showSource = trim($roulette['show_source'] ?? '');
$movieTitle = trim($roulette['movie_title'] ?? '');
$showTitle = trim($roulette['show_title'] ?? '');
$genreFilter = trim($roulette['genre_filter'] ?? '');
$genreAll = (int)($roulette['genre_all'] ?? 0) === 1;
$filterGenres = array_values(array_filter(array_map('trim', explode(',', $genreFilter)), fn($v) => $v !== ''));

function lower_str(string $value): string {
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

$filterGenres = array_map('lower_str', $filterGenres);
if ($genreAll) {
    $filterGenres = [];
}
$type = $movieSource !== '' ? 'movie' : 'show';
$sectionKeys = array_filter(array_map('trim', explode(',', $type === 'movie' ? $movieSource : $showSource)));

$titleMap = [];
$sourceKeys = $type === 'movie' ? $movieSource : $showSource;
$sourceTitles = $type === 'movie' ? $movieTitle : $showTitle;
$keyList = array_map('trim', explode(',', $sourceKeys));
$titleList = array_map('trim', explode(',', $sourceTitles));
foreach ($keyList as $idx => $k) {
    if ($k === '') continue;
    $titleMap[$k] = $titleList[$idx] ?? '';
}

if (!$sectionKeys) {
    echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

function plex_get(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $http >= 400) {
        return ['error' => $err ?: $resp];
    }
    $data = json_decode($resp, true);
    if (!is_array($data)) {
        return ['error' => 'invalid_response'];
    }
    return $data;
}

function imdb_from_guid(array $meta): string {
    // Newer Plex may include "Guid" array or "guid" string
    $guidArr = $meta['Guid'] ?? [];
    if (is_array($guidArr)) {
        foreach ($guidArr as $g) {
            $id = $g['id'] ?? '';
            if (strpos($id, 'imdb://') === 0) {
                return substr($id, 7);
            }
        }
    }
    $guidStr = $meta['guid'] ?? '';
    if (is_string($guidStr) && strpos($guidStr, 'imdb://') === 0) {
        return substr($guidStr, 7);
    }
    return '';
}

function list_from_meta(array $meta, string $key, string $field = 'tag'): string {
    if (empty($meta[$key]) || !is_array($meta[$key])) return '';
    $vals = [];
    foreach ($meta[$key] as $item) {
        $val = $item[$field] ?? '';
        if ($val !== '') $vals[] = $val;
    }
    return implode(', ', $vals);
}

$items = [];
$seen = [];
$limitPerSection = 200;
$plexType = $type === 'movie' ? 1 : 2;

$stmt = $pdo->prepare("SELECT film_id FROM filmvalg WHERE status IN ('watchlist','watched','dismissed')");
$stmt->execute();
$taken = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);

foreach ($sectionKeys as $key) {
    $sectionTitle = $titleMap[$key] ?? '';
    $countUrl = $serverUrl . '/library/sections/' . rawurlencode($key) . '/all?type=' . $plexType
        . '&X-Plex-Container-Start=0&X-Plex-Container-Size=0'
        . '&X-Plex-Token=' . rawurlencode($serverToken);
    $countData = plex_get($countUrl, $serverToken);
    $totalSize = (int)($countData['MediaContainer']['totalSize'] ?? 0);
    if ($totalSize <= 0) continue;

    for ($start = 0; $start < $totalSize; $start += $limitPerSection) {
        $url = $serverUrl . '/library/sections/' . rawurlencode($key) . '/all?type=' . $plexType
            . '&includeGuids=1'
            . '&X-Plex-Token=' . rawurlencode($serverToken)
            . '&X-Plex-Container-Start=' . $start . '&X-Plex-Container-Size=' . $limitPerSection;
        $data = plex_get($url, $serverToken);
        if (isset($data['error'])) continue;
        $meta = $data['MediaContainer']['Metadata'] ?? [];
        if (!is_array($meta)) continue;

        foreach ($meta as $m) {
            $ratingKey = $m['ratingKey'] ?? '';
            if ($ratingKey === '' || isset($seen[$ratingKey])) continue;
            $filmId = 'plex:' . $ratingKey;
            if (isset($taken[$filmId])) continue;
            $seen[$ratingKey] = true;
            $thumb = $m['thumb'] ?? '';
            $poster = $thumb ? ($serverUrl . $thumb . '?X-Plex-Token=' . rawurlencode($serverToken)) : '';
            $imdb = imdb_from_guid($m);
            $itemGenres = list_from_meta($m, 'Genre');
        if ($filterGenres) {
            $itemGenreList = array_map('lower_str', array_map('trim', explode(',', $itemGenres)));
            $hasAny = false;
            foreach ($filterGenres as $fg) {
                if (in_array($fg, $itemGenreList, true)) { $hasAny = true; break; }
            }
            if (!$hasAny) {
                continue;
            }
        }

        $items[] = [
            'id' => $filmId,
            'ratingKey' => $ratingKey,
            'title' => $m['title'] ?? '',
            'year' => $m['year'] ?? '',
            'type' => $m['type'] ?? $type,
            'summary' => $m['summary'] ?? '',
            'thumb' => $poster,
            'imdb_id' => $imdb,
            'imdb_url' => $imdb ? ('https://www.imdb.com/title/' . $imdb . '/') : '',
            'plex_url' => $serverId ? ('https://app.plex.tv/desktop/#!/server/' . rawurlencode($serverId) . '/details?key=' . rawurlencode('/library/metadata/' . $ratingKey)) : '',
            'director' => list_from_meta($m, 'Director'),
            'writer' => list_from_meta($m, 'Writer'),
            'genres' => $itemGenres,
            'actors' => list_from_meta($m, 'Role'),
            'section_title' => $sectionTitle,
        ];
        }
    }
}

echo json_encode([
    'roulette_id' => $rouletteId,
    'items' => $items
], JSON_UNESCAPED_UNICODE);
