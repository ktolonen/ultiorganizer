# Scoresheet Change History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record every change to a game's scoresheet data with enough detail to audit who changed what, and restore a game to a previous state.

**Architecture:** One new table, `uo_game_history`, with a nullable snapshot column. Every mutation writes one small row holding structured JSON detail — that is the audit trail, one INSERT and zero reads. A full pre-change snapshot is written only at destructive boundaries, memoized per game per request so one bulk save produces one snapshot. All recording lives inside the `lib/` mutators next to the existing permission checks, which is what makes `api/` and every future caller covered automatically.

**Tech Stack:** PHP 8.3, MariaDB, plain SQL through the project's `DBQuery*` helpers. Tests are PHPUnit in the sibling `ktolonen/ultiorganizer-tests` harness checkout.

**Spec:** `docs/superpowers/specs/2026-08-29-scoresheet-change-history-design.md`

## Global Constraints

- PHP code style is PER-CS 2.0. Run `composer format` and `composer lint` on changed files before each commit.
- All SQL lives in `lib/`. Pages never issue queries directly.
- Permission checks live inside reusable `lib/` mutation helpers, not only in routed page handlers.
- New pages use the `?view=...` routing pattern and `requireRoutedView()`.
- Keep edits ASCII. Keep comments proportionate — a guard or a cast needs no comment.
- Reuse existing translated strings where one fits rather than adding a near-duplicate.
- **Never change a mutator's observable behaviour when adding recording.** Capture the existing return value in a local, record, then return that original value. Do not alter a mutator's return value, its `die()` paths, or its early returns. `GameAddScore()`, `GameAddScoreEntry()` and `GameAddDefense()` already end in `$result = DBQuery($query); return $result;`, so the record call goes between those two lines. The harness pins shared `lib/` behaviour exactly, so a changed return value fails `./test:integration`.
- **Two repositories.** SUT changes are committed in `/home/kari/dev/ultiorganizer` on branch `scoresheet-change-history`. Test changes are committed in `/home/kari/dev/ultiorganizer-tests` on `main`. Each task's commit step names which repo.
- The harness builds its database from the SUT's `sql/ultiorganizer.sql` (see `scripts/container_runner.py:331`), so Task 1 must land before any later test can pass.
- Harness fixture facts used throughout: game `700` (hometeam `300`, visitorteam `301`, season `HRN2026`, result 15-11, halftime 35) has 4 `uo_goal` rows and 4 `uo_played` rows. Players: `800` "Ari Ace" (team 300, num 8), `801` "Bea Blade" (team 300, num 12), `802` "Timo Twist" (team 301, num 7), `803` "Nia North" (team 301, num 14). Game `701` has no result.

---

### Task 1: Schema and migration

**Files:**
- Modify: `sql/ultiorganizer.sql`
- Modify: `sql/upgrade_db.php`
- Modify: `lib/database.php:12`

**Interfaces:**
- Consumes: nothing.
- Produces: table `uo_game_history` with columns `history_id`, `game`, `time`, `user_id`, `ip`, `source`, `target`, `action`, `detail`, `has_snapshot`, `snapshot`. `DB_VERSION` becomes `100`.

- [ ] **Step 1: Add the table to the fresh-install schema**

In `sql/ultiorganizer.sql`, insert this block in alphabetical position (after the `uo_game_pool` block, before `uo_gameevent`):

```sql
CREATE TABLE IF NOT EXISTS `uo_game_history` (
  `history_id` int(10) NOT NULL AUTO_INCREMENT,
  `game` int(10) NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` varchar(50) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `source` varchar(20) DEFAULT NULL,
  `target` varchar(20) NOT NULL,
  `action` varchar(10) NOT NULL,
  `detail` text DEFAULT NULL,
  `has_snapshot` tinyint(1) NOT NULL DEFAULT 0,
  `snapshot` mediumtext DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `idx_game_history_game_time` (`game`,`time`),
  KEY `idx_game_history_restorable` (`game`,`has_snapshot`,`time`),
  KEY `idx_game_history_user_time` (`user_id`,`time`),
  CONSTRAINT `fk_game_history_game` FOREIGN KEY (`game`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Update the fresh-install version seed**

Find the `uo_database` seed row in `sql/ultiorganizer.sql` and change the seeded version from `99` to `100`. Search for it with:

```bash
grep -n "uo_database" sql/ultiorganizer.sql
```

- [ ] **Step 3: Add the upgrade step**

In `sql/upgrade_db.php`, after `upgrade99()`, add:

```php
function upgrade100()
{
    DBQuery("CREATE TABLE IF NOT EXISTS `uo_game_history` (
	  `history_id` int(10) NOT NULL AUTO_INCREMENT,
	  `game` int(10) NOT NULL,
	  `time` datetime NOT NULL DEFAULT current_timestamp(),
	  `user_id` varchar(50) NOT NULL,
	  `ip` varchar(45) DEFAULT NULL,
	  `source` varchar(20) DEFAULT NULL,
	  `target` varchar(20) NOT NULL,
	  `action` varchar(10) NOT NULL,
	  `detail` text DEFAULT NULL,
	  `has_snapshot` tinyint(1) NOT NULL DEFAULT 0,
	  `snapshot` mediumtext DEFAULT NULL,
	  PRIMARY KEY (`history_id`),
	  KEY `idx_game_history_game_time` (`game`,`time`),
	  KEY `idx_game_history_restorable` (`game`,`has_snapshot`,`time`),
	  KEY `idx_game_history_user_time` (`user_id`,`time`),
	  CONSTRAINT `fk_game_history_game` FOREIGN KEY (`game`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
```

`CREATE TABLE IF NOT EXISTS` is the guard that makes re-running the upgrade safe.

- [ ] **Step 4: Bump the version constant**

In `lib/database.php:12`, change `define('DB_VERSION', 99);` to `define('DB_VERSION', 100);`

- [ ] **Step 5: Run the schema version contract checker**

```bash
php docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php
```

Expected: PASS. If it reports a mismatch between `DB_VERSION`, `upgrade100()` and the `uo_database` seed, fix the one it names before continuing.

- [ ] **Step 6: Verify the table is actually created**

```bash
cd ../ultiorganizer-tests && ./test:lint
```

Expected: PASS (this recreates the disposable database from the SUT schema, so a syntax error in the new SQL surfaces here).

- [ ] **Step 7: Commit (SUT repo)**

```bash
cd /home/kari/dev/ultiorganizer
git add sql/ultiorganizer.sql sql/upgrade_db.php lib/database.php
git commit -m "Add uo_game_history table and upgrade100()"
```

---

### Task 2: Recording core and snapshot builder

**Files:**
- Create: `lib/gamehistory.functions.php`
- Test: `../ultiorganizer-tests/tests/Integration/Lib/GamehistoryFunctionsLibTest.php`

**Interfaces:**
- Consumes: `uo_game_history` from Task 1.
- Produces:
  - `IsGameHistoryDisabled(): bool`
  - `GameHistorySource(): string`
  - `GameHistorySuppressed($set = null): bool`
  - `GameHistoryRecord($gameId, $target, $action, $detail = []): int|false`
  - `GameHistoryBuildSnapshot($gameId): array`
  - `GameHistorySnapshotIfNeeded($gameId): int|false`

**Include-cycle constraint:** `lib/game.functions.php` will require this file (Task 3), so this file must **not** require `game.functions.php` at the top. Snapshot queries are therefore written directly here rather than reusing `GameScoreBoard()` and friends. `GameHistoryRestore()` in Task 6 breaks the cycle with a lazy `require_once` inside the function body.

- [ ] **Step 1: Scaffold the test file in the harness**

```bash
cd ../ultiorganizer-tests
git checkout main && git pull
./libtest:catalog-refresh
./libtest:scaffold --lib-file gamehistory.functions.php
```

Expected: creates `tests/Integration/Lib/GamehistoryFunctionsLibTest.php`. If `libtest:catalog-refresh` does not see the new lib file yet, create the SUT file as an empty stub with the guard header first, then re-run.

- [ ] **Step 2: Write the failing test**

Replace the scaffolded contents of `../ultiorganizer-tests/tests/Integration/Lib/GamehistoryFunctionsLibTest.php` with:

```php
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use UltiorganizerHarness\Support\LegacyApp;

final class GamehistoryFunctionsLibTest extends TestCase
{
    protected function setUp(): void
    {
        LegacyApp::resetRequestState();
        LegacyApp::loadLibFileUsingProfile('gamehistory.functions.php', 'database_only');

        // IsGameHistoryDisabled() caches in a static, so the setting must be
        // written before the first call in this process.
        DBQuery("DELETE FROM uo_setting WHERE name='DisableGameHistory'");
        DBQuery("INSERT INTO uo_setting (name, value) VALUES ('DisableGameHistory', 'false')");

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['uid'] = 'testuser';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';

        // The snapshot memo lives in the request-local cache, which PHPUnit
        // does not reset between tests in the same process.
        CacheForgetNamespace('game_history_snapshot');
    }

    protected function tearDown(): void
    {
        DBQuery("DELETE FROM uo_game_history WHERE game IN (700, 701)");
        unset($_SESSION['uid']);
        LegacyApp::closeDatabaseConnection();
    }

    public function testRecordStoresSessionUserIpAndJsonDetail(): void
    {
        $id = (int) GameHistoryRecord(700, 'result', 'update', ['home' => 15, 'away' => 11]);
        $this->assertGreaterThan(0, $id);

        $row = DBQueryToRow(
            "SELECT game, user_id, ip, target, action, detail, has_snapshot, snapshot
             FROM uo_game_history WHERE history_id=$id"
        );

        $this->assertSame('700', (string) $row['game']);
        $this->assertSame('testuser', $row['user_id']);
        $this->assertSame('203.0.113.7', $row['ip']);
        $this->assertSame('result', $row['target']);
        $this->assertSame('update', $row['action']);
        $this->assertSame(['home' => 15, 'away' => 11], json_decode($row['detail'], true));
        $this->assertSame('0', (string) $row['has_snapshot']);
        $this->assertNull($row['snapshot']);
    }

    public function testRecordFallsBackToUnknownUserWithoutSession(): void
    {
        unset($_SESSION['uid']);
        $id = (int) GameHistoryRecord(700, 'goal', 'add', ['num' => 5]);
        $row = DBQueryToRow("SELECT user_id FROM uo_game_history WHERE history_id=$id");
        $this->assertSame('unknown', $row['user_id']);
    }

    public function testRecordRejectsInvalidGameId(): void
    {
        $this->assertFalse(GameHistoryRecord(0, 'goal', 'add', []));
    }

    public function testRecordIsSuppressedWhileTheSuppressionFlagIsSet(): void
    {
        GameHistorySuppressed(true);
        try {
            $this->assertFalse(GameHistoryRecord(700, 'goal', 'add', ['num' => 1]));
        } finally {
            GameHistorySuppressed(false);
        }
        $count = (int) DBQueryToValue("SELECT COUNT(*) FROM uo_game_history WHERE game=700");
        $this->assertSame(0, $count);
    }

    public function testBuildSnapshotCapturesGameScalarsGoalsAndPlayers(): void
    {
        $snapshot = GameHistoryBuildSnapshot(700);

        $this->assertSame(1, $snapshot['v']);
        $this->assertSame(15, $snapshot['game']['homescore']);
        $this->assertSame(11, $snapshot['game']['visitorscore']);
        $this->assertSame(35, $snapshot['game']['halftime']);

        $this->assertCount(4, $snapshot['goals']);
        $first = $snapshot['goals'][0];
        $this->assertSame(1, $first['num']);
        $this->assertSame(800, $first['scorer']);
        $this->assertSame('Ari Ace', $first['scorer_name']);
        // Jersey number comes from uo_played for this game, not uo_player.
        $this->assertSame(8, $first['scorer_num']);
        $this->assertSame(801, $first['assist']);
        $this->assertSame('Bea Blade', $first['assist_name']);
        $this->assertSame(12, $first['assist_num']);

        // Goal 3 is a callahan by home player 801.
        $this->assertSame(1, $snapshot['goals'][2]['iscallahan']);

        $this->assertCount(4, $snapshot['played']);
        $players = array_column($snapshot['played'], 'name', 'player');
        $this->assertSame('Timo Twist', $players[802]);
    }

    public function testSnapshotIfNeededWritesOneRestorableRowPerGamePerRequest(): void
    {
        $first = (int) GameHistorySnapshotIfNeeded(700);
        $this->assertGreaterThan(0, $first);

        // The second call is memoized: it returns the same id and writes no
        // second row.
        $this->assertSame($first, (int) GameHistorySnapshotIfNeeded(700));

        $rows = DBQueryToArray(
            "SELECT target, action, has_snapshot, snapshot FROM uo_game_history WHERE game=700"
        );
        $this->assertCount(1, $rows);
        $this->assertSame('snapshot', $rows[0]['target']);
        $this->assertSame('capture', $rows[0]['action']);
        $this->assertSame('1', (string) $rows[0]['has_snapshot']);

        $stored = json_decode($rows[0]['snapshot'], true);
        $this->assertSame(15, $stored['game']['homescore']);
        $this->assertCount(4, $stored['goals']);
    }

    public function testIsGameHistoryDisabledReflectsTheSeededSetting(): void
    {
        // IsGameHistoryDisabled() caches in a static for the life of the
        // process, so only the seeded value is observable here. The
        // recording-off path is exercised through GameHistorySuppressed()
        // above; this pins the setting parsing itself.
        $this->assertFalse(IsGameHistoryDisabled());
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: FAIL — `Call to undefined function GameHistoryRecord()`.

- [ ] **Step 4: Write the implementation**

Create `lib/gamehistory.functions.php`:

```php
<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/cache.functions.php';
require_once __DIR__ . '/comment.functions.php';

function IsGameHistoryDisabled()
{
    static $disabled = null;

    if ($disabled !== null) {
        return $disabled;
    }

    $value = DBQueryToValue("SELECT value FROM uo_setting WHERE name='DisableGameHistory'");
    if ($value === null || $value === false) {
        $disabled = false;
        return $disabled;
    }

    $normalized = strtolower(trim((string) $value));
    $disabled = in_array($normalized, ["1", "true", "yes", "on", "enabled"], true);
    return $disabled;
}

/**
 * Resolve the entry point that is performing the change.
 *
 * Each app entry point defines UO_APP_SOURCE. Deriving the value here rather
 * than passing it from every call site is what keeps api/ and future callers
 * attributed correctly without touching them.
 */
function GameHistorySource()
{
    if (defined('UO_APP_SOURCE')) {
        return substr((string) UO_APP_SOURCE, 0, 20);
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? "";
    foreach (["api", "scorekeeper", "spiritkeeper", "mobile", "admin"] as $app) {
        if (strpos($script, '/' . $app . '/') !== false) {
            return $app;
        }
    }
    return "user";
}

function GameHistorySuppressed($set = null)
{
    static $suppressed = false;

    if ($set !== null) {
        $suppressed = (bool) $set;
    }
    return $suppressed;
}

function GameHistoryRecord($gameId, $target, $action, $detail = [])
{
    if (IsGameHistoryDisabled() || GameHistorySuppressed()) {
        return false;
    }

    $gameId = (int) $gameId;
    if ($gameId <= 0) {
        return false;
    }

    $userId = !empty($_SESSION['uid']) ? $_SESSION['uid'] : "unknown";
    $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "";
    $json = json_encode($detail, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = "";
    }

    $query = sprintf(
        "INSERT INTO uo_game_history (game, user_id, ip, source, target, action, detail)
			VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s')",
        $gameId,
        DBEscapeString(substr((string) $userId, 0, 50)),
        DBEscapeString(substr($ip, 0, 45)),
        DBEscapeString(GameHistorySource()),
        DBEscapeString(substr((string) $target, 0, 20)),
        DBEscapeString(substr((string) $action, 0, 10)),
        DBEscapeString($json),
    );
    return DBQueryInsert($query);
}

function GameHistoryBuildSnapshot($gameId)
{
    $gameId = (int) $gameId;

    $game = DBQueryToRow(sprintf(
        "SELECT homescore, visitorscore, isongoing, hasstarted, forfeit, official, halftime
			FROM uo_game WHERE game_id=%d",
        $gameId,
    ));

    $goals = DBQueryToArray(sprintf(
        "SELECT g.num, g.assist, g.scorer, g.time, g.homescore, g.visitorscore,
			g.ishomegoal, g.iscallahan,
			pla.num AS assist_num, CONCAT_WS(' ', pa.firstname, pa.lastname) AS assist_name,
			pls.num AS scorer_num, CONCAT_WS(' ', ps.firstname, ps.lastname) AS scorer_name
		FROM uo_goal g
		LEFT JOIN uo_player pa ON pa.player_id=g.assist
		LEFT JOIN uo_played pla ON pla.player=g.assist AND pla.game=g.game
		LEFT JOIN uo_player ps ON ps.player_id=g.scorer
		LEFT JOIN uo_played pls ON pls.player=g.scorer AND pls.game=g.game
		WHERE g.game=%d ORDER BY g.num",
        $gameId,
    ));

    $played = DBQueryToArray(sprintf(
        "SELECT pd.player, p.team, pd.num, CONCAT_WS(' ', p.firstname, p.lastname) AS name,
			pd.captain, pd.spirit_captain, pd.accredited, pd.acknowledged
		FROM uo_played pd
		INNER JOIN uo_player p ON p.player_id=pd.player
		WHERE pd.game=%d ORDER BY p.team, pd.num",
        $gameId,
    ));

    $defenses = DBQueryToArray(sprintf(
        "SELECT num, author, time, iscallahan, iscaught, ishomedefense
			FROM uo_defense WHERE game=%d ORDER BY num",
        $gameId,
    ));

    $timeouts = DBQueryToArray(sprintf(
        "SELECT num, time, ishome FROM uo_timeout WHERE game=%d ORDER BY num",
        $gameId,
    ));

    $spiritTimeouts = DBQueryToArray(sprintf(
        "SELECT num, time, ishome FROM uo_spirit_timeout WHERE game=%d ORDER BY num",
        $gameId,
    ));

    // Media links live in uo_gameevent too, but are guarded by hasAddMediaRight()
    // rather than hasEditGameEventsRight(), so a restore must never rewrite them.
    $events = DBQueryToArray(sprintf(
        "SELECT num, time, type, ishome, info FROM uo_gameevent
			WHERE game=%d AND type<>'media' ORDER BY num",
        $gameId,
    ));

    return [
        'v' => 1,
        'game' => GameHistoryIntFields($game, ['homescore', 'visitorscore', 'isongoing',
            'hasstarted', 'forfeit', 'halftime']),
        'goals' => GameHistoryIntRows($goals, ['num', 'assist', 'scorer', 'time', 'homescore',
            'visitorscore', 'ishomegoal', 'iscallahan', 'assist_num', 'scorer_num']),
        'played' => GameHistoryIntRows($played, ['player', 'team', 'num', 'captain',
            'spirit_captain', 'accredited', 'acknowledged']),
        'defenses' => GameHistoryIntRows($defenses, ['num', 'author', 'time', 'iscallahan',
            'iscaught', 'ishomedefense']),
        'timeouts' => GameHistoryIntRows($timeouts, ['num', 'time', 'ishome']),
        'spirit_timeouts' => GameHistoryIntRows($spiritTimeouts, ['num', 'time', 'ishome']),
        'events' => GameHistoryIntRows($events, ['num', 'time', 'ishome']),
        'comment' => CommentRaw(COMMENT_TYPE_GAME, $gameId),
    ];
}

/**
 * MySQL returns every column as a string. Snapshots are compared and restored
 * field by field, so the numeric columns are cast once here instead of at each
 * later reader.
 */
function GameHistoryIntFields($row, $fields)
{
    if (!is_array($row)) {
        return [];
    }
    foreach ($fields as $field) {
        if (array_key_exists($field, $row)) {
            $row[$field] = $row[$field] === null ? null : (int) $row[$field];
        }
    }
    return $row;
}

function GameHistoryIntRows($rows, $fields)
{
    if (!is_array($rows)) {
        return [];
    }
    foreach ($rows as $i => $row) {
        $rows[$i] = GameHistoryIntFields($row, $fields);
    }
    return $rows;
}

/**
 * Capture the current scoresheet once per game per request.
 *
 * A desktop save calls three destructive helpers in sequence, and all three
 * must share one restore point. The request-local cache from
 * cache.functions.php is the memo, so the second and third calls return the
 * first call's history id without writing a second row.
 */
function GameHistorySnapshotIfNeeded($gameId)
{
    if (IsGameHistoryDisabled() || GameHistorySuppressed()) {
        return false;
    }

    $gameId = (int) $gameId;
    if ($gameId <= 0) {
        return false;
    }

    return CacheRemember("game_history_snapshot", $gameId, function () use ($gameId) {
        return GameHistoryWriteSnapshot($gameId);
    });
}

function GameHistoryWriteSnapshot($gameId)
{
    $gameId = (int) $gameId;

    $json = json_encode(GameHistoryBuildSnapshot($gameId), JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $userId = !empty($_SESSION['uid']) ? $_SESSION['uid'] : "unknown";
    $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "";

    $query = sprintf(
        "INSERT INTO uo_game_history (game, user_id, ip, source, target, action, has_snapshot, snapshot)
			VALUES (%d, '%s', '%s', '%s', 'snapshot', 'capture', 1, '%s')",
        $gameId,
        DBEscapeString(substr((string) $userId, 0, 50)),
        DBEscapeString(substr($ip, 0, 45)),
        DBEscapeString(GameHistorySource()),
        DBEscapeString($json),
    );
    return DBQueryInsert($query);
}
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: PASS, no failures.

- [ ] **Step 6: Format and lint**

```bash
cd /home/kari/dev/ultiorganizer
composer format && composer lint
```

Expected: no findings on `lib/gamehistory.functions.php`.

- [ ] **Step 7: Commit (both repos)**

```bash
cd /home/kari/dev/ultiorganizer
git add lib/gamehistory.functions.php
git commit -m "Add game history recording core and snapshot builder"

cd ../ultiorganizer-tests
git add tests/Integration/Lib/GamehistoryFunctionsLibTest.php config/lib-test-catalog.json
git commit -m "Add lib tests for gamehistory.functions.php"
```

---

### Task 3: Wire result and goal mutators, and declare the app sources

**Files:**
- Modify: `lib/game.functions.php` (header require; `GameUpdateResult`, `GameSetResult`, `GameClearResult`, `GameSetForfeit`, `GameSyncResultFromGoals`, `GameAddScore`, `GameAddScoreEntry`, `GameRemoveScore`, `GameRemoveAllScores`, `GameSetDefenses`, `GameAddDefense`, `GameRemoveAllDefenses`)
- Modify: `index.php`, `api/index.php`, `scorekeeper/index.php`, `mobile/index.php`, `spiritkeeper/index.php`
- Test: `../ultiorganizer-tests/tests/Integration/Lib/GamehistoryFunctionsLibTest.php`

**Interfaces:**
- Consumes: `GameHistoryRecord()`, `GameHistorySnapshotIfNeeded()` from Task 2.
- Produces: `UO_APP_SOURCE` constant defined by every app entry point. Result and goal mutations recorded with targets `result`, `goal`, `defense`.

- [ ] **Step 1: Write the failing test**

Append these methods to `GamehistoryFunctionsLibTest`, and extend `setUp()` to also load `game.functions.php`:

Change the `setUp()` load line to:

```php
        // pool_stack brings pool/series/season/statistical, which the restore
        // guards in Task 6 (IsPoolLocked, IsSeasonStatsCalculated,
        // isEventReadonly) need. Loading it here keeps Tasks 3-6 on one setUp.
        LegacyApp::loadLibFilesUsingProfile(
            ['user.functions.php', 'gamehistory.functions.php', 'game.functions.php'],
            'pool_stack'
        );
        $_SESSION['userproperties']['userrole']['superadmin'] = true;
```

Add to `tearDown()`, before the existing deletes:

```php
        // Task 3 tests mutate game 701, which the baseline fixture leaves unplayed.
        DBQuery("DELETE FROM uo_goal WHERE game=701");
        DBQuery("UPDATE uo_game SET homescore=NULL, visitorscore=NULL, isongoing=0, hasstarted=0 WHERE game_id=701");
```

Add these test methods:

```php
    public function testGameSetResultRecordsTheFinalScore(): void
    {
        GameSetResult(701, 13, 9, false);

        $row = DBQueryToRow(
            "SELECT target, action, detail FROM uo_game_history
             WHERE game=701 AND target='result' ORDER BY history_id DESC LIMIT 1"
        );

        $this->assertSame('result', $row['target']);
        $this->assertSame('update', $row['action']);
        $detail = json_decode($row['detail'], true);
        $this->assertSame(13, $detail['home']);
        $this->assertSame(9, $detail['away']);
        $this->assertSame('final', $detail['state']);
    }

    public function testGameSetResultAlsoWritesARestorableSnapshot(): void
    {
        GameSetResult(701, 13, 9, false);

        $count = (int) DBQueryToValue(
            "SELECT COUNT(*) FROM uo_game_history WHERE game=701 AND has_snapshot=1"
        );
        $this->assertSame(1, $count);
    }

    public function testGameAddScoreEntryRecordsOneGoalRowPerPoint(): void
    {
        GameAddScoreEntry([
            'game' => 701, 'num' => 1, 'assist' => 802, 'scorer' => 803,
            'time' => 60, 'homescore' => 0, 'visitorscore' => 1,
            'ishomegoal' => 0, 'iscallahan' => 0,
        ]);

        $rows = DBQueryToArray(
            "SELECT action, detail FROM uo_game_history WHERE game=701 AND target='goal'"
        );
        $this->assertCount(1, $rows);
        $this->assertSame('add', $rows[0]['action']);

        $detail = json_decode($rows[0]['detail'], true);
        $this->assertSame(1, $detail['num']);
        $this->assertSame(803, $detail['scorer']);
        $this->assertSame(802, $detail['assist']);
        $this->assertSame('0-1', $detail['score']);
    }

    public function testGameRemoveAllScoresSnapshotsBeforeClearingAndRecordsTheCount(): void
    {
        GameAddScoreEntry([
            'game' => 701, 'num' => 1, 'assist' => 802, 'scorer' => 803,
            'time' => 60, 'homescore' => 0, 'visitorscore' => 1,
            'ishomegoal' => 0, 'iscallahan' => 0,
        ]);

        GameRemoveAllScores(701);

        $snapshot = DBQueryToRow(
            "SELECT snapshot FROM uo_game_history
             WHERE game=701 AND has_snapshot=1 ORDER BY history_id DESC LIMIT 1"
        );
        // The snapshot holds the pre-clear state, so the goal is still in it.
        $stored = json_decode($snapshot['snapshot'], true);
        $this->assertCount(1, $stored['goals']);

        $clear = DBQueryToRow(
            "SELECT action, detail FROM uo_game_history
             WHERE game=701 AND target='goal' AND action='clear' ORDER BY history_id DESC LIMIT 1"
        );
        $this->assertSame(1, json_decode($clear['detail'], true)['removed']);
    }

    public function testBulkRewriteWritesExactlyOneSnapshotPerRequest(): void
    {
        // A desktop save calls three destructive helpers in sequence. The
        // per-request memo must collapse them to a single restore point.
        GameRemoveAllScores(701);
        GameRemoveAllTimeouts(701);
        GameRemoveAllSpiritTimeouts(701);

        $count = (int) DBQueryToValue(
            "SELECT COUNT(*) FROM uo_game_history WHERE game=701 AND has_snapshot=1"
        );
        $this->assertSame(1, $count);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: FAIL — the new assertions find no `uo_game_history` rows.

- [ ] **Step 3: Require the new lib file from game.functions.php**

In `lib/game.functions.php`, after the existing requires (around line 8), add:

```php
require_once __DIR__ . '/gamehistory.functions.php';
```

- [ ] **Step 4: Record the result mutations**

In `GameUpdateResult()`, after `$result = DBQuery($query);` and before `return $result;`:

```php
        GameHistoryRecord($gameId, "result", "update", [
            'home' => (int) $home,
            'away' => (int) $away,
            'state' => "ongoing",
        ]);
```

In `GameSetResult()`, replace the existing `LogGameUpdate($gameId, "result: $home - $away");` line position as follows — keep the `LogGameUpdate()` call exactly where it is, add the snapshot before the write and the record after it:

```php
        LogGameUpdate($gameId, "result: $home - $away");
        GameHistorySnapshotIfNeeded($gameId);
        $query = sprintf(
            "UPDATE uo_game SET homescore='%s', visitorscore='%s', isongoing='0', hasstarted='2', timer_start=NULL, timer_pause_start=NULL, timer_paused_duration=0 WHERE game_id='%s'",
            DBEscapeString($home),
            DBEscapeString($away),
            DBEscapeString($gameId),
        );
        $result = DBQuery($query);
        GameHistoryRecord($gameId, "result", "update", [
            'home' => (int) $home,
            'away' => (int) $away,
            'state' => "final",
        ]);

        if ($updatePools) {
            $poolId = GamePool($gameId);
            ResolvePoolStandings($poolId);
            PoolResolvePlayed($poolId);
        }
        return $result;
```

The `if ($updatePools)` tail and the `return $result;` are the function's existing code, reproduced here so a literal paste does not drop pool resolution.

In `GameClearResult()`, after `LogGameUpdate($gameId, "result cleared");` add `GameHistorySnapshotIfNeeded($gameId);`, and after `$result = DBQuery($query);` add:

```php
        GameHistoryRecord($gameId, "result", "clear", []);
```

In `GameSetForfeit()`, after `$result = DBQuery($query);`:

```php
    GameHistoryRecord($gameId, "forfeit", "update", ['forfeit' => $labels[$forfeit]]);
```

In `GameSyncResultFromGoals()`, after its existing `LogGameUpdate($gameId, "result from goals: $home - $away");`:

```php
    GameHistoryRecord($gameId, "result", "update", [
        'home' => (int) $home,
        'away' => (int) $away,
        'state' => "from_goals",
    ]);
```

- [ ] **Step 5: Record the goal mutations**

In `GameAddScore()`, after the insert succeeds:

```php
        GameHistoryRecord($gameId, "goal", "add", [
            'num' => (int) $number,
            'scorer' => $goal === null ? null : (int) $goal,
            'assist' => $pass === null ? null : (int) $pass,
            'time' => (int) $time,
            'score' => (int) $hscores . "-" . (int) $ascores,
            'home' => $home ? 1 : 0,
            'callahan' => $iscallahan ? 1 : 0,
        ]);
```

In `GameAddScoreEntry()`, after the insert succeeds — this one receives an array, so read the fields from it:

```php
        GameHistoryRecord($uo_goal['game'], "goal", "add", [
            'num' => (int) $uo_goal['num'],
            'scorer' => isset($uo_goal['scorer']) ? (int) $uo_goal['scorer'] : null,
            'assist' => isset($uo_goal['assist']) ? (int) $uo_goal['assist'] : null,
            'time' => (int) ($uo_goal['time'] ?? 0),
            'score' => (int) $uo_goal['homescore'] . "-" . (int) $uo_goal['visitorscore'],
            'home' => !empty($uo_goal['ishomegoal']) ? 1 : 0,
            'callahan' => !empty($uo_goal['iscallahan']) ? 1 : 0,
        ]);
```

In `GameRemoveScore()`, after the delete succeeds:

```php
    GameHistoryRecord($gameId, "goal", "remove", ['num' => (int) $num]);
```

In `GameRemoveAllScores()`, count the rows before deleting so the record is meaningful, snapshot first, then delete:

```php
    $removed = (int) DBQueryToValue(sprintf("SELECT COUNT(*) FROM uo_goal WHERE game=%d", (int) $gameId));
    GameHistorySnapshotIfNeeded($gameId);
```

and after the delete:

```php
    GameHistoryRecord($gameId, "goal", "clear", ['removed' => $removed]);
```

- [ ] **Step 6: Record the defense mutations**

Apply the same shape to `GameSetDefenses()` (target `defense`, action `update`, detail `['home' => (int) $home, 'away' => (int) $away]`), `GameAddDefense()` (action `add`, detail with `num`, `player`, `time`, `caught`, `callahan`), and `GameRemoveAllDefenses()` (count first, `GameHistorySnapshotIfNeeded()` before the delete, action `clear` with `removed` after it).

- [ ] **Step 7: Declare the app source in every entry point**

Add near the top of each entry point, before any `lib/` include:

```php
define('UO_APP_SOURCE', 'user');        // index.php
define('UO_APP_SOURCE', 'api');         // api/index.php
define('UO_APP_SOURCE', 'scorekeeper'); // scorekeeper/index.php
define('UO_APP_SOURCE', 'mobile');      // mobile/index.php
define('UO_APP_SOURCE', 'spiritkeeper');// spiritkeeper/index.php
```

`index.php` serves both `?view=user/...` and `?view=admin/...`, so it declares `user` and `GameHistorySource()`'s script-path fallback is never reached in practice. That fallback exists for CLI and cron-style callers.

- [ ] **Step 8: Run the test to verify it passes**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: PASS, no failures.

- [ ] **Step 9: Run the integration suite**

```bash
cd ../ultiorganizer-tests && ./test:integration
```

Expected: PASS. The harness pins shared `lib/` behaviour exactly, and this task changed twelve functions in `lib/game.functions.php`, so a regression here is the signal that a record call changed a return value or fired before a permission check.

- [ ] **Step 10: Format, lint, and commit (both repos)**

```bash
cd /home/kari/dev/ultiorganizer
composer format && composer lint
git add lib/game.functions.php index.php api/index.php scorekeeper/index.php mobile/index.php spiritkeeper/index.php
git commit -m "Record result, goal and defense changes in game history"

cd ../ultiorganizer-tests
git add tests/Integration/Lib/GamehistoryFunctionsLibTest.php
git commit -m "Cover game history recording of result and goal mutations"
```

---

### Task 4: Wire the remaining mutators

**Files:**
- Modify: `lib/game.functions.php` (`GameAddPlayer`, `GameAddNewPlayer`, `GameRemovePlayer`, `GameRemoveAllPlayers`, `GameSetPlayerNumber`, `GameSetRolePlayers`, `GameAddTimeout`, `GameRemoveAllTimeouts`, `GameAddSpiritTimeout`, `GameRemoveAllSpiritTimeouts`, `GameSetScoreSheetKeeper`, `GameSetHalftime`, `GameSetStartingTeam`, `GameSetCapEvent`, `GameRemoveCapEvent`, `AddGameMediaEvent`, `RemoveGameMediaEvent`)
- Modify: `lib/comment.functions.php` (`SetGameComment`)
- Test: `../ultiorganizer-tests/tests/Integration/Lib/GamehistoryFunctionsLibTest.php`

**Interfaces:**
- Consumes: `GameHistoryRecord()`, `GameHistorySnapshotIfNeeded()`.
- Produces: recording for targets `played`, `timeout`, `spirit_timeout`, `official`, `halftime`, `gameevent`, `mediaevent`, `comment`.

- [ ] **Step 1: Write the failing test**

Append to `GamehistoryFunctionsLibTest`:

```php
    public function testGameAddPlayerRecordsThePlayerAndJerseyNumber(): void
    {
        GameAddPlayer(701, 800, 8);

        $row = DBQueryToRow(
            "SELECT action, detail FROM uo_game_history
             WHERE game=701 AND target='played' ORDER BY history_id DESC LIMIT 1"
        );
        $this->assertSame('add', $row['action']);
        $detail = json_decode($row['detail'], true);
        $this->assertSame(800, $detail['player']);
        $this->assertSame(8, $detail['num']);
    }

    public function testGameSetPlayerNumberRecordsTheNewNumber(): void
    {
        GameAddPlayer(701, 800, 8);
        GameSetPlayerNumber(701, 800, 21);

        $row = DBQueryToRow(
            "SELECT action, detail FROM uo_game_history
             WHERE game=701 AND target='played' AND action='update' ORDER BY history_id DESC LIMIT 1"
        );
        $detail = json_decode($row['detail'], true);
        $this->assertSame(800, $detail['player']);
        $this->assertSame(21, $detail['num']);
    }

    public function testGameSetHalftimeRecordsTheHalftimeValue(): void
    {
        GameSetHalftime(701, 1800);

        $row = DBQueryToRow(
            "SELECT target, action, detail FROM uo_game_history
             WHERE game=701 AND target='halftime' ORDER BY history_id DESC LIMIT 1"
        );
        $this->assertSame('update', $row['action']);
        $this->assertSame(1800, json_decode($row['detail'], true)['time']);
    }

    public function testGameSetScoreSheetKeeperRecordsTheOfficialName(): void
    {
        GameSetScoreSheetKeeper(701, 'Official 1');

        $row = DBQueryToRow(
            "SELECT target, detail FROM uo_game_history
             WHERE game=701 AND target='official' ORDER BY history_id DESC LIMIT 1"
        );
        $this->assertSame('Official 1', json_decode($row['detail'], true)['name']);
    }

    public function testGameAddTimeoutRecordsTheTimeoutSide(): void
    {
        GameAddTimeout(701, 1, 300, true);

        $row = DBQueryToRow(
            "SELECT target, action, detail FROM uo_game_history
             WHERE game=701 AND target='timeout' ORDER BY history_id DESC LIMIT 1"
        );
        $this->assertSame('add', $row['action']);
        $detail = json_decode($row['detail'], true);
        $this->assertSame(300, $detail['time']);
        $this->assertSame(1, $detail['home']);
    }
```

Extend `tearDown()` with:

```php
        DBQuery("DELETE FROM uo_played WHERE game=701");
        DBQuery("DELETE FROM uo_timeout WHERE game=701");
        DBQuery("DELETE FROM uo_spirit_timeout WHERE game=701");
        DBQuery("DELETE FROM uo_gameevent WHERE game=701");
        DBQuery("UPDATE uo_game SET halftime=35, official=NULL WHERE game_id=701");
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: FAIL on the five new methods.

- [ ] **Step 3: Record the player-list mutations**

After each write succeeds:

- `GameAddPlayer($gameId, $playerId, $number)`: `GameHistoryRecord($gameId, "played", "add", ['player' => (int) $playerId, 'num' => (int) $number]);`
- `GameAddNewPlayer(...)`: same shape, using the id returned by the insert and the passed `$number`, plus `'created' => 1`.
- `GameRemovePlayer($gameId, $playerId)`: `GameHistoryRecord($gameId, "played", "remove", ['player' => (int) $playerId]);`
- `GameSetPlayerNumber($gameId, $playerId, $number)`: `GameHistoryRecord($gameId, "played", "update", ['player' => (int) $playerId, 'num' => (int) $number]);`
- `GameRemoveAllPlayers($gameId)`: count first, `GameHistorySnapshotIfNeeded($gameId);` before the delete, then `GameHistoryRecord($gameId, "played", "clear", ['removed' => $removed]);`
- `GameSetRolePlayers($gameId, $teamId, $roleColumn, $playerIds)`: `GameHistoryRecord($gameId, "played", "update", ['team' => (int) $teamId, 'role' => $roleColumn, 'players' => array_map('intval', $playerIds)]);`

`GameSetRolePlayers()` is the single recording point for captains and spirit captains, because `GameSetCaptains()` and `GameSetSpiritCaptains()` both delegate to it.

- [ ] **Step 4: Record the timeout mutations**

- `GameAddTimeout($gameId, $number, $time, $home)`: `GameHistoryRecord($gameId, "timeout", "add", ['num' => (int) $number, 'time' => (int) $time, 'home' => $home ? 1 : 0]);`
- `GameAddSpiritTimeout(...)`: identical with target `spirit_timeout`.
- `GameRemoveAllTimeouts($gameId)` and `GameRemoveAllSpiritTimeouts($gameId)`: count first, `GameHistorySnapshotIfNeeded($gameId);` before the delete, then a `clear` record with `['removed' => $removed]`.

- [ ] **Step 5: Record the metadata mutations**

- `GameSetScoreSheetKeeper($gameId, $name)`: `GameHistoryRecord($gameId, "official", "update", ['name' => (string) $name]);`
- `GameSetHalftime($gameId, $time)`: `GameHistoryRecord($gameId, "halftime", "update", ['time' => (int) $time]);`
- `GameSetStartingTeam($gameId, $home)`: `GameHistoryRecord($gameId, "gameevent", "update", ['type' => "start", 'home' => $home ? 1 : 0]);`
- `GameSetCapEvent($gameId, $type, $time, $target)`: `GameHistoryRecord($gameId, "gameevent", "update", ['type' => (string) $type, 'time' => (int) $time]);`
- `GameRemoveCapEvent($gameId, $type)`: `GameHistoryRecord($gameId, "gameevent", "remove", ['type' => (string) $type]);`
- `AddGameMediaEvent($gameId, $time, $urlId)`: `GameHistoryRecord($gameId, "mediaevent", "add", ['url' => (int) $urlId, 'time' => (int) $time]);`
- `RemoveGameMediaEvent($gameId, $urlId)`: `GameHistoryRecord($gameId, "mediaevent", "remove", ['url' => (int) $urlId]);`

Media events are recorded but deliberately excluded from snapshots and restore, because they are guarded by `hasAddMediaRight()` rather than `hasEditGameEventsRight()`.

- [ ] **Step 6: Record the game note**

In `lib/comment.functions.php`, inside `SetGameComment()`, after the write succeeds and only for game notes:

```php
    if ($type == COMMENT_TYPE_GAME && function_exists('GameHistoryRecord')) {
        GameHistoryRecord($gameId, "comment", $change['action'] === "delete" ? "remove" : "update", [
            'length' => strlen((string) $comment),
        ]);
    }
```

The `function_exists()` guard is required here and nowhere else: `comment.functions.php` is loaded by `common.functions.php` early in the include chain, before `gamehistory.functions.php` is reached. The comment text itself is not recorded — the snapshot already carries it, and duplicating free text into the audit row would widen the privacy surface for no gain.

- [ ] **Step 7: Run the tests to verify they pass**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: PASS, no failures.

- [ ] **Step 8: Run the integration suite**

```bash
cd ../ultiorganizer-tests && ./test:integration
```

Expected: PASS.

- [ ] **Step 9: Format, lint, and commit (both repos)**

```bash
cd /home/kari/dev/ultiorganizer
composer format && composer lint
git add lib/game.functions.php lib/comment.functions.php
git commit -m "Record player list, timeout, metadata and note changes in game history"

cd ../ultiorganizer-tests
git add tests/Integration/Lib/GamehistoryFunctionsLibTest.php
git commit -m "Cover game history recording of remaining scoresheet mutators"
```

---

### Task 5: Read API and detail formatting

**Files:**
- Modify: `lib/gamehistory.functions.php`
- Test: `../ultiorganizer-tests/tests/Integration/Lib/GamehistoryFunctionsLibTest.php`

**Interfaces:**
- Consumes: recording from Tasks 3 and 4.
- Produces:
  - `GameHistoryList($gameId, $limit = null, $offset = null): array`
  - `GameHistoryCount($gameId): int`
  - `GameHistoryAll($filters, $limit = null, $offset = null): array`
  - `GameHistoryAllCount($filters): int`
  - `GameHistoryEntry($historyId): array|null`
  - `GameHistoryFormatDetail($row): string`

`$filters` is an associative array accepting the keys `season`, `game`, `user`, `from` and `to`. Every key is optional.

- [ ] **Step 1: Write the failing test**

`GameHistoryFormatDetail()` calls `_()`. If the gettext extension is absent in the
harness container that function is undefined, so add this shim at the top of the
test file, after the `use` statements and before the class — the same technique
`CommentFunctionsLibTest` uses for `utf8entities()`:

```php
if (!function_exists('_')) {
    function _(string $text): string
    {
        return $text;
    }
}
```

Then append to `GamehistoryFunctionsLibTest`:

```php
    public function testListReturnsNewestFirstAndOmitsTheSnapshotPayload(): void
    {
        GameHistoryRecord(700, 'result', 'update', ['home' => 1, 'away' => 0, 'state' => 'ongoing']);
        GameHistoryRecord(700, 'goal', 'add', ['num' => 2, 'score' => '1-1']);

        $rows = GameHistoryList(700);

        $this->assertCount(2, $rows);
        $this->assertSame('goal', $rows[0]['target']);
        $this->assertSame('result', $rows[1]['target']);
        $this->assertArrayNotHasKey('snapshot', $rows[0]);
        $this->assertSame('0', (string) $rows[0]['has_snapshot']);
    }

    public function testCountMatchesTheNumberOfRecordedRows(): void
    {
        GameHistoryRecord(700, 'goal', 'add', ['num' => 1]);
        GameHistoryRecord(700, 'goal', 'add', ['num' => 2]);
        $this->assertSame(2, GameHistoryCount(700));
    }

    public function testListDeniesAUserWithoutGameEditRights(): void
    {
        GameHistoryRecord(700, 'goal', 'add', ['num' => 1]);
        $_SESSION['userproperties']['userrole'] = [];
        $_SESSION['uid'] = 'anonymous';
        try {
            $this->assertSame([], GameHistoryList(700));
            $this->assertSame(0, GameHistoryCount(700));
        } finally {
            $_SESSION['uid'] = 'testuser';
            $_SESSION['userproperties']['userrole']['superadmin'] = true;
        }
    }

    public function testEntryReturnsTheDecodedSnapshot(): void
    {
        $id = (int) GameHistorySnapshotIfNeeded(700);
        $entry = GameHistoryEntry($id);

        $this->assertSame('snapshot', $entry['target']);
        $this->assertSame(15, $entry['snapshot']['game']['homescore']);
        $this->assertCount(4, $entry['snapshot']['goals']);
    }

    public function testAllDeniesNonSuperAdmins(): void
    {
        GameHistoryRecord(700, 'goal', 'add', ['num' => 1]);
        $_SESSION['userproperties']['userrole'] = [];
        try {
            $this->assertSame([], GameHistoryAll(['game' => 700]));
            $this->assertSame(0, GameHistoryAllCount(['game' => 700]));
        } finally {
            $_SESSION['userproperties']['userrole']['superadmin'] = true;
        }
    }

    public function testAllFiltersByGame(): void
    {
        GameHistoryRecord(700, 'goal', 'add', ['num' => 1]);
        GameHistoryRecord(701, 'goal', 'add', ['num' => 1]);

        $rows = GameHistoryAll(['game' => 700]);
        $this->assertCount(1, $rows);
        $this->assertSame('700', (string) $rows[0]['game']);
        $this->assertSame(1, GameHistoryAllCount(['game' => 700]));
    }

    public function testFormatDetailRendersEachTargetCompactly(): void
    {
        $this->assertSame(
            'Result 15-11 (final)',
            GameHistoryFormatDetail([
                'target' => 'result',
                'action' => 'update',
                'detail' => json_encode(['home' => 15, 'away' => 11, 'state' => 'final']),
            ])
        );

        $this->assertSame(
            'Point 3: 2-1',
            GameHistoryFormatDetail([
                'target' => 'goal',
                'action' => 'add',
                'detail' => json_encode(['num' => 3, 'score' => '2-1']),
            ])
        );

        $this->assertSame(
            'Points removed: 24',
            GameHistoryFormatDetail([
                'target' => 'goal',
                'action' => 'clear',
                'detail' => json_encode(['removed' => 24]),
            ])
        );
    }
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: FAIL — `Call to undefined function GameHistoryList()`.

- [ ] **Step 3: Write the implementation**

Append to `lib/gamehistory.functions.php`:

```php
function GameHistoryList($gameId, $limit = null, $offset = null)
{
    $gameId = (int) $gameId;
    if (!hasEditGameEventsRight($gameId)) {
        return [];
    }

    $query = sprintf(
        "SELECT history_id, game, time, user_id, ip, source, target, action, detail, has_snapshot
			FROM uo_game_history WHERE game=%d ORDER BY time DESC, history_id DESC",
        $gameId,
    );
    if ($limit !== null) {
        $query .= sprintf(" LIMIT %d", (int) $limit);
        if ($offset !== null) {
            $query .= sprintf(" OFFSET %d", (int) $offset);
        }
    }
    return DBQueryToArray($query);
}

function GameHistoryCount($gameId)
{
    $gameId = (int) $gameId;
    if (!hasEditGameEventsRight($gameId)) {
        return 0;
    }
    return (int) DBQueryToValue(sprintf(
        "SELECT COUNT(*) FROM uo_game_history WHERE game=%d",
        $gameId,
    ));
}

function GameHistoryWhere($filters)
{
    $where = ["1=1"];

    if (!empty($filters['game'])) {
        $where[] = sprintf("h.game=%d", (int) $filters['game']);
    }
    if (!empty($filters['user'])) {
        $where[] = sprintf("h.user_id='%s'", DBEscapeString($filters['user']));
    }
    if (!empty($filters['from'])) {
        $where[] = sprintf("h.time >= '%s'", DBEscapeString($filters['from']));
    }
    if (!empty($filters['to'])) {
        $where[] = sprintf("h.time <= '%s'", DBEscapeString($filters['to']));
    }
    if (!empty($filters['season'])) {
        $where[] = sprintf(
            "h.game IN (SELECT gp.game FROM uo_game_pool gp
				INNER JOIN uo_pool po ON po.pool_id=gp.pool
				INNER JOIN uo_series se ON se.series_id=po.series
				WHERE se.season='%s')",
            DBEscapeString($filters['season']),
        );
    }
    return implode(" AND ", $where);
}

function GameHistoryAll($filters, $limit = null, $offset = null)
{
    if (!isSuperAdmin()) {
        return [];
    }

    $query = sprintf(
        "SELECT h.history_id, h.game, h.time, h.user_id, h.ip, h.source, h.target,
			h.action, h.detail, h.has_snapshot
		FROM uo_game_history h WHERE %s ORDER BY h.time DESC, h.history_id DESC",
        GameHistoryWhere($filters),
    );
    if ($limit !== null) {
        $query .= sprintf(" LIMIT %d", (int) $limit);
        if ($offset !== null) {
            $query .= sprintf(" OFFSET %d", (int) $offset);
        }
    }
    return DBQueryToArray($query);
}

function GameHistoryAllCount($filters)
{
    if (!isSuperAdmin()) {
        return 0;
    }
    return (int) DBQueryToValue(sprintf(
        "SELECT COUNT(*) FROM uo_game_history h WHERE %s",
        GameHistoryWhere($filters),
    ));
}

function GameHistoryEntry($historyId)
{
    $historyId = (int) $historyId;
    $row = DBQueryToRow(sprintf(
        "SELECT history_id, game, time, user_id, ip, source, target, action, detail,
			has_snapshot, snapshot FROM uo_game_history WHERE history_id=%d",
        $historyId,
    ));
    if (!$row) {
        return null;
    }
    if (!hasEditGameEventsRight($row['game'])) {
        return null;
    }

    $row['detail'] = $row['detail'] === null ? [] : json_decode($row['detail'], true);
    $row['snapshot'] = $row['snapshot'] === null ? null : json_decode($row['snapshot'], true);
    return $row;
}

function GameHistoryFormatDetail($row)
{
    $detail = $row['detail'] ?? [];
    if (is_string($detail)) {
        $detail = json_decode($detail, true);
    }
    if (!is_array($detail)) {
        $detail = [];
    }

    $target = $row['target'] ?? "";
    $action = $row['action'] ?? "";

    if ($target == "result" && $action == "clear") {
        return _("Result cleared");
    }
    if ($target == "result") {
        return sprintf(
            "%s %d-%d (%s)",
            _("Result"),
            (int) ($detail['home'] ?? 0),
            (int) ($detail['away'] ?? 0),
            $detail['state'] ?? "",
        );
    }
    if ($target == "goal" && $action == "clear") {
        return sprintf("%s: %d", _("Points removed"), (int) ($detail['removed'] ?? 0));
    }
    if ($target == "goal" && $action == "remove") {
        return sprintf("%s %d", _("Point"), (int) ($detail['num'] ?? 0));
    }
    if ($target == "goal") {
        return sprintf(
            "%s %d: %s",
            _("Point"),
            (int) ($detail['num'] ?? 0),
            $detail['score'] ?? "",
        );
    }
    if ($target == "played" && $action == "clear") {
        return sprintf("%s: %d", _("Players removed"), (int) ($detail['removed'] ?? 0));
    }
    if ($target == "played") {
        return sprintf("%s %d", _("Player"), (int) ($detail['player'] ?? 0));
    }
    if ($target == "snapshot") {
        return _("Saved state");
    }
    if ($target == "restore") {
        return _("Restored");
    }
    if ($target == "halftime") {
        return sprintf("%s %d", _("Halftime"), (int) ($detail['time'] ?? 0));
    }
    if ($target == "official") {
        return sprintf("%s %s", _("Official"), $detail['name'] ?? "");
    }

    return $target;
}
```

`GameHistoryFormatDetail()` renders at display time from structured detail, so an old row follows the reader's language rather than the writer's. `GameHistoryWhere()` is internal to this file and is not part of the page-facing interface.

- [ ] **Step 4: Run the test to verify it passes**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: PASS, no failures.

- [ ] **Step 5: Run the database access boundary checker**

```bash
cd /home/kari/dev/ultiorganizer
php docs/ai/review-database-access/scripts/check-db-access.php --changed
```

Expected: PASS. All SQL is inside `lib/`.

- [ ] **Step 6: Format, lint, and commit (both repos)**

```bash
cd /home/kari/dev/ultiorganizer
composer format && composer lint
git add lib/gamehistory.functions.php
git commit -m "Add game history read API and detail formatting"

cd ../ultiorganizer-tests
git add tests/Integration/Lib/GamehistoryFunctionsLibTest.php
git commit -m "Cover game history read API and detail formatting"
```

---

### Task 6: Restore

**Files:**
- Modify: `lib/gamehistory.functions.php`
- Test: `../ultiorganizer-tests/tests/Integration/Lib/GamehistoryFunctionsLibTest.php`

**Interfaces:**
- Consumes: `GameHistoryEntry()`, `GameHistorySnapshotIfNeeded($gameId, $force)`, `GameHistorySuppressed()`.
- Produces: `GameHistoryRestore($historyId): array` returning `['restored' => bool, 'warnings' => list<string>]`.

- [ ] **Step 1: Write the failing test**

Append to `GamehistoryFunctionsLibTest`:

```php
    public function testRestorePutsBackTheGoalSequenceAndResult(): void
    {
        // Capture game 700 as the fixture leaves it, then damage it.
        $snapshotId = (int) GameHistorySnapshotIfNeeded(700);

        GameRemoveAllScores(700);
        GameSetResult(700, 3, 2, false);
        $this->assertSame(0, (int) DBQueryToValue("SELECT COUNT(*) FROM uo_goal WHERE game=700"));

        $result = GameHistoryRestore($snapshotId);

        $this->assertTrue($result['restored']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(4, (int) DBQueryToValue("SELECT COUNT(*) FROM uo_goal WHERE game=700"));

        $game = DBQueryToRow(
            "SELECT homescore, visitorscore, hasstarted, isongoing FROM uo_game WHERE game_id=700"
        );
        $this->assertSame('15', (string) $game['homescore']);
        $this->assertSame('11', (string) $game['visitorscore']);
        // The fixture is hasstarted=1. GameSetResult() forces 2, so this pins
        // that the snapshot's own flags win.
        $this->assertSame('1', (string) $game['hasstarted']);
        $this->assertSame('0', (string) $game['isongoing']);

        $goal = DBQueryToRow("SELECT assist, scorer, iscallahan FROM uo_goal WHERE game=700 AND num=3");
        $this->assertSame('800', (string) $goal['assist']);
        $this->assertSame('801', (string) $goal['scorer']);
        $this->assertSame('1', (string) $goal['iscallahan']);
    }

    public function testRestoreWritesOneRestoreRowAndOneSnapshotNotAPerGoalTrail(): void
    {
        $snapshotId = (int) GameHistorySnapshotIfNeeded(700);
        GameRemoveAllScores(700);

        // Delete only the change rows -- the snapshot row is what we restore from.
        DBQuery("DELETE FROM uo_game_history WHERE game=700 AND has_snapshot=0");
        GameHistoryRestore($snapshotId);

        // The replay goes through GameAddScoreEntry() four times; suppression
        // must keep those out of the trail.
        $goalRows = (int) DBQueryToValue(
            "SELECT COUNT(*) FROM uo_game_history WHERE game=700 AND target='goal'"
        );
        $this->assertSame(0, $goalRows);

        $restoreRows = (int) DBQueryToValue(
            "SELECT COUNT(*) FROM uo_game_history WHERE game=700 AND target='restore'"
        );
        $this->assertSame(1, $restoreRows);
    }

    public function testRestoreIsItselfUndoableBecauseItSnapshotsFirst(): void
    {
        $snapshotId = (int) GameHistorySnapshotIfNeeded(700);
        GameRemoveAllScores(700);
        DBQuery("DELETE FROM uo_game_history WHERE game=700 AND has_snapshot=0");

        GameHistoryRestore($snapshotId);

        $snapshots = (int) DBQueryToValue(
            "SELECT COUNT(*) FROM uo_game_history WHERE game=700 AND has_snapshot=1"
        );
        // The original plus the pre-restore capture.
        $this->assertSame(2, $snapshots);
    }

    public function testRestoreRefusesARowWithoutASnapshot(): void
    {
        $id = (int) GameHistoryRecord(700, 'goal', 'add', ['num' => 1]);
        $result = GameHistoryRestore($id);
        $this->assertFalse($result['restored']);
    }

    public function testRestoreDeniesAUserWithoutGameEditRights(): void
    {
        $snapshotId = (int) GameHistorySnapshotIfNeeded(700);
        $_SESSION['userproperties']['userrole'] = [];
        $_SESSION['uid'] = 'anonymous';
        try {
            $result = GameHistoryRestore($snapshotId);
            $this->assertFalse($result['restored']);
        } finally {
            $_SESSION['uid'] = 'testuser';
            $_SESSION['userproperties']['userrole']['superadmin'] = true;
        }
    }
```

Extend `tearDown()` so the damaged fixture game is put back for the next test:

```php
        // Restore tests mutate the shared fixture game 700.
        DBQuery("DELETE FROM uo_goal WHERE game=700");
        DBQuery("INSERT INTO uo_goal (game, num, assist, scorer, time, homescore, visitorscore, ishomegoal, iscallahan, timestamp) VALUES
            (700, 1, 801, 800, 120, 1, 0, 1, 0, '2026-06-01 10:02:00'),
            (700, 2, 803, 802, 300, 1, 1, 0, 0, '2026-06-01 10:05:00'),
            (700, 3, 800, 801, 480, 2, 1, 1, 1, '2026-06-01 10:08:00'),
            (700, 4, 802, 803, 660, 2, 2, 0, 0, '2026-06-01 10:11:00')");
        DBQuery("UPDATE uo_game SET homescore=15, visitorscore=11, isongoing=0, hasstarted=1 WHERE game_id=700");
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: FAIL — `Call to undefined function GameHistoryRestore()`.

- [ ] **Step 3: Add the force parameter to the snapshot helper**

In `lib/gamehistory.functions.php`, change the signature and the memo check:

```php
function GameHistorySnapshotIfNeeded($gameId, $force = false)
{
    if (IsGameHistoryDisabled()) {
        return false;
    }
    // The suppression flag silences change rows during a restore replay, but a
    // restore must still capture the state it is about to replace.
    if (GameHistorySuppressed() && !$force) {
        return false;
    }

    $gameId = (int) $gameId;
    if ($gameId <= 0) {
        return false;
    }

    if ($force) {
        CacheForgetNamespace("game_history_snapshot");
    }

    return CacheRemember("game_history_snapshot", $gameId, function () use ($gameId) {
        return GameHistoryWriteSnapshot($gameId);
    });
}
```

`GameHistoryWriteSnapshot()` is unchanged.

- [ ] **Step 4: Write the restore implementation**

Append to `lib/gamehistory.functions.php`:

```php
/**
 * Restore a game's scoresheet to a previously captured state.
 *
 * The replay goes through the ordinary game mutators rather than raw SQL so
 * that ResolvePoolStandings(), PoolResolvePlayed() and RefreshGameSpiritData()
 * still run. game.functions.php requires this file, so it is required lazily
 * here to break the include cycle.
 */
function GameHistoryRestore($historyId)
{
    require_once __DIR__ . '/game.functions.php';

    $failed = ['restored' => false, 'warnings' => []];

    $entry = GameHistoryEntry($historyId);
    if (!$entry || empty($entry['has_snapshot']) || !is_array($entry['snapshot'])) {
        return $failed;
    }

    $gameId = (int) $entry['game'];
    $snapshot = $entry['snapshot'];

    // The replay calls mutators guarded by two different rights, and a die()
    // inside one of them would abort mid-rebuild past the finally below. The
    // guard set here must stay a superset of every replayed mutator's own
    // check: GameAddPlayer()/GameAddNewPlayer() use hasEditGamePlayersRight(),
    // everything else uses hasEditGameEventsRight().
    if (!hasEditGameEventsRight($gameId) || !hasEditGamePlayersRight($gameId)) {
        return $failed;
    }
    $seasonId = GameSeason($gameId);
    if (isEventReadonly($seasonId) && !canBypassEventReadonly($seasonId)) {
        return $failed;
    }
    if (IsPoolLocked(GamePool($gameId))) {
        return $failed;
    }
    if (IsSeasonStatsCalculated($seasonId)) {
        return $failed;
    }

    GameHistorySnapshotIfNeeded($gameId, true);

    $warnings = [];
    GameHistorySuppressed(true);
    try {
        $idMap = GameHistoryRestorePlayers($gameId, $snapshot['played'] ?? [], $warnings);

        GameRemoveAllScores($gameId);
        foreach ($snapshot['goals'] ?? [] as $goal) {
            GameAddScoreEntry([
                'game' => $gameId,
                'num' => (int) $goal['num'],
                'assist' => GameHistoryMapPlayer($goal['assist'] ?? null, $idMap),
                'scorer' => GameHistoryMapPlayer($goal['scorer'] ?? null, $idMap),
                'time' => (int) ($goal['time'] ?? 0),
                'homescore' => (int) $goal['homescore'],
                'visitorscore' => (int) $goal['visitorscore'],
                'ishomegoal' => (int) $goal['ishomegoal'],
                'iscallahan' => (int) $goal['iscallahan'],
            ]);
        }

        GameRemoveAllTimeouts($gameId);
        foreach ($snapshot['timeouts'] ?? [] as $timeout) {
            GameAddTimeout($gameId, (int) $timeout['num'], (int) $timeout['time'], (int) $timeout['ishome']);
        }

        GameRemoveAllSpiritTimeouts($gameId);
        foreach ($snapshot['spirit_timeouts'] ?? [] as $timeout) {
            GameAddSpiritTimeout($gameId, (int) $timeout['num'], (int) $timeout['time'], (int) $timeout['ishome']);
        }

        foreach ($snapshot['events'] ?? [] as $event) {
            if ($event['type'] == "start") {
                GameSetStartingTeam($gameId, (int) $event['ishome']);
            } elseif (GameIsCapEventType($event['type'])) {
                GameSetCapEvent($gameId, $event['type'], (int) $event['time'], (int) $event['ishome']);
            }
        }

        GameSetScoreSheetKeeper($gameId, $snapshot['game']['official'] ?? null);
        GameSetHalftime($gameId, (int) ($snapshot['game']['halftime'] ?? 0));
        SetGameComment(COMMENT_TYPE_GAME, $gameId, $snapshot['comment'] ?? "", empty($snapshot['comment']));

        GameHistoryRestoreResult($gameId, $snapshot['game'] ?? []);
    } finally {
        GameHistorySuppressed(false);
    }

    GameHistoryRecord($gameId, "restore", "restore", [
        'from' => (int) $historyId,
        'warnings' => count($warnings),
    ]);

    if (function_exists('RefreshGameSpiritData')) {
        RefreshGameSpiritData($gameId);
    }

    return ['restored' => true, 'warnings' => $warnings];
}

/**
 * Rebuild uo_played and return a map from snapshot player ids to current ones.
 *
 * uo_goal declares ON DELETE SET NULL on both player keys, so a player deleted
 * since the snapshot cannot be resolved by id. The stored jersey number and
 * team are the fallback, and anything still unmatched is reported rather than
 * silently dropped.
 */
function GameHistoryRestorePlayers($gameId, $playedRows, &$warnings)
{
    $idMap = [];

    GameRemoveAllPlayers($gameId);
    $roles = [];
    foreach ($playedRows as $row) {
        $playerId = (int) $row['player'];
        $exists = (int) DBQueryToValue(sprintf(
            "SELECT COUNT(*) FROM uo_player WHERE player_id=%d",
            $playerId,
        ));

        if (!$exists) {
            $rematched = DBQueryToValue(sprintf(
                "SELECT player_id FROM uo_player WHERE team=%d AND num=%d LIMIT 1",
                (int) $row['team'],
                (int) $row['num'],
            ));
            if ($rematched === null || $rematched === false) {
                $warnings[] = sprintf(
                    _("Player %s could not be restored."),
                    $row['name'] ?? $playerId,
                );
                continue;
            }
            $idMap[$playerId] = (int) $rematched;
            $playerId = (int) $rematched;
        }

        // GameAddPlayer() returns false without dying when
        // GameAllowsPlayerOnRoster() refuses. That matters here: the roster was
        // just emptied, so the "already on this game's roster" fallback inside
        // GameAllowsPlayerOnRoster() can no longer rescue an unaccredited
        // player in a season with require_accreditation set.
        if (GameAddPlayer($gameId, $playerId, (int) $row['num']) === false) {
            $warnings[] = sprintf(
                _("Player %s could not be restored."),
                $row['name'] ?? $playerId,
            );
            continue;
        }

        if (!empty($row['captain'])) {
            $roles[(int) $row['team']]['captain'][] = $playerId;
        }
        if (!empty($row['spirit_captain'])) {
            $roles[(int) $row['team']]['spirit_captain'][] = $playerId;
        }
    }

    foreach ($roles as $teamId => $columns) {
        foreach ($columns as $column => $playerIds) {
            GameSetRolePlayers($gameId, $teamId, $column, $playerIds);
        }
    }

    return $idMap;
}

function GameHistoryMapPlayer($playerId, $idMap)
{
    if ($playerId === null || (int) $playerId <= 0) {
        return null;
    }
    $playerId = (int) $playerId;
    return $idMap[$playerId] ?? $playerId;
}

/**
 * The three result mutators each force their own hasstarted value (0, 1 and 2),
 * so none of them can reproduce an arbitrary snapshot. Fixture game 700, for
 * example, is hasstarted=1 with a non-null final score, which GameSetResult()
 * would silently promote to 2. The stored flags are therefore written back
 * after the mutator has done the pool and standings work.
 */
function GameHistoryRestoreResult($gameId, $gameFields)
{
    $home = $gameFields['homescore'] ?? null;
    $away = $gameFields['visitorscore'] ?? null;

    if ($home === null || $away === null) {
        GameClearResult($gameId);
    } elseif (!empty($gameFields['isongoing'])) {
        GameUpdateResult($gameId, (int) $home, (int) $away);
    } else {
        GameSetResult($gameId, (int) $home, (int) $away);
    }

    DBQuery(sprintf(
        "UPDATE uo_game SET hasstarted=%d, isongoing=%d WHERE game_id=%d",
        (int) ($gameFields['hasstarted'] ?? 0),
        (int) ($gameFields['isongoing'] ?? 0),
        (int) $gameId,
    ));
}
```

`GameHistoryRestorePlayers()`, `GameHistoryMapPlayer()` and `GameHistoryRestoreResult()` exist because `GameHistoryRestore()` would otherwise be far too long to read, and all three are called only from it.

- [ ] **Step 5: Verify the guard set is a strict superset**

`lib/database.php` exposes no transaction helpers, so the replay cannot be rolled
back. A `die()` inside any replayed mutator would abort past the `finally`,
leaving the suppression flag set and the game half-rebuilt. The only thing making
the "restore is never destructive" claim true is that `GameHistoryRestore()`
checks every right its replay will need, up front.

Enumerate the guard in each function the replay calls and confirm none is
stricter than what `GameHistoryRestore()` already checked:

```bash
cd /home/kari/dev/ultiorganizer
grep -n "function GameRemoveAllScores\|function GameAddScoreEntry\|function GameRemoveAllTimeouts\|function GameAddTimeout\|function GameRemoveAllSpiritTimeouts\|function GameAddSpiritTimeout\|function GameRemoveAllPlayers\|function GameAddPlayer\|function GameSetRolePlayers\|function GameSetStartingTeam\|function GameSetCapEvent\|function GameSetScoreSheetKeeper\|function GameSetHalftime\|function GameSetResult\|function GameUpdateResult\|function GameClearResult" -A 3 lib/game.functions.php | grep -n "hasEdit\|isSuperAdmin\|isEventReadonly\|die("
```

Expected: every hit is `hasEditGameEventsRight` or `hasEditGamePlayersRight`, both
already checked. If any function guards on something else, add that check to
`GameHistoryRestore()` before continuing — do not rely on the `finally`.

Note one side effect that is not a defect to fix here but must be in the docs:
`GameAddPlayer()` also runs `UPDATE uo_player SET num=...`, so restoring a
snapshot rewrites each player's current team roster number to the number they
wore in that game. Record this in `docs/game-history.md` in Task 10.

- [ ] **Step 6: Run the test to verify it passes**

```bash
cd ../ultiorganizer-tests && ./libtest:run --lib-file gamehistory.functions.php
```

Expected: PASS, no failures.

- [ ] **Step 7: Run the integration suite**

```bash
cd ../ultiorganizer-tests && ./test:integration
```

Expected: PASS.

- [ ] **Step 8: Format, lint, and commit (both repos)**

```bash
cd /home/kari/dev/ultiorganizer
composer format && composer lint
git add lib/gamehistory.functions.php
git commit -m "Add game history restore with player rematching"

cd ../ultiorganizer-tests
git add tests/Integration/Lib/GamehistoryFunctionsLibTest.php
git commit -m "Cover game history restore"
```

---

### Task 7: Per-game history page for event admins

**Files:**
- Create: `user/gamehistory.php`
- Modify: `view_ids.inc.php`
- Modify: `user/addresult.php:65-72`, `user/addplayerlists.php:277`, `user/addscoresheet.php`, `user/adddefensesheet.php:65`, `user/addspirit.php:225` (add the tab)

**Interfaces:**
- Consumes: `GameHistoryList()`, `GameHistoryCount()`, `GameHistoryEntry()`, `GameHistoryFormatDetail()`, `GameHistoryRestore()`.
- Produces: route `?view=user/gamehistory&game=N`, view id `GAMEHISTORY`.

- [ ] **Step 1: Add the view id**

In `view_ids.inc.php`, after `define("ADDDEFENSESHEET", 216);`:

```php
define("GAMEHISTORY", 217);
```

`217` is the lowest free id in the user range.

- [ ] **Step 2: Create the page**

Create `user/gamehistory.php`:

```php
<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/gamehistory.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';

if (empty($_GET["game"])) {
    showPage(_("History"), "<p class='warning'>" . _("Game not found") . ".</p>");
    return;
}

$gameId = intval($_GET["game"]);

if (!hasEditGameEventsRight($gameId)) {
    die('Insufficient rights to edit game');
}

$game_result = GameInfo($gameId);
$seasoninfo = SeasonInfo($game_result['season']);

$LAYOUT_ID = GAMEHISTORY;
$title = _("History");
$html = "";
$feedback = "";

if (!empty($_POST['restore']) && !empty($_POST['history_id'])) {
    $outcome = GameHistoryRestore(intval($_POST['history_id']));
    if ($outcome['restored']) {
        $feedback .= "<p>" . _("Score sheet restored") . ".</p>";
        foreach ($outcome['warnings'] as $warning) {
            $feedback .= "<p class='warning'>" . utf8entities($warning) . "</p>";
        }
    } else {
        $feedback .= "<p class='warning'>" . _("Score sheet not restored") . ".</p>";
    }
    $game_result = GameInfo($gameId);
}

$viewEntry = null;
if (!empty($_GET['entry'])) {
    $viewEntry = GameHistoryEntry(intval($_GET['entry']));
}

pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$menutabs[_("Result")] = "?view=user/addresult&game=$gameId";
$menutabs[_("Players")] = "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Scoresheet")] = "?view=user/addscoresheet&game=$gameId";
$menutabs[_("History")] = "?view=user/gamehistory&game=$gameId";
pageMenu($menutabs);

$html .= $feedback;

$rows = GameHistoryList($gameId, 200);
if (empty($rows)) {
    $html .= "<p>" . _("No changes recorded") . ".</p>";
} else {
    $html .= "<table class='data'>\n<tr>";
    $html .= "<th>" . _("Time") . "</th>";
    $html .= "<th>" . _("User") . "</th>";
    $html .= "<th>" . _("Source") . "</th>";
    $html .= "<th>" . _("Change") . "</th>";
    $html .= "<th></th>";
    $html .= "</tr>\n";

    foreach ($rows as $row) {
        $html .= "<tr>";
        $html .= "<td>" . utf8entities($row['time']) . "</td>";
        $html .= "<td>" . utf8entities($row['user_id']) . "</td>";
        $html .= "<td>" . utf8entities($row['source']) . "</td>";
        $html .= "<td>" . utf8entities(GameHistoryFormatDetail($row)) . "</td>";
        $html .= "<td>";
        if (!empty($row['has_snapshot'])) {
            $html .= "<a href='?view=user/gamehistory&amp;game=$gameId&amp;entry="
                . intval($row['history_id']) . "'>" . _("Show") . "</a> ";
            $html .= "<form method='post' style='display:inline'>";
            $html .= "<input type='hidden' name='history_id' value='" . intval($row['history_id']) . "'/>";
            $html .= "<input type='submit' name='restore' value='" . _("Restore") . "'/>";
            $html .= "</form>";
        }
        $html .= "</td></tr>\n";
    }
    $html .= "</table>\n";
}

if ($viewEntry !== null && is_array($viewEntry['snapshot'])) {
    $html .= "<h2>" . _("Saved state") . "</h2>\n";
    $html .= "<table class='data'>\n<tr><th>" . _("Point") . "</th><th>"
        . _("Score") . "</th><th>" . _("Assist") . "</th><th>" . _("Scorer") . "</th></tr>\n";
    foreach ($viewEntry['snapshot']['goals'] as $goal) {
        $html .= "<tr>";
        $html .= "<td>" . intval($goal['num']) . "</td>";
        $html .= "<td>" . intval($goal['homescore']) . "-" . intval($goal['visitorscore']) . "</td>";
        $html .= "<td>" . utf8entities($goal['assist_name'] ?? "") . "</td>";
        $html .= "<td>" . utf8entities($goal['scorer_name'] ?? "") . "</td>";
        $html .= "</tr>\n";
    }
    $html .= "</table>\n";
}

echo $html;

//common end
contentEnd();
pageEnd();
```

The page follows the `pageTopHeadOpen()` / `contentStart()` / `echo $html` / `contentEnd()` / `pageEnd()` idiom used by `user/addresult.php`. `showPage()` appears only on the early-exit error path, which is how `user/addresult.php` uses it too.

Keep the presentation terse: column headings carry the meaning, so no inline legend or explanatory paragraph is added.

- [ ] **Step 3: Add the tab to the sibling pages**

In each of `user/addresult.php`, `user/addplayerlists.php`, `user/addscoresheet.php`, `user/adddefensesheet.php` and `user/addspirit.php`, add this line to the existing `$menutabs` block, after the `_("Scoresheet")` entry:

```php
$menutabs[_("History")] = "?view=user/gamehistory&game=$gameId";
```

- [ ] **Step 4: Verify the page renders**

```bash
docker compose -f docs/dev/compose.yaml --profile devtools up --build -d dev
php -l user/gamehistory.php
```

Expected: `No syntax errors detected`.

Then follow `docs/ai/screenshot-verify/SKILL.md` to capture `?view=user/gamehistory&game=<a game with history>` at desktop and mobile widths. Enter and re-save a scoresheet first so the table is not empty. Confirm the table does not overflow horizontally on mobile.

- [ ] **Step 5: Format, lint, and commit (SUT repo)**

```bash
cd /home/kari/dev/ultiorganizer
composer format && composer lint
git add view_ids.inc.php user/gamehistory.php user/addresult.php user/addplayerlists.php user/addscoresheet.php user/adddefensesheet.php user/addspirit.php
git commit -m "Add per-game score sheet history page"
```

---

### Task 8: Superadmin viewer and the recording switch

**Files:**
- Create: `admin/gamehistory.php`
- Modify: `view_ids.inc.php`
- Modify: `menufunctions.php` (menu entry)
- Modify: `admin/serverconf.php` (the `DisableGameHistory` control)

**Interfaces:**
- Consumes: `GameHistoryAll()`, `GameHistoryAllCount()`, `GameHistoryFormatDetail()`, `SetServerConfValue()`.
- Produces: route `?view=admin/gamehistory`, view id `GAMEHISTORYADMIN`, setting `DisableGameHistory`.

- [ ] **Step 1: Add the view id**

In `view_ids.inc.php`, after `define("SUCCESS", 343);`:

```php
define("GAMEHISTORYADMIN", 353);
```

`353` is the lowest free id in the admin range.

- [ ] **Step 2: Create the page**

Create `admin/gamehistory.php`, modelled on `admin/eventviewer.php`. It must:

- include `auth.php`, `menufunctions.php`, `lib/gamehistory.functions.php` and `lib/game.functions.php`,
- set `$LAYOUT_ID = GAMEHISTORYADMIN;` and `$title = _("Score sheet history");`,
- build `$filters` from `$_POST` with the keys `season`, `game`, `user`, `from`, `to`,
- page the results with `$pageSize = 100` using the same `page_nav` / `page_input` POST handling `admin/eventviewer.php` already implements,
- render one row per entry with time, game, user, source and `GameHistoryFormatDetail($row)`,
- escape every value with `utf8entities()`.

Do not add a delete or prune control. History is removed only by the foreign key cascade when its game or season is deleted, which is the retention decision recorded in the spec.

- [ ] **Step 3: Add the menu entry**

In `menufunctions.php`, add a link to `?view=admin/gamehistory` in the same admin menu group that already lists `?view=admin/eventviewer`, labelled `_("Score sheet history")`.

- [ ] **Step 4: Add the recording switch**

In `admin/serverconf.php`, add a `DisableGameHistory` checkbox alongside the existing `DisableVisitorLogging` control, persisted through `SetServerConfValue()`. Follow whatever pattern the existing control uses in that file — read it first rather than inventing a new one.

- [ ] **Step 5: Verify**

```bash
php -l admin/gamehistory.php
```

Expected: `No syntax errors detected`.

Then use `docs/ai/screenshot-verify/SKILL.md` on `?view=admin/gamehistory` and on `?view=admin/serverconf`, at desktop and mobile widths.

- [ ] **Step 6: Format, lint, and commit (SUT repo)**

```bash
cd /home/kari/dev/ultiorganizer
composer format && composer lint
git add view_ids.inc.php admin/gamehistory.php menufunctions.php admin/serverconf.php
git commit -m "Add superadmin score sheet history viewer and recording switch"
```

---

### Task 9: Privacy coverage

**Files:**
- Modify: `lib/privacy.functions.php`
- Modify: `docs/ai/privacy-coverage/tables.txt`
- Modify: `docs/privacy.md`

**Interfaces:**
- Consumes: `uo_game_history`.
- Produces: `uo_game_history` reachable from the user export, the player export, and the anonymization and deletion flows.

`uo_game_history` holds personal data twice: `user_id` and `ip` identify a registered user, and snapshots embed player names as free text.

- [ ] **Step 1: Classify the table**

Add `uo_game_history` to `docs/ai/privacy-coverage/tables.txt` in the personal-data category, matching the file's existing format.

- [ ] **Step 2: Run the checker to see the gap**

```bash
php docs/ai/privacy-coverage/scripts/check-privacy-coverage.php
```

Expected: FAIL, reporting that `uo_game_history` is classified as personal but not reachable from `lib/privacy.functions.php`.

- [ ] **Step 3: Add the export**

In `lib/privacy.functions.php`, in the user export path (near the existing `PrivacyUserEventLogWhere()` usage around lines 346 and 647), add a section collecting the user's `uo_game_history` rows — `history_id`, `game`, `time`, `source`, `target`, `action`, `detail` — excluding `snapshot`, which is game data rather than that user's data.

- [ ] **Step 4: Add the anonymization**

Anonymizing a **user** replaces `user_id` and clears `ip` on their `uo_game_history` rows, matching how the flow already handles `uo_event_log`.

Anonymizing a **player** must also rewrite the name fields inside stored snapshots, because those are free text and not foreign keys:

```php
    $query = sprintf(
        "UPDATE uo_game_history SET snapshot = REPLACE(snapshot, '%s', '%s')
			WHERE has_snapshot=1 AND snapshot LIKE '%%%s%%'",
        DBEscapeString($oldName),
        DBEscapeString($newName),
        DBEscapeString($oldName),
    );
```

Confirm the surrounding function's existing variable names before pasting this — read the player anonymization function first and match its naming.

- [ ] **Step 5: Run the checker to verify it passes**

```bash
php docs/ai/privacy-coverage/scripts/check-privacy-coverage.php
```

Expected: PASS.

- [ ] **Step 6: Update the privacy document**

In `docs/privacy.md`, add `uo_game_history` to the per-table behaviour list: exported for the owning user, `user_id` and `ip` anonymized with the user, embedded player names rewritten with the player, rows deleted by cascade with the game.

- [ ] **Step 7: Commit (SUT repo)**

```bash
cd /home/kari/dev/ultiorganizer
composer format && composer lint
git add lib/privacy.functions.php docs/ai/privacy-coverage/tables.txt docs/privacy.md
git commit -m "Cover game history in privacy export and anonymization"
```

---

### Task 10: Documentation

**Files:**
- Create: `docs/game-history.md`
- Modify: `AGENTS.md` (topic list)
- Modify: `docs/README.md` (topic list)
- Modify: `docs/scoresheet.md`
- Modify: `docs/configuration-flags.md`

- [ ] **Step 1: Write the topic document**

Create `docs/game-history.md` covering: what `uo_game_history` stores; the two row kinds (change rows and snapshot rows) and why snapshots are sparse; the recording points table from the spec; how `UO_APP_SOURCE` attributes a change; the restore contract including the guards, the player rematching rule, and the media-event exclusion; the two views and who can read them; and the retention decision (cascade only, no age-based pruning).

Two caveats must appear explicitly, because both are surprising and neither is
visible from the code:

- restoring a snapshot rewrites each restored player's `uo_player.num`, because
  `GameAddPlayer()` updates the team roster number as well as the game one;
- a restore is not transactional. `lib/database.php` has no transaction helpers,
  so the guarantee rests on `GameHistoryRestore()` checking every right its
  replay needs before it starts.

- [ ] **Step 2: Register it in both topic lists**

Add to `AGENTS.md` under "Competition workflow" and to `docs/README.md`:

```markdown
- `docs/game-history.md`: score sheet change history, snapshot boundaries, and the restore contract.
```

Both lists are required — `AGENTS.md` states the rule explicitly.

- [ ] **Step 3: Update the scoresheet document**

In `docs/scoresheet.md`, the "Current persistence behavior" lists now understate what each save does. Add a line to each affected list noting that the mutation is recorded in `uo_game_history`, and add a short section pointing to `docs/game-history.md`.

- [ ] **Step 4: Register the new setting**

In `docs/configuration-flags.md`, add `DisableGameHistory` as an `INSTALLATION_SETTING` example. Note that it is not exposed in `install.php`: the default (recording enabled) applies on a fresh install and an admin changes it afterwards in `admin/serverconf.php`.

- [ ] **Step 5: Commit (SUT repo)**

```bash
cd /home/kari/dev/ultiorganizer
git add docs/game-history.md docs/README.md AGENTS.md docs/scoresheet.md docs/configuration-flags.md
git commit -m "Document score sheet change history"
```

---

### Task 11: Final verification sweep

**Files:** none modified unless a check fails.

- [ ] **Step 1: Run every repo checker**

```bash
cd /home/kari/dev/ultiorganizer
composer check
php docs/ai/review-database-access/scripts/check-db-access.php --all
php docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php
php docs/ai/privacy-coverage/scripts/check-privacy-coverage.php
php docs/ai/release-package-coverage/scripts/check-release-coverage.php
```

Expected: all PASS. Release coverage should need no change — `lib/`, `user/` and `admin/` are existing runtime paths, and `/docs/** export-ignore` already covers the new document.

- [ ] **Step 2: Review the user-facing language**

Dispatch the `grammar-terminology-reviewer` subagent over `user/gamehistory.php`, `admin/gamehistory.php`, `admin/serverconf.php` and the new strings in `lib/gamehistory.functions.php`. Apply what it reports.

- [ ] **Step 3: Refresh the gettext catalogs**

```bash
./docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh
```

Expected: the new `_()` strings appear in the catalogs. No new top-level app directory was added, so the script's hard-coded scan list needs no change.

- [ ] **Step 4: Verify against the real database**

Follow `docs/ai/query-database/SKILL.md`. Enter a scoresheet through `user/addscoresheet.php`, re-save it with a changed point, and confirm the row counts:

```sql
SELECT target, action, has_snapshot, COUNT(*)
FROM uo_game_history WHERE game = <id>
GROUP BY target, action, has_snapshot;
```

Expected: exactly one `snapshot`/`capture` row per save, one `goal`/`add` row per point written, one `goal`/`clear` row per save. Then restore the game from the snapshot and confirm `uo_goal`, `uo_played`, `uo_timeout` and the `uo_game` result match the snapshot exactly. Report the row counts in the handback.

- [ ] **Step 5: Run the full matrix**

```bash
cd ../ultiorganizer-tests
git checkout main && git pull
./test:matrix --sut-path ../ultiorganizer
```

Expected: PASS. This is the gate CI enforces. The harness pins shared `lib/` behaviour exactly, including export byte output, and this branch changed roughly 25 functions in `lib/game.functions.php`, so treat any failure here as a real regression rather than harness drift.

- [ ] **Step 6: Report**

Summarise in the handback: the row counts from Step 4, the matrix result from Step 5, and anything left undone.
