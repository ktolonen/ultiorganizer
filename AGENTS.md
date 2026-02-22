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

## Configuration flags

Use these exact type names when discussing configuration work:

1. `SYSTEM_FLAG`
2. `INSTALLATION_SETTING`
3. `EVENT_SETTING`

### 1) `SYSTEM_FLAG`
- Scope: installation/system-level, defined by deployer/developer, not editable in normal UI.
- Storage: `conf/config.inc.php` (document default/example in `conf/config.inc.example.php`).
- Example: `ENABLE_ADMIN_DB_ACCESS`.
- Use when:
  - the value is environment/security/infrastructure specific.
  - changing it should require file/system access or deployment process.
- Implementation rules:
  - define a clear constant-like key (UPPER_CASE recommended).
  - provide safe default/fallback handling in code if missing.
  - document purpose and allowed values in `conf/config.inc.example.php`.
  - do not store these in DB unless there is a strong compatibility reason.

### 2) `INSTALLATION_SETTING`
- Scope: installation-wide, editable by admin users via admin UI.
- Storage: database-backed server configuration (managed through admin pages like `admin/serverconf.php`).
- Example: `GoogleMapsAPIKey`.
- Use when:
  - all events/users in one installation share one value.
  - admins should be able to change value without file edits/deploy.
- Implementation rules:
  - add read/write support in existing server configuration flow.
  - validate and sanitize input at write time; escape at output/use time.
  - define sensible default behavior when setting is unset.
  - keep SQL/data access in `lib/`.

### 3) `EVENT_SETTING`
- Scope: per event/season/tournament entity.
- Storage: event/season related DB tables and forms.
- Example: `use_season_points` in `admin/addseasons.php`.
- Use when:
  - value can differ between events in same installation.
  - behavior must be controlled by event admins.
- Implementation rules:
  - store with the event/season record (or tightly related table).
  - expose in relevant event admin UI.
  - include in event create/edit flows and read paths that depend on it.
  - keep SQL/data access in `lib/`.

### Database change requirements
- If a new `INSTALLATION_SETTING` or `EVENT_SETTING` needs new schema/columns/tables:
  - update base schema in `sql/ultiorganizer.sql`.
  - add a new upgrade step in `sql/upgrade_db.php`.
  - bump `DB_VERSION` so `CheckDB()` applies upgrade on startup.
- Prefer backward-compatible migrations:
  - nullable or defaulted new columns.
  - safe fallback behavior in PHP until migration has run.

### Choosing configuration type
- Choose `SYSTEM_FLAG` for deploy-time/environment/security toggles.
- Choose `INSTALLATION_SETTING` for installation-wide admin-managed values.
- Choose `EVENT_SETTING` for per-event behavior and event admin control.
- Do not use a broader scope than needed (prefer narrowest valid scope).

### Changing a flag from one type to another
- Treat as a data migration task, not only a code rename.
- Required checklist:
  - define source and target type explicitly (`SYSTEM_FLAG`/`INSTALLATION_SETTING`/`EVENT_SETTING`).
  - add migration logic (if DB involved) in `sql/upgrade_db.php` and bump `DB_VERSION`.
  - provide fallback for old installations during transition.
  - update admin UI surfaces and docs where the setting is edited.
  - verify read paths no longer depend on old storage location.
