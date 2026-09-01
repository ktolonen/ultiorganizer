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
- **Snapshot rows** -- `target='snapshot'`, `action='capture'`, `has_snapshot=1`, with the full scoresheet state serialized into `snapshot` as JSON by `GameHistoryBuildSnapshot()`. These are the rows the admin and per-game history pages offer to restore. The `uo_game` fields captured include the defense counts (`homedefenses`, `visitordefenses`) and the live-clock columns (`timer_start`, `timer_pause_start`, `timer_paused_duration`, with `NULL` preserved faithfully) since format `v2`; a `v1` snapshot predates these and simply lacks the keys. `GameHistoryRestore()` treats the two groups differently on a `v1` restore: the defense counts are left untouched, since the guarded `GameSetDefenses()` call is skipped entirely when the keys are missing. The timer columns depend on which result branch the restore takes: `GameClearResult()` and `GameSetResult()` both NULL them unconditionally as part of their own write, so a `v1` restore into a cleared or final result loses the clock regardless; `GameUpdateResult()` (the still-ongoing branch) never touches them at all, so a `v1` restore into the ongoing state leaves the clock as it was. A `v2` snapshot's captured value is written back afterward in all three cases.

Recording can be turned off installation-wide with the `DisableGameHistory` setting; see `docs/configuration-flags.md`.

## Why snapshots are sparse

A snapshot is not taken on every write -- the scorekeeper saves one point at a time, and snapshotting each of those would mean roughly one snapshot per goal. Instead, `GameHistorySnapshotIfNeeded()` runs at the very start of essentially every other scoresheet mutator in `lib/game.functions.php` -- immediately after that mutator's own permission check, before its write -- capturing the state about to be overwritten so it can be recovered afterward. It is memoized per game for the duration of the request, so a desktop save that calls several of these mutators in sequence (scorekeeper name, halftime, starting offence, then a bulk roster/score/defense/timeout rewrite) still produces exactly one snapshot row for that game, not one per mutator. This applies even to a single-field change made on its own, such as setting only the halftime or only the scorekeeper name: an isolated change like that is itself the entire "save" a user can restore back out of, so it needs a restore point the same as a bulk rewrite does.

Excluded from this: `GameAddScore()`, `GameAddScoreEntry()` and `GameRemoveScore()`, the per-goal paths, which never call it -- see the comment on `GameRemoveScore()`. Those are still covered indirectly: `GameRemoveAllScores()`, called by the desktop save's bulk rewrite, snapshots before clearing the point sequence, so a save built from several per-goal calls still has exactly one restore point.

`GameUpdateResult()` (the "still ongoing" save) takes an optional `$snapshot` parameter, defaulting to `true`, so it snapshots like every other mutator by default -- a default-true is deliberate: a future caller that forgets to think about it still gets a restore point rather than silently losing one. The two callers that invoke it once per point -- `mobile/addscoresheet.php` and `scorekeeper/addscoresheet.php` -- pass `false` explicitly, since snapshotting on every point would mean roughly one snapshot per goal. `user/addscoresheet.php`'s call leaves the default: its bulk save already snapshots via `GameRemoveAllScores()`, and the per-request memo makes its own `GameUpdateResult()` call a no-op.

`SetGameComment()` (`lib/comment.functions.php`, game-type comments only) snapshots before applying the change, not after, so a standalone comment edit has its own restore point and a bulk save's later mutators do not snapshot an already-updated comment.

`GameHistoryRestore()` (`lib/gamehistory.functions.php`) force-captures the state it is about to replace as its own snapshot, even while history recording is otherwise suppressed for the duration of its own replay, so a restore can itself be undone. This force-capture, and the restore's own audit row, both bypass the `DisableGameHistory` setting as well as suppression: the setting governs routine recording volume, not the safety of an explicit destructive admin action, so restoring while recording is disabled must not become unrecoverable. Every other call site stays subject to the setting.

## Recording points

The full set of `target`/`action` combinations, and the mutator that writes each one:

| Target | Action | Mutator |
|---|---|---|
| `result` | `update` | `GameUpdateResult()`, `GameSetResult()`, `GameSyncResultFromGoals()` |
| `result` | `clear` | `GameClearResult()` |
| `forfeit` | `update` | `GameSetForfeit()` |
| `played` | `add` | `GameAddPlayer()`, `GameAddNewPlayer()` |
| `played` | `update` | `GameSetPlayerNumber()`, `GameSetRolePlayers()` (captain / spirit captain, called from `GameSetCaptains()` / `GameSetSpiritCaptains()`) |
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
| `snapshot` | `capture` | `GameHistoryWriteSnapshot()` |
| `restore` | `restore` | `GameHistoryRestore()` |

Because every one of the scoresheet input paths documented in `docs/scoresheet.md` (result-only entry, player-list entry, the detailed desktop scoresheet, mobile entry, scorekeeper entry) is built out of these same shared mutators, every one of those flows is covered: each save writes its usual rows to `uo_game`/`uo_played`/`uo_goal`/etc. *and* the matching history rows above, not just the former.

## Attribution

`GameHistorySource()` resolves the app that made the change from the `UO_APP_SOURCE` constant. `api/`, `scorekeeper/`, `spiritkeeper/`, and `mobile/index.php` (when reached directly) each define this constant themselves at their own entry point. The root `index.php`, which serves `user/` and `admin/` pages -- and `mobile/` pages when routed through it -- derives the value instead of hardcoding it: it takes the leading path segment of the resolved `?view=...` value, keeps it only if it is one of `admin`, `user`, or `mobile`, and otherwise falls back to `user`. This is why a forfeit set from `admin/editgame.php` (which calls the same `GameSetForfeit()` used everywhere else) is attributed to `admin`, and a point entered from `mobile/addscoresheet.php` is attributed to `mobile`, without either page passing that information itself. `GameHistorySource()` also falls back to matching `$_SERVER['SCRIPT_NAME']` against a fixed app list when `UO_APP_SOURCE` is undefined at all, a defensive path that is effectively unreachable since every entry point defines the constant.

## Viewing history

- `user/gamehistory.php` shows the change history for one game. It requires `hasEditGameEventsRight($gameId)` -- the same right needed to edit that game's scoresheet, so a team's own game admins can review their game's history without needing broader access. Rows with `has_snapshot=1` get a "Show" link (renders the point sequence from the snapshot) and a "Restore this version" action.
- `admin/gamehistory.php`, titled "Scoresheet history" in the UI, is an installation-wide log across all games, with filters by season, game, user, and date range. It is restricted to `isSuperAdmin()` in both its data queries (`GameHistoryAll()`, `GameHistoryAllCount()`) and its menu link, so a season or team admin who can reach the per-game page cannot browse other teams' history through this one.

## Restoring a snapshot

`GameHistoryRestore($historyId)` rebuilds a game's scoresheet from a snapshot row's `snapshot` JSON. The contract:

- **Guards run before anything is touched.** The replay calls mutators guarded by three different rights: `GameAddPlayer()` / `GameAddNewPlayer()` need `hasEditGamePlayersRight()`, most of the rest (including `GameSetForfeit()`, called last) need `hasEditGameEventsRight()`, and restoring an `acknowledged` roster flag needs `hasAccredidationRight()` for any team that actually has an acknowledged player in the snapshot -- it is still an accreditation mutation even though it no longer goes through `AcknowledgeUnaccredited()` (see below). `GameHistoryRestore()` checks all of these up front and refuses to start if any are missing. This guard set has to stay a superset of every mutator's own check, because the replay does not run inside a transaction: `lib/database.php` offers no transaction-helper API, and while raw `START TRANSACTION`/`COMMIT`/`ROLLBACK` are used elsewhere in the codebase (e.g. `lib/privacy.functions.php`), the restore replay deliberately does not use one, so a `die()` partway through a bulk-rewriting mutator would leave the scoresheet in a mixed state with no rollback. Checking every needed right before starting is the only thing standing between "restore" and a half-applied game.
- **The replay goes through the ordinary mutators for everything except the roster**, not raw SQL, so that `ResolvePoolStandings()` and `PoolResolvePlayed()` still run as a side effect of the replayed result/forfeit calls, keeping pool standings consistent with the restored scoresheet the same as after a normal edit, and `GameSetDefenses()` restores the aggregate defense counts the same way. `RefreshGameSpiritData()` is called explicitly once the replay finishes, so spirit visibility and cached team statistics also reflect the restored state. History recording is suppressed for the duration of the replay (`GameHistorySuppressed()`), except that the pre-restore state is still force-captured as its own snapshot first, so a restore can itself be undone -- and unlike every other snapshot capture and change row, that force-capture and the restore's own audit row both ignore the `DisableGameHistory` setting too (see "Why snapshots are sparse" above).
- **The roster is written directly, deliberately bypassing `GameAddPlayer()`'s accreditation gate.** `GameHistoryRestorePlayers()` calls `GameRemoveAllPlayers()` first, which destroys `GameAllowsPlayerOnRoster()`'s "already on this game's roster" exception -- so in a `require_accreditation` season, routing the rebuild back through `GameAddPlayer()` would reject an unaccredited player even when the snapshot recorded `acknowledged=1`, dropping them from the restored roster. Instead, each row (jersey number, `captain`, `spirit_captain`, `accredited`, `acknowledged`) is inserted straight into `uo_played`: the snapshot is evidence the player was legitimately on that roster, and the accreditation right for every acknowledged team is already checked up front, so restore does not re-litigate the gate. One consequence: restoring no longer calls `AcknowledgeUnaccredited()`, so it no longer writes a `uo_accreditationlog` row for a restored acknowledgment, and it no longer needs (or has) a per-player "moved teams since the snapshot" re-check -- the up-front team-level guard is the only accreditation check restore performs.
- **Players are rematched by jersey number when the id no longer exists.** `uo_goal` sets both player foreign keys to `NULL` on delete, so a player removed from the roster since the snapshot was taken cannot be resolved by id. `GameHistoryRestorePlayers()` falls back to looking up a player on the same team with the same jersey number; if that also fails, the player is skipped and reported as a warning rather than silently dropped. This is the only remaining way a restored player produces a warning -- there is no longer an accreditation-based warning path, since the direct roster write above has nothing left to reject.
- **`uo_gameevent` rows are rebuilt, not just upserted, for the types the replay can reinstate.** `GameSetCapEvent()` is upsert-only and nothing else removed stale `uo_gameevent` rows before replay, so a cap set after the snapshot would otherwise survive a restore untouched. `GameRemoveAllGameEvents()` deletes the starting-offence and cap-event rows before the replay loop re-adds only what the snapshot actually had. It is deliberately narrower than "every non-media row": other event types (e.g. `turnover`) are captured in the snapshot but have no replay branch, so widening the delete to them would destroy data the replay could never put back.
- **Media events are deliberately excluded.** `GameHistoryBuildSnapshot()` excludes `uo_gameevent` rows of type `media` because media links are guarded by `hasAddMediaRight()`, a different right from the ones checked above, and a restore must not add or remove them.
- **`IsPoolLocked()` and `IsSeasonStatsCalculated()` do not block a restore.** They are only ever turned into warning text elsewhere in the app (by `CheckGameResult()`, for ordinary result edits), never enforced by the mutators themselves, so blocking on them here would make a restore stricter than an ordinary edit. `GameHistoryRestore()` reuses the same warning wording and surfaces both conditions as warnings the operator sees after restoring, instead of refusing the restore.

Two effects of a restore are easy to miss because neither is visible from reading the restore code in isolation:

- **Restoring a roster rewrites `uo_player.num`.** `GameAddPlayer()` updates both the per-game jersey number (`uo_played.num`) and the team roster number (`uo_player.num`) on every call, and `GameHistoryRestorePlayerRow()` (the direct-write path used by restore, see above) preserves the same side effect. Restoring an old roster snapshot therefore also changes the player's current roster number outside that game, not just their number for the restored game.
- **A restore does not run inside a transaction.** As above: `lib/database.php` has no transaction-helper API, raw transactions are possible and used elsewhere in the codebase, and the restore replay deliberately does not use one. The safety of a restore rests entirely on `GameHistoryRestore()` checking every right its replay will need before it writes anything, not on any ability to roll back partway through.

## Retention

Rows are removed only by cascade: deleting the `uo_game` row a history row belongs to deletes that row too, through the `fk_game_history_game` foreign key (`ON DELETE CASCADE`). This fires whenever a game is deleted, whether through the single-game `DeleteGame()` or the bulk event-data cleanup in `lib/data.functions.php`. There is no age-based pruning job and no delete control in either history-viewing page: history for a game that still exists is kept indefinitely, and nothing removes it on its own schedule.

## Privacy

- The player and registered-user data export (`lib/privacy.functions.php`) includes a user's own `uo_game_history` rows but excludes the `snapshot` column, since a snapshot describes the game's full state, including other players' names, not only the exporting user's data.
- Deleting a registered user's data anonymizes their `uo_game_history` rows (`user_id` set to `-`, `ip` cleared) rather than deleting them: the row is the game's change history, not solely that user's data, and it is removed only when its game is (see Retention above).
- Anonymizing a player rewrites the player's name wherever it is embedded as free text inside a snapshot's JSON -- `played[].name`, `goals[].scorer_name`, `goals[].assist_name` -- keyed by player id, since these fields are not foreign keys and anonymizing `uo_player` does not reach them on its own.

See `docs/privacy.md` for the full export, anonymization, and deletion behavior across all tables.
