<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/cache.functions.php';
require_once __DIR__ . '/comment.functions.php';
// Safe to require directly, unlike comment.functions.php/accreditation.functions.php
// (which game.functions.php loads before this file, so requiring this file back
// from either of them would cycle -- see their function_exists() guards).
// user.functions.php's own top-level requires never reach back to
// game.functions.php or this file, only a lazy require inside a function body,
// so there is no cycle here. GameHistoryList()/Count() below already assumed
// this file's rights functions were available; this makes that assumption
// explicit instead of relying on some other caller having loaded it first.
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

/**
 * Every legitimate caller has already authorized its own write; this is the
 * backstop for a future caller that has not. It accepts the union of the
 * rights actually held by today's callers -- hasEditGameEventsRight() (most
 * mutators), hasEditGamePlayersRight() (GameAddPlayer()/GameAddNewPlayer()),
 * hasAccredidationRight() against either of the game's two teams
 * (AcknowledgeUnaccredited()/UnAcknowledgeUnaccredited()), and
 * hasAddMediaRight() (AddGameMediaEvent()/RemoveGameMediaEvent()).
 * Narrowing this to only the first right would silently stop recording for
 * accreditation-only or media-only admins.
 *
 * hasAddMediaRight() carries no game or team scope at all -- unlike the
 * other three, it is true for any logged-in session -- so it is accepted
 * only when $target is 'mediaevent', the one target the media-only path
 * ever writes. AddGameMediaEvent()/RemoveGameMediaEvent() never call
 * GameHistorySnapshotIfNeeded() (media links are excluded from snapshots,
 * see GameHistoryBuildSnapshot()), so a caller passing no $target -- as
 * GameHistorySnapshotIfNeeded() does -- never reaches the media branch.
 *
 * $allowAnonymousResult is a separate signal from the four rights above: it
 * is set only by GameSetResult() when it was itself called with
 * $checkRights=false, the ANONYMOUS_RESULT_INPUT self-report route (see
 * result.php/scorekeeper/result.php) where the caller never held any
 * session-scoped game right to begin with. The flag alone grants nothing --
 * it is re-validated here against the installation's own
 * ANONYMOUS_RESULT_INPUT constant, so a future $checkRights=false caller on
 * an installation where that setting is off still hits the ordinary rights
 * checks above and is refused.
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
    // reaches none of the checks above -- without this branch it would change
    // the note with neither a restore point nor an audit row.
    //
    // Preferred source is CanManageGameComment() itself, which derives
    // authorship server-side. It cannot answer after a delete: ApplyCommentChange()
    // logs comment_delete, and GameCommentMeta() then treats that as a cutoff
    // and finds no comment_create after it, so created_by comes back empty.
    // The post-write record call therefore passes the authorship captured
    // before the write. Only the identity being claimed crosses the boundary;
    // that it is the CURRENT session's identity is still checked here.
    // CanManageGameComment() lets a note's original author edit or delete it
    // after losing hasEditGameEventsRight(), so that write is legitimate but
    // reaches none of the checks above. Authorship is resolved here, server
    // side, and never accepted as an argument: a caller-supplied identity
    // compared against the caller's own session proves nothing, since
    // $_SESSION['uid'] is exactly what such a caller would pass.
    //
    // This is why SetGameComment() records BEFORE ApplyCommentChange():
    // that call logs comment_delete, GameCommentMeta() then treats it as a
    // cutoff and finds no comment_create after it, so afterwards
    // CanManageGameComment() can no longer recognise the author. Recording
    // first keeps the check answerable without trusting the caller.
    if (
        $target === 'comment'
        && function_exists('CanManageGameComment') && defined('COMMENT_TYPE_GAME')
        && CanManageGameComment($gameId, COMMENT_TYPE_GAME)
    ) {
        return true;
    }

    if ($allowAnonymousResult && defined('ANONYMOUS_RESULT_INPUT') && ANONYMOUS_RESULT_INPUT) {
        return true;
    }

    // Scoped to the played target, like the media and comment branches above.
    // hasAccredidationRight() grants acknowledgement changes on a team's
    // roster and nothing else, so leaving this unscoped would let an
    // accreditation-only caller reach the reusable helpers directly and forge
    // result, goal or forfeit rows -- or capture a whole snapshot -- for a
    // fixture they cannot otherwise edit. AcknowledgeUnaccredited() and
    // UnAcknowledgeUnaccredited() are the callers this exists for, and both
    // record against "played".
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
    // acknowledged by an admin of their new team -- while the fixture's own
    // two teams say nothing about that right. Checking only those would let
    // the acknowledgement succeed and silently refuse both its snapshot and
    // its audit row, which is the one outcome this feature exists to prevent.
    //
    // Derived from the roster rather than taken as an argument: the caller
    // supplies nothing, so there is no identity to forge. It is marginally
    // broader than the transferred player alone -- an admin holding the right
    // on any rostered player's current team can record "played" history for
    // this game -- which is the deliberate cost of having no such argument.
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
    // $force exists solely for GameHistoryRestore()'s own restore-audit row:
    // the setting governs routine recording volume, not the safety of an
    // explicit destructive admin action, so that row must not be suppressed
    // by DisableGameHistory. Suppression (mid-replay change rows) is not
    // affected by $force.
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

    // result.php/scorekeeper/result.php skip their own auth guard (which
    // would otherwise stamp $_SESSION['uid']='anonymous' for a guest, see
    // auth.guard.php) precisely when ANONYMOUS_RESULT_INPUT is enabled, so a
    // truly session-less submission through that route leaves 'uid' unset.
    // "anonymous" marks that origin distinctly from "unknown", which stays
    // reserved for a missing session on every other, non-validated path.
    $anonymous = empty($_SESSION['uid'])
        && $allowAnonymousResult && defined('ANONYMOUS_RESULT_INPUT') && ANONYMOUS_RESULT_INPUT;
    $userId = !empty($_SESSION['uid']) ? $_SESSION['uid'] : ($anonymous ? "anonymous" : "unknown");
    // DisableVisitorLogging is documented as stopping IP recording entirely
    // (docs/privacy.md), so it suppresses the address here too. Applied to
    // every row rather than only anonymous ones: an anonymous row is the
    // sharpest case, since registered-user deletion cannot reach it and the
    // row lives until the game is deleted, but the promise is unconditional.
    // user.functions.php (required at the top of this file) pulls in
    // logging.functions.php, so this is never an undefined call that would
    // silently fall back to recording.
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
    // game.functions.php requires this file (see the comment on
    // GameHistoryRestore()), so GameTimerState() below is required lazily
    // here to break the same include cycle.
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
    // v3 adds timer_elapsed: the game time GameTimerState() computes as
    // already elapsed at capture time, reusing its own arithmetic rather
    // than reimplementing it. The result replay in GameHistoryRestore() uses
    // this to derive
    // a fresh timer_start at restore time instead of replaying the stale
    // absolute epoch -- see the comment there. A v1/v2 snapshot lacks this
    // key and falls back to the old (documented) verbatim-epoch restore.
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
function GameHistorySnapshotIfNeeded($gameId, $force = false, $allowAnonymousResult = false, $target = null)
{
    // $force is GameHistoryRestore()'s pre-restore capture: the setting
    // governs routine recording volume, not the safety of an explicit
    // destructive admin action, so a forced capture must still write even
    // while recording is disabled -- otherwise a restore under
    // DisableGameHistory would be unrecoverable.
    if (IsGameHistoryDisabled() && !$force) {
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

    if (!GameHistoryAuthorized($gameId, $target, $allowAnonymousResult)) {
        return false;
    }

    if ($force) {
        CacheForgetNamespace("game_history_snapshot");
    }

    // Inlined rather than kept as a standalone GameHistoryWriteSnapshot()
    // function: that function performed the uo_game_history insert with none
    // of the guards above applied, and its only caller was this closure, so
    // it was an ungated public entry point in practice. Folding it in here
    // removes that entry point instead of guarding it.
    return CacheRemember("game_history_snapshot", $gameId, function () use ($gameId, $allowAnonymousResult) {
        $json = json_encode(GameHistoryBuildSnapshot($gameId), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        // See GameHistoryRecord()'s identical "anonymous" derivation.
        $anonymous = empty($_SESSION['uid'])
            && $allowAnonymousResult && defined('ANONYMOUS_RESULT_INPUT') && ANONYMOUS_RESULT_INPUT;
        $userId = !empty($_SESSION['uid']) ? $_SESSION['uid'] : ($anonymous ? "anonymous" : "unknown");
        // See GameHistoryRecord()'s identical address suppression.
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
        // admin/gamehistory.php feeds a bare YYYY-MM-DD from <input type='date'>,
        // which MySQL widens to 00:00:00 -- so a plain <= would exclude every
        // row recorded on the chosen end date.
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
 * $allowMismatchedFixture defaults to false so that reads withhold a snapshot
 * whose recorded teams are no longer the game's teams. SetGame() can move a
 * game to another pool or change its responsible team, and hasEditGameEventsRight()
 * resolves through both, so an admin who gains rights that way would otherwise
 * read a snapshot describing teams they have no rights over. Only
 * GameHistoryRestore() opts in, because it reports the mismatch as a specific
 * refusal rather than silently showing nothing.
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

    // Computed here so restore and reads share one comparison. Positional
    // (home-to-home, visitor-to-visitor): a set comparison would pass
    // GameChangeHome()'s swap. Pre-v4 snapshots carry neither key and are
    // treated as matching, the same fallback the restore guard documents.
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
 * still run. game.functions.php requires this file, so it is required lazily
 * here to break the include cycle.
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

    // Restoring an "acknowledged" flag (see GameHistoryRestorePlayers()) writes
    // uo_played directly rather than calling AcknowledgeUnaccredited(), but it
    // is still an accreditation mutation and needs hasAccredidationRight() --
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

    // GameHistoryEntry() computed this: SetGame() (reassignment) and
    // GameChangeHome() (swap) are not scoresheet mutators and never snapshot,
    // so a snapshot taken before either change would replay the OLD teams'
    // roster, goals and defenses onto whatever fixture now sits at this
    // game_id. Unlike IsPoolLocked()/IsSeasonStatsCalculated() below, which
    // are policy conditions no stricter than an ordinary result edit, that is
    // corruption rather than policy -- so this rejects instead of warning.
    // Checked here rather than at load so an unauthorized caller cannot learn
    // the fixture changed. A pre-v4 snapshot has neither team key and the
    // mismatch cannot be detected, so restore proceeds as before.
    if (!empty($entry['fixture_mismatch'])) {
        return [
            'restored' => false,
            'warnings' => [_("Restore refused: the home and away teams have changed since this snapshot was taken.")],
        ];
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
        $idMap = GameHistoryRestorePlayers($historyId, $warnings);

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

        // uo_gameevent gets no explicit RemoveAll* before replay like goals,
        // defences and timeouts do, and GameSetCapEvent() is upsert-only, so an
        // event set after the snapshot (e.g. a time cap) would otherwise
        // survive a restore. Media rows are excluded -- see the comment on
        // GameRemoveAllGameEvents().
        GameRemoveAllGameEvents($gameId);
        foreach ($snapshot['events'] ?? [] as $event) {
            // The snapshot stores the raw uo_gameevent.type column value
            // ('offence'), not the "start" label GameHistoryFormatDetail() uses
            // for its rendered text -- those are two different vocabularies.
            if ($event['type'] == "offence") {
                GameSetStartingTeam($gameId, (int) $event['ishome']);
            } elseif (GameIsCapEventType($event['type'])) {
                // The cap target lives in info, not ishome -- caps carry no
                // team attribution (see GameHistoryBuildSnapshot()).
                GameSetCapEvent($gameId, $event['type'], (int) $event['time'], (int) $event['info']);
            }
        }

        GameSetScoreSheetKeeper($gameId, $snapshot['game']['official'] ?? null);
        $halftime = $snapshot['game']['halftime'] ?? null;
        GameSetHalftime($gameId, $halftime === null ? null : (int) $halftime);

        // Guarded by key presence, not just ?? 0: a v1 snapshot (see
        // GameHistoryBuildSnapshot()) never captured these, and a restore of
        // one must leave the current defence totals alone rather than zero
        // them.
        $gameFields = $snapshot['game'] ?? [];
        if (array_key_exists('homedefenses', $gameFields) && array_key_exists('visitordefenses', $gameFields)) {
            GameSetDefenses($gameId, (int) $gameFields['homedefenses'], (int) $gameFields['visitordefenses']);
        }

        // empty() would treat a comment of "0" as a delete: match
        // CommentRequestedChange()'s own === "" test instead.
        SetGameComment(COMMENT_TYPE_GAME, $gameId, $snapshot['comment'] ?? "", ($snapshot['comment'] ?? "") === "");

        // Inlined rather than a GameHistoryRestoreResult() helper: its only
        // caller was here, and as a public lib function it performed the raw
        // uo_game write below with no rights check of its own -- the mutators
        // it delegates to check rights, but their return value is discarded,
        // so a caller could reach the write past a refusal.
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

        // Guarded by key presence, not just ?? default: a v1 snapshot (see
        // GameHistoryBuildSnapshot()) never captured the timer columns. Of the
        // three branches above, GameClearResult() and GameSetResult() both NULL
        // the timer columns unconditionally as part of their own write, so a v1
        // restore into either state loses the clock regardless of this guard;
        // GameUpdateResult() (the isongoing branch) never touches them at all,
        // so a v1 restore into the ongoing state leaves the clock as-is. None of
        // the three has a timer setter for a v1/v2 snapshot's captured value, so
        // this writes the columns directly, in the same write-back as
        // hasstarted/isongoing so ordering against the mutators above is already
        // correct.
        if (array_key_exists('timer_start', $resultFields)) {
            if (array_key_exists('timer_elapsed', $resultFields) && $resultFields['timer_start'] !== null) {
                // v3: timer_start is an absolute Unix epoch (see GameTimerState()),
                // so replaying it verbatim would count every second between
                // capture and restore as game time. Instead, derive a fresh epoch
                // from the elapsed game time GameHistoryBuildSnapshot() captured
                // via GameTimerState() itself, reusing that function's own
                // elapsed formula rather than a second implementation of it.
                // `timer_start = now - elapsed, timer_paused_duration = 0` and
                // running the clock forward from `elapsed` reproduces exactly
                // `elapsed` if the snapshot was paused (freeze immediately, by
                // also setting timer_pause_start = now) or keeps counting up
                // from `elapsed` if it was running -- see docs/game-history.md.
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

        // Must run after the result replay above, not before: standings are
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

    // Unconditional (force=true): a restore's own audit row must survive even
    // while DisableGameHistory is set, the same as its pre-restore capture
    // above -- see the comment on GameHistoryRecord()'s $force parameter.
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
 * Rebuild uo_played and return a map from snapshot player ids to current ones.
 *
 * uo_goal declares ON DELETE SET NULL on both player keys, so a player deleted
 * since the snapshot cannot be resolved by id. The stored jersey number and
 * team are the fallback, and anything still unmatched is reported rather than
 * silently dropped.
 *
 * Rows are written directly rather than through GameAddPlayer(): that
 * mutator's GameAllowsPlayerOnRoster() gate rejects an unaccredited player in
 * a require_accreditation season, including one the snapshot recorded as
 * acknowledged -- the roster was just emptied above, so the gate's own
 * "already on this game's roster" exception can no longer rescue them. The
 * snapshot is evidence the player was legitimately on this roster, so restore
 * must not re-litigate that gate; the accreditation right for every
 * acknowledged team is already checked up front in GameHistoryRestore().
 */
function GameHistoryRestorePlayers($historyId, &$warnings)
{
    // Takes a history id, not a caller-supplied row set. The rows below are
    // written straight into uo_played, deliberately bypassing
    // GameAllowsPlayerOnRoster() (see this function's docblock), so accepting
    // rows as an argument would let any caller holding the ordinary player
    // rights add an unaccredited player to a require_accreditation season
    // through here. Deriving them from a stored snapshot removes the input
    // rather than validating it.
    //
    // The guard is re-run here rather than assumed from GameHistoryRestore():
    // this function has to be safe standing alone, since it sits in the
    // shared lib interface. It is the same superset that function computes --
    // both editing rights, plus the accreditation right for every team with
    // an acknowledged row.
    $idMap = [];

    $entry = GameHistoryEntry($historyId, true);
    if (!$entry || !is_array($entry['snapshot'])) {
        return $idMap;
    }
    $gameId = (int) $entry['game'];
    if (!hasEditGameEventsRight($gameId) || !hasEditGamePlayersRight($gameId)) {
        return $idMap;
    }

    $playedRows = $entry['snapshot']['played'] ?? [];
    foreach ($playedRows as $row) {
        if (!empty($row['acknowledged']) && !hasAccredidationRight((int) $row['team'])) {
            return $idMap;
        }
    }

    // Snapshot-side ambiguity pre-scan, keyed on the same (team, num) the
    // rematch query below uses. If two snapshot rows whose ids no longer
    // exist share a jersey number, the rematch below can only ever return
    // one candidate for that number -- both rows would silently collapse
    // onto it, merging one player's goals/assists/defences onto another's.
    // This has to be caught before the loop below picks a match, because
    // loop order would otherwise let whichever row is processed first win
    // arbitrarily; a resolved-looking restore that quietly merged two
    // players is worse than warning about both.
    // $consumedCandidates is pre-seeded with every row whose id still
    // exists: that row will write directly to its own id (see the loop
    // below), so a rematch below that resolves to the same id has to be
    // refused too, not just a second rematch. It stays order-independent
    // this way: uo_player itself is not modified until a row actually
    // writes, so which row the loop reaches first cannot change what a
    // rematch query finds.
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

        // The row is still restored: GamePlayers() joins uo_played against the
        // player's CURRENT uo_player.team, so a player who changes teams
        // already lists under the new team for every past game they played --
        // restore reproduces the recorded roster rather than causing that.
        // Dropping them instead would make restore lossier than the state it
        // is reproducing, so this warns and continues.
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

            // A snapshot row with no jersey number has nothing to rematch on.
            // The pre-scan already leaves it out of the ambiguity grouping,
            // but the query below casts null to 0, and 0 is a real jersey
            // (uo_player.num is tinyint unsigned) -- so without this the row
            // would silently resolve onto whoever wears number 0 and inherit
            // this player's goals, assists and defences.
            if (($row['num'] ?? null) === null) {
                $warnings[] = sprintf(
                    _("Player %s could not be restored."),
                    $row['name'] ?? $originalId,
                );
                $idMap[$originalId] = null;
                continue;
            }

            // uo_player has no unique constraint on (team, num), so a naive
            // LIMIT 1 could attribute this row's goals, assists and defences
            // to an arbitrary teammate wearing the same number. Fetch up to
            // two candidates instead: a match is only trusted when exactly
            // one exists.
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

        // Written directly rather than through GameAddPlayer(), whose
        // GameAllowsPlayerOnRoster() gate would reject an unaccredited player
        // in a require_accreditation season -- see this function's docblock.
        // Preserved as SQL NULL rather than coerced to 0: the schema makes
        // both num columns nullable, and 0 is a jersey a player may actually
        // wear, so collapsing "unnumbered" onto it would invent a number here
        // and on the player's global uo_player row.
        $num = ($row['num'] ?? null) === null ? "NULL" : (string) (int) $row['num'];

        // The up-front guard in GameHistoryRestore() only checks
        // hasAccredidationRight() for the teams recorded in the SNAPSHOT. A
        // player who has since moved teams needs the right rechecked against
        // their CURRENT team before writing acknowledged=1, or an admin
        // holding the right only on the old team could grant an
        // acknowledgment on the new one. Missing the right does not abort the
        // restore -- as with an unresolvable player above, this row is
        // downgraded and warned about instead, so the rest of the restore
        // still completes.
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

        // Matches GameAddPlayer()'s existing side effect (see "Restoring a
        // roster rewrites uo_player.num" in docs/game-history.md), but only
        // while the player is still on the team the snapshot recorded. For a
        // player who has since transferred, this global column belongs to
        // their CURRENT team, which the restoring admin may hold no rights
        // over at all -- restore's authority is over this game's roster
        // (uo_played.num, written above), not over another team's squad
        // numbering. The game record is still reproduced either way.
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
