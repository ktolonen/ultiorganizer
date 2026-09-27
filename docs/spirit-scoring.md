# Spirit Scoring

Spirit score logic, visibility, stoppages and settings. See `docs/permissions.md` for spirit rights and `docs/spiritkeeper.md` for the mobile entry app.

## Code locations

- Logic: `lib/spirit.functions.php`; comments: `lib/comment.functions.php`
- Entry: `user/addspirit.php` and `spiritkeeper/`
- Public displays: `teamcard.php`, `seriesstatus.php`, `spiritstatus.php`, `gameplay.php`
- Admin: `spiritmode` in `admin/addseasons.php`; settings in `admin/spiritsettings.php`; review, missing-score and comment searches, stoppage summaries and Spiritkeeper tokens in `admin/spirit.php`
- Stoppage entry: `user/addscoresheet.php`, `scorekeeper/addspirittimeouts.php`, `mobile/addspirittimeouts.php`; `user/pdfscoresheet.php` prints a stoppage area

## Data model

- `uo_spirit_score`: scores. The presence of all required category rows for a game and team is the submission; all zeroes is a valid submission, no rows means no submission, and there is no `N/A` state. Admin deletion returns to "no submission".
- `uo_spirit_category`: categories grouped by `mode`; `uo_season.spiritmode` selects the group, so a mode is an installation-wide taxonomy.
- `uo_game.show_spirit`: cached public visibility, maintained by `RefreshGameSpiritVisibility()`.
- `uo_team_spirit_stats`: cached team averages.
- `uo_spirit_timeout`: spirit stoppages.
- `uo_comment`: spirit comments with `COMMENT_TYPE_SPIRIT_HOME` (5) and `COMMENT_TYPE_SPIRIT_VISITOR` (6), via `SpiritCommentTypeForTeam()`, `CanCreateSpiritComment()`, `CanManageSpiritComment()`, `SetSpiritComment()`.

Event snapshot import (`lib/data.functions.php`) reuses the installation's existing categories and copies `spiritmode`, which assumes the snapshot came from the same installation. From another installation with different categories in that mode, the import adds a warning rather than remapping; cross-installation remapping is out of scope.

## Settings

`EVENT_SETTING`s (see [configuration-flags.md](configuration-flags.md)):

- `spiritmode`: scoring model, and the on/off switch for spirit in the event. Empty or `0` disables spirit scoring, stoppages and the admin `Spirit` menu.
- `showspiritpoints`: public score visibility.
- `showspiritcomments`: public comment visibility.
- `showspiritcommentstoteams` (default off): in the Spiritkeeper token flow a team sees the note it gave and, once both teams have submitted, the note it received. Independent of `showspiritcomments`.
- `showspiritpointsonlyoncomplete`: non-admins see scores and averages only once both teams have submitted complete scores.
- `lockteamspiritonsubmit`: blocks team-side edits after a team's complete submission.

## Visibility

- `ShowSpiritScoresForSeason()` and `ShowSpiritComments()` require `spiritmode > 0` and the matching `showspirit*` setting, or `hasSpiritToolsRight()`.
- `CanViewSpiritScoresForGame()` and `CanViewSpiritCommentsForGame()` centralize per-game checks; privileged users bypass the public flags, others depend on the settings and `uo_game.show_spirit`.
- `spiritstatus.php` is hidden when `ShowSpiritScoresForSeason()` is false, and so are the other aggregated outputs: `SeasonSpiritTopTeamsBySeriesType()`, the team card history averages (`TeamSpiritAveragesByName()`, `TeamSpiritCategoryHistoryAveragesByName()`), the `SpiritPoints` CSV column (`TeamsToCsv()`, zero when hidden), and the spirit block of `/api/v1/gameplay`.
- Public aggregates (`SeriesSpiritBoard()`, `SeriesSpiritBoardTotalAverages(..., false)`, `TeamSpiritTotal(..., false)`, `TeamSpiritStats2(..., false)`, `TeamSpiritTotalByPool()`, `SpiritRebuildTeamStatsForSeason()`) read only games with `show_spirit=1`; passing `true` includes incomplete games for admin views.
- In the token flow, received scores appear only after the team and its opponent have both submitted (`SpiritTokenCanViewReceivedPoints()`, not configurable).

## Submission rules

- `TeamSpiritSubmissionComplete()`: all required categories exist for the game and team. `GameSpiritComplete()`: both teams complete.
- `CanEditSpiritSubmission()` blocks team edits after a complete submission when `lockteamspiritonsubmit=1`. Spirit comments follow the same lock.
- Deleting a submission is admin-only (`CanDeleteSpiritSubmission()`, `GameDeleteSpiritPoints()`, a button in `user/addspirit.php`) and rebuilds the visibility and stats caches.
- Token-flow notes are saved with `SpiritTokenSaveComment()` only while `SpiritTokenCanSubmit()` holds.
- Forfeited games (`uo_game.forfeit`) take no team submissions: `SpiritTokenCanSubmit()` and the team path of `CanEditSpiritSubmission()` refuse, and Spiritkeeper shows a forfeit note. Spirit admins bypass this.

## Spirit stoppages

Enabled when `spiritmode > 0` and `hide_time_on_scoresheet` is off. Each `uo_spirit_timeout` row stores game, team, sequence and time in seconds. All entry pages replace the game's rows on save. Entry UIs and printed sheets offer four slots per team, though the data model has no limit. `GameEvents()` exposes them as `spirit_timeout`, labelled `Spirit stoppage` in replays.

## WFDF operating principles

Principles from WFDF events and Spirit Director workflows, which guide changes:

- Admins and Spirit Directors see all scores and comments immediately and can edit or delete submissions; team submissions are otherwise final.
- The public sees a game's scores only after both teams submit, and public averages include only those games. Admin averages may include incomplete games so missing submissions can be followed.
- Spirit comments are usually not public. In token flows, teams ideally see what they received only after submitting their own.
- All zeroes is a valid score; deletion is explicit.
- Missing-score tracking and spirit stoppage recording are first-class workflows.

Open gaps: an event setting for whether teams may see received scores after submitting, and one shared reveal rule used by browser views, exports and the API.
