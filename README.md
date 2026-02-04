# Watchulater Local

![License](https://img.shields.io/github/license/adultkips/watchulater-local)
![PHP](https://img.shields.io/badge/PHP-8.x-blue)

A local-first Plex companion app that helps you decide what to watch next.  
No cloud account, no hosted backend - everything runs locally on your machine.

## What you get

- Plex onboarding (PIN login + server selection)
- Roulette flow (watchlist / watched / dismissed / skip)
- Watchlist, Watched, Dismissed with filter/sort/search
- Watch Now flow from your filtered list
- Battles with live ranking between watched titles
- Single-user local setup with SQLite

## Screenshots

### Profile
![Profile](screenshots/1.png)

### Create your first roulette
![Create your first roulette](screenshots/2.png)

### Roulette view
![Roulette view](screenshots/3.png)

### List view
![List view](screenshots/4.png)

### Battles view
![Battles view](screenshots/5.png)

## Requirements

- Windows (or adapt commands for macOS/Linux)
- PHP 8.x with:
  - `pdo_sqlite`
  - `curl`
  - `json`

## Quick start (Windows)

1. Clone or download this repository
2. Start the local server:

   ```powershell
   .\start_server.bat
   ```

3. Open:

   `http://localhost:8000`

On first load, the app auto-creates `db/watchulater.db` from `db/schema.sql` + `db/seed.sql`.

## First-run setup

1. Open onboarding
2. Link Plex account (PIN flow or manual token)
3. Select Plex server
4. Finish setup and create your first roulette

## Troubleshooting

- **`php` not found**  
  Install PHP and make sure `php -v` works in terminal.

- **404 on `/`**  
  Start server from project root and use:  
  `php -S localhost:8000 -t public_html`

- **Plex TLS/certificate errors**  
  Update `cacert.pem` from: https://curl.se/ca/cacert.pem

- **Port 8000 already in use**  
  Change `PORT` in `start_server.bat`.

## Security / local data

- All runtime data is local
- SQLite DB: `db/watchulater.db` (git-ignored)
- `.env` is git-ignored
- CSRF protection is enabled for POST endpoints (cookie/session based)

## License

MIT - see [LICENSE](LICENSE).
