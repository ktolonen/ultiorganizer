# Database Upgrades

Schema change workflow and the automatic upgrade runtime.

## Base rules

- Base schema lives in `sql/ultiorganizer.sql`.
- Production upgrades are handled by versioned functions in `sql/upgrade_db.php`.
- `CheckDB()` runs the upgrades, only through the automatic maintenance gate in `lib/database.php`.
- `upgradeXX()` means "upgrade the schema to version `XX`".
- Bump `DB_VERSION` whenever a new upgrade step is added.
- Automatic update maintenance uses `MAINTENANCE_RUNTIME_DIR/maintenance.flag` plus the transient lock file `MAINTENANCE_RUNTIME_DIR/maintenance.lock`.
- When `MAINTENANCE_RUNTIME_DIR` is undefined, `DBMaintenanceRuntimeDir()` derives `<system temp dir>/ultiorganizer-maintenance-<hash of the installation directory>`. The `install.php` status page shows the resolved path, which a hand-made `maintenance.flag` needs.

## Required workflow

1. Pick the next version number from the latest `upgradeXX()` in `sql/upgrade_db.php`.
2. Add `upgradeXX()` with the required statements, guarding each structural change so a rerun is safe.
3. Update `define('DB_VERSION', XX);` in `lib/database.php`.
4. Update `sql/ultiorganizer.sql` so fresh installs include the final structure and the matching `uo_database` version row.
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

## Local development: database ahead of the checked-out branch

Upgrades only run forward and then require `getDBVersion() === DB_VERSION` exactly. Switching to a branch with a lower `DB_VERSION` than the installed schema (e.g. from a feature branch back to `master`) is treated as a failed upgrade: an `automatic/failed` flag is written and every page shows "Database upgrade failed".

Confirm with `SELECT MAX(version) FROM uo_database` against `DB_VERSION`. Recover by running a branch that has the newest upgrade (cherry-pick it if needed), not by editing `uo_database`; deleting the flag alone does not help, since the next request recreates it.

## Rules of thumb

- Prefer additive, backward-compatible changes.
- Use nullable columns or defaults when possible.
- If a destructive change is required, migrate data before removing or renaming old structures.
- Do not rely on manual production DB edits outside the schema file and upgrade functions.
