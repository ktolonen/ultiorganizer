<?php
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/player.functions.php';
include_once $include_prefix . 'lib/image.functions.php';
include_once $include_prefix . 'lib/url.functions.php';
include_once $include_prefix . 'lib/common.functions.php';

function TeamPlayerArray($teamId)
{
  $ret = array();
  if ($result = TeamPlayerList($teamId)) {
    while ($row = mysqli_fetch_assoc($result)) {
      $ret["" . $row['player_id']] = $row['firstname'] . " " . $row['lastname'];
    }
  }
  return $ret;
}

function TeamPlayerAccreditationArray($teamId)
{
  $ret = array();
  if ($result = TeamPlayerList($teamId)) {
    while ($row = mysqli_fetch_assoc($result)) {
      $ret["" . $row['accreditation_id']] = $row['firstname'] . " " . $row['lastname'];
    }
  }
  return $ret;
}

function TeamPlayerList($teamId)
{
  $query = sprintf(
    "SELECT player_id, firstname, lastname, num, accredited, accreditation_id, profile_id FROM uo_player WHERE team = %d ORDER BY lastname ASC, firstname ASC",
    (int)$teamId
  );
  return DBQuery($query);
}

function TeamName($teamId)
{
  $query = sprintf(
    "SELECT name FROM uo_team WHERE team_id='%s'",
    DBEscapeString($teamId)
  );
  $row = DBQueryToRow($query);
  $name = isset($row["name"]) ? $row["name"] : "";
  return $name;
}

function TeamPseudoName($pteamId)
{
  $query = sprintf(
    "SELECT name FROM uo_scheduling_name WHERE scheduling_id=%d",
    (int)$pteamId
  );
  return DBQueryToValue($query);
}

function TeamInfo($teamId)
{
  $query = sprintf(
    "SELECT team.name, team.club, club.name AS clubname, team.pool, pool.name AS poolname, ser.name AS seriesname,
		team.series, ser.type, ser.season, s.name AS seasonname, team.abbreviation, team.country, c.name AS countryname, c.flagfile
		FROM uo_team team 
		LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id) 
		LEFT JOIN uo_series ser ON (ser.series_id=team.series)
		LEFT JOIN uo_season s ON (ser.season=s.season_id)
		LEFT JOIN uo_club club ON (team.club=club.club_id)
		LEFT JOIN uo_country c ON (team.country=c.country_id)
		WHERE team.team_id = '%s'",
    DBEscapeString($teamId)
  );

  return DBQueryToRow($query);
}

function Teams($filter = null, $ordering = null)
{
  if (!isset($ordering)) {
    $ordering = array("season.starttime" => "ASC", "series.ordering" => "ASC", "pool.ordering" => "ASC", "team.rank" => "ASC", "team.name" => "ASC");
  }
  $tables = array("uo_team" => "team", "uo_pool" => "pool", "uo_series" => "series", "uo_season" => "season");
  $orderby = CreateOrdering($tables, $ordering);
  $where = CreateFilter($tables, $filter);
  $query = "SELECT team_id, team.name, series.name as seriesname, pool.name as poolname, season.name as seasonname
		FROM uo_team team LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id)
		LEFT JOIN uo_series series ON (team.series=series.series_id)
		LEFT JOIN uo_season season ON (series.season=season.season_id)
		$where $orderby";
  return DBQuery(trim($query));
}

function TeamListAll($grouped = false, $onlyold = false, $namefilter = "")
{
  if ($grouped) {
    $query = sprintf("SELECT MAX(team.team_id) AS team_id, team.name, ser.name AS seriesname
			FROM uo_team team 
			LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id) 
			LEFT JOIN uo_series ser ON (ser.series_id=pool.series)
			LEFT JOIN uo_season season ON (ser.season=season.season_id)");
    if ($onlyold) {
      $query .= sprintf("RIGHT JOIN uo_season_stats ss ON(ser.season=ss.season)");
    }
    if (!empty($namefilter) && $namefilter != "ALL") {
      if ($namefilter == "#") {
        $query .= " WHERE UPPER(team.name) REGEXP '^[0-9]'";
      } else {
        $query .= " WHERE UPPER(team.name) LIKE '" . DBEscapeString($namefilter) . "%'";
      }
    }

    $query .= sprintf(" GROUP BY team.name, ser.name
			ORDER BY team.name, ser.name");
  } else {
    $query = sprintf("SELECT team.team_id, team.name, team.club, club.name AS clubname, team.pool, pool.name AS poolname, ser.name AS seriesname,
			team.series, ser.type, ser.season, season.name AS seasonname, team.country, c.flagfile
			FROM uo_team team 
			LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id) 
			LEFT JOIN uo_series ser ON (ser.series_id=pool.series)
			LEFT JOIN uo_club club ON (team.club=club.club_id)
			LEFT JOIN uo_country c ON (team.country=c.country_id)
			LEFT JOIN uo_season season ON (ser.season=season.season_id)");
    if ($onlyold) {
      $query .= sprintf("RIGHT JOIN uo_season_stats ss ON(ser.season=ss.season)");
    }
    if (!empty($namefilter) && $namefilter != "ALL") {
      if ($namefilter == "#") {
        $query .= " WHERE UPPER(team.name) REGEXP '^[0-9]'";
      } else {
        $query .= " WHERE UPPER(team.name) LIKE '" . DBEscapeString($namefilter) . "%'";
      }
    }

    $query .= sprintf(" ORDER BY team.name, ser.name, club.name, pool.name");
  }
  return DBQuery($query);
}

function TeamProfile($teamId)
{
  $query = sprintf(
    "SELECT tp.team_id, tp.captain, tp.coach, tp.story, tp.achievements, tp.profile_image
		FROM uo_team_profile tp 
		WHERE tp.team_id = '%s'",
    DBEscapeString($teamId)
  );

  return  DBQueryToRow($query);
}

function TeamFullInfo($teamId)
{
  $query = sprintf(
    "SELECT pj.*, club.name as clubname, ps.name AS lastname, pjs.rank AS poolrank, pjs.activerank
		FROM uo_team pj 
		LEFT JOIN uo_pool ps ON (pj.pool=ps.pool_id) 
		LEFT JOIN uo_team_pool pjs ON (pjs.team=pj.team_id)
		LEFT JOIN uo_club club ON (pj.club=club.club_id)
		WHERE pj.team_id = '%s'",
    DBEscapeString($teamId)
  );

  return DBQueryToRow($query);
}

function TeamPoolInfo($teamId, $poolId)
{
  $query = sprintf(
    "SELECT pj.*, club.name as clubname, ps.name AS lastname, pjs.rank AS poolrank, pjs.activerank
		FROM uo_team pj 
		LEFT JOIN uo_team_pool pjs ON (pjs.team=pj.team_id)
		LEFT JOIN uo_pool ps ON (pjs.pool=ps.pool_id) 		
		LEFT JOIN uo_club club ON (pj.club=club.club_id)
		WHERE pj.team_id = '%s' AND ps.pool_id='%s'",
    DBEscapeString($teamId),
    DBEscapeString($poolId)
  );

  return DBQueryToRow($query);
}

function TeamPlayedSeasons($name, $type)
{
  $query = sprintf(
    "SELECT pj.team_id, ps.pool_id, ser.season as season_id
		FROM uo_team pj
		LEFT JOIN uo_pool ps ON (pj.pool=ps.pool_id)
		LEFT JOIN uo_series ser ON (ps.series=ser.series_id) 
		WHERE pj.name='%s' AND ser.type='%s' 
		ORDER BY season_id, pool",
    DBEscapeString($name),
    DBEscapeString($type)
  );

  return DBQuery($query);
}

function TeamSeason($teamId)
{
  $query = sprintf(
    "SELECT ser.season as season FROM uo_team as team
				left join uo_series as ser on (team.series = ser.series_id) WHERE team_id=%d",
    (int)$teamId
  );

  return DBQueryToValue($query);
}

function TeamComingGames($teamId, $placeId)
{
  $query = sprintf(
    "
		SELECT Kj.name AS hometeamname, Vj.name As visitorteamname, p.time, p.game_id, p.homescore, 
  		p.visitorscore, p.hasstarted, Kj.team_id AS kId, Vj.team_id AS vId  
		FROM ((uo_game p INNER JOIN uo_team AS Kj ON (p.hometeam=Kj.team_id)) 
		INNER JOIN uo_team AS Vj ON (p.visitorteam=Vj.team_id)) 
		WHERE (p.reservation='%s') AND (p.hometeam='%s' OR p.visitorteam='%s') 
		ORDER BY time ASC",
    DBEscapeString($placeId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQuery($query);
}

function TeamTournamentGames($teamId, $reservationId)
{
  $query = sprintf(
    "
		SELECT Kj.name AS hometeamname, Vj.name AS visitorteamname, p.time, p.homescore, p.visitorscore, 
  			p.hasstarted, p.game_id, Kj.team_id AS kId, Vj.team_id AS vId,
			p.game_id IN (SELECT DISTINCT game FROM uo_goal) As goals		
		FROM uo_game AS p, uo_team AS Kj, uo_team AS Vj 
		WHERE p.hometeam = Kj.team_id And p.visitorteam = Vj.team_id AND p.reservation = '%s' 
			AND (p.visitorteam = '%s' OR p.hometeam = '%s') AND (time < Now()) 
		ORDER BY time ASC",
    DBEscapeString($reservationId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQuery($query);
}

function TeamGames($teamId)
{
  $defense_str = " ";
  if (ShowDefenseStats()) {
    $defense_str = ",pp.homedefenses,pp.visitordefenses ";
  }
  $query = sprintf(
    "SELECT pp.game_id, pp.hometeam, pp.visitorteam, pp.homescore, pp.visitorscore, 
  				pp.hasstarted, pp.pool, ser.season AS season_id, ps.name, ser.name AS seriesname, pjs.activerank" . $defense_str .
      "FROM uo_game pp 
				LEFT JOIN uo_pool ps ON (ps.pool_id=pp.pool)
				LEFT JOIN uo_series ser ON (ps.series=ser.series_id)
				LEFT JOIN uo_team_pool pjs ON(pp.pool=pjs.pool AND pjs.team='%s') WHERE pp.valid=true 
					AND (pp.visitorteam='%s' OR pp.hometeam='%s') AND (pp.hasstarted>0)
				ORDER BY pp.pool",
    DBEscapeString($teamId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQuery($query);
}

function SchedulingNameByMoveTo($topool, $torank)
{
  $query = sprintf(
    "SELECT sn.scheduling_id, sn.name
			FROM uo_moveteams m 
			LEFT JOIN uo_scheduling_name sn ON (sn.scheduling_id = m.scheduling_id)
			WHERE m.topool=%d AND m.torank=%d",
    (int) $topool,
    (int) $torank
  );

  return DBQueryToRow($query);
}


function TeamSerieGames($teamId, $serieId)
{
  $query = sprintf(
    "
			SELECT pp.game_id, pp.hometeam, pp.visitorteam, pp.homescore, 
			pp.visitorscore, pp.hasstarted, pp.time
			FROM uo_game pp 
			WHERE pp.pool='%s' AND pp.valid=true AND (pp.visitorteam='%s' OR pp.hometeam='%s') 
			ORDER BY pp.time ASC",
    DBEscapeString($serieId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQuery($query);
}

function TeamPoolCountBYEs($teamId, $poolId)
{  // counts how many BYEs a team had in its previous games of that pool (possibly taken over from previous pools)
  $query = sprintf(
    "
			SELECT count(pp.game_id)
			FROM uo_game pp 
			RIGHT JOIN uo_game_pool pps ON(pps.game=pp.game_id)
			LEFT JOIN uo_team hometeam ON (pp.hometeam=hometeam.team_id)
			LEFT JOIN uo_team visitorteam ON (pp.visitorteam=visitorteam.team_id)
			WHERE pps.pool='%s' AND ((pp.visitorteam='%s' AND hometeam.valid=2) OR (pp.hometeam='%s' AND visitorteam.valid=2))",
    DBEscapeString($poolId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQueryToValue($query);
}

function TeamPoolGames($teamId, $poolId)
{
  $query = sprintf(
    "
			SELECT pp.game_id, pp.hometeam, pp.visitorteam, pp.homescore, 
			pp.visitorscore, pp.hasstarted, pp.time
			FROM uo_game pp 
			RIGHT JOIN uo_game_pool pps ON(pps.game=pp.game_id)
			WHERE pps.pool='%s' AND pp.valid=true AND (pp.visitorteam='%s' OR pp.hometeam='%s') 
			ORDER BY pp.time ASC",
    DBEscapeString($poolId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQuery($query);
}

function TeamPoolLastGame($teamId, $poolId)
{
  $query = sprintf(
    "
		SELECT pp.game_id, pp.hometeam, pp.visitorteam, pp.homescore, 
		pp.visitorscore, pp.hasstarted, pp.time
		FROM uo_game pp 
		RIGHT JOIN uo_game_pool pps ON(pps.game=pp.game_id)
		WHERE pps.pool='%s' AND pp.valid=true AND pps.timetable=1 AND (pp.visitorteam='%s' OR pp.hometeam='%s') 
		ORDER BY pp.time DESC LIMIT 1",
    DBEscapeString($poolId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQueryToRow($query);
}

function TeamGetNextGames($teamId, $poolId)
{
  $query = sprintf(
    "
		SELECT pp.game_id, pp.hometeam, pp.visitorteam, pp.time, res.fieldname
		FROM uo_game pp 
		RIGHT JOIN uo_game_pool pps ON(pps.game=pp.game_id)
		LEFT JOIN uo_reservation res ON (pp.reservation=res.id)
		WHERE pps.pool='%s' AND pp.valid=true AND pps.timetable=1 AND (pp.visitorteam='%s' OR pp.hometeam='%s') 
		ORDER BY pp.time ASC",
    DBEscapeString($poolId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQueryToRow($query);
}

function TeamPoolGamesLeft($teamId, $poolId)
{
  $query = sprintf(
    "
			SELECT pp.game_id, pp.hometeam, pp.visitorteam, pp.time
			FROM uo_game pp 
			RIGHT JOIN uo_game_pool pps ON(pps.game=pp.game_id)
			WHERE pps.pool='%s' AND pp.valid=true 
				AND (pp.hasstarted=0 OR pp.isongoing=1) AND (hometeam=%d OR visitorteam=%d)					
			ORDER BY pp.time ASC",
    DBEscapeString($poolId),
    DBEscapeString($teamId),
    DBEscapeString($teamId)
  );

  return DBQueryToArray($query);
}

function TeamStanding($teamId, $poolId)
{
  $query = sprintf(
    "SELECT activerank	FROM uo_team_pool
				WHERE pool=%d AND team=%d",
    (int)$poolId,
    (int)$teamId
  );
  return DBQueryToValue($query, true);
}

function TeamMove($teamId, $frompool, $inplayofftree = false)
{

  // no access right check since called after game result is updated
  // to automatically move teams to next pool when the all games are played
  // and order is known.

  //get position to move
  $fromplacing = TeamStanding($teamId, $frompool);

  //get move
  $move = PoolGetMoveToPool($frompool, $fromplacing);

  //if pool is not follower, do not make move
  $poolinfo = PoolInfo($frompool);
  if ($inplayofftree && $poolinfo['follower'] != $move['topool']) {
    return;
  }

  if ($move['ismoved']) {

    $query = sprintf(
      "SELECT team FROM uo_team_pool
  					WHERE pool=%d AND rank=%d",
      (int)$move['topool'],
      (int)$move['torank']
    );

    $team_row = DBQueryToRow($query);
    $team_exist = isset($team_row['team']) ? (int)$team_row['team'] : 0;

    //same team
    if ($team_exist && $team_exist == $teamId) {
      return;
    }

    //different team in same position
    if ($team_exist && $team_exist != $teamId) {
      $query = sprintf(
        "SELECT g.game_id FROM uo_game g
            			LEFT JOIN uo_game_pool gp ON(g.game_id=game)
      					WHERE (g.hometeam=%d OR g.hometeam=%d) AND (g.hasstarted>0)  
      					AND gp.pool=%d",
        (int)$team_exist,
        (int)$team_exist,
        (int)$move['topool']
      );

      $games = DBQueryRowCount($query);
      if ($games) {
        echo "<p>" . _("Move not allowed. Game already played!") . "</p>";
        return;
      } else {
        $query = sprintf(
          "DELETE FROM uo_team_pool WHERE pool=%d AND rank=%d",
          (int)$move['topool'],
          (int)$move['torank']
        );
      }
    }
  }

  //insert team to next pool
  $query = sprintf(
    "INSERT IGNORE INTO uo_team_pool
				(team, pool, rank, activerank) 
				VALUES	('%s','%s','%s','%s')",
    (int)$teamId,
    (int)$move['topool'],
    (int)$move['torank'],
    (int)$move['torank']
  );

  $result = DBQuery($query);

  //update team pool
  $query = sprintf(
    "UPDATE uo_team SET
			pool=%d WHERE team_id=%d",
    (int)$move['topool'],
    (int)$move['torank']
  );

  DBQuery($query);

  //replace pseudo team with real team in games
  if (isRespTeamHomeTeam()) {
    $query = sprintf(
      "UPDATE uo_game SET
    		hometeam=%d, respteam=%d WHERE scheduling_name_home=%d AND scheduling_name_home!=0",
      (int)$teamId,
      (int)$teamId,
      (int)$move['scheduling_id']
    );
  } else {
    $query = sprintf(
      "UPDATE uo_game SET
    		hometeam=%d WHERE scheduling_name_home=%d AND scheduling_name_home!=0",
      (int)$teamId,
      (int)$move['scheduling_id']
    );
  }
  DBQuery($query);

  $query = sprintf(
    "UPDATE uo_game SET
		visitorteam=%d WHERE scheduling_name_visitor=%d AND scheduling_name_visitor!=0",
    (int)$teamId,
    (int)$move['scheduling_id']
  );

  DBQuery($query);

  //set move done
  $query = sprintf(
    "UPDATE uo_moveteams SET
		ismoved='1' WHERE frompool='%s' AND fromplacing='%s'",
    (int)$frompool,
    (int)$fromplacing
  );

  DBQuery($query);

  //set pool visible
  if ($poolinfo['follower'] != $move['topool']) {
    $query = sprintf("UPDATE uo_pool SET visible='1' WHERE pool_id=%d", (int)$move['topool']);
    DBQuery($query);
    DBQuery($query);
  }

  // check if special ranking rules apply in the destination pool
  CheckSpecialRanking($move['topool']);
}

function TeamPoolGamesAgainst($teamId1, $teamId2, $poolId)
{
  $query = sprintf(
    "
			SELECT pp.game_id, pp.hometeam, pp.visitorteam, pp.homescore, 
			pp.visitorscore, pp.hasstarted, pp.time
			FROM uo_game pp 
			RIGHT JOIN uo_game_pool pps ON(pps.game=pp.game_id)
			WHERE pps.pool='%s' AND pp.valid=true AND 
			(pp.visitorteam='%s' AND pp.hometeam='%s')
			ORDER BY pp.time ASC",
    DBEscapeString($poolId),
    DBEscapeString($teamId1),
    DBEscapeString($teamId2)
  );

  return DBQueryToArray($query);
}

function TeamPlayedGames($name, $seriestype, $sorting, $curSeason = false)
{

  $query = sprintf(
    "SELECT pj1.name AS hometeamname, pj2.name AS visitorteamname, pp.homescore, pp.visitorscore, pp.hasstarted,
	ser.season as season_id, ps.name, pp.game_id, ps.pool_id
	FROM uo_game pp 
	LEFT JOIN uo_pool ps ON (ps.pool_id=pp.pool)
	LEFT JOIN uo_series ser ON (ps.series=ser.series_id)
	LEFT JOIN uo_team pj1 ON(pp.hometeam=pj1.team_id) 
	LEFT JOIN uo_team pj2 ON (pp.visitorteam=pj2.team_id)
	WHERE (pj1.name='%s' OR pj2.name='%s') AND ser.type='%s' 
	AND pp.valid=true",
    DBEscapeString($name),
    DBEscapeString($name),
    DBEscapeString($seriestype)
  );

  if (!$curSeason) {
    $curentSeason = CurrentSeason();
    $query .= sprintf(" AND ser.season!='%s'", DBEscapeString($curentSeason));
  }

  switch ($sorting) {

    case "team":
      $query .= " ORDER BY hometeamname ASC, visitorteamname ASC";
      break;

    case "result":
      $query .= " ORDER BY pp.homescore DESC, pp.visitorscore DESC, hometeamname ASC, visitorteamname ASC";
      break;

    case "serie":
      $query .= " ORDER BY ser.season DESC, ps.name ASC, hometeamname ASC, visitorteamname ASC";
      break;

    default:
      $query .= " ORDER BY ser.season DESC, ps.name ASC, hometeamname ASC, visitorteamname ASC";
      break;
  }
  return DBQueryToArray($query);
}

function TeamStatsByPool($poolId, $teamId)
{
  $query = sprintf(
    "
		SELECT COUNT(*) AS games,
  		COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS wins, 
  		COUNT((hometeam=%d AND (homescore=visitorscore)) OR (visitorteam=%d AND (homescore=visitorscore)) OR NULL) AS draws, 
  		COUNT((hometeam=%d AND (homescore<visitorscore)) OR (visitorteam=%d AND (homescore>visitorscore)) OR NULL) AS losses 
		FROM uo_game 
		LEFT JOIN uo_game_pool gp ON(game_id=gp.game)
		WHERE (hometeam=%d OR visitorteam=%d) AND isongoing=0 AND hasstarted>0
		AND gp.pool=%d",
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$poolId
  );

  return DBQueryToRow($query);
}
function TeamStats($teamId)
{
  $query = sprintf(
    "
		SELECT COUNT(*) AS games, 
  		COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS wins, 
  		COUNT((hometeam=%d AND (homescore=visitorscore)) OR (visitorteam=%d AND (homescore=visitorscore)) OR NULL) AS draws,
  		COUNT((hometeam=%d AND (homescore<visitorscore)) OR (visitorteam=%d AND (homescore>visitorscore)) OR NULL) AS losses 
  		FROM uo_game 
		LEFT JOIN uo_game_pool gp ON(game_id=gp.game)
		WHERE (hasstarted>0) AND (hometeam=%d OR visitorteam=%d) AND isongoing=0 AND gp.timetable=1",
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId,
    (int)$teamId
  );

  return DBQueryToRow($query);
}

function TeamVictoryPointsByPool($poolId, $teamId)
{
  $query = sprintf(
    "
SELECT tot.pool,tot.team_id,count(tot.game_id) as games,sum(tot.diff) as margin,
	   sum(tot.victorypoints) as victorypoints,sum(swiss.victorypoints) as oppvp,
	   sum(tot.score) as score
FROM 
(SELECT gp.pool,hometeam as team_id, visitorteam as opp_id,game.game_id,homescore-visitorscore as diff, vp.victorypoints,homescore as score
FROM uo_game game
LEFT JOIN uo_victorypoints vp ON homescore-visitorscore=vp.pointdiff
LEFT JOIN uo_game_pool gp ON (game_id=gp.game)
WHERE isongoing=0 AND hasstarted>0
UNION
SELECT gp.pool,visitorteam as team_id, hometeam as opp_id,game.game_id,visitorscore-homescore as diff, vp.victorypoints,visitorscore as score
FROM uo_game game
LEFT JOIN uo_victorypoints vp ON visitorscore-homescore=vp.pointdiff
LEFT JOIN uo_game_pool gp ON (game_id=gp.game)
WHERE isongoing=0 AND hasstarted>0) tot

LEFT JOIN

(SELECT un.pool,un.team_id,count(un.game_id) as games,sum(victorypoints) as victorypoints FROM
(SELECT gp.pool,hometeam as team_id, game.game_id,vp.victorypoints
FROM uo_game game
LEFT JOIN uo_victorypoints vp ON homescore-visitorscore=vp.pointdiff
LEFT JOIN uo_game_pool gp ON (game_id=gp.game)
WHERE isongoing=0 AND hasstarted>0
UNION
SELECT gp.pool,visitorteam as team_id, game.game_id,vp.victorypoints
FROM uo_game game
LEFT JOIN uo_victorypoints vp ON visitorscore-homescore=vp.pointdiff
LEFT JOIN uo_game_pool gp ON (game_id=gp.game)
WHERE isongoing=0 AND hasstarted>0) un
GROUP BY pool,team_id) swiss

ON swiss.pool=tot.pool AND tot.opp_id=swiss.team_id		
		
WHERE tot.team_id='%d' AND tot.pool='%s'
GROUP BY tot.pool,tot.team_id",
    DBEscapeString($teamId),
    DBEscapeString($poolId)
  );

  return DBQueryToRow($query);
}



function TeamPoints($teamId)
{
  $query = sprintf(
    "
		SELECT j.team_id, COALESCE(k.scores,0) + COALESCE(v.scores,0) AS scores, COALESCE(k.against,0) + COALESCE(v.against,0) AS against
		FROM uo_team AS j 
		LEFT JOIN (SELECT hometeam, FORMAT(SUM(homescore),0) AS scores, FORMAT(SUM(visitorscore),0) AS against
			FROM uo_game 
			LEFT JOIN uo_game_pool gp1 ON(game_id=gp1.game)
			WHERE hometeam=%d AND hasstarted>0 AND isongoing=0 AND gp1.timetable=1 GROUP BY hometeam) AS k 
		ON (j.team_id=k.hometeam) 
		LEFT JOIN (SELECT visitorteam, FORMAT(SUM(visitorscore),0) AS scores, FORMAT(SUM(homescore),0) AS against 
			FROM uo_game 
			LEFT JOIN uo_game_pool gp2 ON(game_id=gp2.game)
			WHERE visitorteam=%d AND hasstarted>0 AND isongoing=0 AND gp2.timetable=1 GROUP BY visitorteam) AS v 
			ON (j.team_id=v.visitorteam) 
		WHERE j.team_id=%d",
    (int)$teamId,
    (int)$teamId,
    (int)$teamId
  );

  return DBQueryToRow($query);
}

function TeamPointsByPool($poolId, $teamId)
{
  $query = sprintf(
    "
		SELECT j.team_id, COALESCE(k.scores,0) + COALESCE(v.scores,0) AS scores, COALESCE(k.against,0) + COALESCE(v.against,0) AS against
		FROM uo_team AS j 
		LEFT JOIN (SELECT hometeam, FORMAT(SUM(homescore),0) AS scores, FORMAT(SUM(visitorscore),0) AS against
			FROM uo_game 
			LEFT JOIN uo_game_pool gp1 ON(game_id=gp1.game)
			WHERE hometeam=%d AND hasstarted>0 AND isongoing=0 AND gp1.pool=%d GROUP BY hometeam) AS k 
		ON (j.team_id=k.hometeam) 
		LEFT JOIN (SELECT visitorteam, FORMAT(SUM(visitorscore),0) AS scores, FORMAT(SUM(homescore),0) AS against 
			FROM uo_game 
			LEFT JOIN uo_game_pool gp2 ON(game_id=gp2.game)
			WHERE visitorteam=%d AND hasstarted>0 AND isongoing=0 AND gp2.pool=%d GROUP BY visitorteam) AS v 
			ON (j.team_id=v.visitorteam) WHERE j.team_id=%d",
    (int)$teamId,
    (int)$poolId,
    (int)$teamId,
    (int)$poolId,
    (int)$teamId
  );

  return DBQueryToRow($query);
}

function TeamScoreBoard($teamId, $pools, $sorting, $limit)
{
  if ($pools) {
    if (!is_array($pools)) {
      $pools = explode(",", (string)$pools);
    }
    $pools = array_filter(array_map('intval', $pools), function ($val) {
      return $val > 0;
    });
    $pools = empty($pools) ? array(0) : $pools;
    $poolList = implode(",", $pools);

    $query = sprintf(
      "
			SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin, 
				COALESCE(t1.callahan,0) AS callahan, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, COALESCE(pel.games,0) AS games 
			FROM uo_player AS p 
				LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m 
					LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game) 
					LEFT JOIN uo_game AS g1 ON (m.game=g1.game_id) 
						WHERE ps.pool IN($poolList) AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer) 
				LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1 
					LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game) 
					LEFT JOIN uo_game AS g2 ON (m1.game=g2.game_id) 
						WHERE ps1.pool IN($poolList) AND m1.scorer IS NOT NULL AND iscallahan=1 AND g2.isongoing=0 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1) 			
				LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin FROM uo_goal AS m2 
					LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game) 
					LEFT JOIN uo_game AS g3 ON (m2.game=g3.game_id) 
						WHERE ps2.pool IN($poolList) AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist) 
				LEFT JOIN uo_team AS j ON (p.team=j.team_id) 
				LEFT JOIN (SELECT player, COUNT(*) AS games FROM uo_played up
					LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
						WHERE g4.pool IN($poolList) AND g4.isongoing=0 
						GROUP BY player) AS pel ON (p.player_id=pel.player) WHERE p.team=%d",
      (int)$teamId
    );
  } else {
    $query = sprintf(
      "
			SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin, 
				COALESCE(t1.callahan,0) AS callahan,(COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, COALESCE(pel.games,0) AS games 
			FROM uo_player AS p 
			LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m LEFT JOIN uo_game AS game ON (m.game=game.game_id) 
				WHERE (game.hometeam=%d or game.visitorteam=%d) AND game.isongoing=0 AND scorer IS NOT NULL GROUP BY scorer) AS t ON (p.player_id=t.scorer) 
			LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1 LEFT JOIN uo_game AS game1 ON (m1.game=game1.game_id) 
				WHERE (game1.hometeam=%d or game1.visitorteam=%d) AND game1.isongoing=0 AND m1.scorer IS NOT NULL AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1) 				
			LEFT JOIN  (SELECT m2.assist AS assist, COUNT(*) AS fedin FROM uo_goal AS m2 
			LEFT JOIN uo_game AS game2 ON (m2.game=game2.game_id) 
				WHERE (game2.hometeam=%d or game2.visitorteam=%d) AND game2.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist) 
			LEFT JOIN uo_team AS j ON (p.team=j.team_id) 
			LEFT JOIN (SELECT player, COUNT(*) AS games FROM uo_played up
				LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
				WHERE (g4.hometeam=%d or g4.visitorteam=%d) AND g4.isongoing=0 GROUP BY player) AS pel 
					ON (p.player_id=pel.player) WHERE p.team=%d",
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId
    );
  }

  switch ($sorting) {
    case "total":
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "goal":
      $query .= " ORDER BY done DESC, total DESC, fedin DESC, lastname ASC";
      break;

    case "callahan":
      $query .= " ORDER BY callahan DESC, total DESC, lastname ASC";
      break;

    case "pass":
      $query .= " ORDER BY fedin DESC, total DESC, done DESC, lastname ASC";
      break;

    case "games":
      $query .= " ORDER BY games DESC, total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "team":
      $query .= " ORDER BY teamname ASC, total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "name":
      $query .= " ORDER BY firstname,lastname ASC, total DESC, done DESC, fedin DESC";
      break;

    default:
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC";
      break;
  }

  if ($limit > 0) {
    $query .= " limit $limit";
  }

  return DBQuery($query);
}


function TeamScoreBoardWithDefenses($teamId, $pools, $sorting, $limit)
{
  if ($pools) {
    if (!is_array($pools)) {
      $pools = explode(",", (string)$pools);
    }
    $pools = array_filter(array_map('intval', $pools), function ($val) {
      return $val > 0;
    });
    $pools = empty($pools) ? array(0) : $pools;
    $poolList = implode(",", $pools);
    // This part needs to be tested......but should work
    $query = sprintf(
      "
			SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin, 
				COALESCE(t1.callahan,0) AS callahan, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, COALESCE(pel.games,0) AS games, COALESCE(d.deftotal) AS deftotal   
			FROM uo_player AS p 
				LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m 
					LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game) 
					LEFT JOIN uo_game AS g1 ON (m.game=g1.game_id) 
					WHERE ps.pool IN($poolList) AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer) 
				LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1 
					LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game) 
					LEFT JOIN uo_game AS g2 ON (m1.game=g2.game_id) 
					WHERE ps1.pool IN($poolList) AND m1.scorer IS NOT NULL AND iscallahan=1 AND g2.isongoing=0 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1) 			
				LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin FROM uo_goal AS m2 
					LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game) 
					LEFT JOIN uo_game AS g3 ON (m2.game=g3.game_id) 
					WHERE ps2.pool IN($poolList) AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist)
				LEFT JOIN (SELECT m3.author AS author, COUNT(*) AS deftotal FROM uo_defense AS m3 
					LEFT JOIN uo_game_pool AS ps2 ON (m3.game=ps2.game) 
					LEFT JOIN uo_game AS g3 ON (m3.game=g3.game_id) 
					WHERE ps2.pool IN($poolList) AND g3.isongoing=0 GROUP BY author) AS d ON (p.player_id=d.author)
				LEFT JOIN uo_team AS j ON (p.team=j.team_id) 
				LEFT JOIN (SELECT player, COUNT(*) AS games FROM uo_played up
					LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
					WHERE g4.pool IN($poolList) AND g4.isongoing=0 
					GROUP BY player) AS pel ON (p.player_id=pel.player) WHERE p.team=%d",
      (int)$teamId
    );
  } else {
    $query = sprintf(
      "
			SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done, COALESCE(s.fedin,0) AS fedin, 
				COALESCE(t1.callahan,0) AS callahan,(COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, COALESCE(pel.games,0) AS games, COALESCE(t2.deftotal,0) AS deftotal
			FROM uo_player AS p 
			LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m LEFT JOIN uo_game AS game ON (m.game=game.game_id) 
				WHERE (game.hometeam=%d or game.visitorteam=%d) AND game.isongoing=0 AND scorer IS NOT NULL GROUP BY scorer) AS t ON (p.player_id=t.scorer) 
			LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1 LEFT JOIN uo_game AS game1 ON (m1.game=game1.game_id) 
				WHERE (game1.hometeam=%d or game1.visitorteam=%d) AND game1.isongoing=0 AND m1.scorer IS NOT NULL AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1) 				
			LEFT JOIN  (SELECT m2.assist AS assist, COUNT(*) AS fedin FROM uo_goal AS m2 
			LEFT JOIN uo_game AS game2 ON (m2.game=game2.game_id) 
				WHERE (game2.hometeam=%d or game2.visitorteam=%d) AND game2.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist)
			LEFT JOIN (SELECT m3.author AS author, COUNT(*) AS deftotal FROM uo_defense AS m3 LEFT JOIN uo_game AS game ON (m3.game=game.game_id) 
				WHERE (game.hometeam=%d or game.visitorteam=%d) AND game.isongoing=0 AND author IS NOT NULL GROUP BY author) AS t2 ON (p.player_id=t2.author)
			LEFT JOIN uo_team AS j ON (p.team=j.team_id) 
			LEFT JOIN (SELECT player, COUNT(*) AS games FROM uo_played up
				LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
				WHERE (g4.hometeam=%d or g4.visitorteam=%d) AND g4.isongoing=0 GROUP BY player) AS pel 
					ON (p.player_id=pel.player) WHERE p.team=%d",
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId,
      (int)$teamId
    );
  }

  switch ($sorting) {
    case "deftotal":
      $query .= " ORDER BY deftotal DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "total":
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "goal":
      $query .= " ORDER BY done DESC, total DESC, fedin DESC, lastname ASC";
      break;

    case "callahan":
      $query .= " ORDER BY callahan DESC, total DESC, lastname ASC";
      break;

    case "pass":
      $query .= " ORDER BY fedin DESC, total DESC, done DESC, lastname ASC";
      break;

    case "games":
      $query .= " ORDER BY games DESC, total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "team":
      $query .= " ORDER BY teamname ASC, total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "name":
      $query .= " ORDER BY firstname,lastname ASC, total DESC, done DESC, fedin DESC";
      break;

    default:
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC";
      break;
  }

  if ($limit > 0) {
    $query .= " limit $limit";
  }

  return DBQueryToArray($query);
}


/**
 * Returns all played games between given teams within same type of series.
 *
 * @param string $team1 first team name
 * @param string $team2 second team name
 * @param string $seriestype type of series
 * @param string $sorting return value sorting: team, result, series
 * @return mysqli_result array of players
 */
function GetAllPlayedGames($team1, $team2, $seriestype, $sorting)
{
  $query = sprintf(
    "
		SELECT pj1.name AS hometeamname, pj2.name AS visitorteamname, pp.homescore, pp.visitorscore,
  			pp.hasstarted, ser.season AS season_id, ps.name, 
			pp.game_id, ps.pool_id, s.name AS seasonname 
		FROM uo_game pp 
		LEFT JOIN uo_pool ps ON (ps.pool_id=pp.pool) 
		LEFT JOIN uo_series ser ON (ps.series=ser.series_id)
		LEFT JOIN uo_season s ON (s.season_id=ser.season)
		LEFT JOIN uo_team pj1 ON(pp.hometeam=pj1.team_id) 
		LEFT JOIN uo_team pj2 ON (pp.visitorteam=pj2.team_id)
		WHERE ((REPLACE(pj1.name,' ','')='%s' AND REPLACE(pj2.name,' ','')='%s') OR (REPLACE(pj1.name,' ','')='%s' AND REPLACE(pj2.name,' ','')='%s'))
			AND (pp.hasstarted > 0)
		AND ser.type='%s' AND pp.valid=true ",
    DBEscapeString($team1),
    DBEscapeString($team2),
    DBEscapeString($team2),
    DBEscapeString($team1),
    DBEscapeString($seriestype)
  );

  switch ($sorting) {
    case "team":
      $query .= " ORDER BY hometeamname ASC, visitorteamname ASC";
      break;

    case "result":
      $query .= " ORDER BY pp.homescore DESC, pp.visitorscore DESC, hometeamname ASC, visitorteamname ASC";
      break;

    case "series":
      $query .= " ORDER BY s.starttime DESC, ps.name ASC, hometeamname ASC, visitorteamname ASC";
      break;

    default:
      $query .= " ORDER BY s.starttime DESC, ps.name ASC, hometeamname ASC, visitorteamname ASC";
      break;
  }
  return DBQuery($query);
}

function TeamResponsibleGames($teamId, $placeId)
{
  $query = sprintf(
    "
		SELECT Kj.name As hometeamname, Vj.name As visitorteamname, p.time, p.game_id, p.homescore,
  			p.visitorscore, pp.hasstarted, COALESCE(m.goals,0) As goals 
		FROM uo_game AS p 
		LEFT JOIN (SELECT COUNT(*) AS goals, game 
			FROM uo_goal GROUP BY game) AS m ON (p.game_id=m.game), uo_team As Kj, uo_team As Vj  
			WHERE p.visitorteam=Vj.team_id AND p.hometeam=Kj.team_id 
			AND (p.reservation=%d AND p.RespTeam=%d))
		GROUP BY Kj.name, Vj.name, p.time, p.game_id, p.homescore, p.visitorscore, pp.hasstarted",
    (int)$placeId,
    (int)$teamId
  );

  return DBQueryToArray($query);
}

function TeamGetTeamsByName($teamname)
{

  $query = sprintf(
    "SELECT t.team_id FROM uo_team t 
    	LEFT JOIN uo_team_stats ts ON(ts.team_id=t.team_id)
		WHERE ts.team_id IS NOT NULL AND t.name LIKE '%s%%' GROUP BY t.team_id ORDER BY t.team_id DESC",
    DBEscapeString($teamname)
  );

  $teams = DBQueryToArray($query);

  return $teams;
}
function TeamCopyRoster($copyfrom, $copyto)
{
  if (hasEditPlayersRight($copyto)) {
    $team_players = TeamPlayerList($copyfrom);
    while ($player = mysqli_fetch_assoc($team_players)) {
      $query = sprintf(
        "INSERT INTO uo_player(firstname, lastname, profile_id, accreditation_id, team, num)
      			VALUES ('%s','%s',%d,'%s',%d,%d)",
        DBEscapeString($player["firstname"]),
        DBEscapeString($player["lastname"]),
        (int)$player["profile_id"],
        DBEscapeString($player["accreditation_id"]),
        (int)$copyto,
        (int)$player["num"]
      );
      DBQuery($query);
    }
  } else {
    die('Insufficient rights to edit roster');
  }
}

function GetTeamPlayers()
{
  if (isset($_GET['search']) || isset($_GET['query']) || isset($_GET['q'])) {
    if (isset($_GET['search']))
      $search = $_GET['search'];
    elseif (isset($_GET['query']))
      $search = $_GET['query'];
    elseif (isset($_GET['q']))
      $search = $_GET['q'];
    else $search = "0";
  }
  $query = sprintf(
    "SELECT firstname, lastname, num, accreditation_id, profile_id, player_id, accredited
		FROM uo_player 
		WHERE team=%d 
		ORDER BY lastname ASC, firstname ASC, num ASC",
    (int)$search
  );

  return DBQueryToArray($query);
}

function RemovePlayer($playerId)
{
  $playerInfo = PlayerInfo($playerId);
  if (!$playerInfo) {
    return false;
  }
  if (hasEditPlayersRight($playerInfo['team'])) {
    Log2("player", "delete", PlayerName($playerId));

    $query = sprintf(
      "DELETE FROM uo_player WHERE player_id='%s'",
      DBEscapeString($playerId)
    );
    return DBQuery($query);
  } else {
    die('Insufficient rights to remove player');
  }
}

function AddPlayer($teamId, $firstname, $lastname, $profileId, $num = -1)
{
  if (hasEditPlayersRight($teamId)) {

    if (!empty($profileId)) {
      $profile = PlayerProfile($profileId);
      $accreditationId = $profile['accreditation_id'];
    } else {
      $query = sprintf(
        "INSERT INTO uo_player_profile (firstname,lastname,num) VALUES
				('%s','%s',%d)",
        DBEscapeString($firstname),
        DBEscapeString($lastname),
        (int)$num
      );
      $profileId = DBQueryInsert($query);
      $accreditationId = 0;
    }
    $query = "INSERT INTO uo_player (firstname, lastname, profile_id, accreditation_id,team";

    if ($num >= 0) {
      $query .= ",num";
    }

    $query .= ") ";
    $query .= sprintf(
      "VALUES ('%s', '%s', %d, '%s', %d",
      DBEscapeString($firstname),
      DBEscapeString($lastname),
      (int)$profileId,
      $accreditationId,
      (int)$teamId
    );

    if ($num >= 0) {
      $query .= sprintf(",%d", (int)$num);
    }
    $query .= sprintf(")");
    $playerId = DBQueryInsert($query);
    Log1("player", "add", $playerId, $teamId);

    return $playerId;
  } else {
    die('Insufficient rights to add player');
  }
}

function CanDeletePlayer($playerId)
{
  $query = sprintf(
    "SELECT count(*) FROM uo_played WHERE player='%s'",
    DBEscapeString($playerId)
  );
  $count = DBQueryToValue($query);
  return ($count == 0);
}

function SetTeamProfile($profile)
{

  if (hasEditPlayersRight($profile['team_id'])) {

    if (!empty($profile['abbreviation'])) {
      $query = sprintf(
        "UPDATE uo_team SET abbreviation='%s' WHERE team_id='%s'",
        DBEscapeString($profile['abbreviation']),
        DBEscapeString($profile['team_id'])
      );

      DBQuery($query);
    }

    $query = sprintf(
      "
			SELECT team_id
			FROM uo_team_profile 
			WHERE team_id='%s'",
      DBEscapeString($profile['team_id'])
    );

    $result = DBQueryRowCount($query);

    //add
    if ($result == 0) {
      $query = sprintf(
        "INSERT INTO uo_team_profile (team_id,
			captain, coach, story, achievements) VALUES 
			('%s', '%s', '%s', '%s', '%s')",
        DBEscapeString($profile['team_id']),
        DBEscapeString($profile['captain']),
        DBEscapeString($profile['coach']),
        DBEscapeString($profile['story']),
        DBEscapeString($profile['achievements'])
      );
      //update
    } else {
      $query = sprintf(
        "UPDATE uo_team_profile SET captain='%s', coach='%s',
				story='%s', achievements='%s' WHERE team_id='%s'",
        DBEscapeString($profile['captain']),
        DBEscapeString($profile['coach']),
        DBEscapeString($profile['story']),
        DBEscapeString($profile['achievements']),
        DBEscapeString($profile['team_id'])
      );
    }
    $result = DBQuery($query);
    LogTeamProfileUpdate($profile['team_id']);
    return $result;
  } else {
    die('Insufficient rights to edit team profile');
  }
}

function UploadTeamImage($teamId)
{
  if (isSuperAdmin() || hasEditPlayersRight($teamId)) {
    $max_file_size = 5 * 1024 * 1024; //5 MB

    if ($_FILES['picture']['size'] > $max_file_size) {
      return "<p class='warning'>" . _("File is too large") . "</p>";
    }

    $imgType = $_FILES['picture']['type'];
    $type = explode("/", $imgType);
    $type1 = $type[0];
    $type2 = $type[1];
    if ($type1 != "image") {
      return "<p class='warning'>" . _("File is not supported image format") . "</p>";
    }

    if (!extension_loaded("gd")) {
      return "<p class='warning'>" . _("Missing gd extension for image handling.") . "</p>";
    }

    $file_tmp_name = $_FILES['picture']['tmp_name'];
    $imgname = time() . $teamId . ".jpg";
    $basedir = "" . UPLOAD_DIR . "teams/$teamId/";
    if (!is_dir($basedir)) {
      recur_mkdirs($basedir, 0775);
      recur_mkdirs($basedir . "thumbs/", 0775);
    }

    ConvertToJpeg($file_tmp_name, $basedir . $imgname);
    CreateThumb($basedir . $imgname, $basedir . "thumbs/" . $imgname, 320, 240);

    //currently removes old image, in future there might be a gallery of images
    RemoveTeamProfileImage($teamId);
    SetTeamProfileImage($teamId, $imgname);

    return "";
  } else {
    die('Insufficient rights to upload image');
  }
}


function SetTeamProfileImage($teamId, $filename)
{
  if (isSuperAdmin() || hasEditPlayersRight($teamId)) {

    $query = sprintf(
      "UPDATE uo_team_profile SET profile_image='%s' WHERE team_id='%s'",
      DBEscapeString($filename),
      DBEscapeString($teamId)
    );

    DBQuery($query);
  } else {
    die('Insufficient rights to edit team profile');
  }
}

function RemoveTeamProfileImage($teamId)
{
  if (isSuperAdmin() || hasEditPlayersRight($teamId)) {

    $profile = TeamProfile($teamId);

    if (!empty($profile['profile_image'])) {

      //thumbnail
      $file = "" . UPLOAD_DIR . "teams/$teamId/thumbs/" . $profile['profile_image'];
      if (is_file($file)) {
        unlink($file); //  remove old images if present
      }

      //image
      $file = "" . UPLOAD_DIR . "teams/$teamId/" . $profile['profile_image'];

      if (is_file($file)) {
        unlink($file); //  remove old images if present
      }

      $query = sprintf(
        "UPDATE uo_team_profile SET profile_image=NULL WHERE team_id='%s'",
        DBEscapeString($teamId)
      );

      DBQuery($query);
    }
  } else {
    die('Insufficient rights to edit team profile');
  }
}

function AddTeam($params)
{
  if (hasEditTeamsRight($params['series'])) {
    $poolValue = !empty($params['pool']) ? (int)$params['pool'] : "NULL";
    $query = sprintf(
      "
			INSERT INTO uo_team
			(name, pool, uo_team.rank, valid, series) 
			VALUES ('%s', %s, '%s', '%s', '%s')",
      DBEscapeString($params['name']),
      $poolValue,
      DBEscapeString($params['rank']),
      DBEscapeString($params['valid']),
      DBEscapeString($params['series'])
    );

    $teamId = DBQueryInsert($query);

    if (!empty($params['country'])) {
      DBQuery("UPDATE uo_team SET country=" . (int)$params['country'] . " WHERE team_id=$teamId");
    }
    if (!empty($params['club'])) {
      DBQuery("UPDATE uo_team SET club=" . (int)$params['club'] . " WHERE team_id=$teamId");
    }

    if (!empty($params['abbreviation'])) {
      DBQuery("UPDATE uo_team SET abbreviation='" . DBEscapeString($params['abbreviation']) . "' WHERE team_id=$teamId");
    }

    Log1("team", "add", $teamId);
    return $teamId;
  } else {
    die('Insufficient rights to add team');
  }
}

function SetTeam($params)
{
  if (hasEditTeamsRight($params['series'])) {
    $poolValue = !empty($params['pool']) ? (int)$params['pool'] : "NULL";
    $query = sprintf(
      "
			UPDATE uo_team SET
			name='%s', pool=%s, abbreviation='%s',
			rank='%s', valid='%s', series='%s'
			WHERE team_id='%s'",
      DBEscapeString($params['name']),
      $poolValue,
      DBEscapeString($params['abbreviation']),
      DBEscapeString($params['rank']),
      DBEscapeString($params['valid']),
      DBEscapeString($params['series']),
      DBEscapeString($params['team_id'])
    );

    $result = DBQuery($query);

    if (!empty($params['country'])) {
      DBQuery("UPDATE uo_team SET country=" . (int)$params['country'] . " WHERE team_id=" . (int)$params['team_id']);
    }
    if (!empty($params['club'])) {
      DBQuery("UPDATE uo_team SET club=" . (int)$params['club'] . " WHERE team_id=" . (int)$params['team_id']);
    }

    return $result;
  } else {
    die('Insufficient rights to edit team');
  }
}

function SetTeamName($teamId, $name)
{
  $series = getTeamSeries($teamId);
  if (hasEditTeamsRight($series)) {
    $query = sprintf(
      "
			UPDATE uo_team SET name='%s' WHERE team_id='%s'",
      DBEscapeString($name),
      DBEscapeString($teamId)
    );

    return DBQuery($query);
  } else {
    die('Insufficient rights to edit team');
  }
}

function SetTeamOwner($teamId, $clubId)
{
  $series = getTeamSeries($teamId);
  if (hasEditTeamsRight($series)) {
    $query = sprintf(
      "
			UPDATE uo_team SET club='%s' WHERE team_id='%s'",
      DBEscapeString($clubId),
      DBEscapeString($teamId)
    );

    return DBQuery($query);
  } else {
    die('Insufficient rights to edit team');
  }
}

function SetTeamSerieRank($teamId, $poolId, $rank, $activerank)
{
  $poolInfo = PoolInfo($poolId);
  if (hasEditTeamsRight($poolInfo['series'])) {
    $query = sprintf(
      "
			UPDATE uo_team_pool SET
			rank='%s', activerank='%s'
			WHERE team='%s' AND pool='%s'",
      (int) $rank,
      (int) $activerank,
      (int) $teamId,
      (int) $poolId
    );

    $result = DBQuery($query);
    return $result;
  } else {
    die('Insufficient rights to edit team rank');
  }
}

function SetTeamPoolRank($teamId, $poolId, $rank)
{
  $poolInfo = PoolInfo($poolId);
  if (hasEditTeamsRight($poolInfo['series'])) {
    $query = sprintf(
      "
			UPDATE uo_team_pool SET
			rank='%s'
			WHERE team='%s' AND pool='%s'",
      (int) $rank,
      (int) $teamId,
      (int) $poolId
    );

    $result = DBQuery($query);

    return $result;
  } else {
    die('Insufficient rights to edit team rank');
  }
}

function SetTeamRank($teamId, $poolId, $activerank)
{
  $poolInfo = PoolInfo($poolId);
  if (hasEditTeamsRight($poolInfo['series'])) {
    $query = sprintf(
      "
			UPDATE uo_team_pool SET
			activerank='%s'
			WHERE team='%s' AND pool='%s'",
      (int) $activerank,
      (int) $teamId,
      (int) $poolId
    );

    $result = DBQuery($query);

    return $result;
  } else {
    die('Insufficient rights to edit team rank');
  }
}

function SetTeamSeeding($seriesId, $teamId, $seed)
{
  if (hasEditTeamsRight($seriesId)) {
    $query = sprintf(
      "
			UPDATE uo_team SET
			rank=%d
			WHERE team_id=%d",
      (int)$seed,
      (int)$teamId
    );

    return DBQuery($query);
  } else {
    die('Insufficient rights to edit team rank');
  }
}

function DeleteTeam($teamId)
{
  $series = getTeamSeries($teamId);
  if (hasEditTeamsRight($series)) {
    Log2("team", "delete", TeamName($teamId));
    $query = sprintf(
      "DELETE FROM uo_userproperties WHERE value='teamadmin:%d'",
      (int)$teamId
    );

    DBQuery($query);

    $query = sprintf(
      "DELETE FROM uo_team_pool WHERE team='%s'",
      DBEscapeString($teamId)
    );

    DBQuery($query);

    $query = sprintf(
      "DELETE FROM uo_team WHERE team_id=%d",
      (int)$teamId
    );

    DBQuery($query);
  } else {
    die('Insufficient rights to delete team');
  }
}

function CanDeleteTeam($teamId)
{
  $query = sprintf(
    "SELECT count(*) FROM uo_game WHERE hometeam=%d OR visitorteam=%d",
    (int)$teamId,
    (int)$teamId
  );
  $count = DBQueryToValue($query);
  if ($count == 0) {
    $query = sprintf(
      "SELECT count(*) FROM uo_player WHERE team=%d",
      (int)$teamId
    );
    $count = DBQueryToValue($query);
    return $count == 0;
  } else return false;
}

function AddTeamProfileUrl($teamId, $type, $url, $name)
{
  if (isSuperAdmin() || hasEditPlayersRight($teamId)) {
    $url = SafeUrl($url);
    $query = sprintf(
      "INSERT INTO uo_urls (owner,owner_id,type,name,url)
				VALUES('team',%d,'%s','%s','%s')",
      (int)$teamId,
      DBEscapeString($type),
      DBEscapeString($name),
      DBEscapeString($url)
    );
    return DBQuery($query);
  } else {
    die('Insufficient rights to add url');
  }
}

function RemoveTeamProfileUrl($teamId, $urlId)
{
  if (isSuperAdmin() || hasEditPlayersRight($teamId)) {
    $query = sprintf(
      "DELETE FROM uo_urls WHERE url_id=%d",
      (int)$urlId
    );
    return DBQuery($query);
  } else {
    die('Insufficient rights to remove url');
  }
}

function TeamsToCsv($season, $separator)
{ // SELECT ssc.*, SUM(value*factor) FROM uo_spirit_score ssc   LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id) WHERE team_id=1398

  $query = sprintf(
    "SELECT j.name AS Team, j.abbreviation AS ShortName, club.name AS Club,
		c.name AS Country, ser.name AS Division, ps.name AS Pool,	
		COALESCE(k.games,0) + COALESCE(v.games,0) AS Games,
		COALESCE(k.wins,0) + COALESCE(v.wins,0) AS Wins,
		COALESCE(k.scores,0) + COALESCE(v.scores,0) AS GoalsFor,
		COALESCE(k.against,0) + COALESCE(v.against,0) AS GoalsAgainst,
		COALESCE(k.spirit,0) + COALESCE(v.spirit,0) AS SpiritPoints
		FROM uo_team AS j
		LEFT JOIN (SELECT COUNT(*) AS games, 
  			COUNT(g.homescore>g.visitorscore OR NULL) as wins, 
  			COUNT(g.homescore=g.visitorscore OR NULL) as draws, 
  		  	COUNT(g.homescore<g.visitorscore OR NULL) as losses, 
  			g.hometeam, FORMAT(SUM(g.homescore),0) AS scores, FORMAT(SUM(COALESCE(hspirit.score,0)),0) AS spirit, FORMAT(SUM(g.visitorscore),0) AS against
			FROM uo_game g
			LEFT JOIN uo_game_pool gp1 ON(g.game_id=gp1.game)
			LEFT JOIN (
        SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS score
        FROM uo_spirit_score ssc
        LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
        GROUP BY ssc.game_id, ssc.team_id
      ) AS hspirit ON (g.game_id = hspirit.game_id AND g.hometeam = hspirit.team_id)
			WHERE g.isongoing=0 AND gp1.timetable=1 GROUP BY hometeam) AS k
		ON (j.team_id=k.hometeam)
		LEFT JOIN (SELECT COUNT(*) AS games, 
  			COUNT(g.homescore<g.visitorscore OR NULL) as wins, 
  			COUNT(g.homescore=g.visitorscore OR NULL) as draws, 
  		  	COUNT(g.homescore>g.visitorscore OR NULL) as losses, 
  			g.visitorteam, FORMAT(SUM(g.visitorscore),0) AS scores, FORMAT(SUM(COALESCE(vspirit.score,0)),0) AS spirit, FORMAT(SUM(g.homescore),0) AS against
			FROM uo_game g
			LEFT JOIN uo_game_pool gp2 ON(g.game_id=gp2.game)
			LEFT JOIN (
        SELECT ssc.game_id, ssc.team_id, SUM(ssc.value * sct.factor) AS score
        FROM uo_spirit_score ssc
        LEFT JOIN uo_spirit_category sct ON (ssc.category_id = sct.category_id)
        GROUP BY ssc.game_id, ssc.team_id
      ) AS vspirit ON (g.game_id = vspirit.game_id AND g.visitorteam = vspirit.team_id)
			WHERE g.isongoing=0 AND gp2.timetable=1 GROUP BY visitorteam) AS v
			ON (j.team_id=v.visitorteam)
		LEFT JOIN uo_series ser ON(ser.series_id=j.series)
		LEFT JOIN uo_pool ps ON (j.pool=ps.pool_id) 		
		LEFT JOIN uo_club club ON (j.club=club.club_id)
		LEFT JOIN uo_country c ON (j.country=c.country_id)
		WHERE ser.season='%s'
		GROUP BY j.team_id
		ORDER BY ser.ordering, j.name",
    DBEscapeString($season)
  );

  $result = DBQuery($query);
  return ResultsetToCsv($result, $separator);
}
