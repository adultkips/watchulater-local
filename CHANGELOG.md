# Changelog

All notable changes to this project are documented in this file.

## [0.1.0] - 2026-02-04

### Added
- 100% local single-user architecture (SQLite)
- Plex onboarding flow (PIN login + manual token fallback + server selection)
- Roulette system with create/edit/view flows
- Watchlist / Watched / Dismissed list pages with filtering/sorting/search
- Watch Now flow from filtered lists
- Battles system with scoring and live ranklist
- README improvements and screenshot gallery
- GitHub issue templates (bug + feature)

### Changed
- UX/UI refresh across onboarding, profile, roulette, lists, and battles
- Improved local-first setup and startup guidance

### Fixed
- Multiple stability/flow issues in onboarding and server connection handling
- Local reset/reconnect and persistence behavior improvements

### Security
- POST-only mutating endpoints
- CSRF protection for state-changing requests
