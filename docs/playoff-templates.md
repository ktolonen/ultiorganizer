# Playoff templates

This page describes how the HTML bracket templates under `cust/<id>/layouts/` work. Templates are static scaffolds with placeholder tokens; the renderer fills the tokens with team names, scores, and final placements at request time, and the pool generator uses an optional move-comment block to wire up the bracket flow.

The main renderer and generator live in `poolstatus.php` and `lib/pool.functions.php`; related entry points are listed below.

## Quick recipe

1. Choose the team count `N` and round count `R`.
2. Copy a similar file from `cust/default/layouts/`.
3. Keep exactly `R + 1` columns per row.
4. Fill round 1 with `[team N]` and `[game 1/G]`.
5. Fill later rounds with `[winner R/G]`, `[loser R/G]`, and `[game R/G]`.
6. Fill final standings with `[placement P]`.
7. Add a move-comment block if the bracket is not a plain pair-off bracket.
8. Run the playoff layout validator.

## File location and lookup

Templates live under `cust/<id>/layouts/` and are loaded through `PlayoffTemplate($teams, $rounds, $id = "")` in `lib/pool.functions.php`:

1. If `$id` is empty, it is built from the team and round count: `<N>_teams_<R>_rounds`.
2. The loader looks for `cust/<CUSTOMIZATIONS>/layouts/<id>.html` first.
3. If that file does not exist, it falls back to `cust/default/layouts/<id>.html`.
4. If neither is present, the renderer falls back to a plain table per round and a list of placements.

A pool can override the file name with the `playoff_template` field on `uo_pool`. When set, that string is used as `$id` instead of the auto-derived `<N>_teams_<R>_rounds`. Use this to point one bracket at a tournament-specific layout while keeping the default for everything else.

## File naming

The auto-derived id uses two integers:

- `N` — number of teams in the master pool.
- `R` — number of rounds played to resolve the bracket.

For example `cust/default/layouts/8_teams_3_rounds.html` covers an 8-team three-round playoff. The number of rounds matches the formula in `GeneratePlayoffPools()`: start with `roundsToWin = (N + 1) / 2` and halve until below one, counting iterations. For `N = 6` the formula uses a hard-coded `roundsToWin = 4`. The validator checks this match and warns if the file name disagrees with the formula.

## Placeholder grammar

Each cell in the table can carry one placeholder. The renderer recognises the following tokens and replaces them via `str_replace`:

| Token | Where it appears | What the renderer fills |
|---|---|---|
| `[round R]` | header row | localised round name (`Finals`, `Semifinals`, `Quarterfinals`, or `Round R`) |
| `[placement]` | header row | localised "Placement" header |
| `[team N]` | round 1 column | the team at master-pool seed `N` |
| `[game R/G]` | round R column | a hyperlink to the scoresheet, the live or final score, or a fallback placeholder when the game is scheduled but not played |
| `[winner R/G]` | round R+1 column | the team in pool R+1 whose `fromplacing` is odd (came from a "winner" position in pool R) |
| `[loser R/G]` | round R+1 column | the team in pool R+1 whose `fromplacing` is even (came from a "loser" position in pool R) |
| `[placement P]` | placement column | the team that ends up in final position `P` |

Indexes follow the convention `R/G` where `R` is the round number (1-based) and `G` is the game number within that round (1-based). Round numbers must satisfy `1 ≤ R ≤ rounds`. Game numbers within a round must be unique. `[winner R/G]` and `[loser R/G]` reference the corresponding `[game R/G]`.

The header column count is `R + 1` — one column per round plus the placement column.

## Bracket lines

There is no SVG, canvas, or extra widget. The bracket lines you see in the rendered template are CSS borders (`border-top`, `border-right`, `border-bottom`, `border-left`) on individual `<td>` cells. A typical "team A — game — team B — spacer" pattern uses four rows:

1. Team A cell with `border-bottom`.
2. Game cell with `border-right; border-top` plus `font-weight:bold; text-align:center; vertical-align:middle`.
3. Team B cell with `border-right; border-bottom`.
4. Spacer cell with `border-top` to close the elbow.

The cell to the right of a game uses `border-left` plus a top or bottom border to draw the horizontal line into the next round's slot. Adjacent rounds connect through these per-cell borders only — there is no shared rendering helper, so when a placeholder moves you must update the borders on the same row and on the rows above and below by hand.

All cells in a row must declare a width that sums to 100 percent. Use period decimals (`33.3333333333333%`), never commas — comma decimals are invalid CSS and the browser will drop the rule.

## The move-comment block

Some templates open with a comment that tells `GeneratePlayoffPools()` how to move teams between pools and how to map the final pool's standings to event placements:

```
<!--  corresponding moves:
1 3 5 7 9 11 13 15 2 4 6 8 10 12 14 16
1 3 5 7 2 4 6 8 9 11 13 15 10 12 14 16
1 3 2 4 5 7 6 8 9 11 10 12 13 15 14 16
1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16
-->
```

The comment must be the very first thing in the file. Whitespace between `<!--` and `corresponding moves:` is flexible — the parser regex accepts any number of spaces, including none. The body has exactly `R` non-empty lines, each one a permutation of `1..N`:

- Line 1 maps round-1 (master pool) standings into round-2 slot positions.
- Line 2 maps round-2 standings into round-3 slot positions.
- ... and so on.
- The last line is the final-ranking permutation: pool standings position → final placement.

Each line's `j`-th entry (1-based) is the source standing position in the previous pool that fills slot `j` in the next pool. Using line 1 from the example above, slot 1 of round 2 is filled from round-1 standings position 1, slot 2 from position 3, slot 9 from position 2, and so on — a winners-on-top, losers-below split that matches the standard pair-off but is now explicit.

When the block is valid for the round count, the parser uses the comment lines for both `PoolAddMove(...)` calls and the `AddSpecialRankingRule(...)` calls on the last line. When the comment is absent or invalid, the generator falls back to the standard pair-off algorithm.

The standard algorithm is sufficient for plain knock-out brackets but cannot express crossings such as 6-team or odd-team placement brackets. Templates that depend on a specific slot mapping must ship the move-comment block, otherwise the renderer's `[winner R/G]` / `[loser R/G]` substitutions land on the wrong teams even though the visual scaffold looks correct.

## Rendering pipeline

The flow for `?view=poolstatus` is:

1. The page resolves the pool's followers (`PoolPlayoffFollowersArray($poolId)`) to determine how many rounds will be rendered.
2. It calls `PlayoffTemplate($totalteams, $rounds, $poolinfo['playoff_template'])` to load the HTML.
3. For each round, it walks the pool's teams in slot order. For each team it determines the team name, gathers the `[game R/G]` substitution from the team's actual game results in this pool, and runs `str_replace` on the template for `[round R]`, `[team N]`, `[game R/G]`, `[winner R/G]`, and `[loser R/G]` as appropriate.
4. After the per-round loop it walks placement positions 1..N, calls `PoolTeamFromStandings(...)`, and substitutes `[placement P]`.
5. Finally `[placement]` (the header) is replaced with the localised "Placement" string.

The external entry points follow the same shape with minor differences such as no fallback "Game N" label in `ext/*`, and country-aware flag rendering in `ext/countrystatus.php`.

## Pool generation

`GeneratePlayoffPools($poolId, $generate = true)` in `lib/pool.functions.php` is the single source of truth for materialising a bracket into actual `uo_pool` rows and `uo_moveteams` entries:

1. Read all teams seeded into the master pool, ordered by `uo_team_pool.rank`.
2. Compute the number of rounds with the `roundsToWin = (N + 1) / 2`, halve-until-below-one formula. `N = 6` is special-cased to `roundsToWin = 4`.
3. Load the template with `PlayoffTemplate($teams, $rounds, $poolInfo['playoff_template'])`.
4. Parse the optional move-comment block described above. If it is valid for the round count, use it for pool moves and final ranking; otherwise use the standard pair-off algorithm.
5. For each subsequent round, create a follower pool and add either the parsed moves or the standard pair-off moves.
6. Mark the last follower pool with `placementpool = 1` so it shows up in the placement walk used by `lib/series.functions.php` and `TeamSeriesStanding()`.

When `$generate` is false the function returns the pool descriptors without writing to the database — useful for previews.

## BYE handling

When a pool has an odd number of teams, the team in the last slot has no opponent in that round. The renderer detects this by querying `TeamPoolGamesArray($team['team_id'], $pool['pool_id'])`: if the team has zero games in the current pool, it is the bye team. The renderer carries that team forward and can substitute it into the next round's bye winner token, such as `[winner 1/3]` in a 5-team bracket.

Templates handle byes in two ways:

- **Carry-over only.** Templates for 3, 5, and 7 teams place the bye team's `[team N]` token directly into a column or rely on `[winner 1/⌈N/2⌉]` substitution to label the round-2 slot. They contain no `BYE` literal and no `[game R/G]` for the bye pair.
- **Visible BYE marker.** The 9-team template uses an explicit `BYE` literal in cells where the bye team's "opponent" would sit, and reserves slots in pool 2 and pool 3 for the bye carry-over. Cells that pair an actual team with `BYE` must not contain a `[game R/G]` token, because no game is generated for the pair and `str_replace` cannot fill the cell with a real score.

The validator allows `[winner R/⌈N/2⌉]` to exist without a matching `[game R/⌈N/2⌉]` in odd-team templates: the renderer fills these via the bye-pseudo-winner branch rather than from a stored game.

## Adding a new template

1. Decide the bracket layout on paper: round-1 pairings, round-2 pairings, where each placement comes from. Choose whether the bracket needs a move-comment block (anything beyond plain knock-out usually does).
2. Copy an existing template with the same column count. Save it under `cust/<id>/layouts/<N>_teams_<R>_rounds.html` for installation-specific layouts, or under `cust/default/layouts/` to make it the global default.
3. Replace the bracket positions with the placeholder tokens from the table above. Keep `<td>` widths in period-decimal percentages that sum to 100 percent.
4. If the bracket uses crossings or special placement flow, add the move-comment block at the very top of the file with `R` permutation lines (one per round, last line being the final ranking). The parser is whitespace-tolerant — any number of spaces between `<!--` and `corresponding moves:` works.
5. If the bracket carries an odd team into a later round as a bye, place the explicit `BYE` literal where the missing opponent would sit and remove any `[game R/G]` token from the BYE-paired cell.
6. Run the validator: `php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php --file=cust/<id>/layouts/<N>_teams_<R>_rounds.html`.
7. Smoke-test the page that consumes it (`?view=poolstatus&pool=<id>` for the relevant pool) on both desktop and mobile viewports — the bracket lines are CSS borders and small width or border slips break the visual silently.

## Validator

The bundled validator at `docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php` enforces the contract above:

- file name vs declared `[round R]` and `[placement]` headers
- coverage of `[team 1..N]` and `[placement 1..N]`
- uniqueness and round-bounded numbering of `[game R/G]`
- every `[winner R/G]` and `[loser R/G]` references an existing `[game R/G]`, with the bye-pseudo-winner allowance for odd `N`
- valid CSS percentage widths (no comma decimals)
- per-row td count of `R + 1` and width sum near 100 percent
- well-formed move-comment block with exactly `R` permutation lines

Run it with `--odd` to focus on odd-team templates, `--file=` for a single file, and `-v` for informational notes such as the bye carry-over annotation.

## Related files

- `lib/pool.functions.php`: `PlayoffTemplate()` (template lookup) and `GeneratePlayoffPools()` (pool generation, move-comment parsing, special vs standard moves).
- `poolstatus.php`: main page that consumes the placeholder grammar; canonical reference for substitution behaviour.
- `ext/poolstatus.php`, `ext/eventpools.php`, `ext/countrystatus.php`: alternative entry points that share the placeholder contract.
- `cust/default/layouts/`: ships the canonical templates plus the `playoff_layouts.ods` / `.xls` design aids.
- `docs/ai/review-playoff-layouts/SKILL.md`: read-only review skill, with the bundled validator under `scripts/`.
