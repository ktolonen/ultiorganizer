# Scorekeeper

This page documents the standalone Scorekeeper app under `scorekeeper/`.

Scorekeeper is the mobile-style officiating surface for game-day result and scoresheet entry. It uses the shared Ultiorganizer game, player, standings, and spirit helpers, but it has its own lightweight routed entrypoint and its own incremental workflow.

## Core entrypoints

- `scorekeeper/index.php`: bootstrap, session setup, routed page shell, and footer.
- `scorekeeper/login.php`: scorekeeper login page.
- `scorekeeper/respgames.php`: list of games the current user is responsible for.
- `scorekeeper/addplayerlists.php`: game-specific player list editor.
- `scorekeeper/addscoresheet.php`: incremental scoresheet entry and live game-clock control page.
- `scorekeeper/endgame.php`: final confirmation page before saving the final result.
- `scorekeeper/gameplay.php`: read-only replay of the saved game events.

Related scorekeeper pages split metadata into smaller task-oriented views:

- `scorekeeper/addofficial.php`
- `scorekeeper/addcomment.php`
- `scorekeeper/addfirstoffence.php`
- `scorekeeper/addhalftime.php`
- `scorekeeper/addtimeouts.php`
- `scorekeeper/addspirittimeouts.php`
- `scorekeeper/deletescore.php`
- `scorekeeper/scoreboard.php`

## Routing and shell

Scorekeeper uses the query-string view pattern under its own entrypoint:

- URL shape: `/scorekeeper/?view=<page>`
- page resolution: `resolveViewPath()` in `scorekeeper/index.php`
- auth guard: `scorekeeper/auth.php` via `lib/auth.guard.php`

The shell loads the shared customizable mobile stylesheet through `mobileStyles()`, so Scorekeeper follows the same mobile visual language as Spiritkeeper and other mobile-facing views.

## Main workflow

The current Scorekeeper workflow is incremental:

1. open the game from `respgames.php`
2. set the player lists in `addplayerlists.php`
3. control the game clock and enter goals in `addscoresheet.php`
4. record related metadata in the separate helper pages
5. confirm the result in `endgame.php`
6. review the replay in `gameplay.php`

This differs from `user/addscoresheet.php`, which is a larger bulk-edit form. Scorekeeper is designed for sideline use during the game.

## Game list behavior

`scorekeeper/respgames.php` shows games returned by `GameResponsibilityArray()`.

Current behavior:

- users can filter by event, "today only", and "hide played games"
- games are grouped by reservation group and field
- each listed game shows time, teams, current score, and ongoing/play-state styling
- the main actions are `Result`, `Players`, and `Scoresheet`

The list treats a game as played when `hasstarted > 0` and `isongoing = 0`.

## Live game clock

The current Scorekeeper flow includes a live game clock for seasons where `hide_time_on_scoresheet` is false.

Shared timer state comes from `lib/game.functions.php`:

- `GameTimerState()`
- `GameTimeStart()`
- `GameTimePause()`
- `GameTimeResume()`
- `GameElapsedTime()`

Current behavior in `scorekeeper/addscoresheet.php`:

- the scorekeeper can start, pause, resume, and end the game
- before the clock is started, the scorekeeper can choose `No game clock` for that game and fall back to manual time entry
- a live `MM:SS` clock is shown in the page header while the timed workflow is active
- when the clock is paused, the scorekeeper can set it to an exact `MM:SS` value
- goals cannot be added until the game clock has been started
- selecting the team radio for a new goal stamps the current rounded clock time into the goal time fields
- the header clock updates client-side once per second while the game is running

Timer lifecycle normalization currently resets timer state when:

- a game clock is started,
- a game is finalized through `GameSetResult()`,
- a result is cleared through `GameClearResult()`.

## Hidden-time seasons

If the season uses `hide_time_on_scoresheet`, Scorekeeper keeps the older non-clock behavior.

In that mode:

- the live game clock is not used,
- point times are not exposed as operator-entered timed values,
- the old "save as result" behavior remains in the scorekeeper flow.

This preserves existing behavior for events that intentionally do not use visible point times.

## Goal entry behavior

Goals are entered one at a time in `scorekeeper/addscoresheet.php`.

Current persistence behavior:

- each goal is inserted with `GameAddScoreEntry()` into `uo_goal`
- if the aggregate score increased, `GameUpdateResult()` advances the current result on `uo_game`
- the page validates that the new goal time is later than the previous point when times are in use
- deleting the latest goal is handled by `scorekeeper/deletescore.php`

Assist and scorer selections are drawn from the game-specific played roster in `uo_played`, not directly from the full team roster.

## Related game-data pages

Scorekeeper stores related game metadata through separate pages:

- `addofficial.php`: game official name
- `addcomment.php`: game note
- `addfirstoffence.php`: starting offence
- `addhalftime.php`: halftime end time
- `addtimeouts.php`: ordinary timeouts
- `addspirittimeouts.php`: spirit timeouts when spirit mode is enabled and timed scoresheets are visible

Timeout-related pages now follow the same incremental pattern:

- the page shows the current live clock
- the scorekeeper explicitly selects which team took the timeout
- that selection stamps the current rounded game time into the next empty slot for that team
- changing the selection before saving moves the pending stamped timeout to the newly selected team

`addspirittimeouts.php` also exposes local pause/resume controls for the game clock because spirit timeouts often require the clock to be paused.

## Ending the game

Timed scorekeeper entry no longer saves the final result directly from `addscoresheet.php`.

Instead:

- `addscoresheet.php` links to `endgame.php`
- `endgame.php` shows the current final result and a gameplay-style summary
- confirming from `endgame.php` calls `GameSetResult()`
- the user is then redirected to `gameplay.php`

This reduces accidental finalization during active entry.

## Result-only entry

Scorekeeper still includes result-oriented pages:

- `scorekeeper/addresult.php`
- `scorekeeper/result.php`

These pages are separate from the incremental detailed scoresheet flow. They update aggregate result state on `uo_game`, while the detailed point-by-point sheet remains in `uo_goal`.

## Data model

Scorekeeper uses the same core scoresheet tables as the rest of the application:

- `uo_game`: aggregate result, halftime, official, status flags, and timer columns
- `uo_played`: per-game player list
- `uo_goal`: detailed scoring sequence
- `uo_timeout`: ordinary timeouts
- `uo_spirit_timeout`: spirit timeouts
- `uo_gameevent`: game events such as starting offence and other event markers
- `uo_comment`: game notes

The live clock additionally uses these `uo_game` columns:

- `timer_start`
- `timer_pause_start`
- `timer_paused_duration`

## Relationship to other entry surfaces

Scorekeeper overlaps with mobile and desktop scoresheet functionality, but it is intentionally narrower:

- `user/addscoresheet.php` is the larger bulk editor
- `mobile/addscoresheet.php` is the deprecated legacy incremental mobile flow kept for compatibility
- `scorekeeper/` is the dedicated officiating surface centered on responsibility-based game access and now on the live clock workflow

Spirit score submission itself does not live in Scorekeeper. That workflow is handled in `spiritkeeper/` and the main logged-in spirit pages.
