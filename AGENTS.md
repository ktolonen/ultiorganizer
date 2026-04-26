# AGENTS.md

Root guidance for coding agents. Keep this file short; detailed topic docs live under `docs/`.

## Project overview

- Ultiorganizer is a PHP web app for online Ultimate tournament score keeping.
- Main entry point is `index.php`; root pages are routed via `?view=...`.
- Shared utilities and SQL-backed data access live in `lib/`.
- Access-controlled areas live in `admin/` and `user/`.

## Repository layout

- `admin/`: admin-only pages.
- `user/`: logged-in user pages.
- `lib/`: shared utilities; SQL belongs here.
- `api/`: JSON API entry points and routing.
- `cust/`: skins and installation-specific customizations.
- `mobile/`, `scorekeeper/`, `spiritkeeper/`, `login/`, `ext/`: specialized entry points. `mobile/` is a deprecated legacy interface kept for compatibility; `scorekeeper/` and `spiritkeeper/` are the supported replacements.
- `images/`, `locale/`, `plugins/`: static assets, translations, and plugin code.
- `script/`: client-side JavaScript assets.
- `conf/`: server configuration; keep writable only during install.
- `sql/`: schema and upgrade assets.

## `lib/` PHP index

Prefer reusing these helpers before adding new utility code or direct SQL.

### Root `lib/`

- `lib/accreditation.functions.php`: player accreditation, license data, acknowledgements, and accreditation logs.
- `lib/api.functions.php`: API token hashing, lookup, touch, CRUD, and rate limiting.
- `lib/auth.guard.php`: include-time auth guard that starts the session and redirects anonymous users.
- `lib/club.functions.php`: club CRUD, club-team links, profiles, images, and external URLs.
- `lib/comment.functions.php`: game and spirit comment storage, permission checks, metadata, HTML rendering, and logging hooks.
- `lib/common.functions.php`: shared low-level helpers for dates, locale/time formatting, colors, CSV export, SQL filter/order builders, view resolution, and generic comment helpers.
- `lib/configuration.functions.php`: server config reads/writes, feature flags, customization discovery, and localization discovery.
- `lib/country.functions.php`: country CRUD, dropdown helpers, team/country relations, and timezone list helpers.
- `lib/data.functions.php`: `EventDataXMLHandler` for season XML export/import and XML-to-database mapping.
- `lib/database.php`: mysqli connection lifecycle, query/prepared-statement wrappers, result casting, and DB metadata helpers.
- `lib/database.maintenance.php`: maintenance flag parsing, runtime-state locks, blocked maintenance responses, and controlled automatic DB-upgrade coordination.
- `lib/debug.functions.php`: lightweight debug print helpers.
- `lib/game.functions.php`: game CRUD, results, scoresheet events, media links, player assignments, scheduling, live timing, and spirit table output.
- `lib/image.functions.php`: uploaded image lookup/removal plus JPEG conversion and thumbnail generation.
- `lib/include_only.guard.php`: include-time guard for files that must not be invoked directly.
- `lib/location.functions.php`: location CRUD, search payloads, and localized location info text.
- `lib/logging.functions.php`: audit/event logs, visitor/page-load logs, and helper log writers per domain action.
- `lib/player.functions.php`: player/profile CRUD, roster/profile sync, player stats, profile media, and CSV export.
- `lib/plugin.functions.php`: plugin manifest lookup by category/type/format.
- `lib/pool.functions.php`: pool/template CRUD, pool teams and games, standings-derived moves, playoff generation, swiss helpers, and CSV export.
- `lib/privacy.functions.php`: privacy export, anonymization, deletion, and audit helpers for registered-user and player data.
- `lib/reservation.functions.php`: reservation/field CRUD, scheduled-game lookups, unscheduled-team helpers, and delete checks.
- `lib/search.functions.php`: reusable search form builders and result renderers for seasons, series, pools, teams, users, players, reservations, and games.
- `lib/season.functions.php`: season CRUD, season relations, reservations, admins/roles, readonly mode, and deletion checks.
- `lib/seasonpoints.functions.php`: season-points round CRUD, round scoring, and per-series totals.
- `lib/series.functions.php`: series CRUD, team enrollment, series scoreboards, related games/pools, and team copy helpers.
- `lib/session.functions.php`: secure session start/regenerate/destroy helpers and HTTPS detection.
- `lib/spirit.functions.php`: spirit mode/category config, submission/token flows, visibility rules, Spiritkeeper helpers, aggregates, rebuilds, and CSV export.
- `lib/standings.functions.php`: pool standings resolution, tie-breakers, swiss ranking, and standings lookup helpers.
- `lib/statistical.functions.php`: precomputed season/series/team/player stats reads and stat rebuild routines.
- `lib/swissdraw.functions.php`: swissdraw move resolution, duplicate-game avoidance, tie handling, and playoff/BYE checks.
- `lib/team.functions.php`: team/roster CRUD, team stats, team profile/media, standings/move views, and CSV export.
- `lib/timetable.functions.php`: timetable grouping/render helpers, conflict detection, move-time management, and timetable CSV export.
- `lib/translation.functions.php`: DB-backed translation loading, CRUD, autocomplete translation helpers, and translated field widgets.
- `lib/url.functions.php`: generic URL/media/mail CRUD for owners and game media lookups.
- `lib/user.functions.php`: authentication, password hashing/reset, registration/email confirmation, session setup, user roles, permission checks, and responsibility helpers.
- `lib/view.guard.php`: include-time view guard for routed pages.
- `lib/yui.functions.php`: thin wrapper for loading YUI assets.

### 3rd party libraries

- `lib/feed_generator/`: third-party RSS/Atom feed writer library (`FeedWriter`/`FeedItem`); treat the folder as vendor code.
- `lib/hsvclass/`: third-party `HSVClass` plus RGB/HSV conversion helpers for color math; treat the folder as vendor code.
- `lib/phpqrcode/`: third-party QR code generation library with bundled encoder, PNG/vector output, and support modules; prefer using the existing library entrypoints rather than editing internals.
- `lib/tfpdf/`: third-party Unicode PDF library plus `cellfit` helper, bundled font metrics, and generated Unicode font metadata; treat the whole tree as vendor PDF infrastructure.
- `lib/yuiloader/`: third-party YUI PHP loader/combo service with bundled version metadata; use it as legacy asset-loading infrastructure rather than app-specific business logic.

## Working rules

- Keep SQL and shared data access in `lib/`.
- Use the existing `?view=...` routing pattern for new pages.
- Prefer small, focused changes and avoid large refactors unless explicitly requested.
- Avoid touching `conf/` unless required.
- Keep edits ASCII unless the file already uses Unicode.
- If making UI changes, verify both desktop and mobile layouts.
- After adding or changing user-facing text, run `docs/ai/review-user-language/SKILL.md` as a final review step on your changes.
- After adding or changing database-related functionality, run `docs/ai/review-database-access/SKILL.md` as a final review step on your changes.
- If you add new player data or registered-user data, update the privacy tools and documentation so the new data is covered by the relevant privacy export and anonymization or deletion flow.
- If you present a plan for work that changes user-facing text or database access, include the relevant review-skill checks as final plan steps.
- When adding a new `SYSTEM_FLAG` or `INSTALLATION_SETTING`, ask the user whether it should be added to the installation process, and cover `install.php` if the answer is yes.
- When adding a new markdown document under `docs/`, also add it to the topic lists in both `AGENTS.md` and `docs/README.md`.
- Keep the root `README.md` pointing to `docs/README.md` as the documentation index instead of maintaining a parallel topic list there.

## Verification

- No automated test suite is documented.
- If local `php` is not available, use the Docker-based local development environment from `docs/local-development.md`, preferably the optional `dev` workspace, for PHP linting, checker scripts, and other CLI verification.
- Start the workspace with `docker compose -f docs/dev/compose.yaml --profile devtools up --build dev` and run commands with `docker compose -f docs/dev/compose.yaml exec -T dev ...`. If the `dev` service is unavailable but `app` is running, use `docker compose -f docs/dev/compose.yaml exec -T app ...` for equivalent PHP-based checks.
- Verify changes by running the app and exercising the relevant page flow.

## Topic docs

- `docs/README.md`: index of project documentation under `docs/`.
- `docs/local-development.md`: local Docker-based setup.
- `docs/dev/`: Docker Compose assets and image definitions used by the local development guide.
- `docs/database-upgrades.md`: schema and migration workflow.
- `docs/database-access.md`: database access boundaries, allowed helper layers, migration guidance, and checker behavior.
- `docs/configuration-flags.md`: configuration taxonomy and migration rules. Use the exact type names `SYSTEM_FLAG`, `INSTALLATION_SETTING`, and `EVENT_SETTING`.
- `docs/api.md`: API structure, constraints, and examples.
- `docs/routing.md`: request entry points and view resolution.
- `docs/translations.md`: translation and gettext workflow.
- `docs/permissions.md`: permission storage, roles, and enforcement helpers.
- `docs/privacy.md`: privacy admin tools, export scope, and anonymization or deletion behavior by table.
- `docs/pdf-printing.md`: PDF entrypoints, purpose files, customization fallbacks, and tFPDF notes.
- `docs/schedule.md`: schedule concept, scheduling workflow, row compilation, and database tables.
- `docs/scorekeeper.md`: Scorekeeper app routing, responsibility list, live clock workflow, and related pages.
- `docs/scoresheet.md`: scoresheet concept, input paths, visualization, and database tables.
- `docs/spirit-scoring.md`: spirit score logic, comments, and related settings.
- `docs/spiritkeeper.md`: standalone Spiritkeeper app, authenticated and token access modes, and visibility rules.
- `docs/codebase-notes.md`: third-party components, PDF generation, plugins, and customization notes.
- `docs/terminology.md`: canonical Ultiorganizer terminology, aliases, and approved abbreviations.
- `docs/ai/review-user-language/SKILL.md`: read-only skill for reviewing user-facing spelling, grammar, and terminology consistency.
- `docs/ai/fix-user-language/SKILL.md`: fix skill for page-level or term-level user-facing wording and gettext updates.
- `docs/ai/review-database-access/SKILL.md`: read-only skill for reviewing database access boundary violations and legacy cursor-style DB helper usage.
