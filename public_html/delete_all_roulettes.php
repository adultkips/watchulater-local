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
$ids = $input['ids'] ?? [];
if (!is_array($ids) || count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_ids']);
    exit;
}
$ids = array_values(array_filter(array_map('trim', $ids), fn($v) => $v !== ''));
if (count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_ids']);
    exit;
}

try {
    $pdo->beginTransaction();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM roulette_state WHERE roulette_id IN ($placeholders)");
    $stmt->execute($ids);
    $stmt = $pdo->prepare("DELETE FROM roulettes WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $pdo->commit();
    echo json_encode(['success' => true, 'count' => count($ids)]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'delete_failed']);
}
