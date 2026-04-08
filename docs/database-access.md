# Database Access Policy

This document defines how Ultiorganizer code should access the database and how the incremental checker under `docs/ai/` enforces that boundary.

## Goals

- Keep SQL and low-level database access in `lib/`.
- Keep routed and entrypoint PHP focused on request handling and rendering.
- Prefer library APIs that return plain PHP scalars, rows, and arrays over raw `mysqli_result` resources.
- Allow incremental cleanup of legacy code without blocking unrelated work.

## Layering Rules

### Routed and entrypoint PHP

The following locations are treated as public or app entrypoints:

- repo-root `*.php`
- `admin/`
- `user/`
- `mobile/`
- `scorekeeper/`
- `spiritkeeper/`
- `ext/`
- `login/`
- `api/`

Files in these locations must not:

- call `mysqli_*` functions directly
- call low-level wrappers such as `DBQuery`, `DBPrepare`, `DBStmt*`, `DBQueryTo*`, `DBFetch*`, `DBNumRows`, `DBDataSeek`, or `DBInsertId`

Instead, they should call domain helpers from `lib/*.functions.php` and work with normal PHP values.

### `lib/`

`lib/` is the database access layer.

- SQL belongs in `lib/`.
- New read helpers should prefer:
  - `DBQueryToValue()` for a scalar
  - `DBQueryToRow()` for one row
  - `DBQueryToArray()` for a list of rows
- `DBQuery()` should be reserved for cases where a cursor-style result is truly needed.

The long-term goal is to keep direct `mysqli_*` usage inside [lib/database.php](/home/kari/code/ultiorganizer/lib/database.php) and a small number of documented legacy exceptions while existing cursor-heavy helpers are migrated.

## Exception Categories

These files are exempt from page-layer enforcement because they are DB infrastructure or installation/upgrade code:

- `lib/database.php`
- `install.php`
- `sql/upgrade_db.php`

Some existing public/app files still violate the rule. Those are tracked in `docs/ai/db-access-allowlist.txt` until they are migrated. New files must not be added to that allowlist.

## Migration Recipe

When converting a routed or entrypoint file away from raw MySQL result handling:

1. Move the query into an appropriate `lib/*.functions.php` helper if it is not already there.
2. Change that helper to return a PHP scalar, row, or array unless cursor semantics are genuinely required.
3. Update the caller to iterate over the returned PHP structure instead of calling `mysqli_fetch_assoc()`, `mysqli_num_rows()`, or `mysqli_data_seek()`.
4. If the helper still needs a cursor during transition, keep the cursor handling inside `lib/`, not in the page.
5. Remove the file from `docs/ai/db-access-allowlist.txt` once it no longer violates the checker rules.

## Checker Behavior

`docs/ai/check-db-access.php` supports two modes:

- `php docs/ai/check-db-access.php --all`
  - scans the repository for current violations and backlog signals
- `php docs/ai/check-db-access.php --changed`
  - scans changed PHP files from git
  - if you pass file paths after `--changed`, only those files are scanned

Reported rule groups:

- `forbidden-mysqli`
  - direct `mysqli_*` function calls in routed or entrypoint PHP
- `forbidden-low-level-db-call`
  - direct calls to low-level DB wrappers in routed or entrypoint PHP
- `legacy-lib-cursor-api`
  - backlog signals in `lib/`, currently `return DBQuery(...)` and `@return mysqli_result`

Only non-allowlisted `forbidden-mysqli` and `forbidden-low-level-db-call` findings fail the checker in v1. `legacy-lib-cursor-api` is report-only for now.
