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
- `mobile/`, `scorekeeper/`, `ext/`: specialized entry points.
- `conf/`: server configuration; keep writable only during install.
- `sql/`: schema and upgrade assets.

## Working rules

- Keep SQL and shared data access in `lib/`.
- Use the existing `?view=...` routing pattern for new pages.
- Prefer small, focused changes and avoid large refactors unless explicitly requested.
- Avoid touching `conf/` unless required.
- Keep edits ASCII unless the file already uses Unicode.
- If making UI changes, verify both desktop and mobile layouts.
- When adding a new markdown document under `docs/`, also add it to the topic lists in both `AGENTS.md` and `docs/README.md`.
- Keep the root `README.md` pointing to `docs/README.md` as the documentation index instead of maintaining a parallel topic list there.

## Verification

- No automated test suite is documented.
- Verify changes by running the app and exercising the relevant page flow.

## Topic docs

- `docs/README.md`: index of project documentation under `docs/`.
- `docs/local-development.md`: local Docker-based setup.
- `docs/database-upgrades.md`: schema and migration workflow.
- `docs/configuration-flags.md`: configuration taxonomy and migration rules. Use the exact type names `SYSTEM_FLAG`, `INSTALLATION_SETTING`, and `EVENT_SETTING`.
- `docs/api.md`: API structure, constraints, and examples.
- `docs/routing.md`: request entry points and view resolution.
- `docs/translations.md`: translation and gettext workflow.
- `docs/permissions.md`: permission storage, roles, and enforcement helpers.
- `docs/schedule.md`: schedule concept, scheduling workflow, row compilation, and database tables.
- `docs/scoresheet.md`: scoresheet concept, input paths, visualization, and database tables.
- `docs/spirit-scoring.md`: spirit score logic, comments, and related settings.
- `docs/codebase-notes.md`: third-party components, PDF generation, plugins, and customization notes.
