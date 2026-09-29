# Scorekeeper

`scorekeeper/` is the mobile officiating app for game-day result and scoresheet entry. It uses the shared game, player, standings and spirit helpers, with its own routed entrypoint and an incremental workflow. It replaces the deprecated `mobile/` flow; `user/addscoresheet.php` remains the desktop bulk editor. Spirit scores are submitted in `spiritkeeper/`, not here. See `docs/scoresheet.md` for the shared data model.

## Pages

- `index.php`: bootstrap, session, routed shell (`?view=<page>`, resolved by `resolveViewPath()`), guarded by `scorekeeper/auth.php` via `lib/auth.guard.php`. Styles come from `mobileStyles()`.
- `login.php`: login with interface language selection.
- `respgames.php`: the user's games from `GameResponsibilityArray()`, filterable by event, "today only" and "hide played games", grouped by reservation group and field, with `Result`, `Players` and `Scoresheet` actions. A game is played when `hasstarted > 0` and `isongoing = 0`.
- `addplayerlists.php`: per-game roster for one team at a time.
- `addscoresheet.php`: goal entry and live clock control.
- `endgame.php`: confirmation before saving the final result.
- `gameplay.php`: read-only replay.
- `addresult.php`, `result.php`: aggregate result only.
- Metadata pages: `addofficial.php` (scorekeeper name, stored in `official`), `addcomment.php`, `addfirstoffence.php`, `addhalftime.php`, `addscorecap.php`, `addtimeouts.php`, `addspirittimeouts.php`, `deletescore.php` (removes the latest goal), `scoreboard.php`.

Workflow: open the game from `respgames.php`, set player lists, run the clock and enter goals, record metadata, confirm in `endgame.php`, review in `gameplay.php`.

## Client script

`scorekeeper/index.php` loads `script/scorekeeper.js` on every page with a relative `src` (`$styles_prefix`), not `BASEURL`: an absolute `BASEURL` fails for a phone reaching the server by another host name. The script holds:

- the shared game clock (see [Live game clock](#live-game-clock))
- a double-submit guard on every form. The form is marked busy synchronously, but its buttons are disabled one tick later, because a disabled submit button is left out of the POST and the pages branch on which button was pressed (`add` vs `forceadd`, `startgame` vs `pausegame`). Forms restored from the back/forward cache are re-enabled on `pageshow`.

## Live game clock

Used when the season's `hide_time_on_scoresheet` is off. State lives in the `uo_game` columns `timer_start`, `timer_pause_start` and `timer_paused_duration`, managed by `GameTimerState()`, `GameTimeStart()`, `GameTimePause()`, `GameTimeResume()` and `GameElapsedTime()` in `lib/game.functions.php`.

In `addscoresheet.php` the scorekeeper can start, pause, resume and end the game, or choose `No game clock` before starting and enter times manually. A paused clock can be set to an exact `MM:SS`. Goals cannot be added before the clock starts, and selecting the scoring team stamps the current rounded time into the goal fields.

Starting the clock, `GameSetResult()` and `GameClearResult()` reset the timer state.

### Shared clock module

`addscoresheet.php`, `addtimeouts.php`, `addspirittimeouts.php`, `addhalftime.php` and `endgame.php` render the clock through helpers in `scorekeeper/auth.php`:

- `ScorekeeperTimerStateDefaults()`: timer state when the clock is not in play
- `ScorekeeperClockHeader()`: the `#gametime` element (`.sk-gameclock`)
- `ScorekeeperClockScript()`: passes `GameTimerState()` to `window.scorekeeperClock.init()`

`script/scorekeeper.js` anchors on the server's `elapsed` seconds plus a client timestamp and derives the time from `Date.now()` differences on demand, so throttled phone timers do not drift and a wrong device clock does not matter. The anchor is Navigation Timing `responseStart`, the closest moment to the server's reading (`index.php` buffers the whole page); implausible values (in the future or over five minutes old) and browsers without Navigation Timing fall back to `Date.now()`.

`window.scorekeeperClock.roundedTime()` recomputes on every call, so a time prefilled right after the screen wakes is current. `isActive()` is false where no clock is shown, and callers then leave the field unchanged rather than filling in `00:00`.

## Hidden-time seasons

With `hide_time_on_scoresheet` on, there is no live clock, no operator-entered point times, and the result is saved with the "save as result" action.

## Goal entry

Each goal is inserted with `GameAddScoreEntry()`, and `GameUpdateResult()` advances the score when the total increased. With times in use, a goal must be later than the previous point. Assist and scorer choices come from `uo_played`, and the page warns when either team's played roster is empty.

## Roster accreditation

`uo_season.require_accreditation` (`EVENT_SETTING`, off by default, set in `admin/addseasons.php`) stops `scorekeeper/addplayerlists.php` and `user/addplayerlists.php` from adding a player whose `uo_player.accredited` is 0, which is how WFDF events keep a banned or medically withdrawn player off the scoresheet.

- Unaccredited and not on the roster: controls are disabled and the save handler refuses the addition.
- Unaccredited but already on the roster: controls stay enabled so the player can be removed deliberately; dropping them silently would orphan their goals.
- Either way the row is marked `Not accredited`.

It is off by default because `accredited` defaults to 0, which would make every roster unfillable in an installation that never accredits.

`GameAddPlayer()` enforces the rule through `GameAllowsPlayerOnRoster()`, so every roster path, including the deprecated `mobile/addplayerlists.php`, is covered; the two page handlers also disable the controls. A player already on the game's roster stays allowed. `GameAddPlayer()` also writes the `uo_played.accredited` snapshot read by `SeasonUnaccredited()` and `admin/accreditation.php`.

## Timeouts

Timeout pages show the live clock. Selecting a team stamps the current rounded time into that team's next empty slot; changing the selection moves the pending stamp. `addspirittimeouts.php` also offers pause and resume, since spirit stoppages usually stop the clock.

`addtimeouts.php` renders `GameTimeoutsPerTeam()` slots per team: `uo_pool.timeouts` (doubled when `timeoutsper` is `half`) plus `timeoutsovertime`, or 4 when the pool sets no limit. It never renders fewer slots than already recorded, because saving rewrites all timeouts.

## Caps

When timed actions are available, `addscoresheet.php` offers `Halftime cap` and `Time cap`. A cap records when it was called and the new point cap (see `docs/terminology.md`). The form suggests the current rounded clock time (or the latest point time without a clock) and a point cap one above the leading score, and can update or remove an existing cap.

An existing cap stays editable after its point cap is reached; only a new cap must name a reachable one. Saving unchanged values is a no-op: `GameSetCapEvent()` compares time and target first.

Caps are `uo_gameevent` rows of type `half_cap` or `time_cap` (time in `time`, point cap in `info`, exposed as `target` in the v1 gameplay API), rendered by `GameCapEventText()` as neutral events. With hidden times, cap actions and cap times are hidden.

## Ending the game

Timed entry does not finalize from `addscoresheet.php`. It links to `endgame.php`, which shows the final result and a summary; confirming calls `GameSetResult()` and redirects to `gameplay.php`.
