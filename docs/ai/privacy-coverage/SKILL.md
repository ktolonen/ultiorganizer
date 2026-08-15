---
name: privacy-coverage
description: Read-only review skill for Ultiorganizer privacy tooling coverage. Use after adding a table or column that stores player, registered-user, or other personal data, and after changing the privacy export, anonymization, or deletion flows. Verify every schema table is classified as personal or not, and that personal tables are reachable from lib/privacy.functions.php. Run the bundled checker first, then report findings without editing files.
metadata:
  short-description: Review privacy export and deletion coverage of schema tables
---

# Privacy Coverage

Review whether the privacy tools reach every table holding personal data, without editing files.

Always read this reference first:

- `docs/privacy.md`

## Purpose

Personal data is spread across many tables, and the admin privacy tools in
`lib/privacy.functions.php` must reach all of them for export, anonymization,
and deletion to be complete.

Nothing in the schema marks which tables hold personal data. A new table or a
new column is therefore invisible to the privacy flow until somebody remembers
it, and the omission surfaces only when a data subject makes a request.

`docs/ai/privacy-coverage/tables.txt` closes that gap by classifying every table
explicitly, so a new table has to be triaged rather than defaulting to "not
personal".

This skill reports findings only. It must not apply fixes.

## Review Scope

Run this skill as a final review step after:

- adding a table or column that stores player or registered-user data
- changing `lib/privacy.functions.php`
- changing the privacy admin pages under `admin/`
- adding a schema upgrade that introduces person-linked data

## Checker Workflow

Run the bundled checker before manual review:

- `php docs/ai/privacy-coverage/scripts/check-privacy-coverage.php`

Add `-v` to print the classification of every table.

The checker reports:

- `ERROR` — a schema table missing from `tables.txt`
- `ERROR` — a table classified `personal` that `lib/privacy.functions.php` never references
- `ERROR` — a table classified `personal-exempt` with no reason note
- `ERROR` — a table classified `none` that has an obviously personal column
- `WARNING` — a stale classification line for a dropped table

## Classification Kinds

When a new table appears, add one line to `tables.txt`:

- `personal` — holds personal data reachable from a data subject; must be referenced by `lib/privacy.functions.php`
- `personal-exempt` — holds personal data but is intentionally outside automated coverage; requires a trailing `# reason` note
- `none` — holds no personal data

## Manual Review Rules

The checker proves a table is *mentioned* in the privacy library. It cannot
prove the handling is correct, so confirm by reading:

- export — does `PrivacyCollectPlayerReportData()` or `PrivacyCollectUserReportData()` actually return the new rows?
- anonymization — does `PrivacyAnonymizePlayer()` clear or overwrite the identifying columns rather than only the row's owner reference?
- deletion — does `PrivacyDeleteUserData()` remove the rows, and does anything reference them afterwards?
- free-text columns — story, achievements, comment, coach, and captain fields can contain names that no per-subject query will ever find
- new data added to an existing covered table still needs the export renderer updated, which no table-level check can detect

Also confirm `docs/privacy.md` documents the new data, as `AGENTS.md` requires.

## Output

Report findings only.

Each finding should include:

- the table and its classification
- which flow is incomplete: export, anonymization, or deletion
- what a data subject would be able to observe as a result
- the smallest change that closes the gap

Keep the review concise and actionable. Do not produce patches by default.
