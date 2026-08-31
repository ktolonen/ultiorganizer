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
        $states = [
            'ongoing' => _("Ongoing"),
            'final' => _("Final"),
            'from_goals' => _("Recalculated"),
        ];
        $state = (string) ($detail['state'] ?? "");
        return sprintf(
            "%s %d-%d (%s)",
            _("Result"),
            (int) ($detail['home'] ?? 0),
            (int) ($detail['away'] ?? 0),
            $states[$state] ?? $state,
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
        return sprintf("%s %s", _("Scorekeeper"), $detail['name'] ?? "");
    }
    if ($target == "forfeit") {
        $labels = [
            'none' => _("None"),
            'home' => _("Home team forfeited"),
            'away' => _("Away team forfeited"),
            'both' => _("Both teams forfeited"),
        ];
        $forfeit = (string) ($detail['forfeit'] ?? "");
        return sprintf("%s: %s", _("Forfeit"), $labels[$forfeit] ?? $forfeit);
    }
    if ($target == "defense" && $action == "clear") {
        return sprintf("%s %s: %d", _("Defences"), _("removed"), (int) ($detail['removed'] ?? 0));
    }
    if ($target == "defense" && $action == "update") {
        return sprintf(
            "%s: %d-%d",
            _("Defences"),
            (int) ($detail['home'] ?? 0),
            (int) ($detail['away'] ?? 0),
        );
    }
    if ($target == "defense") {
        return sprintf("%s %d", _("Defence"), (int) ($detail['num'] ?? 0));
    }
    if ($target == "timeout" && $action == "clear") {
        return sprintf("%s %s: %d", _("Timeouts"), _("removed"), (int) ($detail['removed'] ?? 0));
    }
    if ($target == "timeout") {
        return sprintf("%s %d", _("Timeout"), (int) ($detail['num'] ?? 0));
    }
    if ($target == "spirit_timeout" && $action == "clear") {
        return sprintf("%s %s: %d", _("Spirit timeouts"), _("removed"), (int) ($detail['removed'] ?? 0));
    }
    if ($target == "spirit_timeout") {
        return sprintf("%s %d", _("Spirit timeout"), (int) ($detail['num'] ?? 0));
    }
    if ($target == "comment" && $action == "remove") {
        return sprintf("%s %s", _("Game note"), _("removed"));
    }
    if ($target == "comment") {
        return _("Game note");
    }
    if ($target == "mediaevent") {
        return sprintf("%s %s", _("Media"), $action == "remove" ? _("removed") : _("added"));
    }
    if ($target == "gameevent") {
        $type = (string) ($detail['type'] ?? "");
        if ($type == "start" && $action == "remove") {
            return sprintf("%s %s", _("Starting offence"), _("removed"));
        }
        if ($type == "start") {
            return sprintf(
                "%s: %s",
                _("Starting offence"),
                !empty($detail['home']) ? _("Home team") : _("Away team"),
            );
        }
        $capName = function_exists('GameCapEventName') ? GameCapEventName($type) : "";
        if ($capName === "") {
            $capName = _("Cap event");
        }
        if ($action == "remove") {
            return sprintf("%s %s", $capName, _("removed"));
        }
        return $capName;
    }

    return $target;
}

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
    // everything else -- including GameSetForfeit(), called at the end of the
    // replay -- uses hasEditGameEventsRight(). All three of those functions
    // already fold in isEventReadonly()/canBypassEventReadonly() internally,
    // so that is not repeated here as an independent check.
    if (!hasEditGameEventsRight($gameId) || !hasEditGamePlayersRight($gameId)) {
        return $failed;
    }
    $seasonId = GameSeason($gameId);

    // Restoring an "acknowledged" flag (see GameHistoryRestorePlayers()) goes
    // through AcknowledgeUnaccredited(), guarded by hasAccredidationRight() --
    // a third right, distinct from the two above. Only the teams that actually
    // have an acknowledged player in the snapshot need it, checked up front
    // for the same die()-before-finally reason.
    $acknowledgedTeams = [];
    foreach ($snapshot['played'] ?? [] as $row) {
        if (!empty($row['acknowledged'])) {
            $acknowledgedTeams[(int) $row['team']] = true;
        }
    }
    foreach (array_keys($acknowledgedTeams) as $teamId) {
        if (!hasAccredidationRight($teamId)) {
            return $failed;
        }
    }

    GameHistorySnapshotIfNeeded($gameId, true);

    // Neither of these blocks: CheckGameResult() only ever turns them into
    // warning HTML on the routed result-entry pages, and the lib mutators
    // themselves (GameSetResult() included) never enforce them. Blocking
    // restore here would make it stricter than an ordinary result edit. The
    // operator still needs to know, so they become warnings instead -- reusing
    // the exact wording CheckGameResult() already uses for the same two
    // conditions rather than coining new msgids.
    $warnings = [];
    if (IsPoolLocked(GamePool($gameId))) {
        $warnings[] = _("Pool is locked.");
    }
    if (IsSeasonStatsCalculated($seasonId)) {
        $warnings[] = _("Event played.");
    }

    // Save/restore rather than a bare false: a caller further up the stack
    // (e.g. a bulk-restore loop) may already have suppression on, and this
    // must not clear it out from under that caller.
    $previousSuppressed = GameHistorySuppressed();
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

        GameRemoveAllDefenses($gameId);
        foreach ($snapshot['defenses'] ?? [] as $defense) {
            GameAddDefense(
                $gameId,
                GameHistoryMapPlayer($defense['author'] ?? null, $idMap),
                (int) $defense['ishomedefense'],
                (int) $defense['iscaught'],
                (int) $defense['time'],
                (int) $defense['iscallahan'],
                (int) $defense['num'],
            );
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
                // The cap target lives in info, not ishome -- caps carry no
                // team attribution (see GameHistoryBuildSnapshot()).
                GameSetCapEvent($gameId, $event['type'], (int) $event['time'], (int) $event['info']);
            }
        }

        GameSetScoreSheetKeeper($gameId, $snapshot['game']['official'] ?? null);
        GameSetHalftime($gameId, (int) ($snapshot['game']['halftime'] ?? 0));
        SetGameComment(COMMENT_TYPE_GAME, $gameId, $snapshot['comment'] ?? "", empty($snapshot['comment']));

        GameHistoryRestoreResult($gameId, $snapshot['game'] ?? []);

        // Must run after GameHistoryRestoreResult(), not before: standings are
        // recomputed by reading uo_game directly (see ResolvePoolStandings()'s
        // SQL), so whichever of these two runs last is the one whose recompute
        // sticks. GameUpdateResult() -- the isongoing branch -- does not
        // recompute standings at all, so if forfeit were restored first and
        // the game turns out to be ongoing, no call in this replay would ever
        // recompute standings against the correct score; restoring forfeit
        // last guarantees GameSetForfeit()'s own recompute is that call.
        GameSetForfeit($gameId, (int) ($snapshot['game']['forfeit'] ?? 0));
    } finally {
        GameHistorySuppressed($previousSuppressed);
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

        // GameAddPlayer() always inserts acknowledged=0; the caller already
        // confirmed hasAccredidationRight() for every team that needs this.
        if (!empty($row['acknowledged'])) {
            AcknowledgeUnaccredited($playerId, $gameId, "restore");
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
