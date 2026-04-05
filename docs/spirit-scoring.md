# Spirit Scoring

This page collects two things:

- current spirit-related behavior in this repository,
- WFDF-oriented operating principles described by Bruno from recent events.

Keep those separate when planning changes. The repository does not yet implement all of the WFDF-oriented behavior.

## Core code locations

- Core spirit score logic lives in `lib/spirit.functions.php`.
- Spirit score entry UI is `user/addspirit.php`.
- Public spirit displays are primarily in `teamcard.php`, `seriesstatus.php`, `spiritstatus.php`, and `gameplay.php`.
- Event creation and the top-level `spiritmode` switch are in `admin/addseasons.php`.
- Detailed spirit settings are managed in `admin/spiritsettings.php`.
- Spirit admin tooling and missing-score review are in `admin/spirit.php`.
- Spiritkeeper provides the dedicated mobile spirit-entry surface for both token and authenticated access.
- Spirit-timeout entry uses `user/addscoresheet.php`, `mobile/addspirittimeouts.php`, and `scorekeeper/addspirittimeouts.php`.
- Printable field sheets are generated through `user/pdfscoresheet.php` and include a dedicated spirit-timeout area on the scoresheet PDF layouts.

## Data model and comment types

- Spirit scores are stored in `uo_spirit_score`.
- Game-level public spirit visibility is cached in `uo_game.show_spirit`.
- Spirit category definitions are stored in `uo_spirit_category`.
- Cached team averages are stored in `uo_team_spirit_stats`.
- Spirit timeout rows are stored in `uo_spirit_timeout`.
- Spirit comments are stored in `uo_comment` and handled in `lib/comment.functions.php`.
- `COMMENT_TYPE_SPIRIT_HOME` is `5`.
- `COMMENT_TYPE_SPIRIT_VISITOR` is `6`.
- Main comment helpers are `SpiritCommentTypeForTeam()`, `CanCreateSpiritComment()`, `CanManageSpiritComment()`, and `SetSpiritComment()`.

## WFDF-Oriented Principles

These are the operating principles Bruno described from WFDF events and Spirit Director workflows.

- If logged in as admin, all spirit scores and comments should be visible immediately.
- New role needed for spirit admin (= spirit director) to see and control spirit related fucntionality.
- There should be a new spirit menu on event menu. This should contain spirit related tools from td-tools and spirit board.
- For non-admin users, spirit scores should normally become visible only after both teams have submitted their scores for the game.
- A single submitted score should not make a game visible publicly and should not enter public averages yet.
- Public spirit averages should be calculated only from games where both teams have submitted.
- Admin-facing averages and review tools may include incomplete games so Spirit Directors can follow missing submissions.
- Spirit comments are usually not visible to the general public.
- When token-based team submission links are used, teams should ideally be able to see the scores and comments they received only after they submit their own score.
- Team-submitted spirit scores should be treated as final; later edits or deletion should be an admin or Spirit Director action.
- A score of all zeroes must remain a valid submitted score. Deletion should be explicit, not inferred from zero values.
- Missing-score tracking is an important Spirit Director workflow.
- Recording spirit timeouts is a recurring event need and should have first-class support if the event uses that workflow.

## Required Configuration Surface

Use the exact type names from [configuration-flags.md](/home/kari/code/ultiorganizer/docs/configuration-flags.md).

### Existing settings

- `EVENT_SETTING`: `spiritmode`
  Selects the scoring model for the event. This is also the event-level "is spirit scoring used at all" switch. When `spiritmode` is empty or `0`, spirit scoring is treated as disabled for the event and the admin Spirit menu should stay hidden.
- `EVENT_SETTING`: `showspiritpoints`
  Enables spirit score visibility for the event. Admins bypass it via `ShowSpiritScoresForSeason()`.
- `EVENT_SETTING`: `showspiritcomments`
  Controls per-event visibility of spirit comments.
- `EVENT_SETTING`: `showspiritpointsonlyoncomplete`
  When enabled, non-admin users only see spirit scores and spirit-based averages after both teams have submitted complete scores for the game.
- `EVENT_SETTING`: `lockteamspiritonsubmit`
  When enabled, team-side edits are blocked after that team has submitted a complete spirit score for the game.

### Admin-only capabilities that should exist regardless of event settings

- Edit a submitted spirit score.
- Delete a submitted spirit score explicitly.
- Review missing submissions by game, pool, and division.

## Current Repository Behavior

This section describes what the repository does today.

### Admin setup and navigation

- `spiritmode` is the top-level event switch that enables or disables spirit scoring for a season.
- Detailed spirit settings are edited in `admin/spiritsettings.php`, linked from `admin/spirit.php`.
- The admin `Spirit` menu entry is shown only for events where `spiritmode > 0`.
- There is a dedicated `spiritadmin:<seasonId>` role for spirit-specific tooling and review. It is intentionally narrower than season admin and does not grant broader event administration rights.
- `admin/spirit.php` contains spirit review tools, missing-score searches, comment search, and Spiritkeeper token utilities.
- Spirit timeout recording is enabled only when `spiritmode > 0`.
- Spirit timeout entry is additionally blocked when `hide_time_on_scoresheet` is enabled for the season.

### Spirit timeout behavior

- Spirit timeouts are stored separately from ordinary timeouts in `uo_spirit_timeout`.
- Each spirit-timeout row stores the game, team ownership, sequence number, and timestamp in seconds.
- Spirit timeouts are edited from timeout-oriented score-sheet surfaces, and mobile and scorekeeper both use dedicated spirit-timeout pages.
- Desktop bulk score-sheet editing in `user/addscoresheet.php` replaces all existing spirit-timeout rows for the game on save, then re-inserts the submitted rows.
- `mobile/addspirittimeouts.php` and `scorekeeper/addspirittimeouts.php` save spirit timeouts independently from ordinary timeout pages, using the same replace-on-save model.
- The data model does not enforce a hard maximum, but the current entry UIs and printable scoresheets provide four spirit-timeout slots per team.
- `GameEvents()` exposes spirit timeouts as event type `spirit_timeout`, and gameplay replays label them `Spirit timeout`.
- `admin/spirit.php` includes season-scoped spirit-timeout summary counts and links to games where spirit timeouts were recorded.

### Score visibility

- `ShowSpiritScoresForSeason()` allows spirit score visibility when the event has `spiritmode > 0` and `showspiritpoints` is enabled.
- Season admins bypass the public visibility flag and can always see scores.
- `RefreshGameSpiritVisibility()` maintains `uo_game.show_spirit` as the shared visibility flag for public spirit rows and spirit-based calculations.
- `CanViewSpiritScoresForGame()` and `CanViewSpiritCommentsForGame()` centralize non-admin visibility checks.
- `spiritstatus.php` hides the full spirit page when `ShowSpiritScoresForSeason()` is false.

### Aggregate calculations

- `SeriesSpiritBoard()`, `SeriesSpiritBoardTotalAverages(..., false)`, `TeamSpiritTotal(..., false)`, `TeamSpiritStats2(..., false)`, `TeamSpiritTotalByPool()`, and `SpiritRebuildTeamStatsForSeason()` now read only games with `uo_game.show_spirit=1`.
- `TeamSpiritTotal(..., true)` and `TeamSpiritStats2(..., true)` support admin-style incomplete-game inclusion.

### Submission locking

- `TeamSpiritSubmissionComplete()` treats a team submission as complete when all required spirit categories for the active mode exist for that game and team.
- `GameSpiritComplete()` requires complete submissions from both teams.
- `CanEditSpiritSubmission()` blocks team-side score edits after a complete submission when `lockteamspiritonsubmit=1`.
- `CanDeleteSpiritSubmission()` and `GameDeleteSpiritPoints()` provide an admin-only path to remove a submitted spirit score and rebuild visibility/stat caches.
- `user/addspirit.php` exposes the delete action as an admin-only button next to each existing submitted spirit score.
- Spirit comment create/update/delete uses the same shared lock through `CanCreateSpiritComment()` and `CanManageSpiritComment()`.
- Token-based spirit-note editing uses `SpiritTokenSaveComment()` and is allowed only while `SpiritTokenCanSubmit()` is still true for that team/game.

### Submission state semantics

- Presence of the required `uo_spirit_score` rows is the submission state for a game/team.
- A score of all zeroes is still a valid completed submission if all required category rows exist.
- If a game/team has no `uo_spirit_score` rows, that means there is no spirit submission for that game/team.
- There is no separate `N/A` state in the current model, and none is planned.
- Admin deletion removes the `uo_spirit_score` rows and returns the game/team to the same "no submission" state.

### Important current gaps

- The repository does not contain an event-level setting for "opponent can see received scores/comments after submitting own score".
- The current token-based self-service flow hardcodes a narrow version of that reveal rule: received scores are shown only after the team has submitted its own score and the opponent has also submitted.
- The current token-based self-service flow allows editing only the submitting team's own outbound spirit note. It does not expose opponent spirit notes.

## Remaining implementation gaps

### 1. Token and reveal workflow

- Add an `EVENT_SETTING` for whether teams may see received spirit scores/comments only after they submit their own score.
- Centralize that rule in shared spirit/comment visibility helpers so browser views, exports, and APIs follow the same behavior.
- Decide whether token flows should ever expose received spirit notes, and if so under what event-level setting and moderation rules.
