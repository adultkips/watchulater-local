<?php
// public_html/bootstrap.php
require __DIR__ . '/../db/connect.php';

function ensure_db_initialized(PDO $pdo): void {
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        return;
    }

    $schemaPath = __DIR__ . '/../db/schema.sql';
    $seedPath = __DIR__ . '/../db/seed.sql';
    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql !== false) {
        $pdo->exec($schemaSql);
    }
    $seedSql = file_get_contents($seedPath);
    if ($seedSql !== false) {
        $pdo->exec($seedSql);
    }
}

function get_settings(PDO $pdo): array {
    ensure_db_initialized($pdo);
    $stmt = $pdo->query('SELECT * FROM settings WHERE id = 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['onboarded' => 0];
}

function require_onboarded(PDO $pdo): void {
    $settings = get_settings($pdo);
    $needsOnboarding = empty($settings['onboarded'])
        || empty($settings['plex_token'])
        || empty($settings['plex_server_id']);
    if ($needsOnboarding) {
        header('Location: onboarding.php');
        exit;
    }
}
