# Schedule

A schedule is the combination of game rows (`uo_game`), their pool membership (`uo_game_pool`), field and time windows (`uo_reservation`, `uo_location`), placeholder participants (`uo_scheduling_name`, `uo_moveteams`), and the pages that render and edit them:

- generation: `lib/pool.functions.php`, `admin/poolgames.php`
- reservations: `lib/reservation.functions.php`, `admin/addreservation.php`
- placement: `admin/schedule.php`, `admin/saveschedule.php`
- rendering: `games.php`, PDF, iCalendar, the API

## Public View

`?view=games` (`games.php`) resolves scope from query parameters, maps `filter` into timetable query parameters, loads rows with `TimetableGames()`, loads reservation-group tabs with `TimetableGrouping()`, renders through timetable view helpers, and switches to printable HTML or PDF when requested.

Supported scope selectors are:

- `season=<seasonId>`
- `series=<seriesId>`
- `pool=<poolId>`
- `pools=<poolId,poolId,...>`
- `team=<teamId>`

If no scope is given, the page falls back to `CurrentSeason()`. `pools=` becomes the internal `poolgroup` mode. Invalid or empty `pools=` input also falls back to the current season.

Main display controls are `filter`, `group`, `print`, and `singleview`. `group` filters by reservation group, `print=1` switches to printable HTML, and `singleview=1` suppresses the normal menu and group-selector wrapper.

`games.php` maps visible filters into timetable query behavior:

| `filter` | `timefilter` | `order` | format |
| --- | --- | --- | --- |
| `tournaments` | `all` | `tournaments` | html |
| `series` | `all` | `series` | html |
| `places` | `all` | `places` | html |
| `timeslot` | `all` | `time` | html |
| `today` | `today` | `series` | html |
| `tomorrow` | `tomorrow` | `series` | html |
| `yesterday` | `yesterday` | `series` | html |
| `next` | `all` | `tournaments` | html |
| `season` | `all` | `places` | pdf |
| `onepage` | `all` | `onepage` | pdf |

`next` is not in the menu. The footer links iCalendar, printable HTML and both PDF layouts. A reservation is one `uo_reservation` row (field `fieldname`, group `reservationgroup`, start and end time) at a `uo_location`.

Grouping in `lib/timetable.functions.php`:

- `TournamentView()`: reservation group -> date / location -> pool
- `SeriesView()`: series -> pool
- `PlaceView()`: reservation group -> date -> field
- `TimeView()`: exact game time

## How a Game Row Is Built

`TimetableGames()` in `lib/timetable.functions.php` builds the rowset shared by HTML, PDF and the API, joining the game with its pool, series, season, reservation, location, teams, countries, scheduling names, and a `uo_goal` count (`scoresheet`) for scoresheet presence.

The HTML row shows a country flag before real team names in international events; the API also uses the abbreviation and country fields. The views preload live-media links (`GetMediaUrlListForGames(..., "live")`) and RSS state (`IsGameRSSEnabled()`).

`GameRow()` renders optional date, time, field, series, and pool columns; either real team names or scheduling-name placeholders; score state; optional translated `gamename`; optional live-media icons; and an info / action cell.

Info-cell logic:

- `Game history` appears for unstarted real-team matchups when `GetAllPlayedGames()` finds prior meetings
- `Gameplay` appears for finished games when the derived `scoresheet` count is non-zero
- `Ongoing` appears for live games, optionally linking to gameplay when scoresheet rows exist

`games.php` fetches a season comment with `CommentHTML(1, $id)` but does not render it.

## Scheduling Workflow

Reservations are created and edited through `admin/addreservation.php` with `AddReservation()` and `SetReservation()`. Relevant fields are season, location, field name, reservation group, start time, and end time. Field input supports comma-separated fields and numeric ranges, so adding multiple fields creates multiple `uo_reservation` rows.

`admin/poolgames.php` is the admin hub for generating games, previewing pairings, manually adding games, listing scheduled games by reservation, listing unscheduled games, listing moved games, and linking into drag-and-drop scheduling and manual editing. The main helpers here are `GenerateGames()`, `PoolAddGame()`, `PoolGames()`, `PoolGamesNotScheduled()`, and `PoolMovedGames()`.

`GenerateGames()` in `lib/pool.functions.php` supports round robin, playoff, Swiss draw, and crossmatch generation. It inserts rows into `uo_game` and visible linkage rows into `uo_game_pool` with `timetable=1`. When a pool does not yet contain real teams, generation can use placeholder participants from `uo_moveteams` and `uo_scheduling_name`.

Home and away assignment for generated games is event-controlled through `uo_season.hometeammode`:

- `0`: balance home team equally using the existing per-pool generation rules
- `1`: keep the higher-ranked side as home based on generator order / seed order

It affects previews and newly generated games only.

`uo_game_pool` is the only record of a game's pool (`uo_game` has no `pool` column):

- `timetable=1`: the owning pool, exactly one per game. Pool, series and season lookups join through it.
- `timetable=0`: carryover into continuation pools (e.g. pool A into upper pool F), so their stats include earlier results without duplicating the game.

`admin/schedule.php` builds a drag-and-drop board from unscheduled games loaded through `UnscheduledPoolGameInfo()`, `UnscheduledSeriesGameInfo()`, or `UnscheduledSeasonGameInfo()`, and reservation columns loaded through `ReservationInfoArray()`.

Game duration is `uo_game.timeslot`, else `uo_pool.timeslot`; it drives row height, offsets, and overflow and conflict checks.

`admin/saveschedule.php` is the save path. Reservation columns are serialized as minute offsets from reservation start, `ClearReservation()` first unschedules the games currently attached to that reservation that the user may schedule there (games of another event stay put and are reported as left unchanged), `ScheduleGame()` reapplies games with new start time and reservation id, and `UnScheduleGame()` clears rows from the unscheduled column.

Validation after save checks:

- reservation end-time overflow
- intra-pool conflicts
- inter-pool conflicts
- move-time constraints from `uo_movingtime`

Only pairs including a game on the saved board are reported; the other game may be outside it, but unrelated pre-existing conflicts are not reported. Pairs are not reordered: `TimetableIntraPoolConflicts()` returns them chronologically (`g1.time <= g2.time`), and `TimetableInterPoolConflicts()` returns source-pool game then destination-pool game as a dependency, so a destination game before its source is reported even without overlap.

`admin/editgame.php` is the direct edit path for one game row. It can change teams, placeholders, reservation, time, pool, validity, responsible team, translated game name, and live-stream fields.

## Settings

- `uo_setting.CurrentSeason`: default public scope
- `uo_setting.GameRSSEnabled`: per-row RSS icon
- `uo_season.timezone`: shown below schedule views by `PrintTimeZone()`
- `uo_season.hometeammode`: home assignment for generated games
- `uo_pool.timeslot`, `uo_game.timeslot`: game duration
- `uo_pool.type`, `ordering`, `color`: generation strategy, order, and PDF colors
- `uo_movingtime`: field-to-field move times for conflict checks

## Related outputs

Rows and the footer link to `reservationinfo`, `poolstatus`, `gameplay.php`, `gamecard.php`, iCalendar, printable HTML, and the PDF list and one-page layouts. `cust/default/pdfschedule.php` and the API schedule normalizer in `api/v1/router.php` render the same `TimetableGames()` rows differently.
