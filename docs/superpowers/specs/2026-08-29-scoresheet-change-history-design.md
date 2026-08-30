# Scoresheet change history — design

Date: 2026-08-29
Status: approved design, ready for an implementation plan

## Problem

Ultiorganizer records that a scoresheet was saved, but not what changed.

The only trail today is `uo_event_log`. `LogGameUpdate()` writes one row per save
with a `varchar(50)` description that `LogEvent()` hard-truncates, so the stored
history of a game is a sequence of strings like `scoresheet saved` and
`result: 15 - 12`. That answers *that* something happened and never *what*.

Three specific gaps:

- `user/addscoresheet.php` is a full-sheet rewrite. It calls `GameRemoveAllScores()`
  and rebuilds the whole point sequence, so the previous sequence is gone. The
  `uo_goal.timestamp` column looks like history but is reset on every save.
- The `LogGameUpdate($gameId, "scoresheet saved", "addscoresheet")` call at
  `user/addscoresheet.php:421` fires *before* the writes, so it cannot anchor a
  capture of the replaced state.
- `GameUpdateResult()`, `GameAddScore()`, `GameAddScoreEntry()`,
  `GameRemoveAllScores()` and the `uo_played` / `uo_timeout` mutators log nothing
  at all, and `api/` contains no logging calls whatsoever.

## Goals

- **Audit.** Who changed what, when, and from which surface, for every write to a
  game's scoresheet data.
- **Restore.** Put a game's scoresheet back to a state it previously held.
- **Coverage.** Every entry surface: `user/`, `scorekeeper/`, `mobile/`, `api/`,
  `admin/`, and any future `lib/` caller.
- **Readers.** Event admins see the history of a game they may edit; superadmins
  get a site-wide viewer.

## Non-goals

- Replacing or changing `uo_event_log`. Its contract, `admin/eventviewer.php`
  rendering and `PrivacySanitizePlayerEventLogRows()` are untouched.
- Age-based retention or scheduled pruning. See "Retention".
- Restoring media links, spirit scores, or anything outside the scoresheet.
- Fixing the pre-existing `LogDefenseUpdate()` bug (it writes category `defense`,
  which is absent from `EventCategories()`, so those rows never appear in the
  event viewer). Noted here so it is not lost; out of scope for this work.

## Approach

One table, `uo_game_history`, with a nullable snapshot column.

**Every mutation writes one small row** — game, time, user, ip, source, target,
action, and a structured JSON detail. One `INSERT`, zero reads. That is the audit
trail, and it stays cheap enough for the scorekeeper hot path, where a single game
produces roughly 70 individual mutations.

**A full snapshot is stored only at destructive boundaries**, holding the state
*before* the change. Snapshotting every mutation instead would cost ~5 KB per
write, meaning ~70 near-identical versions and ~70 MB for a 200-game tournament;
coalescing those would discard exactly the intermediate steps the audit trail
exists to record.

The sparse boundary set is not a compromise — it is the right set. Nobody wants
the sheet as it stood after point 14 of 24 during live entry. What people want
back is "the sheet before this admin re-entered it" and "the sheet as it was when
finalized", which is precisely what the boundaries capture.

Expected volume for a 200-game tournament: ~600 snapshots (~3 MB) and ~14,000
change rows (~2 MB).

### Considered and rejected

- **Snapshot per change, coalesced into one table.** Simplest model, every row
  restorable, but it rebuilds the whole sheet on every goal insert and the
  coalescing needed to bound storage is precisely what makes the audit lossy.
- **Two tables — a change journal plus a separate snapshot table.** Correct on
  cost, but two APIs, two UI concepts, and restore targets that do not line up
  with the audit rows an admin is looking at. Folding the snapshot into a nullable
  column on the journal removes the split at no cost.
- **Deltas only, with prior states materialised by replaying backwards.**
  Cheapest storage, but restore correctness then depends on every delta being
  complete and perfectly reversible across ~25 mutators. Too fragile.

## Schema

```sql
CREATE TABLE IF NOT EXISTS `uo_game_history` (
  `history_id`   int(10)      NOT NULL AUTO_INCREMENT,
  `game`         int(10)      NOT NULL,
  `time`         datetime     NOT NULL DEFAULT current_timestamp(),
  `user_id`      varchar(50)  NOT NULL,
  `ip`           varchar(45)  DEFAULT NULL,
  `source`       varchar(20)  DEFAULT NULL,
  `target`       varchar(20)  NOT NULL,
  `action`       varchar(10)  NOT NULL,
  `detail`       text         DEFAULT NULL,
  `has_snapshot` tinyint(1)   NOT NULL DEFAULT 0,
  `snapshot`     mediumtext   DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `idx_game_history_game_time` (`game`,`time`),
  KEY `idx_game_history_restorable` (`game`,`has_snapshot`,`time`),
  KEY `idx_game_history_user_time` (`user_id`,`time`),
  CONSTRAINT `fk_game_history_game` FOREIGN KEY (`game`)
    REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Column values:

- `target`: `result`, `goal`, `defense`, `played`, `timeout`, `spirit_timeout`,
  `gameevent`, `mediaevent`, `comment`, `official`, `halftime`, `forfeit`,
  `snapshot`, `restore`.
- `action`: `add`, `update`, `remove`, `clear`, `capture`, `restore`.

A snapshot is written *before* the change it protects against, so it cannot name
that change's target. It therefore gets its own row with target `snapshot` and
action `capture`. That row is the restorable one; the change rows that follow it
in the same request describe what was then done.
- `source`: `user`, `admin`, `scorekeeper`, `mobile`, `spiritkeeper`, `api`.

Three deliberate differences from `uo_event_log`:

- `ip` is `varchar(45)` so an IPv6 address is not silently truncated; the event
  log's `varchar(15)` does truncate one.
- `detail` is structured JSON rather than a 50-character prose string that gets
  chopped mid-word.
- The foreign key cascade means deleting a game or a season removes its history,
  with no separate sweep to write or remember.

`has_snapshot` exists so that "which rows are restorable" is an indexed lookup
rather than a prefix scan over a `MEDIUMTEXT`. List queries name their columns
explicitly and never select `snapshot`.

## Library API

New file `lib/gamehistory.functions.php`, guarded with `denyDirectLibAccess()`
like every other `lib/` file. All SQL for the table lives here.

```
GameHistoryRecord($gameId, $target, $action, $detail = [])
GameHistorySnapshotIfNeeded($gameId)
GameHistoryBuildSnapshot($gameId)
GameHistoryList($gameId, $limit = null, $offset = null)
GameHistoryCount($gameId)
GameHistoryAll($filters, $limit = null, $offset = null)
GameHistoryAllCount($filters)
GameHistoryEntry($historyId)
GameHistoryRestore($historyId)
GameHistoryFormatDetail($row)
```

Permission checks live inside these functions, not in the pages that call them:

- `GameHistoryList()`, `GameHistoryCount()`, `GameHistoryEntry()` and
  `GameHistoryRestore()` require `hasEditGameEventsRight($gameId)`.
- `GameHistoryAll()` and `GameHistoryAllCount()` require `isSuperAdmin()`.
- `GameHistoryRecord()` and `GameHistorySnapshotIfNeeded()` are internal to the
  recording path and are never called from a page. They carry no check of their
  own because every caller is a mutator that has already enforced one.

`GameHistoryFormatDetail()` renders a stored detail into localized text at display
time. Details are stored structured, never pre-rendered, so existing rows follow
the reader's language rather than the writer's.

### Source derivation

Today's `source` is an optional argument that each call site must remember to
pass, which is why `api/` passes nothing. Instead:

- each app entry point (`index.php`, `api/index.php`, `scorekeeper/index.php`,
  `mobile/index.php`, `spiritkeeper/index.php`) defines a `UO_APP_SOURCE`
  constant,
- `GameHistoryRecord()` reads it, falling back to deriving a value from the
  script path when the constant is absent.

Recording inside a `lib/` mutator then attributes the right surface automatically,
whoever the caller is.

**Identity on `api/` is a known limit.** `GameHistoryRecord()` takes the user from
`$_SESSION['uid']`, and API tokens carry no user: `uo_api_token` stores
`token_id`, `scope_type` and `scope_id` only. An API-driven change would therefore
record the correct `source` and a `user_id` of `unknown`. This costs nothing
today, because `api/` performs no scoresheet writes at all — it has no call to
`GameSetResult()`, `GameAddScore()`, `GameAddScoreEntry()` or `GameAddPlayer()`.
If write endpoints are added later, `GameHistoryRecord()` should record the token
id as the identity rather than `unknown`. This is the property that makes full coverage achievable
at all, and it is the reason recording belongs in `lib/` next to the permission
check rather than in the routed page handlers.

## Recording points

All in `lib/game.functions.php` except the last. Ordering within each mutator is
fixed: `GameHistorySnapshotIfNeeded()` runs **before** the write, so it captures
the state about to be destroyed; `GameHistoryRecord()` runs **after** the write
succeeds, so a rejected or failed write leaves no history row.

| Target | Functions |
| --- | --- |
| `result` | `GameUpdateResult` `GameSetResult`* `GameClearResult`* `GameSetForfeit` `GameSyncResultFromGoals` |
| `goal` | `GameAddScore` `GameAddScoreEntry` `GameRemoveScore` `GameRemoveAllScores`* |
| `defense` | `GameSetDefenses` `GameAddDefense` `GameRemoveAllDefenses`* |
| `played` | `GameAddPlayer` `GameAddNewPlayer` `GameRemovePlayer` `GameRemoveAllPlayers`* `GameSetPlayerNumber` `GameSetRolePlayers` |
| `timeout` | `GameAddTimeout` `GameRemoveAllTimeouts`* `GameAddSpiritTimeout` `GameRemoveAllSpiritTimeouts`* |
| `gameevent` | `GameSetStartingTeam` `GameSetCapEvent` `GameRemoveCapEvent` |
| `mediaevent` | `AddGameMediaEvent` `RemoveGameMediaEvent` |
| `official` / `halftime` | `GameSetScoreSheetKeeper` `GameSetHalftime` |
| `comment` | `SetGameComment` in `lib/comment.functions.php`, `COMMENT_TYPE_GAME` only |

`*` also calls `GameHistorySnapshotIfNeeded()`.

`GameSetRolePlayers()` covers both captains and spirit captains, since
`GameSetCaptains()` and `GameSetSpiritCaptains()` both delegate to it.

### One snapshot per bulk save

`GameHistorySnapshotIfNeeded()` memoizes per game per request, using the pattern
in `docs/runtime-cache.md`. A desktop save calls `GameRemoveAllScores()`,
`GameRemoveAllTimeouts()` and `GameRemoveAllSpiritTimeouts()` in sequence, and
produces exactly one snapshot rather than three. The snapshot is taken before the
first of them runs, so it holds the complete pre-save state.

The existing `LogGameUpdate()` calls stay exactly as they are.

## Snapshot format

```json
{"v": 1,
 "game": {"homescore": 15, "visitorscore": 12, "isongoing": 0, "hasstarted": 2,
          "forfeit": 0, "official": "...", "halftime": 900},
 "goals": [{"num": 1, "assist": 123, "assist_num": "3", "assist_name": "Player 1",
            "scorer": 456, "scorer_num": "7", "scorer_name": "Player 2",
            "time": 90, "homescore": 1, "visitorscore": 0,
            "ishomegoal": 1, "iscallahan": 0}],
 "played": [{"player": 123, "team": 7, "num": "3", "name": "Player 1",
             "captain": 1, "spirit_captain": 0,
             "accredited": 0, "acknowledged": 0}],
 "defenses": [], "timeouts": [], "spirit_timeouts": [],
 "events": [], "comment": ""}
```

Player rows carry **id, jersey number and display name together**. The id alone is
not enough: `uo_goal` declares `ON DELETE SET NULL` on both player foreign keys,
so a player deleted between snapshot and restore would otherwise be
unrecoverable. With the number and name stored, restore can re-match, and the
read-only view renders without joining `uo_player` at all.

`events` holds `uo_gameevent` rows **excluding `type='media'`**. Media links live
in the same table but are guarded by `hasAddMediaRight()` rather than
`hasEditGameEventsRight()`, so including them would let a restore rewrite rows the
restoring user may have no right to touch. Media changes still produce audit rows
under target `mediaevent`; they are simply outside the restore scope.

The `v` field is the format version, so a later change to the shape can be
detected rather than guessed at.

## Restore

`GameHistoryRestore($historyId)` refuses unless the row has a snapshot and the
caller passes the same guard set `CheckGameResult()` already applies:
`hasEditGameEventsRight()`, `isEventReadonly()` / `canBypassEventReadonly()`,
`IsPoolLocked()`, and `IsSeasonStatsCalculated()`.

Then, in order:

1. Call `GameHistorySnapshotIfNeeded()` to capture the current state, so the
   restore is itself undoable.
2. Replay **through the existing mutators**, never raw SQL — `GameRemoveAllScores()`
   then a `GameAddScoreEntry()` loop, `GameRemoveAllPlayers()` then a
   `GameAddPlayer()` loop, `GameSetRolePlayers()` for the captain flags, the
   timeout and spirit-timeout pairs, `GameSetStartingTeam()`, `GameSetCapEvent()`,
   `GameSetScoreSheetKeeper()`, `GameSetHalftime()`, `SetGameComment()`, and
   `GameSetResult()` / `GameClearResult()` for the aggregate result. This is what
   keeps `ResolvePoolStandings()`, `PoolResolvePlayed()` and
   `RefreshGameSpiritData()` running.
3. Re-match players by `player_id` first, then by jersey number within the team's
   current roster. Unmatched players are reported to the operator as a warning and
   stored as `NULL`, which is already a valid state in `uo_goal`.
4. Write a `restore` row whose detail names the source `history_id`.

Because step 2 goes through the ordinary mutators, it would otherwise emit a
change row per replayed goal, player and timeout — roughly 70 rows of noise per
restore. `GameHistoryRestore()` therefore sets a request-scoped suppression flag
that `GameHistoryRecord()` honors, so a restore appears in the timeline as exactly
two things: the pre-restore snapshot and the `restore` row. Suppression covers
recording only; the snapshot in step 1 is taken before the flag is set.

Restore is therefore never destructive: the state it replaces is always captured
first, and appears in the timeline as another restorable row.

Three limits are deliberate and belong in the documentation rather than in a fix:

- **The replay is not transactional.** `lib/database.php` exposes no transaction
  helpers, and the replayed mutators `die()` rather than return on a failed rights
  check, which would abort past any `finally`. The non-destructive guarantee
  therefore rests on `GameHistoryRestore()` checking every right its replay needs
  *before* it starts — `hasEditGameEventsRight()` **and**
  `hasEditGamePlayersRight()`, since `GameAddPlayer()` guards on the latter.
- **The result mutators cannot express every state.** `GameSetResult()`,
  `GameUpdateResult()` and `GameClearResult()` each force their own `hasstarted`
  value (2, 1 and 0). A game that was `hasstarted=1` with a final score cannot be
  reproduced by calling one of them, so `GameHistoryRestore()` writes the stored
  `hasstarted` and `isongoing` back after the mutator has done its pool work.
- **Restoring a roster rewrites current jersey numbers.** `GameAddPlayer()` also
  runs `UPDATE uo_player SET num=...`, so a restore resets each restored player's
  team roster number to the number they wore in that game.

`GameAddPlayer()` also returns `false` without dying when
`GameAllowsPlayerOnRoster()` refuses. Because the restore empties the roster
first, that helper's "already on this game's roster" fallback can no longer
rescue an unaccredited player in a season with `require_accreditation` set. Those
players are reported in the warning list rather than dropped silently.

## Views

**`?view=user/gamehistory&game=N`** — new routed page `user/gamehistory.php`,
guarded with `requireRoutedView()`. Linked from the `$menutabs` set already shared
by `addscoresheet`, `addplayerlists`, `addresult` and `addspirit`. Columns: time,
user, source, change. Restorable rows carry *View* and *Restore* actions; *View*
renders the snapshot read-only. Keep the presentation terse — no inline legends
where a column heading suffices.

**`?view=admin/gamehistory`** — new page `admin/gamehistory.php` beside
`eventviewer.php`. Superadmin only, filterable by season, game, user and date
range, paged the way `EventList()` / `EventCount()` already are.

Both layouts verified on desktop and mobile.

## Retention

None by age. History lives as long as its game does and is removed by the
`ON DELETE CASCADE` foreign key when a game or season is deleted.

This is a deliberate decision, not an omission. At roughly 5 MB per 200-game
tournament the growth is small, and an age-based cutoff would need either a cron
the project does not have or opportunistic pruning on the request path. If an
installation ever does need a cutoff, a prune function and an admin control can be
added later without touching the schema.

One `INSTALLATION_SETTING`, `DisableGameHistory`, stored in `uo_setting` and
managed in `admin/serverconf.php`, mirrors the existing `DisableVisitorLogging`
escape hatch. `GameHistoryRecord()` and `GameHistorySnapshotIfNeeded()` return
early when it is set, memoizing the lookup the way `IsVisitorLoggingDisabled()`
does. `install.php` is not changed; the default (recording enabled) applies on a
fresh install and an admin can change it afterwards.

## Migration

- `upgrade100()` in `sql/upgrade_db.php`, creating the table, guarded so
  re-running the upgrade is safe.
- `DB_VERSION` 99 → 100 in `lib/database.php`.
- The table plus a `uo_database` seed row for version 100 in
  `sql/ultiorganizer.sql`, so fresh installs match upgraded ones.

## Privacy

`uo_game_history` holds personal data twice over: `user_id` and `ip` identify a
registered user, and snapshots embed player names. Both must be covered.

- Classify `uo_game_history` in `docs/ai/privacy-coverage/tables.txt`.
- Reach it from `lib/privacy.functions.php` in the user export, the player export,
  and the anonymization and deletion flows.
- Anonymizing a user replaces `user_id` and clears `ip` on their rows; anonymizing
  a player must also rewrite the `*_name` fields inside stored snapshot JSON,
  since those are free text rather than foreign keys.
- Update `docs/privacy.md` with the new table's scope and behavior.

## Verification

Repo-mandated checks, each a step in the implementation plan:

- `php docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php`, then the
  `db-upgrade-consistency` skill.
- `docs/ai/review-database-access/SKILL.md` and its checker on the changed files.
- `docs/ai/privacy-coverage/SKILL.md` and its checker.
- `docs/ai/format-and-lint/SKILL.md` on the changed PHP.
- `grammar-terminology-reviewer` subagent for the new user-facing text, then
  `./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh`.
- `docs/ai/css-style-and-lint/SKILL.md` if the new pages add CSS.
- `docs/ai/screenshot-verify/SKILL.md` on both new pages, desktop and mobile.
- `./test:matrix` in the sibling `../ultiorganizer-tests` checkout, pulled to
  `main` first. The harness pins shared `lib/` behavior exactly, and this work
  changes ~25 functions in `lib/game.functions.php`, so `./test:integration` is
  the minimum and the full matrix is required before handback.

Empirical database verification, per the project's rule that query changes are
checked against real data rather than reasoned about: enter a scoresheet through
`user/addscoresheet.php`, re-save it with changes, and confirm the row counts in
`uo_game_history` — one snapshot per save, one change row per mutation — using
`docs/ai/query-database/SKILL.md`. Then restore and confirm `uo_goal`,
`uo_played`, `uo_timeout` and the `uo_game` result match the snapshot exactly.

No release-packaging change is needed: `lib/`, `user/` and `admin/` are existing
runtime paths, and `/docs/** export-ignore` already covers the new documentation.

## Documentation

- New `docs/game-history.md` describing the table, the recording points, the
  restore contract and the two views. Added to the topic lists in **both**
  `AGENTS.md` and `docs/README.md`.
- `docs/scoresheet.md` updated — its "Current persistence behavior" lists now
  understate what each save does.
- `docs/privacy.md` updated as described above.
- `docs/configuration-flags.md` gains `DisableGameHistory` as an
  `INSTALLATION_SETTING` example if the existing list is extended.
