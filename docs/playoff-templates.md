# Playoff templates

HTML bracket templates under `cust/<id>/layouts/` are static scaffolds with placeholder tokens. `poolstatus.php` fills them with team names, scores and final placements, and `GeneratePlayoffPools()` in `lib/pool.functions.php` reads an optional move-comment block to wire up the bracket flow.

## Adding a template

1. Sketch the bracket: round-1 pairings, later pairings, and where each placement comes from. Anything beyond a plain knock-out usually needs a move-comment block.
2. Copy a template with the same column count to `cust/<id>/layouts/<N>_teams_<R>_rounds.html` (or `cust/default/layouts/` for the global default).
3. Keep `R + 1` cells per row with period-decimal widths summing to 100%.
4. Fill round 1 with `[team N]` and `[game 1/G]`, later rounds with `[winner R/G]`, `[loser R/G]` and `[game R/G]`, and the last column with `[placement P]`.
5. Add the move-comment block if needed, and `BYE` markers for an odd team carried forward.
6. Run `php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php --file=<path>`.
7. Check `?view=poolstatus&pool=<id>` on desktop and mobile; the bracket lines are CSS borders and break silently.

## File location and lookup

Templates live under `cust/<id>/layouts/` and are loaded through `PlayoffTemplate($teams, $rounds, $id = "")` in `lib/pool.functions.php`:

1. If `$id` is empty, it is built from the team and round count: `<N>_teams_<R>_rounds`.
2. The loader looks for `cust/<CUSTOMIZATIONS>/layouts/<id>.html` first.
3. If that file does not exist, it falls back to `cust/default/layouts/<id>.html`.
4. If neither is present, the renderer falls back to a plain table per round and a list of placements.

A pool's `uo_pool.playoff_template` overrides the derived `$id`, pointing one bracket at a tournament-specific layout.

## File naming

`N` is the number of teams in the master pool and `R` the number of rounds. `R` must match `GeneratePlayoffPools()`: start with `roundsToWin = (N + 1) / 2` and halve until below one, counting iterations (`N = 6` is hard-coded to `roundsToWin = 4`). The validator warns on a mismatch.

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

In `R/G`, `R` is the 1-based round (at most `rounds`) and `G` the 1-based game within it, unique per round. `[winner R/G]` and `[loser R/G]` refer to the matching `[game R/G]`. The header has `R + 1` columns: one per round plus placement.

## Bracket lines

There is no SVG, canvas, or extra widget. The bracket lines you see in the rendered template are CSS borders (`border-top`, `border-right`, `border-bottom`, `border-left`) on individual `<td>` cells. A typical "team A — game — team B — spacer" pattern uses four rows:

1. Team A cell with `border-bottom`.
2. Game cell with `border-right; border-top` plus `font-weight:bold; text-align:center; vertical-align:middle`.
3. Team B cell with `border-right; border-bottom`.
4. Spacer cell with `border-top` to close the elbow.

The cell right of a game uses `border-left` plus a top or bottom border to lead into the next round. When a placeholder moves, update the borders on its row and the rows around it by hand.

Cell widths in a row sum to 100% and use period decimals (`33.3333333333333%`); comma decimals are invalid CSS and get dropped.

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

The comment must come first in the file (any whitespace between `<!--` and `corresponding moves:`). It has exactly `R` non-empty lines, each a permutation of `1..N`. Line `k` maps round-`k` standings into round-`k+1` slots: its `j`-th entry is the standing position that fills slot `j`. In line 1 above, slot 1 comes from position 1, slot 2 from position 3, slot 9 from position 2. The last line maps the final pool's standings to event placements.

A valid block drives the `PoolAddMove(...)` calls and, from its last line, `AddSpecialRankingRule(...)`. Without a valid block the generator uses the standard pair-off, which cannot express crossings such as 6-team or odd-team placement brackets; a template that depends on a specific mapping must ship the block, or its `[winner R/G]` / `[loser R/G]` tokens land on the wrong teams.

## Rendering pipeline

The flow for `?view=poolstatus` is:

1. The page resolves the pool's followers (`PoolPlayoffFollowersArray($poolId)`) to determine how many rounds will be rendered.
2. It calls `PlayoffTemplate($totalteams, $rounds, $poolinfo['playoff_template'])` to load the HTML.
3. For each round, it walks the pool's teams in slot order. For each team it determines the team name, gathers the `[game R/G]` substitution from the team's actual game results in this pool, and runs `str_replace` on the template for `[round R]`, `[team N]`, `[game R/G]`, `[winner R/G]`, and `[loser R/G]` as appropriate.
4. After the per-round loop it walks placement positions 1..N, calls `PoolTeamFromStandings(...)`, and substitutes `[placement P]`.
5. Finally `[placement]` (the header) is replaced with the localised "Placement" string.

The external entry points follow the same shape with minor differences such as no fallback "Game N" label in `ext/*`, and country-aware flag rendering in `ext/countrystatus.php`.

## Pool generation

`GeneratePlayoffPools($poolId, $generate = true)` materialises a bracket into `uo_pool` rows and `uo_moveteams` entries: it reads the master pool's teams by `uo_team_pool.rank`, computes `R` (see "File naming"), loads the template, creates a follower pool per round with the parsed or standard moves, and marks the last one `placementpool = 1` for the placement walk in `lib/series.functions.php` and `TeamSeriesStanding()`. With `$generate = false` it returns the descriptors without writing, for previews.

## BYE handling

With an odd team count, the last slot has no opponent. The renderer treats a team with no games in the pool (`TeamPoolGamesArray()`) as the bye team and carries it into the next round's winner token, such as `[winner 1/3]` in a 5-team bracket.

Templates handle byes in two ways:

- **Carry-over only.** Templates for 3, 5, and 7 teams place the bye team's `[team N]` token directly into a column or rely on `[winner 1/⌈N/2⌉]` substitution to label the round-2 slot. They contain no `BYE` literal and no `[game R/G]` for the bye pair.
- **Visible BYE marker.** The 9-team template uses an explicit `BYE` literal in cells where the bye team's "opponent" would sit, and reserves slots in pool 2 and pool 3 for the bye carry-over. Cells that pair an actual team with `BYE` must not contain a `[game R/G]` token, because no game is generated for the pair and `str_replace` cannot fill the cell with a real score.

The validator allows `[winner R/⌈N/2⌉]` to exist without a matching `[game R/⌈N/2⌉]` in odd-team templates: the renderer fills these via the bye-pseudo-winner branch rather than from a stored game.

## Validator

`docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php` checks:

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
