# Documentation

This directory collects general project documentation.

## Current topics

### Core architecture

- `api.md`: API structure, constraints, and examples.
- `codebase-notes.md`: third-party components, PDF generation, plugins, and customization notes.
- `lib-index.md`: file-by-file map of shared helpers and third-party libraries under `lib/`.
- `routing.md`: request entry points and view resolution.
- `runtime-cache.md`: request-local helper caching guidance and database-log recapture commands.
- `deployment.md`: production release package and installation guidance.
- `local-development.md`: local Docker-based setup.
- `dev/`: Docker Compose assets and image definitions used by the local development guide.
- `code-style.md`: PHP code style conventions, formatter and linter setup, and pre-commit hook.

### Data, configuration, and security

- `database-upgrades.md`: schema and migration workflow.
- `database-access.md`: database access boundaries, allowed helper layers, migration guidance, and checker behavior.
- `configuration-flags.md`: configuration taxonomy and migration rules.
- `permissions.md`: permission storage, roles, enforcement helpers, and spirit-director behavior.
- `privacy.md`: privacy admin tools, export scope, and anonymization or deletion behavior by table.

### Competition workflow

- `playoff-templates.md`: playoff bracket template grammar, lookup, move-comment block, BYE handling, and pool generation.
- `ranking.md`: pool ranking resolvers per pool type, tie-break order, special-ranking overrides, and event final-standings rendering.
- `schedule.md`: schedule concept, scheduling workflow, row compilation, and database tables.

### Scorekeeping and spirit

- `scorekeeper.md`: Scorekeeper app routing, live clock workflow, and related pages.
- `scoresheet.md`: scoresheet concept, input paths, visualization, and database tables.
- `spirit-scoring.md`: spirit score logic, comments, and related settings.
- `spiritkeeper.md`: standalone Spiritkeeper app, authenticated and token access modes, and current behavior.

### Language and output

- `pdf-printing.md`: PDF entrypoints, purpose files, customization fallbacks, and tFPDF notes.
- `translations.md`: translation and gettext workflow.
- `terminology.md`: canonical Ultiorganizer terminology, aliases, and approved abbreviations.

### AI review assets

- `ai/review-user-language/SKILL.md`: read-only review skill for project spelling, grammar, and terminology consistency in user-facing content.
- `ai/fix-user-language/SKILL.md`: fix skill for user-facing wording, terminology normalization, and gettext-backed copy updates.
- `ai/review-database-access/SKILL.md`: read-only review skill for database access boundaries, page-layer DB usage, and legacy cursor-style APIs.
- `ai/review-playoff-layouts/SKILL.md`: read-only review skill for playoff bracket layout placeholders, CSS widths, and the move-comment block.
- `ai/format-and-lint/SKILL.md`: fix skill that runs PHP-CS-Fixer and PHPStan on changed PHP files and applies safe fixes.

The `docs/ai/` directory contains repo-local AI assets and skills, including local review/fix skills and the database-access review skill with its bundled checker.
