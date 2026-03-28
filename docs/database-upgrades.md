# Database Upgrades

This page mirrors the database-change guidance from `AGENTS.md`.

## Base rules

- Base schema lives in `sql/ultiorganizer.sql`.
- Production upgrades are handled by versioned functions in `sql/upgrade_db.php`.
- `CheckDB()` in `lib/database.php` runs upgrade steps on startup.
- Bump `DB_VERSION` whenever a new upgrade step is added.

## Required workflow

1. Pick the next version number from the latest `upgradeXX()` in `sql/upgrade_db.php`.
2. Add `upgradeXX()` with the required `ALTER`, `CREATE`, or related statements.
3. Update `define('DB_VERSION', XX);` in `lib/database.php`.
4. Update `sql/ultiorganizer.sql` so fresh installs include the final structure.
5. Update related SQL and data access in `lib/` if PHP reads or writes the changed fields.
6. Verify both the upgrade path and clean install path.

## Rules of thumb

- Prefer additive, backward-compatible changes.
- Use nullable columns or defaults when possible.
- If a destructive change is required, migrate data before removing or renaming old structures.
- Do not rely on manual production DB edits outside the schema file and upgrade functions.
