<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/accreditation.functions.php';
require_once __DIR__ . '/configuration.functions.php';
require_once __DIR__ . '/common.functions.php';

function SeasonScoreCounter($seasonId = "")
{
    $query = "SELECT COALESCE(SUM(game.homescore), 0) + COALESCE(SUM(game.visitorscore), 0) AS scores
		FROM uo_game game
		INNER JOIN uo_game_pool gp ON (gp.game=game.game_id AND gp.timetable=1)
		LEFT JOIN uo_pool pool ON (pool.pool_id=gp.pool)
		LEFT JOIN uo_series ser ON (pool.series=ser.series_id)";

    if (!empty($seasonId)) {
        $query .= sprintf(" WHERE ser.season='%s'", DBEscapeString($seasonId));
    }

    return (int) DBQueryToValue($query);
}

function GameSetPools($games)
{
    $gameIds = array_filter(array_map('intval', (array) $games), function ($val) {
        return $val > 0;
    });
    if (empty($gameIds)) {
        return [];
    }
    $query = "SELECT DISTINCT p.pool_id, p.name
        FROM uo_game g
        INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
        LEFT JOIN uo_pool p ON (p.pool_id=gp.pool)
        WHERE g.game_id in (";
    $query .= implode(",", $gameIds);
    $query .= ") ORDER BY p.ordering ASC";
    $result = DBQuery($query);

    $ret = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $ret[$row['pool_id']] = $row;
    }
    return $ret;
}

function PoolGameSetResults($pool, $games)
{
    $gameIds = array_filter(array_map('intval', (array) $games), function ($val) {
        return $val > 0;
    });
    if (empty($gameIds)) {
        return [];
    }
    $query = sprintf(
        "SELECT time, k.name As hometeamname, v.name As visitorteamname, p.*,s.name AS gamename
		FROM uo_game AS p 
		LEFT JOIN uo_team As k ON (p.hometeam=k.team_id) 
		LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
		LEFT JOIN uo_scheduling_name s ON(s.scheduling_id=p.name)
		LEFT JOIN uo_game_pool gp ON (gp.game=p.game_id AND gp.timetable=1)
		WHERE p.game_id IN (%s) AND gp.pool=%d",
        implode(",", $gameIds),
        (int) $pool,
    );
    $result = DBQuery($query);

    return $result;
}

function GameResult($gameId)
{
    $gameId = (int) $gameId;
    $query = sprintf(
        "SELECT time, k.name As hometeamname, v.name As visitorteamname,
        k.valid as homevalid, v.valid as visitorvalid,
        p.*, gp.pool AS pool, spirit.spiritmode AS spiritmode, spirit.homesotg AS homesotg, spirit.visitorsotg AS visitorsotg, s.name AS gamename
    FROM uo_game AS p
    LEFT JOIN uo_game_pool gp ON (gp.game=p.game_id AND gp.timetable=1)
    -- Grouped by game alone so this yields exactly one row: grouping by mode as
    -- well splits a game whose two teams were scored under different spirit
    -- modes into two rows, which multiplies the game row and leaves one team's
    -- total unread.
    LEFT JOIN (SELECT ssc.game_id,
                      MAX(CASE WHEN ssc.team_id = g.hometeam THEN sct.mode END) AS spiritmode,
                      SUM(CASE WHEN ssc.team_id = g.hometeam THEN ssc.value * sct.factor END) AS homesotg,
                      SUM(CASE WHEN ssc.team_id = g.visitorteam THEN ssc.value * sct.factor END) AS visitorsotg
               FROM uo_spirit_score ssc
               INNER JOIN uo_game g ON (g.game_id = ssc.game_id)
               LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
               WHERE ssc.game_id=%d
               GROUP BY ssc.game_id) AS spirit
       ON (p.game_id = spirit.game_id)
    LEFT JOIN uo_team As k ON (p.hometeam=k.team_id)
    LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
    LEFT JOIN uo_scheduling_name s ON(s.scheduling_id=p.name)
    WHERE p.game_id=%d",
        $gameId,
        $gameId,
    );

    return DBQueryToRow($query);
}

function GoalInfo($gameId, $num)
{
    $query = sprintf(
        "SELECT m.*, s.profile_id AS assist_accrid, 
		s.firstname AS assistfirstname, s.lastname AS assistlastname,
		t.profile_id AS scorer_accrid,
		t.firstname AS scorerfirstname, t.lastname AS scorerlastname 
		FROM (uo_goal AS m LEFT JOIN uo_player AS s ON (m.assist = s.player_id)) 
		LEFT JOIN uo_player AS t ON (m.scorer=t.player_id)
		WHERE m.game=%d AND m.num=%d",
        (int) $gameId,
        (int) $num,
    );

    $result = DBQuery($query);

    if ($row = mysqli_fetch_assoc($result)) {
        return $row;
    } else {
        return false;
    }
}

function GameHomeTeamResults($teamId, $poolId)
{
    $query = sprintf(
        "SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.visitorteam, COALESCE(pm.goals,0) AS scoresheet,
			sn.name AS gamename, g.isongoing, g.hasstarted, g.forfeit
			FROM uo_game g
			INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (g.game_id=pm.game)
			LEFT JOIN uo_scheduling_name sn ON(g.name=sn.scheduling_id)
			WHERE g.hometeam=%d AND gp.pool=%d
			GROUP BY g.game_id",
        (int) $teamId,
        (int) $poolId,
    );
    return DBQueryToArray($query);
}

function GameHomePseudoTeamResults($schedulingId, $poolId)
{
    $query = sprintf(
        "SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.visitorteam,
			sn.name AS gamename, g.isongoing, g.hasstarted
			FROM uo_game g
			INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
			LEFT JOIN uo_scheduling_name sn ON(g.name=sn.scheduling_id)
			WHERE g.scheduling_name_home=%d AND gp.pool=%d
			GROUP BY g.game_id",
        (int) $schedulingId,
        (int) $poolId,
    );
    return DBQueryToArray($query);
}

function GameVisitorTeamResults($teamId, $poolId)
{
    $query = sprintf(
        "SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.hometeam, COALESCE(pm.goals,0) AS scoresheet,
			g.isongoing, g.forfeit
			FROM uo_game g
			INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (g.game_id=pm.game)
			WHERE g.visitorteam=%d AND gp.pool=%d AND g.hasstarted>0 AND g.valid=1 AND g.isongoing=0
			GROUP BY g.game_id",
        (int) $teamId,
        (int) $poolId,
    );
    return DBQueryToArray($query);
}

function GameNameFromId($gameId)
{
    $query = sprintf(
        "SELECT k.name As hometeamname, v.name As visitorteamname 
		FROM (uo_game AS p LEFT JOIN uo_team As k ON (p.hometeam=k.team_id)) LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
		WHERE game_id=%d",
        (int) $gameId,
    );
    $result = DBQuery($query);
    if (!$result) {
        return "";
    }

    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        return "";
    }
    $homeName = isset($row['hometeamname']) ? $row['hometeamname'] : "";
    $visitorName = isset($row['visitorteamname']) ? $row['visitorteamname'] : "";
    return $homeName . " - " . $visitorName;
}

function GameSeries($gameId)
{
    $query = sprintf(
        "SELECT s.series
		FROM uo_game p
		INNER JOIN uo_game_pool gp ON (gp.game=p.game_id AND gp.timetable=1)
		LEFT JOIN uo_pool s ON (s.pool_id=gp.pool)
		WHERE p.game_id='%s'",
        DBEscapeString($gameId),
    );
    $result = DBQueryToValue($query);

    return $result;
}

function GameRespTeam($gameId)
{
    $query = sprintf(
        "SELECT hometeam, visitorteam 
		FROM uo_game  
		WHERE game_id='%s'",
        (int) $gameId,
    );
    $result = DBQuery($query);
    if (!$result) {
        return -1;
    }

    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        return -1;
    }
    if (isset($_SESSION['userproperties']['userrole']['teamadmin'][$row['hometeam']])) {
        return $row['hometeam'];
    }
    if (isset($_SESSION['userproperties']['userrole']['teamadmin'][$row['visitorteam']])) {
        return $row['visitorteam'];
    }
    return -1;
}

/**
 * Returns game admins (scorekeepers) for given game.
 *
 * @param int $gameId uo_game.game_id
 * @return array array of users
 */
function GameAdmins($gameId)
{
    $query = sprintf(
        "SELECT u.userid, u.name FROM uo_users u
  			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
  			WHERE SUBSTRING_INDEX(up.value, ':', -1)='%d'
			ORDER BY u.name",
        (int) $gameId,
    );
    return DBQueryToArray($query);
}

function GamePool($gameId)
{
    $query = sprintf(
        "SELECT pool
		FROM uo_game_pool
		WHERE game=%d AND timetable=1",
        (int) $gameId,
    );
    $result = DBQueryToValue($query);

    return $result;
}

/**
 * Which team took the first offence, or null when it was never recorded.
 *
 * Callers must separate the null from the 0 before comparing, because a loose
 * `== 0` also matches null and so reports the visiting team for every game
 * that carries no offence event at all.
 *
 * @param int $gameId uo_game.game_id
 * @return int|null 1 for the home team, 0 for the visitors, null when unrecorded
 */
function GameIsFirstOffenceHome($gameId)
{
    $query = sprintf(
        "SELECT ishome
		FROM uo_gameevent
		WHERE game=%d AND type='offence' ORDER BY time",
        (int) $gameId,
    );
    $result = DBQueryToValue($query);

    return $result === null ? null : (int) $result;
}

function GameReservation($gameId)
{
    $query = sprintf(
        "SELECT reservation 
		FROM uo_game  
		WHERE game_id=%d",
        (int) $gameId,
    );
    $result = DBQueryToValue($query);

    return $result;
}

function GameSeason($gameId)
{
    $query = sprintf(
        "SELECT ser.season
		FROM uo_game p
		INNER JOIN uo_game_pool gp ON (gp.game=p.game_id AND gp.timetable=1)
		LEFT JOIN uo_pool s ON (s.pool_id=gp.pool)
		LEFT JOIN uo_series ser ON (s.series=ser.series_id)
		WHERE p.game_id=%d",
        (int) $gameId,
    );
    $result = DBQueryToValue($query);

    return $result;
}

function GamePlayers($gameId, $teamId)
{
    $query = sprintf(
        "SELECT p.player_id, pg.num, p.firstname, p.lastname, pg.captain, pg.spirit_captain
		FROM uo_played AS pg 
		LEFT JOIN uo_player AS p ON(pg.player=p.player_id)
		WHERE pg.game=%d AND p.team=%d
		ORDER BY pg.num ASC, p.lastname ASC, p.firstname ASC",
        (int) $gameId,
        (int) $teamId,
    );

    return DBQueryToArray($query);
}

function GameRolePlayers($gameId, $teamId, $roleColumn)
{
    if ($roleColumn !== 'captain' && $roleColumn !== 'spirit_captain') {
        return [];
    }

    $query = sprintf(
        "SELECT pg.player
		FROM uo_played AS pg 
		LEFT JOIN uo_player AS p ON(pg.player=p.player_id)
		WHERE pg.%s=1 AND pg.game=%d AND p.team=%d",
        $roleColumn,
        (int) $gameId,
        (int) $teamId,
    );

    $rows = DBQueryToArray($query);
    $playerIds = [];
    foreach ($rows as $row) {
        $playerIds[] = (int) $row['player'];
    }

    return $playerIds;
}

function GameCaptains($gameId, $teamId)
{
    return GameRolePlayers($gameId, $teamId, 'captain');
}

function GameSpiritCaptains($gameId, $teamId)
{
    return GameRolePlayers($gameId, $teamId, 'spirit_captain');
}

function GameCaptain($gameId, $teamId)
{
    $captains = GameCaptains($gameId, $teamId);
    if (count($captains) > 0) {
        return $captains[0];
    }

    return null;
}

function GameFilterRolePlayers($gameId, $teamId, $playerIds)
{
    $allowedPlayers = [];
    foreach (GamePlayers($gameId, $teamId) as $player) {
        $allowedPlayers[(int) $player['player_id']] = true;
    }

    $filteredPlayerIds = [];
    foreach ((array) $playerIds as $playerId) {
        $playerId = (int) $playerId;
        if ($playerId > 0 && !empty($allowedPlayers[$playerId])) {
            $filteredPlayerIds[$playerId] = $playerId;
        }
    }

    return array_values($filteredPlayerIds);
}

function GameSetRolePlayers($gameId, $teamId, $roleColumn, $playerIds)
{
    if ($roleColumn !== 'captain' && $roleColumn !== 'spirit_captain') {
        return false;
    }

    if (hasEditGameEventsRight($gameId)) {
        $playerIds = GameFilterRolePlayers($gameId, $teamId, $playerIds);

        $query = sprintf(
            "UPDATE uo_played AS pg
			LEFT JOIN uo_player AS p ON (pg.player=p.player_id)
			SET pg.%s=0
			WHERE pg.game=%d AND p.team=%d",
            $roleColumn,
            (int) $gameId,
            (int) $teamId,
        );
        DBQuery($query);

        if (count($playerIds) === 0) {
            return true;
        }

        $query = sprintf(
            "UPDATE uo_played
			SET %s=1
			WHERE game=%d AND player IN (%s)",
            $roleColumn,
            (int) $gameId,
            implode(',', $playerIds),
        );

        return DBQuery($query);
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameSetCaptains($gameId, $teamId, $playerIds)
{
    return GameSetRolePlayers($gameId, $teamId, 'captain', $playerIds);
}

function GameSetSpiritCaptains($gameId, $teamId, $playerIds)
{
    return GameSetRolePlayers($gameId, $teamId, 'spirit_captain', $playerIds);
}

function GameAll($limit = 50, $onlyPublicExternal = false)
{
    $limit = intval($limit);
    $publicExternalFilter = $onlyPublicExternal ? " AND s.api_public=1" : "";
    //common game query
    $query = "SELECT pp.game_id, pp.time, pp.hometeam, pp.visitorteam, pp.homescore,
			pp.visitorscore, gp.pool AS pool, pool.name AS poolname, pool.timeslot,
			ps.series_id, ps.name AS seriesname, ps.season, s.name AS seasonname, ps.type, pr.fieldname, pr.reservationgroup,
			pr.id AS reservation_id, pr.starttime, pr.endtime, pl.id AS place_id,
			pl.name AS placename, pl.address, pp.isongoing, pp.hasstarted, home.name AS hometeamname, visitor.name AS visitorteamname,
			phome.name AS phometeamname, pvisitor.name AS pvisitorteamname, pool.color, pgame.name AS gamename,
			home.abbreviation AS homeshortname, visitor.abbreviation AS visitorshortname, homec.country_id AS homecountryid,
			homec.name AS homecountry, visitorc.country_id AS visitorcountryid, visitorc.name AS visitorcountry, s.timezone
			FROM uo_game pp
			LEFT JOIN uo_game_pool gp ON (gp.game=pp.game_id AND gp.timetable=1)
			LEFT JOIN uo_pool pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_season s ON (s.season_id=ps.season)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)
			LEFT JOIN uo_team AS home ON (pp.hometeam=home.team_id)
			LEFT JOIN uo_team AS visitor ON (pp.visitorteam=visitor.team_id)
			LEFT JOIN uo_country AS homec ON (homec.country_id=home.country)
			LEFT JOIN uo_country AS visitorc ON (visitorc.country_id=visitor.country)
			LEFT JOIN uo_scheduling_name AS pgame ON (pp.name=pgame.scheduling_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
			WHERE pp.valid=true AND pp.hasstarted>0 AND pp.isongoing=0 $publicExternalFilter ORDER BY pp.time DESC, ps.ordering, pool.ordering, pp.game_id
			LIMIT $limit";
    return DBQuery($query);
}

function GameAllArray($limit = 50, $onlyPublicExternal = false)
{
    return DBFetchAllAssoc(GameAll($limit, $onlyPublicExternal));
}

/**
 * Return the played player's id for a game roster number.
 *
 * Returns null when the number is not on the team's roster for the game.
 */
function GamePlayerFromNumber($gameId, $teamId, $number)
{
    $query = sprintf(
        "SELECT p.player_id
		FROM uo_player AS p 
		INNER JOIN (SELECT player, num FROM uo_played WHERE game='%s')
			AS pel ON (p.player_id=pel.player) 
		WHERE p.team='%s' AND pel.num='%s'",
        DBEscapeString($gameId),
        DBEscapeString($teamId),
        DBEscapeString($number),
    );

    $result = DBQueryToValue($query);
    return $result;
}


function GameTeamScoreBorad($gameId, $teamId)
{
    $query = sprintf(
        "SELECT p.player_id, p.firstname, p.lastname, p.profile_id, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin, 
		COALESCE(c.callahan,0) AS callahan,
		(COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, pel.num AS num FROM uo_player AS p
		LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done 
			FROM uo_goal AS m WHERE m.game='%s' AND m.scorer IS NOT NULL GROUP BY scorer) AS t ON (p.player_id=t.scorer) 
		LEFT JOIN (SELECT m1.scorer AS scorer, COUNT(*) AS callahan
			FROM uo_goal AS m1 WHERE m1.game='%s' AND m1.scorer IS NOT NULL AND m1.iscallahan=1
			GROUP BY scorer) AS c ON (p.player_id=c.scorer)
		LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin FROM uo_goal AS m2 
			WHERE m2.game='%s' AND m2.assist IS NOT NULL GROUP BY assist) AS s ON (p.player_id=s.assist) 
		RIGHT JOIN (SELECT player, num FROM uo_played WHERE game='%s') as pel ON (p.player_id=pel.player) 
			WHERE p.team='%s' 
		ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, firstname ASC",
        DBEscapeString($gameId),
        DBEscapeString($gameId),
        DBEscapeString($gameId),
        DBEscapeString($gameId),
        DBEscapeString($teamId),
    );

    $result = DBQuery($query);

    return $result;
}

function GameTeamScoreBoardArray($gameId, $teamId)
{
    return DBFetchAllAssoc(GameTeamScoreBorad($gameId, $teamId));
}

function GameTeamDefenseBoard($gameId, $teamId)
{
    $query = sprintf(
        "SELECT p.player_id, p.firstname, p.lastname, p.profile_id, COALESCE(t.done,0) AS done, pel.num AS num FROM uo_player AS p 
		LEFT JOIN (SELECT m.author AS author, COUNT(*) AS done 
			FROM uo_defense AS m WHERE m.game='%s' AND m.author IS NOT NULL GROUP BY author) AS t ON (p.player_id=t.author) 
		RIGHT JOIN (SELECT player, num FROM uo_played WHERE game='%s') as pel ON (p.player_id=pel.player) 
			WHERE p.team='%s' 
		ORDER BY done DESC, lastname ASC, firstname ASC",
        DBEscapeString($gameId),
        DBEscapeString($gameId),
        DBEscapeString($teamId),
    );

    $result = DBQuery($query);
    return $result;
}

function GameTeamDefenseBoardArray($gameId, $teamId)
{
    return DBFetchAllAssoc(GameTeamDefenseBoard($gameId, $teamId));
}

function GameScoreBoard($gameId)
{
    $query = sprintf(
        "SELECT p.profile_id, p.player_id, p.firstname, p.lastname, pj.name AS teamname, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin,
			(COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total
		FROM uo_player AS p LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done 
		FROM uo_goal AS m WHERE m.game='%s' AND m.scorer IS NOT NULL
			GROUP BY scorer) AS t ON (p.player_id=t.scorer) 
		LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin
		FROM uo_goal AS m2 WHERE m2.game='%s' AND m2.assist IS NOT NULL
			GROUP BY assist) AS s ON (p.player_id=s.assist) 
		RIGHT JOIN (SELECT player, num FROM uo_played
			WHERE game='%s') as pel ON (p.player_id=pel.player)
		LEFT JOIN uo_team pj ON (pj.team_id=p.team) WHERE p.profile_id IS NOT NULL AND p.lastname IS NOT NULL 
		ORDER BY p.profile_id ",
        DBEscapeString($gameId),
        DBEscapeString($gameId),
        DBEscapeString($gameId),
    );

    $result = DBQuery($query);
    return $result;
}

function GameScoreBoardArray($gameId)
{
    return DBFetchAllAssoc(GameScoreBoard($gameId));
}

function GameGoals($gameId)
{
    $query = sprintf(
        "
		SELECT m.*, s.num AS assistnum, s.firstname AS assistfirstname, s.lastname AS assistlastname, t.num AS scorernum, t.firstname AS scorerfirstname, t.lastname AS scorerlastname 
		FROM (uo_goal AS m LEFT JOIN uo_player AS s ON (m.assist = s.player_id)) 
		LEFT JOIN uo_player AS t ON (m.scorer=t.player_id) 
		WHERE m.game='%s' 
		ORDER BY m.num",
        DBEscapeString($gameId),
    );

    return DBQueryToArray($query);
}

function GameDefenses($gameId)
{
    $query = sprintf(
        "SELECT m.*, s.firstname AS defenderfirstname, s.lastname AS defenderlastname 
		FROM (uo_defense AS m LEFT JOIN uo_player AS s ON (m.author = s.player_id))
		WHERE m.game='%s' 
		ORDER BY m.num",
        DBEscapeString($gameId),
    );

    return DBQueryToArray($query);
}


function GameLastGoal($gameId)
{
    $query = sprintf(
        "SELECT m.*, s.firstname AS assistfirstname, s.lastname AS assistlastname, t.firstname AS scorerfirstname, t.lastname AS scorerlastname 
		FROM (uo_goal AS m LEFT JOIN uo_player AS s ON (m.assist = s.player_id)) 
		LEFT JOIN uo_player AS t ON (m.scorer=t.player_id) 
		WHERE m.game='%s' 
		ORDER BY m.num DESC",
        DBEscapeString($gameId),
    );

    return DBQueryToRow($query);
}

function GoalPlayerDisplayText($playerId, $gameId, $firstname = '', $lastname = '')
{
    $playerId = (int) $playerId;
    if ($playerId <= 0) {
        return '';
    }

    $name = trim($firstname . ' ' . $lastname);
    if ($name === '') {
        $name = trim(PlayerName($playerId));
    }

    $number = PlayerNumber($playerId, $gameId);
    $prefix = $number >= 0 ? "#" . $number . " " : '';

    return trim($prefix . $name);
}

function GoalDisplayText($goal, $gameId, $withNumbers = false)
{
    if (!empty($goal['iscallahan'])) {
        return _("Callahan goal");
    }

    $assistText = '';
    $scorerText = '';
    if ($withNumbers) {
        $assistText = GoalPlayerDisplayText($goal['assist'], $gameId);
        $scorerText = GoalPlayerDisplayText($goal['scorer'], $gameId);
    } else {
        $assistText = trim(($goal['assistfirstname'] ?? '') . ' ' . ($goal['assistlastname'] ?? ''));
        $scorerText = trim(($goal['scorerfirstname'] ?? '') . ' ' . ($goal['scorerlastname'] ?? ''));
    }

    if ($assistText !== '' && $scorerText !== '') {
        return $assistText . " --> " . $scorerText;
    }
    if ($scorerText !== '') {
        return $scorerText;
    }
    if ($assistText !== '') {
        return $assistText;
    }

    return '';
}

function GameAllGoals($gameId)
{
    // Order by the point number, not the clock. A point time is optional and a
    // recorded one is sometimes out of sequence, so ordering by time puts goals
    // in the wrong order and the offence and break counts follow it.
    $query = sprintf(
        "SELECT num,time,ishomegoal 
		FROM uo_goal 
		WHERE game='%s' 
		ORDER BY num",
        DBEscapeString($gameId),
    );

    return DBQueryToArray($query);
}

function GameEvents($gameId)
{
    $query = sprintf(
        "SELECT time,ishome,type,info
		FROM (
			SELECT time,ishome,'timeout' AS type,NULL AS info FROM `uo_timeout`
				WHERE game='%s'
			UNION ALL
			SELECT time,ishome,'spirit_timeout' AS type,NULL AS info FROM `uo_spirit_timeout`
				WHERE game='%s'
			UNION ALL
			SELECT time,ishome,type,info FROM uo_gameevent WHERE game='%s'
		) AS tapahtuma 
		WHERE type!='media'
		ORDER BY time ",
        DBEscapeString($gameId),
        DBEscapeString($gameId),
        DBEscapeString($gameId),
    );

    return DBQueryToArray($query);
}

function GameCapEventTypes()
{
    return ['half_cap', 'time_cap'];
}

function GameIsCapEventType($type)
{
    return in_array($type, GameCapEventTypes(), true);
}

function GameCapEvent($gameId, $type)
{
    if (!GameIsCapEventType($type)) {
        return null;
    }

    $query = sprintf(
        "SELECT time,type,info FROM uo_gameevent
		WHERE game=%d AND type='%s'
		LIMIT 1",
        (int) $gameId,
        DBEscapeString($type),
    );

    return DBQueryToRow($query);
}

/**
 * Every cap event of a game, keyed by cap type. One query instead of one per
 * cap type, because the scoresheet renders both on every page load.
 */
function GameCapEvents($gameId)
{
    $query = sprintf(
        "SELECT time,type,info FROM uo_gameevent
		WHERE game=%d AND type IN ('%s')",
        (int) $gameId,
        implode("','", array_map('DBEscapeString', GameCapEventTypes())),
    );

    $events = [];
    foreach (DBQueryToArray($query) as $event) {
        $events[$event['type']] = $event;
    }

    return $events;
}

function GameCapEventName($type)
{
    if ($type === 'half_cap') {
        return _("Halftime cap");
    }
    if ($type === 'time_cap') {
        return _("Time cap");
    }

    return '';
}

/**
 * Cap event as replay text: which cap was called, when, and the point cap it
 * set -- "Time cap 6.45 - new point cap 4". "Point cap" is the project term for
 * a score cap, see docs/terminology.md.
 *
 * $showTime decides who prints the time, and depends on where the caller puts
 * it. Renderers that lead with the time (the scorekeeper pages, mobile, the
 * [mm.ss] prefix in ext/rss.php) already read correctly and pass false, keeping
 * their own stamp: "6.45 Time cap - new point cap 4". Renderers that would
 * append it after the label instead pass !$hideTimeOnScoresheet and print no
 * time of their own, because a trailing stamp would leave two unlabelled
 * numbers side by side: "Time cap - new point cap 4 6.45".
 *
 * @param array $event Game event row
 * @param bool $showTime Whether this text should carry the event time
 * @return string
 */
function GameCapEventText($event, $showTime = true)
{
    $name = GameCapEventName($event['type'] ?? '');
    if ($name === '') {
        return '';
    }

    if ($showTime) {
        $name .= " " . SecToMin((int) ($event['time'] ?? 0));
    }

    return sprintf(_("%s - new point cap %d"), $name, (int) ($event['info'] ?? 0));
}

function GameSetCapEvent($gameId, $type, $time, $target)
{
    $gameId = (int) $gameId;
    $time = max(0, (int) $time);
    $target = (int) $target;

    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game events');
    }
    if (!GameIsCapEventType($type) || $target < 1 || $target > 255) {
        return false;
    }

    $eventNum = DBQueryToValue(
        sprintf(
            "SELECT num FROM uo_gameevent WHERE game=%d AND type='%s' LIMIT 1",
            $gameId,
            DBEscapeString($type),
        ),
    );

    if ($eventNum !== null && $eventNum !== false) {
        $query = sprintf(
            "UPDATE uo_gameevent
			SET time=%d,info='%d'
			WHERE game=%d AND num=%d",
            $time,
            $target,
            $gameId,
            (int) $eventNum,
        );

        return DBExecute($query);
    }

    $lastNum = (int) DBQueryToValue(
        sprintf("SELECT MAX(num) FROM uo_gameevent WHERE game=%d", $gameId),
    );
    $query = sprintf(
        "INSERT INTO uo_gameevent (game,num,ishome,time,type,info)
		VALUES(%d,%d,0,%d,'%s','%d')",
        $gameId,
        $lastNum + 1,
        $time,
        DBEscapeString($type),
        $target,
    );

    return DBExecute($query);
}

function GameRemoveCapEvent($gameId, $type)
{
    $gameId = (int) $gameId;

    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game events');
    }
    if (!GameIsCapEventType($type)) {
        return false;
    }

    $query = sprintf(
        "DELETE FROM uo_gameevent WHERE game=%d AND type='%s'",
        $gameId,
        DBEscapeString($type),
    );

    return DBExecute($query);
}

function GameMediaEvents($gameId)
{
    $query = sprintf(
        "SELECT u.time, u.ishome, u.type as eventtype, u.info, urls.*
		FROM uo_gameevent u
		LEFT JOIN uo_urls urls ON(u.info=urls.url_id)
		WHERE u.game=%d AND u.type='media'
		ORDER BY time ",
        (int) $gameId,
    );

    return DBQueryToArray($query);
}

function AddGameMediaEvent($gameId, $time, $urlId)
{
    if (hasAddMediaRight()) {
        $lastnum = DBQueryToValue("SELECT MAX(num) FROM uo_gameevent WHERE game=" . intval($gameId));
        $lastnum = intval($lastnum) + 1;

        $query = sprintf(
            "INSERT INTO uo_gameevent (game,num,ishome,time,type,info)
				VALUES(%d,$lastnum,0,%d,'media',%d)",
            (int) $gameId,
            (int) $time,
            (int) $urlId,
        );

        return DBQueryInsert($query);
    } else {
        die('Insufficient rights to add media');
    }
}

function RemoveGameMediaEvent($gameId, $urlId)
{
    if (hasAddMediaRight()) {
        $query = sprintf(
            "DELETE FROM uo_gameevent WHERE game=%d AND type='media' AND info=%d",
            (int) $gameId,
            (int) $urlId,
        );
        return DBQuery($query);
    } else {
        die('Insufficient rights to remove media');
    }
}

function GameTimeouts($gameId)
{
    $query = sprintf(
        "SELECT num,time,ishome 
		FROM uo_timeout 
		WHERE game='%s' 
		ORDER BY time",
        DBEscapeString($gameId),
    );

    return DBQueryToArray($query);
}

function GameSpiritTimeouts($gameId)
{
    $query = sprintf(
        "SELECT num,time,ishome
		FROM uo_spirit_timeout
		WHERE game='%s'
		ORDER BY time",
        DBEscapeString($gameId),
    );

    return DBQuery($query);
}

function GameSpiritTimeoutsArray($gameId)
{
    return DBFetchAllAssoc(GameSpiritTimeouts($gameId));
}

function GameTurnovers($gameId)
{
    $query = sprintf(
        "SELECT time, ishome 
		FROM uo_gameevent 
		WHERE game='%s' AND type='turnover' 
		ORDER BY time",
        DBEscapeString($gameId),
    );

    return DBQuery($query);
}

function GameTurnoversArray($gameId)
{
    return DBFetchAllAssoc(GameTurnovers($gameId));
}

function GameInfo($gameId)
{
    $query = sprintf(
        "SELECT pp.game_id, hometeam, kj.name as hometeamname, kj.abbreviation as hometeamshortname, visitorteam, vj.name as visitorteamname, vj.abbreviation as visitorteamshortname, gp.pool as pool,
			time, homescore, visitorscore, pool.timecap, pool.scorecap, pool.winningscore, pool.drawsallowed, pool.timeslot AS timeslot,
			pp.timeslot AS gametimeslot, pool.series, pool.color, ser.season, ser.name AS seriesname,
			pool.name AS poolname, phome.name AS phometeamname, pvisitor.name AS pvisitorteamname, pp.scheduling_name_home,
			pp.scheduling_name_visitor, isongoing, hasstarted, pl.name AS placename, res.fieldname, sname.name AS gamename,
			kj.valid as homevalid, vj.valid as visitorvalid
		FROM uo_game pp
			LEFT JOIN uo_game_pool gp ON (gp.game=pp.game_id AND gp.timetable=1)
			left join uo_reservation res on (pp.reservation=res.id)
			LEFT JOIN uo_location pl ON (res.location=pl.id)
			left join uo_pool pool on (pool.pool_id=gp.pool)
			left join uo_series ser on (ser.series_id=pool.series)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
			LEFT JOIN uo_scheduling_name AS sname ON (pp.name=sname.scheduling_id)
		WHERE pp.game_id=%d",
        (int) $gameId,
    );
    return DBQueryToRow($query);
}


function GameName($gameInfo)
{
    if ($gameInfo['hometeam'] && $gameInfo['visitorteam']) {
        return ShortDate($gameInfo['time']) . " " . DefHourFormat($gameInfo['time']) . " " . $gameInfo['hometeamname'] . "-" . $gameInfo['visitorteamname'];
    } else {
        return ShortDate($gameInfo['time']) . " " . DefHourFormat($gameInfo['time']) . " " . $gameInfo['phometeamname'] . "-" . $gameInfo['pvisitorteamname'];
    }
}

function GameHasStarted($gameInfo)
{
    return $gameInfo['hasstarted'] > 0;
}

/**
 * Recorded halftime of a game in seconds, or null when none was recorded.
 *
 * "Not recorded" is stored as either NULL or 0 depending on which input path
 * wrote the row, and both mean the same thing: no game breaks for half at
 * 0:00. Callers must not intval() the raw column, because that turns a missing
 * halftime into one at second 0, which then sorts ahead of the first goal.
 *
 * @param array $gameInfo game row as returned by GameResult()
 * @return int|null halftime in seconds, or null when not recorded
 */
function GameHalftimeSeconds($gameInfo)
{
    $halftime = intval($gameInfo['halftime'] ?? 0);

    return $halftime > 0 ? $halftime : null;
}

/**
 * State of a game's clock, for the scorekeeper pages that display it.
 *
 * `ongoing` means the clock is running, which is narrower than the isongoing
 * column it is read from. That column marks the game as in progress and is
 * also set by the result entry paths in GameUpdateResult(), which never start
 * a clock. Reporting those games as running handed the client a clock with no
 * start time, so it counted up from zero and fell back to zero again on the
 * next request. The clock is only meaningful once timer_start is set.
 *
 * `started` stays broader on purpose: a game in progress can have its clock
 * started, so the page offers that.
 *
 * @param int $gameId uo_game.game_id
 * @return array timer state, matching ScorekeeperTimerStateDefaults()
 */
function GameTimerState($gameId)
{
    $gameId = (int) $gameId;
    $state = [
        "started" => false,
        "ongoing" => false,
        "paused" => false,
        "elapsed" => 0,
        "mm" => 0,
        "ss" => 0,
        "rss" => 0,
    ];

    $query = sprintf(
        "SELECT hasstarted, isongoing, timer_start, timer_pause_start, timer_paused_duration FROM uo_game WHERE game_id=%d LIMIT 1",
        $gameId,
    );
    $row = DBQueryToRow($query);
    if (!$row) {
        return $state;
    }

    $state['started'] = ((int) $row['hasstarted'] > 0) || !empty($row['timer_start']);
    $state['ongoing'] = (int) $row['isongoing'] === 1 && !empty($row['timer_start']);
    $state['paused'] = $state['ongoing'] && !empty($row['timer_pause_start']);

    if (empty($row['timer_start'])) {
        return $state;
    }

    $elapsed = time() - (int) $row['timer_start'] - (int) $row['timer_paused_duration'];
    if (!empty($row['timer_pause_start'])) {
        $elapsed -= time() - (int) $row['timer_pause_start'];
    }
    $elapsed = max(0, $elapsed);

    $state['elapsed'] = $elapsed;
    $state['mm'] = (int) floor($elapsed / 60);
    $state['ss'] = $elapsed % 60;
    $state['rss'] = (int) (round($state['ss'] / 5) * 5);

    if ($state['rss'] === 60) {
        $state['mm']++;
        $state['rss'] = 0;
    }

    return $state;
}

/**
 * Timeouts one team is allowed in a game, from the game's pool format.
 *
 * `timeoutsper` says whether `timeouts` counts per game or per half.
 * `timeoutsovertime` adds recordable overtime timeout slots. Falls back to 4
 * regulation slots when the pool does not define a limit, which is what the
 * scorekeeper offered before pool formats were consulted.
 *
 * @param int $gameId uo_game.game_id
 * @return int allowed timeouts per team
 */
function GameTimeoutsPerTeam($gameId)
{
    $default = 4;
    $poolId = GamePool($gameId);
    if (!$poolId) {
        return $default;
    }

    $pool = PoolInfo($poolId);
    if (!$pool) {
        return $default;
    }

    $timeouts = empty($pool['timeouts']) ? $default : (int) $pool['timeouts'];
    if (!empty($pool['timeouts']) && ($pool['timeoutsper'] ?? '') === 'half') {
        $timeouts *= 2;
    }
    $timeouts += max(0, (int) ($pool['timeoutsovertime'] ?? 0));

    return max(1, $timeouts);
}

/**
 * Highest number of timeouts recorded for either team in a game.
 *
 * Saving a timeout form clears every timeout and rewrites only the rendered
 * slots, so a page must never offer fewer slots than this or it silently
 * deletes what it did not show.
 */
function GameRecordedTimeoutCount($timeouts)
{
    $home = 0;
    $away = 0;
    foreach ($timeouts as $timeout) {
        if ((int) $timeout['ishome'] === 1) {
            $home++;
        } else {
            $away++;
        }
    }

    return max($home, $away);
}

/**
 * Highest score a game result may carry.
 *
 * The bound is what CheckGameResult() has always promised in its warning text.
 * Its purpose is to keep a mistyped score out of standings and statistics, so
 * it is deliberately far above any real Ultimate score.
 */
const MAX_GAME_SCORE = 1000;

/**
 * Returns true when a value is a whole number within the allowed score range.
 *
 * Accepts integers and digit strings, since callers pass both.
 */
function IsValidGameScore($score)
{
    if (is_int($score)) {
        return $score >= 0 && $score <= MAX_GAME_SCORE;
    }

    if (is_string($score) && ctype_digit(trim($score))) {
        return (int) trim($score) <= MAX_GAME_SCORE;
    }

    return false;
}

function CheckGameResult($game, $home, $away)
{
    $gameId = (int) substr($game, 0, -1);
    $errors = "";
    if (!IsValidGameScore($home) || !IsValidGameScore($away)) {
        $errors .= "<p class='warning'>" . _("Points must be between 0 and 1000.") . "</p>";
    }
    if ($gameId == 0 || !checkChkNum($game)) {
        $errors .= "<p class='warning'>" . _("Erroneous scoresheet number:") . " " . $game . "</p>";
    } else {
        $pool = GamePool($gameId);
        if (!$pool) {
            $errors .= "<p class='warning'>" . _("Game has no pool.") . "</p>";
        } else {
            if (IsPoolLocked($pool)) {
                $errors .= "<p class='warning'>" . _("Pool is locked.") . "</p>";
            }
        }
    }
    if (IsSeasonStatsCalculated(GameSeason($gameId))) {
        $errors .= "<p class='warning'>" . _("Event played.") . "</p>";
    }
    if (!($home + $away)) {
        $errors .= "<p class='warning'>" . _("No goals.") . "</p>";
    }
    return $errors;
}

function GameUpdateResult($gameId, $home, $away)
{
    // Enforced here rather than per entry point: user/addresult.php and
    // mobile/addresult.php never call CheckGameResult().
    if (!IsValidGameScore($home) || !IsValidGameScore($away)) {
        return false;
    }
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "UPDATE uo_game SET homescore='%s', visitorscore='%s', isongoing='1', hasstarted='1' WHERE game_id='%s'",
            DBEscapeString($home),
            DBEscapeString($away),
            DBEscapeString($gameId),
        );
        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameSetResult($gameId, $home, $away, $updatePools = true, $checkRights = true)
{
    if (!IsValidGameScore($home) || !IsValidGameScore($away)) {
        return false;
    }
    $seasonId = GameSeason($gameId);
    if (!$checkRights && isEventReadonly($seasonId) && !canBypassEventReadonly($seasonId)) {
        die('Insufficient rights to edit game');
    }
    if (!$checkRights || hasEditGameEventsRight($gameId)) {
        LogGameUpdate($gameId, "result: $home - $away");
        $query = sprintf(
            "UPDATE uo_game SET homescore='%s', visitorscore='%s', isongoing='0', hasstarted='2', timer_start=NULL, timer_pause_start=NULL, timer_paused_duration=0 WHERE game_id='%s'",
            DBEscapeString($home),
            DBEscapeString($away),
            DBEscapeString($gameId),
        );
        $result = DBQuery($query);

        if ($updatePools) {
            $poolId = GamePool($gameId);
            ResolvePoolStandings($poolId);
            PoolResolvePlayed($poolId);
        }
        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function SeasonForfeitGames($seasonId)
{
    $query = sprintf(
        "SELECT g.game_id, g.time, g.homescore, g.visitorscore,
		        ht.name AS hometeamname, vt.name AS visitorteamname,
		        po.pool_id, po.name AS poolname,
		        se.series_id, se.name AS seriesname
		 FROM uo_game g
		 INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
		 LEFT JOIN uo_team ht ON g.hometeam = ht.team_id
		 LEFT JOIN uo_team vt ON g.visitorteam = vt.team_id
		 LEFT JOIN uo_pool po ON po.pool_id = gp.pool
		 LEFT JOIN uo_series se ON po.series = se.series_id
		 WHERE se.season = '%s' AND g.forfeit > 0
		 ORDER BY g.time",
        DBEscapeString($seasonId),
    );
    $result = DBQuery($query);
    $games = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $games[] = $row;
    }
    return $games;
}

// Forfeit codes: 0 = not a forfeit, 1 = home team forfeited (away wins),
// 2 = away team forfeited (home wins), 3 = both teams forfeited (both lose).
function GameSetForfeit($gameId, $forfeit)
{
    $seasonId = GameSeason($gameId);
    if (isEventReadonly($seasonId) && !canBypassEventReadonly($seasonId)) {
        die('Insufficient rights to edit game');
    }
    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game');
    }
    $forfeit = max(0, min(3, intval($forfeit)));
    $labels = [0 => "none", 1 => "home", 2 => "away", 3 => "both"];
    LogGameUpdate($gameId, "forfeit: " . $labels[$forfeit]);
    $query = sprintf(
        "UPDATE uo_game SET forfeit='%d' WHERE game_id='%s'",
        $forfeit,
        DBEscapeString($gameId),
    );
    $result = DBQuery($query);
    // Forfeited games carry no spirit; recompute visibility and cached team
    // statistics so their data is dropped from averages (and restored on undo).
    if (function_exists('RefreshGameSpiritData')) {
        RefreshGameSpiritData($gameId);
    }
    // The forfeit direction changes the win/loss a game contributes, so
    // recompute the cached pool standings the same way GameSetResult() does.
    $poolId = GamePool($gameId);
    ResolvePoolStandings($poolId);
    PoolResolvePlayed($poolId);
    return $result;
}

function GameClearResult($gameId, $updatepools = true)
{
    if (hasEditGameEventsRight($gameId)) {
        LogGameUpdate($gameId, "result cleared");
        $query = sprintf(
            "UPDATE uo_game SET homescore=NULL, visitorscore=NULL, isongoing='0', hasstarted='0', timer_start=NULL, timer_pause_start=NULL, timer_paused_duration=0 WHERE game_id='%s'",
            DBEscapeString($gameId),
        );
        $result = DBQuery($query);

        if ($updatepools) {
            $poolId = GamePool($gameId);
            ResolvePoolStandings($poolId);
            PoolResolvePlayed($poolId);
        }
        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameSetDefenses($gameId, $home, $away)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "UPDATE uo_game SET homedefenses='%s', visitordefenses='%s' WHERE game_id='%s'",
            DBEscapeString($home),
            DBEscapeString($away),
            DBEscapeString($gameId),
        );
        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

/**
 * Returns true when a player may be put on a game roster.
 *
 * When the event requires accreditation, only accredited players may be added.
 * A player already on the roster stays allowed so that renumbering and
 * re-saving an existing roster keeps working.
 */
function GameAllowsPlayerOnRoster($gameId, $playerId)
{
    $seasonInfo = SeasonInfo(GameSeason($gameId));
    if (empty($seasonInfo['require_accreditation'])) {
        return true;
    }

    if (isAccredited($playerId)) {
        return true;
    }

    $query = sprintf(
        "SELECT COUNT(*) FROM uo_played WHERE game='%s' AND player='%s'",
        DBEscapeString($gameId),
        DBEscapeString($playerId),
    );

    return (int) DBQueryToValue($query) > 0;
}

function GameAddPlayer($gameId, $playerId, $number)
{
    if (hasEditGamePlayersRight($gameId)) {
        // Enforced here so every roster path inherits it: the modern handlers
        // check accreditation themselves, but mobile/addplayerlists.php reached
        // this mutation directly and bypassed the event rule.
        if (!GameAllowsPlayerOnRoster($gameId, $playerId)) {
            return false;
        }

        $query = sprintf(
            "INSERT INTO uo_played
			(game, player, num, accredited) 
			VALUES ('%s', '%s', '%s', %d)
			ON DUPLICATE KEY UPDATE num=%d",
            DBEscapeString($gameId),
            DBEscapeString($playerId),
            DBEscapeString($number),
            (int) isAccredited($playerId),
            DBEscapeString($number),
        );

        $result = DBQuery($query);
        $query = sprintf(
            "UPDATE uo_player SET num=%d WHERE player_id=%d",
            (int) $number,
            (int) $playerId,
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameAddNewPlayer($gameId, $firstname, $lastname, $accrid, $teamId, $number)
{
    if (hasEditGamePlayersRight($gameId)) {
        $query = sprintf(
            "INSERT INTO uo_player (firstname, lastname, accreditation_id, team) VALUES ('%s', '%s', '%s', %d)",
            DBEscapeString($firstname),
            DBEscapeString($lastname),
            DBEscapeString($accrid),
            (int) $teamId,
        );
        $playerId = DBQueryInsert($query);

        GameAddPlayer($gameId, $playerId, $number);
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameRemovePlayer($gameId, $playerId)
{
    if (hasEditGamePlayersRight($gameId)) {
        $query = sprintf(
            "DELETE FROM uo_played 
			WHERE game='%s' AND player='%s'",
            DBEscapeString($gameId),
            DBEscapeString($playerId),
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameRemoveAllPlayers($gameId)
{
    if (hasEditGamePlayersRight($gameId)) {
        $query = sprintf(
            "DELETE FROM uo_played
			WHERE game='%s'",
            DBEscapeString($gameId),
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameSetPlayerNumber($gameId, $playerId, $number)
{
    if (hasEditGamePlayersRight($gameId)) {
        $query = sprintf(
            "UPDATE uo_played 
			SET num='%s', accredited=%d 
			WHERE game=%d AND player=%d",
            DBEscapeString($number),
            (int) isAccredited($playerId),
            (int) $gameId,
            (int) $playerId,
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameRemoveAllScores($gameId)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "DELETE FROM uo_goal 
			WHERE game='%s'",
            DBEscapeString($gameId),
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameRemoveAllDefenses($gameId)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "DELETE FROM uo_defense 
			WHERE game='%s'",
            DBEscapeString($gameId),
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}


function GameRemoveScore($gameId, $num)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "DELETE FROM uo_goal
			WHERE game='%s' AND num=%d",
            DBEscapeString($gameId),
            (int) $num,
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

/**
 * Lowers the game's aggregate result back onto its remaining goals.
 *
 * Removing a goal only deletes the uo_goal row, so the score on uo_game keeps
 * counting it. Nothing brings that total back down on its own: the scoresheet
 * raises the aggregate only when a new goal beats the stored total, so a goal
 * corrected in favour of the other team leaves the published result wrong
 * until the game is finalized. Callers that delete a goal must call this.
 *
 * The result is only lowered when it was actually tracking the goals, which is
 * true exactly when it still shows the score the removed goal carried. A game
 * whose scoresheet was never completed can be finalized at its real score
 * through result.php while only a handful of goals were typed in, and deleting
 * one of those goals must not drop the published result to the scoresheet's
 * partial total.
 *
 * Only the score is touched. Whether the game is running or finished is not
 * this function's business, so isongoing, hasstarted and the timer columns are
 * left as they are, unlike GameSetResult() and GameClearResult(). The cached
 * pool standings are refreshed the way those two do, because a goal can be
 * deleted from a game that was already finalized.
 *
 * @param int $gameId uo_game.game_id
 * @param int $removedHome home score the removed goal carried
 * @param int $removedAway visitor score the removed goal carried
 * @return bool result of the update, or false when the result was left alone
 */
function GameSyncResultFromGoals($gameId, $removedHome, $removedAway)
{
    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game');
    }

    $stored = DBQueryToRow(sprintf(
        "SELECT homescore, visitorscore FROM uo_game WHERE game_id=%d LIMIT 1",
        (int) $gameId,
    ));
    if (!$stored
        || (int) $stored['homescore'] !== (int) $removedHome
        || (int) $stored['visitorscore'] !== (int) $removedAway
    ) {
        return false;
    }

    $query = sprintf(
        "SELECT homescore, visitorscore
		FROM uo_goal
		WHERE game=%d
		ORDER BY num DESC
		LIMIT 1",
        (int) $gameId,
    );
    $lastgoal = DBQueryToRow($query);

    $home = $lastgoal ? (int) $lastgoal['homescore'] : 0;
    $away = $lastgoal ? (int) $lastgoal['visitorscore'] : 0;

    LogGameUpdate($gameId, "result from goals: $home - $away");
    $result = DBQuery(sprintf(
        "UPDATE uo_game SET homescore='%s', visitorscore='%s' WHERE game_id=%d",
        DBEscapeString($home),
        DBEscapeString($away),
        (int) $gameId,
    ));

    $poolId = GamePool($gameId);
    ResolvePoolStandings($poolId);
    PoolResolvePlayed($poolId);

    return $result;
}

/**
 * Add goal to game. Does not update game result!
 *
 */
function GameAddScore($gameId, $pass, $goal, $time, $number, $hscores, $ascores, $home, $iscallahan)
{
    if (hasEditGameEventsRight($gameId)) {
        $assistValue = ($pass === -1 || $pass === "" || $pass === null) ? "NULL" : "'" . DBEscapeString($pass) . "'";
        $scorerValue = ($goal === -1 || $goal === "" || $goal === null) ? "NULL" : "'" . DBEscapeString($goal) . "'";
        $query = sprintf(
            "INSERT INTO uo_goal 
				(game, num, assist, scorer, time, homescore, visitorscore, ishomegoal, iscallahan) 
				VALUES ('%s', '%s', %s, %s, '%s', '%s', '%s', '%s', '%s')
				ON DUPLICATE KEY UPDATE 
				assist=%s, scorer=%s, time='%s', homescore='%s', visitorscore='%s', ishomegoal='%s', iscallahan='%s'",
            DBEscapeString($gameId),
            DBEscapeString($number),
            $assistValue,
            $scorerValue,
            DBEscapeString($time),
            DBEscapeString($hscores),
            DBEscapeString($ascores),
            DBEscapeString($home),
            DBEscapeString($iscallahan),
            $assistValue,
            $scorerValue,
            DBEscapeString($time),
            DBEscapeString($hscores),
            DBEscapeString($ascores),
            DBEscapeString($home),
            DBEscapeString($iscallahan),
        );

        $result = DBQuery($query);
        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameAddDefense($gameId, $player, $home, $caught, $time, $iscallahan, $number)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "INSERT INTO uo_defense 
			(game, num, author, time, iscallahan, iscaught, ishomedefense) 
			VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s') 
			ON DUPLICATE KEY UPDATE 
			author='%s', time='%s', iscallahan='%s', iscaught='%s', ishomedefense='%s'",
            DBEscapeString($gameId),
            DBEscapeString($number),
            DBEscapeString($player),
            DBEscapeString($time),
            DBEscapeString($iscallahan),
            DBEscapeString($caught),
            DBEscapeString($home),
            DBEscapeString($player),
            DBEscapeString($time),
            DBEscapeString($iscallahan),
            DBEscapeString($caught),
            DBEscapeString($home),
        );

        $result = DBQuery($query);
        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameAddScoreEntry($uo_goal)
{
    if (hasEditGameEventsRight($uo_goal['game'])) {
        $assist = $uo_goal['assist'];
        $scorer = $uo_goal['scorer'];
        $assistValue = ($assist === -1 || $assist === 0 || $assist === "0" || $assist === "" || $assist === null || strcasecmp((string) $assist, "x") == 0 || strcasecmp((string) $assist, "xx") == 0) ? "NULL" : "'" . DBEscapeString($assist) . "'";
        $scorerValue = ($scorer === -1 || $scorer === 0 || $scorer === "0" || $scorer === "" || $scorer === null || strcasecmp((string) $scorer, "x") == 0 || strcasecmp((string) $scorer, "xx") == 0) ? "NULL" : "'" . DBEscapeString($scorer) . "'";

        $query = sprintf(
            "INSERT INTO uo_goal 
			(game, num, assist, scorer, time, homescore, visitorscore, ishomegoal, iscallahan) 
			VALUES ('%s', '%s', %s, %s, '%s', '%s', '%s', '%s', '%s')",
            DBEscapeString($uo_goal['game']),
            DBEscapeString($uo_goal['num']),
            $assistValue,
            $scorerValue,
            DBEscapeString($uo_goal['time']),
            DBEscapeString($uo_goal['homescore']),
            DBEscapeString($uo_goal['visitorscore']),
            DBEscapeString($uo_goal['ishomegoal']),
            DBEscapeString($uo_goal['iscallahan']),
        );

        $result = DBQuery($query);
        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameRemoveAllTimeouts($gameId)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "DELETE FROM uo_timeout 
			WHERE game='%s'",
            DBEscapeString($gameId),
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameAddTimeout($gameId, $number, $time, $home)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "INSERT INTO uo_timeout 
			(game, num, time, ishome) 
			VALUES ('%s', '%s', '%s', '%s')",
            DBEscapeString($gameId),
            DBEscapeString($number),
            DBEscapeString($time),
            DBEscapeString($home),
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameRemoveAllSpiritTimeouts($gameId)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "DELETE FROM uo_spirit_timeout
			WHERE game='%s'",
            DBEscapeString($gameId),
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameAddSpiritTimeout($gameId, $number, $time, $home)
{
    if (hasEditGameEventsRight($gameId)) {
        $query = sprintf(
            "INSERT INTO uo_spirit_timeout
			(game, num, time, ishome)
			VALUES ('%s', '%s', '%s', '%s')",
            DBEscapeString($gameId),
            DBEscapeString($number),
            DBEscapeString($time),
            DBEscapeString($home),
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameSetScoreSheetKeeper($gameId, $name)
{
    if (hasEditGameEventsRight($gameId)) {
        if (isset($name)) {
            $query = sprintf("
		UPDATE uo_game 
		SET official='%s' 
		WHERE game_id='%s'", DBEscapeString($name), DBEscapeString($gameId));
        } else {
            $query = sprintf("
		UPDATE uo_game
		SET official=NULL
		WHERE game_id='%s'", DBEscapeString($gameId));
        }
        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}


function GameSetHalftime($gameId, $time)
{
    if (hasEditGameEventsRight($gameId)) {
        if (isset($time)) {
            $query = sprintf("
			UPDATE uo_game 
			SET halftime='%s' 
			WHERE game_id='%s'", DBEscapeString($time), DBEscapeString($gameId));
        } else {
            $query = sprintf("
			UPDATE uo_game 
			SET halftime=NULL 
			WHERE game_id='%s'", DBEscapeString($gameId));
        }
        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameSetCaptain($gameId, $teamId, $playerId)
{
    if ((int) $playerId <= 0) {
        return GameSetCaptains($gameId, $teamId, []);
    }

    return GameSetCaptains($gameId, $teamId, [$playerId]);
}

function GameSetStartingTeam($gameId, $home)
{
    if (hasEditGameEventsRight($gameId)) {
        if ($home === null) {
            $query = sprintf(
                "DELETE FROM uo_gameevent WHERE game=%d AND type='offence'",
                (int) $gameId,
            );

            $result = DBQuery($query);

            return $result;
        } else {
            $query = sprintf(
                "INSERT INTO uo_gameevent (game, num, time, type, ishome) VALUES (%d, 0, 0, 'offence', %d)
			ON DUPLICATE KEY UPDATE ishome='%d'",
                (int) $gameId,
                (int) $home,
                (int) $home,
            );

            $result = DBQuery($query);

            return $result;
        }
    } else {
        die('Insufficient rights to edit game');
    }
}

/**
 * Set the owning (timetable=1) pool for a game in uo_game_pool, guaranteeing
 * exactly one owner row. Promotes an existing timetable=0 carryover row at
 * the same pool and removes any stale timetable=1 row pointing elsewhere.
 * Callers must have verified the appropriate edit-rights for the destination
 * pool's series before invoking.
 *
 * The insert runs before the delete inside a transaction, so a failure on the
 * destination row (for example, an FK violation against uo_pool) rolls back
 * cleanly and leaves the existing owner row in place. This protects against
 * the game disappearing from owner-row joins that the rest of the schema
 * now depends on.
 */
function SetGamePool($gameId, $poolId)
{
    $gameId = (int) $gameId;
    $poolId = (int) $poolId;
    if ($gameId <= 0 || $poolId <= 0) {
        return;
    }
    $previousExceptionMode = DBShouldThrowExceptions();
    DBSetExceptionMode(true);
    try {
        DBQuery('START TRANSACTION');
        DBQuery(sprintf(
            "INSERT INTO uo_game_pool (game, pool, timetable) VALUES (%d, %d, 1)
                ON DUPLICATE KEY UPDATE timetable=1",
            $gameId,
            $poolId,
        ));
        DBQuery(sprintf(
            "DELETE FROM uo_game_pool WHERE game=%d AND timetable=1 AND pool!=%d",
            $gameId,
            $poolId,
        ));
        DBQuery('COMMIT');
    } catch (Throwable $e) {
        DBQuery('ROLLBACK');
        DBSetExceptionMode($previousExceptionMode);
        throw $e;
    }
    DBSetExceptionMode($previousExceptionMode);
}

function AddGame($params)
{
    $poolinfo = PoolInfo($params['pool']);
    if (hasEditGamesRight($poolinfo['series'])) {
        $query = sprintf(
            "INSERT INTO uo_game
			(hometeam, visitorteam, reservation, time, valid, respteam)
			VALUES ('%s', '%s', '%s', '%s', '%s', '%s')",
            DBEscapeString($params['hometeam']),
            DBEscapeString($params['visitorteam']),
            DBEscapeString($params['reservation']),
            DBEscapeString($params['time']),
            DBEscapeString($params['valid']),
            DBEscapeString($params['respteam']),
        );

        $id = DBQueryInsert($query);
        SetGamePool($id, $params['pool']);

        Log1("game", "add", $id);
        return $id;
    } else {
        die('Insufficient rights to add game');
    }
}

function SetGame($gameId, $params)
{
    $series = GameSeries($gameId);
    if (hasEditGamesRight($series)) {
        if (!empty($params['pool'])) {
            $poolinfo = PoolInfo($params['pool']);
            if (!$poolinfo || !hasEditGamesRight($poolinfo['series'])) {
                die('Insufficient rights to edit game');
            }
        }

        $result = null;
        $allowedKeys = array_flip([
            "hometeam",
            "visitorteam",
            "scheduling_name_home",
            "scheduling_name_visitor",
            "reservation",
            "time",
            "valid",
            "islive",
            "liveurl",
        ]);

        $nullableFKs = ['reservation', 'hometeam', 'visitorteam'];
        foreach ($params as $key => $param) {
            if (!isset($allowedKeys[$key]) || $param === null || $param === false) {
                continue;
            }
            $isNullableFK = in_array($key, $nullableFKs, true);
            if (empty($param) && $isNullableFK) {
                $query = sprintf(
                    "UPDATE uo_game SET %s=NULL WHERE game_id='%s'",
                    $key,
                    DBEscapeString($gameId),
                );
            } elseif ($param === '' && $key !== 'liveurl') {
                continue;
            } else {
                $query = sprintf(
                    "UPDATE uo_game SET %s='%s' WHERE game_id='%s'",
                    $key,
                    DBEscapeString($param),
                    DBEscapeString($gameId),
                );
            }
            $result = DBQuery($query);
        }

        if (!empty($params['pool'])) {
            SetGamePool($gameId, $params['pool']);
            $result = true;
        }

        if (!empty($params['respteam'])) {
            $query = sprintf(
                "UPDATE uo_game SET respteam=%d
					WHERE game_id=%d",
                (int) $params['respteam'],
                (int) $gameId,
            );

            DBQuery($query);
        } else {
            $query = sprintf(
                "UPDATE uo_game SET respteam=NULL
					WHERE game_id=%d",
                (int) $gameId,
            );

            DBQuery($query);
        }

        if (!empty($params['name'])) {
            $query = sprintf(
                "INSERT INTO uo_scheduling_name
				(name) VALUES ('%s')",
                DBEscapeString($params['name']),
            );

            $nameId = DBQueryInsert($query);

            $query = sprintf(
                "UPDATE uo_game SET
					name=%d	WHERE game_id=%d",
                (int) $nameId,
                (int) $gameId,
            );
            DBQuery($query);
        } elseif (isset($params['name']) && $params['name'] === '') {
            $query = sprintf(
                "UPDATE uo_game SET name=NULL WHERE game_id=%d",
                (int) $gameId,
            );
            DBQuery($query);
        }

        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

/**
 * Swap home and visitor teams and results.
 */
function GameChangeHome($gameId)
{
    $series = GameSeries($gameId);
    if (hasEditGamesRight($series)) {

        $query = sprintf(
            "SELECT hometeam,visitorteam,respteam, homescore,visitorscore, scheduling_name_home, scheduling_name_visitor FROM uo_game
					WHERE game_id=%d",
            (int) $gameId,
        );
        $game = DBQueryToRow($query);

        $homeTeamSql = $game['visitorteam'] === null ? "NULL" : (string) (int) $game['visitorteam'];
        $visitorTeamSql = $game['hometeam'] === null ? "NULL" : (string) (int) $game['hometeam'];
        $homeScoreSql = $game['visitorscore'] === null ? "NULL" : (string) (int) $game['visitorscore'];
        $visitorScoreSql = $game['homescore'] === null ? "NULL" : (string) (int) $game['homescore'];
        $homeSchedulingSql = $game['scheduling_name_visitor'] === null ? "NULL" : (string) (int) $game['scheduling_name_visitor'];
        $visitorSchedulingSql = $game['scheduling_name_home'] === null ? "NULL" : (string) (int) $game['scheduling_name_home'];
        if ($game['respteam'] === null) {
            $respTeamSql = "NULL";
        } elseif ($game['hometeam'] == $game['respteam']) {
            $respTeamSql = $homeTeamSql;
        } elseif ($game['visitorteam'] == $game['respteam']) {
            $respTeamSql = $visitorTeamSql;
        } else {
            $respTeamSql = (string) (int) $game['respteam'];
        }

        $query = sprintf(
            "UPDATE uo_game SET hometeam=%s,visitorteam=%s,homescore=%s,visitorscore=%s, scheduling_name_home=%s, scheduling_name_visitor=%s, respteam=%s
					WHERE game_id=%d",
            $homeTeamSql,
            $visitorTeamSql,
            $homeScoreSql,
            $visitorScoreSql,
            $homeSchedulingSql,
            $visitorSchedulingSql,
            $respTeamSql,
            (int) $gameId,
        );

        DBQuery($query);
    } else {
        die('Insufficient rights to delete game');
    }
}

function GameChangeName($gameId, $name)
{
    $gameinfo = GameInfo($gameId);
    if (hasEditGamesRight($gameinfo['series'])) {
        if (empty($gameinfo['name'])) {
            $query = sprintf(
                "INSERT INTO uo_scheduling_name 
				(name) VALUES ('%s')",
                DBEscapeString($name),
            );
            $nameId = DBQueryInsert($query);

            $query = sprintf(
                "UPDATE uo_game SET name=%d WHERE game_id=%d",
                (int) $nameId,
                (int) $gameId,
            );
            $result = DBQuery($query);
        } else {
            $query = sprintf(
                "UPDATE uo_scheduling_name SET
				name='%s' WHERE scheduling_id=%d",
                DBEscapeString($name),
                (int) $gameinfo['name'],
            );
            $result = DBQuery($query);
        }
        return $result;
    } else {
        die('Insufficient rights to edit game');
    }
}

function GameProcessMassInput($post)
{
    $html = "";
    $scores = [];
    $changed = [];
    $ok_clear = 0;
    $ok_set = 0;
    $error_set = 0;
    $error_clear = 0;

    foreach ($post['scoreId'] as $key => $value) {
        $scores[$key]['gameid'] = $value;
    }
    foreach ($post['homescore'] as $key => $value) {
        $scores[$key]['home'] = $value;
    }
    foreach ($post['visitorscore'] as $key => $value) {
        $scores[$key]['visitor'] = $value;
    }
    foreach ($scores as $score) {
        $gameId = $score['gameid'];
        $game = GameInfo($gameId);
        if ($game['homescore'] !== $score['home'] || $game['visitorscore'] !== $score['visitor']) {
            if ($score['home'] === "" && $score['visitor'] === "" && (!is_null($game['homescore']) || !is_null($game['visitorscore']))) {
                $ok = GameClearResult($gameId, false);
                if ($ok) {
                    $ok_clear++;
                    $changed[GamePool($gameId)] = 1;
                } else {
                    $error_clear++;
                }
                // echo "clear $gameId";
            } elseif ($score['home'] !== "" && $score['visitor'] !== "") {
                $ok = GameSetResult($gameId, $score['home'], $score['visitor'], false);
                if ($ok) {
                    $ok_set++;
                    $changed[GamePool($gameId)] = 1;
                } else {
                    $error_set++;
                }
            }
        }
    }

    if ($ok_clear > 0) {
        $html .= "<p>" . sprintf(_("Results cleared: %s."), $ok_clear) . "</p>";
    }
    if ($ok_set > 0) {
        $html .= "<p>" . sprintf(_("Results changed: %s."), $ok_set) . "</p>";
    }
    if ($error_clear + $error_set > 0) {
        $html .= "<p>" . sprintf(_("Errors: %s."), ($error_clear + $error_set)) . "</p>";
    }

    foreach ($changed as $poolId => $_) {
        ResolvePoolStandings($poolId);
        PoolResolvePlayed($poolId);
    }

    return $html;
}

function DeleteGame($gameId)
{
    $series = GameSeries($gameId);
    if (hasEditGamesRight($series)) {
        Log2("game", "delete", GameNameFromId($gameId));
        $query = sprintf(
            "DELETE FROM uo_game 
        WHERE game_id='%d'",
            (int) $gameId,
        );

        $result = DBQuery($query);


        $query = sprintf(
            "DELETE FROM uo_game_pool
        WHERE game='%d' AND timetable=1",
            (int) $gameId,
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to delete game');
    }
}

function DeleteMovedGame($gameId, $poolId)
{
    $series = GameSeries($gameId);
    if (hasEditGamesRight($series)) {
        Log1("game", "delete", $gameId, $poolId, "Delete moved game");
        $query = sprintf(
            "DELETE FROM uo_game_pool 
		WHERE (game='%d' AND pool='%d' AND timetable='0')",
            (int) $gameId,
            (int) $poolId,
        );

        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to delete game');
    }
}

function PoolDeleteAllGames($poolId)
{
    $series = PoolSeries($poolId);
    if (hasEditGamesRight($series)) {
        Log1("game", "delete", $poolId, 0, "Delete pool games");
        // Delete games owned by this pool first; FK CASCADE removes their uo_game_pool rows.
        $query = sprintf(
            "DELETE g FROM uo_game g
                INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
                WHERE gp.pool=%d",
            (int) $poolId,
        );
        $result = DBQuery($query);

        // Mop up remaining carryover (timetable=0) entries pointing at this pool.
        $query = sprintf(
            "DELETE FROM uo_game_pool WHERE pool=%d",
            (int) $poolId,
        );
        $result = DBQuery($query);

        return $result;
    } else {
        die('Insufficient rights to delete game');
    }
}

function PoolSeries($poolId)
{
    $query = sprintf(
        "SELECT series
		FROM uo_pool
		WHERE pool_id='%d'",
        (int) $poolId,
    );
    return DBQueryToValue($query);
}

function UnscheduledGameInfo($teams = [])
{
    if (count($teams) == 0) {
        $query = "SELECT game_id FROM uo_game WHERE reservation IS NULL AND time IS NULL";
    } else {
        $fetch = [];
        foreach ($teams as $teamid) {
            $fetch[] = (int) $teamid;
        }
        $query = "SELECT game_id FROM uo_game WHERE reservation IS NULL AND time IS NULL AND
			hometeam IN (" . implode(",", $fetch) . ") AND visitorteam IN (" . implode(",", $fetch) . ")";
    }
    $result = DBQuery($query);

    $ret = [];
    while ($row = mysqli_fetch_row($result)) {
        $ret[$row[0]] = GameInfo($row[0]);
    }
    return $ret;
}

function UnscheduledPoolGameInfo($poolId)
{

    $query = sprintf(
        "SELECT g.game_id FROM uo_game g
		INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
		WHERE g.reservation IS NULL AND g.time IS NULL AND gp.pool=%d
		ORDER BY g.game_id",
        (int) $poolId,
    );

    $result = DBQuery($query);

    $ret = [];
    while ($row = mysqli_fetch_row($result)) {
        $ret[$row[0]] = GameInfo($row[0]);
    }
    return $ret;
}

function UnscheduledSeriesGameInfo($seriesId)
{

    $query = sprintf(
        "SELECT g.game_id FROM uo_game g
		INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
		LEFT JOIN uo_pool pool ON (pool.pool_id=gp.pool)
		WHERE g.reservation IS NULL AND g.time IS NULL AND pool.series=%d
		ORDER BY pool.ordering, g.game_id",
        (int) $seriesId,
    );

    $result = DBQuery($query);

    $ret = [];
    while ($row = mysqli_fetch_row($result)) {
        $ret[$row[0]] = GameInfo($row[0]);
    }
    return $ret;
}

function UnscheduledSeasonGameInfo($seasonId)
{

    $query = sprintf(
        "SELECT g.game_id FROM uo_game g
		INNER JOIN uo_game_pool gp ON (gp.game=g.game_id AND gp.timetable=1)
		LEFT JOIN uo_pool pool ON (pool.pool_id=gp.pool)
		LEFT JOIN uo_series ser ON (ser.series_id=pool.series)
		WHERE g.reservation IS NULL AND g.time IS NULL AND ser.season='%s'
		ORDER BY ser.ordering, pool.ordering, g.game_id",
        DBEscapeString($seasonId),
    );

    $result = DBQuery($query);
    $ret = [];
    while ($row = mysqli_fetch_row($result)) {
        $ret[$row[0]] = GameInfo($row[0]);
    }
    return $ret;
}

function ScheduleGame($gameId, $epoc, $reservation)
{
    if (hasEditGamesRight(GameSeries($gameId))) {
        $query = sprintf(
            "UPDATE uo_game SET time='%s', reservation=%d WHERE game_id=%d",
            EpocToMysql($epoc),
            (int) $reservation,
            (int) $gameId,
        );
        DBQuery($query);
    } else {
        die('Insufficient rights to schedule game');
    }
}

function UnScheduleGame($gameId)
{
    if (hasEditGamesRight(GameSeries($gameId))) {
        $query = sprintf(
            "UPDATE uo_game SET time=NULL, reservation=NULL WHERE game_id=%d",
            (int) $gameId,
        );
        DBQuery($query);
    } else {
        die('Insufficient rights to schedule game');
    }
}

function ClearReservation($reservationId)
{
    foreach (ReservationGames($reservationId) as $row) {
        if (hasEditGamesRight(GameSeries($row['game_id']))) {
            UnScheduleGame($row['game_id']);
        } // else ignore games not managed by user
    }
}

function CanDeleteGame($gameId)
{
    $query = sprintf(
        "SELECT count(*) FROM uo_goal WHERE game=%d",
        (int) $gameId,
    );
    $count = DBQueryToValue($query);

    if ($count == 0) {
        $query = sprintf(
            "SELECT count(*) FROM uo_played WHERE game=%d",
            (int) $gameId,
        );
        $count = DBQueryToValue($query);
        if ($count == 0) {
            $query = sprintf(
                "SELECT count(*) FROM uo_gameevent WHERE game=%d",
                (int) $gameId,
            );
            $count = DBQueryToValue($query);
            if ($count == 0) {
                $query = sprintf(
                    "SELECT homescore,visitorscore FROM uo_game WHERE game_id=%d",
                    (int) $gameId,
                );
                $row = DBQueryToRow($query);

                return (intval($row['homescore']) + intval($row['visitorscore'])) == 0;
            } else {
                return false;
            } // FIXME test hasstarted?
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function ResultsToCsv($season, $separator)
{

    $query = sprintf(
        "SELECT kj.name as Home, vj.name as Away,
			homescore AS HomeScores, visitorscore AS AwayScores, ser.name AS Division, pool.name AS Pool
		FROM uo_game pp
			INNER JOIN uo_game_pool gp ON (gp.game=pp.game_id AND gp.timetable=1)
			left join uo_reservation res on (pp.reservation=res.id)
			left join uo_pool pool on (pool.pool_id=gp.pool)
			left join uo_series ser on (ser.series_id=pool.series)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
		WHERE ser.season='%s' AND (pp.hasstarted>0)
		ORDER BY ser.ordering, pool.ordering, pp.time ASC, pp.game_id ASC",
        DBEscapeString($season),
    );

    $result = DBQuery($query);
    return ResultsetToCsv($result, $separator);
}

function SpiritTable($gameinfo, $points, $categories, $home, $wide = true)
{
    $home = $home ? "home" : "vis";
    $html = "<table class='spirit-table'>\n";
    $html .= "<tr>";
    if ($wide) {
        $html .= "<th style='width:70%;text-align: right;'></th>";
    }
    $vmin = 99999;
    $vmax = -99999;
    foreach ($categories as $cat) {
        if ($vmin > $cat['min']) {
            $vmin = $cat['min'];
        }
        if ($vmax < $cat['max']) {
            $vmax = $cat['max'];
        }
    }

    if ($vmax - $vmin < 12) {
        $colspan = ($wide ? 3 : 2);
        $html .= "<th></th></tr>\n";

        foreach ($categories as $cat) {
            if ($cat['index'] == 0) {
                continue;
            }
            $id = $cat['category_id'];
            $html .= "<tr>";
            if ($wide) {
                $html .= "<td style='width:70%'>";
            } else {
                $html .= "<td colspan='$colspan'>";
            }
            $html .= _($cat['text']);
            $html .= "<input type='hidden' id='" . $home . "valueId$id' name='" . $home . "valueId[]' value='$id'/>";
            if ($wide) {
                $html .= "</td>";
            } else {
                $html .= "</td></tr>\n<tr>";
            }

            $cellColspan = $wide ? "" : " colspan='$colspan'";
            $html .= "<td class='spirit-control-cell'$cellColspan><fieldset class='spirit-controlgroup' id='" . $home . "cat'" . $id . "_0' data-role='controlgroup' data-type='horizontal' >";
            for ($i = $vmin; $i <= $vmax; ++$i) {
                if ($i < $cat['min']) {
                    // $html .= "<td></td>";
                } else {
                    $id = $cat['category_id'];
                    $checked = (isset($points[$id]) && !is_null($points[$id]) && $points[$id] == $i) ? "checked='checked'" : "";
                    $html .= "<span class='spirit-choice'>";
                    $html .= "<label for='" . $home . "cat" . $id . "_" . $i . "'>$i</label>";
                    $html .= "<input type='radio' id='" . $home . "cat" . $id . "_" . $i . "' name='" . $home . "cat" . $id . "' value='$i' $checked/>";
                    $html .= "</span>";

                    // $html .= "<td class='center'>
                    // <input type='radio' id='".$home."cat".$id."_".$i."' name='".$home."cat". $id . "' value='$i'  $checked/></td>";
                }
            }
            $html .= "</fieldset></td>";
            $html .= "</tr>\n";
        }
    } else {
        $colspan = 2;
        $html .= "<th colspan='2'></th></tr>\n";

        foreach ($categories as $cat) {
            if ($cat['index'] == 0) {
                continue;
            }
            $id = $cat['category_id'];
            $html .= "<tr>";
            $html .= "<td style='width:70%'>" . _($cat['text']);
            $html .= "<input type='hidden' id='" . $home . "valueId$id' name='" . $home . "valueId[]' value='$id'/></td>";
            $value = isset($points[$id]) ? $points[$id] : '';
            $html .= "<td class='center'>
      <input type='text' inputmode='numeric' pattern='[0-9]*' id='" . $home . "cat" . $id . "_0' name='" . $home . "cat$id' value='" . $value . "'/></td>";
            $html .= "</tr>\n";
        }
    }


    $html .= "<tr>";
    $html .= "<td class='highlight' colspan='$colspan'>" . _("Total points");
    $total = SpiritTotal($points, $categories);
    if (!isset($total)) {
        $total = ": -";
    } else {
        $html .= ": $total";
    }
    $html .= "</tr>";

    $html .= "</table>\n";

    return $html;
}

function isGameLive($gameId)
{

    $query = sprintf("SELECT islive FROM uo_game WHERE game_id=%d LIMIT 1", (int) $gameId);

    return (int) DBQueryToValue($query);
}

function GameLiveURL($gameId)
{

    $query = sprintf("SELECT liveurl FROM uo_game WHERE game_id=%d LIMIT 1", (int) $gameId);

    $result = DBQueryToValue($query);

    if ($result) {
        return filter_var($result, FILTER_VALIDATE_URL);
    } else {
        return false;
    }
}

function UpdateGameLiveURL($gameId, $url)
{
    $gameId = (int) $gameId;
    if (!hasEditGamesRight(GameSeries($gameId))) {
        die('Insufficient rights to edit game');
    }

    $query = sprintf("UPDATE uo_game SET liveurl = '%s' WHERE game_id = %d", DBEscapeString($url), $gameId);

    return DBQuery($query);
}

function isGameOngoing($gameId)
{

    $query = sprintf("SELECT isongoing FROM uo_game WHERE game_id=%d LIMIT 1", (int) $gameId);

    return (int) DBQueryToValue($query);
}

function isGamePaused($gameId)
{

    $query = sprintf("SELECT (isongoing=1 AND timer_pause_start IS NOT NULL) AS ispaused FROM uo_game WHERE game_id=%d LIMIT 1", (int) $gameId);

    return (int) DBQueryToValue($query);
}

function GameTimeReset($gameId)
{
    $gameId = (int) $gameId;
    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game events');
    }

    $query = sprintf(
        "UPDATE uo_game SET timer_start=NULL, timer_pause_start=NULL, timer_paused_duration=0, isongoing=0, hasstarted=0 WHERE game_id=%d",
        $gameId,
    );

    return DBQuery($query);
}

function GameTimeStart($gameId)
{
    $gameId = (int) $gameId;
    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game events');
    }

    $query = sprintf(
        "UPDATE uo_game SET hasstarted = 1, isongoing = 1, timer_start = %d, timer_pause_start = NULL, timer_paused_duration = 0 WHERE game_id = %d",
        time(),
        $gameId,
    );

    return DBQuery($query);
}

function GameTimePause($gameId)
{
    $gameId = (int) $gameId;
    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game events');
    }

    $query = sprintf("UPDATE uo_game SET timer_pause_start = %d 
    WHERE game_id = %d AND isongoing = 1 AND timer_pause_start IS NULL", time(), $gameId);

    return DBQuery($query);
}

function GameTimeResume($gameId)
{
    $gameId = (int) $gameId;
    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game events');
    }

    $query = sprintf("SELECT timer_pause_start, timer_paused_duration FROM uo_game WHERE game_id = %d LIMIT 1", $gameId);
    $row = DBQueryToRow($query);

    if ($row && $row['timer_pause_start']) {
        $pausedTime = time() - (int) $row['timer_pause_start'];
        $totalPaused = (int) $row['timer_paused_duration'] + $pausedTime;

        $updateQuery = sprintf("UPDATE uo_game SET timer_paused_duration = %d, timer_pause_start = NULL 
      WHERE game_id = %d", $totalPaused, $gameId);

        return DBQuery($updateQuery);
    }

    return false; // Not paused or invalid
}

function GameTimeSetElapsed($gameId, $elapsedSeconds)
{
    $gameId = (int) $gameId;
    $elapsedSeconds = max(0, (int) $elapsedSeconds);
    if (!hasEditGameEventsRight($gameId)) {
        die('Insufficient rights to edit game events');
    }

    $query = sprintf(
        "SELECT timer_pause_start, timer_paused_duration FROM uo_game WHERE game_id = %d AND isongoing = 1 AND timer_pause_start IS NOT NULL LIMIT 1",
        $gameId,
    );
    $row = DBQueryToRow($query);

    if (!$row || empty($row['timer_pause_start'])) {
        return false;
    }

    $timerStart = (int) $row['timer_pause_start'] - (int) $row['timer_paused_duration'] - $elapsedSeconds;
    $updateQuery = sprintf(
        "UPDATE uo_game SET timer_start = %d WHERE game_id = %d",
        $timerStart,
        $gameId,
    );

    return DBQuery($updateQuery);
}

function GameElapsedTime($gameId)
{
    $state = GameTimerState($gameId);

    return ["mm" => $state['mm'], "ss" => $state['ss'], "rss" => $state['rss']];
}
