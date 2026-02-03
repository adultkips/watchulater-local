<?php
require __DIR__ . '/../db/connect.php';

function battle_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function battle_column_exists(PDO $pdo, string $table, string $column): bool {
    $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === $column) return true;
    }
    return false;
}

function ensure_battle_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS battle_presets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT NOT NULL CHECK (type IN ('movie','show')),
            source_title TEXT,
            source_key TEXT,
            filters_json TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS battle_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            preset_id INTEGER NOT NULL,
            winner_id TEXT NOT NULL,
            loser_id TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (preset_id) REFERENCES battle_presets(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS battle_stats (
            preset_id INTEGER NOT NULL,
            film_id TEXT NOT NULL,
            points INTEGER NOT NULL DEFAULT 0,
            battles_played INTEGER NOT NULL DEFAULT 0,
            battles_won INTEGER NOT NULL DEFAULT 0,
            opponents TEXT,
            won_against TEXT,
            lost_against TEXT,
            PRIMARY KEY (preset_id, film_id),
            FOREIGN KEY (preset_id) REFERENCES battle_presets(id) ON DELETE CASCADE
        )
    ");

    $columns = [
        'cover_image' => "ALTER TABLE battle_presets ADD COLUMN cover_image TEXT",
        'genre_filter' => "ALTER TABLE battle_presets ADD COLUMN genre_filter TEXT",
        'genre_all' => "ALTER TABLE battle_presets ADD COLUMN genre_all INTEGER NOT NULL DEFAULT 0",
        'rating_min' => "ALTER TABLE battle_presets ADD COLUMN rating_min REAL",
        'rating_filter' => "ALTER TABLE battle_presets ADD COLUMN rating_filter TEXT",
        'recommended_only' => "ALTER TABLE battle_presets ADD COLUMN recommended_only INTEGER NOT NULL DEFAULT 0"
    ];
    foreach ($columns as $name => $sql) {
        if (!battle_column_exists($pdo, 'battle_presets', $name)) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_battle_log_preset ON battle_log(preset_id)");
}

ensure_battle_schema($pdo);
