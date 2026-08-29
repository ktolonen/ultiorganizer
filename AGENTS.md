# AGENTS.md

Root guidance for coding agents. Keep this file short; detailed topic docs live under `docs/`.

## Project overview

- Ultiorganizer is a PHP web app for online Ultimate tournament score keeping.
- Main entry point is `index.php`; root pages are routed via `?view=...`.
- Shared utilities and SQL-backed data access live in `lib/`.
- Access-controlled areas live in `admin/` and `user/`.

## Tech Stack

- **PHP** is the primary language (PHP 8.3 in CI) — `index.php`, `lib/`, `admin/`, `user/`, the standalone apps, and the repo checker scripts are all PHP.
- **Client-side JavaScript** under `script/` is hand-written **ES5** (linted with ESLint 9 via the `dev` Docker image); page styling is plain **CSS**, primarily under `cust/` (linted with Stylelint via the `dev` Docker image).
- **Shell** scripts under `docs/` handle release packaging and gettext catalogs; one **Python** helper lives at `docs/ai/analyze-lib-functions/`.
- **Markdown** docs under `docs/` are heavily maintained — keep documentation in sync with code changes.

## Repository layout

- `admin/`: admin-only pages.
- `user/`: logged-in user pages.
- `lib/`: shared utilities; SQL belongs here.
- `api/`: JSON API entry points and routing.
- `cust/`: skins and installation-specific customizations. `default`, `slkl` and `wfdf` are maintained; `bula`, `fpudd`, `gummis` and `windmill` are unmaintained legacy kept for compatibility — see `docs/customization.md`.
- `mobile/`, `scorekeeper/`, `spiritkeeper/`, `timekeeper/`, `login/`, `ext/`: specialized entry points. `mobile/` is a deprecated legacy interface kept for compatibility; `scorekeeper/` and `spiritkeeper/` are its supported replacements, and `timekeeper/` is a standalone, public WFDF time-limit signalling aid (see `docs/timekeeper.md`).
- `images/`, `locale/`, `plugins/`: static assets, translations, and plugin code.
- `script/`: client-side JavaScript assets.
- `conf/`: server configuration; keep writable only during install.
- `sql/`: schema and upgrade assets.

## `lib/` PHP index

Prefer reusing shared helpers in `lib/` before adding new utility code or direct SQL. See `docs/lib-index.md` for the file-by-file library map and third-party library notes.

## Working rules

- Follow the PHP code style described in `docs/code-style.md` (PER-CS 2.0). Run `composer format` and `composer lint` on changed files before handing back work; the repo ships a pre-commit hook at `.githooks/pre-commit` for this, but it only runs once enabled per clone with `git config core.hooksPath .githooks` — don't assume it's active.
- Hand-written client JavaScript under `script/` (including the `script/*.inc` `<script>` snippets) is linted with ESLint (`docker compose -f docs/dev/compose.yaml exec -T dev eslint script`). The config lives at `eslint.config.js` in the repo root; the toolchain itself is installed inside the `dev` Docker image, not at the repo root.
- This is a PHP project, not an npm project: when adding Node-based dev tooling (linters, formatters, etc.), install it inside the `dev` Docker image and ship only the tool's own config file at the repo root (e.g. `eslint.config.js`). Do not add a root `package.json` — it signals an npm project to humans and tooling.
- Keep SQL and shared data access in `lib/`.
- `lib/*.functions.php` files are a shared interface, not a scratchpad. A function called from outside `lib/` belongs there even when only one page calls it — that is what the interface is for, and for a mutation it is where the permission check has to live (see the next rule). What does not belong is an internal step split out for the convenience of its neighbours: inline anything whose callers are all in the same file. Search for an existing helper by behavior and table name before adding one — the same query already exists under more than one naming convention (`getTeamSeries()` and `TeamSeason()`).
- Put permission checks inside reusable `lib/` mutation helpers, not only in routed page handlers, so future callers cannot accidentally bypass access control.
- Use the existing `?view=...` routing pattern for new pages.
- Prefer small, focused changes and avoid large refactors unless explicitly requested.
- Keep comments proportionate to the change. A small edit to existing code — a guard, a strict comparison, a cast, an extra counter — needs no comment; the reasoning belongs in the commit message. Add one only for what the code cannot say, such as a non-obvious invariant or a deliberate deviation that will later look like a mistake. Reserve docblocks for genuinely new shared helpers, and keep them short.
- When adding a schema change: add `upgradeXX()` in `sql/upgrade_db.php`, bump `DB_VERSION` in `lib/database.php`, and update `sql/ultiorganizer.sql` for fresh installs, including a `uo_database` seed row for the new version. Guard every structural change so re-running the upgrade is safe. Run `docs/ai/db-upgrade-consistency/SKILL.md` afterwards. See `docs/database-upgrades.md`.
- If the current branch already introduces the latest unmerged database upgrade, fold further schema changes into that upgrade instead of adding another one. Ask the developer to reset or clean the local database so the amended upgrade runs again.
- Avoid touching `conf/` unless required.
- Values in `conf/config.inc.php` (`DB_DATABASE`, `CUSTOMIZATIONS`, `BASEURL`, upload paths, etc.) describe one installation, not the project. Read them at the point of use in scripts and checks instead of hardcoding or remembering them — a stale assumed value still returns plausible-looking results, so nothing announces the mistake.
- Keep edits ASCII unless the file already uses Unicode.
- If making UI changes, verify both desktop and mobile layouts.
- After adding or changing CSS, run `docs/ai/css-style-and-lint/SKILL.md` to analyze style consistency and run Stylelint on the changed files.
- After adding or changing user-facing text, delegate the final review to the `grammar-terminology-reviewer` subagent (it runs on a cheaper model) instead of running the review inline. The subagent runs the `docs/ai/review-user-language/SKILL.md` and `docs/ai/fix-user-language/SKILL.md` skills. If the subagent is unavailable, fall back to running `docs/ai/review-user-language/SKILL.md` directly.
- Reuse existing translated strings when feasible instead of adding synonyms, capitalization-only variants, or comma/punctuation-only variants.
- For compact standings or statistics tables, reuse the column-abbreviation and legend API in `lib/common.functions.php` (`ColumnAbbr`/`ColumnAbbrCell`/`ColumnAbbrLabel`/`ColumnLegend`/`TableLegend`) instead of inlining headers; register a new column by adding a `case` to `ColumnAbbr()`. Abbreviations are language-neutral literals explained by a localized legend — see `docs/lib-index.md` and the abbreviation note in `docs/terminology.md`.
- After adding or changing database-related functionality, run `docs/ai/review-database-access/SKILL.md` as a final review step on your changes.
- After adding or changing a playoff bracket layout under `cust/*/layouts/`, or the placeholder contract in `lib/pool.functions.php`, run `docs/ai/review-playoff-layouts/SKILL.md` as a final review step on your changes.
- After adding or changing PHP code, run `docs/ai/format-and-lint/SKILL.md` to apply PER-CS 2.0 formatting and surface PHPStan findings on the changed files.
- If you add new player data or registered-user data, update the privacy tools and documentation so the new data is covered by the relevant privacy export and anonymization or deletion flow. Classify any new table in `docs/ai/privacy-coverage/tables.txt` and run `docs/ai/privacy-coverage/SKILL.md`.
- If you present a plan for work that changes user-facing text or database access, include the relevant review-skill checks as final plan steps.
- When adding a new `SYSTEM_FLAG` or `INSTALLATION_SETTING`, ask the user whether it should be added to the installation process, and cover `install.php` if the answer is yes.
- When adding a new markdown document under `docs/`, also add it to the topic lists in both `AGENTS.md` and `docs/README.md`.
- Keep the root `README.md` pointing to `docs/README.md` as the documentation index instead of maintaining a parallel topic list there.
- When adding new files or directories, decide whether they belong in the production release package. Runtime files must be included by `docs/release/build-release.sh`; development-only files must be excluded through `.gitattributes` `export-ignore`. Run `docs/release/build-release.sh` and inspect the package contents when changing release-relevant paths. A new top-level app directory must also be added to the hard-coded scan list in `docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh` so its strings reach the catalogs, and linked from `menufunctions.php` if it needs a menu entry. Classify every new top-level path in `docs/ai/release-package-coverage/inventory.txt` and run `docs/ai/release-package-coverage/SKILL.md`.

## Verification

- The production test suite is the harness in the separate [`ktolonen/ultiorganizer-tests`](https://github.com/ktolonen/ultiorganizer-tests) repository, not part of this repo. Clone it once as a sibling of this checkout, which is the layout CI uses and the harness defaults to (`--sut-path ../ultiorganizer`): `git clone https://github.com/ktolonen/ultiorganizer-tests.git ../ultiorganizer-tests`
- Run harness commands from that checkout: `./doctor` (environment check, needs Docker), `./test:quick` (day-to-day), `./test:matrix` (the full matrix CI runs). Pass `--sut-path <path>` to test a worktree or PR checkout instead of the sibling default.
- The harness pins shared `lib/` behaviour exactly, including export byte output, so run `./test:integration` after changing anything in `lib/`, not only the suite for the topic you touched.
- Keep the harness on its `main` branch and pull before a run — CI checks out `ref: main`, so a stale local copy can disagree with CI. Run `./test:matrix` before declaring branch work done. See the harness README for suite definitions and reporting commands.
- PHP syntax check a single file: `php -l <file.php>`
- Format changed PHP: `composer format` (check-only: `composer format:check`)
- Static analysis: `composer lint` (uses `phpstan-baseline.neon` for legacy findings)
- Combined format-check + lint: `composer check`
- Lint JavaScript in `script/` (run via the `dev` container; ESLint is not installed on the host): `docker compose -f docs/dev/compose.yaml exec -T dev eslint script` (apply autofixes with `--fix`).
- Lint CSS (run via the `dev` container; Stylelint is not installed on the host): `docker compose -f docs/dev/compose.yaml exec -T dev stylelint "cust/**/*.css"` (apply autofixes on changed files with `--fix`).
- DB access boundary check (changed files): `php docs/ai/review-database-access/scripts/check-db-access.php --changed`
- DB access boundary check (full repo): `php docs/ai/review-database-access/scripts/check-db-access.php --all`
- Playoff layout templates (all): `php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php`
- Schema version contract: `php docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php`
- Release package classification: `php docs/ai/release-package-coverage/scripts/check-release-coverage.php`
- Privacy coverage of schema tables: `php docs/ai/privacy-coverage/scripts/check-privacy-coverage.php`
- Refresh gettext catalogs after changing translated strings: `./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh`
- If local `php` is not available, use the Docker-based local development environment from `docs/local-development.md`, preferably the optional `dev` workspace, for PHP linting, checker scripts, and other CLI verification.
- Start the workspace with `docker compose -f docs/dev/compose.yaml --profile devtools up --build dev` and run commands with `docker compose -f docs/dev/compose.yaml exec -T dev ...`. If the `dev` service is unavailable but `app` is running, use `docker compose -f docs/dev/compose.yaml exec -T app ...` for equivalent PHP-based checks.
- Verify changes by running the app and exercising the relevant page flow.
- After any change that alters SQL or query results, verify it empirically against the real database rather than reasoning about the query: run the old and the new query, compare their output, and report the row counts in your handback. Use `docs/ai/query-database/SKILL.md` for read-only ad-hoc queries against the local dev database.
- After UI or report changes, regenerate the affected pages and confirm them with `docs/ai/screenshot-verify/SKILL.md` before committing, covering both desktop and mobile layouts. Do not skip this because the code looks correct — screenshots are the evidence.

## CI

GitHub Actions runs the same checks automatically on every push to `master` and on every pull request. The workflow is at [`.github/workflows/ci.yml`](.github/workflows/ci.yml) and has six jobs:

- `php-quality` — `composer check` (PHP-CS-Fixer + PHPStan) on PHP 8.3.
- `composer-audit` — `composer audit` against `composer.lock`; fails on any reported security advisory.
- `js-lint` — `eslint script` against the same ESLint 9 config used locally (`eslint.config.js`).
- `repo-checkers` — DB access boundary check and playoff layout templates check.
- `release-package-smoke` — runs `docs/release/build-release.sh` and asserts the archive contains `index.php`.
- `harness` — checks out the sibling `ktolonen/ultiorganizer-tests` repository and runs its full test matrix (lint, unit, integration, export, api, smoke, crawl) against the pull request's code. Per-case results are written to the run's job summary, and the full report tree (including the `report:html` browser index) is uploaded as the `harness-reports` artifact.

Pre-commit hooks remain the fast local gate; CI is the source of truth for what is allowed to merge. The `harness` job is the production test suite — it lives in a separate public repository so it can be developed and versioned independently; see the Verification section above for how to clone and run it locally.

## Documentation

### Docs Tone

- README and public docs must be generic and product-focused. Author, maintainer, and contributor credits are fine and belong in `README.md`.
- Never put real personal data in committed documentation, examples, or screenshots: no real player, team member, or registered-user names, no contact details, and no rows copied out of a live or production database. Use neutral placeholders such as `Team A`, `Team B`, or `Player 1`, which the existing docs already use.

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

- `docs/playoff-templates.md`: playoff bracket template grammar, lookup, move-comment block, BYE handling, and pool generation.
- `docs/ranking.md`: pool ranking resolvers per pool type, tie-break order, special-ranking overrides, and event final-standings rendering.
- `docs/schedule.md`: schedule concept, scheduling workflow, row compilation, and database tables.

### Scorekeeping and spirit

- `docs/scorekeeper.md`: Scorekeeper app routing, responsibility list, live clock workflow, and related pages.
- `docs/scoresheet.md`: scoresheet concept, input paths, visualization, and database tables.
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
