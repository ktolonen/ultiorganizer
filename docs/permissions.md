# Permissions

This document describes the permission model that is implemented in the codebase today.

## Storage and session shape

- Permissions are stored in `uo_userproperties`.
- `SetUserSessionData()` in [lib/user.functions.php](../lib/user.functions.php) loads those rows into `$_SESSION['userproperties']`.
- Rows are split on `:` and stored as nested arrays keyed by property name, then by role/value.
- Examples:
  `userrole = superadmin` becomes `$_SESSION['userproperties']['userrole']['superadmin']`
  `userrole = seasonadmin:EVENT24` becomes `$_SESSION['userproperties']['userrole']['seasonadmin']['EVENT24']`
  `editseason = EVENT24` becomes `$_SESSION['userproperties']['editseason']['EVENT24']`

## Property types used for access control

- `userrole`
  The actual permission-bearing property.
- `editseason`
  Controls which season blocks can appear in the left edit menu. `getEditSeasonLinks()` only builds season links for seasons present here.
- `poolselector`
  Controls which seasons, divisions, and pools are shown in the public navigation.
- Logged-in state
  `isLoggedIn()` only checks that `$_SESSION['uid']` exists and is not `anonymous`.

Season-scoped role assignment through `AddSeasonUserRole()` also calls `AddEditSeason()`, so users who receive a season-scoped role also get that season into `editseason`.

## Active roles

- `superadmin`
  Global administrator. This is also the implementation behind `hasViewUsersRight()`, `hasEditUsersRight()`, `hasChangeCurrentSeasonRight()`, and `hasTranslationRight()`.
- `seasonadmin:<seasonId>`
  Season-wide event administration.
- `spiritadmin:<seasonId>`
  Season-wide spirit tooling and spirit review/edit rights.
- `seriesadmin:<seriesId>`
  Division-scoped team/game/standing administration for one series.
- `teamadmin:<teamId>`
  Team-scoped player management and team responsibility access.
- `accradmin:<teamId>`
  Team-scoped accreditation access.
- `resadmin:<reservationId>`
  Reservation scheduling access. `hasScheduleRights()` returns true when the user has at least one of these.
- `resgameadmin:<reservationId>`
  Game-entry rights for games in a reservation.
- `gameadmin:<gameId>`
  Game-entry rights for a single game.
- `playeradmin:<profileId>`
  Edit rights for a single player profile.

## Core permission helpers

The main helpers live in [lib/user.functions.php](../lib/user.functions.php).

- Global/scope helpers:
  `isSuperAdmin()`, `isSeasonAdmin()`, `isSpiritAdmin()`, `hasScheduleRights()`, `hasViewUsersRight()`, `hasEditUsersRight()`, `hasTranslationRight()`
- Season and series page helpers:
  `hasSeasonSeriesPageAccess()`, `hasAccreditationPageAccess()`, `hasReservationsPageAccess()`
- Write helpers:
  `hasEditSeasonSeriesRight()`, `hasEditPlacesRight()`, `hasEditTeamsRight()`, `hasEditGamesRight()`, `hasEditPlayerProfileRight()`, `hasEditPlayersRight()`, `hasEditGamePlayersRight()`, `hasEditGameEventsRight()`, `hasAccredidationRight()`
- Spirit season helpers:
  `hasSpiritToolsRight()`, `hasSpiritEditRight()`

## Read-only events

`canBypassEventReadonly()` returns true only for `superadmin`.

The following helpers deny writes when the event is read-only and the user is not `superadmin`:

- `hasEditSeasonSeriesRight()`
- `hasEditPlacesRight()`
- `hasEditTeamsRight()`
- `hasEditGamesRight()`
- `hasEditPlayerProfileRight()`
- `hasEditPlayersRight()`
- `hasEditGamePlayersRight()`
- `hasEditGameEventsRight()`
- `hasAccredidationRight()`
- `hasSpiritEditRight()`

Read-only status does not block spirit review access by itself.

## Spirit-specific access

The spirit-specific logic is implemented in [lib/spirit.functions.php](../lib/spirit.functions.php).

### Season-level spirit visibility

- `ShowSpiritScoresForSeason($seasoninfo)` returns true only when the season has `spiritmode > 0` and either:
  `showspiritpoints` is enabled, or
  the current user has `hasSpiritToolsRight($seasonId)`
- `ShowSpiritComments($seasoninfo)` returns true only when the season has `spiritmode > 0` and either:
  `showspiritcomments` is enabled, or
  the current user has `hasSpiritToolsRight($seasonId)`

### Full spirit review and edit rights

- `hasSpiritToolsRight($season)` is true for `seasonadmin`, `spiritadmin`, and `superadmin`.
- `hasSpiritEditRight($season)` is `hasSpiritToolsRight($season)` plus the event must not be read-only unless the user is `superadmin`.
- `HasFullGameSpiritEditRight($gameId)` is true when:
  `hasSpiritEditRight($season)` is true, or
  the user has `seriesadmin` for the game series, `resgameadmin` for the reservation, or `gameadmin` for the game, and the event is not read-only unless the user is `superadmin`.
- `HasFullGameSpiritViewRight($gameId)` is true when:
  `hasSpiritToolsRight($season)` is true, or
  `HasFullGameSpiritEditRight($gameId)` is true.

### Team-scoped spirit entry

- `SpiritEntryTeamForUser($gameId)` returns:
  `-1` if the user has no spirit access for the game
  `0` if the user has full spirit review access or can manage both teams
  a team id when the user can submit spirit for exactly one side
- `SpiritEntryUrl($gameId, $baseView)` returns an empty string when there is no access, or a URL containing `game=<id>` and optionally `team=<id>`.

`CanEditSpiritSubmission($gameId, $teamId)` works as follows:

- Users with `HasFullGameSpiritEditRight($gameId)` can edit either team’s submission.
- Otherwise, the user must have `hasEditPlayersRight()` for the opposing team.
  Example: editing the home team’s spirit submission requires player-edit rights for the visitor team.
- If `lockteamspiritonsubmit` is enabled and that team already has a complete spirit submission, editing is blocked.

`CanDeleteSpiritSubmission($gameId, $teamId)` requires `HasFullGameSpiritEditRight($gameId)`. Team-scoped submitters cannot delete submissions unless they also have full spirit edit rights.

`CanViewSpiritScoresForGame($gameId)` and `CanViewSpiritCommentsForGame($gameId)` allow privileged spirit users to bypass public visibility flags. Other users depend on the season settings and `uo_game.show_spirit`.

Spirit comment permissions in [lib/comment.functions.php](../lib/comment.functions.php) follow the same model:

- `CanCreateSpiritComment()` delegates to `CanEditSpiritSubmission()`
- `CanManageSpiritComment()` uses `HasFullGameSpiritEditRight()`

## Menu visibility

The main left menu is built in [menufunctions.php](../menufunctions.php).

### Administration block

- The `Administration` block is shown when `hasScheduleRights()` or `isSuperAdmin()` or `hasTranslationRight()` is true.
- In current implementation, `hasTranslationRight()` is the same as `isSuperAdmin()`, so in practice this block is shown for:
  users with any `resadmin` role
  superadmins
- Inside that block:
  `Scheduling` is shown for users with `hasScheduleRights()`
  `Translations` is shown for users with `hasTranslationRight()`, which currently means superadmins
  `Events`, `Rule templates`, `Clubs & Countries`, `Field locations`, `Field reservations`, `Users`, `API Tokens`, `Logs`, `Database`, and `Settings` are shown for `superadmin`

### Season edit blocks

`getEditSeasonLinks()` builds season-specific blocks only for seasons present in `editseason`.

Within each such season:

- `seasonadmin` adds:
  `Event`, `Divisions`, `Teams`, `Pools`, `Scheduling`, `Games`, `Standings`, `Accreditation`
  `Spirit` when the season has `spiritmode`
  `Season points` when `use_season_points` is enabled
- `seriesadmin` adds:
  `<Series> Teams`, `<Series> Games`, `<Series> Pool standings`, `Accreditation`
  These are only added when the user is not already `seasonadmin` for the same season.
- `spiritadmin` adds:
  `Spirit`
  This is only added when the user is not already `seasonadmin` for that season and the season has `spiritmode`.
- `teamadmin` adds either:
  a direct `Team: <name>` link when the user has fewer than two team responsibilities in that season
  or `Team responsibilities` when they have multiple team responsibilities
- `accradmin` is also handled in `getEditSeasonLinks()` for team and accreditation navigation
- `gameadmin` and `resgameadmin` mark the season as having game responsibilities
- Any season marked as having game responsibilities gets:
  `Game responsibilities` and `Contacts`

## Page-level access checks

Examples of explicit route checks currently in use:

- [admin/seasonadmin.php](../admin/seasonadmin.php) and [admin/seasonseries.php](../admin/seasonseries.php) require `isSeasonAdmin($season)`
- [admin/seasonpools.php](../admin/seasonpools.php) requires `isSeasonAdmin($season)`
- [admin/seasonteams.php](../admin/seasonteams.php), [admin/seasongames.php](../admin/seasongames.php), and [admin/seasonstandings.php](../admin/seasonstandings.php) require `hasSeasonSeriesPageAccess($season, $series)`
- [admin/accreditation.php](../admin/accreditation.php) requires `hasAccreditationPageAccess($season)`
- [admin/reservations.php](../admin/reservations.php) requires `hasReservationsPageAccess($season)`
- [admin/spirit.php](../admin/spirit.php) requires `hasSpiritToolsRight($season)`

Spirit entry pages also perform direct access checks before rendering:

- [user/addspirit.php](../user/addspirit.php)
- [spiritkeeper/editgame.php](../spiritkeeper/editgame.php)

These pages use `SpiritEntryTeamForUser()` and `HasFullGameSpiritViewRight()` to decide whether the user has no access, team-scoped submit access, or full review access.

## Game-edit tab visibility examples

The spirit tab/link in these pages is shown through `SpiritEntryUrl()`:

- [user/addresult.php](../user/addresult.php)
- [user/addplayerlists.php](../user/addplayerlists.php)
- [user/addscoresheet.php](../user/addscoresheet.php)
- [user/adddefensesheet.php](../user/adddefensesheet.php)
- [user/respgames.php](../user/respgames.php)
- [mobile/addscoresheet.php](../mobile/addscoresheet.php)

If `SpiritEntryUrl()` returns an empty string, the spirit link is hidden.

Scorekeeper no longer shows spirit links. Spirit entry has been moved out of `scorekeeper/` and into Spiritkeeper or the main logged-in user pages.
