# Scoresheet

A scoresheet is not one table. It combines:

- `uo_game`: teams, schedule context, current or final score, halftime, official (scorekeeper name), status flags (`isongoing`, `hasstarted`) and live-clock columns
- `uo_played`: the per-game roster, with the jersey numbers and captain roles used in that game
- `uo_goal`: the point-by-point sequence
- `uo_timeout`, `uo_spirit_timeout`, `uo_gameevent`, `uo_comment`: timeouts, spirit stoppages, game events and the game note

See `sql/ultiorganizer.sql` for columns.

## Input paths

All paths write through shared mutators, mostly in `lib/game.functions.php`, that check their own rights: `hasEditGameEventsRight($gameId)` for most, `hasEditGamePlayersRight($gameId)` for roster changes, and the comment rights in `SetGameComment()`. These rights are false in a read-only event unless the user can bypass it (see `docs/permissions.md`). The by-game-ID result entry is the exception; see "Authorization" in `docs/scoresheet-history.md`.

### Result only

`user/addresult.php` (also `scorekeeper/addresult.php` and `scorekeeper/result.php`) records the aggregate score only; it writes nothing to `uo_goal` or `uo_played`.

- `GameUpdateResult()`: score, `isongoing=1`, `hasstarted=1`
- `GameSetResult()`: score, `isongoing=0`, `hasstarted=2`
- `GameClearResult()`: clears the result and the started flags

### Player lists

`user/addplayerlists.php` (both teams, plus captains and spirit captains), `scorekeeper/addplayerlists.php` (one team at a time) build `uo_played` from the team roster in `uo_player` with `GameAddPlayer()`, `GameRemovePlayer()`, `GameSetPlayerNumber()`, `GameSetCaptains()` and `GameSetSpiritCaptains()`.

Detailed entry resolves assist and scorer numbers through `uo_played`, not `uo_player`, so it depends on the player lists.

### Desktop scoresheet

`user/addscoresheet.php` is a bulk editor that rewrites the whole sheet on save: points (team, assist, scorer, time unless hidden), scorekeeper, starting offence, timeouts, spirit stoppages (spirit-enabled seasons with visible times), halftime, game note, and the ongoing flag.

On save it calls `GameSetScoreSheetKeeper()`, `GameSetHalftime()`, `GameSetStartingTeam()`, `SetGameComment(COMMENT_TYPE_GAME, ...)`, clears and re-adds timeouts and spirit stoppages, and replaces the point sequence with `GameRemoveAllScores()` plus `GameAddScore()` per point. An ongoing game updates the result with `GameUpdateResult()`; clearing the ongoing flag finalizes it with `GameSetResult()`.

### Scorekeeper

`scorekeeper/` (see `docs/scorekeeper.md`) enters one point at a time with `GameAddScoreEntry()`, advance the score with `GameUpdateResult()`, and finalize with `GameSetResult()`. Metadata has its own pages (`addofficial.php`, `addcomment.php`, `addhalftime.php`, `addtimeouts.php`, `addspirittimeouts.php`, `addfirstoffence.php`). Spirit scores are submitted in `spiritkeeper/` or the logged-in user pages, not here.

## Change history

Every mutation above also records a row in `uo_scoresheet_history`, and a bulk rewrite captures one restorable snapshot of the state it replaces. See `docs/scoresheet-history.md`.

## Parallel editing

The desktop save rewrites the whole sheet, so a sheet loaded before a scorekeeper entered a point would delete that point. To prevent this, `ScoresheetHistoryToken()` reads the game's highest `history_id` before any rendered state, and the form carries it in a hidden field. Reading it first means a change landing in between can only cause a spurious refusal, never a silent overwrite.

The token counts only changes the desktop sheet rewrites:

- `result`, `forfeit`, `goal`, `timeout`, `spirit_timeout`, `official`, `halftime`, `comment`, the starting-offence `gameevent`, `fixture` and `restore`
- `played`, except captain and spirit-captain updates, because the save resolves posted jersey numbers against the current roster
- `timer` start and reset, which also write `isongoing` and `hasstarted`

Other clock changes, `defense`, `mediaevent` and cap events are excluded: the sheet never writes them, and counting them would make a scorekeeper collide with their own work.

The comparison runs after the payload validates and before the save's own writes, which record their own history rows. A payload with no token is a conflict. On a mismatch the save is refused with the entries kept, and the refusal carries the compared token, so saving again deliberately overwrites exactly the changes the operator was warned about. A save refused over its own point validation keeps the older token.

This is a comparison, not a lock: a point entered between the check and the rewrite is lost, but recoverable from the snapshot the save takes first. `scorekeeper/` carries no token; its point entry appends, but its timeout pages still replace the list unchecked. With `DisableScoresheetHistory` on, the token never moves and saves are unchecked.

## Data notes

- `uo_goal.num` is the point order from 1. Older scorekeeper games, and games from the removed `mobile/` pages, may start at 0; a desktop save renumbers from 1. `uo_goal` also stores the running score after each point.
- `uo_gameevent` holds the starting offence and cap events. Caps use type `half_cap` or `time_cap` with the point cap in `info`, and are shown without a team.
- The game note is a `uo_comment` row with `type = COMMENT_TYPE_GAME` (4) and `id` = game id.
- Pool rules (halftime, point cap, time cap, timeouts) come from `uo_pool`; `hide_time_on_scoresheet` from `uo_season`.

## Replay views

`gameplay.php` is the full replay: result (`GameResult()`), team scoreboards (`GameTeamScoreBorad()`), the goal list (`GameGoals()`), timeouts, spirit stoppages and events (`GameEvents()`), neutral cap markers, halftime markers, captain markers, and the game note (`GameCommentHtml(COMMENT_TYPE_GAME)`). Scoreboard totals count one per assist and goal; Callahans are included in goals, not added again.

`scorekeeper/gameplay.php` shows a compact sequential replay of the same data and link to team scoreboards.

## Hidden times

When `uo_season.hide_time_on_scoresheet` is on, the entry pages take no point times, timeouts or spirit stoppages (the flows store increasing synthetic times instead), and the replay views hide times. The point sequence is still kept.

## PDF scoresheets

`user/pdfscoresheet.php` prints blank field-use scoresheets from the schedule, rosters and team names, using `cust/<customization>/pdfscoresheet.php` with fallback to `cust/default/`. It does not render saved `uo_goal` data. See `docs/pdf-printing.md`.
