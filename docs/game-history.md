# Scoresheet Change History

This document describes the `uo_game_history` table: what it records, how a change is attributed, how a recorded state is restored, and how it is covered by privacy tooling. See `docs/scoresheet.md` for the scoresheet concept and the entry flows that write to it.

## What is stored

Every mutation to a game's scoresheet -- result, roster, points, defenses, timeouts, spirit stoppages, metadata, comments, cap events, and media links -- is recorded as a row in `uo_game_history`, in addition to the ordinary write to `uo_game`, `uo_played`, `uo_goal`, and the other scoresheet tables. The row carries:

- `game`: the game the change belongs to (foreign key to `uo_game`, cascades on delete),
- `time`: when the change happened,
- `user_id`, `ip`: who made it,
- `source`: which app made it (see "Attribution" below),
- `target`, `action`: what was changed and how, e.g. `goal`/`add`, `played`/`clear`, `result`/`update`,
- `detail`: a small JSON payload describing the change (an added player id, a removed-count, a new result), used to render a human-readable description via `GameHistoryFormatDetail()`,
- `has_snapshot`, `snapshot`: present only on the second row kind, described next.

There are two kinds of rows in the same table:

- **Change rows** -- one per mutation, written by `GameHistoryRecord()`. These exist purely as an audit trail: who changed what, when, from where. They are not restorable on their own.
- **Snapshot rows** -- `target='snapshot'`, `action='capture'`, `has_snapshot=1`, with the full scoresheet state serialized into `snapshot` as JSON by `GameHistoryBuildSnapshot()`. These are the rows the admin and per-game history pages offer to restore.

Recording can be turned off installation-wide with the `DisableGameHistory` setting; see `docs/configuration-flags.md`.

### Snapshot formats

`GameHistoryBuildSnapshot()` stamps a format version in the snapshot's `v` key. Older snapshots stay restorable, so `GameHistoryRestore()` keys every field it replays on the key actually being present.

| Version | Adds | Restore behavior for an older snapshot |
|---|---|---|
| `v1` | the base scoresheet: result, roster, goals, defenses, timeouts, events, comment | -- |
| `v2` | `homedefenses`, `visitordefenses`, and the live-clock columns `timer_start`, `timer_pause_start`, `timer_paused_duration` | a `v1` restore leaves the defense counts alone, and leaves the clock to whatever the replayed result call did to it |
| `v3` | `timer_elapsed`, the game time `GameTimerState()` reports as already elapsed at capture | a `v2` restore writes `timer_start` back verbatim, and since it is an absolute Unix epoch, a clock that was running counts the time since capture as game time. A `v1` restore is unaffected: with no `timer_start` key at all, the whole timer write-back is skipped |
| `v4` | `hometeam`, `visitorteam` (`NULL`-preserving, since a fixture side can be unassigned) | a pre-`v4` mismatch cannot be detected, so the restore guard under "Restoring a snapshot" does not apply |

From `v3` on, the result replay derives a fresh `timer_start` of `now - timer_elapsed` instead of replaying the captured epoch, freezing it immediately via `timer_pause_start = now` when the snapshot was paused.

## Why snapshots are sparse

A snapshot is not taken on every write -- the scorekeeper saves one point at a time, and snapshotting each of those would mean roughly one snapshot per goal. Instead, `GameHistorySnapshotIfNeeded()` runs at the start of essentially every other scoresheet mutator in `lib/game.functions.php`, immediately after that mutator's own permission check and before its write, capturing the state about to be overwritten. It is memoized per game for the duration of the request, so a desktop save that calls several of these mutators in sequence still produces exactly one snapshot row. A single-field change made on its own -- setting only the halftime, or only the scorekeeper name -- gets its own snapshot the same way, since that change is itself the entire save a user can restore back out of.

The exceptions:

- **The per-goal paths.** `GameAddScore()`, `GameAddScoreEntry()` and `GameRemoveScore()` never snapshot. They are covered indirectly: the desktop save's bulk rewrite goes through `GameRemoveAllScores()`, which snapshots before clearing the point sequence.
- **`GameUpdateResult()`** takes an optional `$snapshot` parameter, defaulting to `true`. The two callers that invoke it once per point -- `mobile/addscoresheet.php` and `scorekeeper/addscoresheet.php` -- pass `false`. `user/addscoresheet.php` leaves the default, and the per-request memo makes its call a no-op after the bulk save's own snapshot.
- **The five `GameTime*()` clock mutators.** They record a `timer` change row but never snapshot. Restore is whole-sheet, so such a restore point would differ only in the clock, and using it to undo a mistaken reset would also roll back goals, roster and result. The clock columns are still captured by every other mutator's snapshot, so an unrelated restore does not destroy them -- they are just not independently restorable. The remedy for a clock mistake is `GameTimeSetElapsed()`.

`SetGameComment()` (`lib/comment.functions.php`, game-type comments only) snapshots before applying the change, so a standalone comment edit has its own restore point and a later mutator in a bulk save does not snapshot an already-updated comment.

`GameHistoryRestore()` force-captures the state it is about to replace, so a restore can itself be undone. That capture and the restore's own audit row bypass both the replay suppression and the `DisableGameHistory` setting: the setting governs routine recording volume, not the recoverability of an explicit destructive action. Every other call site stays subject to it.

## Recording points

The full set of `target`/`action` combinations, and the mutator that writes each one:

| Target | Action | Mutator |
|---|---|---|
| `result` | `update` | `GameUpdateResult()`, `GameSetResult()`, `GameSyncResultFromGoals()` |
| `result` | `clear` | `GameClearResult()` |
| `forfeit` | `update` | `GameSetForfeit()` |
| `played` | `add` | `GameAddPlayer()`, `GameAddNewPlayer()` |
| `played` | `update` | `GameSetPlayerNumber()`, `GameSetRolePlayers()` (captain / spirit captain, called from `GameSetCaptains()` / `GameSetSpiritCaptains()`), `AcknowledgeUnaccredited()` / `UnAcknowledgeUnaccredited()` (`lib/accreditation.functions.php`, accreditation acknowledgment) |
| `played` | `remove` | `GameRemovePlayer()` |
| `played` | `clear` | `GameRemoveAllPlayers()` |
| `goal` | `add` | `GameAddScore()`, `GameAddScoreEntry()` |
| `goal` | `remove` | `GameRemoveScore()` |
| `goal` | `clear` | `GameRemoveAllScores()` |
| `defense` | `add` | `GameAddDefense()` |
| `defense` | `update` | `GameSetDefenses()` |
| `defense` | `clear` | `GameRemoveAllDefenses()` |
| `timeout` | `add` | `GameAddTimeout()` |
| `timeout` | `clear` | `GameRemoveAllTimeouts()` |
| `spirit_timeout` | `add` | `GameAddSpiritTimeout()` |
| `spirit_timeout` | `clear` | `GameRemoveAllSpiritTimeouts()` |
| `gameevent` | `update` | `GameSetCapEvent()`, `GameSetStartingTeam()` |
| `gameevent` | `remove` | `GameRemoveCapEvent()`, `GameSetStartingTeam()` (unsetting) |
| `mediaevent` | `add` | `AddGameMediaEvent()` |
| `mediaevent` | `remove` | `RemoveGameMediaEvent()` |
| `official` | `update` | `GameSetScoreSheetKeeper()` |
| `halftime` | `update` | `GameSetHalftime()` |
| `comment` | `update` / `remove` | `SetGameComment()` (`lib/comment.functions.php`, game-type comments only) |
| `timer` | `start` / `pause` / `resume` / `reset` / `update` | `GameTimeStart()`, `GameTimePause()`, `GameTimeResume()`, `GameTimeReset()`, `GameTimeSetElapsed()` |
| `snapshot` | `capture` | `GameHistorySnapshotIfNeeded()` |
| `restore` | `restore` | `GameHistoryRestore()` |

Because every scoresheet input path documented in `docs/scoresheet.md` (result-only entry, player-list entry, the detailed desktop scoresheet, mobile entry, scorekeeper entry) is built out of these same shared mutators, every one of those flows is covered.

## Authorization

Every mutator above checks its own permission before calling `GameHistoryRecord()` or `GameHistorySnapshotIfNeeded()`, but neither helper enforced anything itself, so a future caller could have forged an audit row or captured a snapshot for an arbitrary game. Both now call `GameHistoryAuthorized($gameId, $target)` after the disabled-setting, suppression and `$gameId` checks.

It accepts the union of the rights the callers in the table above actually hold. `hasEditGameEventsRight($gameId)` and `hasEditGamePlayersRight($gameId)` apply to any target. The others are each scoped to the one target they are granted for, so a caller holding only that right cannot reach the helpers directly and forge rows for another target:

| Right | Scoped to | Callers |
|---|---|---|
| `hasAddMediaRight()` | `mediaevent` | `AddGameMediaEvent()`, `RemoveGameMediaEvent()` |
| `hasAccredidationRight()` | `played` | `AcknowledgeUnaccredited()`, `UnAcknowledgeUnaccredited()` |
| `CanManageGameComment()` | `comment` | `SetGameComment()` |
| `ANONYMOUS_RESULT_INPUT` | `result` | `GameSetResult()` |

`hasAddMediaRight()` needs the scope most: unlike the others it carries no game or team scope at all, and any logged-in session holds it. It is also the only one of the four whose callers never snapshot, and correspondingly the only target `GameHistorySnapshotIfNeeded()` is never called with -- `SetGameComment()` passes `comment`, the accreditation helpers pass `played`, and the anonymous `GameSetResult()` route passes `result`, so those three branches do apply to a snapshot capture.

The accreditation branch also accepts the right against the current team of any player on the game's roster, not only the game's own two teams. `AcknowledgeUnaccredited()` authorizes against the player's current team, so an admin of the new team legitimately acknowledges a player who has since transferred, and checking only the fixture's teams would let that acknowledgement succeed while silently refusing to record it.

### The anonymous self-report route

`result.php` and `scorekeeper/result.php` call `GameSetResult($gameId, $home, $away, true, false)` -- `$checkRights=false` -- for the by-game-ID result entry that `ANONYMOUS_RESULT_INPUT` (see `docs/configuration-flags.md`) exists to support. The submitter holds none of the rights above, so without a separate signal the change would go unrecorded, which is the opposite of what an unattributed change needs. `GameSetResult()` therefore passes `$allowAnonymousResult=true` down whenever it was itself called with `$checkRights=false`, and `GameHistoryAuthorized()` grants access on that basis only after independently confirming the installation's `ANONYMOUS_RESULT_INPUT` constant -- so the same caller on an installation where the setting is off still hits the ordinary checks and is refused.

A row recorded this way with no session stores `user_id` as the literal string `anonymous` rather than the usual `unknown`, so the history pages show the unattributed origin distinctly.

### Game notes edited by their author

`CanManageGameComment()` lets the original author of a game note update or delete it after they have lost `hasEditGameEventsRight()`. That write is legitimate and must be recorded, but passes none of the ordinary checks, hence the `comment` branch.

Authorship is resolved server-side rather than passed in, which the delete path makes awkward: `ApplyCommentChange()` logs a `comment_delete` event, `GameCommentMeta()` treats the newest such event as a cutoff and looks for a `comment_create` after it, so once the delete is applied the author is no longer recognisable. `SetGameComment()` therefore makes **both** history calls before `ApplyCommentChange()`. Nothing observable changes: `ApplyCommentChange()` returns `true` in every branch and its result was never gated on.

The create path needs none of this -- `CanCreateGameComment()` still requires `hasEditGameEventsRight()`.

## Attribution

`GameHistorySource()` resolves the app that made the change from the `UO_APP_SOURCE` constant. `api/`, `scorekeeper/`, `spiritkeeper/`, and `mobile/index.php` (when reached directly) each define it at their own entry point. The root `index.php`, which serves `user/` and `admin/` pages -- and `mobile/` pages when routed through it -- derives it instead: it takes the leading path segment of the resolved `?view=...` value, keeps it only if it is `admin`, `user`, or `mobile`, and otherwise falls back to `user`. This is why a forfeit set from `admin/editgame.php` is attributed to `admin` and a point entered from `mobile/addscoresheet.php` to `mobile`, without either page passing that information. `GameHistorySource()` also falls back to matching `$_SERVER['SCRIPT_NAME']`, a defensive path that is effectively unreachable.

`ip` is left empty when `DisableVisitorLogging` is set, on every row: that setting stops IP recording entirely (see `docs/privacy.md`).

## Viewing history

- `user/gamehistory.php` shows the change history for one game. It requires `hasEditGameEventsRight($gameId)` -- the same right needed to edit that game's scoresheet, so a team's own game admins can review their game's history without broader access. Rows with `has_snapshot=1` get a "Show" link (renders the point sequence from the snapshot) and a "Restore this version" action.
- `admin/gamehistory.php`, titled "Scoresheet history" in the UI, is an installation-wide log across all games, with filters by season, game, user, and date range. It is restricted to `isSuperAdmin()` in both its data queries (`GameHistoryAll()`, `GameHistoryAllCount()`) and its menu link, so a season or team admin who can reach the per-game page cannot browse other teams' history through this one.

A snapshot whose recorded teams are no longer the game's teams is withheld from reads. `hasEditGameEventsRight()` resolves through the game's current series, responsible team and reservation, all of which `SetGame()` can change, so an admin can gain rights over a game after it has moved -- and would otherwise read a roster and scorer names belonging to the previous fixture. `GameHistoryRestore()` is the only caller that opts in to receiving a mismatched snapshot, because it reports the mismatch as a specific refusal.

Change rows other than snapshots stay readable after a move. They describe this game, which the admin now legitimately administers, and the `user_id` they expose is ordinary audit visibility between admins of the same game.

## Restoring a snapshot

`GameHistoryRestore($historyId)` rebuilds a game's scoresheet from a snapshot row's `snapshot` JSON.

**The replay is not transactional.** `lib/database.php` offers no transaction-helper API; raw `START TRANSACTION`/`COMMIT`/`ROLLBACK` are used elsewhere in the codebase (e.g. `lib/privacy.functions.php`), but the restore replay does not use one. A `die()` partway through a bulk-rewriting mutator would leave the scoresheet in a mixed state with no rollback, so the safety of a restore rests entirely on checking every right the replay will need before it writes anything. The rest of the contract follows from that:

- **Guards run before anything is touched.** The replay calls mutators guarded by three different rights: `GameAddPlayer()` / `GameAddNewPlayer()` need `hasEditGamePlayersRight()`, most of the rest (including `GameSetForfeit()`, called last) need `hasEditGameEventsRight()`, and restoring an `acknowledged` roster flag needs `hasAccredidationRight()` for every team with an acknowledged player in the snapshot -- it is still an accreditation mutation even though it no longer goes through `AcknowledgeUnaccredited()`. `GameHistoryRestore()` checks all of these up front and refuses to start if any are missing.
- **The replay goes through the ordinary mutators for everything except the roster**, not raw SQL, so `ResolvePoolStandings()` and `PoolResolvePlayed()` still run as a side effect of the replayed result and forfeit calls and pool standings stay consistent with the restored scoresheet. `GameSetDefenses()` restores the aggregate defense counts the same way, and `RefreshGameSpiritData()` is called once the replay finishes. History recording is suppressed for the duration of the replay (`GameHistorySuppressed()`), except for the pre-restore capture and the restore's own audit row.
- **The roster is written directly, bypassing `GameAddPlayer()`'s accreditation gate.** `GameHistoryRestorePlayers()` calls `GameRemoveAllPlayers()` first, which destroys `GameAllowsPlayerOnRoster()`'s "already on this game's roster" exception -- so in a `require_accreditation` season, routing the rebuild back through `GameAddPlayer()` would drop an unaccredited player from the restored roster even when the snapshot recorded `acknowledged=1`. Each row (jersey number, `captain`, `spirit_captain`, `accredited`, `acknowledged`) is inserted straight into `uo_played` instead: the snapshot is evidence the player was legitimately on that roster. One consequence is that a restored acknowledgment no longer writes a `uo_accreditationlog` row.
- **Each acknowledgment is rechecked against the player's current team.** The up-front guard only covers the teams the snapshot recorded, so the restore loop resolves each about-to-be-acknowledged player's current team (post-rematch) and rechecks `hasAccredidationRight()` before writing `acknowledged=1`. A player who moved teams, restored by an admin holding the right only on the old team, gets `acknowledged=0` and a warning rather than a silently granted acknowledgment on the new team. This does not abort the restore.
- **Players are rematched by jersey number when the id no longer exists, but only when the match is unique.** `uo_goal` sets both player foreign keys to `NULL` on delete, so a player removed since the snapshot cannot be resolved by id; `GameHistoryRestorePlayers()` falls back to a player on the same team with the same jersey number. `uo_player` has no unique constraint on `(team, num)`, so a non-unique match would silently attribute the snapshot's goals, assists and defenses to the wrong person and rewrite their roster number -- the rematch is skipped and warned about instead, the same as when no candidate is found. Two ambiguity cases are handled beyond that: two snapshot rows sharing `(team, num)` after both players were deleted would otherwise both rematch onto the same survivor, so a pre-scan warns on every row in such a group rather than letting loop order pick a winner; and a candidate already claimed by another row -- including a row whose own id still exists -- cannot be reused.
- **A player with no jersey number is never rematched.** Both `uo_player.num` and `uo_played.num` are nullable and 0 is a jersey a player may actually wear, so a null number is written back as SQL `NULL` rather than 0, on the `uo_played` row and on the player's global `uo_player` row. A deleted player with no number has nothing to match on -- casting null to 0 in the lookup would resolve them onto whoever wears number 0 -- so they get the ordinary "could not be restored" warning.
- **`uo_gameevent` rows are rebuilt, not just upserted, for the types the replay can reinstate.** `GameSetCapEvent()` is upsert-only and nothing else removed stale rows before replay, so a cap set after the snapshot would otherwise survive a restore. `GameRemoveAllGameEvents()` deletes the starting-offence and cap-event rows before the replay loop re-adds what the snapshot had. It is narrower than "every non-media row": other event types (e.g. `turnover`) are captured in the snapshot but have no replay branch, so widening the delete would destroy data the replay could never put back.
- **Media events are excluded.** `GameHistoryBuildSnapshot()` excludes `uo_gameevent` rows of type `media`, because media links are guarded by `hasAddMediaRight()` rather than by the rights checked above, and a restore must not add or remove them.
- **`IsPoolLocked()` and `IsSeasonStatsCalculated()` do not block a restore.** They are only ever turned into warning text elsewhere in the app (by `CheckGameResult()`, for ordinary result edits) and never enforced by the mutators themselves, so blocking on them here would make a restore stricter than an ordinary edit. `GameHistoryRestore()` reuses the same wording and surfaces both as warnings instead.
- **A `hometeam`/`visitorteam` mismatch does block a restore.** `SetGame()` (reassignment) and `GameChangeHome()` (swap) are not scoresheet mutators and never snapshot, so a snapshot taken before either is a scoresheet for a fixture the game no longer represents; replaying it would write roster, goal and defense rows for teams that are not in the game, and invert `ishomegoal` semantics on a swap. That is corruption rather than a policy condition, so the restore is refused. The comparison is positional (`hometeam` to `hometeam`), since a set comparison would miss a swap. A pre-`v4` snapshot has neither key and restores as before.

One effect is easy to miss: **restoring a roster rewrites `uo_player.num`**, the team roster number, not only the per-game `uo_played.num`. `GameAddPlayer()` updates both on every call and the direct-write path preserves that, so restoring an old roster snapshot also changes the player's current roster number outside the game. It is skipped for a player who has transferred since the snapshot: that column then belongs to their current team, which the restoring admin may hold no rights over. `uo_played.num` is restored either way.

## Retention

Rows are removed only by cascade: deleting the `uo_game` row a history row belongs to deletes that row too, through the `fk_game_history_game` foreign key (`ON DELETE CASCADE`). This fires whenever a game is deleted, whether through the single-game `DeleteGame()` or the bulk event-data cleanup in `lib/data.functions.php`. There is no age-based pruning job and no delete control in either history-viewing page.

## Privacy

- The registered-user data export (`lib/privacy.functions.php`) includes a user's own `uo_game_history` rows but excludes the `snapshot` column, since a snapshot describes the game's full state, including other players' names.
- The player data export projects a player's own name values out of every snapshot's `played[].name` / `goals[].scorer_name` / `goals[].assist_name` -- `PrivacyPlayerGameHistoryNameRows()` walks the same shape `PrivacyAnonymizePlayer()` rewrites and keeps only the entries keyed to that player's id, tagged with the game and snapshot time. This is how a prior spelling of a player's name, retained in an old snapshot but no longer present in `uo_player`, still reaches their report.
- Deleting a registered user's data anonymizes their `uo_game_history` rows (`user_id` set to `-`, `ip` cleared) rather than deleting them: the row is the game's change history, not solely that user's data.
- Anonymizing a player rewrites the player's name wherever it is embedded as free text inside a snapshot's JSON, keyed by player id, since those fields are not foreign keys and anonymizing `uo_player` does not reach them.

See `docs/privacy.md` for the full export, anonymization, and deletion behavior across all tables.
