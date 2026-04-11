---
name: review-user-language
description: Read-only review skill for Ultiorganizer user-facing language. Use after adding or changing a page, page module, or other code with user-facing text. Review changed content for US English spelling, grammar, gettext-backed wording, terminology correctness, and on-page terminology consistency. Prioritize user-modified files first, then check the surrounding page or module for consistency warnings. Do not apply fixes.
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

- US English spelling and grammar
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
2. nearby user-facing literals rendered on the same page
3. user-facing docs included in the change

After checking the changed content, widen to the surrounding page or page module and look for terminology inconsistency. If the changed text is correct but the page mixes terms, report that as a warning only.

## Terminology rules

- Treat `docs/terminology.md` as the source of truth for preferred terms and accepted aliases.
- If new or changed user-facing text introduces the wrong term, report it as an error.
- If a changed string is inside an existing legacy context and matches the surrounding wording, allow that local context unless it creates a new inconsistency on the page.
- If the page already mixes terms such as `event` and `season`, or `division` and `series`, report that as a warning even if the changed line itself is acceptable.
- Do not demand internal renames for DB fields, API fields, helper names, or existing code identifiers unless the user explicitly requested that broader scope.

## Gettext rules

- Give extra attention to strings inside `_()` because they are user-facing and translation-backed.
- Also inspect adjacent labels, headings, button text, notices, and warnings on the same page so wording remains consistent.
- Do not edit `.po` or `.mo` files in this skill.

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
