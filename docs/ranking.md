# Ranking

This page describes how Ultiorganizer resolves team order within pools and how event-wide final standings are presented.

The main source for pool ranking is `lib/standings.functions.php`. Final-standings rendering for an event lives in `teams.php` under the `bystandings` list.

## Pool ranking entry point

`ResolvePoolStandings($poolId)` dispatches to a type-specific resolver based on the pool's `type` field:

- type 1: round-robin / series — `ResolveSeriesPoolStandings`
- type 2: playoff / bracket — `ResolvePlayoffPoolStandings`
- type 3: swiss draw — `ResolveSwissdrawPoolStandings`
- type 4: cross-match — `ResolveCrossMatchPoolStandings`

Each resolver writes the resulting position into `uo_team_pool.activerank`.

Games marked as forfeits (`uo_game.forfeit = 1`) are treated identically to played games in all ranking calculations. The forfeit flag is a display annotation only and has no effect on standings, tie-breaks, or pool moves.

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

In Ultimate parlance "goals" means points scored. The codebase uses the `goals*` naming throughout (`getMatchesGoals`, `cmp_goalsdiff`, `cmp_goalsmade`).

If teams remain tied after all five conditions, they keep a shared `activerank`. The resolver then continues with any teams still below them.

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

## Special ranking overrides

`CheckSpecialRanking($poolId)` consults `uo_specialranking`, which maps a source `(frompool, fromplacing)` to a target rank in the current pool. Matching rows update `uo_team_pool.activerank` directly. This runs after the playoff resolver to allow tournament-specific overrides such as fixed re-seeding between phases.

## Lookup helpers

- `TeamPoolStanding($teamId, $poolId)`: returns the stored `activerank` for a team in a pool.
- `TeamSeriesStanding($teamId)`: walks the series' placement pools (`SeriesPlacementPoolIds`) in order and counts un-moved teams to derive the team's final placement. If the team is not found in any placement pool, it falls back to `TeamPoolStanding` for the team's home pool.

## Event final standings (`teams.php` `bystandings`)

The "Standings" tab in `teams.php` (`?view=teams&list=bystandings`) renders a single table with one column per series.

For each series, the view calls `SeriesRanking($series_id)` from `lib/series.functions.php` to retrieve teams in final placement order. It then composes a placement column on the left and one team column per series:

- Row 1: `Gold`
- Row 2: `Silver`
- Row 3: `Bronze`
- Rows 4+: ordinal placement (`4th`, `5th`, ...)

The top three rows render in bold. Empty cells are rendered for series that have fewer placements than the longest column. If the season is marked international, each team name is preceded by a country flag.

`SeriesRanking` is the authoritative source for placement order across a series. It aggregates results across the series' pools so that final placements reflect both the round-robin phase and any playoff or placement rounds. Pool-level `activerank` values written by the resolvers above feed into this aggregation through the placement-pool walk used by `TeamSeriesStanding` and the series-level helpers in `lib/series.functions.php`.

## Event statistics

The pool resolvers above and `SeriesRanking()` produce *live* standings that update whenever pool moves and game results change. At the end of an event an admin freezes those standings into precomputed statistics rows so cross-event reports can read a stable answer without recomputing per request.

### Computing and freezing: `admin/stats.php`

The admin entry point is `admin/stats.php`. It is gated by the `CALCSEASONSTATISTICS` layout permission.

Pressing **Calculate** (`calc` POST) runs the following helpers from `lib/statistical.functions.php` in order, each under a `set_time_limit(120)` budget:

1. `CalcSeasonStats($season)`: aggregate season totals (teams, players, games).
2. `CalcSeriesStats($season)`: per-series aggregates.
3. `CalcTeamStats($season)`: per-team final standings within each series.
4. `CalcTeamSpiritStats($season)`: per-team spirit aggregates.
5. `CalcPlayerStats($season)`: per-player scoring stats.
6. `SetEventReadonly($season)`: marks the event read-only so live results no longer change.

If the season has players without a profile id, the page shows the count and asks the admin to confirm before running. Player statistics are skipped for those rows.

`IsSeasonStatsCalculated($season)` controls page state. Before the first calculation only the **Calculate** button is shown. After calculation the page renders the season totals (teams / players / games / divisions) plus **Recalculate** and **Undo** buttons. **Undo** (`undo` POST) calls `DeleteSeasonStats($season)`, which clears the precomputed rows and re-opens the event for live changes.

#### Manual reorder of final standings

Once stats are calculated, the page renders a draggable list per series under the **Final Standings** heading. The list source is `SeasonTeamStatistics($season)`, grouped by series with one column per series, using YUI drag-and-drop.

When the admin presses **Save standings**, the page builds a request string of the form `team1:team2:…:|team4:team5:…:|` — colon-separated team ids per series, pipe-separated between series — and POSTs it asynchronously to `?view=admin/saveteamstandings`. The handler persists the new order back into the precomputed team-stats rows. The save indicator is rendered into `#responseStatus`.

This is the only path that lets an admin override the resolver-derived order, for example to encode a placement decision the resolvers cannot express on their own.

### Cross-event reports: `statistics.php`

`statistics.php` reads the precomputed rows produced by `admin/stats.php` and renders cross-event leaderboards. It is a public routed view (`?view=statistics`) with four lists, all grouped first by season type and then by series type:

- `teamstandings` (default): per-event Gold / Silver / Bronze teams via `TeamStandings($season_id, $seriestype)`.
- `spiritstandings`: per-event Gold / Silver / Bronze by spirit via `SeasonSpiritTopTeamsBySeriesType($season_id, $seriestype, 3)`.
- `playerscoreboard`: per-event top 3 player scoreboard via `AlltimeScoreboard($season_id, $seriestype)`.
- `playerscoresall`: all-time top 100 plus per-(season type, series type) top 30 via `ScoreboardAllTime(...)`. Sortable by games, assists (`pass`), goals, or total via the `sort` query parameter.

Events without precomputed stats are skipped silently. If no event in any group has stats yet, the page shows "Event statistics have not yet been computed."

The team-standings columns link back to the live placement table (`?view=teams&season=…&list=bystandings`), and the spirit-standings columns link to the live spirit list (`?view=teams&season=…&list=byspirit`).

## Season points administration

Season points are an alternative ranking surface independent of the per-pool resolvers above. When an event opts in via `use_season_points` on the season, admins enter a fixed integer score for each team in each round, and the totals across rounds drive the `Points` tab in `teams.php`.

The admin entry point is `admin/seasonpoints.php`. It is restricted by `isSeasonAdmin($season)` and uses the `SEASONADMIN` left menu.

### Workflow

1. Select an event (season). When the page is opened without `season`, only the season picker is rendered.
2. Select a division (series) within the event. The first available series from `SeasonSeries` is used if the requested one is not valid. If no series exist, the page shows "No divisions defined" and exits.
3. List existing rounds in a table with a per-row delete button (`delete_round`).
4. Add a new round with `round_no` (positive integer) and `round_name` (non-empty) via `add_round`. The form pre-fills the next round number as `max(round_no) + 1`.
5. Select a round and enter points per team (`save_points`). Each entry must be an integer between 0 and 1000; empty entries default to `0`. The first validation error is shown as a warning and the entire save is aborted.

The team table in the round-edit form is sortable by team name, round points, or total points (`sort` and `dir` query parameters). Sort ties fall back to team name.

A warning banner is shown if the selected season does not have `use_season_points` enabled, but the admin can still manage data — useful for preparing rounds before flipping the flag on.

### Storage

Season points data is read and written through `lib/seasonpoints.functions.php`:

- `SeasonPointsRounds($season, $seriesId)`: rounds for a (season, series) pair.
- `AddSeasonPointsRound($season, $seriesId, $roundNo, $name)` / `DeleteSeasonPointsRound($roundId)`: round CRUD.
- `SeasonPointsRoundPoints($roundId)`: array of points keyed by `team_id` for one round.
- `SaveSeasonPointsRoundPoints($roundId, $pointsByTeam)`: persist a full round's points.
- `SeasonPointsSeriesTotals($season, $seriesId)`: per-team sums across all rounds in the series.

### Public view

`teams.php` exposes a `Points` tab when `seasonInfo.use_season_points` is set (`?view=teams&list=seasonpoints`). The list orders teams by season total descending, with the most recent round's points as the first tie-breaker and team name as the final fallback. Each row shows the running total and, if more than one round exists, a `total (r1 + r2 + …)` breakdown.

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
