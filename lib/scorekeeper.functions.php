<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/common.functions.php';
require_once __DIR__ . '/user.functions.php';
require_once __DIR__ . '/game.functions.php';
require_once __DIR__ . '/reservation.functions.php';
require_once __DIR__ . '/series.functions.php';
require_once __DIR__ . '/logging.functions.php';

/*
 * Scorekeeping links. A token in uo_scorekeeper_token opens Scorekeeper for
 * one game or for every game in one reservation (a field for a block of time).
 * A logged-in user who opens the link gets a grant row in uo_scorekeeper_grant;
 * an anonymous session keeps the token id in $_SESSION['scorekeeper_tokens'],
 * which counts only while the event allows anonymous scorekeeping. See
 * docs/scorekeeper.md.
 */

/**
 * Normalizes a token from a URL, or returns '' when it cannot be one.
 */
function ScorekeeperTokenNormalize($token)
{
    $token = strtolower(trim(is_string($token) ? $token : ''));
    return (strlen($token) === 32 && ctype_xdigit($token)) ? $token : '';
}

/**
 * Who may print or share a game's scorekeeping link: the event and division
 * admins, and the reservation's game admins. A grant does not count, so a
 * link cannot be used to see or rotate the link itself.
 */
function CanIssueGameScorekeeperToken($gameId)
{
    $gameId = (int) $gameId;
    $series = GameSeries($gameId);
    if (empty($series)) {
        return false;
    }
    $season = SeriesSeasonId($series);
    $reservation = GameReservation($gameId);
    $roles = $_SESSION['userproperties']['userrole'] ?? [];
    $hasRight = isset($roles['superadmin'])
        || isset($roles['seasonadmin'][$season])
        || isset($roles['seriesadmin'][$series])
        || (!empty($reservation) && isset($roles['resgameadmin'][$reservation]));
    if (!$hasRight) {
        return false;
    }
    return !isEventReadonly($season) || canBypassEventReadonly($season);
}

/**
 * Who may print or share a reservation's scorekeeping link: the event admins,
 * the reservation's game admins, and the admins of a division with a game in it.
 */
function CanIssueReservationScorekeeperToken($reservationId)
{
    $reservationId = (int) $reservationId;
    $season = DBQueryToValue(sprintf("SELECT season FROM uo_reservation WHERE id=%d", $reservationId));
    if (empty($season)) {
        return false;
    }
    $roles = $_SESSION['userproperties']['userrole'] ?? [];
    $hasRight = isset($roles['superadmin'])
        || isset($roles['seasonadmin'][$season])
        || isset($roles['resgameadmin'][$reservationId]);
    if (!$hasRight && !empty($roles['seriesadmin'])) {
        $series = DBQueryToArray(sprintf(
            "SELECT DISTINCT p.series FROM uo_game g
				JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
				JOIN uo_pool p ON (p.pool_id=gp.pool)
			WHERE g.reservation=%d",
            $reservationId,
        ));
        foreach ($series as $row) {
            if (isset($roles['seriesadmin'][$row['series']])) {
                $hasRight = true;
                break;
            }
        }
    }
    if (!$hasRight) {
        return false;
    }
    return !isEventReadonly($season) || canBypassEventReadonly($season);
}

/**
 * Returns the current token for a game or reservation, creating it on first
 * use. Returns null when the user may not issue it, so a printout made by
 * someone else can leave the link out instead of failing.
 */
function ScorekeeperToken($scope, $id)
{
    $id = (int) $id;
    if ($scope === 'game') {
        $allowed = CanIssueGameScorekeeperToken($id);
    } elseif ($scope === 'reservation') {
        $allowed = CanIssueReservationScorekeeperToken($id);
    } else {
        return null;
    }
    if (!$allowed) {
        return null;
    }

    $select = sprintf("SELECT token FROM uo_scorekeeper_token WHERE %s=%d", $scope, $id);
    $token = DBQueryToValueUncached($select);
    if (empty($token)) {
        // INSERT IGNORE and re-read: two first printouts at the same time
        // must end up with one token, the unique key on the scope decides.
        DBQuery(sprintf(
            "INSERT IGNORE INTO uo_scorekeeper_token (token, %s) VALUES ('%s', %d)",
            $scope,
            bin2hex(random_bytes(16)),
            $id,
        ));
        $token = DBQueryToValueUncached($select);
    }
    return empty($token) ? null : (string) $token;
}

/**
 * Replaces a game's or reservation's token. The old link stops working and
 * everyone who opened it loses access.
 */
function ScorekeeperRotateToken($scope, $id)
{
    if (ScorekeeperToken($scope, $id) === null) {
        return null;
    }
    DBQuery(sprintf("DELETE FROM uo_scorekeeper_token WHERE %s=%d", $scope, (int) $id));
    Log1("security", "change", (string) (int) $id, $scope, "scorekeeping link rotated");
    CacheForgetNamespace("scorekeeper_grant");
    return ScorekeeperToken($scope, $id);
}

/**
 * Absolute Scorekeeper URL for a token.
 */
function ScorekeeperTokenUrl($token)
{
    return BASEURL . "/scorekeeper/?t=" . rawurlencode((string) $token);
}

/**
 * The token row with the event it belongs to, or null.
 */
function ScorekeeperTokenRow($tokenId)
{
    $row = DBQueryToRowUncached(sprintf(
        "SELECT t.token_id, t.game, t.reservation, r.season AS reservation_season
			FROM uo_scorekeeper_token t
			LEFT JOIN uo_reservation r ON (r.id=t.reservation)
		WHERE t.token_id=%d",
        (int) $tokenId,
    ));
    if (!is_array($row)) {
        return null;
    }
    $row['season'] = !empty($row['game']) ? GameSeason((int) $row['game']) : $row['reservation_season'];
    return $row;
}

/**
 * Short text naming what a token opens, for the login page and the notice
 * after opening a link.
 */
function ScorekeeperTokenDescription($tokenId)
{
    $row = ScorekeeperTokenRow($tokenId);
    if ($row === null) {
        return "";
    }
    if (!empty($row['game'])) {
        $game = GameInfo((int) $row['game']);
        if (!is_array($game)) {
            return "";
        }
        return GameName($game);
    }
    $reservation = ReservationInfo((int) $row['reservation']);
    if (!is_array($reservation)) {
        return "";
    }
    return ReservationPlaceText(U_($reservation['name']), U_($reservation['fieldname'])) . ", " . ShortDate($reservation['starttime']);
}

/**
 * Whether the event lets a scorekeeping link work without logging in.
 */
function IsAnonymousScorekeepingAllowed($season)
{
    if (empty($season)) {
        return false;
    }
    return (int) DBQueryToValueUncached(sprintf(
        "SELECT anonymous_scorekeeping FROM uo_season WHERE season_id='%s'",
        DBEscapeString((string) $season),
    )) === 1;
}

/**
 * Opens a scorekeeping link for this session.
 *
 * Returns ['status' => 'invalid'] for an unknown token, 'login' when the user
 * has to log in first (the token is kept as pending and claimed by
 * ScorekeeperClaimSessionTokens()), or 'granted' with the token id and event.
 */
function ScorekeeperRedeemToken($token)
{
    $token = ScorekeeperTokenNormalize($token);
    $tokenId = $token === '' ? 0 : (int) DBQueryToValueUncached(sprintf(
        "SELECT token_id FROM uo_scorekeeper_token WHERE token='%s'",
        DBEscapeString($token),
    ));
    $row = $tokenId > 0 ? ScorekeeperTokenRow($tokenId) : null;
    if ($row === null) {
        unset($_SESSION['scorekeeper_pending_token']);
        return ['status' => 'invalid'];
    }

    if (isLoggedIn()) {
        ScorekeeperAddGrant($tokenId, $_SESSION['uid']);
        return ['status' => 'granted', 'token_id' => $tokenId, 'season' => $row['season']];
    }

    if (IsAnonymousScorekeepingAllowed($row['season'])) {
        $tokens = ScorekeeperSessionTokenIds();
        $tokens[] = $tokenId;
        // A session that gains access gets a new id, like a login does.
        regenerateSessionId();
        $_SESSION['scorekeeper_tokens'] = array_values(array_unique($tokens));
        unset($_SESSION['scorekeeper_pending_token']);
        CacheForgetNamespace("scorekeeper_grant");
        return ['status' => 'granted', 'token_id' => $tokenId, 'season' => $row['season']];
    }

    $_SESSION['scorekeeper_pending_token'] = $tokenId;
    return ['status' => 'login', 'token_id' => $tokenId, 'season' => $row['season']];
}

/**
 * Turns the tokens this session opened before logging in -- the pending one
 * and any used anonymously -- into grants for the logged-in user. Returns the
 * event of the last one claimed, or null.
 */
function ScorekeeperClaimSessionTokens()
{
    if (!isLoggedIn()) {
        return null;
    }
    $tokenIds = ScorekeeperSessionTokenIds();
    if (!empty($_SESSION['scorekeeper_pending_token'])) {
        $tokenIds[] = (int) $_SESSION['scorekeeper_pending_token'];
    }
    unset($_SESSION['scorekeeper_pending_token'], $_SESSION['scorekeeper_tokens']);

    $season = null;
    foreach (array_unique($tokenIds) as $tokenId) {
        $row = ScorekeeperTokenRow($tokenId);
        if ($row !== null) {
            ScorekeeperAddGrant($tokenId, $_SESSION['uid']);
            $season = $row['season'];
        }
    }
    return $season;
}

function ScorekeeperAddGrant($tokenId, $userId)
{
    DBQuery(sprintf(
        "INSERT IGNORE INTO uo_scorekeeper_grant (token_id, userid) VALUES (%d, '%s')",
        (int) $tokenId,
        DBEscapeString((string) $userId),
    ));
    CacheForgetNamespace("scorekeeper_grant");
}

/**
 * Token ids an anonymous session has opened.
 *
 * @return int[]
 */
function ScorekeeperSessionTokenIds()
{
    $ids = $_SESSION['scorekeeper_tokens'] ?? [];
    return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
}

/**
 * Whether an anonymous session may use Scorekeeper at all. Each edit is still
 * checked per game by ScorekeeperGrantTokenId().
 */
function ScorekeeperSessionHasAnonymousAccess()
{
    return !isLoggedIn() && ScorekeeperSessionTokenIds() !== [];
}

/**
 * SQL pieces that limit uo_scorekeeper_token (alias t) to the tokens this
 * session holds, or null when it holds none.
 *
 * @return array{join: string, where: string}|null
 */
function ScorekeeperSessionTokenSql()
{
    if (isLoggedIn()) {
        return [
            'join' => sprintf(
                "JOIN uo_scorekeeper_grant sg ON (sg.token_id=t.token_id AND sg.userid='%s')",
                DBEscapeString($_SESSION['uid']),
            ),
            'where' => "",
        ];
    }
    $ids = ScorekeeperSessionTokenIds();
    if ($ids === []) {
        return null;
    }
    return ['join' => "", 'where' => " AND t.token_id IN (" . implode(",", $ids) . ")"];
}

/**
 * Id of a token held by this session that covers the game, or 0.
 *
 * Everything is checked live: a rotated token no longer exists, a game moved
 * out of the reservation is no longer covered, a reservation token covers its
 * games only on the reservation's date (the same date test as the "Show today
 * only" filter in scorekeeper/respgames.php), and an anonymous session needs
 * the event to still allow anonymous scorekeeping. Where the grant counts is
 * decided by ScorekeeperGrantCovers().
 */
function ScorekeeperGrantTokenId($gameId)
{
    $gameId = (int) $gameId;
    if ($gameId <= 0) {
        return 0;
    }
    $sql = ScorekeeperSessionTokenSql();
    if ($sql === null) {
        return 0;
    }
    if (!isLoggedIn() && !IsAnonymousScorekeepingAllowed(GameSeason($gameId))) {
        return 0;
    }
    $key = ($_SESSION['uid'] ?? '') . ":" . $gameId . ":" . md5($sql['join'] . $sql['where']);
    return (int) CacheRemember("scorekeeper_grant", $key, function () use ($gameId, $sql) {
        return (int) DBQueryToValueUncached(sprintf(
            "SELECT t.token_id FROM uo_scorekeeper_token t
				%s
				JOIN uo_game g ON (g.game_id=%d)
				LEFT JOIN uo_reservation r ON (r.id=t.reservation)
			WHERE (t.game=g.game_id OR (t.reservation=g.reservation AND DATE(r.starttime)='%s'))%s
			ORDER BY t.token_id LIMIT 1",
            $sql['join'],
            $gameId,
            DBEscapeString(date('Y-m-d')),
            $sql['where'],
        ));
    });
}

/**
 * Games of an event that this session's scorekeeping links currently cover.
 *
 * @return int[]
 */
function ScorekeeperGrantedGameIds($season)
{
    $sql = ScorekeeperSessionTokenSql();
    if ($sql === null || (!isLoggedIn() && !IsAnonymousScorekeepingAllowed($season))) {
        return [];
    }
    $rows = DBQueryToArrayUncached(sprintf(
        "SELECT DISTINCT g.game_id FROM uo_scorekeeper_token t
			%s
			LEFT JOIN uo_reservation r ON (r.id=t.reservation)
			JOIN uo_game g ON (g.game_id=t.game OR (g.reservation=t.reservation AND DATE(r.starttime)='%s'))
			JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
			JOIN uo_pool p ON (p.pool_id=gp.pool)
			JOIN uo_series s ON (s.series_id=p.series)
		WHERE s.season='%s'%s",
        $sql['join'],
        DBEscapeString(date('Y-m-d')),
        DBEscapeString((string) $season),
        $sql['where'],
    ));
    return array_map('intval', array_column($rows, 'game_id'));
}

/**
 * Events in which this session holds a scorekeeping link, so Scorekeeper can
 * offer them even when they are not marked current.
 *
 * @return string[]
 */
function ScorekeeperGrantedSeasonIds()
{
    $sql = ScorekeeperSessionTokenSql();
    if ($sql === null) {
        return [];
    }
    $rows = DBQueryToArrayUncached(sprintf(
        "SELECT DISTINCT COALESCE(s.season, r.season) AS season FROM uo_scorekeeper_token t
			%s
			LEFT JOIN uo_reservation r ON (r.id=t.reservation)
			LEFT JOIN uo_game_pool gp ON (gp.game=t.game AND gp.timetable=1)
			LEFT JOIN uo_pool p ON (p.pool_id=gp.pool)
			LEFT JOIN uo_series s ON (s.series_id=p.series)
		WHERE 1=1%s",
        $sql['join'],
        $sql['where'],
    ));
    $seasons = array_filter(array_column($rows, 'season'));
    if (!isLoggedIn()) {
        $seasons = array_filter($seasons, 'IsAnonymousScorekeepingAllowed');
    }
    return array_values($seasons);
}

/**
 * The message left by opening a scorekeeping link, as HTML, cleared once read.
 */
function ScorekeeperTakeNoticeHtml()
{
    $notice = $_SESSION['scorekeeper_notice'] ?? null;
    unset($_SESSION['scorekeeper_notice']);
    if (!is_array($notice)) {
        return "";
    }
    if (($notice['type'] ?? '') === 'invalid') {
        return "<p class='warning'>" . utf8entities(_("This scorekeeping link is no longer valid. Ask the event organizer for a new one.")) . "</p>\n";
    }
    $description = ScorekeeperTokenDescription((int) ($notice['token'] ?? 0));
    if ($description === "") {
        return "";
    }
    return "<p>" . utf8entities(sprintf(_("You can keep score for %s."), $description)) . "</p>\n";
}
