# Ranking

How team order is resolved within pools (`lib/standings.functions.php`) and how event final standings, statistics and season points are presented.

## Pool ranking entry point

`ResolvePoolStandings($poolId)` dispatches to a type-specific resolver based on the pool's `type` field:

- type 1: round-robin / series — `ResolveSeriesPoolStandings`
- type 2: playoff / bracket — `ResolvePlayoffPoolStandings`
- type 3: swiss draw — `ResolveSwissdrawPoolStandings`
- type 4: cross-match — `ResolveCrossMatchPoolStandings`

Each resolver writes the resulting position into `uo_team_pool.activerank`.

`ResolvePoolStandings()` has no rights check of its own, because result saves call it under game rights. Admin pages that recalculate on request use `RecalculatePoolStandings($poolId)`, which requires `hasEditTeamsRight()` on the pool's division.

`uo_game.forfeit`: `0` none, `1` home forfeited, `2` away forfeited, `3` both forfeited (both lose). The round-robin (`getMatchesWins`), playoff and cross-match resolvers take the win and loss from the forfeit flag, not the score, so a `0-0` forfeit still decides the game and advances the other team in a bracket; a double forfeit leaves bracket positions unchanged. The score is untouched, so goal-difference tie-breaks are unaffected. `SeriesTeamStatsPoints()`, `CalcTeamStats()` and `TeamsToCsv()` count wins, draws and losses the same way.

The Swiss-draw resolver ranks by victory points from the score margin, so a `0-0` forfeit gives both teams draw points there; record a Swiss forfeit with a decisive score if it must affect ranking. In every pool type the flag still excludes the game from spirit averages and shows the forfeit mark.

## `uo_team_pool` rank fields

- `rank`: initial seeding inside the pool. Set when teams are placed and not changed by the resolvers.
- `activerank`: current resolved standing. This is the column the ranking functions update and the column other code reads to determine pool order.

Resolvers that order teams use `activerank ASC, rank ASC` (or `rank ASC` only for the playoff resolver) so that ties on `activerank` fall back to the seeded order.

## Round-robin pool (type 1)

`ResolveSeriesPoolStandings` ranks teams by:

1. Games played (desc) — initial sort.
2. Score, defined as `wins × 2 + draws × 1` (`Score()` in `lib/standings.functions.php`).

For teams that share a rank, the following tie-breakers are applied in order. Solving any one of them moves on to the remaining tied teams:

1. Head-to-head: wins-based score in matches between the tied teams only.
2. Goal difference (`goalsmade - goalsagainst`) in matches between the tied teams only.
3. Goal difference across all matches in the pool.
4. Goals made in matches between the tied teams only.
5. Goals made across all matches in the pool.

"Goals" in the code (`getMatchesGoals`, `cmp_goalsdiff`, `cmp_goalsmade`) means points scored. Teams still tied after all five share an `activerank`.

After ranks are written, the resolver also triggers automatic pool moves:

- All pool games must be played: `hasstarted > 0` AND `isongoing = 0`.
- No two teams may share the same `activerank`.
- For every entry returned by `PoolMovingsFromPool($poolId)` whose destination pool has `mvgames = 1`, `PoolMakeMove(frompool, fromplacing, false)` is called and the destination pool is set visible.

## Playoff pool (type 2)

`ResolvePlayoffPoolStandings` walks teams in pairs by current `rank`: 1 vs 2, 3 vs 4, and so on.

For each pair, it counts wins from completed (`isongoing = 0`) non-tie games between the two teams within this pool:

- The team with more wins gets the lower (better) `activerank`.
- If both have equal wins, current positions are kept.
- When both teams have no remaining games in the pool, both are advanced via `TeamMove($teamId, $poolId, true)`.

If the pool has an odd number of teams, the last team is given the highest (worst) `activerank` and is moved if eligible.

After the pair-by-pair pass, `CheckSpecialRanking` applies any overrides from `uo_specialranking`.

## Swiss draw pool (type 3)

`ResolveSwissdrawPoolStandings` reads per-team statistics from `TeamVictoryPointsByPool` and sorts using `CompareTeamsSwissdraw`. The comparator switches between two orderings depending on whether the two teams being compared have each played exactly one game:

When `a.games == 1 AND b.games == 1`:

1. Victory points (desc)
2. Margin / point differential (desc)
3. Total points scored (desc)
4. Spirit score (desc)

Otherwise:

1. Number of games (desc)
2. Victory points (desc)
3. Opponent's victory points (desc)
4. Total points scored (desc)
5. Spirit score (desc)

`SolveStandingsAccordingSwissdraw` sweeps the sorted list and assigns `activerank`. Teams that compare equal share the same rank.

Move resolution and BYE handling for swiss draw live in `lib/swissdraw.functions.php`.

## Cross-match pool (type 4)

`ResolveCrossMatchPoolStandings` is structurally similar to the playoff resolver but uses the initial ordering `activerank ASC, rank ASC`. Teams are paired and ranked by head-to-head wins, and when both teams in a pair have no remaining games `TeamMove($teamId, $poolId)` is called (without the playoff resolver's `true` flag) to advance them.

`printCrossmatchPool()` in `poolstatus.php` renders one row per game, coloured with the winner and loser continuation pools from `PoolGetMoveToPool()` for the row's pair. The pair is derived from the home side's slot (falling back to the visitor's), mapped to its position in slot order: placings 1-2 are the first pair, 3-4 the second. This matches how `GenerateGames()`, the resolver and move `fromplacing` pair teams; the row number would break for `best N matches` pools, which have several rows per pair. A row with no resolvable slot gets no colours.

An unresolved placeholder (a `uo_scheduling_name` moved in through `uo_moveteams`) has no `uo_team_pool` row, so its slot falls back to `uo_moveteams.torank`. `TimetableGames($poolId, "pool", "all", "crossmatch")` orders rows by the same slot and fallback so a pair's games stay together; unresolvable rows come last.

## Special ranking overrides

`CheckSpecialRanking($poolId)` consults `uo_specialranking`, which maps a source `(frompool, fromplacing)` to a target rank in the current pool. Matching rows update `uo_team_pool.activerank` directly. This runs after the playoff resolver to allow tournament-specific overrides such as fixed re-seeding between phases.

## Lookup helpers

- `TeamPoolStanding($teamId, $poolId)`: returns the stored `activerank` for a team in a pool.
- `TeamSeriesStanding($teamId)`: walks the series' placement pools (`SeriesPlacementPoolIds`) in order and counts un-moved teams to derive the team's final placement. If the team is not found in any placement pool, it falls back to `TeamPoolStanding` for the team's home pool.

## Event final standings (`teams.php` `bystandings`)

`?view=teams&list=bystandings` renders one table with a placement column (`Gold`, `Silver`, `Bronze` in bold, then `4th`, `5th`, ...) and one column per series, from `SeriesFinalStandings($series_id)`. Per division it is all-or-nothing: confirmed manual placements are used when they cover every team (`HasCompleteManualFinalStandings`), otherwise the live order from `SeriesRanking($series_id)` with an "Automatic final standings, not confirmed" note in the header. Teams may share a placement cell. Disqualified teams go in a last row, shown only when there are any. International seasons show country flags.

`SeriesRanking` aggregates the series' pools through the placement-pool walk used by `TeamSeriesStanding` and `lib/series.functions.php`, so pool `activerank` values feed it.

Manual placements live in `uo_team_final_standing` and are edited per division in `admin/finalstandings.php` (seeded by the upgrade from existing `uo_team_stats`). The pre-fill order is the saved placements, else season points, else the live placement-pool order, else the team list. Every team must get a placement or a disqualification; shared placements are valid. Clearing reverts the division to live standings. The page warns when scheduled games are incomplete.

## Event statistics

At the end of an event an admin freezes the live standings into precomputed rows so cross-event reports read a stable answer.

### `admin/stats.php`

Gated by the `CALCSEASONSTATISTICS` permission. **Calculate** runs, each with `set_time_limit(120)`, from `lib/statistical.functions.php`:

1. `CalcSeasonStats($season)`: season totals
2. `CalcSeriesStats($season)`: per-series aggregates
3. `CalcTeamStats($season)`: final standings per series, from confirmed manual placements when complete, else live standings
4. `CalcTeamSpiritStats($season)`
5. `CalcPlayerStats($season)`: players without a profile id are skipped after the admin confirms their count
6. `SetEventReadonly($season)`

`IsSeasonStatsCalculated($season)` switches the page between **Calculate** and the totals with **Recalculate** and **Undo** (`DeleteSeasonStats($season)`, which also re-opens the event). The page reports how many divisions have confirmed final standings and warns about the rest.

After calculation it shows a drag-and-drop list per series (YUI) from `SeasonTeamStatistics($season)`. **Save final standings** POSTs `team1:team2:...:|team4:...:|` (ids per series, series separated by `|`) to `?view=admin/saveteamstandings`, which updates the manual final standings and the team-stats rows.

### Cross-event reports: `statistics.php`

`?view=statistics` reads the precomputed rows, grouped by season type then series type:

- `teamstandings` (default): per-event medal teams, `TeamStandings($season_id, $seriestype)`
- `spiritstandings`: per-event top 3 by spirit, `SeasonSpiritTopTeamsBySeriesType(...)`
- `playerscoreboard`: per-event top 3 players, `AlltimeScoreboard(...)`
- `playerscoresall`: all-time top 100, all-time Callahan top 20, and top 30 per group, `ScoreboardAllTime(...)`, sortable by games, assists (`pass`), goals or total

Totals are assists + goals; Callahans are part of goals and appear only in their own list. Events without stats are skipped; with none at all the page says "Event statistics have not yet been computed." Columns link to `?view=teams&list=bystandings` and `list=byspirit`.

## Season points

An alternative ranking independent of the pool resolvers. When a season sets `use_season_points`, admins enter an integer score per team per round, and `?view=teams&list=seasonpoints` (the `Points` tab) ranks by total, then the latest round's points, then team name, showing `total (r1 + r2 + ...)` when there are several rounds.

`admin/seasonpoints.php` requires `isSeasonAdmin($season)`. After choosing the event and division, an admin adds rounds (`round_no` positive, `round_name` non-empty, number pre-filled as max + 1), deletes them, and enters points per team (integers 0-1000, empty = 0; the first invalid entry aborts the whole save). The page works without `use_season_points`, with a warning, so rounds can be prepared in advance.

Storage helpers in `lib/seasonpoints.functions.php`: `SeasonPointsRounds()`, `AddSeasonPointsRound()`, `DeleteSeasonPointsRound()`, `SeasonPointsRoundPoints()`, `SaveSeasonPointsRoundPoints()`, `SeasonPointsSeriesTotals()`.

## Related files

- `lib/standings.functions.php`: pool-level ranking and tie-break logic.
- `lib/swissdraw.functions.php`: swissdraw move resolution and BYE handling.
- `lib/series.functions.php`: series ranking aggregation used by the `bystandings` view.
- `lib/pool.functions.php`: pool moves (`PoolMakeMove`, `PoolMovingsFromPool`, `PoolFollowersArray`).
- `lib/statistical.functions.php`: precomputed season / series / team / player stats reads and `Calc*` rebuild routines.
- `lib/seasonpoints.functions.php`: season-points round CRUD, per-round scoring, and per-series totals.
- `admin/stats.php`: admin UI for calculating, freezing, and manually reordering final standings.
- `admin/saveteamstandings.php`: handler that persists the drag-and-drop order from `admin/stats.php`.
- `admin/seasonpoints.php`: admin UI for managing season-points rounds and per-round points.
- `statistics.php`: cross-event team, spirit, and player leaderboards over precomputed stats.
- `teams.php`: rendering of the placement table for event final standings and the season-points leaderboard.
