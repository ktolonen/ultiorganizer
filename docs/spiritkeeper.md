# Spiritkeeper

This page documents the standalone Spiritkeeper app under `spiritkeeper/`.

Spiritkeeper is the dedicated mobile surface for spirit entry and review. It now has two access modes:

- public token-based team submission,
- authenticated username/password access for logged-in users who already have spirit rights in the main application.

It does not use the main `?view=...` router, but it writes through the same spirit helpers and database model as the rest of the application.

## Core entrypoints

- `spiritkeeper/index.php`: public bootstrap and page shell for Spiritkeeper.
- `spiritkeeper/teamgames.php`: game list view for both token and authenticated Spiritkeeper access.
- `spiritkeeper/submitsotg.php`: spirit score form for one game.
- `lib/spirit.functions.php`: shared token-aware lookup, validation, save, visibility helpers, and Spiritkeeper-specific display helpers.

## URL and access model

- The public URL shape is `/spiritkeeper/?token=...`.
- The authenticated entry URL shape is `/spiritkeeper/?view=editgame&game=<id>` and may also include `team=<responsibleTeamId>`.
- Tokens are stored in `uo_team.sotg_token`.
- `SpiritTeamIdByToken()` resolves the token to the owning team.
- `admin/spirit.php` remains the place where season admins or spirit admins generate and list Spiritkeeper URLs.
- Authenticated Spiritkeeper access uses the normal user session and `UserAuthenticate()` flow.

The token identifies a team, not a user account. The public token flow therefore does not rely on logged-in rights checks from `user/addspirit.php`.

## Data model

Both Spiritkeeper access modes use the same current spirit storage model as the rest of the repository:

- scores are stored in `uo_spirit_score`,
- score categories come from `uo_spirit_category`,
- game-level public visibility is cached in `uo_game.show_spirit`,
- visibility/stat refresh is done through `RefreshGameSpiritData()`.

The token flow does not write to legacy `uo_spirit`, and it does not directly update cached `homesotg` or `visitorsotg` columns on `uo_game`.

## Token team and game mapping

The token team is the team that owns the Spiritkeeper URL.

For a given game:

- the token team submits a score for the opponent team,
- the rated team stored in `uo_spirit_score.team_id` is therefore the opponent,
- the token team may later see the score they received only through the token-specific reveal rule.

`SpiritTokenGameRows()` returns all spirit-enabled games for the token-owning team.

## Token submission rules

`SpiritTokenCanSubmit()` is the main guard for public submission. A token-based submission is allowed only when:

- the token resolves to a team,
- the game belongs to that team,
- the season has `spiritmode > 0`,
- the event is not in `event_readonly`,
- the game has started (`hasstarted > 0`),
- the team's own public token submission is not locked by `lockteamspiritonsubmit`.

Submission uses `SpiritTokenSaveSubmission()`, which:

- validates values against the active category definitions,
- accepts zero values if all required categories are present,
- replaces the existing `uo_spirit_score` rows for that game/team,
- calls `RefreshGameSpiritData()` so visibility and aggregate caches stay in sync.

## Token visibility behavior

The token flow is intentionally narrower than the logged-in spirit UI.

Current behavior:

- the token holder can always see the list of their own team games,
- after submitting, the token holder can see the score they gave,
- the token holder can see the score they received only when both conditions are true:
  `SpiritTokenHasOwnSubmission()` is true and `SpiritTokenHasReceivedSubmission()` is true,
- the token holder can add, update, or delete their own spirit note while the token submission is still open,
- opponent spirit notes are not shown in the public token flow.

This reveal behavior is implemented by `SpiritTokenCanViewReceivedPoints()`. It is currently hardcoded for the token flow and is not yet an event-level setting.

## Authenticated Spiritkeeper behavior

Authenticated Spiritkeeper is a focused team-and-game spirit workflow.

- `spiritkeeper/index.php` accepts login POSTs when no token is present.
- `spiritkeeper/home.php` is the authenticated event and team selection page.
- `spiritkeeper/teamgames.php` shows only the selected team's game list in authenticated mode.
- `spiritkeeper/login.php` is the dedicated login page for direct authenticated links.
- `spiritkeeper/editgame.php` is the mobile game-level editor for logged-in users.
- Access checks still come from `SpiritEntryTeamForUser()`, `HasFullGameSpiritViewRight()`, `CanEditSpiritSubmission()`, and the spirit comment helpers.
- Season admins and spirit admins can select from all teams in their spirit-enabled events.
- Team admins can select from the teams they manage, including multiple teams across events.
- In authenticated mode, `team=<id>` identifies the submitting team, matching `SpiritEntryUrl()` semantics from the main app.
- Team-scoped users see the opponent team's score form, because they submit spirit for the other team.
- Full-view users can switch between the two team submissions for the same game.
- Comment creation, comment deletion, and read-only review follow the same shared permission model as `user/addspirit.php`.
- If there is exactly one current event and one accessible team, authenticated Spiritkeeper opens the team game list directly.

## UI behavior

The Spiritkeeper UI is intentionally simple:

- it uses a standalone HTML shell in `spiritkeeper/index.php`,
- it uses the current locale/session bootstrap,
- it loads the shared customizable mobile stylesheet from `cust/<CUSTOMIZATIONS>/ultiorganizer-mobile.css`, falling back to `cust/default/ultiorganizer-mobile.css`,
- it renders category inputs dynamically from `SpiritCategories()`,
- the token submit page shows a live total,
- it shares the same base mobile layout and design language as scorekeeper.

If a category model uses more than a small radio range, the form falls back to numeric inputs with the configured `min` and `max`.

## Relationship to other spirit entry points

Spiritkeeper and `user/addspirit.php` now share the same canonical spirit model, but they are different interfaces:

- `user/addspirit.php` is the logged-in edit/review tool with role-aware rights and comment handling,
- `spiritkeeper/` is the mobile spirit app for token-based team submission and focused authenticated game editing.

This means:

- admin edits or deletions made in the logged-in UI affect what the token flow later shows,
- token submissions affect `show_spirit` and spirit averages the same way as logged-in submissions,
- token users can edit only their own outbound spirit note on the token submit page while submission is open,
- opponent spirit-note visibility remains limited to authenticated Spiritkeeper and the main logged-in spirit UI,
- spirit timeouts remain on score-entry surfaces because they are recorded during the game by officials,
- scorekeeper no longer exposes spirit entry pages.

## Current gaps

- There is no event-level configuration flag for token-specific reveal policy.
- The token flow does not expose opponent spirit notes.
- The token flow is still a separate standalone entrypoint rather than a routed `?view=...` page.
