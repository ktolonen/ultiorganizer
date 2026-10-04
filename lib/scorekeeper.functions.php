<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/common.functions.php';
require_once __DIR__ . '/user.functions.php';
require_once __DIR__ . '/game.functions.php';
require_once __DIR__ . '/reservation.functions.php';
require_once __DIR__ . '/season.functions.php';
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
 * Who may print or share a game's scorekeeping link: the event admins. A
 * grant does not count, so a link cannot be used to see or rotate the link
 * itself.
 */
function CanIssueGameScorekeeperToken($gameId)
{
    $season = GameSeason((int) $gameId);
    return !empty($season) && isSeasonAdmin($season)
        && (!isEventReadonly($season) || canBypassEventReadonly($season));
}

/**
 * Who may print or share a reservation's scorekeeping link: the event admins.
 */
function CanIssueReservationScorekeeperToken($reservationId)
{
    $season = ReservationSeason((int) $reservationId);
    return !empty($season) && isSeasonAdmin($season)
        && (!isEventReadonly($season) || canBypassEventReadonly($season));
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
    if (!ScorekeeperRevokeToken($scope, $id)) {
        return null;
    }
    return ScorekeeperToken($scope, $id);
}

/**
 * Deletes a game's or reservation's token, which revokes every grant it gave.
 * A new link is created the next time one is shown or printed.
 */
function ScorekeeperRevokeToken($scope, $id)
{
    $allowed = $scope === 'game' ? CanIssueGameScorekeeperToken($id)
        : ($scope === 'reservation' && CanIssueReservationScorekeeperToken($id));
    if (!$allowed) {
        return false;
    }
    DBQuery(sprintf("DELETE FROM uo_scorekeeper_token WHERE %s=%d", $scope, (int) $id));
    Log1("security", "change", (string) (int) $id, $scope, "scorekeeping link revoked");
    CacheForgetNamespace("scorekeeper_grant");
    return true;
}

/**
 * The event's existing scorekeeping links, keyed 'game:<id>' and
 * 'reservation:<id>', each with its creation time, the users who opened it
 * and the number of scoresheet changes anonymous sessions made through it.
 * Event admins only; links are not created here.
 *
 * @return array<string, array{token_id: int, created: string, users: array<int, array{userid: string, name: ?string}>, anonymous: int}>
 */
function SeasonScorekeeperTokens($season)
{
    if (!isSeasonAdmin($season)) {
        return [];
    }
    $rows = DBQueryToArrayUncached(sprintf(
        "SELECT t.token_id, t.game, t.reservation, t.created, sg.userid, u.name
			FROM uo_scorekeeper_token t
			LEFT JOIN uo_reservation r ON (r.id=t.reservation)
			LEFT JOIN uo_game_pool gp ON (gp.game=t.game AND gp.timetable=1)
			LEFT JOIN uo_pool p ON (p.pool_id=gp.pool)
			LEFT JOIN uo_series s ON (s.series_id=p.series)
			LEFT JOIN uo_scorekeeper_grant sg ON (sg.token_id=t.token_id)
			LEFT JOIN uo_users u ON (u.userid=sg.userid)
		WHERE r.season='%1\$s' OR s.season='%1\$s'
		ORDER BY t.token_id, sg.created",
        DBEscapeString((string) $season),
    ));
    $tokens = [];
    foreach ($rows as $row) {
        $key = !empty($row['game']) ? "game:" . (int) $row['game'] : "reservation:" . (int) $row['reservation'];
        if (!isset($tokens[$key])) {
            $tokens[$key] = ['token_id' => (int) $row['token_id'], 'created' => $row['created'], 'users' => [], 'anonymous' => 0];
        }
        if ($row['userid'] !== null) {
            $tokens[$key]['users'][] = ['userid' => $row['userid'], 'name' => $row['name']];
        }
    }
    if ($tokens !== []) {
        $keys = [];
        foreach ($tokens as $key => $token) {
            $keys[$token['token_id']] = $key;
        }
        $counts = DBQueryToArrayUncached(sprintf(
            "SELECT scorekeeper_token, COUNT(*) AS changes FROM uo_scoresheet_history
			WHERE user_id='anonymous' AND has_snapshot=0 AND scorekeeper_token IN (%s)
			GROUP BY scorekeeper_token",
            implode(",", array_keys($keys)),
        ));
        foreach ($counts as $count) {
            $tokens[$keys[(int) $count['scorekeeper_token']]]['anonymous'] = (int) $count['changes'];
        }
    }
    return $tokens;
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
        if (!$game['hometeam'] || !$game['visitorteam']) {
            $game['phometeamname'] = U_($game['phometeamname']);
            $game['pvisitorteamname'] = U_($game['pvisitorteamname']);
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
 * What a game's or reservation's link opens: its event, a heading, and the
 * games it covers. Returns null for an unknown game or reservation.
 *
 * @return array{season: string, subject: string, games: array<int, array<string, mixed>>}|null
 */
function ScorekeeperLinkTarget($scope, $id)
{
    if ($scope === 'game') {
        $game = GameInfo((int) $id);
        if (!is_array($game)) {
            return null;
        }
        $named = $game;
        if (!$game['hometeam'] || !$game['visitorteam']) {
            $named['phometeamname'] = U_($game['phometeamname']);
            $named['pvisitorteamname'] = U_($game['pvisitorteamname']);
        }
        return ['season' => $game['season'], 'subject' => GameName($named), 'games' => [$game]];
    }
    $reservation = ReservationInfo((int) $id);
    if (!is_array($reservation)) {
        return null;
    }
    return [
        'season' => $reservation['season'],
        'subject' => ReservationPlaceText(U_($reservation['name']), U_($reservation['fieldname'])) . ", " . DefWeekDateFormat($reservation['starttime']),
        // A field link covers only the games of the reservation's own event.
        'games' => ReservationGames((int) $id, $reservation['season']),
    ];
}

/**
 * The games a link covers as a small table, as HTML.
 */
function ScorekeeperLinkGamesHtml($games)
{
    $html = "<table class='scorekeeping-link-games'>";
    foreach ($games as $row) {
        $home = $row['hometeam'] ? $row['hometeamname'] : U_($row['phometeamname']);
        $away = $row['visitorteam'] ? $row['visitorteamname'] : U_($row['pvisitorteamname']);
        $html .= "<tr><td>" . DefHourFormat($row['time']) . "</td><td>" . utf8entities($home) . " - " . utf8entities($away) . "</td>"
            . "<td>" . utf8entities(U_($row['seriesname'])) . ", " . utf8entities(U_($row['poolname'])) . "</td></tr>";
    }
    return $html . "</table>";
}

/**
 * The printable sheet for a game's or reservation's link, as HTML. Creates the
 * link on first use like ScorekeeperToken(), and returns '' when the user may
 * not issue it.
 */
function ScorekeeperLinkSheetHtml($scope, $id)
{
    $target = ScorekeeperLinkTarget($scope, $id);
    $token = $target === null ? null : ScorekeeperToken($scope, $id);
    if ($token === null) {
        return "";
    }
    $url = ScorekeeperTokenUrl($token);
    $html = "<div class='scorekeeping-sheet'>";
    $html .= "<h1>" . utf8entities(SeasonName($target['season'])) . "</h1>";
    $html .= "<h2>" . utf8entities($target['subject']) . "</h2>";
    $html .= ScorekeeperLinkGamesHtml($target['games']);
    $html .= "<div class='scorekeeping-qr'>" . ScorekeeperQrSvg($url, 8) . "</div>";
    $html .= "<p>" . utf8entities(_("Scan to keep score in Scorekeeper.")) . "</p>";
    $html .= "<p class='scorekeeping-url'>" . utf8entities($url) . "</p>";
    return $html . "</div>";
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
 * Whether an anonymous session may use Scorekeeper at all: it must hold a
 * token that still exists in an event that still allows anonymous
 * scorekeeping. Each game is still checked by ScorekeeperGrantTokenId().
 */
function ScorekeeperSessionHasAnonymousAccess()
{
    if (!IsScorekeeperApp() || isLoggedIn()) {
        return false;
    }
    $tokenIds = ScorekeeperSessionTokenIds();
    if ($tokenIds === []) {
        return false;
    }
    return (bool) CacheRemember("scorekeeper_grant", "anonymous:" . implode(",", $tokenIds), function () use ($tokenIds) {
        foreach ($tokenIds as $tokenId) {
            $row = ScorekeeperTokenRow($tokenId);
            if ($row !== null && IsAnonymousScorekeepingAllowed($row['season'])) {
                return 1;
            }
        }
        return 0;
    });
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
 * Hours past midnight that a scorekeeping link still works for the day
 * before, so a late game keeps its link until it ends.
 */
const SCOREKEEPER_LINK_GRACE_HOURS = 4;

/**
 * The first and last game day (Y-m-d) on which scorekeeping links of an event
 * work right now: today in the event's timezone, and yesterday during the
 * first SCOREKEEPER_LINK_GRACE_HOURS. Game and reservation times are stored
 * as the event's local time.
 *
 * @return array{0: string, 1: string}
 */
function ScorekeeperOpenDays($season)
{
    $info = SeasonInfo($season);
    $timezone = is_array($info) ? (string) ($info['timezone'] ?? '') : '';
    try {
        $zone = new DateTimeZone($timezone !== '' ? $timezone : date_default_timezone_get());
    } catch (Exception $e) {
        $zone = new DateTimeZone(date_default_timezone_get());
    }
    $now = new DateTimeImmutable('now', $zone);
    return [$now->modify('-' . SCOREKEEPER_LINK_GRACE_HOURS . ' hours')->format('Y-m-d'), $now->format('Y-m-d')];
}

/**
 * SQL condition for a token (alias t) covering a game (alias g) of the event:
 * a game token on the game's day, or any day when the game has no time yet,
 * and a reservation token (reservation alias r) of the same event on the
 * reservation's day.
 */
function ScorekeeperCoverageSql($season)
{
    [$from, $to] = ScorekeeperOpenDays($season);
    $days = sprintf("BETWEEN '%s' AND '%s'", DBEscapeString($from), DBEscapeString($to));
    return sprintf(
        "((t.game=g.game_id AND (g.time IS NULL OR DATE(g.time) %s))
			OR (t.reservation=g.reservation AND r.season='%s' AND DATE(r.starttime) %s))",
        $days,
        DBEscapeString((string) $season),
        $days,
    );
}

/**
 * Id of a token held by this session that covers the game, or 0.
 *
 * Everything is checked live: a rotated token no longer exists, a game moved
 * out of the reservation is no longer covered, a token works only on its game
 * day (ScorekeeperOpenDays()), and an anonymous session needs the event to
 * still allow anonymous scorekeeping. Where the grant counts is decided by
 * ScorekeeperGrantCovers().
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
    $key = ($_SESSION['uid'] ?? '') . ":" . $gameId . ":" . md5($sql['join'] . $sql['where']);
    return (int) CacheRemember("scorekeeper_grant", $key, function () use ($gameId, $sql) {
        $season = GameSeason($gameId);
        if (empty($season) || (!isLoggedIn() && !IsAnonymousScorekeepingAllowed($season))) {
            return 0;
        }
        return (int) DBQueryToValueUncached(sprintf(
            "SELECT t.token_id FROM uo_scorekeeper_token t
				%s
				JOIN uo_game g ON (g.game_id=%d)
				LEFT JOIN uo_reservation r ON (r.id=t.reservation)
			WHERE %s%s
			ORDER BY t.token_id LIMIT 1",
            $sql['join'],
            $gameId,
            ScorekeeperCoverageSql($season),
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
			JOIN uo_game g ON %s
			JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
			JOIN uo_pool p ON (p.pool_id=gp.pool)
			JOIN uo_series s ON (s.series_id=p.series)
		WHERE s.season='%s'%s",
        $sql['join'],
        ScorekeeperCoverageSql($season),
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
    $tokenId = (int) ($notice['token'] ?? 0);
    $description = ScorekeeperTokenDescription($tokenId);
    if ($description === "") {
        return "";
    }
    // A link opened on another day covers nothing yet, so say when it works
    // instead of promising access.
    $row = ScorekeeperTokenRow($tokenId);
    if (!empty($row['game'])) {
        $game = GameInfo((int) $row['game']);
        $time = is_array($game) ? (string) ($game['time'] ?? '') : '';
    } else {
        $reservation = ReservationInfo((int) $row['reservation']);
        $time = is_array($reservation) ? (string) ($reservation['starttime'] ?? '') : '';
    }
    if ($time !== '') {
        [$from, $to] = ScorekeeperOpenDays($row['season']);
        $day = substr($time, 0, 10);
        if ($day < $from || $day > $to) {
            return "<p class='warning'>" . utf8entities(sprintf(_("This scorekeeping link works only on %s."), ShortDate($time))) . "</p>\n";
        }
    }
    return "<p>" . utf8entities(sprintf(_("You can keep score for %s."), $description)) . "</p>\n";
}

/**
 * A QR code as inline SVG, drawn from phpqrcode's module matrix so no image
 * file or response header is involved.
 */
function ScorekeeperQrSvg($text, $moduleSize = 6)
{
    include_once __DIR__ . '/phpqrcode/qrlib.php';
    $margin = 4;
    $rows = QRcode::text((string) $text, false, QR_ECLEVEL_M, 1, 0);
    if (!is_array($rows) || $rows === []) {
        return "";
    }
    $count = count($rows);
    $size = ($count + 2 * $margin) * $moduleSize;
    $path = "";
    foreach ($rows as $y => $row) {
        $length = strlen($row);
        for ($x = 0; $x < $length; $x++) {
            if ($row[$x] === '1') {
                $path .= "M" . (($x + $margin) * $moduleSize) . "," . (($y + $margin) * $moduleSize) . "h$moduleSize" . "v$moduleSize" . "h-$moduleSize" . "z";
            }
        }
    }
    return "<svg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 $size $size' role='img'>"
        . "<rect width='100%' height='100%' fill='#fff'/><path d='$path' fill='#000'/></svg>";
}
