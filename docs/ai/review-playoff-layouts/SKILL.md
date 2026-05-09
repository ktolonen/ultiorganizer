---
name: review-playoff-layouts
description: Read-only review skill for Ultiorganizer playoff bracket layouts. Use after adding or changing a `cust/<id>/layouts/<N>_teams_<R>_rounds.html` template, after editing the `PlayoffTemplate()` placeholder contract in `lib/pool.functions.php`, or after editing the move-comment parser in `GeneratePlayoffPools()`. Run the bundled checker first, then report findings without editing files.
metadata:
  short-description: Review playoff bracket layout placeholders, widths, and move-comment block
---

# Review Playoff Layouts

Review Ultiorganizer playoff bracket layouts without editing files.

Always read this reference first:

- `docs/codebase-notes.md` (customization layout files)
- `lib/pool.functions.php` `PlayoffTemplate()` and `GeneratePlayoffPools()`
- `poolstatus.php`, `ext/poolstatus.php`, `ext/eventpools.php`, `ext/countrystatus.php` for the placeholder substitution contract

## Purpose

Use this skill for read-only review of:

- placeholder coverage and consistency in `cust/*/layouts/<N>_teams_<R>_rounds.html`
- CSS percentage widths and per-row column structure in those templates
- move-comment data, when present, against the renderer's permutation contract
- file naming versus declared rounds and the default-mode round-count formula

This skill reports findings only. It must not apply fixes.

Run this skill as a final review step after adding or changing a playoff bracket template, the placeholder contract that consumes it, or the move-comment parser.

## Review scope

Start with user-modified content in the current worktree.

Use repo state to identify the first review scope:

- `git status --short`
- `git diff --name-only`
- `git diff --cached --name-only`

Prioritize changed `cust/*/layouts/*.html` files first, then the `PlayoffTemplate()` callers and `GeneratePlayoffPools()` if those changed.

## Checker workflow

Run the bundled checker before manual review:

- `php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php`

Limit to changed files when the change scope is small:

- `php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php --file=cust/default/layouts/6_teams_3_rounds.html`

Limit to odd-team templates when reviewing odd-N work:

- `php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php --odd`

Pass `-v` for the informational notes that explain odd-team bye wiring and similar context.

The checker enforces these rules:

- file name `<N>_teams_<R>_rounds.html` matches the declared `[round 1..R]` headers and the `[placement]` header
- `[team 1..N]` and `[placement 1..N]` are present, each `[placement N]` exactly once
- each `[game R/G]` token appears exactly once and stays inside `1..R`
- every `[winner R/G]` and `[loser R/G]` references an existing `[game R/G]`, with an explicit allowance for the odd-N bye pseudo-winner at `[winner 1/⌈N/2⌉]`
- CSS `width:` values are valid percentages (no comma decimal locale)
- per row, td widths sum to 100 percent within a small tolerance and the count matches `R + 1` columns
- when present, the `<!--  corresponding moves: ... -->` block has exactly `R` non-empty lines and each line is a permutation of `1..N`
- the filename's round count matches the default-mode formula in `GeneratePlayoffPools()`: `roundsToWin = (N+1)/2`, halved until below one

## Manual review rules

After the checker output, inspect each flagged template and the surrounding contract for issues the static rules may miss:

- bracket lines drawn by `border-left/right/top/bottom` should still connect a game cell to its two team cells and onward to the next round; when a placeholder moves, check the borders on the same row and on the rows above and below
- the placement column should still feed `[placement 1..N]` from the round whose game produces them
- odd-team templates rely on either the renderer's `is_odd($teams)` fallback (no move comment) or a `<!--  corresponding moves:` block; if a template adds or removes the comment block, verify both the visual bracket and the move-comment lines
- when `PlayoffTemplate()` callers change, confirm that the `str_replace` keys still match the placeholder grammar `\[(round|team|game|winner|loser|placement)(?:\s+(\d+)(?:\/(\d+))?)?\]`
- when `GeneratePlayoffPools()` changes, re-run the checker on every layout under `cust/*/layouts/` so a regression in the move-comment parser surfaces against the live data

Treat the checker output categories as the primary review buckets:

- `Errors`: missing or duplicated placeholders, orphan winner/loser tokens, invalid CSS widths, malformed move-comment data
- `Warnings`: row width totals off by tolerance, non-contiguous game numbers, odd-team templates without a move-comment block, filename round count differing from the default formula

## Output

Report findings only.

Each finding should include:

- file path
- line reference when available
- checker rule or manual review category
- the problematic placeholder, width, or move-comment entry
- a short explanation of the contract being violated

Keep the review concise and actionable. Do not produce patches by default.
