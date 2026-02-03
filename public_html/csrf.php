<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void {
    $token = $_SESSION['csrf_token'] ?? '';
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || $header === '' || !hash_equals($token, $header)) {
        http_response_code(403);
        echo json_encode(['error' => 'csrf_invalid']);
        exit;
    }
}
