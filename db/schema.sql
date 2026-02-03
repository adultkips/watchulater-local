-- SQLite schema for Watchulater
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS settings (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  onboarded INTEGER NOT NULL DEFAULT 0,
  plex_client_id TEXT,
  plex_token TEXT,
  plex_server_id TEXT,
  plex_server_url TEXT,
  plex_server_name TEXT,
  plex_server_token TEXT,
  plex_server_token_manual INTEGER NOT NULL DEFAULT 0,
  plex_server_url_manual INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS filmvalg (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  film_id TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL CHECK (status IN ('dismissed','watchlist','watched')),
  rating REAL,
  recommended INTEGER,
  title TEXT,
  year TEXT,
  slug TEXT,
  imdb_id TEXT,
  thumb TEXT,
  type TEXT NOT NULL CHECK (type IN ('movie','show')),
  genre TEXT,
  section_title TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS roulettes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  cover_image TEXT,
  movie_title TEXT,
  movie_source TEXT,
  show_title TEXT,
  show_source TEXT,
  genre_filter TEXT,
  genre_all INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS roulette_state (
  roulette_id INTEGER PRIMARY KEY,
  current_index INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (roulette_id) REFERENCES roulettes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS battle_presets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  type TEXT NOT NULL CHECK (type IN ('movie','show')),
  source_title TEXT,
  source_key TEXT,
  cover_image TEXT,
  genre_filter TEXT,
  genre_all INTEGER NOT NULL DEFAULT 0,
  rating_min REAL,
  rating_filter TEXT,
  recommended_only INTEGER NOT NULL DEFAULT 0,
  filters_json TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS battle_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  preset_id INTEGER NOT NULL,
  winner_id TEXT NOT NULL,
  loser_id TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (preset_id) REFERENCES battle_presets(id) ON DELETE CASCADE
);

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
);

CREATE INDEX IF NOT EXISTS idx_filmvalg_status ON filmvalg(status);
CREATE INDEX IF NOT EXISTS idx_roulettes_updated ON roulettes(updated_at);
CREATE INDEX IF NOT EXISTS idx_battle_log_preset ON battle_log(preset_id);
