<?php
require __DIR__ . '/../db/connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$type = strtolower(trim($_GET['type'] ?? ''));

// Reuse existing plex_sections.php endpoint
ob_start();
include __DIR__ . '/plex_sections.php';
$resp = ob_get_clean();

if (isset($resp[0]) && ord($resp[0]) === 0xEF) {
    $resp = preg_replace('/^\xEF\xBB\xBF/', '', $resp);
}
$data = json_decode($resp, true);
if (!is_array($data) || empty($data['sections'])) {
    // Try to recover sections array from mixed output
    $sections = [];
    if (preg_match('/"sections"\s*:\s*(\[[\s\S]*\])\s*[,\}]/', $resp, $m)) {
        $maybe = json_decode($m[1], true);
        if (is_array($maybe)) {
            $sections = $maybe;
        }
    }
    if (!$sections) {
        echo json_encode([]);
        exit;
    }
} else {
    $sections = $data['sections'];
}
if ($type === 'movie' || $type === 'show') {
    $sections = array_values(array_filter($sections, function ($s) use ($type) {
        return strtolower($s['type'] ?? '') === $type;
    }));
}

echo json_encode($sections, JSON_UNESCAPED_UNICODE);
