# SOTG Token Flow

This page documents the public token-based SOTG flow under `sotg/`.

It is a thin standalone entrypoint for team-submitted spirit scores. It does not use the main `?view=...` router, but it now writes through the same spirit helpers and database model as the rest of the application.

## Core entrypoints

- `sotg/index.php`: public bootstrap and page shell for token-based SOTG.
- `sotg/teamgames.php`: game list for the token-owning team.
- `sotg/submitsotg.php`: spirit score form for one game.
- `sotg/sotg.functions.php`: small UI helpers for token parsing, category ordering, defaults, and display summaries.
- `lib/spirit.functions.php`: shared token-aware lookup, validation, save, and visibility helpers.

## URL and token model

- The public URL shape is `/sotg/?token=...`.
- Tokens are stored in `uo_team.sotg_token`.
- `SpiritTeamIdByToken()` resolves the token to the owning team.
- `admin/spirit.php` remains the place where season admins or spirit admins generate and list SOTG URLs.

The token identifies a team, not a user account. The public token flow therefore does not rely on logged-in rights checks from `user/addspirit.php`.

## Data model

The token flow uses the same current spirit storage model as the rest of the repository:

- scores are stored in `uo_spirit_score`,
- score categories come from `uo_spirit_category`,
- game-level public visibility is cached in `uo_game.show_spirit`,
- visibility/stat refresh is done through `RefreshGameSpiritData()`.

The token flow does not write to legacy `uo_spirit`, and it does not directly update cached `homesotg` or `visitorsotg` columns on `uo_game`.

## Team and game mapping

The token team is the team that owns the SOTG URL.

For a given game:

- the token team submits a score for the opponent team,
- the rated team stored in `uo_spirit_score.team_id` is therefore the opponent,
- the token team may later see the score they received only through the token-specific reveal rule.

`SpiritTokenGameRows()` returns all spirit-enabled games for the token-owning team.

## Submission rules

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

## Visibility behavior

The token flow is intentionally narrower than the logged-in spirit UI.

Current behavior:

- the token holder can always see the list of their own team games,
- after submitting, the token holder can see the score they gave,
- the token holder can see the score they received only when both conditions are true:
  `SpiritTokenHasOwnSubmission()` is true and `SpiritTokenHasReceivedSubmission()` is true,
- spirit comments are not shown in the public token flow.

This reveal behavior is implemented by `SpiritTokenCanViewReceivedPoints()`. It is currently hardcoded for the token flow and is not yet an event-level setting.

## UI behavior

The public token UI is intentionally simple:

- it uses a standalone HTML shell in `sotg/index.php`,
- it uses the current locale/session bootstrap,
- it renders category inputs dynamically from `SpiritCategories()`,
- it shows a live total on the submit page,
- it does not depend on jQuery Mobile or other removed legacy assets.

If a category model uses more than a small radio range, the form falls back to numeric inputs with the configured `min` and `max`.

## Relationship to logged-in spirit entry

The public token flow and `user/addspirit.php` now share the same canonical spirit model, but they are different interfaces:

- `user/addspirit.php` is the logged-in edit/review tool with role-aware rights and comment handling,
- `sotg/` is the public token tool for team-side submission and limited score review.

This means:

- admin edits or deletions made in the logged-in UI affect what the token flow later shows,
- token submissions affect `show_spirit` and spirit averages the same way as logged-in submissions,
- comment creation and comment visibility remain exclusive to the logged-in spirit flow.

## Current gaps

- There is no event-level configuration flag for token-specific reveal policy.
- The token flow does not expose spirit comments.
- The token flow is still a separate standalone entrypoint rather than a routed `?view=...` page.
