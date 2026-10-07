# Scoresheet Change History

`uo_scoresheet_history` records every scoresheet change, who made it, and restorable snapshots of earlier states. See `docs/scoresheet.md` for the scoresheet itself and the entry flows that write to it. The desktop scoresheet also uses this table to detect concurrent edits (see "Parallel editing" in `docs/scoresheet.md`).

Recording can be turned off installation-wide with the `DisableScoresheetHistory` setting (`docs/configuration-flags.md`).

## What is stored

Every mutation made through the scoresheet mutators writes a row alongside its ordinary write to `uo_game`, `uo_played`, `uo_goal` and the other scoresheet tables:

- `game`: foreign key to `uo_game`, cascades on delete
- `time`, `user_id`, `ip`: when and by whom
- `source`: which app made it (see "Attribution")
- `target`, `action`: what changed and how, e.g. `goal`/`add`, `played`/`clear`
- `detail`: small JSON payload rendered by `ScoresheetHistoryFormatDetail()`. It carries the values that describe the change itself (a cap event's time and point-cap target), because the snapshot is captured before the change and cannot supply them.
- `has_snapshot`, `snapshot`: set only on snapshot rows

There are two row kinds:

- **Change rows**, written by `ScoresheetHistoryRecord()`: an audit trail, not restorable on their own.
- **Snapshot rows** (`target='snapshot'`, `action='capture'`, `has_snapshot=1`): the full scoresheet serialized by `ScoresheetHistoryBuildSnapshot()`. These are what the history pages offer to restore.

Tournament administration outside the mutators is not recorded: the BYE results assigned by `CheckBYE()` (`lib/swissdraw.functions.php`) and the team fill-ins from pool moves (`lib/pool.functions.php`, `lib/team.functions.php`) follow from the pool's own settings and move table, which are their record.

### Snapshot formats

The snapshot's `v` key holds its format version. Older snapshots stay restorable, so `ScoresheetHistoryRestore()` replays a field only when its key is present.

| Version | Adds | Restoring an older snapshot |
|---|---|---|
| `v1` | result, roster, goals, defenses, timeouts, events, comment | -- |
| `v2` | `homedefenses`, `visitordefenses`, `timer_start`, `timer_pause_start`, `timer_paused_duration` | `v1` leaves defense counts alone, and the clock in whatever state the replayed result call leaves it |
| `v3` | `timer_elapsed` (elapsed game time from `GameTimerState()`) | `v2` writes back the absolute `timer_start` epoch, so a running clock counts the time since capture |
| `v4` | `hometeam`, `visitorteam` (nullable) | the fixture-mismatch guard cannot apply before `v4` |

From `v3` on, restore sets `timer_start = now - timer_elapsed`, and a paused snapshot is frozen with `timer_pause_start = now`.

## When snapshots are taken

Snapshots are sparse; one per point would mean one per goal. `ScoresheetHistorySnapshotIfNeeded()` runs at the start of nearly every scoresheet mutator in `lib/game.functions.php`, after its permission check and before its write, capturing the state about to be overwritten. It is memoized per game per request, so a desktop save calling several mutators produces one snapshot. A capture identical to the game's latest snapshot reuses that row instead of adding another (the pre-restore capture is exempt). A standalone single-field change, such as only the halftime, gets its own snapshot.

Exceptions:

- **Per-goal paths.** `GameAddScore()`, `GameAddScoreEntry()` and `GameRemoveScore()` never snapshot. The desktop bulk save is covered by `GameRemoveAllScores()`, which does.
- **`GameUpdateResult()`** takes `$snapshot` (default `true`). The per-point caller `scorekeeper/addscoresheet.php` passes `false`; `GameApplyScoreTap()`, behind the +1/-1 buttons of `scorekeeper/addresult.php`, takes no snapshot either.
- **Fixture mutators.** `GameChangeHome()` and `SetGame()` record a `fixture` row but never snapshot: the captured state would carry the outgoing teams and be withheld as a fixture mismatch immediately. `SetGame()` records only when a team column actually changed (compared against a read-back of the row), and records a separate `fixture`/`move` row when the pool changes. Both are written before `SetGamePool()` runs, because the history rights are resolved through the game's series, which that call can change.
- **The five `GameTime*()` clock mutators** record a `timer` row but never snapshot. Restore is whole-sheet, so a clock-only restore point would also roll back goals and roster. Other snapshots still capture the clock. Fix a clock mistake with `GameTimeSetElapsed()`.

`SetGameComment()` (`lib/comment.functions.php`, game comments only) snapshots before its change, so a standalone comment edit has its own restore point.

`ScoresheetHistoryRestore()` force-captures the state it replaces, so a restore can be undone. That capture and the restore's audit row ignore both replay suppression and `DisableScoresheetHistory`: the setting governs routine volume, not the recoverability of a destructive action.

## Recording points

| Target | Action | Mutator |
|---|---|---|
| `result` | `update` | `GameUpdateResult()`, `GameApplyScoreTap()`, `GameSetResult()`, `GameSyncResultFromGoals()` |
| `result` | `clear` | `GameClearResult()` |
| `forfeit` | `update` | `GameSetForfeit()` |
| `played` | `add` | `GameAddPlayer()`, `GameAddNewPlayer()` |
| `played` | `update` | `GameSetPlayerNumber()`, `GameSetRolePlayers()` (via `GameSetCaptains()` / `GameSetSpiritCaptains()`) |
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
| `fixture` | `update` | `SetGame()`, when `hometeam` or `visitorteam` changes |
| `fixture` | `move` | `SetGame()`, when the pool changes |
| `official` | `update` | `GameSetScoreSheetKeeper()` |
| `halftime` | `update` | `GameSetHalftime()` |
| `comment` | `update` / `remove` | `SetGameComment()` |
| `timer` | `start` / `pause` / `resume` / `reset` / `update` | `GameTimeStart()`, `GameTimePause()`, `GameTimeResume()`, `GameTimeReset()`, `GameTimeSetElapsed()` |
| `snapshot` | `capture` | `ScoresheetHistorySnapshotIfNeeded()` |
| `restore` | `restore` | `ScoresheetHistoryRestore()` |

Three mutators compare against current state first and return without snapshotting or recording when nothing would change, because the snapshot is taken before the statement runs and a `DBAffectedRows()` gate cannot prevent it: `GameSetRolePlayers()` (clears and reapplies the whole role; `user/addplayerlists.php` calls it four times per save), `GameSetCapEvent()` (an upsert; compares time and target), and `GameRemoveCapEvent()` (looks the cap up first).

Every scoresheet input path in `docs/scoresheet.md` is built from these mutators, so every flow is covered.

## Authorization

`ScoresheetHistoryRecord()` and `ScoresheetHistorySnapshotIfNeeded()` call `ScoresheetHistoryAuthorized($gameId, $target)` after the disabled, suppression and `$gameId` checks, so no caller can forge an audit row or capture a snapshot for an arbitrary game.

`hasEditGameEventsRight($gameId)` and `hasEditGamePlayersRight($gameId)` allow any target. The other rights are each scoped to one target:

| Right | Target | Callers |
|---|---|---|
| `hasAddMediaRight()` | `mediaevent` | `AddGameMediaEvent()`, `RemoveGameMediaEvent()`, `RemoveMediaUrl()` |
| `CanManageGameComment()` | `comment` | `SetGameComment()` |

The scope matters most for `hasAddMediaRight()`, which any logged-in session holds.

### Game notes edited by their author

`CanManageGameComment()` lets a note's author update or delete it after losing `hasEditGameEventsRight()`, hence the `comment` branch. Authorship is resolved from `comment_create` / `comment_delete` events, so once a delete is applied the author is no longer recognisable. `SetGameComment()` therefore makes both history calls before `ApplyCommentChange()`. Creating a note still requires `hasEditGameEventsRight()`.

## Attribution

`ScoresheetHistorySource()` reads the `UO_APP_SOURCE` constant. `api/`, `scorekeeper/` and `spiritkeeper/` define it at their entry point. The root `index.php` derives it from the leading segment of `?view=...`, keeping `admin` or `user` and otherwise using `user`. A final `$_SERVER['SCRIPT_NAME']` match is a defensive fallback. Older rows may carry `mobile`, written by the removed legacy mobile pages.

`ip` is empty on every row when `DisableVisitorLogging` is set (see `docs/privacy.md`).

A row written by an anonymous Scorekeeper session through a scorekeeping link stores `user_id` `anonymous` and the link's token id in `scorekeeper_token`, the only attribution such a row has. The column has no foreign key, so the id still tells links apart after one is replaced. The history page shows it as `#<id>` next to the user.

## Viewing history

- `user/scoresheethistory.php` shows one game's history. It requires `hasViewScoresheetHistoryRight($gameId)`: `hasEditGameEventsRight($gameId)`, so a team's own game admins can review it, or `isSeasonAdmin()` on the event, which still works after the event is read-only. Snapshot rows get a "Show" link. "Restore this version" requires `hasRestoreScoresheetHistoryRight($gameId)`: superadmin or the event's `seasonadmin` only.
- The list hides the rows of a save that changed nothing. For `goal`, `defense`, `timeout`, `spirit_timeout` and `played`, a clear-and-re-add block is hidden when its `add` details equal the previous block of that target with no other row of that target, and no `restore`, in between. The check covers the fetched window only, so its oldest block is always shown. `&all=1` ("Show all") lists everything. Snapshot rows are never hidden.
- `user/addscoresheet.php` links to it from the hint column, with the newest history time beside the link. The link is omitted when the game has no history rows. It is deliberately not a per-game tab.
- `admin/seasonscoresheethistory.php` ("Scoresheet history", from `admin/seasonadmin.php`) lists one row per game of an event: time, user and source of the latest change, change count, and scheduled time. Both the page and `SeasonScoresheetHistorySummary()` require `isSeasonAdmin($seasonId)`. The game number links to the per-game page, which links back only for season admins.

  Games whose latest change falls outside their scheduled date are highlighted and can be filtered. The comparison uses `uo_game.time` against the database clock without timezone conversion, so near midnight on an event in another timezone it can land on the wrong side. Other filters are a date range on the latest change. The latest row is `MAX(history_id)`, not `MAX(time)`, since a bulk save writes several rows in the same second. There is no cross-event log and no search by user or IP.

### Reading a saved state

"Show" renders the snapshot section by section: result, halftime, starting offence, cap events, forfeit, game clock, defence counts, scorekeeper, game comment, then points, rosters, defences, timeouts and spirit stoppages. Sections empty on both sides are omitted.

Each section is compared against the current scoresheet, built with the same `ScoresheetHistoryBuildSnapshot()`. Rows pair by key (`num` for defences and stoppages, `player` for roster rows); points pair by order, because `num` may start at 0 or 1. Rows are marked `*` (a displayed field differs), `-` (only in the saved state) or `+` (only in the current one), and a changed value is shown beside the current one. The heading counts the differences.

Values are rendered as the restore would replay them: the result shows `Ongoing` or `Final` and an unset result as `None`, a forfeit names the forfeiting team, a cap event shows its point-cap target, and roster rows show `accredited` and `acknowledged`. Only displayed fields are compared. The clock is compared by `timer_elapsed` with `Running` or `Paused`, not by the raw epoch columns; a never-started clock reads `None`, and a pre-`v3` snapshot omits the clock row. A key missing from an older format is noted, not compared against zero.

### Fixture mismatch

A snapshot whose recorded teams are no longer the game's teams is withheld from reads. `hasEditGameEventsRight()` resolves through the game's current series, responsible team and reservation, which `SetGame()` can change, so without this an admin who gained rights after a move could read the previous fixture's roster. `ScoresheetHistoryEntry()` sets `fixture_mismatch` on the withheld entry so the page can explain the empty "Show"; `ScoresheetHistoryRestore()` is the only caller that receives the snapshot, to refuse with a specific message.

The guard compares teams only. A pool move or reservation change keeps the same two teams, whose new administrator may legitimately read the earlier snapshots; widening the guard would also block restores after every routine pool reassignment. The `fixture`/`move` row makes such moves visible. Change rows stay readable after any move.

## Restoring a snapshot

`ScoresheetHistoryRestore($historyId)` rebuilds the scoresheet from a snapshot's JSON.

The replay is neither transactional nor serialized. A failure midway leaves a mixed state, so safety rests on checking every needed right before the first write. A concurrent point save or second restore can interleave, leaving the point sequence and `uo_game` score from different states. Serializing would lock the pool and standings tables during live scoring; this is an accepted residual, bounded by the restore right being admin-only and by the pre-restore snapshot.

- **Rights are checked up front.** The replay's mutators need `hasEditGamePlayersRight()`, `hasEditGameEventsRight()` and, for `acknowledged`, `hasAccredidationRight()`. `hasRestoreScoresheetHistoryRight()` implies all three for the game's teams, so nothing can fail mid-replay.
- **The replay uses the ordinary mutators except for the roster.** At the end it calls `ResolvePoolStandings()`, `PoolResolvePlayed()` and `RefreshGameSpiritData()` itself, since `GameUpdateResult()` does not recompute standings. `GameSetDefenses()` restores the defense counts. Recording is suppressed during the replay (`ScoresheetHistorySuppressed()`) except for the pre-restore capture and the audit row.
- **The roster is written directly to `uo_played`** (number, `captain`, `spirit_captain`, `accredited`, `acknowledged`). Going through `GameAddPlayer()` after `GameRemoveAllPlayers()` would drop acknowledged unaccredited players in a `require_accreditation` season. A restored acknowledgment writes no `uo_accreditationlog` row.
- **Each acknowledgment is rechecked** with `hasAccredidationRight()` against the player's current team. A failed check restores `acknowledged=0` with a warning and does not abort.
- **Deleted players are rematched by jersey number only when the match is unique.** `uo_goal` sets player keys to `NULL` on delete, so the restore falls back to the same team and number. Non-unique matches, snapshot rows sharing `(team, num)`, and candidates already claimed by another row are skipped with a warning. A number since reassigned to a different player is a unique match and is accepted: this is an accepted residual, because the common case is a re-imported squad, and the admin sees the saved roster by name before restoring.
- **A player without a jersey number is never rematched**, since 0 is a valid number. A null number is restored as `NULL`.
- **`uo_gameevent` rows the replay can reinstate are rebuilt.** `GameRemoveAllGameEvents()` deletes starting-offence and cap rows first, since `GameSetCapEvent()` only upserts. Other event types (e.g. `turnover`) have no replay branch and are left alone.
- **Media events are excluded** from snapshots; they are guarded by `hasAddMediaRight()` instead.
- **`IsPoolLocked()` and `IsSeasonStatsCalculated()` only warn**, since ordinary edits are not blocked by them either.
- **A `hometeam`/`visitorteam` mismatch blocks the restore.** Replaying would write rows for teams not in the game, or invert `ishomegoal` after a swap. The comparison is positional, so a swap is caught. Pre-`v4` snapshots restore as before.
- **`uo_player.num` is not touched**, only `uo_played.num`. The squad number is current state that no snapshot captures, so rewriting it could not be undone.

## Retention

- **Cascade:** deleting a `uo_game` row deletes its history (`fk_scoresheet_history_game`, `ON DELETE CASCADE`), both through `DeleteGame()` and the event-data cleanup in `lib/data.functions.php`.
- **Event cleanup:** `DeleteEventScoresheetHistory($seasonId)` drops the history of every game the event owns through its timetable pool row. `admin/stats.php` offers it to superadmins as an unchecked-by-default checkbox, applied after statistics are archived. It cannot be undone: the audit trail and every restore point go. Carryover pool rows are not matched, because a game moved into another event still links back through them. Results and scoresheets are untouched. The deletion is logged in `uo_event_log` (`category='game'`, `source='history-cleanup'`) with the row count. Run `OPTIMIZE TABLE uo_scoresheet_history` afterwards to reclaim disk.

There is no age-based pruning and no delete control on the history pages.

## Privacy

- The registered-user export includes the user's own history rows without the `snapshot` column, which contains other players' data.
- The player export projects the player's own entries out of every snapshot's `played[]`, `goals[]` and `defenses[]` (`PrivacyPlayerScoresheetHistorySnapshotRows()`), so earlier name spellings, numbers and captaincies reach the report. `PrivacyPlayerScoresheetHistoryDetailRows()` does the same for change-row `detail` (`played.player`, `played.players`, `goal.scorer`, `goal.assist`, `defense.player`).
- Deleting a registered user anonymizes their rows (`user_id` set to `-`, `ip` cleared) rather than deleting them.
- Anonymizing a player rewrites their name inside snapshot JSON, keyed by player id.

See `docs/privacy.md` for all tables.
