---
name: fix-user-language
description: Fix Ultiorganizer user-facing language for project spelling, grammar, gettext-backed wording, and terminology correctness. Use for either a single page sweep or a single-term sweep across user-facing surfaces. Default to one page or module when the caller is not explicit. Do not rename internal identifiers unless the user asks for that broader scope.
metadata:
  short-description: Fix user-facing wording and terminology
---

# Fix User Language

Fix Ultiorganizer user-facing language while respecting current internal naming boundaries.

Always read these references first:

- `docs/terminology.md`
- `docs/translations.md`

## Purpose

Use this skill to apply wording fixes for:

- project spelling and grammar, with WFDF rules as the source of truth where applicable
- terminology correctness
- user-facing terminology consistency

This skill is allowed to edit source files and translation catalogs, but not runtime identifiers unless the user explicitly asks for a broader rename.

## Operating modes

This skill supports two modes:

- `page sweep`: fix all relevant user-facing wording on one page or one page module
- `term sweep`: fix one requested term across user-facing surfaces in the repo

If the caller is not explicit, default to `page sweep`.

## Page sweep rules

- Scan the selected page or module for user-facing strings, especially `_()` strings, headings, labels, buttons, warnings, notices, and nearby literals.
- Normalize the page to one consistent terminology set instead of making a partial fix.
- Apply project-preferred spelling, using WFDF rules where applicable, unless the user explicitly wants compatibility wording.
- Use the preferred terms in `docs/terminology.md`, while leaving internal identifiers unchanged.

## Term sweep rules

- Apply one requested preferred term across user-facing surfaces such as docs, gettext-backed strings, and visible UI literals.
- Touch only user-facing or documentation-facing occurrences.
- Do not rename DB columns, API field names, helper names, or legacy code symbols unless the user explicitly requested that broader change.
- Preserve exact runtime identifiers like `series_id`, `season`, `fedin`, `done`, or `iscallahan` unless they are part of user-facing output text.

## Gettext and locale handling

- When fixing gettext-backed source strings, update the tracked translation catalogs by default:
  - `locale/de_DE.utf8/LC_MESSAGES/messages.po`
  - `locale/fi_FI.utf8/LC_MESSAGES/messages.po`
- Prefer the helper script `docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh` for catalog refreshes instead of ad hoc gettext commands.
- If the environment supports rebuilding compiled catalogs, update the corresponding `messages.mo` files too.
- If `.mo` rebuild tooling is unavailable, complete the source and `.po` changes and state that `.mo` regeneration could not be performed.
- Do not add new locale trees or broaden locale coverage.

## Terminology rules

- Treat `docs/terminology.md` as the canonical terminology source.
- Prefer the canonical terms in `docs/terminology.md` in docs and user-facing strings.
- Allow legacy/internal names to remain in code identifiers and schema-facing contexts unless the caller asked for an internal rename.
- If a page mixes terminology, finish the page in one consistent set of user-facing terms.

## Workflow

1. Determine whether the task is a page sweep or a term sweep. Default to page sweep if unclear.
2. Inspect changed or requested files first.
3. Identify user-facing strings, prioritizing `_()` strings and visible copy.
4. Apply terminology and project spelling fixes.
5. Update `.po` catalogs when gettext-backed strings changed.
6. Rebuild `.mo` files if tooling is available.
7. Report what was changed and any translation build steps that could not be completed.

## Boundaries

- Do not rename internal identifiers by default.
- Do not change API or schema naming unless explicitly requested.
- Keep fixes focused on the requested page or requested term scope.
