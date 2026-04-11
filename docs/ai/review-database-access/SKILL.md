---
name: review-database-access
description: Read-only review skill for Ultiorganizer database access boundaries. Use after adding or changing PHP functionality that calls the database or changes DB access structure. Review changed PHP for page-layer DB usage, misplaced SQL outside `lib/`, low-level DB wrapper calls in routed files, or legacy cursor-style helper APIs in `lib/`. Run the bundled checker first, then report findings without editing files.
metadata:
  short-description: Review database access boundary violations
---

# Review Database Access

Review Ultiorganizer database access without editing files.

Always read this reference first:

- `docs/database-access.md`

## Purpose

Use this skill for read-only review of:

- direct `mysqli_*` usage in routed or entrypoint PHP
- low-level DB wrapper calls in routed or entrypoint PHP
- new SQL or low-level data access placed outside `lib/`
- legacy cursor-style helper APIs in `lib/`

This skill reports findings only. It must not apply fixes.

Run this skill as a final review step after implementing new or changed database-related functionality.

## Review Scope

Start with user-modified PHP in the current worktree.

Use repo state to identify the first review scope:

- `git status --short`
- `git diff --name-only`
- `git diff --cached --name-only`

Prioritize changed PHP files over the rest of the repo.

## Checker Workflow

Run the bundled checker before manual review:

- `php docs/ai/review-database-access/scripts/check-db-access.php --changed`

If the caller asked for a broader audit, or if there is no meaningful change scope, run:

- `php docs/ai/review-database-access/scripts/check-db-access.php --all`

If you only need to review specific files, pass them after `--changed`.

## Manual Review Rules

After the checker output, inspect the changed files and surrounding helpers for issues the regex rules may miss:

- routed and entrypoint PHP should call `lib/*.functions.php` helpers instead of low-level DB APIs
- SQL and result-shape decisions should stay in `lib/`
- new read helpers should prefer scalars, rows, and arrays over raw cursor returns
- new page code should not introduce fresh dependencies on `DBQuery()`, `DBFetch*()`, `DBNumRows()`, or `mysqli_*`

Treat these checker rule groups as the primary output categories:

- `Errors`: non-allowlisted `forbidden-mysqli` and `forbidden-low-level-db-call` findings
- `Warnings`: allowlisted page-layer findings, `legacy-lib-cursor-api` findings, and manual-review concerns that do not clearly violate the current blocking rules

## Output

Report findings only.

Each finding should include:

- file path
- line reference when available
- checker rule or review category
- the problematic call or boundary issue
- a short explanation of the preferred boundary

Keep the review concise and actionable. Do not produce patches by default.
