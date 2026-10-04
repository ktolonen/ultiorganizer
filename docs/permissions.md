# Permissions

The permission model: storage, roles, helpers, and where they are enforced.

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
  `hasSeasonSeriesPageAccess()`, `hasAccreditationPageAccess()`, `hasReservationsPageAccess()`, `hasViewScoresheetHistoryRight()`
- Write helpers:
  `hasEditSeasonSeriesRight()`, `hasEditPlacesRight()`, `hasEditTeamsRight()`, `hasEditGamesRight()`, `hasEditPlayerProfileRight()`, `hasEditPlayersRight()`, `hasEditGamePlayersRight()`, `hasEditGameEventsRight()`, `hasAccredidationRight()`, `hasRestoreScoresheetHistoryRight()`
- Spirit season helpers:
  `hasSpiritToolsRight()`, `hasSpiritEditRight()`

## Scorekeeping links

A scorekeeping link (`docs/scorekeeper.md`) gives game-entry rights without a role row. `hasEditGameEventsRight()` and `hasEditGamePlayersRight()` end their role check with `ScorekeeperGrantCovers($game)`, which is true only when `IsScorekeeperApp()` holds, so a link works in Scorekeeper and never in the desktop editors or the API. The read-only check after it still applies.

Logged-in users hold links as rows in `uo_scorekeeper_grant`; an anonymous session holds them in `$_SESSION['scorekeeper_tokens']`, and only while the event's `anonymous_scorekeeping` setting is on. `lib/auth.guard.php` admits such a session through `$auth_allow_anonymous`, which `scorekeeper/auth.php` sets. Game notes stay out of reach for it, because `CanManageGameComment()` requires a login.

`CanIssueGameScorekeeperToken()` and `CanIssueReservationScorekeeperToken()` decide who may see, print and replace a link: `superadmin` and `seasonadmin`. Division admins and reservation game admins already keep score through their own roles, so they get no link; scoresheets they print leave the QR code out. A link holder cannot see or replace the link itself. The event's link list (`admin/scorekeepinglinks.php`), which names the users who opened each link, is for event admins only; `SeasonScorekeeperTokens()` checks `isSeasonAdmin()`. Revoking uses the same issue check as replacing.

## Read-only events

`canBypassEventReadonly()` returns true only for `superadmin`.

All the write helpers above, and `hasSpiritEditRight()`, deny writes in a read-only event unless the user is `superadmin`. Spirit review access is not blocked.

Permission branches that do not go through these helpers check the flag themselves: the note-author branch of `CanManageGameComment()` (and so `CanManageSpiritComment()`), and the publisher branch of `CanRemoveMediaUrl()` for game, team, division and pool links. Player and club links are not tied to an event.

## Cross-event scoping

A mutation that checks rights on one event, division or pool must not write rows that belong to another. The shared helpers below enforce this, so a page that authorizes the event in its URL cannot be used to change another event's data:

- `RecalculatePoolStandings()` checks `hasEditTeamsRight()` on the pool's own division before resolving its standings. Admin pages use it; `ResolvePoolStandings()` itself stays unguarded because result saves call it under game rights. `PoolConfirmMoves()` and `AutoResolveTiesInSourcePools()` check the same right.
- `PoolUndoMove()` requires edit rights on both the source and the target pool.
- `RemoveReservation()` and `SetReservation()` check rights against the reservation's stored event; a reservation with no event is editable by a superadmin only.
- `PoolSetSchedulingName()` renames only a scheduling name that a move or game of the given event uses.
- `SetTeamSeeding()` updates only teams of the given division.
- `ScheduleGame()` and `SetGame()` accept only reservations, teams and the responsible team of the game's own event. `CanScheduleGameInReservation()` holds the reservation rule, so `ClearReservation()` and `admin/saveschedule.php` leave another event's game in a reservation untouched instead of stopping the save.
- `GameAllowsPlayerOnRoster()` admits only players of the game's home or visiting team, except that a player already on the game's roster stays allowed after a team change.

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
  the user has `seriesadmin` for the game series and the event is not read-only unless the user is `superadmin`. `gameadmin` and `resgameadmin` run the scoring desk and get no spirit rights.
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
  Example: editing the home team’s spirit submission requires player-edit rights for the away team.
- If `lockteamspiritonsubmit` is enabled and that team already has a complete spirit submission, editing is blocked.

`CanDeleteSpiritSubmission($gameId, $teamId)` requires `HasFullGameSpiritEditRight($gameId)`. Team-scoped submitters cannot delete submissions unless they also have full spirit edit rights.

`CanViewSpiritScoresForGame($gameId)` and `CanViewSpiritCommentsForGame($gameId)` allow privileged spirit users to bypass public visibility flags. Other users depend on the season settings and `uo_game.show_spirit`.

Spirit comment permissions in [lib/comment.functions.php](../lib/comment.functions.php) follow the same model:

- `CanCreateSpiritComment()` delegates to `CanEditSpiritSubmission()`
- `CanManageSpiritComment()` uses `HasFullGameSpiritEditRight()`

### Game note visibility

`CanViewGameComment($gameId, $seasoninfo)` in [lib/comment.functions.php](../lib/comment.functions.php) gates the game note (`COMMENT_TYPE_GAME`) rendered on the public game page. Season admins and the spirit director — the `hasSpiritToolsRight()` set (`seasonadmin`, `spiritadmin`, `superadmin`) — always see it; everyone else, including team-, series-, and game-scoped admins and scorekeepers, sees it only when the season's `showgamecomments` setting is enabled. It defaults to off, so scorekeeper-entered game notes stay private unless an admin publishes them per event.

## Menu visibility

The left menu is built in [menufunctions.php](../menufunctions.php).

- The `Administration` block appears for `hasScheduleRights()` (any `resadmin`) and superadmins. `Scheduling` needs `hasScheduleRights()`; `Translations` and all other entries need `superadmin`.
- `getEditSeasonLinks()` builds a block per season in `editseason`:
  - `seasonadmin`: `Event`, `Divisions`, `Teams`, `Pools`, `Scheduling`, `Games`, `Pool standings`, `Final standings`, `Accreditation`, plus `Spirit` with `spiritmode` and `Season points` with `use_season_points`
  - `seriesadmin` (if not `seasonadmin`): per-division `Teams`, `Games`, `Pool standings`, and `Accreditation`
  - `spiritadmin` (if not `seasonadmin`, with `spiritmode`): `Spirit`
  - `teamadmin`: `Team: <name>`, or `Team responsibilities` for two or more teams; `accradmin` adds team and accreditation links
  - `gameadmin` / `resgameadmin`: `Game responsibilities` and `Contacts`

## Page-level checks

Admin pages check their scope before rendering, e.g. `isSeasonAdmin($season)` in `admin/seasonadmin.php`, `admin/seasonseries.php` and `admin/seasonpools.php`; `hasSeasonSeriesPageAccess()` in `admin/seasonteams.php`, `admin/seasongames.php`, `admin/seasonstandings.php`, `admin/serieteams.php` and `admin/seasonmoves.php` (which also checks the division belongs to the event); `hasAccreditationPageAccess()`, `hasReservationsPageAccess()` and `hasSpiritToolsRight()` in `admin/accreditation.php`, `admin/reservations.php` and `admin/spirit.php`.

`user/addspirit.php` and `spiritkeeper/editgame.php` use `SpiritEntryTeamForUser()` and `HasFullGameSpiritViewRight()` to choose between no access, one-team submission and full review. The game-edit pages show the spirit link only when `SpiritEntryUrl()` returns one. Scorekeeper shows no spirit links.
