# Schedule

This document describes schedule behavior as it is implemented today in Ultiorganizer.

In this codebase, a "schedule" is not a single object or table. It is the combination of game rows in `uo_game`, pool linkage in `uo_game_pool`, reservation windows in `uo_reservation`, placeholder participants in `uo_scheduling_name`, and the public and admin pages that render, assign, or validate time and field placement.

This page focuses on current behavior. It does not propose workflow changes.

## Overview

The schedule implementation has four main layers:

- generation: create game rows from pool structure in `lib/pool.functions.php` and `admin/poolgames.php`
- reservations: define date, field, and venue windows in `lib/reservation.functions.php` and `admin/addreservation.php`
- placement: assign games to reservations and start times in `admin/schedule.php` and `admin/saveschedule.php`
- rendering: expose the result through `games.php`, PDF, iCalendar, API, and related read-only pages

The practical result is that "building the schedule" means creating reservations, generating or manually adding games, assigning them to fields and times, validating conflicts, and rendering the result in several outputs.

## Public View

Main page:

- `games.php`, routed as `index.php?view=games`

`games.php` is mostly a controller. It resolves scope from query parameters, maps `filter` into timetable query parameters, loads rows with `TimetableGames()`, loads reservation-group tabs with `TimetableGrouping()`, renders through timetable view helpers, and switches to printable HTML or PDF when requested.

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

Notes:

- `next` is implemented but not exposed in the visible menu
- the footer also exposes iCalendar export, printable HTML, and both PDF layouts

Important grouping terms:

- reservation group: `uo_reservation.reservationgroup`
- location: `uo_location`
- reservation: one `uo_reservation` row, including start and end time
- field: `uo_reservation.fieldname`

The main HTML grouping logic lives in `lib/timetable.functions.php`:

- `TournamentView()`: reservation group -> date / location -> pool
- `SeriesView()`: series -> pool
- `PlaceView()`: reservation group -> date -> field
- `TimeView()`: exact game time

## How a Game Row Is Built

`TimetableGames()` in `lib/timetable.functions.php` builds the main schedule rowset. It joins:

- `uo_game`
- a derived `uo_goal` count for scoresheet presence
- `uo_pool`
- `uo_series`
- `uo_season`
- `uo_reservation`
- `uo_location`
- home and visitor `uo_team`
- home and visitor `uo_country`
- `uo_scheduling_name` for placeholder participants and game name

The output row contains the data needed by HTML, PDF, and API consumers: game id, time, reservation, place, field, timezone, real teams, placeholder names, score state, pool and series names, pool color, derived `scoresheet` count, and abbreviation / country / flag metadata.

Important current detail:

- the HTML row renderer does not use abbreviation or country / flag fields
- the API and some other outputs do use them

The timetable views also preload live-media links through `GetMediaUrlListForGames(..., "live")` and RSS enablement through `IsGameRSSEnabled()`, so `GameRow()` renders from both the timetable query and preloaded media / RSS state.

`GameRow()` renders optional date, time, field, series, and pool columns; either real team names or scheduling-name placeholders; score state; optional translated `gamename`; optional live-media icons; and an info / action cell.

Info-cell logic:

- `Game history` appears for unstarted real-team matchups when `GetAllPlayedGames()` finds prior meetings
- `Gameplay` appears for finished games when the derived `scoresheet` count is non-zero
- `Ongoing` appears for live games, optionally linking to gameplay when scoresheet rows exist

Current quirks:

- `games.php` still fetches a season comment with `CommentHTML(1, $id)`, but does not render it
- `next` exists in code but is not shown in the menu

## Scheduling Workflow

Reservations are created and edited through `admin/addreservation.php` with `AddReservation()` and `SetReservation()`. Relevant fields are season, location, field name, reservation group, start time, end time, and optional `timeslots`. Field input supports comma-separated fields and numeric ranges, so adding multiple fields creates multiple `uo_reservation` rows.

`admin/poolgames.php` is the admin hub for generating games, previewing pairings, manually adding games, listing scheduled games by reservation, listing unscheduled games, listing moved games, and linking into drag-and-drop scheduling and manual editing. The main helpers here are `GenerateGames()`, `PoolAddGame()`, `PoolGames()`, `PoolGamesNotScheduled()`, and `PoolMovedGames()`.

`GenerateGames()` in `lib/pool.functions.php` supports round robin, playoff, Swiss draw, and crossmatch generation. It inserts rows into `uo_game` and visible linkage rows into `uo_game_pool` with `timetable=1`. When a pool does not yet contain real teams, generation can use placeholder participants from `uo_moveteams` and `uo_scheduling_name`.

`uo_game_pool` has two relevant meanings in scheduling:

- `timetable=1`: normal visible schedule membership
- `timetable=0`: moved-game linkage into another pool

`admin/schedule.php` builds a drag-and-drop board from unscheduled games loaded through `UnscheduledPoolGameInfo()`, `UnscheduledSeriesGameInfo()`, or `UnscheduledSeasonGameInfo()`, and reservation columns loaded through `ReservationInfoArray()`.

Game duration precedence is:

- `uo_game.timeslot` overrides `uo_pool.timeslot`

That duration drives row height, offset accumulation, and overflow / conflict checks.

`admin/saveschedule.php` is the save path. Reservation columns are serialized as minute offsets from reservation start, `ClearReservation()` first unschedules all games currently attached to that reservation, `ScheduleGame()` reapplies games with new start time and reservation id, and `UnScheduleGame()` clears rows from the unscheduled column.

Validation after save checks:

- reservation end-time overflow
- intra-pool conflicts
- inter-pool conflicts
- move-time constraints from `uo_movingtime`

`admin/editgame.php` is the direct edit path for one game row. It can change teams, placeholders, reservation, time, pool, validity, responsible team, translated game name, and live-stream fields.

## Settings and Flags

Direct schedule-affecting settings:

- `uo_setting.CurrentSeason`: default public schedule scope
- `uo_season.timezone`: rendered at the bottom of schedule views by `PrintTimeZone()`
- `uo_setting.GameRSSEnabled`: enables the per-row RSS icon in `GameRow()`
- `uo_pool.timeslot` and `uo_game.timeslot`: affect scheduling duration, drag height, and overflow / conflict checks
- `uo_pool.type`, `uo_pool.ordering`, `uo_pool.color`: affect generation strategy, ordering, and PDF / one-page visual output
- `uo_movingtime`: affects post-save conflict checking

Important contextual flags that do not currently change the public `games.php` row HTML directly:

- `uo_season.istournament`
- `uo_season.isinternational`
- `uo_season.isnationalteams`

These matter in admin and enrollment flows, and international-related metadata is already carried in timetable rows for API and other outputs.

Flags that matter elsewhere, but not for the core HTML schedule row layout:

- `uo_season.hide_time_on_scoresheet`
- `uo_season.event_readonly`
- `uo_season.api_public`
- `uo_game.show_spirit`

## Related Outputs

The schedule row and footer link into:

- `reservationinfo`
- `poolstatus`
- `gameplay.php`
- `gamecard.php`
- iCalendar export
- printable HTML
- PDF list and one-page schedule output

The PDF layer in `cust/default/pdfprinter.php` uses the same timetable rowset, but renders it differently from `GameRow()`. The API schedule normalizer in `api/v1/router.php` also uses more of the row than the HTML renderer does, especially abbreviations and country / flag data.

## Database Model

The most important tables for schedule behavior are:

- `uo_game`: the actual game row, including teams or placeholders, reservation, time, pool, score state, duration override, and related flags
- `uo_game_pool`: visible and moved pool membership
- `uo_reservation`: reservation group, field, season, and reservation time window
- `uo_location`: venue identity used by reservations
- `uo_pool`: pool type, ordering, default timeslot, color, and series linkage
- `uo_series`: division identity and season linkage
- `uo_season`: event metadata such as timezone and contextual flags
- `uo_scheduling_name`: placeholder participant names and translated game names
- `uo_moveteams`: move rules and placeholder participants for future games
- `uo_team`: real team identity
- `uo_country`: country and flag metadata that timetable queries can expose
- `uo_goal`: used only to derive scoresheet presence for schedule rows
- `uo_urls`: live-media links
- `uo_gameevent`: some media lookups and later gameplay views
- `uo_setting`: server-level schedule-affecting settings such as `CurrentSeason` and `GameRSSEnabled`
- `uo_movingtime`: field-to-field move-time constraints used in validation

## In Practice

The shortest way to read the schedule implementation is:

1. `uo_game` is the schedule row.
2. `uo_reservation` is the field / time container.
3. `uo_game_pool` decides which pool contexts expose the game.
4. `games.php` renders the public view from `TimetableGames()`.
5. `admin/schedule.php` and `admin/saveschedule.php` rewrite reservation assignment and validate the result.
