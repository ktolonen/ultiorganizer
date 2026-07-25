# Scorekeeper

This page documents the standalone Scorekeeper app under `scorekeeper/`.

Scorekeeper is the mobile-style officiating surface for game-day result and scoresheet entry. It uses the shared Ultiorganizer game, player, standings, and spirit helpers, but it has its own lightweight routed entrypoint and its own incremental workflow.

## Core entrypoints

- `scorekeeper/index.php`: bootstrap, session setup, routed page shell, and footer.
- `scorekeeper/login.php`: Scorekeeper login page with interface language selection.
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

## Client script

`scorekeeper/index.php` loads `script/scorekeeper.js` into every Scorekeeper page. It holds two
independent pieces:

- the shared game clock (see [Live game clock](#live-game-clock) below)
- a double-submit guard on every form in the app: a second submit while one is in flight is blocked
  and the submit controls are disabled until the page navigates. The form is *marked* busy
  synchronously, so a second submit is rejected immediately, but the buttons are only *disabled* one
  tick later — a disabled submit button is left out of the POST body, and the Scorekeeper pages
  branch on which button was pressed (`add` vs `forceadd`, `startgame` vs `pausegame`). Forms
  restored from the back/forward cache are re-enabled on `pageshow`.

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

### Shared clock module

Every page that shows the clock (`addscoresheet.php`, `addtimeouts.php`, `addspirittimeouts.php`,
`addhalftime.php`, `endgame.php`) renders it through the same three helpers, so the timing rules
live in one place:

- `ScorekeeperTimerStateDefaults()` in `scorekeeper/auth.php`: the timer state shape used when the
  game clock is not in play
- `ScorekeeperClockHeader()` in `scorekeeper/auth.php`: the `#gametime` header element, styled by
  `.sk-gameclock`
- `ScorekeeperClockScript()` in `scorekeeper/auth.php`: hands `GameTimerState()` to
  `window.scorekeeperClock.init()` in `script/scorekeeper.js`

`script/scorekeeper.js` anchors on the `elapsed` second count from `GameTimerState()` plus a
client-side timestamp for the moment the server took that reading, and derives every value from the
difference on demand. Counting seconds in a `setInterval` drifts badly on phones, because browsers
throttle or suspend timers while the screen is off. Because only *differences* of `Date.now()` are
used, a device with a wrong absolute clock still shows the correct game time.

The anchor is the Navigation Timing `responseStart`, not `Date.now()` at script execution. The
server reads the clock while generating the response, so anchoring on script start would fold the
transfer and parse time into the clock and leave it permanently that far behind — on a slow venue
connection that means goals and timeouts stamped early. `responseStart` is the closest observable
moment to the server's reading, since `scorekeeper/index.php` buffers the whole page and flushes it
at the end. Implausible values (an anchor in the future, or more than five minutes old) fall back to
`Date.now()`, as do browsers without Navigation Timing.

Callers that prefill a time field (goal time, timeout time) call
`window.scorekeeperClock.roundedTime()`, which recomputes on each call rather than reading a
cached tick value. Reading a cached value is what previously stamped a stale time into a goal
added right after the screen woke up. `window.scorekeeperClock.isActive()` is false on pages where
the clock is not shown, so those callers leave the field alone instead of filling in `00:00`.

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

`addscoresheet.php` warns when either team's played roster is empty, because empty assist and scorer
dropdowns are otherwise a confusing symptom of players never having been checked in.

## Roster accreditation

`uo_season.require_accreditation` is an `EVENT_SETTING`, off by default, set in
`admin/addseasons.php`.

When it is on, `scorekeeper/addplayerlists.php` will not let a scorekeeper add a player whose
`uo_player.accredited` is 0. This is how WFDF events keep a banned player, or one withdrawn for
medical reasons, off the scoresheet: the tournament desk clears the accredited flag, and the
scorekeeper then sees the player marked and cannot select them.

The rules are:

- unaccredited and **not** on the roster: checkbox, jersey and role controls are disabled, and the
  save handler refuses the addition even if the disabled control is bypassed
- unaccredited but **already** on the roster: controls stay enabled so the scorekeeper can
  deliberately remove the player, since silently dropping someone who already has goals recorded in
  `uo_goal` would corrupt the scoresheet
- either way, the row is marked `Not accredited`

The setting is off by default because `uo_player.accredited` is `NOT NULL DEFAULT 0`, so in an
installation that never accredits anyone, enforcing it would make every roster unfillable.

Enforcement lives in the scorekeeper page, not in `GameAddPlayer()`. That helper writes the
`uo_played.accredited` snapshot which `SeasonUnaccredited()` and `admin/accreditation.php` read, so
tournament desks can still record unaccredited players and acknowledge them afterwards. The desktop
`user/addplayerlists.php` is unaffected for the same reason.

## Related game-data pages

Scorekeeper stores related game metadata through separate pages:

- `addofficial.php`: scorekeeper name (the `official` column; the label was renamed because
  scorekeepers track score and time, they do not make rulings)
- `addcomment.php`: game note
- `addfirstoffence.php`: starting offence
- `addhalftime.php`: halftime end time
- `addtimeouts.php`: ordinary timeouts
- `addspirittimeouts.php`: spirit stoppages when spirit mode is enabled and timed scoresheets are visible

Timeout-related pages now follow the same incremental pattern:

- the page shows the current live clock
- the scorekeeper explicitly selects which team took the timeout
- that selection stamps the current rounded game time into the next empty slot for that team
- changing the selection before saving moves the pending stamped timeout to the newly selected team

`addspirittimeouts.php` also exposes local pause/resume controls for the game clock because spirit stoppages often require the clock to be paused.

The number of timeout slots `addtimeouts.php` renders per team comes from the game's pool format
through `GameTimeoutsPerTeam()`: `uo_pool.timeouts`, doubled when `uo_pool.timeoutsper` is `half`,
falling back to 4 when the pool defines no limit. The page never renders fewer slots than there are
timeouts already recorded for a team, because saving clears all timeouts and rewrites only the
submitted slots, so a lowered pool limit would otherwise delete existing entries.

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
- `uo_spirit_timeout`: spirit stoppages
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
