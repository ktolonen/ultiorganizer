<?php 
include_once $include_prefix.'lib/accreditation.functions.php';
include_once $include_prefix.'lib/facebook.functions.php';
include_once $include_prefix.'lib/configuration.functions.php';

function GameSetPools($games) {
	$query = "SELECT DISTINCT pool_id, p.name from uo_game g left join uo_pool p on (g.pool=p.pool_id) WHERE g.game_id in (";
	$query .= implode(",", $games);
	$query .= ") ORDER BY p.ordering ASC";
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$ret = array();
	while ($row = mysql_fetch_assoc($result)){
		$ret[$row['pool_id']] = $row;
	}
	return $ret;
}

function PoolGameSetResults($pool, $games) {
	$query = sprintf("SELECT time, k.name As hometeamname, v.name As visitorteamname, p.*,s.name AS gamename
		FROM uo_game AS p 
		LEFT JOIN uo_team As k ON (p.hometeam=k.team_id) 
		LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
		LEFT JOIN uo_scheduling_name s ON(s.scheduling_id=p.name)
		WHERE p.game_id IN (%s) AND pool=%d",
		mysql_real_escape_string(implode(",", $games)),
		(int)$pool);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return $result;
}

function GameResult($gameId) {
	$query = sprintf("
		SELECT time, k.name As hometeamname, v.name As visitorteamname, k.valid as homevalid, v.valid as visitorvalid, p.*,s.name AS gamename
		FROM uo_game AS p 
		LEFT JOIN uo_team As k ON (p.hometeam=k.team_id) 
		LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
		LEFT JOIN uo_scheduling_name s ON(s.scheduling_id=p.name)
		WHERE p.game_id='%s'",
		mysql_real_escape_string($gameId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
}

function GoalInfo($gameId, $num) {
	$query = sprintf("SELECT m.*, s.profile_id AS assist_accrid, 
		s.firstname AS assistfirstname, s.lastname AS assistlastname,
		t.profile_id AS scorer_accrid,
		t.firstname AS scorerfirstname, t.lastname AS scorerlastname 
		FROM (uo_goal AS m LEFT JOIN uo_player AS s ON (m.assist = s.player_id)) 
		LEFT JOIN uo_player AS t ON (m.scorer=t.player_id)
		WHERE m.game=%d AND m.num=%d",
		(int)$gameId, (int)$num);
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		return $row;
	} else return false;
}

function GameHomeTeamResults($teamId, $poolId) {
	$query = sprintf("
		SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.visitorteam, COALESCE(pm.goals,0) AS scoresheet,
			sn.name AS gamename, g.isongoing, g.hasstarted
			FROM uo_game g 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (g.game_id=pm.game)
			LEFT JOIN uo_scheduling_name sn ON(g.name=sn.scheduling_id)
			WHERE g.hometeam=%d AND g.pool=%d
			GROUP BY g.game_id",
			(int) $teamId,
			(int) $poolId);
	return DBQueryToArray($query);
}

function GameHomePseudoTeamResults($schedulingId, $poolId) {
	$query = sprintf("SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.visitorteam, 
			sn.name AS gamename, g.isongoing, g.hasstarted
			FROM uo_game g 
			LEFT JOIN uo_scheduling_name sn ON(g.name=sn.scheduling_id)
			WHERE g.scheduling_name_home=%d AND g.pool=%d
			GROUP BY g.game_id",
			(int) $schedulingId,
			(int) $poolId);
	return DBQueryToArray($query);
}

function GameVisitorTeamResults($teamId, $poolId) {
	$query = sprintf("
		SELECT g.game_id, g.homescore, g.visitorscore, g.hasstarted, g.hometeam, COALESCE(pm.goals,0) AS scoresheet
			FROM uo_game g 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (g.game_id=pm.game)
			WHERE g.visitorteam=%d AND g.pool=%d AND g.hasstarted>0 AND g.valid=1 AND isongoing=0
			GROUP BY g.game_id",
			(int) $teamId,
			(int) $poolId);
	return DBQueryToArray($query);
}

function GameNameFromId($gameId)
	{
	$query = sprintf("
		SELECT k.name As hometeamname, v.name As visitorteamname 
		FROM (uo_game AS p LEFT JOIN uo_team As k ON (p.hometeam=k.team_id)) LEFT JOIN uo_team AS v ON (p.visitorteam=v.team_id)
		WHERE game_id=%d",
		(int)$gameId);		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_assoc($result);
	return $row['hometeamname'] ." - ". $row['visitorteamname'];
	}
	
function GameSeries($gameId) {
	$query = sprintf("
		SELECT s.series 
		FROM uo_game p left join uo_pool s on (p.pool=s.pool_id)  
		WHERE game_id='%s'",
		mysql_real_escape_string($gameId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	
	return $row[0];
}

function GameRespTeam($gameId) {
	$query = sprintf("
		SELECT respteam 
		FROM uo_game  
		WHERE game_id='%s'",
		(int)$gameId);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	
	return $row[0];
}

/**
 * Returns game admins (scorekeepers) for given game.
 *
 * @param int $gameId uo_game.game_id
 * @return php array of users
 */
function GameAdmins($gameId) {
   $query = sprintf("SELECT u.userid, u.name FROM uo_users u
  			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
  			WHERE SUBSTRING_INDEX(up.value, ':', -1)='%d'
			ORDER BY u.name",
      (int)$gameId);
    return DBQueryToArray($query);
}

function GamePool($gameId) {
	$query = sprintf("
		SELECT pool 
		FROM uo_game  
		WHERE game_id=%d",
		(int)$gameId);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	
	return $row[0];
}

function GameIsFirstOffenceHome($gameId) {
	$query = sprintf("
		SELECT ishome 
		FROM uo_gameevent  
		WHERE game=%d ORDER BY time",
		(int)$gameId);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	if(!mysql_num_rows($result))
		return -1;
		
	$row = mysql_fetch_row($result);
	
	return $row[0];
}

function GameReservation($gameId) {
	$query = sprintf("
		SELECT reservation 
		FROM uo_game  
		WHERE game_id=%d",
		(int)$gameId);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	
	return $row[0];
}

function GameSeason($gameId) {
	$query = sprintf("SELECT ser.season 
		FROM uo_game p LEFT JOIN uo_pool s on (p.pool=s.pool_id)
 			LEFT JOIN uo_series ser ON (s.series=ser.series_id)  
		WHERE game_id=%d",
			(int)$gameId);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	
	return $row[0];
}
	
function GamePlayers($gameId, $teamId){
	$query = sprintf("SELECT p.player_id, pg.num, p.firstname, p.lastname 
		FROM uo_played AS pg 
		LEFT JOIN uo_player AS p ON(pg.player=p.player_id)
		WHERE pg.game=%d AND p.team=%d",
		(int)$gameId,
		(int)$teamId);
		
	return DBQueryToArray($query);
	}

function GameCaptain($gameId, $teamId){
	$query = sprintf("SELECT pg.player, pg.num 
		FROM uo_played AS pg 
		LEFT JOIN uo_player AS p ON(pg.player=p.player_id)
		WHERE pg.captain=1 AND pg.game=%d AND p.team=%d",
		(int)$gameId,
		(int)$teamId);

	return DBQueryToValue($query);
}

function GameAll($limit=50){
  $limit=intval($limit);
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
	
function GamePlayerFromNumber($gameId, $teamId, $number)
	{
	$query = sprintf("
		SELECT p.player_id
		FROM uo_player AS p 
		INNER JOIN (SELECT player, num FROM uo_played WHERE game='%s')
			AS pel ON (p.player_id=pel.player) 
		WHERE p.team='%s' AND pel.num='%s'",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($number));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	if(!mysql_num_rows($result))
		return -1;
		
	$row = mysql_fetch_row($result);
	
	if($row && $row[0])
		return intval($row[0]);
	else
		return -1;
	}

	
function GameTeamScoreBorad($gameId, $teamId)
	{
	$query = sprintf("
		SELECT p.player_id, p.firstname, p.lastname, p.profile_id, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin, 
		(COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, pel.num AS num FROM uo_player AS p 
		LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done 
			FROM uo_goal AS m WHERE m.game='%s' AND m.scorer IS NOT NULL GROUP BY scorer) AS t ON (p.player_id=t.scorer) 
		LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin FROM uo_goal AS m2 
			WHERE m2.game='%s' AND m2.assist IS NOT NULL GROUP BY assist) AS s ON (p.player_id=s.assist) 
		RIGHT JOIN (SELECT player, num FROM uo_played WHERE game='%s') as pel ON (p.player_id=pel.player) 
			WHERE p.team='%s' 
		ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, firstname ASC",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameTeamDefenseBoard($gameId, $teamId)
	{
	$query = sprintf("
		SELECT p.player_id, p.firstname, p.lastname, p.profile_id, COALESCE(t.done,0) AS done, pel.num AS num FROM uo_player AS p 
		LEFT JOIN (SELECT m.author AS author, COUNT(*) AS done 
			FROM uo_defense AS m WHERE m.game='%s' AND m.author IS NOT NULL GROUP BY author) AS t ON (p.player_id=t.author) 
		RIGHT JOIN (SELECT player, num FROM uo_played WHERE game='%s') as pel ON (p.player_id=pel.player) 
			WHERE p.team='%s' 
		ORDER BY done DESC, lastname ASC, firstname ASC",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameScoreBoard($gameId)
	{
	$query = sprintf("
		SELECT p.profile_id, p.player_id, p.firstname, p.lastname, pj.name AS teamname, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin, 
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
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GameGoals($gameId)
	{
	$query = sprintf("
		SELECT m.*, s.firstname AS assistfirstname, s.lastname AS assistlastname, t.firstname AS scorerfirstname, t.lastname AS scorerlastname 
		FROM (uo_goal AS m LEFT JOIN uo_player AS s ON (m.assist = s.player_id)) 
		LEFT JOIN uo_player AS t ON (m.scorer=t.player_id) 
		WHERE m.game='%s' 
		ORDER BY m.num",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameDefenses($gameId)
	{
	$query = sprintf("
		SELECT m.*, s.firstname AS defenderfirstname, s.lastname AS defenderlastname 
		FROM (uo_defense AS m LEFT JOIN uo_player AS s ON (m.author = s.player_id))
		WHERE m.game='%s' 
		ORDER BY m.num",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}


function GameLastGoal($gameId){
	$query = sprintf("
		SELECT m.*, s.firstname AS assistfirstname, s.lastname AS assistlastname, t.firstname AS scorerfirstname, t.lastname AS scorerlastname 
		FROM (uo_goal AS m LEFT JOIN uo_player AS s ON (m.assist = s.player_id)) 
		LEFT JOIN uo_player AS t ON (m.scorer=t.player_id) 
		WHERE m.game='%s' 
		ORDER BY m.num DESC",
		mysql_real_escape_string($gameId));
		
	return DBQueryToRow($query);
}
	
function GameAllGoals($gameId)
	{
	$query = sprintf("
		SELECT num,time,ishomegoal 
		FROM uo_goal 
		WHERE game='%s' 
		ORDER BY time",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GameEvents($gameId){
	$query = sprintf("
		SELECT time,ishome,type 
		FROM (SELECT time,ishome,'timeout' AS type FROM `uo_timeout` 
			WHERE game='%s' UNION ALL SELECT time,ishome,type FROM uo_gameevent WHERE game='%s') AS tapahtuma 
		WHERE type!='media'
		ORDER BY time ",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId));
		
	return DBQueryToArray($query);
}

function GameMediaEvents($gameId){
	$query = sprintf("
		SELECT u.time, u.ishome, u.type as eventtype, u.info, urls.*
		FROM uo_gameevent u
		LEFT JOIN uo_urls urls ON(u.info=urls.url_id)
		WHERE u.game=%d AND u.type='media'
		ORDER BY time ",
		(int)$gameId);

	return DBQueryToArray($query);
}

function AddGameMediaEvent($gameId, $time, $urlId) {
	if (hasAddMediaRight()){
		$lastnum = DBQueryToValue("SELECT MAX(num) FROM uo_gameevent WHERE game=".intval($gameId));
		$lastnum = intval($lastnum)+1;
		
		$query = sprintf("INSERT INTO uo_gameevent (game,num,ishome,time,type,info)
				VALUES(%d,$lastnum,0,%d,'media',%d)",
			(int)$gameId,
			(int)$time,
			(int)$urlId);
		DBQuery($query);
		return mysql_insert_id();
	} else { die('Insufficient rights to add media'); }	
}

function RemoveGameMediaEvent($gameId, $urlId) {
	if (hasAddMediaRight()){
		$query = sprintf("DELETE FROM uo_gameevent WHERE game=%d AND info=%d",
			(int)$gameId,
			(int)$urlId);
		return DBQuery($query);
	} else { die('Insufficient rights to remove media'); }	
}

function GameTimeouts($gameId)
	{
	$query = sprintf("
		SELECT num,time,ishome 
		FROM uo_timeout 
		WHERE game='%s' 
		ORDER BY time",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GameTurnovers($gameId)
	{
	$query = sprintf("
		SELECT time, ishome 
		FROM uo_gameevent 
		WHERE game='%s' AND type='turnover' 
		ORDER BY time",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GameInfo($gameId) {
	$query = sprintf("SELECT game_id, hometeam, kj.name as hometeamname, visitorteam, vj.name as visitorteamname, pp.pool as pool,
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
		(int)$gameId);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return mysql_fetch_assoc($result);
}

function GameHasStarted($gameInfo) {
   return $gameInfo['hasstarted']>0;
}

function GameName($gameInfo) {
	if($gameInfo['hometeam'] && $gameInfo['visitorteam']){
		return ShortDate($gameInfo['time'])." ".DefHourFormat($gameInfo['time'])." ".$gameInfo['hometeamname']."-".$gameInfo['visitorteamname'];
	}else{
		return ShortDate($gameInfo['time'])." ".DefHourFormat($gameInfo['time'])." ".$gameInfo['phometeamname']."-".$gameInfo['pvisitorteamname'];
	}
}

function GameUpdateResult($gameId, $home, $away) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("UPDATE uo_game SET homescore='%s', visitorscore='%s', isongoing='1', hasstarted='1' WHERE game_id='%s'",
			mysql_real_escape_string($home),
			mysql_real_escape_string($away),
			mysql_real_escape_string($gameId));
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameSetResult($gameId, $home, $away) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("UPDATE uo_game SET homescore='%s', visitorscore='%s', isongoing='0', hasstarted='2' WHERE game_id='%s'",
			mysql_real_escape_string($home),
			mysql_real_escape_string($away),
			mysql_real_escape_string($gameId));
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		if (IsFacebookEnabled()) {
			TriggerFacebookEvent($gameId, "game", 0);
		}
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameSetDefenses($gameId, $home, $away) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("UPDATE uo_game SET homedefenses='%s', visitordefenses='%s' WHERE game_id='%s'",
			mysql_real_escape_string($home),
			mysql_real_escape_string($away),
			mysql_real_escape_string($gameId));
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		if (IsFacebookEnabled()) {
			TriggerFacebookEvent($gameId, "game", 0);
		}
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameClearResult($gameId) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("UPDATE uo_game SET homescore=NULL, visitorscore=NULL, isongoing='0', hasstarted='0' WHERE game_id='%s'",
			mysql_real_escape_string($gameId));
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
//		if (IsFacebookEnabled()) {
//			TriggerFacebookEvent($gameId, "game", 0);
//		}
		return $result;
	} else { die('Insufficient rights to edit game'); }
}


function GameAddPlayer($gameId, $playerId, $number) {
	if (hasEditGamePlayersRight($gameId)) {
		$query = sprintf("INSERT INTO uo_played 
			(game, player, num, accredited) 
			VALUES ('%s', '%s', '%s', %d)
			ON DUPLICATE KEY UPDATE num=%d",
			mysql_real_escape_string($gameId),
			mysql_real_escape_string($playerId),
			mysql_real_escape_string($number),
			(int)isAccredited($playerId),
			mysql_real_escape_string($number));
						
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		$query = sprintf("UPDATE uo_player SET num=%d WHERE player_id=%d", 
			(int)$number, (int)$playerId);
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameAddNewPlayer($gameId, $firstname, $lastname, $accrid, $teamId, $number) {
	if (hasEditGamePlayersRight($gameId)) {
		$query = sprintf("INSERT INTO uo_player (firstname, lastname, team) VALUES ('%s', '%s', %d)",
			mysql_real_escape_string($firstname),
			mysql_real_escape_string($lastname),
			(int)$teamId);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		$playerId = mysql_insert_id();
		
		GameAddPlayer($gameId, $playerId, $number);
	} else { die('Insufficient rights to edit game'); }
}

function GameRemovePlayer($gameId, $playerId) {
	if (hasEditGamePlayersRight($gameId)) {
		$query = sprintf("
			DELETE FROM uo_played 
			WHERE game='%s' AND player='%s'",
			mysql_real_escape_string($gameId),
			mysql_real_escape_string($playerId));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameRemoveAllPlayers($gameId, $playerId) {
	if (hasEditGamePlayersRight($gameId)) {
		$query = sprintf("
			DELETE FROM uo_played
			WHERE game='%s'",
			mysql_real_escape_string($gameId));

		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }

		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameSetPlayerNumber($gameId, $playerId, $number) {
	if (hasEditGamePlayersRight($gameId)) {
		$query = sprintf("
			UPDATE uo_played 
			SET num='%s', accredited=%d 
			WHERE game=%d AND player=%d",
			mysql_real_escape_string($number),
			(int)isAccredited($playerId),
			(int)$gameId,
			(int)$playerId);
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameRemoveAllScores($gameId) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("
			DELETE FROM uo_goal 
			WHERE game='%s'",
			mysql_real_escape_string($gameId));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameRemoveAllDefenses($gameId) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("
			DELETE FROM uo_defense 
			WHERE game='%s'",
			mysql_real_escape_string($gameId));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}


function GameRemoveScore($gameId, $num) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("
			DELETE FROM uo_goal 
			WHERE game='%s' AND num=%d",
			mysql_real_escape_string($gameId),
			(int)$num);
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

/**
 * Add goal to game. Does not update game result!
 * 
 */
function GameAddScore($gameId, $pass, $goal, $time, $number, $hscores, $ascores, $home, $iscallahan) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("
			INSERT INTO uo_goal 
			(game, num, assist, scorer, time, homescore, visitorscore, ishomegoal, iscallahan) 
			VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
			ON DUPLICATE KEY UPDATE 
			assist='%s', scorer='%s', time='%s', homescore='%s', visitorscore='%s', ishomegoal='%s', iscallahan='%s'",
			mysql_real_escape_string($gameId),
			mysql_real_escape_string($number),
			mysql_real_escape_string($pass),
			mysql_real_escape_string($goal),
			mysql_real_escape_string($time),
			mysql_real_escape_string($hscores),
			mysql_real_escape_string($ascores),
			mysql_real_escape_string($home),
			mysql_real_escape_string($iscallahan),
			mysql_real_escape_string($pass),
			mysql_real_escape_string($goal),
			mysql_real_escape_string($time),
			mysql_real_escape_string($hscores),
			mysql_real_escape_string($ascores),
			mysql_real_escape_string($home),
			mysql_real_escape_string($iscallahan));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		if (IsFacebookEnabled()) {
			TriggerFacebookEvent($gameId, "goal", $number);
		}
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameAddDefense($gameId, $player, $home, $caught, $time, $iscallahan, $number) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("
			INSERT INTO uo_defense 
			(game, num, author, time, iscallahan, iscaught, ishomedefense) 
			VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s') 
			ON DUPLICATE KEY UPDATE 
			author='%s', time='%s', iscallahan='%s', iscaught='%s', ishomedefense='%s'",
			mysql_real_escape_string($gameId),
			mysql_real_escape_string($number),
			mysql_real_escape_string($player),
			mysql_real_escape_string($time),
			mysql_real_escape_string($iscallahan),
			mysql_real_escape_string($caught),
			mysql_real_escape_string($home),
			mysql_real_escape_string($player),
			mysql_real_escape_string($time),
			mysql_real_escape_string($iscallahan),
			mysql_real_escape_string($caught),
			mysql_real_escape_string($home));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		/*if (IsFacebookEnabled()) {
			TriggerFacebookEvent($gameId, "goal", $number);
		}*/
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameAddScoreEntry($uo_goal) {
	if (hasEditGameEventsRight($uo_goal['game'])) {
		$query = sprintf("
			INSERT INTO uo_goal 
			(game, num, assist, scorer, time, homescore, visitorscore, ishomegoal, iscallahan) 
			VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
			mysql_real_escape_string($uo_goal['game']),
			mysql_real_escape_string($uo_goal['num']),
			mysql_real_escape_string($uo_goal['assist']),
			mysql_real_escape_string($uo_goal['scorer']),
			mysql_real_escape_string($uo_goal['time']),
			mysql_real_escape_string($uo_goal['homescore']),
			mysql_real_escape_string($uo_goal['visitorscore']),
			mysql_real_escape_string($uo_goal['ishomegoal']),
			mysql_real_escape_string($uo_goal['iscallahan']));
			
		$result = mysql_query($query);

		if (!$result) { die('Invalid query: ' . mysql_error()); }
		if (IsFacebookEnabled()) {
			TriggerFacebookEvent($gameId, "goal", $number);
		}
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameRemoveAllTimeouts($gameId) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("
			DELETE FROM uo_timeout 
			WHERE game='%s'",
			mysql_real_escape_string($gameId));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameAddTimeout($gameId, $number, $time, $home) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("
			INSERT INTO uo_timeout 
			(game, num, time, ishome) 
			VALUES ('%s', '%s', '%s', '%s')",
			mysql_real_escape_string($gameId),
			mysql_real_escape_string($number),
			mysql_real_escape_string($time),
			mysql_real_escape_string($home));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameGetSpiritPoints($gameId, $teamId) {
  $query = sprintf("SELECT * FROM uo_spirit WHERE game_id=%d AND team_id=%d",
  		(int)$gameId,
  		(int)$teamId);
  return DBQueryToRow($query);
}

function GameSetSpiritPoints($gameId, $teamId, $home, $points ) {
	if (hasEditGameEventsRight($gameId)) {
	  
	  $query = sprintf("SELECT * FROM uo_spirit WHERE game_id=%d AND team_id=%d",
	  		(int)$gameId,
	        (int)$teamId);

	  $update = DBQueryRowCount($query);
	  
	  if($update){
	    $query = sprintf("UPDATE uo_spirit SET cat1=%d,cat2=%d,cat3=%d,cat4=%d,cat5=%d  
        	        WHERE game_id=%d AND team_id=%d",
	            (int)$points[0],
	            (int)$points[1],
	            (int)$points[2],
	            (int)$points[3],
  	            (int)$points[4],
	    		(int)$gameId,
	    		(int)$teamId);
	  }else{
	    $query = sprintf("INSERT INTO uo_spirit (game_id,team_id,cat1,cat2,cat3,cat4,cat5) VALUES (%d,%d,%d,%d,%d,%d,%d)",
	            (int)$gameId,
	            (int)$teamId,
	            (int)$points[0],
	    		(int)$points[1],
	    		(int)$points[2],
	    		(int)$points[3],
	    		(int)$points[4]);
	  }
	  DBQuery($query);
	  $total =  array_sum($points);
	  $query = sprintf("UPDATE uo_game SET ");
	  
	  if($home){
	    $query .= sprintf("homesotg=%d ",
	    		(int)$total);
	  }else{
	    $query .= sprintf("visitorsotg=%d ",
	    		(int)$total);
	  }		
	    $query .= sprintf("WHERE game_id=%d",(int)$gameId);
		
		return DBQuery($query);
	} else { die('Insufficient rights to edit game'); }
}

function GameSetScoreSheetKeeper($gameId, $name) {
	if (hasEditGameEventsRight($gameId)) {
	$query = sprintf("
		UPDATE uo_game 
		SET official='%s' 
		WHERE game_id='%s'",
		mysql_real_escape_string($name),
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	} else { die('Insufficient rights to edit game'); }
}


function GameSetHalftime($gameId, $time) {
	if (hasEditGameEventsRight($gameId)) {
		$query = sprintf("
			UPDATE uo_game 
			SET halftime='%s' 
			WHERE game_id='%s'",
			mysql_real_escape_string($time),
			mysql_real_escape_string($gameId));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

function GameSetCaptain($gameId, $teamId, $playerId) {
	if (hasEditGameEventsRight($gameId)) {
		
		$captain = GameCaptain($gameId, $teamId);
		
		if($captain!=$playerId){
			$query = sprintf("
				UPDATE uo_played 
				SET captain=0 
				WHERE game=%d AND player=%d",
				(int)$gameId,
				(int)$captain);
			
			DBQuery($query);
			
			$query = sprintf("
				UPDATE uo_played 
				SET captain=1 
				WHERE game=%d AND player=%d",
				(int)$gameId,
				(int)$playerId);
				
			DBQuery($query);
		}
	} else { die('Insufficient rights to edit game'); }
}

function GameSetStartingTeam($gameId, $home) {
	if (hasEditGameEventsRight($gameId)) {
	     if ($home == NULL) {
		$query = sprintf("DELETE FROM uo_gameevent WHERE game=%d AND type='offence'",
			(int)$gameId);

		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }

		return $result;
	   } else {
		$query = sprintf("INSERT INTO uo_gameevent (game, num, time, type, ishome) VALUES (%d, 0, 0, 'offence', %d)
			ON DUPLICATE KEY UPDATE ishome='%d'",
			(int)$gameId,
			(int)$home,
			(int)$home);
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }

		return $result;
	   }
	} else { die('Insufficient rights to edit game'); }
}

function AddGame($params) {
	$poolinfo = PoolInfo($params['pool']);
	if (hasEditGamesRight($poolinfo['series'])) {
		$query = sprintf("
			INSERT INTO uo_game
			(hometeam, visitorteam, reservation, time, pool, valid, respteam) 
			VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s')",
			mysql_real_escape_string($params['hometeam']),
			mysql_real_escape_string($params['visitorteam']),
			mysql_real_escape_string($params['reservation']),
			mysql_real_escape_string($params['time']),
			mysql_real_escape_string($params['pool']),
			mysql_real_escape_string($params['valid']),
			mysql_real_escape_string($params['respteam']));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	
		$id = mysql_insert_id();
		$query = sprintf("
			INSERT INTO uo_game_pool
			(game, pool, timetable) 
			VALUES ('%s', '%s', 1)",
			mysql_real_escape_string($id),
			mysql_real_escape_string($params['pool']));
		
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		Log1("game","add",$id);
		return $id;
	} else { die('Insufficient rights to add game'); }
}

function SetGame($gameId, $params)	{
	$poolinfo = PoolInfo($params['pool']);
	if (hasEditGamesRight($poolinfo['series'])) {
		
		foreach($params as $key => $param) {
			if (!empty($param)) {
				$query = sprintf("
					UPDATE uo_game SET ".$key."='%s' 
					WHERE game_id='%s'\n",
					mysql_real_escape_string($param),
					mysql_real_escape_string($gameId));
					
					$result = DBQuery($query);
			}
		}
		
		
		if(!empty($params['respteam'])){
				$query = sprintf("UPDATE uo_game SET respteam=%d
					WHERE game_id=%d",
					(int)$params['respteam'],
					(int)$gameId);
			
			DBQuery($query);
		}else{
			$query = sprintf("UPDATE uo_game SET respteam=NULL
					WHERE game_id=%d",
					(int)$gameId);
			
			DBQuery($query);
		}
		
		if(!empty($params['name'])){
			$query = sprintf("INSERT INTO uo_scheduling_name 
				(name) VALUES ('%s')",
			mysql_real_escape_string($params['name']));
		
			$nameId = DBQueryInsert($query);
			
			$query = sprintf("UPDATE uo_game SET
					name=%d	WHERE game_id=%d",
				(int)$nameId,
				(int)$gameId);
			DBQuery($query);
		}
		
		return $result;
	} else { die('Insufficient rights to edit game'); }
}

/**
 * Swap home and visitor teams and results.
 */
function GameChangeHome($gameId) {
	$series = GameSeries($gameId);
	if (hasEditGamesRight($series)) {

	  $query = sprintf("SELECT hometeam,visitorteam,respteam, homescore,visitorscore, homesotg, visitorsotg, scheduling_name_home, scheduling_name_visitor FROM uo_game
					WHERE game_id=%d",
					(int)$gameId);
	  $game = DBQueryToRow($query);
					
      $query = sprintf("UPDATE uo_game SET hometeam=%d,visitorteam=%d,homescore=%d,visitorscore=%d, homesotg=%d, visitorsotg=%d, scheduling_name_home=%d, scheduling_name_visitor=%d
					WHERE game_id=%d",
                    (int) $game['visitorteam'],
                    (int) $game['hometeam'],
                    (int) $game['visitorscore'],
                    (int) $game['homescore'],
                    (int) $game['visitorsotg'],
                    (int) $game['homesotg'],
                    (int) $game['scheduling_name_visitor'],
                    (int) $game['scheduling_name_home'],
					(int)$gameId);
			
			DBQuery($query);
		if($game['hometeam']==$game['respteam']){
		   $query = sprintf("UPDATE uo_game SET respteam=%d	WHERE game_id=%d",
                    (int) $game['visitorteam'],
					(int)$gameId);
		  DBQuery($query);
		}
	} else { die('Insufficient rights to delete game'); }
}

function GameChangeName($gameId, $name){
  $gameinfo = GameInfo($gameId);
  if (hasEditGamesRight($gameinfo['series'])) {
    if(empty($gameinfo['name'])){
		$query = sprintf("INSERT INTO uo_scheduling_name 
				(name) VALUES ('%s')",
		        mysql_real_escape_string($name));
		$nameId = DBQueryInsert($query);
			
		$query = sprintf("UPDATE uo_game SET name=%d WHERE game_id=%d",
				(int)$nameId,
				(int)$gameId);
		$result = DBQuery($query);
	}else{
	  $query = sprintf("UPADATE uo_scheduling_name SET 
				name='%s' WHERE scheduling_id=%d",
		        mysql_real_escape_string($name),
		        (int)$gameinfo['name']);
      $result = DBQuery($query);
	}
    return $result;
  } else { die('Insufficient rights to edit game'); }
}

function DeleteGame($gameId) {
	$series = GameSeries($gameId);
	if (hasEditGamesRight($series)) {
		Log2("game","delete",GameNameFromId($gameId));
		$query = sprintf("DELETE FROM uo_game WHERE game_id='%s'",
		mysql_real_escape_string($gameId));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	
		$query = sprintf("DELETE FROM uo_game_pool 
				WHERE game='%s' AND timetable=1",
			mysql_real_escape_string($gameId));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to delete game'); }
}

function DeleteMovedGame($gameId,$poolId) {
	$series = GameSeries($gameId);
	if (hasEditGamesRight($series)) {
		Log2("game","delete",GameNameFromId($gameId),$poolId,"Delete moved Game");
		$query = sprintf("DELETE FROM uo_game_pool 
				WHERE (game='%s' AND pool='%s' AND timetable='0')",
			mysql_real_escape_string($gameId),
			mysql_real_escape_string($poolId));
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		return $result;
	} else { die('Insufficient rights to delete game'); }
}

function UnscheduledGameInfo($teams=array()) {
	if (count($teams) == 0) {
		$query = "SELECT game_id FROM uo_game WHERE reservation IS NULL AND time IS NULL";
	} else {
		$fetch = array();
		foreach($teams as $teamid) {
			$fetch[] = (int)$teamid;
		}
		$query = "SELECT game_id FROM uo_game WHERE reservation IS NULL AND time IS NULL AND
			hometeam IN (".implode(",", $fetch).") AND visitorteam IN (".implode(",", $fetch).")";
	}
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$ret = array();
	while ($row = mysql_fetch_row($result)) {
		$ret[$row[0]] = GameInfo($row[0]);	
	}
	return $ret;
}

function UnscheduledPoolGameInfo($poolId) {

	$query = sprintf("SELECT game_id FROM uo_game 
		WHERE reservation IS NULL AND time IS NULL AND pool=%d
		ORDER BY game_id",
		(int)$poolId);
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$ret = array();
	while ($row = mysql_fetch_row($result)) {
		$ret[$row[0]] = GameInfo($row[0]);	
	}
	return $ret;
}

function UnscheduledSeriesGameInfo($seriesId) {

	$query = sprintf("SELECT game_id FROM uo_game 
		LEFT JOIN uo_pool pool ON(pool.pool_id=pool)
		WHERE reservation IS NULL AND time IS NULL AND pool.series=%d
		ORDER BY pool.ordering, game_id",
		(int)$seriesId);
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$ret = array();
	while ($row = mysql_fetch_row($result)) {
		$ret[$row[0]] = GameInfo($row[0]);	
	}
	return $ret;
}

function UnscheduledSeasonGameInfo($seasonId) {

	$query = sprintf("SELECT game_id FROM uo_game 
		LEFT JOIN uo_pool pool ON(pool.pool_id=pool)
		LEFT JOIN uo_series ser ON(ser.series_id=series)
		WHERE reservation IS NULL AND time IS NULL AND ser.season='%s'
		ORDER BY ser.ordering, pool.ordering, game_id",
		mysql_real_escape_string($seasonId));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$ret = array();
	while ($row = mysql_fetch_row($result)) {
		$ret[$row[0]] = GameInfo($row[0]);	
	}
	return $ret;
}

function ScheduleGame($gameId, $epoc, $reservation) {
	if (hasEditGamesRight(GameSeries($gameId))) {
		$query = sprintf("UPDATE uo_game SET time='%s', reservation=%d WHERE game_id=%d",
			EpocToMysql($epoc), (int)$reservation, (int)$gameId);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	} else { die('Insufficient rights to schedule game'); }
}

function UnScheduleGame($gameId) {
	if (hasEditGamesRight(GameSeries($gameId))) {
		$query = sprintf("UPDATE uo_game SET time=NULL, reservation=NULL WHERE game_id=%d",
			(int)$gameId);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	} else { die('Insufficient rights to schedule game'); }
}

function ClearReservation($reservationId) {
	$result = ReservationGames($reservationId);
	while ($row = mysql_fetch_assoc($result)) {
		if (hasEditGamesRight(GameSeries($row['game_id']))) {
			UnScheduleGame($row['game_id']);
		} // else ignore games not managed by user
	} 
}

function CanDeleteGame($gameId) {
	$query = sprintf("SELECT count(*) FROM uo_goal WHERE game=%d",
		(int)$gameId);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if (!$row = mysql_fetch_row($result)) return false;
	if ($row[0] == 0) {
		$query = sprintf("SELECT count(*) FROM uo_played WHERE game=%d",
			(int)$gameId);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		if (!$row = mysql_fetch_row($result)) return false;
		if ($row[0] == 0) {
			$query = sprintf("SELECT count(*) FROM uo_gameevent WHERE game=%d",
				(int)$gameId);
			$result = mysql_query($query);
			if (!$result) { die('Invalid query: ' . mysql_error()); }
			if (!$row = mysql_fetch_row($result)) return false;
			if ($row[0] == 0) {
				$query = sprintf("SELECT homescore,visitorscore FROM uo_game WHERE game_id=%d",
					(int)$gameId);
				$result = mysql_query($query);
				if (!$result) { die('Invalid query: ' . mysql_error()); }
				if (!$row = mysql_fetch_row($result)) return false;
				return (intval($row[0])+intval($row[1]))==0;
			} else return false; // FIXME test hasstarted?
		} else return false;
	} else return false;
}

function ResultsToCsv($season,$separator){

	$query = sprintf("SELECT kj.name as Home, vj.name as Away, 
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
		mysql_real_escape_string($season));
		
    $result = DBQuery($query);
	return ResultsetToCsv($result, $separator);
}
?>
