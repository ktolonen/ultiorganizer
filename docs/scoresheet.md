# Scoresheet

This document describes the scoresheet concept as it is implemented today in Ultiorganizer.

In this codebase, a "scoresheet" is not a single object or table. It is a combination of:

- game-level result and status stored on the game row,
- per-game player participation stored separately from the team roster,
- per-point goal rows and related game events,
- supporting metadata such as official, halftime, timeouts, spirit stoppages, and game note,
- public or operator-facing views that replay the saved game data.

This page focuses on current behavior. It does not propose workflow changes.

## What the scoresheet means in Ultiorganizer

The scoresheet concept spans several layers:

- `uo_game` stores the aggregate game state: teams, scheduled context, current/final score, halftime, official, and status flags.
- `uo_played` stores the per-game player list. This is the set of players who were marked as having played in that specific game, with jersey numbers used for that game.
- `uo_goal` stores the detailed point-by-point scoring sequence.
- `uo_timeout`, `uo_spirit_timeout`, `uo_gameevent`, and `uo_comment` store related game annotations that are shown alongside the scoresheet.

The practical result is that "feeding in a scoresheet" means combining several editing flows:

- entering a result,
- choosing the players who played,
- entering the detailed sequence of points,
- adding related metadata that makes the later visualization useful, including spirit stoppages when the event uses spirit scoring.

## Input Paths

### Result-only entry

Main page:

- `user/addresult.php`

This flow is the lightest-weight way to record a game outcome. The operator enters home and away scores and chooses whether to:

- update the game as ongoing,
- save the game as final,
- clear the result.

Current persistence behavior:

- `GameUpdateResult()` updates `uo_game.homescore`, `uo_game.visitorscore`, `uo_game.isongoing=1`, and `uo_game.hasstarted=1`.
- `GameSetResult()` updates `uo_game.homescore`, `uo_game.visitorscore`, `uo_game.isongoing=0`, and `uo_game.hasstarted=2`.
- `GameClearResult()` clears the result on `uo_game` and resets the started flags.

Important limitation:

- this flow does not create a detailed scoresheet in `uo_goal`,
- it does not build the per-game player list in `uo_played`,
- it only updates aggregate game state on `uo_game`.

### Player list entry

Main pages:

- `user/addplayerlists.php`
- `mobile/addplayerlists.php`
- `scorekeeper/addplayerlists.php`

This flow builds the per-game roster. It starts from the team roster in `uo_player` and stores the selected players for the specific game in `uo_played`.

What the operator enters:

- which players played,
- jersey numbers to use for the game.
- in the desktop flow, team captains and spirit captains for the played roster.

Current persistence behavior:

- `GameAddPlayer()` inserts rows into `uo_played`,
- `GameRemovePlayer()` removes unchecked rows from `uo_played`,
- `GameSetPlayerNumber()` updates the jersey number stored on the `uo_played` row,
- `GameSetCaptains()` updates the `captain` flag on `uo_played`,
- `GameSetSpiritCaptains()` updates the `spirit_captain` flag on `uo_played`.

Important distinction:

- `uo_player` is the team roster source,
- `uo_played` is the game-specific roster used by scoresheet entry and later visualization.

The detailed scoresheet does not resolve assist/scorer numbers directly from the team roster. It resolves them through `uo_played`, so missing player-list data weakens the detailed scoresheet flow.

Desktop behavior in `user/addplayerlists.php` edits both teams on one page.
It also stores captain-role selections for the played roster.

Mobile and scorekeeper behavior differ:

- `mobile/addplayerlists.php` edits one team at a time and usually sends the user from home team to away team and then to score entry,
- `scorekeeper/addplayerlists.php` also edits one team at a time and then returns to the scorekeeper scoresheet flow.

### Detailed desktop scoresheet

Main page:

- `user/addscoresheet.php`

This is the full bulk editor for the detailed game sheet.

What the operator enters:

- scoring team for each point,
- assist number,
- scorer number,
- point time unless the event hides times,
- official name,
- starting offence,
- timeouts,
- spirit stoppages for spirit-enabled seasons,
- halftime end time,
- optional game note,
- whether the game is still ongoing.

Current persistence behavior on save:

- `GameSetScoreSheetKeeper()` updates `uo_game.official`,
- `GameSetHalftime()` updates `uo_game.halftime`,
- `GameSetStartingTeam()` stores the first offence as a `uo_gameevent` row,
- `SetGameComment(COMMENT_TYPE_GAME, ...)` stores the game note in `uo_comment`,
- `GameRemoveAllTimeouts()` deletes existing `uo_timeout` rows for the game and `GameAddTimeout()` re-inserts the submitted timeout rows,
- when `spiritmode > 0` and `hide_time_on_scoresheet` is false, `GameRemoveAllSpiritTimeouts()` deletes existing `uo_spirit_timeout` rows for the game and `GameAddSpiritTimeout()` re-inserts the submitted spirit-timeout rows,
- `GameRemoveAllScores()` deletes existing `uo_goal` rows for the game and the page then rebuilds the entire point sequence with repeated `GameAddScore()` calls.

This is a full-sheet rewrite model, not an incremental point-entry model.

Result handling is tied to the saved sheet:

- if the operator marks the game as ongoing, the page updates the aggregate result through `GameUpdateResult()`,
- if the game was previously ongoing and is now saved without the ongoing flag, the page finalizes the result through `GameSetResult()`.

Important distinction:

- this page edits the detailed sheet as one bulk form,
- the saved detailed point sequence lives in `uo_goal`,
- the aggregate result still lives separately in `uo_game`.

### Mobile entry

Main pages:

- `mobile/addplayerlists.php`
- `mobile/addscoresheet.php`

The mobile flow is designed for incremental entry rather than bulk editing.

This legacy `mobile/` flow is deprecated and kept only for compatibility. New operator workflows should use `scorekeeper/` for score entry and `spiritkeeper/` for spirit workflows.

Player-list behavior is separate and usually completed before point entry. The score-entry page then accepts one point at a time.

What the operator enters for each point:

- scoring team,
- assist number,
- scorer number,
- point time in minutes and seconds unless times are hidden.

Current persistence behavior:

- each saved point is inserted immediately with `GameAddScoreEntry()` into `uo_goal`,
- the current aggregate result is advanced with `GameUpdateResult()` when a new point increases the stored total score,
- a later "Save as result" action finalizes the game through `GameSetResult()`.

Related mobile actions are broken into separate pages:

- `mobile/addofficial.php` for the official name,
- `mobile/addcomment.php` for the game note,
- `mobile/addhalftime.php` for halftime,
- `mobile/addtimeouts.php` for ordinary timeouts,
- `mobile/addspirittimeouts.php` for spirit stoppages when `spiritmode > 0` and time entry is visible,
- `mobile/addfirstoffence.php` for starting offence.

This means the mobile scoresheet is conceptually the same scoresheet, but the data is entered across several smaller pages instead of one bulk form.
Spirit score submission itself is no longer part of the mobile flow and now lives in `spiritkeeper/`.

### Scorekeeper entry

Main pages:

- `scorekeeper/addplayerlists.php`
- `scorekeeper/addscoresheet.php`

The scorekeeper UI follows the same broad model as mobile entry:

- player lists are entered separately,
- goals are entered one at a time,
- the aggregate result is updated progressively,
- a later save finalizes the result.

Current persistence behavior:

- `scorekeeper/addplayerlists.php` manages `uo_played`,
- `scorekeeper/addscoresheet.php` inserts each point through `GameAddScoreEntry()` into `uo_goal`,
- the page advances the aggregate score through `GameUpdateResult()`,
- the final save writes the final result through `GameSetResult()`.

Related metadata is handled in separate scorekeeper pages such as:

- `scorekeeper/addofficial.php`,
- `scorekeeper/addcomment.php`,
- `scorekeeper/addhalftime.php`,
- `scorekeeper/addtimeouts.php` for ordinary timeouts,
- `scorekeeper/addspirittimeouts.php` for spirit stoppages when `spiritmode > 0` and time entry is visible,
- `scorekeeper/addfirstoffence.php`.

Compared with the desktop editor, scorekeeper entry is incremental and segmented rather than bulk.

Spirit score submission is intentionally not part of the scorekeeper surface. Spirit score workflows now live in `spiritkeeper/` or the main logged-in user pages.

## Database Model

The detailed scoresheet uses several tables together.

### Core tables

#### `uo_game`

This is the main game row and the aggregate state holder.

Relevant columns:

- `game_id`
- `hometeam`
- `visitorteam`
- `homescore`
- `visitorscore`
- `reservation`
- `time`
- `pool`
- `halftime`
- `official`
- `isongoing`
- `hasstarted`
- `show_spirit`

Role in the scoresheet:

- stores the current or final result,
- stores scheduling and field context,
- stores halftime and official metadata,
- stores game lifecycle flags that control whether the game is not started, ongoing, or finished.

#### `uo_goal`

This table stores the detailed point-by-point scoring sequence.

Relevant columns:

- `game`
- `num`
- `assist`
- `scorer`
- `time`
- `homescore`
- `visitorscore`
- `ishomegoal`
- `iscallahan`
- `timestamp`

Role in the scoresheet:

- one row per saved point,
- stores the running score after each point,
- stores assist/scorer player references when known,
- stores the point time when time entry is enabled.

`num` is the point order within the game.

#### `uo_played`

This is the per-game player list.

Relevant columns:

- `player`
- `game`
- `num`
- `captain`
- `spirit_captain`

Role in the scoresheet:

- records which players were marked as having played in the game,
- stores the jersey number used for that game,
- stores team-captain and spirit-captain selections for gameplay displays.

This is the bridge between the team roster and game-specific detailed entry.

#### `uo_player`

This is the team roster source.

Relevant columns for the scoresheet concept:

- `player_id`
- `firstname`
- `lastname`
- `team`
- `num`
- `profile_id`

Role in the scoresheet:

- provides the selectable player universe for a team,
- provides names used in scoresheet replay and scoreboards,
- is the target of `uo_goal.assist` and `uo_goal.scorer`.

Current detailed entry resolves player numbers through `uo_played`, not directly through `uo_player`.

#### `uo_timeout`

This stores timeout events for a game.

Relevant columns:

- `timeout_id`
- `game`
- `num`
- `time`
- `ishome`

Role in the scoresheet:

- stores home/away timeout rows shown in the gameplay replay.

#### `uo_spirit_timeout`

This stores spirit-timeout events for spirit-enabled games.

Relevant columns:

- `spirit_timeout_id`
- `game`
- `num`
- `time`
- `ishome`

Role in the scoresheet:

- stores home/away spirit-timeout rows separately from ordinary timeouts,
- is written by the score-sheet timeout entry surfaces,
- is replayed through `GameEvents()` with event type `spirit_timeout`.

#### `uo_gameevent`

This stores additional game events tied to the scoresheet replay.

Relevant columns:

- `game`
- `num`
- `time`
- `type`
- `ishome`
- `info`

Role in the scoresheet:

- stores starting offence and other event rows used in gameplay displays,
- is combined with timeout information in `GameEvents()`.

#### `uo_comment`

This stores free-text notes for several entity types. For scoresheets, the relevant case is the game note.

Relevant columns:

- `type`
- `id`
- `comment`

Role in the scoresheet:

- game notes are stored as `COMMENT_TYPE_GAME = 4`,
- `id` is the game id in that case.

### Supporting lookup and context tables

#### `uo_team`

Provides team names, abbreviations, series, and pool context used by scoresheet entry and visualization.

#### `uo_pool`

Provides rules and game-structure context such as halftime settings, score cap, time cap, timeout configuration, and series linkage.

#### `uo_series`

Provides division-level context for the game.

#### `uo_season`

Provides event-level context and behavior modifiers, including `hide_time_on_scoresheet`.

#### `uo_reservation`

Provides the field reservation referenced by `uo_game.reservation`.

#### `uo_location`

Provides the human-readable venue for a reservation.

### Key relationships

- `uo_goal.game -> uo_game.game_id`
- `uo_goal.assist -> uo_player.player_id`
- `uo_goal.scorer -> uo_player.player_id`
- `uo_played.game -> uo_game.game_id`
- `uo_played.player -> uo_player.player_id`
- `uo_timeout.game -> uo_game.game_id`
- `uo_spirit_timeout.game -> uo_game.game_id`
- `uo_gameevent.game -> uo_game.game_id`

These relationships are declared in the schema and are central to how gameplay views reconstruct the saved scoresheet.

## Visualization And Derived Views

Saved scoresheet data is replayed through several views.

Main pages:

- `gameplay.php`
- `mobile/gameplay.php`
- `scorekeeper/gameplay.php`

### Desktop gameplay view

`gameplay.php` is the richest replay view.

It combines:

- the aggregate result from `uo_game` via `GameResult()`,
- team scoreboards derived from `uo_goal`, `uo_played`, and `uo_player` via `GameTeamScoreBorad()`,
- the chronological goal list from `uo_goal` via `GameGoals()`,
- timeouts and other game events from `uo_timeout` and `uo_gameevent` via `GameEvents()`,
- spirit stoppages from `uo_spirit_timeout` via `GameEvents()`,
- optional game note from `uo_comment` via `GameCommentHtml(COMMENT_TYPE_GAME)`.

It also renders:

- a point-by-point timeline,
- halftime markers using `uo_game.halftime`,
- assist and scorer names, optionally prefixed with the game-specific jersey numbers from `uo_played`,
- captain markers based on `uo_played.captain` and `uo_played.spirit_captain`.

### Mobile gameplay view

`mobile/gameplay.php` is a more compact replay view.

It still uses the same underlying saved data:

- result from `uo_game`,
- goals from `uo_goal`,
- game events from `uo_timeout` and `uo_gameevent`,
- spirit stoppage events from `uo_spirit_timeout`,
- official name from `uo_game.official`.

Compared with the desktop page, the mobile page shows a simpler sequential replay and links out to team scoreboards.

### Scorekeeper gameplay view

`scorekeeper/gameplay.php` is similar in spirit to the mobile view:

- it replays goals from `uo_goal`,
- shows event rows from `uo_timeout`, `uo_spirit_timeout`, and `uo_gameevent`,
- shows the official from `uo_game`,
- links to team scoreboards.

It is still a replay of the same saved scoresheet data, not a separate data model.

### Time visibility flag

Time visibility is controlled by `uo_season.hide_time_on_scoresheet`.

Current behavior:

- `user/addscoresheet.php`, `mobile/addscoresheet.php`, and `scorekeeper/addscoresheet.php` all check this flag,
- `gameplay.php`, `mobile/gameplay.php`, and `scorekeeper/gameplay.php` also check this flag.

When it is enabled:

- operators do not enter explicit point times,
- operators do not enter regular timeouts or spirit stoppages from the score-sheet timeout pages,
- detailed point rows still exist in `uo_goal`, but the entry flows synthesize increasing values instead of taking visible game-clock input,
- gameplay views suppress time display even though the sequence of points is still preserved.

## PDF Printing Concept

Main page:

- `user/pdfscoresheet.php`

PDF layout file:

- `cust/<customization>/pdfscoresheet.php`, with fallback to `cust/default/pdfscoresheet.php`

The PDF scoresheet flow is related to the scoresheet concept, but it is not the same as the saved detailed in-app scoresheet.

Current concept:

- PDFs are printable field-use artifacts,
- they are generated from scheduled game metadata,
- they can include team player lists taken from the roster data,
- printable scoresheet layouts now also include a dedicated spirit-timeout area for field-side recording,
- they are intended for operations and on-field use before or during games.

Current data sources in the PDF flow are primarily:

- scheduled game and field context,
- team and season naming context,
- team player lists.

Important clarification:

- this flow is not documented here as a rendering implementation,
- current PDF generation is not a walkthrough of how saved `uo_goal` rows are replayed,
- conceptually it belongs near scoresheets, but technically it is a separate print-oriented workflow.

## Permissions And Behavior Modifiers

Detailed score entry requires game-event edit rights.

Current gate:

- `hasEditGameEventsRight($gameId)`

That helper is used directly by the main scoresheet and result-entry pages, and it is also the permission check used by the game mutation helpers in `lib/game.functions.php`.

Event read-only mode also matters:

- `hasEditGameEventsRight()` returns false when the event is read-only and the user cannot bypass it,
- as a result, scoresheet mutation, result mutation, player-list mutation, and related game-note mutation are blocked.

For a broader description of the permission model, see [permissions.md](permissions.md).

Other behavior modifiers:

- `uo_season.hide_time_on_scoresheet` changes both entry and replay behavior,
- spirit entry and defense entry are adjacent game workflows, but they are separate topics,
- this document only references those areas where they touch the scoresheet navigation or shared game data.
