# Watchulater Local

![License](https://img.shields.io/github/license/adultkips/watchulater-local)
![PHP](https://img.shields.io/badge/PHP-8.x-blue)

A 100% local, single-user Watchulater build with Plex integration, onboarding, roulettes, lists, and battles.

## Requirements
- PHP 8.x
- SQLite (via PHP PDO)

## Quick start
1) Start the PHP dev server:

   ```powershell
   .\start_server.bat
   ```

2) Open in browser:
   http://localhost:8000

On first load, the app auto-creates the SQLite database from `db/schema.sql` + `db/seed.sql`.

## Notes
- Database file is local only: `db/watchulater.db` (ignored by git).
- Plex PIN flow requires TLS verification. `cacert.pem` is included.
- If Plex TLS fails on your machine, update `cacert.pem` from https://curl.se/ca/cacert.pem.
- CSRF protection is enabled for all POST endpoints (requires cookies).

## Repository hygiene
- `.env` is ignored by git (if you add one).
- Local DB and runtime artifacts are not tracked.
