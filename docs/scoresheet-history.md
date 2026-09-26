# Scoresheet Change History

This document describes the `uo_scoresheet_history` table: what it records, how a change is attributed, how a recorded state is restored, and how it is covered by privacy tooling. See `docs/scoresheet.md` for the scoresheet concept and the entry flows that write to it.

## What is stored

Every mutation made through the scoresheet mutators -- result, roster, points, defenses, timeouts, spirit stoppages, metadata, comments, cap events, and media links -- is recorded as a row in `uo_scoresheet_history`, in addition to the ordinary write to `uo_game`, `uo_played`, `uo_goal`, and the other scoresheet tables. The row carries:

- `game`: the game the change belongs to (foreign key to `uo_game`, cascades on delete),
- `time`: when the change happened,
- `user_id`, `ip`: who made it,
- `source`: which app made it (see "Attribution" below),
- `target`, `action`: what was changed and how, e.g. `goal`/`add`, `played`/`clear`, `result`/`update`,
- `detail`: a small JSON payload describing the change (an added player id, a removed-count, a new result), used to render a human-readable description via `ScoresheetHistoryFormatDetail()`. A payload carries the values that describe the change itself, not only the row it names: a cap event records its time and its point-cap target, so a corrected target is visible in the log rather than reading as an unexplained `Time cap`. The row's own snapshot cannot supply them, because it is captured before the change,
- `has_snapshot`, `snapshot`: present only on the second row kind, described next.

There are two kinds of rows in the same table:

- **Change rows** -- one per mutation, written by `ScoresheetHistoryRecord()`. These exist purely as an audit trail: who changed what, when, from where. They are not restorable on their own.
- **Snapshot rows** -- `target='snapshot'`, `action='capture'`, `has_snapshot=1`, with the full scoresheet state serialized into `snapshot` as JSON by `ScoresheetHistoryBuildSnapshot()`. These are the rows the admin and per-game scoresheet history pages offer to restore.

Tournament administration writes outside those mutators are not recorded. Swiss pool generation and move confirmation assign the standard BYE result with two bulk `UPDATE`s in `CheckBYE()` (`lib/swissdraw.functions.php`), and pool moves fill the real teams into scheduled fixtures with bulk updates in `lib/pool.functions.php` and `lib/team.functions.php`; none of them produces a history row. Those results and fixtures follow from the pool's own settings and move table rather than from someone editing a game, so the pool is the record of them.

Recording can be turned off installation-wide with the `DisableScoresheetHistory` setting; see `docs/configuration-flags.md`.

### Snapshot formats

`ScoresheetHistoryBuildSnapshot()` stamps a format version in the snapshot's `v` key. Older snapshots stay restorable, so `ScoresheetHistoryRestore()` keys every field it replays on the key actually being present.

| Version | Adds | Restore behavior for an older snapshot |
|---|---|---|
| `v1` | the base scoresheet: result, roster, goals, defenses, timeouts, events, comment | -- |
| `v2` | `homedefenses`, `visitordefenses`, and the live-clock columns `timer_start`, `timer_pause_start`, `timer_paused_duration` | a `v1` restore leaves the defense counts alone, and leaves the clock to whatever the replayed result call did to it |
| `v3` | `timer_elapsed`, the game time `GameTimerState()` reports as already elapsed at capture | a `v2` restore writes `timer_start` back verbatim, and since it is an absolute Unix epoch, a clock that was running counts the time since capture as game time. A `v1` restore is unaffected: with no `timer_start` key at all, the whole timer write-back is skipped |
| `v4` | `hometeam`, `visitorteam` (`NULL`-preserving, since a fixture side can be unassigned) | a pre-`v4` mismatch cannot be detected, so the restore guard under "Restoring a snapshot" does not apply |

From `v3` on, the result replay derives a fresh `timer_start` of `now - timer_elapsed` instead of replaying the captured epoch, freezing it immediately via `timer_pause_start = now` when the snapshot was paused.

## Why snapshots are sparse

A snapshot is not taken on every write -- the scorekeeper saves one point at a time, and snapshotting each of those would mean roughly one snapshot per goal. Instead, `ScoresheetHistorySnapshotIfNeeded()` runs at the start of essentially every other scoresheet mutator in `lib/game.functions.php`, immediately after that mutator's own permission check and before its write, capturing the state about to be overwritten. It is memoized per game for the duration of the request, so a desktop save that calls several of these mutators in sequence still produces exactly one snapshot row. A capture identical to the game's latest stored snapshot is not stored again; the existing row is reused, so re-saving an unchanged scoresheet adds no restore point. The pre-restore capture is exempt. A single-field change made on its own -- setting only the halftime, or only the scorekeeper name -- gets its own snapshot the same way, since that change is itself the entire save a user can restore back out of.

The exceptions:

- **The per-goal paths.** `GameAddScore()`, `GameAddScoreEntry()` and `GameRemoveScore()` never snapshot. They are covered indirectly: the desktop save's bulk rewrite goes through `GameRemoveAllScores()`, which snapshots before clearing the point sequence.
- **`GameUpdateResult()`** takes an optional `$snapshot` parameter, defaulting to `true`. The two callers that invoke it once per point -- `mobile/addscoresheet.php` and `scorekeeper/addscoresheet.php` -- pass `false`. `user/addscoresheet.php` leaves the default, and the per-request memo makes its call a no-op after the bulk save's own snapshot.
- **The fixture mutators.** `GameChangeHome()` and `SetGame()` record a `fixture` row but never snapshot. The state captured before either would record the outgoing `hometeam`/`visitorteam`, so `ScoresheetHistoryEntry()` would withhold it as a fixture mismatch the moment the write lands -- a restore point dead on arrival. `SetGame()` records only when a team column actually changed, since the same call also saves time, reservation and live-stream fields; it compares a read-back of the row rather than `$params`. It records a separate `fixture`/`move` row when the pool changes, guarded the same way against `admin/editgame.php` posting the current pool on every save. Both are written before `SetGamePool()` runs, because that call can carry the game into another series -- and `GameSeries()`, which every right the history helpers resolve is read from, follows it.
- **The five `GameTime*()` clock mutators.** They record a `timer` change row but never snapshot. Restore is whole-sheet, so such a restore point would differ only in the clock, and using it to undo a mistaken reset would also roll back goals, roster and result. The clock columns are still captured by every other mutator's snapshot, so an unrelated restore does not destroy them -- they are just not independently restorable. The remedy for a clock mistake is `GameTimeSetElapsed()`.

`SetGameComment()` (`lib/comment.functions.php`, game-type comments only) snapshots before applying the change, so a standalone comment edit has its own restore point and a later mutator in a bulk save does not snapshot an already-updated comment.

`ScoresheetHistoryRestore()` force-captures the state it is about to replace, so a restore can itself be undone. That capture and the restore's own audit row bypass both the replay suppression and the `DisableScoresheetHistory` setting: the setting governs routine recording volume, not the recoverability of an explicit destructive action. Every other call site stays subject to it.

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
| `gameevent` | `clear` | `GameRemoveAllGameEvents()` |
| `mediaevent` | `add` | `AddGameMediaEvent()` |
| `mediaevent` | `remove` | `RemoveGameMediaEvent()`, `RemoveMediaUrl()` |
| `fixture` | `swap` | `GameChangeHome()` |
| `fixture` | `update` | `SetGame()`, when it changes `hometeam` or `visitorteam` |
| `fixture` | `move` | `SetGame()`, when it changes the game's pool |
| `official` | `update` | `GameSetScoreSheetKeeper()` |
| `halftime` | `update` | `GameSetHalftime()` |
| `comment` | `update` / `remove` | `SetGameComment()` (`lib/comment.functions.php`, game-type comments only) |
| `timer` | `start` / `pause` / `resume` / `reset` / `update` | `GameTimeStart()`, `GameTimePause()`, `GameTimeResume()`, `GameTimeReset()`, `GameTimeSetElapsed()` |
| `snapshot` | `capture` | `ScoresheetHistorySnapshotIfNeeded()` |
| `restore` | `restore` | `ScoresheetHistoryRestore()` |

Three of these mutators cannot let the statement decide whether anything changed, and read the current state first instead. `GameSetRolePlayers()` clears and reapplies the whole role, so the write itself cannot tell a changed assignment from an unchanged one; it compares the filtered selection against the players already holding the role on that team -- `user/addplayerlists.php` calls it four times per save regardless of what the user touched. `GameSetCapEvent()` upserts, so a cap resubmitted with its pre-populated time and target would update no columns; it compares the existing row's time and target against the submitted pair. `GameRemoveCapEvent()` looks the cap up before deleting it. All three return without snapshotting or recording when there is nothing to do, which the `DBAffectedRows()` gate used elsewhere cannot do on its own: it keeps the audit row honest, but the snapshot is taken before the statement runs. `GameRemoveAllGameEvents()` still snapshots before it counts, which is harmless because its only caller is the restore replay, where suppression stops the snapshot and the record alike.

Because every scoresheet input path documented in `docs/scoresheet.md` (result-only entry, player-list entry, the detailed desktop scoresheet, mobile entry, scorekeeper entry) is built out of these same shared mutators, every one of those flows is covered.

## Authorization

Every mutator above checks its own permission before calling `ScoresheetHistoryRecord()` or `ScoresheetHistorySnapshotIfNeeded()`, but neither helper enforced anything itself, so a future caller could have forged an audit row or captured a snapshot for an arbitrary game. Both now call `ScoresheetHistoryAuthorized($gameId, $target)` after the disabled-setting, suppression and `$gameId` checks.

It accepts the union of the rights the callers in the table above actually hold. `hasEditGameEventsRight($gameId)` and `hasEditGamePlayersRight($gameId)` apply to any target. The others are each scoped to the one target they are granted for, so a caller holding only that right cannot reach the helpers directly and forge rows for another target:

| Right | Scoped to | Callers |
|---|---|---|
| `hasAddMediaRight()` | `mediaevent` | `AddGameMediaEvent()`, `RemoveGameMediaEvent()`, `RemoveMediaUrl()` |
| `CanManageGameComment()` | `comment` | `SetGameComment()` |
| `ANONYMOUS_RESULT_INPUT` or `isLoggedIn()` | `result` | `GameSetResult()` |

`hasAddMediaRight()` needs the scope most: unlike the others it carries no game or team scope at all, and any logged-in session holds it. It is also the only one of the three whose callers never snapshot, and correspondingly the only target `ScoresheetHistorySnapshotIfNeeded()` is never called with -- `SetGameComment()` passes `comment` and the by-game-ID `GameSetResult()` route passes `result`, so those two branches do apply to a snapshot capture.

### The by-game-ID self-report route

`result.php` and `scorekeeper/result.php` call `GameSetResult($gameId, $home, $away, true, false)` -- `$checkRights=false` -- for the by-game-ID result entry that `ANONYMOUS_RESULT_INPUT` (see `docs/configuration-flags.md`) exists to support. The submitter holds none of the rights above, so without a separate signal the change would go unrecorded, which is the opposite of what a weakly attributed change needs. `GameSetResult()` therefore passes `$allowAnonymousResult=true` down whenever it was itself called with `$checkRights=false`.

`ScoresheetHistoryAuthorized()` never takes that flag on its own, since it is caller-controlled and says nothing about who is submitting. It grants the `result` target on it only together with one of two independent facts about this request:

- the installation's `ANONYMOUS_RESULT_INPUT` constant is on, which is the case the flag was introduced for; or
- `isLoggedIn()`, which is the case that arises when the constant is **off**. Both pages then include the auth guard and require a login -- but they still pass `$checkRights=false`, so a logged-in submitter holding no game role is admitted by the mutator while reaching none of the rights above. Without this second branch the result would save with neither a snapshot nor an audit row, which is the one combination this table exists to prevent.

An anonymous submitter on an installation with the constant off is refused by both branches, and so is any other target: a caller passing `$allowAnonymousResult` for `comment` or any other target still hits the ordinary checks.

A row recorded this way with no session stores `user_id` as the literal string `anonymous` rather than the usual `unknown`, so the history pages show the unattributed origin distinctly. A logged-in by-ID submission stores that user's id like any other row.

### Game notes edited by their author

`CanManageGameComment()` lets the original author of a game note update or delete it after they have lost `hasEditGameEventsRight()`. That write is legitimate and must be recorded, but passes none of the ordinary checks, hence the `comment` branch.

Authorship is resolved server-side rather than passed in, which the delete path makes awkward: `ApplyCommentChange()` logs a `comment_delete` event, `GameCommentMeta()` treats the newest such event as a cutoff and looks for a `comment_create` after it, so once the delete is applied the author is no longer recognisable. `SetGameComment()` therefore makes **both** history calls before `ApplyCommentChange()`. Nothing observable changes: `ApplyCommentChange()` returns `true` in every branch and its result was never gated on.

The create path needs none of this -- `CanCreateGameComment()` still requires `hasEditGameEventsRight()`.

## Attribution

`ScoresheetHistorySource()` resolves the app that made the change from the `UO_APP_SOURCE` constant. `api/`, `scorekeeper/`, `spiritkeeper/`, and `mobile/index.php` (when reached directly) each define it at their own entry point. The root `index.php`, which serves `user/` and `admin/` pages -- and `mobile/` pages when routed through it -- derives it instead: it takes the leading path segment of the resolved `?view=...` value, keeps it only if it is `admin`, `user`, or `mobile`, and otherwise falls back to `user`. This is why a forfeit set from `admin/editgame.php` is attributed to `admin` and a point entered from `mobile/addscoresheet.php` to `mobile`, without either page passing that information. `ScoresheetHistorySource()` also falls back to matching `$_SERVER['SCRIPT_NAME']`, a defensive path that is effectively unreachable.

`ip` is left empty when `DisableVisitorLogging` is set, on every row: that setting stops IP recording entirely (see `docs/privacy.md`).

## Viewing history

- `user/scoresheethistory.php` shows the change history for one game. It requires `hasViewScoresheetHistoryRight($gameId)`: `hasEditGameEventsRight($gameId)` -- the same right needed to edit that game's scoresheet, so a team's own game admins can review their game's history without broader access -- or `isSeasonAdmin()` on the game's event, which keeps the history readable after the event is set read-only, as archiving statistics does. Rows with `has_snapshot=1` get a "Show" link, which renders the whole saved state and marks what differs from the current scoresheet (see "Reading a saved state" below). The "Restore this version" action is narrower: it needs `hasRestoreScoresheetHistoryRight($gameId)`, which only a superadmin or the event's `seasonadmin` holds. Reading the history is a review tool for anyone who can edit the game; overwriting the whole scoresheet from it is an event administrator's decision.
- The list hides the rows of a save that changed nothing. A desktop save clears each list and adds its rows back, so an unchanged re-save records a `clear` row followed by the same `add` rows as the previous save. For `goal`, `defense`, `timeout`, `spirit_timeout` and `played`, a clear-and-re-add block is hidden when its `add` details equal the previous block of that target and no other row of that target, and no `restore`, came between them. The check runs over the fetched window only, so the oldest block in it is always shown. The rows stay in the table; a "Show all" link (`&all=1`) lists them. No snapshot row is hidden: repeated snapshots are already deduplicated when written.
- The page is reached from `user/addscoresheet.php`, which links to it from the hint column below the "Back to game responsibilities" link, with the time of the newest history row beside the link. It is deliberately not one of the per-game tabs: the tab row is the working set of scoresheet entry pages, and the history is a review detour taken from the scoresheet itself. The row is omitted when the game has no history rows at all, so an installation running with `DisableScoresheetHistory` set never offers a link to an empty page.
- `admin/seasonscoresheethistory.php`, titled "Scoresheet history" in the UI and reached from `admin/seasonadmin.php`, lists one row per game of one event: the time, user and source of that game's latest change, the total number of changes, and the game's scheduled time. It is restricted to `isSeasonAdmin($seasonId)` in both the page and its data query (`SeasonScoresheetHistorySummary()`), so a team admin who can reach the per-game page cannot browse the whole event through this one. The game number links to the per-game page for the row-level detail, which links back here -- but only for a reader who holds `isSeasonAdmin()` on the game's event, since a team's own game admin can reach the per-game page and would only meet this page's refusal.

  A game whose latest change falls outside its scheduled date is highlighted, and a filter narrows the list to those -- the question the page exists to answer is which scoresheets were touched after their gameday. Comparing `uo_game.time`, the scheduled time as the organizer entered it, against `uo_scoresheet_history.time`, the database clock, is exact only while the event runs on the server's clock: nothing in the app converts either to the event's own timezone, so a change made near midnight on an event hosted in another timezone can be marked on the wrong side of the boundary. The remaining filters are a date range on the latest change. `MAX(history_id)` identifies that latest row rather than `MAX(time)`, because a bulk save writes several rows within the same second.

  There is no cross-event log. Finding a change without knowing its event means checking each event's list in turn, and there is no search by user or IP outside a single game's history.

### Reading a saved state

The "Show" link opens the snapshot below the change table, section by section: the result, halftime, starting offensive team, cap events, forfeit, game clock, defence counts, scorekeeper and game comment, then the points, each team's roster, the defences, the timeouts and the spirit stoppages. A section empty on both sides is left out, so a capture taken before the first point shows its roster and result rather than a header-only table.

Every section is compared against the current scoresheet, which the page builds by calling `ScoresheetHistoryBuildSnapshot()` for the game -- the same builder that wrote the stored snapshot, so both sides have the same shape and the comparison is an array walk rather than a second set of queries. Rows pair by their own key (`num` for defences and stoppages, `player` for roster rows); points pair by their order instead, because a point's `num` can start from 0 or 1 depending on the entry path that last saved it and are marked `*` when a displayed field differs, `-` when the row exists only in the saved state, and `+` when it exists only in the current scoresheet. A marked row is tinted, and a changed single value is shown with the current one beside it. The heading counts the differences, or states that the two are identical.

Values that a restore replays are rendered in the form the restore uses, not in a shorter one that would compare equal across states it can distinguish: the result carries `Ongoing` or `Final` beside the score and renders an unset result as `None` rather than `0 - 0`, a forfeit names the team that forfeited instead of reading `Yes`, a cap event carries its point-cap target beside its time, and a roster row lists the `accredited` and `acknowledged` flags beside the captaincies.

Only displayed fields are compared, so a mark always has something visible behind it. The game clock is compared as the one value a reader can act on: the effective elapsed time `timer_elapsed` records, with `Running` or `Paused` beside it. The raw `timer_start`, `timer_pause_start` and `timer_paused_duration` columns are not, since they are wall-clock epochs that differ on nearly every capture and would mark everything while saying nothing about the game. A side whose clock was never started reads `None` rather than `0:00`, and a snapshot older than `v3`, which carries no `timer_elapsed` to compare, leaves the row out entirely. A key an older snapshot format never carried is not a difference either: a snapshot without the v2 defence counts says so in a note instead of comparing against zero.

A fixture change is itself recorded as a `fixture` row, so the history says why the snapshots before it stopped being restorable.

A snapshot whose recorded teams are no longer the game's teams is withheld from reads. `hasEditGameEventsRight()` resolves through the game's current series, responsible team and reservation, all of which `SetGame()` can change, so an admin can gain rights over a game after it has moved -- and would otherwise read a roster and scorer names belonging to the previous fixture. `ScoresheetHistoryRestore()` is the only caller that opts in to receiving a mismatched snapshot, because it reports the mismatch as a specific refusal. `ScoresheetHistoryEntry()` still sets `fixture_mismatch` on the withheld entry it returns, so `user/scoresheethistory.php` can say why a "Show" link renders nothing without ever holding the snapshot.

The guard is scoped to the teams, and they are not the only thing `SetGame()` can change that grants a new administrator rights over the game. A pool move carries the game into another series, and `uo_game.reservation` grants `resgameadmin` holders; neither touches `hometeam`/`visitorteam`, so neither trips the comparison, and an administrator who gains rights that way reads the game's earlier snapshots. This is a deliberate scope rather than an oversight: the guard exists for the case where the snapshot describes *different teams*, which is other people's data and a corrupting restore. A pool or reservation move leaves the same two teams in the same game, which the new administrator now legitimately administers. Widening the comparison to the pool would also hide snapshots and refuse restores after every routine within-series pool reassignment. The `fixture`/`move` row above at least makes such a move visible in the history rather than silent. `GameRespTeam()` needs no separate mention: it derives from the team columns, so the existing comparison already covers it.

Change rows other than snapshots stay readable after a move. They describe this game, which the admin now legitimately administers, and the `user_id` they expose is ordinary audit visibility between admins of the same game.

## Restoring a snapshot

`ScoresheetHistoryRestore($historyId)` rebuilds a game's scoresheet from a snapshot row's `snapshot` JSON.

**The replay is not transactional.** `lib/database.php` offers no transaction-helper API; raw `START TRANSACTION`/`COMMIT`/`ROLLBACK` are used elsewhere in the codebase (e.g. `lib/privacy.functions.php`), but the restore replay does not use one. A `die()` partway through a bulk-rewriting mutator would leave the scoresheet in a mixed state with no rollback, so the safety of a restore rests entirely on checking every right the replay will need before it writes anything. The rest of the contract follows from that:

It is also unserialized, which is the accepted residual risk of that choice. Each roster, goal, defence, event and result statement commits on its own, so a scorekeeper saving a point while a restore runs -- or a second restore started at the same moment -- can interleave: the point sequence and the `uo_game` score can end up sourced from different states, without either write being rejected. Serializing it would mean holding a transaction across `ResolvePoolStandings()`, `PoolResolvePlayed()` and `RefreshGameSpiritData()`, locking the pool and standings tables for the duration of the replay -- during exactly the live scoring the lock would be protecting against. The exposure is bounded instead: restoring needs `hasRestoreScoresheetHistoryRight()`, so only a superadmin or the event's own admin can start one, it is a deliberate rare action rather than part of any scoring flow, and the pre-restore snapshot means an interleaved result can itself be restored back out of.

- **The guard runs before anything is touched.** The replay calls mutators guarded by three different rights -- `hasEditGamePlayersRight()` for `GameAddPlayer()` / `GameAddNewPlayer()`, `hasEditGameEventsRight()` for most of the rest (including `GameSetForfeit()`, called last), and `hasAccredidationRight()` for an `acknowledged` roster flag, which is still an accreditation mutation even though it no longer goes through `AcknowledgeUnaccredited()`. `ScoresheetHistoryRestore()` instead checks the single stricter `hasRestoreScoresheetHistoryRight()` up front and refuses to start without it: a superadmin or event `seasonadmin` holds all three for the game's own teams, so no per-mutator check can fail mid-replay.
- **The replay goes through the ordinary mutators for everything except the roster**, not raw SQL, so each mutator's own bookkeeping still runs. The cached pool standings are the exception: the replay calls `ResolvePoolStandings()` and `PoolResolvePlayed()` itself, at the end, rather than leaving them to a mutator. `GameUpdateResult()` -- the ongoing branch of the result replay -- never recomputes, so a restore to an ongoing state would otherwise leave the cached standings and `uo_pool.played` counting the result it just replaced. `GameSetDefenses()` restores the aggregate defense counts the same way, and `RefreshGameSpiritData()` is called once the replay finishes. History recording is suppressed for the duration of the replay (`ScoresheetHistorySuppressed()`), except for the pre-restore capture and the restore's own audit row.
- **The roster is written directly, bypassing `GameAddPlayer()`'s accreditation gate.** The roster rebuild in `ScoresheetHistoryRestore()` calls `GameRemoveAllPlayers()` first, which destroys `GameAllowsPlayerOnRoster()`'s "already on this game's roster" exception -- so in a `require_accreditation` season, routing the rebuild back through `GameAddPlayer()` would drop an unaccredited player from the restored roster even when the snapshot recorded `acknowledged=1`. Each row (jersey number, `captain`, `spirit_captain`, `accredited`, `acknowledged`) is inserted straight into `uo_played` instead: the snapshot is evidence the player was legitimately on that roster. One consequence is that a restored acknowledgment no longer writes a `uo_accreditationlog` row.
- **Each acknowledgment is rechecked against the player's current team.** The restore loop resolves each about-to-be-acknowledged player's current team (post-rematch) and rechecks `hasAccredidationRight()` before writing `acknowledged=1` -- the same team `AcknowledgeUnaccredited()` authorizes against, and one the up-front guard says nothing about once a player has transferred out of the event. A player who moved teams, restored by an admin holding the right only on the old team, gets `acknowledged=0` and a warning rather than a silently granted acknowledgment on the new team. This does not abort the restore.
- **Players are rematched by jersey number when the id no longer exists, but only when the match is unique.** `uo_goal` sets both player foreign keys to `NULL` on delete, so a player removed since the snapshot cannot be resolved by id; the restore falls back to a player on the same team with the same jersey number. `uo_player` has no unique constraint on `(team, num)`, so a non-unique match would silently attribute the snapshot's goals, assists and defenses to the wrong person and rewrite their roster number -- the rematch is skipped and warned about instead, the same as when no candidate is found. Two ambiguity cases are handled beyond that: two snapshot rows sharing `(team, num)` after both players were deleted would otherwise both rematch onto the same survivor, so a pre-scan warns on every row in such a group rather than letting loop order pick a winner; and a candidate already claimed by another row -- including a row whose own id still exists -- cannot be reused.

  What uniqueness cannot detect is the opposite case: a deleted player whose number has since been reassigned to a *different* player on the same team. That match is unique, so it is accepted, and the snapshot's roster row, goals, assists and defences land on the replacement. This is an accepted residual rather than an oversight. `ON DELETE SET NULL` has already destroyed the only stable identity the snapshot had, so the alternative to a best-effort match on the recorded `(team, num)` is discarding the row outright -- and the fallback exists for the common shape of this, a squad re-imported after a deletion, where the same person comes back under a new id with the same number. The cost of being wrong is bounded by who can trigger it and what they see first: restoring is a superadmin or `seasonadmin` action, and the saved state read beside the current scoresheet names every player the snapshot recorded, so a roster that no longer matches the people on it is visible before the restore rather than after.
- **A player with no jersey number is never rematched.** Both `uo_player.num` and `uo_played.num` are nullable and 0 is a jersey a player may actually wear, so a null number is written back as SQL `NULL` rather than 0 on the restored `uo_played` row. A deleted player with no number has nothing to match on -- casting null to 0 in the lookup would resolve them onto whoever wears number 0 -- so they get the ordinary "could not be restored" warning.
- **`uo_gameevent` rows are rebuilt, not just upserted, for the types the replay can reinstate.** `GameSetCapEvent()` is upsert-only and nothing else removed stale rows before replay, so a cap set after the snapshot would otherwise survive a restore. `GameRemoveAllGameEvents()` deletes the starting-offence and cap-event rows before the replay loop re-adds what the snapshot had. It records a `gameevent`/`clear` row of its own, which the replay's suppression swallows -- so the audit row appears only if some future caller deletes outside a restore. It is narrower than "every non-media row": other event types (e.g. `turnover`) are captured in the snapshot but have no replay branch, so widening the delete would destroy data the replay could never put back.
- **Media events are excluded.** `ScoresheetHistoryBuildSnapshot()` excludes `uo_gameevent` rows of type `media`, because media links are guarded by `hasAddMediaRight()` rather than by the rights checked above, and a restore must not add or remove them.
- **`IsPoolLocked()` and `IsSeasonStatsCalculated()` do not block a restore.** They are only ever turned into warning text elsewhere in the app (by `CheckGameResult()`, for ordinary result edits) and never enforced by the mutators themselves, so blocking on them here would make a restore stricter than an ordinary edit. `ScoresheetHistoryRestore()` reuses the same wording and surfaces both as warnings instead.
- **A `hometeam`/`visitorteam` mismatch does block a restore.** `SetGame()` (reassignment) and `GameChangeHome()` (swap) never snapshot, so a snapshot taken before either is a scoresheet for a fixture the game no longer represents; replaying it would write roster, goal and defense rows for teams that are not in the game, and invert `ishomegoal` semantics on a swap. That is corruption rather than a policy condition, so the restore is refused. The comparison is positional (`hometeam` to `hometeam`), since a set comparison would miss a swap. A pre-`v4` snapshot has neither key and restores as before.
- **A restore does not touch `uo_player.num`**, the player's current squad number, only the per-game `uo_played.num`. `GameAddPlayer()` writes both, and the direct-write path deliberately does not copy that half. The squad number is present state on whatever team the player is on now, not a record of this game, and no snapshot captures it -- so a restore that rewrote it could not be undone by restoring the pre-restore capture, which is the one guarantee the rest of the replay keeps. A restored game therefore shows its own historical numbers, which is what `GamePlayers()` reads (`pg.num`), while the team's current roster is left as the roster admin last set it.

## Retention

Rows are removed two ways.

By cascade: deleting the `uo_game` row a history row belongs to deletes that row too, through the `fk_scoresheet_history_game` foreign key (`ON DELETE CASCADE`). This fires whenever a game is deleted, whether through the single-game `DeleteGame()` or the bulk event-data cleanup in `lib/data.functions.php`.

By an explicit event cleanup: `DeleteEventScoresheetHistory($seasonId)` drops every history row belonging to an event's games. `admin/stats.php` offers it as a superadmin checkbox alongside the existing "Delete event access rights after archiving statistics" one, applied after statistics are archived -- the point at which the event is finished, the results are settled, and the record has done its job. It is left unchecked by default, where the access-rights option is checked: rights deleted in error can be granted again, and this cannot be undone. The game set is every game the event owns, reached through its timetable pool row -- the same ownership `admin/seasonscoresheethistory.php` lists a game by. Carryover pool rows are deliberately not enough: `SetGamePool()` drops only the previous owner and leaves them behind, so a game moved into another event still links back here, and matching them would erase that event's audit trail. Results, rosters and scoresheets are untouched; what goes is the audit trail and every restore point. The deletion itself is recorded in `uo_event_log` (`category='game'`, `source='history-cleanup'`) with the row count, since a record of it cannot live in the table it empties.

InnoDB reuses the freed pages rather than returning them to the filesystem, so a cleanup run to reclaim disk needs an `OPTIMIZE TABLE uo_scoresheet_history` afterwards.

There is still no age-based pruning job and no delete control in either history-viewing page.

## Privacy

- The registered-user data export (`lib/privacy.functions.php`) includes a user's own `uo_scoresheet_history` rows but excludes the `snapshot` column, since a snapshot describes the game's full state, including other players' names.
- The player data export projects a player's own entries out of every snapshot's `played[]` / `goals[]` / `defenses[]` -- `PrivacyPlayerScoresheetHistorySnapshotRows()` walks the same shape `PrivacyAnonymizePlayer()` rewrites and keeps only the entries keyed to that player's id, tagged with the game and snapshot time. Each comes out with the fields belonging to it: the roster row's name, team, jersey number, captaincies and accreditation flags; the player's own side of a goal, never the other; and the defences they authored. This is how a prior spelling of a player's name, and a jersey number or captaincy since changed, retained in an old snapshot but no longer present in `uo_player`, still reach their report.
- `PrivacyPlayerScoresheetHistoryDetailRows()` does the same for the ordinary change rows, whose `detail` names the player as `played.player`, a `played.players` captaincy list, `goal.scorer`, `goal.assist` or `defense.player`.
- Deleting a registered user's data anonymizes their `uo_scoresheet_history` rows (`user_id` set to `-`, `ip` cleared) rather than deleting them: the row is the game's change history, not solely that user's data.
- Anonymizing a player rewrites the player's name wherever it is embedded as free text inside a snapshot's JSON, keyed by player id, since those fields are not foreign keys and anonymizing `uo_player` does not reach them.

See `docs/privacy.md` for the full export, anonymization, and deletion behavior across all tables.
