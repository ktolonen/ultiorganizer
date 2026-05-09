# Database Upgrades

This page mirrors the database-change guidance from `AGENTS.md`.

## Base rules

- Base schema lives in `sql/ultiorganizer.sql`.
- Production upgrades are handled by versioned functions in `sql/upgrade_db.php`.
- `CheckDB()` is still the upgrade runner, but it now runs only through the automatic maintenance gate in `lib/database.php`.
- `upgradeXX()` means "upgrade the schema to version `XX`".
- Bump `DB_VERSION` whenever a new upgrade step is added.
- Automatic update maintenance uses `MAINTENANCE_RUNTIME_DIR/maintenance.flag` plus the transient lock file `MAINTENANCE_RUNTIME_DIR/maintenance.lock`.

## Required workflow

1. Pick the next version number from the latest `upgradeXX()` in `sql/upgrade_db.php`.
2. Add `upgradeXX()` with the required `ALTER`, `CREATE`, or related statements.
3. Update `define('DB_VERSION', XX);` in `lib/database.php`.
4. Update `sql/ultiorganizer.sql` so fresh installs include the final structure.
5. Update related SQL and data access in `lib/` if PHP reads or writes the changed fields.
6. Verify both the upgrade path and clean install path.

## Runtime upgrade flow

- If the database version matches `DB_VERSION` and no maintenance flag exists, requests continue normally.
- If the database version does not match `DB_VERSION` and no maintenance flag exists, Ultiorganizer creates `MAINTENANCE_RUNTIME_DIR/maintenance.flag` in automatic mode and starts the upgrade through one serialized request.
- While the automatic flag is active, all other requests return a maintenance response until the upgrade finishes.
- If the upgrade succeeds and the flag was system-created automatic maintenance, the flag is removed automatically.
- If the upgrade fails, the flag is rewritten to `automatic/failed`, maintenance remains active, and later requests do not retry automatically.

## Maintenance flag contract

- Valid automatic flag states are strict text payloads with exact keys and order:
  ```text
  mode=automatic
  status=pending
  target=86
  ```
  ```text
  mode=automatic
  status=running
  target=86
  started_at=2026-04-09T10:00:00+00:00
  ```
  ```text
  mode=automatic
  status=failed
  target=86
  failed_at=2026-04-09T10:01:00+00:00
  error=Automatic database upgrade failed. Check server logs.
  ```
- Any other content in `MAINTENANCE_RUNTIME_DIR/maintenance.flag` is treated as manual maintenance mode.
- Manual examples include:
  empty file,
  `manual`,
  arbitrary note text,
  malformed automatic payloads.
- Manual maintenance never runs `CheckDB()` and is never cleared automatically.
- The transient lock file `MAINTENANCE_RUNTIME_DIR/maintenance.lock` is used only to serialize the updater; stale locks are recoverable after the fixed timeout in `lib/database.php`.

## Rules of thumb

- Prefer additive, backward-compatible changes.
- Use nullable columns or defaults when possible.
- If a destructive change is required, migrate data before removing or renaming old structures.
- Do not rely on manual production DB edits outside the schema file and upgrade functions.
