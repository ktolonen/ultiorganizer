# Spirit Scoring

This page collects two things:

- current spirit-related behavior in this repository,
- WFDF-oriented operating principles described by Bruno from recent events.

Keep those separate when planning changes. The repository does not yet implement all of the WFDF-oriented behavior.

## Core code locations

- Core spirit score logic lives in `lib/spirit.functions.php`.
- Spirit score entry UI is `user/addspirit.php`.
- Public spirit displays are primarily in `teamcard.php`, `seriesstatus.php`, `spiritstatus.php`, and `gameplay.php`.
- Admin event setup is in `admin/addseasons.php`.
- Admin server-wide spirit comment visibility is in `admin/serverconf.php`.
- Missing-score admin tooling is in `admin/spirit.php`.

## Data model and comment types

- Spirit scores are stored in `uo_spirit_score`.
- Spirit category definitions are stored in `uo_spirit_category`.
- Cached team averages are stored in `uo_team_spirit_stats`.
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
  Selects the scoring model for the event. For WFDF events, the expected default is the WFDF mode in use for that event, typically `1003` in this codebase.
- `EVENT_SETTING`: `showspiritpoints`
  Current repository behavior is only a boolean public visibility toggle. Admins bypass it via `ShowSpiritScoresForSeason()`.
- `EVENT_SETTING`: `showspiritcomments`
  Controls per-event visibility of spirit comments.

### Admin-only capabilities that should exist regardless of event settings

- Edit a submitted spirit score.
- Delete a submitted spirit score explicitly.
- Mark a score as not applicable if the workflow needs that distinction.
- Review missing submissions by game, pool, and division.

## Current Repository Behavior

This section describes what the repository does today.

### Score visibility

- `ShowSpiritScoresForSeason()` allows spirit score visibility when the event has `spiritmode > 0` and `showspiritpoints` is enabled.
- Season admins bypass the public visibility flag and can always see scores.
- `teamcard.php` hides per-game public rows unless `GameSpiritComplete()` says both teams have fully submitted; admins can still see incomplete received rows.
- `spiritstatus.php` hides the full spirit page when `ShowSpiritScoresForSeason()` is false.

### Aggregate calculations

- `SeriesSpiritBoardTotalAverages(..., false)` enforces complete-game-only averages.
- `TeamSpiritTotal(..., false)` and `TeamSpiritStats2(..., false)` also support complete-game-only calculations.
- `TeamSpiritTotal(..., true)` and `TeamSpiritStats2(..., true)` support admin-style incomplete-game inclusion.

### Important current gaps

- `SeriesSpiritBoard()` does not filter out incomplete games before calculating per-team division averages. `seriesstatus.php` and `spiritstatus.php` currently use it.
- `SpiritRebuildTeamStatsForSeason()` writes `uo_team_spirit_stats` from all submitted rows, not only complete games. `teamcard.php` reads those cached stats.
- `gameplay.php` should respect the event-level `showspiritcomments` setting when rendering spirit notes.
- The repository does not contain an event-level setting for "opponent can see received comments after submitting own score".
- No first-class score deletion UI or explicit `N/A` state is documented here.
- No spirit-timeout data model or UI exists in this repository.

## Notes For Future Changes

- Prefer `EVENT_SETTING` over `INSTALLATION_SETTING` for spirit visibility rules unless the whole installation truly shares one policy.
- Treat changes to `showspiritcomments` as event-level configuration work in season admin flows.
- If WFDF behavior is the target, apply the "both teams submitted" rule consistently to:
  game-level views,
  pool/division/team averages,
  CSV exports,
  and any public comments display.
