# Spiritkeeper

`spiritkeeper/` is the mobile app for spirit entry and review, with two access modes:

- public token-based team submission (`/spiritkeeper/?token=...`)
- login for users who already hold spirit rights in the main application (`/spiritkeeper/?view=editgame&game=<id>`, optionally `&team=<responsibleTeamId>`)

It does not use the main `?view=...` router, but writes through the same spirit helpers and tables as `user/addspirit.php` (see `docs/spirit-scoring.md`). Spirit stoppages are recorded on the score-entry surfaces, not here.

## Pages

- `index.php`: shell, locale bootstrap, and login POSTs when no token is present. Loads `cust/<CUSTOMIZATIONS>/ultiorganizer-mobile.css` with fallback to `cust/default/`.
- `login.php`: login for direct authenticated links, with the shared mobile language flags.
- `home.php`: authenticated event and team selection.
- `teamgames.php`: game list in both modes.
- `submitsotg.php`: token score form, with a live total.
- `editgame.php`: authenticated game editor.

Category inputs are rendered from `SpiritCategories()`; a wide range falls back to numeric inputs with the configured `min` and `max`.

## Token mode

Tokens are stored in `uo_team.sotg_token` and resolved by `SpiritTeamIdByToken()`. They identify a team, not a user. Season admins and spirit admins generate and list the URLs in `admin/spirit.php`.

The token team rates its opponent, so `uo_spirit_score.team_id` is the opponent. `SpiritTokenGameRows()` lists the team's spirit-enabled games.

`SpiritTokenCanSubmit()` allows a submission only when the game belongs to the token team, `spiritmode > 0`, the event is not read-only, the game has started, the game is not a forfeit (a forfeit note is shown instead), and `lockteamspiritonsubmit` has not locked the team's submission. `SpiritTokenSaveSubmission()` validates against the active categories (zeros allowed), replaces the team's `uo_spirit_score` rows, and calls `RefreshGameSpiritData()`. It never writes the legacy `uo_spirit` table or the `homesotg`/`visitorsotg` columns.

Visibility:

- the team's own game list, and the score it gave after submitting
- the score it received only when both `SpiritTokenHasOwnSubmission()` and `SpiritTokenHasReceivedSubmission()` hold (`SpiritTokenCanViewReceivedPoints()`, not configurable)
- its own spirit note, editable while submission is open
- the note it received, under the same both-submitted condition, only when `showspiritcommentstoteams` is on

## Authenticated mode

Access uses the normal session and `UserAuthenticate()`, checked through `SpiritEntryTeamForUser()`, `HasFullGameSpiritViewRight()`, `CanEditSpiritSubmission()` and the spirit comment helpers, the same model as `user/addspirit.php`.

- Season and spirit admins choose from all teams in their spirit-enabled events; team admins from the teams they manage.
- With one current event and one accessible team, the team's game list opens directly.
- `team=<id>` is the submitting team, as in `SpiritEntryUrl()`. Team-scoped users see the opponent's score form; full-view users can switch between both submissions.

## Gaps

- The received-score reveal is hardcoded; only the received-comment reveal is configurable.
- The token flow is a standalone entrypoint rather than a routed `?view=...` page.
