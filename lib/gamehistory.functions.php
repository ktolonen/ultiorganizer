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
        return sprintf("%s %s", _("Official"), $detail['name'] ?? "");
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
