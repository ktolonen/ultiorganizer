# Architecture

A bird's-eye orientation to how Ultiorganizer fits together. Read this first, then
follow the links into the topic docs for detail. It deliberately stays high-level and
does not duplicate them.

Ultiorganizer is a PHP web app for running Ultimate tournaments: scheduling, score
keeping, standings, spirit scoring, and public results.

## The shape: one core, many surfaces

There is a single shared core — `index.php`, the `lib/` helpers, and the database —
and several thin entry points layered on top of it:

- `index.php`: the main web app (admin and logged-in user pages).
- `scorekeeper/`, `spiritkeeper/`, `timekeeper/`: standalone game-day apps.
- `mobile/`: the deprecated legacy operator interface, kept only for compatibility.
- `api/`: the JSON API.
- `ext/`: public external outputs (RSS, CSV, XML, widgets).
- `login/`: the shared login surface.

The surfaces differ mostly in routing and UI; they all reach the same data through
`lib/`. See [routing.md](routing.md) for the full entry-point list and guards.

## Request lifecycle

A typical main-app request flows like this:

1. `index.php` boots and defines `UO_ROUTED_VIEW`.
2. It resolves the page from the `?view=...` query parameter.
3. The page's area auth wrapper (`admin/auth.php`, `user/auth.php`, ...) enforces
   access; include-only files are protected by the `*.guard.php` guards.
4. The page handler orchestrates the work but holds no SQL of its own.
5. All SQL and data access live in `lib/*.functions.php`, which call the wrappers in
   `lib/database.php`.
6. `lib/database.php` serves reads through two caches when applicable (see below).
7. Output is rendered with `localization.php` — gettext strings, `U_()` translations,
   and the skin's CSS token cascade.

The page-layer-must-not-touch-SQL rule is enforced; see [database-access.md](database-access.md).

## Domain model spine

The competition data nests like this:

```
event (season) -> division (series) -> pool -> game <-> teams / players
```

- User-facing **event** / **division** are `season` / `series` in code and the schema
  (see [terminology.md](terminology.md) for the naming mismatch).
- A **pool** has one of four types (round-robin, playoff, swiss, cross-match), each
  with its own ranking resolver writing `activerank`; see [ranking.md](ranking.md).
- `uo_game_pool` is the source of truth for which pool(s) a game belongs to; the game
  row itself carries no pool. Scheduling is built on top; see [schedule.md](schedule.md).
- The detailed game record (roster, goals, events, timeouts, note) is a concept
  spanning several tables, not one table; see [scoresheet.md](scoresheet.md).

A file-by-file map of the helpers lives in [lib-index.md](lib-index.md).

## Cross-cutting layers

Concerns that run through most requests, each with its own doc:

- **Auth & permissions** — roles and enforcement helpers: [permissions.md](permissions.md).
- **Configuration** — the SYSTEM / INSTALLATION / EVENT taxonomy: [configuration-flags.md](configuration-flags.md).
- **Translations** — gettext, static localized files, and DB-backed `U_()`: [translations.md](translations.md).
- **Caching** — request-local ([runtime-cache.md](runtime-cache.md)) and cross-request TTL ([persistent-cache.md](persistent-cache.md)).
- **Customization** — skins and the CSS color-token cascade: [customization.md](customization.md).
- **Database changes** — versioned upgrades and migrations: [database-upgrades.md](database-upgrades.md).

## Where to go next

[README.md](README.md) is the full index of topic docs. For implementation details
that do not belong in any single subsystem doc, see [codebase-notes.md](codebase-notes.md).
