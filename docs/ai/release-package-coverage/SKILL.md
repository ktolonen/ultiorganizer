---
name: release-package-coverage
description: Read-only review skill for Ultiorganizer release packaging and new top-level path registration. Use after adding, removing, or renaming a top-level file or directory, after editing .gitattributes export-ignore rules, or after adding a new standalone app directory. Verify each tracked top-level path is classified as runtime or development-only, that its export-ignore state matches, and that app directories reach the gettext catalogs. Run the bundled checker first, then report findings without editing files.
metadata:
  short-description: Review release packaging and top-level path registration
---

# Release Package Coverage

Review which paths ship in the production release package, without editing files.

Always read these references first:

- `docs/deployment.md`
- the classification list at `docs/ai/release-package-coverage/inventory.txt`

## Purpose

`docs/release/build-release.sh` decides what ships by asking git for the
`export-ignore` attribute of each tracked file. Anything not marked
`export-ignore` is copied into the package.

The default is therefore "ship it". A new top-level path that nobody classified
silently leaks development files into a release, and a runtime path with an
over-broad `.gitattributes` rule silently disappears from it. Neither failure
produces an error at build time.

A new top-level app directory has more than one registration point, and the
release package is only the first:

- `.gitattributes` — whether it ships
- `docs/release/build-release.sh` `required_paths` — whether a missing app directory fails the release build's own smoke check
- `docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh` — whether its strings reach the translation catalogs
- `menufunctions.php` — whether users can navigate to it
- `AGENTS.md` and `docs/README.md` — whether it is documented

This skill reports findings only. It must not apply fixes.

## Review Scope

Run this skill as a final review step after:

- adding, removing, or renaming any top-level file or directory
- adding a new standalone app directory such as `scorekeeper/` or `timekeeper/`
- editing `.gitattributes`
- editing `docs/release/build-release.sh`

## Checker Workflow

Run the bundled checker before manual review:

- `php docs/ai/release-package-coverage/scripts/check-release-coverage.php`

Add `-v` to print the classification of every path.

The checker reports:

- `ERROR` — a tracked top-level path missing from `inventory.txt`
- `ERROR` — a path classified `dev` that is not export-ignored, so it would ship
- `ERROR` — a path classified `runtime` or `runtime-app` that is export-ignored, so it is missing from the package
- `ERROR` — a path that mixes export-ignored and shipped files
- `ERROR` — a `runtime-app` directory absent from the gettext scan list
- `ERROR` — a `runtime-app` directory absent from `build-release.sh`'s `required_paths` smoke check
- `WARNING` — a stale inventory line for a path that is no longer tracked

## Classification Kinds

When a new path appears, add one line to `inventory.txt`:

- `runtime` — ships; no tracked file under it is export-ignored
- `runtime-app` — ships, and its PHP contains translatable strings, so it must also appear in the gettext scan list and in `build-release.sh`'s `required_paths`
- `dev` — development-only; every tracked file under it must be export-ignored

## Manual Review Rules

After the checker output, confirm the parts no checker can decide:

- whether a newly classified path genuinely belongs in a production install
- whether a new app directory needs a menu entry in `menufunctions.php`
- whether the release package actually contains the expected files: run `docs/release/build-release.sh` and inspect the archive
- whether new documentation is listed in both `AGENTS.md` and `docs/README.md`

## Output

Report findings only.

Each finding should include:

- the path and its declared or missing classification
- which registration point is inconsistent
- whether a release would gain unwanted files or lose required ones
- the smallest change that resolves it

Keep the review concise and actionable. Do not produce patches by default.
