<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/cache.functions.php';
require_once __DIR__ . '/comment.functions.php';
// Safe to require directly: user.functions.php reaches back to
// game.functions.php only from inside a function body, so there is no cycle.
require_once __DIR__ . '/user.functions.php';

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
 * Resolve the entry point that is performing the change from UO_APP_SOURCE,
 * which every app entry point defines.
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

/**
 * Backstop authorization for the two write helpers, accepting the union of the
 * rights their callers hold. The narrower rights are scoped to the one target
 * each is granted for, so an accreditation-only, media-only or anonymous
 * caller cannot reach the helpers directly and forge rows for another target.
 *
 * $allowAnonymousResult is set by GameSetResult() when it was itself called
 * with $checkRights=false; it grants nothing on its own and is re-validated
 * here against the installation's ANONYMOUS_RESULT_INPUT setting.
 */
function GameHistoryAuthorized($gameId, $target = null, $allowAnonymousResult = false)
{
    if (hasEditGameEventsRight($gameId) || hasEditGamePlayersRight($gameId)) {
        return true;
    }

    if ($target === 'mediaevent' && hasAddMediaRight()) {
        return true;
    }

    // CanManageGameComment() lets a note's original author edit or delete it
    // after losing hasEditGameEventsRight(), so that write is legitimate but
    // reaches none of the checks above. Authorship is resolved here rather
    // than taken as an argument. This is why SetGameComment() records BEFORE
    // ApplyCommentChange(): that call logs comment_delete, which
    // GameCommentMeta() treats as a cutoff, so afterwards the author is no
    // longer recognisable.
    if (
        $target === 'comment'
        && function_exists('CanManageGameComment') && defined('COMMENT_TYPE_GAME')
        && CanManageGameComment($gameId, COMMENT_TYPE_GAME)
    ) {
        return true;
    }

    // Scoped to the result target: the flag is caller-controlled, and
    // ANONYMOUS_RESULT_INPUT says the installation allows anonymous score
    // reporting, nothing about the caller.
    if (
        $target === 'result' && $allowAnonymousResult
        && defined('ANONYMOUS_RESULT_INPUT') && ANONYMOUS_RESULT_INPUT
    ) {
        return true;
    }

    // Scoped to the played target: hasAccredidationRight() grants
    // acknowledgement changes on a team's roster and nothing else.
    if ($target !== 'played') {
        return false;
    }

    $teams = DBQueryToRow(sprintf(
        "SELECT hometeam, visitorteam FROM uo_game WHERE game_id=%d",
        $gameId,
    ));
    if (!is_array($teams)) {
        return false;
    }
    if (hasAccredidationRight((int) $teams['hometeam']) || hasAccredidationRight((int) $teams['visitorteam'])) {
        return true;
    }

    // AcknowledgeUnaccredited() authorizes against the player's CURRENT team,
    // so a player who transferred away since this game is legitimately
    // acknowledged by an admin of their new team, which the fixture's own two
    // teams say nothing about. Accepting any rostered player's current team is
    // marginally broader than that, and takes no argument from the caller.
    $rosterTeams = DBQueryToArray(sprintf(
        "SELECT DISTINCT p.team FROM uo_played pd
			INNER JOIN uo_player p ON p.player_id=pd.player
			WHERE pd.game=%d",
        $gameId,
    ));
    foreach ($rosterTeams as $rosterTeam) {
        if ($rosterTeam['team'] !== null && hasAccredidationRight((int) $rosterTeam['team'])) {
            return true;
        }
    }

    return false;
}

function GameHistoryRecord($gameId, $target, $action, $detail = [], $force = false, $allowAnonymousResult = false)
{
    // $force is GameHistoryRestore()'s own audit row: DisableGameHistory
    // governs routine recording volume, not the recoverability of an explicit
    // destructive action. Suppression is not affected by it.
    if ((IsGameHistoryDisabled() && !$force) || GameHistorySuppressed()) {
        return false;
    }

    $gameId = (int) $gameId;
    if ($gameId <= 0) {
        return false;
    }

    if (!GameHistoryAuthorized($gameId, $target, $allowAnonymousResult)) {
        return false;
    }

    // "anonymous" marks a session-less self-reported result distinctly from
    // "unknown", which stays reserved for a missing session on other paths.
    $anonymous = empty($_SESSION['uid'])
        && $allowAnonymousResult && defined('ANONYMOUS_RESULT_INPUT') && ANONYMOUS_RESULT_INPUT;
    $userId = !empty($_SESSION['uid']) ? $_SESSION['uid'] : ($anonymous ? "anonymous" : "unknown");
    // DisableVisitorLogging stops IP recording entirely (docs/privacy.md), so
    // it suppresses the address here too, on every row.
    $ip = (!empty($_SERVER['REMOTE_ADDR']) && !IsVisitorLoggingDisabled())
        ? $_SERVER['REMOTE_ADDR'] : "";
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
    // Lazy require: game.functions.php requires this file.
    require_once __DIR__ . '/game.functions.php';

    $gameId = (int) $gameId;

    $game = DBQueryToRow(sprintf(
        "SELECT homescore, visitorscore, isongoing, hasstarted, forfeit, official, halftime,
			homedefenses, visitordefenses, timer_start, timer_pause_start, timer_paused_duration,
			hometeam, visitorteam
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

    $gameFields = GameHistoryIntFields($game, ['homescore', 'visitorscore', 'isongoing',
        'hasstarted', 'forfeit', 'halftime', 'homedefenses', 'visitordefenses',
        'timer_start', 'timer_pause_start', 'timer_paused_duration',
        'hometeam', 'visitorteam']);
    // timer_elapsed (v3) lets the restore derive a fresh timer_start instead
    // of replaying the stale absolute epoch in timer_start.
    $gameFields['timer_elapsed'] = (int) GameTimerState($gameId)['elapsed'];

    return [
        'v' => 4,
        'game' => $gameFields,
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
 * Cast a row's numeric columns once, so every later snapshot comparison and
 * restore sees integers rather than the strings MySQL returns.
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
 * Capture the current scoresheet once per game per request, memoized in the
 * request-local cache so the several mutators of one desktop save share a
 * single restore point.
 */
function GameHistorySnapshotIfNeeded($gameId, $force = false, $allowAnonymousResult = false, $target = null)
{
    // $force is GameHistoryRestore()'s pre-restore capture, which must write
    // even while recording is disabled or suppressed -- otherwise a restore
    // would be unrecoverable.
    if (IsGameHistoryDisabled() && !$force) {
        return false;
    }
    if (GameHistorySuppressed() && !$force) {
        return false;
    }

    $gameId = (int) $gameId;
    if ($gameId <= 0) {
        return false;
    }

    if (!GameHistoryAuthorized($gameId, $target, $allowAnonymousResult)) {
        return false;
    }

    if ($force) {
        CacheForgetNamespace("game_history_snapshot");
    }

    return CacheRemember("game_history_snapshot", $gameId, function () use ($gameId, $allowAnonymousResult) {
        $json = json_encode(GameHistoryBuildSnapshot($gameId), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        // Same attribution rules as GameHistoryRecord().
        $anonymous = empty($_SESSION['uid'])
            && $allowAnonymousResult && defined('ANONYMOUS_RESULT_INPUT') && ANONYMOUS_RESULT_INPUT;
        $userId = !empty($_SESSION['uid']) ? $_SESSION['uid'] : ($anonymous ? "anonymous" : "unknown");
        $ip = (!empty($_SERVER['REMOTE_ADDR']) && !IsVisitorLoggingDisabled())
            ? $_SERVER['REMOTE_ADDR'] : "";

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
    });
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
        // The filter is a bare YYYY-MM-DD, which MySQL widens to 00:00:00, so
        // a plain <= would exclude the whole end date.
        $where[] = sprintf("h.time < DATE_ADD('%s', INTERVAL 1 DAY)", DBEscapeString($filters['to']));
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

/**
 * Load one history row.
 *
 * A snapshot whose recorded teams are no longer the game's teams is withheld:
 * SetGame() can move a game, so an admin who gains rights that way would
 * otherwise read a previous fixture's roster and scorer names. Only
 * GameHistoryRestore() passes $allowMismatchedFixture, because it reports the
 * mismatch as a specific refusal.
 */
function GameHistoryEntry($historyId, $allowMismatchedFixture = false)
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

    // Compared positionally, so a GameChangeHome() swap counts as a mismatch.
    // Pre-v4 snapshots carry neither key and are treated as matching.
    $row['fixture_mismatch'] = false;
    $snapshotGame = is_array($row['snapshot']) ? ($row['snapshot']['game'] ?? []) : [];
    if (array_key_exists('hometeam', $snapshotGame) && array_key_exists('visitorteam', $snapshotGame)) {
        $currentTeams = DBQueryToRow(sprintf(
            "SELECT hometeam, visitorteam FROM uo_game WHERE game_id=%d",
            (int) $row['game'],
        ));
        $currentHome = ($currentTeams['hometeam'] ?? null) === null ? null : (int) $currentTeams['hometeam'];
        $currentVisitor = ($currentTeams['visitorteam'] ?? null) === null ? null : (int) $currentTeams['visitorteam'];
        $row['fixture_mismatch'] = $snapshotGame['hometeam'] !== $currentHome
            || $snapshotGame['visitorteam'] !== $currentVisitor;
    }

    if ($row['fixture_mismatch'] && !$allowMismatchedFixture) {
        $row['snapshot'] = null;
        $row['has_snapshot'] = 0;
    }

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
    if ($target == "played" && isset($detail['role'])) {
        $roleLabels = [
            'captain' => _("Captain"),
            'spirit_captain' => _("Spirit captain"),
        ];
        $role = (string) $detail['role'];
        $players = is_array($detail['players'] ?? null) ? $detail['players'] : [];
        return sprintf("%s: %d", $roleLabels[$role] ?? $role, count($players));
    }
    if ($target == "played" && isset($detail['acknowledged'])) {
        return sprintf(
            "%s %d: %s",
            _("Player"),
            (int) ($detail['player'] ?? 0),
            !empty($detail['acknowledged']) ? _("Acknowledged") : _("Not acknowledged"),
        );
    }
    if ($target == "played") {
        return sprintf("%s %d", _("Player"), (int) ($detail['player'] ?? 0));
    }
    if ($target == "timer" && $action == "update") {
        return sprintf("%s: %d", _("Set game clock"), (int) ($detail['elapsed'] ?? 0));
    }
    if ($target == "timer") {
        $labels = [
            'start' => _("Start game clock"),
            'pause' => _("Pause game clock"),
            'resume' => _("Resume game clock"),
            'reset' => _("Reset game clock"),
        ];
        return $labels[$action] ?? _("Game clock");
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
 * still run. See docs/game-history.md for the full restore contract.
 */
function GameHistoryRestore($historyId)
{
    require_once __DIR__ . '/game.functions.php';

    $failed = ['restored' => false, 'warnings' => []];

    $entry = GameHistoryEntry($historyId, true);
    if (!$entry || empty($entry['has_snapshot']) || !is_array($entry['snapshot'])) {
        return $failed;
    }

    $gameId = (int) $entry['game'];
    $snapshot = $entry['snapshot'];

    // The replay is not transactional and a die() inside a mutator would abort
    // mid-rebuild, so every right the replay needs is checked up front. This
    // set must stay a superset of the replayed mutators' own checks:
    // hasEditGamePlayersRight() for GameAddPlayer()/GameAddNewPlayer(),
    // hasEditGameEventsRight() for everything else.
    if (!hasEditGameEventsRight($gameId) || !hasEditGamePlayersRight($gameId)) {
        return $failed;
    }
    $seasonId = GameSeason($gameId);

    // Restoring an "acknowledged" flag is an accreditation mutation even
    // though GameHistoryRestorePlayers() writes uo_played directly, so it
    // needs a third right, for each team with an acknowledged player.
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

    // SetGame() and GameChangeHome() never snapshot, so a snapshot taken
    // before either is a scoresheet for a fixture this game no longer
    // represents. Replaying it would write rows for teams that are not in the
    // game, so unlike the two warnings below this rejects. Checked after the
    // rights above so an unauthorized caller cannot learn the fixture changed.
    if (!empty($entry['fixture_mismatch'])) {
        return [
            'restored' => false,
            'warnings' => [_("Restore refused: the home and away teams have changed since this snapshot was taken.")],
        ];
    }

    GameHistorySnapshotIfNeeded($gameId, true);

    // Warnings, not blocks: the mutators never enforce these either, so
    // refusing here would make a restore stricter than an ordinary result
    // edit. Wording reused from CheckGameResult().
    $warnings = [];
    if (IsPoolLocked(GamePool($gameId))) {
        $warnings[] = _("Pool is locked.");
    }
    if (IsSeasonStatsCalculated($seasonId)) {
        $warnings[] = _("Event played.");
    }

    // Saved and put back rather than cleared, so a caller further up the stack
    // keeps its own suppression.
    $previousSuppressed = GameHistorySuppressed();
    GameHistorySuppressed(true);
    try {
        $idMap = GameHistoryRestorePlayers($historyId, $warnings);

        // A scorer, assist or defender already off the roster at capture time
        // has no uo_played row, so $idMap has no entry for them. If they have
        // since been deleted, replaying their id would violate the uo_goal /
        // uo_defense foreign key and abort the restore partway. They are
        // mapped to NULL instead -- the state ON DELETE SET NULL leaves --
        // and collected here, by id, with whatever name the snapshot has.
        $referenced = [];
        foreach ($snapshot['goals'] ?? [] as $goal) {
            foreach (['assist', 'scorer'] as $key) {
                $id = (int) ($goal[$key] ?? 0);
                if ($id > 0 && !array_key_exists($id, $idMap)) {
                    $name = trim((string) ($goal[$key . '_name'] ?? ""));
                    $referenced[$id] = $name !== "" ? $name : ($referenced[$id] ?? (string) $id);
                }
            }
        }
        foreach ($snapshot['defenses'] ?? [] as $defense) {
            $id = (int) ($defense['author'] ?? 0);
            if ($id > 0 && !array_key_exists($id, $idMap)) {
                $referenced[$id] = $referenced[$id] ?? (string) $id;
            }
        }
        if ($referenced !== []) {
            $existing = DBQueryToArray(sprintf(
                "SELECT player_id FROM uo_player WHERE player_id IN (%s)",
                implode(',', array_map('intval', array_keys($referenced))),
            ));
            $alive = [];
            foreach ($existing as $existingRow) {
                $alive[(int) $existingRow['player_id']] = true;
            }
            foreach ($referenced as $id => $name) {
                if (!isset($alive[$id])) {
                    $idMap[$id] = null;
                    $warnings[] = sprintf(_("Player %s could not be restored."), $name);
                }
            }
        }

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

        // GameSetCapEvent() is upsert-only, so an event set after the snapshot
        // would otherwise survive the replay.
        GameRemoveAllGameEvents($gameId);
        foreach ($snapshot['events'] ?? [] as $event) {
            // The snapshot stores the raw uo_gameevent.type value ('offence'),
            // not the "start" label GameHistoryFormatDetail() renders.
            if ($event['type'] == "offence") {
                GameSetStartingTeam($gameId, (int) $event['ishome']);
            } elseif (GameIsCapEventType($event['type'])) {
                // Caps carry no team attribution: the target is in info.
                GameSetCapEvent($gameId, $event['type'], (int) $event['time'], (int) $event['info']);
            }
        }

        GameSetScoreSheetKeeper($gameId, $snapshot['game']['official'] ?? null);
        $halftime = $snapshot['game']['halftime'] ?? null;
        GameSetHalftime($gameId, $halftime === null ? null : (int) $halftime);

        // A v1 snapshot never captured these; leave the current totals alone
        // rather than zero them.
        $gameFields = $snapshot['game'] ?? [];
        if (array_key_exists('homedefenses', $gameFields) && array_key_exists('visitordefenses', $gameFields)) {
            GameSetDefenses($gameId, (int) $gameFields['homedefenses'], (int) $gameFields['visitordefenses']);
        }

        // Matches CommentRequestedChange()'s === "" test, so a comment of "0"
        // is not a delete.
        SetGameComment(COMMENT_TYPE_GAME, $gameId, $snapshot['comment'] ?? "", ($snapshot['comment'] ?? "") === "");

        $resultFields = $snapshot['game'] ?? [];
        $home = $resultFields['homescore'] ?? null;
        $away = $resultFields['visitorscore'] ?? null;

        if ($home === null || $away === null) {
            GameClearResult($gameId);
        } elseif (!empty($resultFields['isongoing'])) {
            GameUpdateResult($gameId, (int) $home, (int) $away);
        } else {
            GameSetResult($gameId, (int) $home, (int) $away);
        }

        $set = [
            sprintf("hasstarted=%d", (int) ($resultFields['hasstarted'] ?? 0)),
            sprintf("isongoing=%d", (int) ($resultFields['isongoing'] ?? 0)),
        ];

        // A v1 snapshot never captured the timer columns; leave whatever the
        // result branch above did to them. None of those three takes a
        // captured timer value, so it is written directly here, in the same
        // write-back as hasstarted/isongoing.
        if (array_key_exists('timer_start', $resultFields)) {
            if (array_key_exists('timer_elapsed', $resultFields) && $resultFields['timer_start'] !== null) {
                // timer_start is an absolute Unix epoch, so replaying it
                // verbatim would count the time since capture as game time.
                // Derive a fresh epoch from the captured elapsed time instead,
                // freezing it immediately if the snapshot was paused.
                $elapsed = max(0, (int) $resultFields['timer_elapsed']);
                $now = time();
                $set[] = sprintf("timer_start=%d", $now - $elapsed);
                $set[] = "timer_pause_start=" . ($resultFields['timer_pause_start'] === null ? "NULL" : $now);
                $set[] = "timer_paused_duration=0";
            } else {
                $set[] = "timer_start=" . ($resultFields['timer_start'] === null ? "NULL" : (int) $resultFields['timer_start']);
                $set[] = "timer_pause_start=" . ($resultFields['timer_pause_start'] === null ? "NULL" : (int) $resultFields['timer_pause_start']);
                $set[] = sprintf("timer_paused_duration=%d", (int) ($resultFields['timer_paused_duration'] ?? 0));
            }
        }

        DBQuery(sprintf(
            "UPDATE uo_game SET %s WHERE game_id=%d",
            implode(', ', $set),
            (int) $gameId,
        ));

        // Must run after the result replay: standings are recomputed from
        // uo_game, so whichever runs last is the recompute that sticks, and
        // the isongoing branch above does not recompute at all.
        GameSetForfeit($gameId, (int) ($snapshot['game']['forfeit'] ?? 0));
    } finally {
        GameHistorySuppressed($previousSuppressed);
    }

    GameHistoryRecord($gameId, "restore", "restore", [
        'from' => (int) $historyId,
        'warnings' => count($warnings),
    ], true);

    if (function_exists('RefreshGameSpiritData')) {
        RefreshGameSpiritData($gameId);
    }

    return ['restored' => true, 'warnings' => $warnings];
}

/**
 * Rebuild uo_played from a snapshot and return a map from snapshot player ids
 * to current ones.
 *
 * uo_goal declares ON DELETE SET NULL on both player keys, so a player deleted
 * since the snapshot cannot be resolved by id. The stored jersey number and
 * team are the fallback, and anything still unmatched is reported rather than
 * silently dropped.
 *
 * Rows are written straight into uo_played, bypassing GameAddPlayer()'s
 * GameAllowsPlayerOnRoster() gate: the roster was just emptied, so the gate's
 * "already on this game's roster" exception can no longer rescue a player the
 * snapshot recorded as acknowledged. That is why this takes a history id
 * rather than a caller-supplied row set, and repeats GameHistoryRestore()'s
 * full guard rather than inheriting it.
 */
function GameHistoryRestorePlayers($historyId, &$warnings)
{
    $idMap = [];

    $entry = GameHistoryEntry($historyId, true);
    if (!$entry || !is_array($entry['snapshot'])) {
        return $idMap;
    }
    $gameId = (int) $entry['game'];
    if (!hasEditGameEventsRight($gameId) || !hasEditGamePlayersRight($gameId)) {
        return $idMap;
    }
    // The entry above is loaded with the mismatch allowed, so the fixture
    // check has to be repeated here rather than inherited.
    if (!empty($entry['fixture_mismatch'])) {
        return $idMap;
    }

    $playedRows = $entry['snapshot']['played'] ?? [];
    foreach ($playedRows as $row) {
        if (!empty($row['acknowledged']) && !hasAccredidationRight((int) $row['team'])) {
            return $idMap;
        }
    }

    // Ambiguity pre-scan on the same (team, num) the rematch query uses. Two
    // deleted snapshot rows sharing a jersey number would both collapse onto
    // the one candidate that number can return, merging one player's goals
    // onto another's -- so both are warned about rather than letting loop
    // order pick a winner. $consumedCandidates is pre-seeded with the rows
    // whose ids still exist, since those write to their own id and a rematch
    // must not resolve onto them either.
    $exists = [];
    $currentTeams = [];
    $deletedGroups = [];
    $consumedCandidates = [];
    foreach ($playedRows as $i => $row) {
        $playerId = (int) $row['player'];
        $playerRow = DBQueryToRow(sprintf(
            "SELECT team FROM uo_player WHERE player_id=%d",
            $playerId,
        ));
        $exists[$i] = is_array($playerRow);
        $currentTeams[$i] = $exists[$i] ? (int) $playerRow['team'] : null;
        if ($exists[$i]) {
            $consumedCandidates[$playerId] = true;
        } elseif (($row['num'] ?? null) !== null) {
            // A snapshot row with no jersey number cannot rematch on one, so
            // it is left out of the grouping: it falls through to the plain
            // "could not be restored" warning below rather than being
            // reported as a conflict over jersey 0, which is a real number
            // some player may actually wear.
            $key = (int) $row['team'] . ':' . (int) $row['num'];
            $deletedGroups[$key][] = $i;
        }
    }
    $ambiguousRows = [];
    foreach ($deletedGroups as $rowIndexes) {
        if (count($rowIndexes) > 1) {
            foreach ($rowIndexes as $i) {
                $ambiguousRows[$i] = true;
            }
        }
    }

    GameRemoveAllPlayers($gameId);
    foreach ($playedRows as $i => $row) {
        $originalId = (int) $row['player'];
        $playerId = $originalId;

        // The row is still restored: GamePlayers() joins against the player's
        // current uo_player.team, so a transferred player already lists under
        // the new team for every past game they played.
        if ($exists[$i] && $currentTeams[$i] !== (int) $row['team']) {
            $warnings[] = sprintf(
                _("Player %s now plays for %s, so their restored roster entry lists under that team."),
                $row['name'] ?? $originalId,
                TeamName((int) $currentTeams[$i]),
            );
        }

        if (!$exists[$i]) {
            if (isset($ambiguousRows[$i])) {
                $warnings[] = sprintf(
                    _("Player %s could not be restored: jersey number %d is not unique on team %s."),
                    $row['name'] ?? $originalId,
                    (int) $row['num'],
                    TeamName((int) $row['team']),
                );
                $idMap[$originalId] = null;
                continue;
            }

            // Nothing to rematch on, and 0 is a real jersey number, so the
            // query below would resolve this row onto whoever wears it.
            if (($row['num'] ?? null) === null) {
                $warnings[] = sprintf(
                    _("Player %s could not be restored."),
                    $row['name'] ?? $originalId,
                );
                $idMap[$originalId] = null;
                continue;
            }

            // uo_player has no unique constraint on (team, num), so fetch two
            // candidates: a match is trusted only when exactly one exists.
            $rematches = DBQueryToArray(sprintf(
                "SELECT player_id FROM uo_player WHERE team=%d AND num=%d LIMIT 2",
                (int) $row['team'],
                (int) $row['num'],
            ));
            if (count($rematches) !== 1) {
                $warnings[] = count($rematches) > 1
                    ? sprintf(
                        _("Player %s could not be restored: jersey number %d is not unique on team %s."),
                        $row['name'] ?? $originalId,
                        (int) $row['num'],
                        TeamName((int) $row['team']),
                    )
                    : sprintf(
                        _("Player %s could not be restored."),
                        $row['name'] ?? $originalId,
                    );
                $idMap[$originalId] = null;
                continue;
            }
            $candidateId = (int) $rematches[0]['player_id'];
            if (isset($consumedCandidates[$candidateId])) {
                $warnings[] = sprintf(
                    _("Player %s could not be restored: jersey number %d is not unique on team %s."),
                    $row['name'] ?? $originalId,
                    (int) $row['num'],
                    TeamName((int) $row['team']),
                );
                $idMap[$originalId] = null;
                continue;
            }
            $consumedCandidates[$candidateId] = true;
            $idMap[$originalId] = $candidateId;
            $playerId = $candidateId;
        }

        // Both num columns are nullable and 0 is a real jersey number, so an
        // unnumbered player stays SQL NULL rather than becoming a 0.
        $num = ($row['num'] ?? null) === null ? "NULL" : (string) (int) $row['num'];

        // The up-front guard only covers the teams the snapshot recorded, so a
        // player who has since moved teams needs the right rechecked against
        // their current one. A missing right downgrades this row rather than
        // aborting the restore.
        $acknowledged = !empty($row['acknowledged']) ? 1 : 0;
        if ($acknowledged) {
            $currentTeam = (int) DBQueryToValue(sprintf(
                "SELECT team FROM uo_player WHERE player_id=%d",
                $playerId,
            ));
            if (!hasAccredidationRight($currentTeam)) {
                $acknowledged = 0;
                $warnings[] = sprintf(
                    _("Acknowledgement not restored for player %s: accreditation right missing for their current team."),
                    $row['name'] ?? $playerId,
                );
            }
        }

        DBQuery(sprintf(
            "INSERT INTO uo_played (game, player, num, accredited, acknowledged, captain, spirit_captain)
			VALUES (%d, %d, %s, %d, %d, %d, %d)
			ON DUPLICATE KEY UPDATE num=VALUES(num), accredited=VALUES(accredited),
				acknowledged=VALUES(acknowledged), captain=VALUES(captain), spirit_captain=VALUES(spirit_captain)",
            (int) $gameId,
            (int) $playerId,
            $num,
            !empty($row['accredited']) ? 1 : 0,
            $acknowledged,
            !empty($row['captain']) ? 1 : 0,
            !empty($row['spirit_captain']) ? 1 : 0,
        ));

        // Matches GameAddPlayer()'s side effect on the team roster number, but
        // only while the player is still on the recorded team: after a
        // transfer that column belongs to a team the restoring admin may hold
        // no rights over.
        if ($currentTeams[$i] === null || $currentTeams[$i] === (int) $row['team']) {
            DBQuery(sprintf("UPDATE uo_player SET num=%s WHERE player_id=%d", $num, (int) $playerId));
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
    if (array_key_exists($playerId, $idMap)) {
        return $idMap[$playerId];
    }
    return $playerId;
}
