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
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_id']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM battle_presets WHERE id = ?");
$stmt->execute([$id]);
echo json_encode(['success' => true]);
