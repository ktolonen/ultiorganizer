# Schedule

This document describes schedule behavior as it is implemented today in Ultiorganizer.

In this codebase, a "schedule" is not a single object or table. It is a combination of:

- generated game rows,
- pool membership that decides which games belong to visible schedule views,
- reservations that define date, field, and time windows,
- scheduling-name placeholders for not-yet-resolved participants,
- public and admin pages that render, assign, or validate time and field placement.

This page focuses on current behavior. It does not propose workflow changes.

## What the schedule means in Ultiorganizer

The schedule concept spans several layers:

- `uo_game` stores the actual game row, including teams or placeholders, pool, reservation, start time, score state, and related flags.
- `uo_game_pool` stores which pools a game belongs to, including whether that pool membership is part of the visible timetable.
- `uo_reservation` and `uo_location` store the field reservation grid that games are placed onto.
- `uo_scheduling_name` and `uo_moveteams` support schedule rows whose participants are not yet real teams.
- `games.php`, `lib/timetable.functions.php`, `admin/poolgames.php`, `admin/schedule.php`, and `admin/saveschedule.php` provide the main read and write paths.

The practical result is that "building the schedule" means combining several workflows:

- creating reservations,
- generating or manually adding game rows,
- assigning those rows to reservations and start times,
- rendering the result in public and admin views,
- validating that the placement does not create obvious conflicts.

## Main schedule layers

The schedule implementation is split into a few distinct layers:

- game generation in `lib/pool.functions.php` and `admin/poolgames.php`,
- reservation management in `lib/reservation.functions.php` and `admin/addreservation.php`,
- drag-and-drop scheduling in `admin/schedule.php` and `admin/saveschedule.php`,
- public schedule rendering in `games.php` and `lib/timetable.functions.php`,
- linked read-only detail views such as `gameplay.php`, `gamecard.php`, `reservationinfo`, and PDF / iCalendar outputs.

These layers share the same underlying game and reservation rows, but they do not all use the same queries or the same subset of columns.

## Public Schedule View

### Entry point

Main page:

- `games.php`, routed as `index.php?view=games`

`games.php` is mainly a controller that:

- resolves the schedule scope from query parameters,
- maps the visible filter into a timetable query mode,
- loads games through `TimetableGames()`,
- loads reservation-group tabs through `TimetableGrouping()`,
- renders the result through one of the timetable view helpers,
- switches to printable HTML or PDF output when requested.

### Context selectors

The page supports several mutually exclusive schedule scopes:

- `season=<seasonId>`
- `series=<seriesId>`
- `pool=<poolId>`
- `pools=<poolId,poolId,...>`
- `team=<teamId>`

Current behavior:

- `season` is the default scope when no other scope parameter is given,
- the default season comes from `CurrentSeason()`,
- `pools=` becomes the internal `poolgroup` query mode,
- invalid or empty `pools=` input falls back to the current season.

### Display selectors

The main display controls are:

- `filter`
- `group`
- `print`
- `singleview`

`group` filters by reservation group after the main timetable query has been scoped to a season, series, pool, pool group, or team.

`print=1` switches the page to a printable HTML wrapper.

`singleview=1` suppresses the normal page menu and group selector wrapper.

### Filter mapping

`games.php` converts the visible `filter` into a timetable `timefilter`, result ordering, and output format.

| `filter` value | `timefilter` | `order` | format | Notes |
| --- | --- | --- | --- | --- |
| `tournaments` | `all` | `tournaments` | html | Default day-oriented public view |
| `series` | `all` | `series` | html | Groups by division and pool |
| `places` | `all` | `places` | html | Groups by date and field |
| `timeslot` | `all` | `time` | html | Groups by exact game time |
| `today` | `today` | `series` | html | Uses current database date |
| `tomorrow` | `tomorrow` | `series` | html | Uses current database date |
| `yesterday` | `yesterday` | `series` | html | Uses current database date |
| `next` | `all` | `tournaments` | html | Implemented in code but not exposed in the visible menu |
| `season` | `all` | `places` | pdf | List PDF output |
| `onepage` | `all` | `onepage` | pdf | Grid PDF output |

The footer also exposes:

- iCalendar export via `?view=ical`,
- PDF list and grid variants,
- printable HTML.

### Grouping model

Several related terms appear in the schedule code and need to be kept separate:

- reservation group: `uo_reservation.reservationgroup`, a higher-level grouping label such as one tournament site or day block,
- location: `uo_location`, the human-readable venue,
- reservation: `uo_reservation`, one reserved field window with start and end time,
- field: the `uo_reservation.fieldname` value inside a reservation.

`TimetableGrouping()` returns the available reservation groups for the current schedule scope and time filter.

The group selector logic in `games.php`:

- shows one link per reservation group when more than one exists,
- adds an `All` option,
- keeps the current `filter` in the link,
- suppresses the separate schedule heading when a single explicit reservation group is selected.

### Renderer split

The actual HTML grouping logic lives in `lib/timetable.functions.php`:

- `TournamentView()` groups by reservation group, then date / location, then pool,
- `SeriesView()` groups by series, then pool,
- `PlaceView()` groups by reservation group, then date, then field,
- `TimeView()` groups by exact game start time.

All of these renderers consume the same timetable row shape. They differ only in how they break the result into headings and tables.

## How a Game Row Is Compiled

### Query assembly in `TimetableGames()`

`TimetableGames()` in `lib/timetable.functions.php` builds the public schedule rowset.

Its main data sources are:

- `uo_game` as the base table,
- a derived subquery over `uo_goal` to count saved scoring rows per game,
- `uo_pool`,
- `uo_series`,
- `uo_season`,
- `uo_reservation`,
- `uo_location`,
- home and visitor `uo_team`,
- home and visitor `uo_country`,
- `uo_scheduling_name` for the game name and placeholder participants.

The function applies four layers of constraints:

- schedule scope filtering such as season, series, pool, pool group, team, or single game,
- time filtering such as all, today, tomorrow, yesterday, coming, or past,
- optional reservation-group filtering,
- ordering selected by the visible schedule mode.

### Row fields produced

The timetable query produces a superset row that supports HTML, PDF, and API consumers.

Important fields include:

- game identity: `game_id`,
- timing: `time`, `starttime`, `endtime`, `timezone`,
- score state: `homescore`, `visitorscore`, `hasstarted`, `isongoing`,
- team identity: `hometeam`, `visitorteam`, `hometeamname`, `visitorteamname`,
- placeholder identity: `phometeamname`, `pvisitorteamname`,
- structure: `pool`, `poolname`, `series_id`, `seriesname`, `season`, `type`,
- reservation context: `reservation_id`, `reservationgroup`, `place_id`, `placename`, `fieldname`,
- visual / metadata fields: `color`, `gamename`,
- scoresheet inference: `scoresheet`, which is the count of `uo_goal` rows for the game,
- extra team metadata: abbreviations and country / flag fields for both teams.

Important implementation detail:

- the public HTML schedule row renderer does not currently use the abbreviation or country / flag fields,
- the fields are still part of the query contract and are used by API normalization and some non-`games.php` outputs.

### Preloaded row enrichments

Before iterating rows, the timetable renderers also preload media links:

- `CollectGameIdsFromResult()` extracts the current result-set game ids,
- `GetMediaUrlListForGames(..., "live")` loads live-media URLs for those games,
- `IsGameRSSEnabled()` controls whether an RSS column is added.

This means the row renderer is not reading only the timetable query output. It also receives preloaded live-media state and a server-level RSS flag.

### Renderer grouping

The same timetable row can appear under different headings depending on the active renderer.

`TournamentView()`:

- starts a new top-level block when `reservationgroup` changes,
- starts a new subheading when date or place changes,
- starts a new table when pool changes.

`SeriesView()`:

- starts a new top-level block when `series_id` changes,
- starts a new table when pool changes.

`PlaceView()`:

- starts a new top-level block when `reservationgroup` changes,
- starts a new date heading when the reservation date changes,
- starts a new table when place, field, or date changes.

`TimeView()`:

- starts a new table for each distinct `time`.

### `GameRow()` output contract

`GameRow()` is the shared HTML row formatter.

Depending on caller flags, it can render:

- date,
- time,
- field,
- series,
- pool,
- info/action cells.

Team cells:

- if real teams exist, it renders `hometeamname` and `visitorteamname`,
- otherwise it renders `phometeamname` and `pvisitorteamname` in scheduling-name styling.

Score cells:

- if `hasstarted=0`, the row shows `? - ?`,
- if `hasstarted>0` and `isongoing=1`, the current scores are shown in italics,
- if `hasstarted>0` and `isongoing=0`, the final scores are shown as normal text.

Optional cells:

- `gamename` is shown when present,
- pool output links to `?view=poolstatus&pool=<poolId>`,
- live-media icons are rendered when live-media URLs exist and the game is either ongoing or not yet started.

### Related-link logic

The info/action cell has three main behaviors.

Unstarted games:

- if both participants are real teams, `GameRow()` strips spaces from the team names and calls `GetAllPlayedGames()`,
- if there are prior played meetings in the same series type, the row shows a `Game history` link to `?view=gamecard&team1=...&team2=...`.

Finished games:

- if the derived `scoresheet` count is non-zero, the row shows a `Gameplay` link to `?view=gameplay&game=<gameId>`,
- otherwise the info cell stays empty.

Ongoing games:

- if `scoresheet` count is non-zero, the row shows `Ongoing` as a link to `gameplay`,
- otherwise it shows plain `Ongoing` text.

### Quirks in `games.php`

There are a few current quirks worth knowing:

- the internal `next` filter is implemented but not present in the visible tab menu,
- `games.php` fetches a season comment with `CommentHTML(1, $id)` in season mode, but the value is not rendered,
- `singleview=1` suppresses the normal menu and grouping controls instead of changing the row data itself.

## Scheduling Workflow

### Reservation creation and editing

Main page:

- `admin/addreservation.php`

Core persistence helpers:

- `AddReservation()`
- `SetReservation()`

Reservations define the schedule containers that games can later be placed into.

Relevant reservation inputs are:

- season,
- location,
- field name,
- reservation group,
- date,
- start time,
- end time,
- optional `timeslots`.

Important current behavior:

- `fieldname` input supports comma-separated fields and numeric ranges such as `1-4`,
- adding a reservation with multiple fields expands into multiple `uo_reservation` rows,
- `timeslots` exists in the schema and helper layer, but the current admin form comments it out instead of exposing it normally.

### Pool game management

Main page:

- `admin/poolgames.php`

This page is the main admin hub for pool-level schedule construction.

It can:

- generate pool games,
- preview generated pairings,
- manually add a game,
- list scheduled games under each reservation,
- list unscheduled games,
- list moved games,
- link to drag-and-drop scheduling,
- link to manual per-game editing.

The page uses:

- `PoolGames()` for scheduled games in a reservation,
- `PoolGamesNotScheduled()` for timetable games missing `time` or `reservation`,
- `PoolMovedGames()` for pool membership where `uo_game_pool.timetable=0`.

### Game generation

Game generation lives in `GenerateGames()` in `lib/pool.functions.php`.

Current generation modes depend on `uo_pool.type`:

- `1`: round robin,
- `2`: playoff,
- `3`: Swiss draw,
- `4`: crossmatch.

Generation behavior:

- inserts rows into `uo_game`,
- inserts visible pool linkage rows into `uo_game_pool` with `timetable=1`,
- can mark the home side as the responsible team,
- can generate games using real teams or scheduling-name placeholders.

Pseudoteams:

- when a pool has no real `uo_team_pool` members yet, generation can pull placeholder participants from `uo_moveteams` and `uo_scheduling_name`,
- generated rows then use `scheduling_name_home` and `scheduling_name_visitor` instead of real teams.

Manual additions:

- `PoolAddGame()` inserts a single game row and the corresponding visible `uo_game_pool` entry.

### Pool linkage and moved games

`uo_game_pool` is important because a schedule row can belong to more than one pool context.

Current meanings:

- `timetable=1` means normal visible schedule membership for that pool,
- `timetable=0` means the game is linked into a pool as a moved game rather than a normal timetable game.

Moved-game linkage is created when pool moves are confirmed:

- `PoolMakeMoves()` can insert extra `uo_game_pool` rows with `timetable=0`,
- `PoolUndoMove()` removes those moved links again.

### Drag-and-drop scheduling

Main page:

- `admin/schedule.php`

This page builds a drag-and-drop view from two sources:

- unscheduled games loaded through `UnscheduledPoolGameInfo()`, `UnscheduledSeriesGameInfo()`, or `UnscheduledSeasonGameInfo()`,
- reservation columns built from `ReservationInfoArray()`.

Each reservation column is a single reserved field window.

Within the column:

- existing gaps are shown as artificial pause rows,
- games are rendered at heights based on duration,
- dragging changes the order and therefore the offset from reservation start.

Duration precedence in the scheduling UI:

- if `uo_game.timeslot` is set, it is used,
- otherwise `uo_pool.timeslot` is used.

Zero-duration games are detected and warned about. They can be shown, but they are not treated as valid schedule items.

### Persistence in `admin/saveschedule.php`

When the admin saves the drag-and-drop schedule:

- each reservation column is parsed as minute offsets from reservation start,
- `ClearReservation()` first unschedules all games currently attached to that reservation,
- each game in that column is then reapplied with `ScheduleGame(gameId, epoch, reservationId)`,
- the unscheduled column clears games through `UnScheduleGame()`.

This is a full rewrite of reservation membership for the edited reservations, not an incremental per-row patch.

### Validation after save

`admin/saveschedule.php` also performs immediate validation.

Current checks:

- whether a scheduled game exceeds the reservation end time,
- intra-pool scheduling conflicts via `TimetableIntraPoolConflicts()`,
- inter-pool scheduling conflicts via `TimetableInterPoolConflicts()`,
- travel-time / field-change allowances via `TimetableMoveTimes()` and `TimetableMoveTime()`.

Move-time validation depends on `uo_movingtime`.

### Manual edits

Main page:

- `admin/editgame.php`

This is the direct edit path for a single game row.

It can change:

- home and away teams or placeholder participants,
- reservation,
- start time,
- pool,
- validity,
- responsible team,
- responsible user assignment,
- translated game name,
- live-stream fields.

Underlying persistence:

- `SetGame()` updates a whitelisted subset of `uo_game` columns,
- it does not itself resolve schedule conflicts,
- it is a direct row edit rather than a drag-and-drop scheduling rewrite.

## Settings and Event Flags

Several schedule behaviors depend on server settings, event settings, or pool-level configuration. They do not all affect the same layers.

### Direct schedule entry and render effects

#### `uo_setting.CurrentSeason`

This affects the default public scope when no explicit `season`, `series`, `pool`, `pools`, or `team` selector is present.

Current behavior:

- `games.php` falls back to `CurrentSeason()`,
- this makes the server-level current season the default public schedule view.

#### `uo_season.timezone`

This affects timetable display directly.

Current behavior:

- `TimetableGames()` joins `uo_season` and carries `timezone` in each row,
- `PrintTimeZone()` renders the event timezone and the current local time at the end of schedule views.

#### `uo_setting.GameRSSEnabled`

This affects row rendering directly.

Current behavior:

- `IsGameRSSEnabled()` controls whether `GameRow()` appends an RSS feed icon column.

#### `uo_pool.timeslot` and `uo_game.timeslot`

These affect scheduling behavior directly.

Current behavior:

- the admin scheduling UI uses game duration to compute drag height and accumulated offsets,
- `uo_game.timeslot` overrides the pool default when present,
- the same duration is used in end-time validation and conflict checks.

### Direct schedule-generation and validation effects

#### `uo_pool.type`

This controls how `GenerateGames()` builds matchups:

- round robin,
- playoff,
- Swiss draw,
- crossmatch.

#### `uo_pool.ordering`

This affects sort order in many schedule queries and renderers.

#### `uo_pool.color`

This affects non-HTML outputs directly:

- pool color is carried in timetable rows,
- PDF and one-page schedule output use it for colorized rendering.

#### `uo_movingtime`

This affects validation only.

Current behavior:

- `admin/saveschedule.php` uses move-time rules when deciding whether back-to-back placements create conflicts across fields or locations.

### Indirect or contextual event flags

#### `uo_season.istournament`

This is core season metadata but does not currently change `games.php` row HTML directly.

It matters around scheduling because:

- it is part of season identity and admin season configuration,
- it influences how the event is described in admin and API outputs.

#### `uo_season.isinternational`

This does not currently change `games.php` row HTML directly.

It matters around scheduling because:

- timetable queries already carry team country and flag metadata,
- API schedule output includes country data,
- some related non-`games.php` outputs use flags for international events.

#### `uo_season.isnationalteams`

This also does not currently change `games.php` row HTML directly.

It matters around scheduling because:

- it changes how the season is modeled in team and enrollment flows,
- it changes surrounding event semantics such as whether the event is for clubs or national teams,
- schedule consumers can still access country and team metadata from the timetable query even though `games.php` itself does not render special national-team formatting.

### Important non-effects for `games.php` row HTML

The following settings matter elsewhere, but not to the core public row formatting in `games.php`:

- `uo_season.hide_time_on_scoresheet`
- `uo_season.event_readonly`
- `uo_season.api_public`
- `uo_game.show_spirit`

Current interpretation:

- they affect other workflows, permissions, or visibility models,
- they do not change the public `games.php` schedule row layout today,
- `api_public` only matters for API exposure, not for whether the row appears in `games.php`.

## Related Read-Only Outputs

The public schedule row and schedule footer link into several related read-only outputs:

- `reservationinfo` for reservation details,
- `poolstatus` for pool standings and structure,
- `gameplay.php` for scoresheet replay,
- `gamecard.php` for head-to-head history,
- `?view=ical` for iCalendar export,
- printable HTML from `games.php?print=1`,
- PDF list and one-page schedule output through the active PDF printer customization.

### PDF output

`games.php` delegates PDF rendering to the customization-aware printer:

- `cust/<CUSTOMIZATIONS>/pdfprinter.php` when present,
- otherwise `cust/default/pdfprinter.php`.

Current PDF behavior:

- `PrintSchedule()` renders a grouped list view,
- `PrintOnePageSchedule()` renders a reservation-group grid,
- both consume the same core timetable rowset from `TimetableGames()`,
- the one-page grid additionally relies on `TimetableFields()` and `TimetableTimeslots()`.

### API output

The API schedule normalizer uses more of the timetable row than the HTML renderer does.

Current behavior in `api/v1/router.php`:

- team abbreviations are exposed,
- country id, name, and flag are exposed,
- placeholder names are exposed,
- reservation, division, and pool metadata are exposed.

This is one reason the timetable query includes more metadata than `GameRow()` visibly renders.

## Database Model

Schedule behavior depends on several tables together.

### Core schedule tables

#### `uo_game`

This is the main schedule row.

Relevant columns:

- `game_id`
- `hometeam`
- `visitorteam`
- `scheduling_name_home`
- `scheduling_name_visitor`
- `reservation`
- `time`
- `pool`
- `timeslot`
- `homescore`
- `visitorscore`
- `hasstarted`
- `isongoing`
- `respteam`
- `name`
- `valid`
- `islive`
- `liveurl`
- `show_spirit`

Role in scheduling:

- stores the actual scheduled matchup,
- stores whether participants are real teams or placeholders,
- stores the reservation and start time assignment,
- stores per-game duration override,
- stores score state and row-level metadata used by later views.

#### `uo_game_pool`

This table links games to pool contexts.

Relevant columns:

- `game`
- `pool`
- `timetable`

Role in scheduling:

- marks normal visible schedule membership,
- marks moved-game linkage when `timetable=0`,
- is used by many pool-specific schedule and stats queries.

### Competition structure tables

#### `uo_pool`

Relevant columns:

- `pool_id`
- `name`
- `ordering`
- `visible`
- `series`
- `type`
- `timeslot`
- `color`
- `played`
- `follower`
- `playoff_template`

Role in scheduling:

- defines the schedule grouping below series,
- defines pairing-generation strategy,
- provides default game duration,
- contributes ordering and color metadata to schedule rows.

#### `uo_series`

Relevant columns:

- `series_id`
- `name`
- `ordering`
- `season`
- `type`
- `color`

Role in scheduling:

- groups pools into divisions,
- supplies season linkage and series ordering,
- contributes series name and type to schedule queries.

#### `uo_season`

Relevant columns:

- `season_id`
- `name`
- `starttime`
- `endtime`
- `istournament`
- `isinternational`
- `isnationalteams`
- `timezone`
- `event_readonly`
- `api_public`
- `hide_time_on_scoresheet`

Role in scheduling:

- defines the event-level schedule scope,
- provides timezone used in public schedule output,
- stores contextual flags that affect related schedule consumers.

### Reservation and venue tables

#### `uo_reservation`

Relevant columns:

- `id`
- `location`
- `fieldname`
- `reservationgroup`
- `starttime`
- `endtime`
- `season`
- `timeslots`
- `date`

Role in scheduling:

- defines the reserved field window that games are placed into,
- provides reservation-group grouping,
- provides the base start time used by drag-and-drop offset serialization.

#### `uo_location`

Relevant columns:

- `id`
- `name`
- `address`
- `lat`
- `lng`
- `fields`
- `indoor`

Role in scheduling:

- provides the human-readable venue name and related venue metadata for reservations.

#### `uo_location_info`

Relevant columns:

- `location_id`
- `locale`
- `info`

Role in scheduling:

- stores localized location info used by reservation-detail views,
- is part of the broader reservation model even though `games.php` does not render it directly.

### Team identity and placeholder tables

#### `uo_team`

Relevant columns:

- `team_id`
- `name`
- `series`
- `pool`
- `country`
- `abbreviation`
- `valid`

Role in scheduling:

- provides real team identity for schedule rows,
- contributes abbreviation and country linkage.

#### `uo_country`

Relevant columns:

- `country_id`
- `name`
- `flagfile`
- `abbreviation`

Role in scheduling:

- provides country and flag metadata that timetable queries include for API and related outputs.

#### `uo_scheduling_name`

Relevant columns:

- `scheduling_id`
- `name`

Role in scheduling:

- provides placeholder participant names for unresolved teams,
- provides translated game names,
- supports playoff and move-based schedule construction before real teams are known.

#### `uo_moveteams`

Relevant columns:

- `frompool`
- `topool`
- `fromplacing`
- `torank`
- `ismoved`
- `scheduling_id`

Role in scheduling:

- defines how teams move from one pool into another,
- provides placeholder participants for future games,
- drives replacement of scheduling names with real teams when moves are confirmed.

### Read-only row-enrichment tables

#### `uo_goal`

Relevant columns:

- `game`
- `num`

Role in scheduling:

- does not build the schedule itself,
- is used by timetable queries to derive `scoresheet` count,
- that derived count controls whether schedule rows link to `gameplay`.

#### `uo_urls`

Relevant columns:

- `url_id`
- `owner`
- `owner_id`
- `type`
- `name`
- `url`
- `ismedialink`
- `publisher_id`

Role in scheduling:

- stores live-media links and other media URLs for games,
- supports the live-media icons rendered in schedule rows.

#### `uo_gameevent`

Relevant columns:

- `game`
- `num`
- `time`
- `type`
- `ishome`
- `info`

Role in scheduling:

- is not part of core placement,
- is joined when media URLs are fetched for games,
- also supports later gameplay replay views.

#### `uo_setting`

Relevant columns:

- `setting_id`
- `name`
- `value`

Role in scheduling:

- stores server-level settings such as `CurrentSeason` and `GameRSSEnabled`,
- affects the default public schedule scope and optional RSS row icon.

### Scheduling validation support

#### `uo_movingtime`

Relevant columns:

- `season`
- `fromlocation`
- `fromfield`
- `tolocation`
- `tofield`
- `time`

Role in scheduling:

- stores minimum travel / movement time assumptions between scheduled fields,
- is used in post-save conflict validation.

## Summary of current behavior

The schedule implementation today is built around a few stable ideas:

- generated or manually added `uo_game` rows are the core schedule objects,
- `uo_reservation` rows provide the field/time containers,
- `uo_game_pool` decides which pool contexts treat a game as part of the visible timetable,
- public schedule HTML is a thin rendering layer over `TimetableGames()` and `GameRow()`,
- admin scheduling is a separate placement workflow that rewrites reservation assignments and then validates the result,
- event flags such as `isinternational` and `isnationalteams` are important context, but they do not currently change the public `games.php` row layout directly.
