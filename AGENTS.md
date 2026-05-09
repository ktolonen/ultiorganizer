# AGENTS.md

Root guidance for coding agents. Keep this file short; detailed topic docs live under `docs/`.

## Project overview

- Ultiorganizer is a PHP web app for online Ultimate tournament score keeping.
- Main entry point is `index.php`; root pages are routed via `?view=...`.
- Shared utilities and SQL-backed data access live in `lib/`.
- Access-controlled areas live in `admin/` and `user/`.

## Repository layout

- `admin/`: admin-only pages.
- `user/`: logged-in user pages.
- `lib/`: shared utilities; SQL belongs here.
- `api/`: JSON API entry points and routing.
- `cust/`: skins and installation-specific customizations.
- `mobile/`, `scorekeeper/`, `spiritkeeper/`, `login/`, `ext/`: specialized entry points. `mobile/` is a deprecated legacy interface kept for compatibility; `scorekeeper/` and `spiritkeeper/` are the supported replacements.
- `images/`, `locale/`, `plugins/`: static assets, translations, and plugin code.
- `script/`: client-side JavaScript assets.
- `conf/`: server configuration; keep writable only during install.
- `sql/`: schema and upgrade assets.

## `lib/` PHP index

Prefer reusing shared helpers in `lib/` before adding new utility code or direct SQL. See `docs/lib-index.md` for the file-by-file library map and third-party library notes.

## Working rules

- Follow the PHP code style described in `docs/code-style.md` (PER-CS 2.0). Run `composer format` and `composer lint` on changed files before handing back work; the pre-commit hook at `.githooks/pre-commit` enforces this on commit.
- Keep SQL and shared data access in `lib/`.
- Put permission checks inside reusable `lib/` mutation helpers, not only in routed page handlers, so future callers cannot accidentally bypass access control.
- Use the existing `?view=...` routing pattern for new pages.
- Prefer small, focused changes and avoid large refactors unless explicitly requested.
- When adding a schema change: add `upgradeXX()` in `sql/upgrade_db.php`, bump `DB_VERSION` in `lib/database.php`, and update `sql/ultiorganizer.sql` for fresh installs. See `docs/database-upgrades.md`.
- Avoid touching `conf/` unless required.
- Keep edits ASCII unless the file already uses Unicode.
- If making UI changes, verify both desktop and mobile layouts.
- After adding or changing user-facing text, run `docs/ai/review-user-language/SKILL.md` as a final review step on your changes.
- Reuse existing translated strings when feasible instead of adding synonyms, capitalization-only variants, or comma/punctuation-only variants.
- After adding or changing database-related functionality, run `docs/ai/review-database-access/SKILL.md` as a final review step on your changes.
- After adding or changing a playoff bracket layout under `cust/*/layouts/`, or the placeholder contract in `lib/pool.functions.php`, run `docs/ai/review-playoff-layouts/SKILL.md` as a final review step on your changes.
- After adding or changing PHP code, run `docs/ai/format-and-lint/SKILL.md` to apply PER-CS 2.0 formatting and surface PHPStan findings on the changed files.
- If you add new player data or registered-user data, update the privacy tools and documentation so the new data is covered by the relevant privacy export and anonymization or deletion flow.
- If you present a plan for work that changes user-facing text or database access, include the relevant review-skill checks as final plan steps.
- When adding a new `SYSTEM_FLAG` or `INSTALLATION_SETTING`, ask the user whether it should be added to the installation process, and cover `install.php` if the answer is yes.
- When adding a new markdown document under `docs/`, also add it to the topic lists in both `AGENTS.md` and `docs/README.md`.
- Keep the root `README.md` pointing to `docs/README.md` as the documentation index instead of maintaining a parallel topic list there.

## Verification

- No automated test suite is documented.
- PHP syntax check a single file: `php -l <file.php>`
- Format changed PHP: `composer format` (check-only: `composer format:check`)
- Static analysis: `composer lint` (uses `phpstan-baseline.neon` for legacy findings)
- Combined format-check + lint: `composer check`
- DB access boundary check (changed files): `php docs/ai/review-database-access/scripts/check-db-access.php --changed`
- DB access boundary check (full repo): `php docs/ai/review-database-access/scripts/check-db-access.php --all`
- Playoff layout templates (all): `php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php`
- Refresh gettext catalogs after changing translated strings: `./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh`
- If local `php` is not available, use the Docker-based local development environment from `docs/local-development.md`, preferably the optional `dev` workspace, for PHP linting, checker scripts, and other CLI verification.
- Start the workspace with `docker compose -f docs/dev/compose.yaml --profile devtools up --build dev` and run commands with `docker compose -f docs/dev/compose.yaml exec -T dev ...`. If the `dev` service is unavailable but `app` is running, use `docker compose -f docs/dev/compose.yaml exec -T app ...` for equivalent PHP-based checks.
- Verify changes by running the app and exercising the relevant page flow.

## Topic docs

- `docs/README.md`: index of project documentation under `docs/`.

### Core architecture

- `docs/api.md`: API structure, constraints, and examples.
- `docs/codebase-notes.md`: third-party components, PDF generation, plugins, and customization notes.
- `docs/lib-index.md`: file-by-file map of shared helpers and third-party libraries under `lib/`.
- `docs/routing.md`: request entry points and view resolution.
- `docs/local-development.md`: local Docker-based setup.
- `docs/dev/`: Docker Compose assets and image definitions used by the local development guide.
- `docs/code-style.md`: PHP code style conventions, formatter and linter setup, and pre-commit hook.

### Data, configuration, and security

- `docs/database-upgrades.md`: schema and migration workflow.
- `docs/database-access.md`: database access boundaries, allowed helper layers, migration guidance, and checker behavior.
- `docs/configuration-flags.md`: configuration taxonomy and migration rules. Use the exact type names `SYSTEM_FLAG`, `INSTALLATION_SETTING`, and `EVENT_SETTING`.
- `docs/permissions.md`: permission storage, roles, and enforcement helpers.
- `docs/privacy.md`: privacy admin tools, export scope, and anonymization or deletion behavior by table.

### Competition workflow

- `docs/playoff-templates.md`: playoff bracket template grammar, lookup, move-comment block, BYE handling, and pool generation.
- `docs/ranking.md`: pool ranking resolvers per pool type, tie-break order, special-ranking overrides, and event final-standings rendering.
- `docs/schedule.md`: schedule concept, scheduling workflow, row compilation, and database tables.

### Scorekeeping and spirit

- `docs/scorekeeper.md`: Scorekeeper app routing, responsibility list, live clock workflow, and related pages.
- `docs/scoresheet.md`: scoresheet concept, input paths, visualization, and database tables.
- `docs/spirit-scoring.md`: spirit score logic, comments, and related settings.
- `docs/spiritkeeper.md`: standalone Spiritkeeper app, authenticated and token access modes, and visibility rules.

### Language and output

- `docs/pdf-printing.md`: PDF entrypoints, purpose files, customization fallbacks, and tFPDF notes.
- `docs/translations.md`: translation and gettext workflow.
- `docs/terminology.md`: canonical Ultiorganizer terminology, aliases, and approved abbreviations.

### AI review assets

- `docs/ai/review-user-language/SKILL.md`: read-only skill for reviewing user-facing spelling, grammar, and terminology consistency.
- `docs/ai/fix-user-language/SKILL.md`: fix skill for page-level or term-level user-facing wording and gettext updates.
- `docs/ai/review-database-access/SKILL.md`: read-only skill for reviewing database access boundary violations and legacy cursor-style DB helper usage.
- `docs/ai/review-playoff-layouts/SKILL.md`: read-only skill for reviewing playoff bracket layout placeholders, widths, and the move-comment block.
- `docs/ai/format-and-lint/SKILL.md`: fix skill that runs PHP-CS-Fixer and PHPStan on changed PHP files and applies safe fixes.
