# AGENTS.md

Root guidance for coding agents. Keep this file short; detailed topic docs live under `docs/`.

## Project overview

- Ultiorganizer is a PHP web app for online Ultimate tournament score keeping.
- Main entry point is `index.php`; root pages are routed via `?view=...`.
- Shared utilities and SQL-backed data access live in `lib/`.
- Access-controlled areas live in `admin/` and `user/`.

## Tech Stack

- **PHP** (8.3 in CI) for the app, the standalone apps, and the repo checker scripts.
- Hand-written **ES5** JavaScript under `script/` (ESLint 9) and plain **CSS**, mainly under `cust/` (Stylelint). Both linters run in the `dev` Docker image.
- **Shell** scripts under `docs/` for release packaging and gettext catalogs; one **Python** helper in `docs/ai/analyze-lib-functions/`.
- **Markdown** docs under `docs/` — keep them in sync with code changes.

## Repository layout

- `admin/`: admin-only pages.
- `user/`: logged-in user pages.
- `lib/`: shared utilities; SQL belongs here.
- `api/`: JSON API entry points and routing.
- `cust/`: skins and installation-specific customizations. `default`, `slkl` and `wfdf` are maintained; the others are unmaintained legacy — see `docs/customization.md`.
- `mobile/`, `scorekeeper/`, `spiritkeeper/`, `timekeeper/`, `login/`, `ext/`: specialized entry points. `mobile/` is deprecated legacy; `scorekeeper/` and `spiritkeeper/` replace it. `timekeeper/` is a public WFDF time-limit aid (`docs/timekeeper.md`).
- `images/`, `locale/`, `plugins/`: static assets, translations, and plugin code.
- `script/`: client-side JavaScript assets.
- `conf/`: server configuration; keep writable only during install.
- `sql/`: schema and upgrade assets.

## Working rules

### Code

- Follow `docs/code-style.md` (PER-CS 2.0). Run `composer format` and `composer lint` on changed files before handing back work. The pre-commit hook in `.githooks/` runs only after `git config core.hooksPath .githooks`; don't assume it's active.
- This is not an npm project. Install Node-based dev tooling inside the `dev` Docker image and ship only its config file at the repo root (e.g. `eslint.config.js`); never add a root `package.json`.
- Keep SQL and shared data access in `lib/`. Reuse existing helpers first; `docs/lib-index.md` maps them.
- `lib/*.functions.php` is a shared interface, not a scratchpad. A function called from outside `lib/` belongs there even with one caller; a helper whose callers are all in the same file should be inlined. Search by behavior and table name before adding one — the same query already exists under several names (`getTeamSeries()` and `TeamSeason()`).
- Put permission checks inside reusable `lib/` mutation helpers, not only in page handlers.
- Use the `?view=...` routing pattern for new pages.
- Prefer small, focused changes; no large refactors unless asked.
- Keep comments proportionate. A small edit needs no comment; the reasoning belongs in the commit message. Comment only what the code cannot say, such as a non-obvious invariant or a deliberate deviation. Reserve short docblocks for new shared helpers.
- Avoid touching `conf/` unless required. Values in `conf/config.inc.php` (`DB_DATABASE`, `CUSTOMIZATIONS`, `BASEURL`, upload paths) describe one installation: read them at the point of use, never hardcode or remember them.
- Keep edits ASCII unless the file already uses Unicode.

### Database

- Schema change: add `upgradeXX()` in `sql/upgrade_db.php`, bump `DB_VERSION` in `lib/database.php`, and update `sql/ultiorganizer.sql` including its `uo_database` seed row. Guard every structural change so a rerun is safe. Then run `docs/ai/db-upgrade-consistency/SKILL.md`. See `docs/database-upgrades.md`.
- If the branch already adds the latest unmerged upgrade, fold further schema changes into it and ask the developer to reset the local database.
- After database-related changes, run `docs/ai/review-database-access/SKILL.md`.
- New player or registered-user data must be covered by the privacy export and anonymization/deletion flows. Classify new tables in `docs/ai/privacy-coverage/tables.txt` and run `docs/ai/privacy-coverage/SKILL.md`.

### UI, text, and CSS

- Verify UI changes in both desktop and mobile layouts.
- After CSS changes, run `docs/ai/css-style-and-lint/SKILL.md`.
- After user-facing text changes, delegate the review to the `grammar-terminology-reviewer` subagent (it runs `docs/ai/review-user-language/SKILL.md` and `docs/ai/fix-user-language/SKILL.md`). If unavailable, run the review skill directly.
- Reuse existing translated strings instead of adding synonyms or capitalization/punctuation variants.
- For compact standings or statistics tables, use the column-abbreviation and legend API in `lib/common.functions.php` (`ColumnAbbr` and friends) instead of inlining headers; register a new column as a `case` in `ColumnAbbr()`. See `docs/lib-index.md` and `docs/terminology.md`.
- After changing a playoff layout under `cust/*/layouts/` or the placeholder contract in `lib/pool.functions.php`, run `docs/ai/review-playoff-layouts/SKILL.md`.
- After PHP changes, run `docs/ai/format-and-lint/SKILL.md`.

### Process

- A plan that changes user-facing text or database access ends with the relevant review-skill steps.
- When adding a `SYSTEM_FLAG` or `INSTALLATION_SETTING`, ask whether it belongs in the installation process; if so, cover `install.php`.
- A new markdown document under `docs/` goes in the topic lists of both `AGENTS.md` and `docs/README.md`. The root `README.md` points to `docs/README.md` rather than keeping its own topic list.
- Decide whether each new file or directory belongs in the release package. Runtime files must be included by `docs/release/build-release.sh`; development-only files must be excluded with `.gitattributes` `export-ignore`. When changing release-relevant paths, run `build-release.sh` and inspect the package; `docs/ai/test-release-install/SKILL.md` goes further and installs it. Classify every new top-level path in `docs/ai/release-package-coverage/inventory.txt` and run `docs/ai/release-package-coverage/SKILL.md`.
- A new top-level app directory also goes in the scan list of `docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh`, and in `menufunctions.php` if it needs a menu entry.

## Verification

- The production test suite is the separate [`ktolonen/ultiorganizer-tests`](https://github.com/ktolonen/ultiorganizer-tests) harness. Clone it as a sibling checkout (the CI layout and the harness default): `git clone https://github.com/ktolonen/ultiorganizer-tests.git ../ultiorganizer-tests`
- From that checkout, on an up-to-date `main`: `./doctor` (needs Docker), `./test:quick` day to day, `./test:integration` after any `lib/` change (it pins exact output), and `./test:matrix` before declaring branch work done. `--sut-path <path>` tests another worktree.
- `php -l <file.php>`; `composer format` (`format:check`); `composer lint` (PHPStan with `phpstan-baseline.neon`); `composer check` runs both checks.
- ESLint and Stylelint run only in the `dev` container: `docker compose -f docs/dev/compose.yaml exec -T dev eslint script` and `... dev stylelint "cust/**/*.css"` (`--fix` on changed files).
- Repo checkers:
  - `php docs/ai/review-database-access/scripts/check-db-access.php --changed` (or `--all`)
  - `php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php`
  - `php docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php`
  - `php docs/ai/release-package-coverage/scripts/check-release-coverage.php`
  - `php docs/ai/privacy-coverage/scripts/check-privacy-coverage.php`
- Refresh gettext catalogs after changing translated strings: `./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh`
- Without local `php`, use the Docker environment from `docs/local-development.md`: `docker compose -f docs/dev/compose.yaml --profile devtools up --build dev`, then `docker compose -f docs/dev/compose.yaml exec -T dev ...` (or `exec -T app ...` if only `app` runs).
- Exercise the relevant page flow in the running app.
- After a change that alters SQL or query results, verify empirically rather than by reasoning: run the old and new query against the real database, compare the output, and report the row counts (`docs/ai/query-database/SKILL.md`).
- After UI or report changes, confirm desktop and mobile with `docs/ai/screenshot-verify/SKILL.md` before committing. Screenshots are the evidence.

## CI

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on pushes to `master` and on pull requests: `composer check`, `composer audit`, ESLint, the DB-access and playoff-layout checkers, a release-package smoke build, and the full harness matrix (report uploaded as the `harness-reports` artifact). CI decides what may merge; pre-commit hooks are the fast local gate.

## Documentation tone

- README and public docs are generic and product-focused. Author and contributor credits belong in `README.md`.
- Never put real personal data in docs, examples, or screenshots — no real names, contact details, or rows from a live database. Use placeholders such as `Team A` or `Player 1`.

## Topic docs

- `docs/README.md`: index of project documentation under `docs/`.

### Core architecture

- `docs/architecture.md`: bird's-eye orientation — the core/surfaces shape, request lifecycle, domain model, and cross-cutting layers. Start here.
- `docs/api.md`: API structure, constraints, and examples.
- `docs/codebase-notes.md`: third-party components, PDF generation, plugins, and customization notes.
- `docs/customization.md`: skin color token system, recoloring a skin with tokens, the dark-mode approach, and customization verification.
- `docs/lib-index.md`: file-by-file map of shared helpers and third-party libraries under `lib/`.
- `docs/routing.md`: request entry points and view resolution.
- `docs/runtime-cache.md`: request-local helper caching guidance and database-log recapture commands.
- `docs/persistent-cache.md`: cross-request TTL cache helper API, configuration, stampede control, and invalidation guidance.
- `docs/deployment.md`: production release package and installation guidance.
- `docs/local-development.md`: local Docker-based setup and test harness setup.
- `docs/dev/`: Docker Compose assets and image definitions used by the local development guide.
- `docs/code-style.md`: PHP code style conventions, formatter and linter setup, and pre-commit hook.

### Data, configuration, and security

- `docs/database-upgrades.md`: schema and migration workflow.
- `docs/database-access.md`: database access boundaries, allowed helper layers, migration guidance, and checker behavior.
- `docs/configuration-flags.md`: configuration taxonomy and migration rules. Use the exact type names `SYSTEM_FLAG`, `INSTALLATION_SETTING`, and `EVENT_SETTING`.
- `docs/permissions.md`: permission storage, roles, enforcement helpers, and spirit-director behavior.
- `docs/privacy.md`: privacy admin tools, export scope, and anonymization or deletion behavior by table.

### Competition workflow

- `docs/scoresheet-history.md`: scoresheet change history, snapshot boundaries, and the restore contract.
- `docs/playoff-templates.md`: playoff bracket template grammar, lookup, move-comment block, BYE handling, and pool generation.
- `docs/ranking.md`: pool ranking resolvers per pool type, tie-break order, special-ranking overrides, and event final-standings rendering.
- `docs/schedule.md`: schedule concept, scheduling workflow, row compilation, and settings.

### Scorekeeping and spirit

- `docs/scorekeeper.md`: Scorekeeper app routing, responsibility list, live clock workflow, and related pages.
- `docs/scoresheet.md`: scoresheet concept, input paths, parallel editing, and replay views.
- `docs/spirit-scoring.md`: spirit score logic, comments, and related settings.
- `docs/spiritkeeper.md`: standalone Spiritkeeper app, authenticated and token access modes, and visibility rules.
- `docs/timekeeper.md`: standalone Timekeeper app, template-based time limits, signal timers, and the game clock.

### Language and output

- `docs/pdf-printing.md`: PDF entrypoints, purpose files, customization fallbacks, and tFPDF notes.
- `docs/translations.md`: translation and gettext workflow.
- `docs/terminology.md`: canonical Ultiorganizer terminology, aliases, and approved abbreviations.

### AI review assets

- `docs/ai/review-user-language/SKILL.md`: read-only skill for reviewing user-facing spelling, grammar, and terminology consistency.
- `docs/ai/fix-user-language/SKILL.md`: fix skill for page-level or term-level user-facing wording and gettext updates.
- `docs/ai/review-database-access/SKILL.md`: read-only skill for reviewing database access boundary violations and legacy cursor-style DB helper usage.
- `docs/ai/db-upgrade-consistency/SKILL.md`: read-only skill for reviewing agreement between `DB_VERSION`, the `upgradeNN()` steps, and the fresh-install schema seed.
- `docs/ai/release-package-coverage/SKILL.md`: read-only skill for reviewing release packaging classification and registration of new top-level paths.
- `docs/ai/privacy-coverage/SKILL.md`: read-only skill for reviewing privacy export, anonymization, and deletion coverage of schema tables.
- `docs/ai/review-playoff-layouts/SKILL.md`: read-only skill for reviewing playoff bracket layout placeholders, widths, and the move-comment block.
- `docs/ai/css-style-and-lint/SKILL.md`: fix skill for CSS style consistency analysis, Stylelint checks, and safe stylesheet fixes.
- `docs/ai/format-and-lint/SKILL.md`: fix skill that runs PHP-CS-Fixer and PHPStan on changed PHP files and applies safe fixes.
- `docs/ai/analyze-lib-functions/SKILL.md`: analysis skill for lib PHP function usage counts and dead-code candidate triage.
- `docs/ai/screenshot-verify/SKILL.md`: verification skill that takes Chromium screenshots and measures element layout inside the dev container.
- `docs/ai/query-database/SKILL.md`: read-only skill for running ad-hoc SQL against the local dev database to investigate data-driven behavior.
- `docs/ai/test-release-install/SKILL.md`: verification skill that builds a release package and installs it with `install.php` in an isolated Docker stack.
