<?php
include_once $include_prefix . 'lib/club.functions.php';
include_once $include_prefix . 'lib/country.functions.php';


/**
 * Returns selected division based on url or session variable.
 * 
 * @return uo_series.series_id
 */
function CurrentSeries($season)
{

  if (!empty($_GET["series"])) {
    $_SESSION['division'] = $_GET["series"];
    return $_GET["series"];
  }

  $series = SeasonSeries($season);

  if (!empty($_SESSION['division'])) {
    foreach ($series as $ser) {
      if ($ser['series_id'] == $_SESSION['division']) {
        return $_SESSION['division'];
      }
    }
  }


  if (count($series)) {
    $_SESSION['division'] = $series[0]['series_id'];
    return $series[0]['series_id'];
  }

  return -1;
}

/**
 * Get all pools in given division.
 *
 * @param int $seriesId uo_series.series_id
 * @param boolean $onlyvisible set TRUE if only visible pools are returned.
 * @param boolean $nocontinuingpools set TRUE if continuation pools are exluded. 
 * @param boolean $noplacementpools set TRUE if placement pools are exluded.
 * @return Array array of pools
 */
function SeriesPools($seriesId, $onlyvisible = false, $nocontinuingpools = false, $noplacementpools = false)
{

  $query = sprintf("SELECT pool_id, name, type FROM uo_pool WHERE series=%d", (int)$seriesId);

  if ($onlyvisible) {
    $query .= " AND visible=1";
  }
  if ($nocontinuingpools) {
    $query .= " AND continuingpool=0";
  }
  if ($noplacementpools) {
    $query .= " AND placementpool=0";
  }

  $query .= " ORDER BY ordering ASC, name, pool_id";

  return DBQueryToArray($query, true);
}

/**
 * Get all placement pools in given division.
 * @param int $seriesId uo_series.series_id
 * @return PHP array of pools
 */
function SeriesPlacementPoolIds($seriesId)
{

  $query = sprintf(
    "SELECT pool_id, played, type FROM uo_pool WHERE series=%d AND placementpool=1 ORDER BY ordering ASC",
    (int)$seriesId
  );

  return DBQueryToArray($query);
}

/**
 * Get list of division types.
 * @return Array Hardcoded PHP array of division types.
 */
function SeriesTypes()
{
  return array(
    "open",
    "women",
    "mixed",
    "master open",
    "master women",
    "master mixed",
    "grand master",
    "U19 open",
    "U19 women",
    "U19 mixed",
    "U23 open",
    "U23 women",
    "U23 mixed",
    "junior open",
    "junior women"
  );
}

/**
 * Get all teams playing in given division.
 * @param int $seriesId uo_series.series_id
 * @param boolean $orderbyseeding TRUE if order by seeding otherwise order by name.
 * @return Array array of teams.
 */
function SeriesTeams($seriesId, $orderbyseeding = false)
{
  $query = sprintf(
    "SELECT DISTINCT t.team_id, t.name, t.abbreviation, t.club, cl.name AS clubname,
			t.country, c.name AS countryname, t.rank, c.flagfile,
			c.flagfile
			FROM uo_team t
			LEFT JOIN uo_series ser ON(ser.series_id=t.series)
			LEFT JOIN (SELECT team, ordering, pool, name FROM uo_team_pool tp
				LEFT JOIN uo_pool p ON(tp.pool=p.pool_id)
				ORDER BY ordering DESC) AS tp ON(t.team_id=tp.team)
			LEFT JOIN uo_club cl ON(cl.club_id=t.club)
			LEFT JOIN uo_country c ON(c.country_id=t.country)
			WHERE t.series = '%d'
			",
    (int)($seriesId)
  );

  if ($orderbyseeding) {
    $query .= " ORDER BY t.rank, t.name, t.team_id";
  } else {
    $query .= " ORDER BY t.name, t.team_id";
  }
  return DBQueryToArray($query);
}

/**
 * Get aggregated team stats and points for a series in one query.
 * @param int $seriesId uo_series.series_id
 * @return array keyed by team_id with games/wins/draws/losses/scores/against.
 */
function SeriesTeamStatsPoints($seriesId)
{
  $query = sprintf(
    "
    SELECT team_id,
      SUM(games) AS games,
      SUM(wins) AS wins,
      SUM(draws) AS draws,
      SUM(losses) AS losses,
      SUM(scores) AS scores,
      SUM(against) AS against
    FROM (
      SELECT g.hometeam AS team_id,
        1 AS games,
        (g.homescore > g.visitorscore) AS wins,
        (g.homescore = g.visitorscore) AS draws,
        (g.homescore < g.visitorscore) AS losses,
        g.homescore AS scores,
        g.visitorscore AS against
      FROM uo_game g
      LEFT JOIN uo_game_pool gp ON (g.game_id=gp.game)
      LEFT JOIN uo_pool p ON (gp.pool=p.pool_id)
      WHERE p.series=%d AND gp.timetable=1 AND g.isongoing=0 AND g.hasstarted>0
      UNION ALL
      SELECT g.visitorteam AS team_id,
        1 AS games,
        (g.visitorscore > g.homescore) AS wins,
        (g.visitorscore = g.homescore) AS draws,
        (g.visitorscore < g.homescore) AS losses,
        g.visitorscore AS scores,
        g.homescore AS against
      FROM uo_game g
      LEFT JOIN uo_game_pool gp ON (g.game_id=gp.game)
      LEFT JOIN uo_pool p ON (gp.pool=p.pool_id)
      WHERE p.series=%d AND gp.timetable=1 AND g.isongoing=0 AND g.hasstarted>0
    ) AS totals
    GROUP BY team_id
    ",
    (int)$seriesId,
    (int)$seriesId
  );

  $rows = DBQueryToArray($query);
  $stats = array();
  foreach ($rows as $row) {
    $stats[$row['team_id']] = $row;
  }
  return $stats;
}

/**
 * Get all teams in given division without pool.
 * @param int $seriesId uo_series.series_id
 * @return array of teams.
 */
function SeriesTeamsWithoutPool($seriesId)
{
  $query = sprintf(
    "SELECT pj.team_id, pj.name, pj.club, club.name as clubname, pj.rank 
		FROM uo_team pj
		LEFT JOIN uo_team_pool pjs ON (pj.team_id=pjs.team)
		LEFT JOIN uo_club club ON (pj.club=club.club_id)	
		WHERE pj.series = %d AND pjs.pool IS NULL
		ORDER BY pj.rank ASC",
    (int)$seriesId
  );
  return DBQueryToArray($query);
}

function Series($filter = null, $ordering = null)
{
  if (!isset($ordering)) {
    $ordering = array("series.name" => "ASC");
  }
  $tables = array("uo_series" => "series", "uo_season" => "season");
  $orderby = CreateOrdering($tables, $ordering);
  $where = CreateFilter($tables, $filter);
  $query = sprintf("SELECT series_id, series.name as name, season.name as seasonname, series.season
	FROM uo_series series LEFT JOIN uo_season season ON (series.season=season.season_id)
	$where $orderby");
  return DBQuery(trim($query));
}

/**
 * Get all player playing in given division.
 * @param int $seriesId uo_series.series_id
 * @return Array array of players.
 */
function SeriesAllPlayers($seriesId)
{
  $query = sprintf(
    "SELECT p.player_id, p.accreditation_id, p.profile_id FROM uo_player p
			LEFT JOIN uo_team t ON (p.team=t.team_id)
			LEFT JOIN uo_series ser ON (t.series=ser.series_id)
			WHERE ser.series_id='%d'",
    (int) $seriesId
  );
  return DBQueryToArray($query);
}

/**
 * Get name for given division.
 * @param int $seriesId uo_series.series_id
 * @return string The division name.
 */
function SeriesName($serieId)
{
  $query = sprintf(
    "SELECT name FROM uo_series WHERE series_id=%d",
    (int)$serieId
  );

  return U_(DBQueryToValue($query));
}

/**
 * Get season name for given division.
 * @param int $seriesId uo_series.series_id
 * @return string The season name.
 */
function SeriesSeasonName($serieId)
{
  $query = sprintf(
    "SELECT s.name FROM uo_series ser
		LEFT JOIN uo_season s ON(s.season_id=ser.season)
		WHERE ser.series_id=%d",
    (int)$serieId
  );

  return U_(DBQueryToValue($query));
}

/**
 * Get season id for given division.
 * @param int $seriesId uo_series.series_id
 * @return string The season id.
 */
function SeriesSeasonId($serieId)
{
  $query = sprintf(
    "SELECT s.season_id FROM uo_series ser
		LEFT JOIN uo_season s ON(s.season_id=ser.season)
		WHERE ser.series_id=%d",
    (int)$serieId
  );

  return DBQueryToValue($query);
}

/**
 * Get division score board.
 * @param int $seriesId uo_series.series_id
 * @param string $sorting one of: "total", "goal", "pass", "games", "team", "name", "callahan"  
 * @param int $limit Numbers of rows returned, 0 if unlimited
 * @return mysqli_result array of players.
 */
function SeriesScoreBoard($seriesId, $sorting, $limit)
{
  $query = sprintf(
    "
		SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done, 
		COALESCE(t1.callahan,0) AS callahan, COALESCE(s.fedin,0) AS fedin, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, pel.games 
		FROM uo_player AS p 
		LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m 
			LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game)
			LEFT JOIN uo_pool pool ON(ps.pool=pool.pool_id)
			LEFT JOIN uo_game AS g1 ON (ps.game=g1.game_id)
			WHERE pool.series=%d AND ps.timetable=1 AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer)
		LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1 
			LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game)
			LEFT JOIN uo_pool pool ON(ps1.pool=pool.pool_id)
			LEFT JOIN uo_game AS g2 ON (ps1.game=g2.game_id)
			WHERE pool.series=%d AND ps1.timetable=1 AND m1.scorer IS NOT NULL AND g2.isongoing=0  AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1)
		LEFT JOIN  (SELECT m2.assist AS assist, COUNT(*) AS fedin 
			FROM uo_goal AS m2 LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game) 
			LEFT JOIN uo_game AS g3 ON (ps2.game=g3.game_id)
			LEFT JOIN uo_pool pool ON(ps2.pool=pool.pool_id)
			WHERE pool.series=%d AND ps2.timetable=1 AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist) 
		LEFT JOIN uo_team AS j ON (p.team=j.team_id) 
		LEFT JOIN (SELECT up.player, COUNT(*) AS games 
			FROM uo_played up
			LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
			LEFT JOIN uo_pool pool ON(g4.pool=pool.pool_id)
			WHERE pool.series=%d AND g4.isongoing=0 
			GROUP BY player) AS pel ON (p.player_id=pel.player) 
		WHERE pel.games > 0 AND j.series=%d",
    (int)$seriesId,
    (int)$seriesId,
    (int)$seriesId,
    (int)$seriesId,
    (int)$seriesId
  );

  switch ($sorting) {
    case "total":
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "goal":
      $query .= " ORDER BY done DESC, total DESC, fedin DESC, lastname ASC";
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

    case "callahan":
      $query .= " ORDER BY callahan DESC, total DESC, lastname ASC";
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

/**
 * Get division defense board.
 * @param int $seriesId uo_series.series_id
 * @param string $sorting one of: "total", "games", "team", "name", "callahan"  
 * @param int $limit Numbers of rows returned, 0 if unlimited
 * @return mysqli_result array of players.
 */
function SeriesDefenseBoard($seriesId, $sorting, $limit)
{
  $query = sprintf(
    "
		SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS deftotal, 
		pel.games 
		FROM uo_player AS p 
		LEFT JOIN (SELECT m.author AS author, COUNT(*) AS done FROM uo_defense AS m 
			LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game)
			LEFT JOIN uo_pool pool ON(ps.pool=pool.pool_id)
			LEFT JOIN uo_game AS g1 ON (ps.game=g1.game_id)
			WHERE pool.series=%d AND ps.timetable=1 AND g1.isongoing=0 GROUP BY author) AS t ON (p.player_id=t.author) 
		LEFT JOIN uo_team AS j ON (p.team=j.team_id) 
		LEFT JOIN (SELECT up.player, COUNT(*) AS games 
			FROM uo_played up
			LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
			LEFT JOIN uo_pool pool ON(g4.pool=pool.pool_id)
			WHERE pool.series=%d AND g4.isongoing=0 
			GROUP BY player) AS pel ON (p.player_id=pel.player) 
		WHERE pel.games > 0 AND j.series=%d",
    (int)$seriesId,
    (int)$seriesId,
    (int)$seriesId
  );

  switch ($sorting) {
    case "deftotal":
      $query .= " ORDER BY deftotal DESC, lastname ASC";
      break;

    case "games":
      $query .= " ORDER BY games DESC, deftotal DESC, lastname ASC";
      break;

    case "team":
      $query .= " ORDER BY teamname ASC, deftotal DESC, lastname ASC";
      break;

    case "name":
      $query .= " ORDER BY firstname,lastname ASC, deftotal DESC";
      break;

    case "callahan":
      $query .= " ORDER BY callahan DESC, deftotal DESC, lastname ASC";
      break;

    default:
      $query .= " ORDER BY deftotal DESC, lastname ASC";
      break;
  }

  if ($limit > 0) {
    $query .= " limit $limit";
  }

  return DBQuery($query);
}

/**
 * Get division spirit score per category.
 * @param int $seriesId uo_series.series_id
 * @return Array array of spirit scores per team.

 */
function SeriesSpiritBoard($seriesId)
{
  $factorRows = DBQueryToArray("SELECT category_id, factor FROM uo_spirit_category");
  $factor = array();
  foreach ($factorRows as $row) {
    $factor[$row['category_id']] = $row['factor'];
  }

  $query = sprintf(
    "SELECT st.team_id, te.name, st.category_id, st.value, pool.series
      FROM uo_team AS te
      LEFT JOIN uo_spirit_score AS st ON (te.team_id=st.team_id)
      LEFT JOIN uo_game_pool AS gp ON (st.game_id=gp.game)
      LEFT JOIN uo_pool pool ON(gp.pool=pool.pool_id)
      LEFT JOIN uo_game AS g1 ON (gp.game=g1.game_id)
      WHERE pool.series=%d AND gp.timetable=1 AND g1.isongoing=0 AND g1.hasstarted>0
      ORDER BY st.team_id, st.category_id",
    $seriesId
  );

  $scores = DBQueryToArray($query);
  $last_team = null;
  $last_category = null;
  $averages = array();
  $total = 0;
  $sum = 0;
  $games = 0;
  foreach ($scores as $row) {
    if ($last_team != $row['team_id'] || $last_category != $row['category_id']) {
      if (!is_null($last_category)) {
        $teamline[$last_category] = SafeDivide($sum, $games);
        $factorValue = isset($factor[$last_category]) ? $factor[$last_category] : 0;
        $total += SafeDivide($factorValue * $sum, $games);
      }
      if ($last_team != $row['team_id']) {
        if (!is_null($last_team)) {
          $teamline['total'] = $total;
          $teamline['games'] = $games;
          $averages[$last_team] = $teamline;
          $total = 0;
        }
        $teamline = array('teamname' => $row['name']);
      }
      $sum = 0;
      $games = 0;
      $last_team = $row['team_id'];
      $last_category = $row['category_id'];
    }
    $sum += $row['value'];
    ++$games;
  }
  if (!is_null($last_team)) {
    $teamline[$last_category] = SafeDivide($sum, $games);
    $factorValue = isset($factor[$last_category]) ? $factor[$last_category] : 0;
    $total += SafeDivide($factorValue * $sum, $games);
    $teamline['total'] = $total;
    $teamline['games'] = $games;
    $averages[$last_team] = $teamline;
  }
  return $averages;
}

/**
 * Get games where teams are missing spirit points in given division.
 * @param int $seriesId uo_series.series_id
 * @return Array array of missing spirit point rows.
 */
function SeriesMissingSpiritPoints($seriesId)
{
  $query = sprintf(
    "SELECT missing.team_id, missing.teamname, missing.giver_team_id, missing.giver_teamname,
      missing.opponent_name, missing.home_name, missing.visitor_name, missing.game_id, missing.gamename, missing.time
      FROM (
        SELECT g.game_id, g.time, g.hometeam AS team_id, ht.name AS teamname,
          g.visitorteam AS giver_team_id, vt.name AS giver_teamname,
          vt.name AS opponent_name, ht.name AS home_name, vt.name AS visitor_name, sn.name AS gamename
        FROM uo_game g
        LEFT JOIN uo_game_pool gp ON (g.game_id=gp.game)
        LEFT JOIN uo_pool pool ON (gp.pool=pool.pool_id)
        LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
        LEFT JOIN uo_team ht ON (g.hometeam=ht.team_id)
        LEFT JOIN uo_team vt ON (g.visitorteam=vt.team_id)
        LEFT JOIN uo_scheduling_name sn ON (g.name=sn.scheduling_id)
        LEFT JOIN (SELECT DISTINCT game_id, team_id FROM uo_spirit_score) ssc
          ON (ssc.game_id=g.game_id AND ssc.team_id=g.hometeam)
        WHERE ser.series_id=%d AND gp.timetable=1 AND g.isongoing=0 AND g.hasstarted>0 AND ssc.game_id IS NULL
        UNION ALL
        SELECT g.game_id, g.time, g.visitorteam AS team_id, vt.name AS teamname,
          g.hometeam AS giver_team_id, ht.name AS giver_teamname,
          ht.name AS opponent_name, ht.name AS home_name, vt.name AS visitor_name, sn.name AS gamename
        FROM uo_game g
        LEFT JOIN uo_game_pool gp ON (g.game_id=gp.game)
        LEFT JOIN uo_pool pool ON (gp.pool=pool.pool_id)
        LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
        LEFT JOIN uo_team ht ON (g.hometeam=ht.team_id)
        LEFT JOIN uo_team vt ON (g.visitorteam=vt.team_id)
        LEFT JOIN uo_scheduling_name sn ON (g.name=sn.scheduling_id)
        LEFT JOIN (SELECT DISTINCT game_id, team_id FROM uo_spirit_score) ssc
          ON (ssc.game_id=g.game_id AND ssc.team_id=g.visitorteam)
        WHERE ser.series_id=%d AND gp.timetable=1 AND g.isongoing=0 AND g.hasstarted>0 AND ssc.game_id IS NULL
      ) AS missing
      ORDER BY missing.teamname, missing.time, missing.game_id",
    (int)$seriesId,
    (int)$seriesId
  );

  return DBQueryToArray($query);
}

/**
 * Get all games in given division.
 * @param int $seriesId uo_series.series_id
 * @return Array array of games.
 */
function SeriesAllGames($seriesId)
{
  $query = sprintf(
    "
		SELECT gp.game
		FROM uo_game_pool gp 
		LEFT JOIN uo_pool pool ON (pool.pool_id=gp.pool) 
		LEFT JOIN uo_series ser ON (ser.series_id=pool.series)
		WHERE ser.series_id='%d' AND gp.timetable=1
		ORDER BY gp.game",
    (int) $seriesId
  );

  return DBQueryToArray($query);
}

/**
 * Get all information (uo_series.*) for given division.
 * @param int $seriesId uo_series.series_id
 * @return PHP array of information. 
 */
function SeriesInfo($seriesId)
{
  $query = sprintf(
    "SELECT * FROM uo_series WHERE series_id=%d",
    (int)$seriesId
  );
  return DBQueryToRow($query, true);
}

/**
 * Get all enrolled teams in given division.
 * 
 * Access level: Division admin
 * 
 * @param int $seriesId uo_series.series_id
 * @return mysql array of information. 
 */
function SeriesEnrolledTeams($seriesId)
{
  if (hasEditTeamsRight($seriesId)) {
    $query = sprintf(
      "SELECT team.*, user.name AS username FROM uo_enrolledteam team
		LEFT JOIN uo_users user ON(team.userid=user.userid)
		WHERE team.series=%d ORDER BY team.enroll_time ASC",
      (int)$seriesId
    );
    return DBQuery($query);
  } else die("Insufficient rights to get all enrolled teams");
}

/**
 * Delete division.
 * 
 * Access level: Division admin
 * 
 * @param int $seriesId uo_series.series_id
 * @return mysql array of information. 
 */
function DeleteSeries($seriesId)
{
  $seriesInfo = SeriesInfo($seriesId);
  if (hasEditSeasonSeriesRight($seriesInfo['season'])) {
    Log2("series", "delete", SeriesName($seriesId));
    $query = sprintf(
      "DELETE FROM uo_series WHERE series_id='%d'",
      (int)$seriesId
    );

    return DBQuery($query);
  }
}

/**
 * Add division.
 * 
 * Access level: Event admin
 * 
 * @param int $params array of uo_series.* data
 * @return new uo_series.series_id. 
 */
function AddSeries($params)
{
  if (hasEditSeasonSeriesRight($params['season'])) {
    $query = sprintf(
      "INSERT INTO uo_series
				(name,type,ordering,season,valid,pool_template)
				VALUES ('%s','%s','%s','%s',%d,%d)",
      DBEscapeString($params['name']),
      DBEscapeString($params['type']),
      DBEscapeString($params['ordering']),
      DBEscapeString($params['season']),
      (int)$params['valid'],
      (int)$params['pool_template']
    );

    $id = DBQueryInsert($query);
    Log1("series", "add", $id);
    return $id;
  }
}

/**
 * Change division data.
 * 
 * Access level: Event admin
 * 
 * @param int $params array of uo_series.* data
 */
function SetSeries($params)
{
  $seriesInfo = SeriesInfo($params['series_id']);
  if (hasEditSeasonSeriesRight($seriesInfo['season'])) {
    $query = sprintf(
      "
			UPDATE uo_series SET
			name='%s', type='%s', ordering='%s', valid=%d,
			pool_template=%d
			WHERE series_id=%d",
      DBEscapeString($params['name']),
      DBEscapeString($params['type']),
      DBEscapeString($params['ordering']),
      (int)$params['valid'],
      (int)$params['pool_template'],
      (int)$params['series_id']
    );

    return DBQuery($query);
  }
}

/**
 * Change division name.
 * 
 * Access level: Event admin
 * 
 * @param int $params array of uo_series.* data
 * @param string $name new name for division.
 */
function SetSeriesName($seriesId, $name)
{
  $seriesInfo = SeriesInfo($seriesId);
  if (hasEditSeasonSeriesRight($seriesInfo['season'])) {
    $query = sprintf(
      "
			UPDATE uo_series SET name='%s' WHERE series_id='%s'",
      DBEscapeString($name),
      DBEscapeString($seriesId)
    );

    return DBQuery($query);
  }
}

/**
 * Get enrolled team by id.
 *  
 * Access level: Division admin
 *  
 * @param int $seriesId uo_series.series_id
 * @param int $id uo_enrolledteam.id
 * @return php array of uo_enrolledteam.*
 */
function SeriesEnrolledTeamById($seriesId, $id)
{
  if (hasEditTeamsRight($seriesId)) {
    $query = sprintf(
      "SELECT * FROM uo_enrolledteam WHERE series=%d and id=%d",
      (int)$seriesId,
      (int)$id
    );
    return DBQueryToRow($query);
  } else die("Insufficient rights to get all enrolled teams");
}

/**
 * Get enrolled teams by user_id.
 *  
 * Access level: valid user
 *  
 * @param int $seriesId uo_series.series_id
 * @param int $userid uo_user.userid
 * @return mysqli_result of teams.
 */
function SeriesEnrolledTeamsByUser($seriesId, $userid)
{
  if ($userid == 'anonymous') die("Can not enroll for anonymous");
  if ($userid == $_SESSION['uid'] || hasEditTeamsRight($seriesId)) {
    $query = sprintf(
      "SELECT team.*, user.name AS username FROM uo_enrolledteam team
				LEFT JOIN uo_users user ON(team.userid=user.userid)
				WHERE team.series=%d and team.userid='%s' ORDER BY team.enroll_time ASC",
      (int)$seriesId,
      DBEscapeString($userid)
    );
    return DBQuery($query);
  } else die("Insufficient rights to get all enrolled teams for other users");
}

/**
 * Enroll team into division.
 *   
 * Access level: valid user
 *  
 * @param int $seriesId uo_series.series_id
 * @param string $userid uo_user.userid will be granted as team admin.
 * @param string $name name of team
 * @param int $club uo_club.club_id
 * @param int $country uo_country.country_id
 * @return uo_enrolledteam.id
 */
function AddSeriesEnrolledTeam($seriesId, $userid, $name, $club, $country)
{
  if ($userid == 'anonymous') die("Can not enroll for anonymous");
  if ($userid == $_SESSION['uid'] || hasEditTeamsRight($seriesId)) {
    $query = sprintf(
      "INSERT INTO uo_enrolledteam (series, userid, name, clubname, countryname, enroll_time)
				VALUES (%d, '%s', '%s', '%s', '%s', now())",
      (int)$seriesId,
      DBEscapeString($userid),
      DBEscapeString($name),
      DBEscapeString($club),
      DBEscapeString($country)
    );
    $id = DBQueryInsert($query);
    Log1("enrolment", "add", $seriesId, "$name");
    return $id;
  } else die("Insufficient rights to add enrolled teams for other users");
}

/**
 * Delete enrolled team.
 *
 * Access level: user enrolled the team or division admin
 *
 * @param int $seriesId uo_series.series_id
 * @param string $userid uo_user.userid 
 * @param int $id uo_enrolledteam.id
 */
function RemoveSeriesEnrolledTeam($seriesId, $userid, $id)
{
  if ($userid == 'anonymous') die("Can not remove enrolled team for anonymous");
  if ($userid == $_SESSION['uid'] || hasEditTeamsRight($seriesId)) {
    if (!hasEditTeamsRight($seriesId)) {
      $query = "DELETE FROM uo_enrolledteam WHERE series=%d and userid='%s' and id=%d and status=0";
      $query = sprintf(
        $query,
        (int)$seriesId,
        DBEscapeString($userid),
        (int)$id
      );
    } else {
      $query = "DELETE FROM uo_enrolledteam WHERE series=%d and id=%d";
      $query = sprintf(
        $query,
        (int)$seriesId,
        (int)$id
      );
    }
    Log1("enrolment", "delete", $seriesId, $id);
    return DBQuery($query);
  } else die("Insufficient rights to delete enrolled teams for other users");
}

/**
 * Confirm team enrollment into division.
 * 
 * Access level: Division admin
 *
 * @param int $seriesId uo_series.series_id
 * @param int $id uo_enrolledteam.id
 * @return int uo_team.team_id
 */
function ConfirmEnrolledTeam($seriesId, $id)
{
  if (hasEditTeamsRight($seriesId)) {
    $teaminfo = SeriesEnrolledTeamById($seriesId, $id);
    $clubId = ClubId($teaminfo['clubname']);
    $countryId = CountryId($teaminfo['countryname']);

    //clubname not found
    if (!empty($teaminfo['clubname']) && $clubId == -1) {
      $clubId = AddClub($seriesId, $teaminfo['clubname']);
    }

    $query = sprintf(
      "INSERT INTO uo_team (name, series, valid) VALUES ('%s', %d, 1)",
      DBEscapeString($teaminfo['name']),
      (int)$seriesId
    );

    $teamId = DBQueryInsert($query);

    //update team/country info if available
    if ($countryId) {
      DBQuery("UPDATE uo_team SET country=$countryId WHERE team_id=$teamId");
    }
    if ($clubId) {
      DBQuery("UPDATE uo_team SET club=$clubId WHERE team_id=$teamId");
    }

    if ($countryId && !$clubId) {
      $countryinfo = CountryInfo($countryId);
      DBQuery("UPDATE uo_team SET abbreviation=UPPER('" . $countryinfo['abbreviation'] . "') WHERE team_id=$teamId");
    } else {
      $allteams = SeriesTeams($seriesId);
      $notfound = true;
      $letters = 3;
      $num = 0;
      $abb = substr($teaminfo['name'], 0, $letters);
      while ($notfound) {
        $notfound = false;

        foreach ($allteams as $t) {
          if ($abb == $t['abbreviation']) {
            $notfound = true;
            break;
          }
        }
        if ($notfound) {
          $letters++;
          if ($letters > 6) {
            $num++;
            $abb = substr($teaminfo['name'], 0, 5) . "$num";
          } else {
            $abb = substr($teaminfo['name'], 0, $letters);
          }
        }
      }
      DBQuery("UPDATE uo_team SET abbreviation=UPPER('" . DBEscapeString($abb) . "') WHERE team_id=$teamId");
    }


    $seriesInfo = SeriesInfo($seriesId);
    AddSeasonUserRole($teaminfo['userid'], "teamadmin:" . $teamId, $seriesInfo['season']);
    $query = sprintf(
      "UPDATE uo_enrolledteam SET status=1 WHERE id=%d",
      (int)$id
    );

    DBQuery($query);

    Log1("enrolment", "confirm", $seriesId, $teamId);
    return $teamId;
  } else die("Insufficient rights to delete enrolled teams for other users");
}

/**
 * Test if division can be deleted.
 * @param int $seriesId uo_series.series_id
 * @return TRUE if division can be deleted, FALSE otherwise.
 */
function CanDeleteSeries($seriesId)
{
  $query = sprintf(
    "SELECT count(*) FROM uo_pool WHERE series='%s'",
    DBEscapeString($seriesId)
  );
  $result = DBQueryToValue($query);
  if ($result == 0) {
    $query = sprintf(
      "SELECT count(*) FROM uo_team WHERE series='%s'",
      DBEscapeString($seriesId)
    );
    $result = DBQueryToValue($query);
    return $result == 0;
  } else return false;
}

/**
 * Get list of team admins for given division
 * 
 * Access level: Team admin
 *
 * @param int $seriesId uo_series.series_id
 * @return PHP array of users
 */
function SeriesTeamResponsibles($seriesId)
{
  $seasonrights = getEditSeasons($_SESSION['uid']);
  $season = SeriesSeasonId($seriesId);
  if (isset($seasonrights[$season])) {
    $query = sprintf(
      "SELECT u.userid, u.name, u.email, j.name AS teamname
			FROM uo_users u
			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
			LEFT JOIN uo_team j ON (SUBSTRING_INDEX(up.value, ':', -1)=j.team_id)
			WHERE j.series=%d AND SUBSTRING_INDEX(up.value,':',1)='teamadmin'
			GROUP BY u.userid, u.name, u.email, j.name",
      (int)$seriesId
    );

    return DBQueryToArray($query);
  } else {
    die('Insufficient rights');
  }
}

/**
 * Copy teams from one division to another.
 * 
 * Access level: Season admin
 * 
 * @param int $to uo_series.series_id
 * @param int $from uo_series.series_id
 * 
 */
function SeriesCopyTeams($to, $from)
{
  if (isSeasonAdmin(SeriesSeasonId($to))) {
    $teams = SeriesTeams($from);
    foreach ($teams as $team) {
      $query = sprintf(
        "INSERT INTO uo_team(name, club, country, uo_team.rank, abbreviation, valid, series )
      			VALUES ('%s',%d,%d,%d,'%s',1,%d)",
        DBEscapeString($team['name']),
        (int) $team['club'],
        (int) $team['country'],
        (int) $team['rank'],
        DBEscapeString($team['abbreviation']),
        (int) $to
      );
      DBQuery($query);
    }
  } else {
    die('Insufficient rights');
  }
}
