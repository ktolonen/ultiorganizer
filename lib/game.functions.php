<?php
require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/accreditation.functions.php';
require_once __DIR__ . '/configuration.functions.php';

function SeasonScoreCounter($seasonId = "")
{
	$query = "SELECT COALESCE(SUM(game.homescore), 0) + COALESCE(SUM(game.visitorscore), 0) AS scores
		FROM uo_game game
		LEFT JOIN uo_pool pool ON(pool.pool_id=game.pool)
		LEFT JOIN uo_series ser ON(pool.series=ser.series_id)";

	if (!empty($seasonId)) {
		$query .= sprintf(" WHERE ser.season='%s'", DBEscapeString($seasonId));
	}

	return (int) DBQueryToValue($query);
}

function GameSetPools($games)
{
	$gameIds = array_filter(array_map('intval', (array)$games), function ($val) {
		return $val > 0;
	});
	if (empty($gameIds)) {
		return array();
	}
	$query = "SELECT DISTINCT pool_id, p.name from uo_game g left join uo_pool p on (g.pool=p.pool_id) WHERE g.game_id in (";
	$query .= implode(",", $gameIds);
	$query .= ") ORDER BY p.ordering ASC";
	$result = DBQuery($query);

	$ret = array();
	while ($row = mysqli_fetch_assoc($result)) {
		$ret[$row['pool_id']] = $row;
	}
	return $ret;
}

function PoolGameSetResults($pool, $games)
{
	$gameIds = array_filter(array_map('intval', (array)$games), function ($val) {
		return $val > 0;
	});
	if (empty($gameIds)) {
		return array();
	}
	$query = sprintf(
		"SELECT time, k.name As hometeamname, v.name As visitorteamname, p.*,s.name AS gamename
		FROM uo_game AS p 
		LEFT JOIN uo_team As k ON (p.hometeam=k.team_id) 
		LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
		LEFT JOIN uo_scheduling_name s ON(s.scheduling_id=p.name)
		WHERE p.game_id IN (%s) AND pool=%d",
		implode(",", $gameIds),
		(int)$pool
	);
	$result = DBQuery($query);

	return $result;
}

function GameResult($gameId)
{
	$query = sprintf(
		"SELECT time, k.name As hometeamname, v.name As visitorteamname, 
        k.valid as homevalid, v.valid as visitorvalid, 
        p.*, hspirit.mode AS spiritmode, hspirit.sotg AS homesotg, vspirit.sotg AS visitorsotg, s.name AS gamename
    FROM uo_game AS p 
    LEFT JOIN (SELECT ssc.game_id, ssc.team_id, sct.mode, SUM(value*factor) AS sotg 
               FROM uo_spirit_score ssc 
               LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id) 
               GROUP BY game_id, team_id, sct.mode) AS hspirit
       ON (p.game_id = hspirit.game_id AND hspirit.team_id = p.hometeam)
    LEFT JOIN (SELECT ssc.game_id, ssc.team_id, sct.mode, SUM(value*factor) AS sotg 
               FROM uo_spirit_score ssc 
               LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id) 
               GROUP BY game_id, team_id, sct.mode ) AS vspirit
       ON (p.game_id = vspirit.game_id AND vspirit.team_id = p.visitorteam)
    LEFT JOIN uo_team As k ON (p.hometeam=k.team_id) 
    LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
    LEFT JOIN uo_scheduling_name s ON(s.scheduling_id=p.name)
    WHERE p.game_id='%s'",
		DBEscapeString($gameId)
	);

	$result = DBQuery($query);

	return mysqli_fetch_assoc($result);
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
		(int)$gameId,
		(int)$num
	);

	$result = DBQuery($query);

	if ($row = mysqli_fetch_assoc($result)) {
		return $row;
	} else return false;
}

function GameHomeTeamResults($teamId, $poolId)
{
	$query = sprintf(
		"SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.visitorteam, COALESCE(pm.goals,0) AS scoresheet,
			sn.name AS gamename, g.isongoing, g.hasstarted
			FROM uo_game g 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (g.game_id=pm.game)
			LEFT JOIN uo_scheduling_name sn ON(g.name=sn.scheduling_id)
			WHERE g.hometeam=%d AND g.pool=%d
			GROUP BY g.game_id",
		(int) $teamId,
		(int) $poolId
	);
	return DBQueryToArray($query);
}

function GameHomePseudoTeamResults($schedulingId, $poolId)
{
	$query = sprintf(
		"SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.visitorteam, 
			sn.name AS gamename, g.isongoing, g.hasstarted
			FROM uo_game g 
			LEFT JOIN uo_scheduling_name sn ON(g.name=sn.scheduling_id)
			WHERE g.scheduling_name_home=%d AND g.pool=%d
			GROUP BY g.game_id",
		(int) $schedulingId,
		(int) $poolId
	);
	return DBQueryToArray($query);
}

function GameVisitorTeamResults($teamId, $poolId)
{
	$query = sprintf(
		"SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.hometeam, COALESCE(pm.goals,0) AS scoresheet
			FROM uo_game g 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (g.game_id=pm.game)
			WHERE g.visitorteam=%d AND g.pool=%d AND g.hasstarted>0 AND g.valid=1 AND isongoing=0
			GROUP BY g.game_id",
		(int) $teamId,
		(int) $poolId
	);
	return DBQueryToArray($query);
}

function GameNameFromId($gameId)
{
	$query = sprintf(
		"SELECT k.name As hometeamname, v.name As visitorteamname 
		FROM (uo_game AS p LEFT JOIN uo_team As k ON (p.hometeam=k.team_id)) LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
		WHERE game_id=%d",
		(int)$gameId
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
		FROM uo_game p left join uo_pool s on (p.pool=s.pool_id)  
		WHERE game_id='%s'",
		DBEscapeString($gameId)
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
		(int)$gameId
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
 * @return php array of users
 */
function GameAdmins($gameId)
{
	$query = sprintf(
		"SELECT u.userid, u.name FROM uo_users u
  			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
  			WHERE SUBSTRING_INDEX(up.value, ':', -1)='%d'
			ORDER BY u.name",
		(int)$gameId
	);
	return DBQueryToArray($query);
}

function GamePool($gameId)
{
	$query = sprintf(
		"SELECT pool 
		FROM uo_game  
		WHERE game_id=%d",
		(int)$gameId
	);
	$result = DBQueryToValue($query);

	return $result;
}

function GameIsFirstOffenceHome($gameId)
{
	$query = sprintf(
		"SELECT ishome 
		FROM uo_gameevent  
		WHERE game=%d ORDER BY time",
		(int)$gameId
	);
	$result = DBQueryToValue($query);

	return $result;
}

function GameReservation($gameId)
{
	$query = sprintf(
		"SELECT reservation 
		FROM uo_game  
		WHERE game_id=%d",
		(int)$gameId
	);
	$result = DBQueryToValue($query);

	return $result;
}

function GameSeason($gameId)
{
	$query = sprintf(
		"SELECT ser.season 
		FROM uo_game p LEFT JOIN uo_pool s on (p.pool=s.pool_id)
 			LEFT JOIN uo_series ser ON (s.series=ser.series_id)  
		WHERE game_id=%d",
		(int)$gameId
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
		WHERE pg.game=%d AND p.team=%d",
		(int)$gameId,
		(int)$teamId
	);

	return DBQueryToArray($query);
}

function GameRolePlayers($gameId, $teamId, $roleColumn)
{
	if ($roleColumn !== 'captain' && $roleColumn !== 'spirit_captain') {
		return array();
	}

	$query = sprintf(
		"SELECT pg.player
		FROM uo_played AS pg 
		LEFT JOIN uo_player AS p ON(pg.player=p.player_id)
		WHERE pg.%s=1 AND pg.game=%d AND p.team=%d",
		$roleColumn,
		(int)$gameId,
		(int)$teamId
	);

	$rows = DBQueryToArray($query);
	$playerIds = array();
	foreach ($rows as $row) {
		$playerIds[] = (int)$row['player'];
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
	$allowedPlayers = array();
	foreach (GamePlayers($gameId, $teamId) as $player) {
		$allowedPlayers[(int)$player['player_id']] = true;
	}

	$filteredPlayerIds = array();
	foreach ((array)$playerIds as $playerId) {
		$playerId = (int)$playerId;
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
			(int)$gameId,
			(int)$teamId
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
			(int)$gameId,
			implode(',', $playerIds)
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

function GameAll($limit = 50)
{
	$limit = intval($limit);
	//common game query
	$query = "SELECT pp.game_id, pp.time, pp.hometeam, pp.visitorteam, pp.homescore, 
			pp.visitorscore, pp.pool AS pool, pool.name AS poolname, pool.timeslot,
			ps.series_id, ps.name AS seriesname, ps.season, s.name AS seasonname, ps.type, pr.fieldname, pr.reservationgroup,
			pr.id AS reservation_id, pr.starttime, pr.endtime, pl.id AS place_id, 
			pl.name AS placename, pl.address, pp.isongoing, pp.hasstarted, home.name AS hometeamname, visitor.name AS visitorteamname,
			phome.name AS phometeamname, pvisitor.name AS pvisitorteamname, pool.color, pgame.name AS gamename,
			home.abbreviation AS homeshortname, visitor.abbreviation AS visitorshortname, homec.country_id AS homecountryid, 
			homec.name AS homecountry, visitorc.country_id AS visitorcountryid, visitorc.name AS visitorcountry, s.timezone
			FROM uo_game pp 
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
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
			WHERE pp.valid=true AND pp.hasstarted>0 AND pp.isongoing=0  ORDER BY pp.time DESC, ps.ordering, pool.ordering, pp.game_id
			LIMIT $limit";
	return DBQuery($query);
}

function GameAllArray($limit = 50)
{
	return DBFetchAllAssoc(GameAll($limit));
}

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
		DBEscapeString($number)
	);

	$result = DBQueryToValue($query);
	return $result;
}


function GameTeamScoreBorad($gameId, $teamId)
{
	$query = sprintf(
		"SELECT p.player_id, p.firstname, p.lastname, p.profile_id, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin, 
		(COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, pel.num AS num FROM uo_player AS p 
		LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done 
			FROM uo_goal AS m WHERE m.game='%s' AND m.scorer IS NOT NULL GROUP BY scorer) AS t ON (p.player_id=t.scorer) 
		LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin FROM uo_goal AS m2 
			WHERE m2.game='%s' AND m2.assist IS NOT NULL GROUP BY assist) AS s ON (p.player_id=s.assist) 
		RIGHT JOIN (SELECT player, num FROM uo_played WHERE game='%s') as pel ON (p.player_id=pel.player) 
			WHERE p.team='%s' 
		ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, firstname ASC",
		DBEscapeString($gameId),
		DBEscapeString($gameId),
		DBEscapeString($gameId),
		DBEscapeString($teamId)
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
		DBEscapeString($teamId)
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
		DBEscapeString($gameId)
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
	$query = sprintf("
		SELECT m.*, s.num AS assistnum, s.firstname AS assistfirstname, s.lastname AS assistlastname, t.num AS scorernum, t.firstname AS scorerfirstname, t.lastname AS scorerlastname 
		FROM (uo_goal AS m LEFT JOIN uo_player AS s ON (m.assist = s.player_id)) 
		LEFT JOIN uo_player AS t ON (m.scorer=t.player_id) 
		WHERE m.game='%s' 
		ORDER BY m.num",
		DBEscapeString($gameId)
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
		DBEscapeString($gameId)
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
		DBEscapeString($gameId)
	);

	return DBQueryToRow($query);
}

function GoalPlayerDisplayText($playerId, $gameId, $firstname = '', $lastname = '')
{
	$playerId = (int)$playerId;
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
	$query = sprintf(
		"SELECT num,time,ishomegoal 
		FROM uo_goal 
		WHERE game='%s' 
		ORDER BY time",
		DBEscapeString($gameId)
	);

	return DBQueryToArray($query);
}

function GameEvents($gameId)
{
	$query = sprintf(
		"SELECT time,ishome,type 
		FROM (
			SELECT time,ishome,'timeout' AS type FROM `uo_timeout`
				WHERE game='%s'
			UNION ALL
			SELECT time,ishome,'spirit_timeout' AS type FROM `uo_spirit_timeout`
				WHERE game='%s'
			UNION ALL
			SELECT time,ishome,type FROM uo_gameevent WHERE game='%s'
		) AS tapahtuma 
		WHERE type!='media'
		ORDER BY time ",
		DBEscapeString($gameId),
		DBEscapeString($gameId),
		DBEscapeString($gameId)
	);

	return DBQueryToArray($query);
}

function GameMediaEvents($gameId)
{
	$query = sprintf(
		"SELECT u.time, u.ishome, u.type as eventtype, u.info, urls.*
		FROM uo_gameevent u
		LEFT JOIN uo_urls urls ON(u.info=urls.url_id)
		WHERE u.game=%d AND u.type='media'
		ORDER BY time ",
		(int)$gameId
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
			(int)$gameId,
			(int)$time,
			(int)$urlId
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
			"DELETE FROM uo_gameevent WHERE game=%d AND info=%d",
			(int)$gameId,
			(int)$urlId
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
		DBEscapeString($gameId)
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
		DBEscapeString($gameId)
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
		DBEscapeString($gameId)
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
		"SELECT game_id, hometeam, kj.name as hometeamname, kj.abbreviation as hometeamshortname, visitorteam, vj.name as visitorteamname, vj.abbreviation as visitorteamshortname, pp.pool as pool,
			time, homescore, visitorscore, pool.timecap, pool.scorecap, pool.winningscore, pool.drawsallowed, pool.timeslot AS timeslot, 
			pp.timeslot AS gametimeslot, pool.series, pool.color, ser.season, ser.name AS seriesname,
			pool.name AS poolname, phome.name AS phometeamname, pvisitor.name AS pvisitorteamname, pp.scheduling_name_home,
			pp.scheduling_name_visitor, isongoing, hasstarted, pl.name AS placename, res.fieldname, sname.name AS gamename,
			kj.valid as homevalid, vj.valid as visitorvalid
		FROM uo_game pp 
			left join uo_reservation res on (pp.reservation=res.id) 
			LEFT JOIN uo_location pl ON (res.location=pl.id)
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_series ser on (ser.series_id=pool.series)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
			LEFT JOIN uo_scheduling_name AS sname ON (pp.name=sname.scheduling_id)
		WHERE pp.game_id=%d",
		(int)$gameId
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

function GameTimerState($gameId)
{
	$gameId = (int) $gameId;
	$state = array(
		"started" => false,
		"ongoing" => false,
		"paused" => false,
		"mm" => 0,
		"ss" => 0,
		"rss" => 0
	);

	$query = sprintf(
		"SELECT hasstarted, isongoing, timer_start, timer_pause_start, timer_paused_duration FROM uo_game WHERE game_id=%d LIMIT 1",
		$gameId
	);
	$row = DBQueryToRow($query);
	if (!$row) {
		return $state;
	}

	$state['started'] = ((int) $row['hasstarted'] > 0) || !empty($row['timer_start']);
	$state['ongoing'] = (int) $row['isongoing'] === 1;
	$state['paused'] = $state['ongoing'] && !empty($row['timer_pause_start']);

	if (empty($row['timer_start'])) {
		return $state;
	}

	$elapsed = time() - (int) $row['timer_start'] - (int) $row['timer_paused_duration'];
	if (!empty($row['timer_pause_start'])) {
		$elapsed -= time() - (int) $row['timer_pause_start'];
	}
	$elapsed = max(0, $elapsed);

	$state['mm'] = (int) floor($elapsed / 60);
	$state['ss'] = $elapsed % 60;
	$state['rss'] = (int) (round($state['ss'] / 5) * 5);

	if ($state['rss'] === 60) {
		$state['mm']++;
		$state['rss'] = 0;
	}

	return $state;
}

function CheckGameResult($game, $home, $away)
{
	$gameId = (int) substr($game, 0, -1);
	$errors = "";
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
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf(
			"UPDATE uo_game SET homescore='%s', visitorscore='%s', isongoing='1', hasstarted='1' WHERE game_id='%s'",
			DBEscapeString($home),
			DBEscapeString($away),
			DBEscapeString($gameId)
		);
		$result = DBQuery($query);

		return $result;
	} else {
		die('Insufficient rights to edit game');
	}
}

function GameSetResult($gameId, $home, $away, $updatePools = true, $checkRights = true)
{
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
			DBEscapeString($gameId)
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

function GameClearResult($gameId, $updatepools = true)
{
	if (hasEditGameEventsRight($gameId)) {
		LogGameUpdate($gameId, "result cleared");
		$query = sprintf(
			"UPDATE uo_game SET homescore=NULL, visitorscore=NULL, isongoing='0', hasstarted='0', timer_start=NULL, timer_pause_start=NULL, timer_paused_duration=0 WHERE game_id='%s'",
			DBEscapeString($gameId)
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
			DBEscapeString($gameId)
		);
		$result = DBQuery($query);

		return $result;
	} else {
		die('Insufficient rights to edit game');
	}
}

function GameAddPlayer($gameId, $playerId, $number)
{
	if (hasEditGamePlayersRight($gameId)) {
		$query = sprintf(
			"INSERT INTO uo_played 
			(game, player, num, accredited) 
			VALUES ('%s', '%s', '%s', %d)
			ON DUPLICATE KEY UPDATE num=%d",
			DBEscapeString($gameId),
			DBEscapeString($playerId),
			DBEscapeString($number),
			(int)isAccredited($playerId),
			DBEscapeString($number)
		);

		$result = DBQuery($query);
		$query = sprintf(
			"UPDATE uo_player SET num=%d WHERE player_id=%d",
			(int)$number,
			(int)$playerId
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
			"INSERT INTO uo_player (firstname, lastname, team) VALUES ('%s', '%s', %d)",
			DBEscapeString($firstname),
			DBEscapeString($lastname),
			(int)$teamId
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
			DBEscapeString($playerId)
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
			DBEscapeString($gameId)
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
			(int)isAccredited($playerId),
			(int)$gameId,
			(int)$playerId
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
			DBEscapeString($gameId)
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
			DBEscapeString($gameId)
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
			(int)$num
		);

		$result = DBQuery($query);

		return $result;
	} else {
		die('Insufficient rights to edit game');
	}
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
				DBEscapeString($iscallahan)
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
			DBEscapeString($home)
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
		$assistValue = ($assist === -1 || $assist === 0 || $assist === "0" || $assist === "" || $assist === null || strcasecmp((string)$assist, "x") == 0 || strcasecmp((string)$assist, "xx") == 0) ? "NULL" : "'" . DBEscapeString($assist) . "'";
		$scorerValue = ($scorer === -1 || $scorer === 0 || $scorer === "0" || $scorer === "" || $scorer === null || strcasecmp((string)$scorer, "x") == 0 || strcasecmp((string)$scorer, "xx") == 0) ? "NULL" : "'" . DBEscapeString($scorer) . "'";

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
			DBEscapeString($uo_goal['iscallahan'])
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
			DBEscapeString($gameId)
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
			DBEscapeString($home)
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
			DBEscapeString($gameId)
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
			DBEscapeString($home)
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
	if ((int)$playerId <= 0) {
		return GameSetCaptains($gameId, $teamId, array());
	}

	return GameSetCaptains($gameId, $teamId, array($playerId));
}

function GameSetStartingTeam($gameId, $home)
{
	if (hasEditGameEventsRight($gameId)) {
		if ($home === NULL) {
			$query = sprintf(
				"DELETE FROM uo_gameevent WHERE game=%d AND type='offence'",
				(int)$gameId
			);

			$result = DBQuery($query);

			return $result;
		} else {
			$query = sprintf(
				"INSERT INTO uo_gameevent (game, num, time, type, ishome) VALUES (%d, 0, 0, 'offence', %d)
			ON DUPLICATE KEY UPDATE ishome='%d'",
				(int)$gameId,
				(int)$home,
				(int)$home
			);

			$result = DBQuery($query);

			return $result;
		}
	} else {
		die('Insufficient rights to edit game');
	}
}

function AddGame($params)
{
	$poolinfo = PoolInfo($params['pool']);
	if (hasEditGamesRight($poolinfo['series'])) {
		$query = sprintf(
			"INSERT INTO uo_game
			(hometeam, visitorteam, reservation, time, pool, valid, respteam) 
			VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s')",
			DBEscapeString($params['hometeam']),
			DBEscapeString($params['visitorteam']),
			DBEscapeString($params['reservation']),
			DBEscapeString($params['time']),
			DBEscapeString($params['pool']),
			DBEscapeString($params['valid']),
			DBEscapeString($params['respteam'])
		);

		$id = DBQueryInsert($query);
		$query = sprintf(
			"INSERT INTO uo_game_pool
			(game, pool, timetable) 
			VALUES ('%s', '%s', 1)",
			DBEscapeString($id),
			DBEscapeString($params['pool'])
		);

		$result = DBQuery($query);

		Log1("game", "add", $id);
		return $id;
	} else {
		die('Insufficient rights to add game');
	}
}

function SetGame($gameId, $params)
{
	$poolinfo = PoolInfo($params['pool']);
	if (hasEditGamesRight($poolinfo['series'])) {
			$allowedKeys = array_flip(array(
				"hometeam",
				"visitorteam",
				"scheduling_name_home",
				"scheduling_name_visitor",
				"reservation",
				"time",
				"pool",
				"valid",
				"islive",
				"liveurl"
			));

			$nullableFKs = array('reservation', 'hometeam', 'visitorteam');
			foreach ($params as $key => $param) {
				if (!isset($allowedKeys[$key]) || $param === null || $param === false) {
					continue;
				}
				$isNullableFK = in_array($key, $nullableFKs, true);
				if (empty($param) && $isNullableFK) {
					$query = sprintf(
						"UPDATE uo_game SET %s=NULL WHERE game_id='%s'",
						$key,
						DBEscapeString($gameId)
					);
				} elseif ($param === '' && $key !== 'liveurl') {
					continue;
				} else {
					$query = sprintf(
						"UPDATE uo_game SET %s='%s' WHERE game_id='%s'",
						$key,
						DBEscapeString($param),
						DBEscapeString($gameId)
					);
				}
				$result = DBQuery($query);
			}

			if (!empty($params['respteam'])) {
			$query = sprintf(
				"UPDATE uo_game SET respteam=%d
					WHERE game_id=%d",
				(int)$params['respteam'],
				(int)$gameId
			);

			DBQuery($query);
		} else {
			$query = sprintf(
				"UPDATE uo_game SET respteam=NULL
					WHERE game_id=%d",
				(int)$gameId
			);

			DBQuery($query);
		}

		if (!empty($params['name'])) {
			$query = sprintf(
				"INSERT INTO uo_scheduling_name
				(name) VALUES ('%s')",
				DBEscapeString($params['name'])
			);

			$nameId = DBQueryInsert($query);

			$query = sprintf(
				"UPDATE uo_game SET
					name=%d	WHERE game_id=%d",
				(int)$nameId,
				(int)$gameId
			);
			DBQuery($query);
		} elseif (isset($params['name']) && $params['name'] === '') {
			$query = sprintf(
				"UPDATE uo_game SET name=NULL WHERE game_id=%d",
				(int)$gameId
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
			(int)$gameId
		);
		$game = DBQueryToRow($query);

		$query = sprintf(
			"UPDATE uo_game SET hometeam=%d,visitorteam=%d,homescore=%d,visitorscore=%d, scheduling_name_home=%d, scheduling_name_visitor=%d
					WHERE game_id=%d",
			(int) $game['visitorteam'],
			(int) $game['hometeam'],
			(int) $game['visitorscore'],
			(int) $game['homescore'],
			(int) $game['scheduling_name_visitor'],
			(int) $game['scheduling_name_home'],
			(int)$gameId
		);

		DBQuery($query);
		if ($game['hometeam'] == $game['respteam']) {
			$query = sprintf(
				"UPDATE uo_game SET respteam=%d	WHERE game_id=%d",
				(int) $game['visitorteam'],
				(int)$gameId
			);
			DBQuery($query);
		}
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
				DBEscapeString($name)
			);
			$nameId = DBQueryInsert($query);

			$query = sprintf(
				"UPDATE uo_game SET name=%d WHERE game_id=%d",
				(int)$nameId,
				(int)$gameId
			);
			$result = DBQuery($query);
		} else {
			$query = sprintf(
				"UPADATE uo_scheduling_name SET 
				name='%s' WHERE scheduling_id=%d",
				DBEscapeString($name),
				(int)$gameinfo['name']
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
	$scores = array();
	$changed = array();
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
			} else if ($score['home'] !== "" && $score['visitor'] !== "") {
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

	if ($ok_clear > 0)
		$html .= "<p>" . sprintf(_("Results cleared: %s."), $ok_clear) . "</p>";
	if ($ok_set > 0)
		$html .= "<p>" . sprintf(_("Results changed: %s."), $ok_set) . "</p>";
	if ($error_clear + $error_set > 0)
		$html .= "<p>" . sprintf(_("Errors: %s."), ($error_clear + $error_set)) . "</p>";

	foreach ($changed as $poolId => $ok) {
		if ($ok > 0) {
			ResolvePoolStandings($poolId);
			PoolResolvePlayed($poolId);
		}
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
			(int) $gameId
		);

		$result = DBQuery($query);


		$query = sprintf(
			"DELETE FROM uo_game_pool
        WHERE game='%d' AND timetable=1",
			(int) $gameId
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
			(int) $poolId
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
		$query = sprintf(
			"DELETE FROM uo_game_pool
        WHERE pool=%d",
			$poolId
		);
		$result = DBQuery($query);

		$query = sprintf(
			"DELETE FROM uo_game 
        WHERE pool=%d",
			$poolId
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
		"SELECT pool_id
		FROM uo_pool
		WHERE series='%d'",
		(int) $poolId
	);
	return DBQueryToValue($query);
}

function UnscheduledGameInfo($teams = array())
{
	if (count($teams) == 0) {
		$query = "SELECT game_id FROM uo_game WHERE reservation IS NULL AND time IS NULL";
	} else {
		$fetch = array();
		foreach ($teams as $teamid) {
			$fetch[] = (int)$teamid;
		}
		$query = "SELECT game_id FROM uo_game WHERE reservation IS NULL AND time IS NULL AND
			hometeam IN (" . implode(",", $fetch) . ") AND visitorteam IN (" . implode(",", $fetch) . ")";
	}
	$result = DBQuery($query);

	$ret = array();
	while ($row = mysqli_fetch_row($result)) {
		$ret[$row[0]] = GameInfo($row[0]);
	}
	return $ret;
}

function UnscheduledPoolGameInfo($poolId)
{

	$query = sprintf(
		"SELECT g.game_id FROM uo_game g
		WHERE g.reservation IS NULL AND g.time IS NULL AND g.pool=%d
		ORDER BY g.game_id",
		(int)$poolId
	);

	$result = DBQuery($query);

	$ret = array();
	while ($row = mysqli_fetch_row($result)) {
		$ret[$row[0]] = GameInfo($row[0]);
	}
	return $ret;
}

function UnscheduledSeriesGameInfo($seriesId)
{

	$query = sprintf(
		"SELECT g.game_id FROM uo_game g
		LEFT JOIN uo_pool pool ON (pool.pool_id=g.pool)
		WHERE g.reservation IS NULL AND g.time IS NULL AND pool.series=%d
		ORDER BY pool.ordering, g.game_id",
		(int)$seriesId
	);

	$result = DBQuery($query);

	$ret = array();
	while ($row = mysqli_fetch_row($result)) {
		$ret[$row[0]] = GameInfo($row[0]);
	}
	return $ret;
}

function UnscheduledSeasonGameInfo($seasonId)
{

	$query = sprintf(
		"SELECT g.game_id FROM uo_game g
		LEFT JOIN uo_pool pool ON (pool.pool_id=g.pool)
		LEFT JOIN uo_series ser ON (ser.series_id=pool.series)
		WHERE g.reservation IS NULL AND g.time IS NULL AND ser.season='%s'
		ORDER BY ser.ordering, pool.ordering, g.game_id",
		DBEscapeString($seasonId)
	);

	$result = DBQuery($query);
	$ret = array();
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
			(int)$reservation,
			(int)$gameId
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
			(int)$gameId
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
		(int)$gameId
	);
	$count = DBQueryToValue($query);

	if ($count == 0) {
		$query = sprintf(
			"SELECT count(*) FROM uo_played WHERE game=%d",
			(int)$gameId
		);
		$count = DBQueryToValue($query);
		if ($count == 0) {
			$query = sprintf(
				"SELECT count(*) FROM uo_gameevent WHERE game=%d",
				(int)$gameId
			);
			$count = DBQueryToValue($query);
			if ($count == 0) {
				$query = sprintf(
					"SELECT homescore,visitorscore FROM uo_game WHERE game_id=%d",
					(int)$gameId
				);
				$row = DBQueryToRow($query);

				return (intval($row['homescore']) + intval($row['visitorscore'])) == 0;
			} else return false; // FIXME test hasstarted?
		} else return false;
	} else return false;
}

function ResultsToCsv($season, $separator)
{

	$query = sprintf(
		"SELECT kj.name as Home, vj.name as Away, 
			homescore AS HomeScores, visitorscore AS AwayScores, ser.name AS Division, pool.name AS Pool
		FROM uo_game pp 
			left join uo_reservation res on (pp.reservation=res.id) 
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_series ser on (ser.series_id=pool.series)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
		WHERE ser.season='%s' AND (hasstarted>0)
		ORDER BY ser.ordering, pool.ordering, pp.time ASC, pp.game_id ASC",
		DBEscapeString($season)
	);

	$result = DBQuery($query);
	return ResultsetToCsv($result, $separator);
}

function SpiritTable($gameinfo, $points, $categories, $home, $wide = true)
{
	$home = $home ? "home" : "vis";
	$html = "<table class='spirit-table'>\n";
	$html .= "<tr>";
	if ($wide)
		$html .= "<th style='width:70%;text-align: right;'></th>";
	$vmin = 99999;
	$vmax = -99999;
	foreach ($categories as $cat) {
		if ($vmin > $cat['min'])
			$vmin = $cat['min'];
		if ($vmax < $cat['max'])
			$vmax = $cat['max'];
	}

	if ($vmax - $vmin < 12) {
		$colspan = ($wide ? 3 : 2);
		$html .= "<th></th></tr>\n";

		foreach ($categories as $cat) {
			if ($cat['index'] == 0)
				continue;
			$id = $cat['category_id'];
			$html .= "<tr>";
			if ($wide)
				$html .= "<td style='width:70%'>";
			else
				$html .= "<td colspan='$colspan'>";
			$html .= _($cat['text']);
			$html .= "<input type='hidden' id='" . $home . "valueId$id' name='" . $home . "valueId[]' value='$id'/>";
			if ($wide)
				$html .= "</td>";
			else
				$html .= "</td></tr>\n<tr>";

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
			if ($cat['index'] == 0)
				continue;
			$id = $cat['category_id'];
			$html .= "<tr>";
			$html .= "<td style='width:70%'>" . _($cat['text']);
			$html .= "<input type='hidden' id='" . $home . "valueId$id' name='" . $home . "valueId[]' value='$id'/></td>";
			$value = isset($points[$id]) ? $points[$id] : '';
			$html .= "<td class='center'>
      <input type='text' id='" . $home . "cat" . $id . "_0' name='" . $home . "cat$id' value='" . $value . "'/></td>";
			$html .= "</tr>\n";
		}
	}


	$html .= "<tr>";
	$html .= "<td class='highlight' colspan='$colspan'>" . _("Total points");
	$total = SpiritTotal($points, $categories);
	if (!isset($total))
		$total = ": -";
	else
		$html .= ": $total";
	$html .= "</tr>";

	$html .= "</table>\n";

	return $html;
}

function isGameLive($gameId) {

  $query = sprintf("SELECT islive FROM uo_game WHERE game_id=%d LIMIT 1",(int) $gameId);

  return (int) DBQueryToValue($query);
}

function GameLiveURL($gameId) {

  $query = sprintf("SELECT liveurl FROM uo_game WHERE game_id=%d LIMIT 1", (int) $gameId);

  $result = DBQueryToValue($query);

  if ($result)
    return filter_var($result,FILTER_VALIDATE_URL);
  else
    return false;
}

function UpdateGameLiveURL($gameId, $url) {
  $gameId = (int) $gameId;
  if (!hasEditGamesRight(GameSeries($gameId))) {
    die('Insufficient rights to edit game');
  }

  $query = sprintf("UPDATE uo_game SET liveurl = '%s' WHERE game_id = %d", DBEscapeString($url), $gameId);

  return DBQuery($query);
}

function isGameOngoing($gameId) {

  $query = sprintf("SELECT isongoing FROM uo_game WHERE game_id=%d LIMIT 1", (int) $gameId);

  return (int) DBQueryToValue($query);
}

function isGamePaused($gameId) {
  
  $query = sprintf("SELECT (isongoing=1 AND timer_pause_start IS NOT NULL) AS ispaused FROM uo_game WHERE game_id=%d LIMIT 1", (int) $gameId);
  
  return (int) DBQueryToValue($query);
}

function GameTimeReset($gameId) {
  $gameId = (int) $gameId;
  if (!hasEditGameEventsRight($gameId)) {
    die('Insufficient rights to edit game events');
  }

  $query = sprintf(
    "UPDATE uo_game SET timer_start=NULL, timer_pause_start=NULL, timer_paused_duration=0, isongoing=0, hasstarted=0 WHERE game_id=%d",
    $gameId
  );

  return DBQuery($query);
}

function GameTimeStart($gameId) {
  $gameId = (int) $gameId;
  if (!hasEditGameEventsRight($gameId)) {
    die('Insufficient rights to edit game events');
  }

  $query = sprintf(
    "UPDATE uo_game SET hasstarted = 1, isongoing = 1, timer_start = %d, timer_pause_start = NULL, timer_paused_duration = 0 WHERE game_id = %d",
    time(),
    $gameId
  );

  return DBQuery($query);
}

function GameTimePause($gameId) {
  $gameId = (int) $gameId;
  if (!hasEditGameEventsRight($gameId)) {
    die('Insufficient rights to edit game events');
  }

  $query = sprintf("UPDATE uo_game SET timer_pause_start = %d 
    WHERE game_id = %d AND isongoing = 1 AND timer_pause_start IS NULL", time(), $gameId);

  return DBQuery($query);
}

function GameTimeResume($gameId) {
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

function GameTimeSetElapsed($gameId, $elapsedSeconds) {
  $gameId = (int) $gameId;
  $elapsedSeconds = max(0, (int) $elapsedSeconds);
  if (!hasEditGameEventsRight($gameId)) {
    die('Insufficient rights to edit game events');
  }

  $query = sprintf(
    "SELECT timer_pause_start, timer_paused_duration FROM uo_game WHERE game_id = %d AND isongoing = 1 AND timer_pause_start IS NOT NULL LIMIT 1",
    $gameId
  );
  $row = DBQueryToRow($query);

  if (!$row || empty($row['timer_pause_start'])) {
    return false;
  }

  $timerStart = (int) $row['timer_pause_start'] - (int) $row['timer_paused_duration'] - $elapsedSeconds;
  $updateQuery = sprintf(
    "UPDATE uo_game SET timer_start = %d WHERE game_id = %d",
    $timerStart,
    $gameId
  );

  return DBQuery($updateQuery);
}

function GameElapsedTime($gameId) {
  $state = GameTimerState($gameId);

  return array("mm" => $state['mm'], "ss" => $state['ss'], "rss" => $state['rss']);
}
