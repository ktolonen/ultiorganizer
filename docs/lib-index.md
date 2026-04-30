# Library index

Use this index to find existing shared helpers before adding new utility code or direct SQL. Application-specific data access should stay in `lib/` where possible.

## Root `lib/`

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

## Third-party libraries

Treat these folders as vendor or legacy infrastructure. Prefer using their public entry points instead of editing internals.

- `lib/feed_generator/`: third-party RSS/Atom feed writer library (`FeedWriter`/`FeedItem`).
- `lib/hsvclass/`: third-party `HSVClass` plus RGB/HSV conversion helpers for color math.
- `lib/phpqrcode/`: third-party QR code generation library with bundled encoder, PNG/vector output, and support modules.
- `lib/tfpdf/`: third-party Unicode PDF library plus `cellfit` helper, bundled font metrics, and generated Unicode font metadata.
- `lib/yuiloader/`: third-party YUI PHP loader/combo service with bundled version metadata; use it as legacy asset-loading infrastructure rather than app-specific business logic.
