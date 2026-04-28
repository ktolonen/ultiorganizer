# Documentation

This directory collects general project documentation.

## Current topics

- `api.md`: API structure, constraints, and examples.
- `codebase-notes.md`: third-party components, PDF generation, plugins, and customization notes.
- `pdf-printing.md`: PDF entrypoints, purpose files, customization fallbacks, and tFPDF notes.
- `routing.md`: request entry points and view resolution.
- `local-development.md`: local Docker-based setup.
- `dev/`: Docker Compose assets and image definitions used by the local development guide.
- `translations.md`: translation and gettext workflow.
- `database-upgrades.md`: schema and migration workflow.
- `database-access.md`: database access boundaries, allowed helper layers, migration guidance, and checker behavior.
- `configuration-flags.md`: configuration taxonomy and migration rules.
- `ranking.md`: pool ranking resolvers per pool type, tie-break order, special-ranking overrides, and event final-standings rendering.
- `schedule.md`: schedule concept, scheduling workflow, row compilation, and database tables.
- `scorekeeper.md`: Scorekeeper app routing, live clock workflow, and related pages.
- `scoresheet.md`: scoresheet concept, input paths, visualization, and database tables.
- `spirit-scoring.md`: spirit score logic, comments, and related settings.
- `spiritkeeper.md`: standalone Spiritkeeper app, authenticated and token access modes, and current behavior.
- `permissions.md`: permission storage, roles, enforcement helpers, and spirit-director behavior.
- `privacy.md`: privacy admin tools, export scope, and anonymization or deletion behavior by table.
- `terminology.md`: canonical Ultiorganizer terminology, aliases, and approved abbreviations.
- `ai/review-user-language/SKILL.md`: read-only review skill for project spelling, grammar, and terminology consistency in user-facing content.
- `ai/fix-user-language/SKILL.md`: fix skill for user-facing wording, terminology normalization, and gettext-backed copy updates.
- `ai/review-database-access/SKILL.md`: read-only review skill for database access boundaries, page-layer DB usage, and legacy cursor-style APIs.

The `docs/ai/` directory contains repo-local AI assets and skills, including local review/fix skills and the database-access review skill with its bundled checker.
