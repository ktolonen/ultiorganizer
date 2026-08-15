---
name: db-upgrade-consistency
description: Read-only review skill for Ultiorganizer database schema versioning. Use after adding or changing a schema upgrade, bumping DB_VERSION, or editing sql/ultiorganizer.sql. Verify that DB_VERSION, the upgradeNN() migration steps, and the uo_database seed in the fresh-install schema agree, so a change installs correctly on both upgraded and fresh databases. Run the bundled checker first, then report findings without editing files.
metadata:
  short-description: Review schema version and upgrade consistency
---

# Database Upgrade Consistency

Review the Ultiorganizer schema version contract without editing files.

Always read this reference first:

- `docs/database-upgrades.md`

## Purpose

A schema change is only complete when three declarations agree:

1. `DB_VERSION` in `lib/database.php` — the version `CheckDB()` upgrades toward
2. `upgradeNN()` in `sql/upgrade_db.php` — the migration step for each version
3. the `uo_database` seed rows in `sql/ultiorganizer.sql` — the versions a fresh install starts with

`CheckDB()` loops from the installed version up to `DB_VERSION` and runs every
`upgradeNN()` not already recorded in `uo_database`. A fresh install therefore
re-runs every upgrade above the seeded maximum, even though `sql/ultiorganizer.sql`
already contains those schema changes.

That re-run is harmless while the upgrade is guarded (`hasColumn()`, `hasTable()`,
`IF NOT EXISTS`) and a failure when it is not. Guards are a convention in this
codebase, not a rule the runner enforces — several upgrades have none.

This skill reports findings only. It must not apply fixes.

## Review Scope

Run this skill as a final review step after any change to:

- `sql/upgrade_db.php`
- `lib/database.php` (the `DB_VERSION` constant or `CheckDB()`)
- `sql/ultiorganizer.sql`

## Checker Workflow

Run the bundled checker before manual review:

- `php docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php`

Add `-v` to print the three parsed version numbers, which is the fastest way to
see which declaration is out of step.

The checker reports:

- `ERROR` — the highest `upgradeNN()` is above `DB_VERSION`, so it never runs
- `ERROR` — the seeded maximum does not equal `DB_VERSION`, so fresh installs re-run upgrades
- `ERROR` — a gap in the seeded version range
- `ERROR` — an unguarded upgrade sits above the seeded version, which can fail a fresh install
- `WARNING` — a guarded upgrade re-runs on fresh installs (currently harmless)
- `WARNING` — `DB_VERSION` is above the highest upgrade function

## Manual Review Rules

After the checker output, inspect the change for issues the parser cannot see:

- a new `upgradeNN()` should guard every structural change so re-running is safe
- the same schema change should appear in both `sql/upgrade_db.php` and `sql/ultiorganizer.sql`
- column definitions should match between the upgrade and the fresh-install schema, including type, nullability, and default
- if the branch already introduces the latest unmerged upgrade, further schema changes belong in that upgrade rather than a new one, per `AGENTS.md`
- data migrations that are not idempotent need an explicit guard, since the runner offers none

## Output

Report findings only.

Each finding should include:

- the file and declaration involved
- which of the three declarations disagree, and their values
- whether a fresh install or an existing install is affected
- the smallest change that restores agreement

Keep the review concise and actionable. Do not produce patches by default.
