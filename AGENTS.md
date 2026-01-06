# AGENTS.md

## Project overview
- Ultiorganizer is a PHP web app for online Ultimate tournament score keeping.
- Entry point is `index.php`; pages in the repo root are routed via `?view=...`.
- Shared utilities and SQL live in `lib/`.
- Access-controlled areas live in `admin/` and `user/`.

## Repository layout
- `admin/`: admin-only pages (series/event admins).
- `user/`: logged-in user pages (teams, results, etc.).
- `lib/`: shared utilities; SQL statements belong here.
- `script/`: JavaScript assets.
- `conf/`: server config (keep writable only during install).
- `cust/`: skins and customizations. Default is `default`; `slkl` is actively maintained and used in production at https://www.ultimate.fi/pelikone.
- `locale/`: translations and gettext assets.
- `mobile/`: pages for small-screen devices.
- `scorekeeper/`: touchscreen-optimized pages.
- `ext/`: embeddable pages.
- `plugins/`: maintenance/export/import tools.
- `sql/`: database utilities.

## Development setup (summary)
- Requires PHP 8.3+ and MariaDB 10.11+.
- For local dev, the README documents Docker-based setup.

## Code conventions
- Keep SQL statements in `lib/`.
- Use existing page routing pattern (`?view=...`) for new pages.
- Prefer small, focused changes; avoid touching `conf/` unless required.
- Keep edits ASCII unless the file already uses Unicode.

## Database
- Base schema lives in `sql/ultiorganizer.sql`.
- Production upgrades are handled by versioned functions in `sql/upgrade_db.php`, invoked by `CheckDB()` in `lib/database.php` on startup; bump `DB_VERSION` when adding new upgrade steps.

## Third-party components
- YUI assets and loader live under `script/yui/` and `lib/yuiloader/`.
- Bundled PHP libraries include `lib/fpdf/` and `lib/phpqrcode/`.

## Plugins
- `plugins/` are optional and primarily admin-only tools; normal operation should not depend on them.

## Customization notes
- External license database integration is customization-specific; there is no single default external service.

## Testing/verification
- No automated test suite is documented; verify changes by running the app and exercising the relevant page(s).

## AI-specific guidance
- Avoid large refactors unless explicitly requested.
- If making UI changes, verify on both desktop and mobile layouts.

## API approach (planned)
- API lives under `/api`, with versioned paths like `/api/v1/...` and a dedicated entry point in `/api/index.php`.
- JSON only; no HTML responses. Use consistent `status`, `data`, and `error` payloads with HTTP status codes.
- Data normalization and filtering live in `/api`, but SQL and data access are centralized in `lib/` as the single source of truth.
- Initial scope focuses on public data with token authentication; tokens can be installation, season, or user scoped.
- Rate limiting is required (keyed by token + IP), returning `429` and `Retry-After` when exceeded.
- First endpoints mirror `teams.php`, `games.php`, and `gameplay.php`, excluding historical data.
- OpenAPI documentation is required and should live alongside the API (e.g., `/api/openapi.yaml`).
