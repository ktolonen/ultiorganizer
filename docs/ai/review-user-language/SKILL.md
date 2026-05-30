---
name: review-user-language
description: Read-only review skill for Ultiorganizer user-facing language. Use after adding or changing a page, page module, or other code with user-facing text. Review changed content for project spelling, grammar, gettext-backed wording, database-backed U_() translation usage, terminology correctness, and on-page terminology consistency. Prioritize user-modified files first, then check the surrounding page or module for consistency warnings. Do not apply fixes.
metadata:
  short-description: Review user-facing wording and terminology
---

# Review User Language

Review Ultiorganizer user-facing language without editing files.

Always read these references first:

- `docs/terminology.md`
- `docs/translations.md`

## Purpose

Use this skill for read-only review of:

- project spelling and grammar, with WFDF rules as the source of truth where applicable
- correct use of gettext `_()` and database-backed `U_()` translation mechanisms
- terminology correctness against `docs/terminology.md`
- consistency of user-facing terminology on the same page or module

This skill reports findings only. It must not perform updates.

Run this skill as a final review step after implementing new or changed user-facing text.

## Review scope

Start with user-modified content in the current worktree.

Use repo state to identify the first review scope:

- `git status --short`
- `git diff --name-only`
- `git diff --cached --name-only`

Prioritize changed files over the rest of the repo.

Inside changed files, inspect in this order:

1. gettext-backed user-facing strings in `_()`
2. database-backed user-facing values rendered through `U_()`
3. nearby user-facing literals rendered on the same page
4. user-facing docs included in the change
5. table headers, compact labels, leaderboard headings, and button/link text on the same page

After checking the changed content, widen to the surrounding page or page module and look for terminology inconsistency. If the changed text is correct but the page mixes terms, report that as a warning only.
When one term changes on a page, explicitly check the rest of that page for mixed variants such as `Spirit points` and `Spirit score`, `Spirit timeout` and `Spirit stoppage`, or `Defenseboard` and `Defence board`.

## Terminology rules

- Treat `docs/terminology.md` as the source of truth for preferred terms and accepted aliases.
- If new or changed user-facing text introduces the wrong term, report it as an error.
- If a changed string is inside an existing legacy context and matches the surrounding wording, allow that local context unless it creates a new inconsistency on the page.
- If the page already mixes terms such as `event` and `season`, or `division` and `series`, report that as a warning even if the changed line itself is acceptable.
- Check verb-vs-noun UI labels for common actions. Prefer forms such as `Log in` and `Log out` for buttons, links, and headings.
- Do not demand internal renames for DB fields, API fields, helper names, or existing code identifiers unless the user explicitly requested that broader scope.

## Gettext rules

- Give extra attention to strings inside `_()` because they are user-facing and translation-backed.
- Also inspect adjacent labels, headings, button text, notices, warnings, table headers, and compact stat labels on the same page so wording remains consistent.
- If a wording pass reveals an obvious adjacent rendering mistake that affects user-facing text, such as a duplicated label cell or a broken heading, report it as an error even if the underlying issue is not purely terminology.
- Do not edit `.po` or `.mo` files in this skill.

## Database-backed translation rules

- Use `_()` for fixed PHP interface text: button labels, headings, notices, warnings, and ordinary UI prose.
- Use `U_()` for installation-defined or database-provided names and labels that are expected to vary by locale, such as event names, division names, pool names, location names, field names, reservation group names, menu link names, and similar stored display values.
- Use `TranslatedField()` for admin form inputs whose stored values should later be rendered through `U_()`.
- Do not use `U_()` for static UI strings that should be gettext-backed.
- Report newly changed rendering paths as errors when they output a database-backed display name directly without `U_()`.
- Report unnecessary `U_()` on static UI text as an error when it prevents normal gettext extraction or as a warning when it is legacy-adjacent and does not clearly change behavior.
- Do not require `U_()` for identifiers, URL parameters, database keys, API field names, internal enum values, or values that are intentionally not displayed to users.

## Output

Report findings only, grouped under:

- `Errors`: changed or newly introduced wording that should not be merged as written
- `Warnings`: page-level inconsistency, surrounding legacy wording, or cleanup that should be handled separately

Each finding should include:

- file path
- line reference when available
- the problematic term or wording
- the preferred term from `docs/terminology.md`
- a short explanation of why it is an error or warning

Do not produce patches by default. Keep the review concise and actionable.
