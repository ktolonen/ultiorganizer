<?php
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/swissdraw.functions.php';

/**
 * Gets all pools matching with given conditions.
 * @param array $filter
 * @param array $ordering
 */
function Pools($filter=null, $ordering=null) {
  if (!isset($ordering)) {
    $ordering = array("season.starttime" => "ASC", "series.ordering" => "ASC", "pool.ordering" => "ASC");
  }
  $tables = array("uo_pool" => "pool", "uo_series" => "series", "uo_season" => "season");
  $orderby = CreateOrdering($tables, $ordering);
  $where = CreateFilter($tables, $filter);
  $query = "SELECT pool_id, pool.name, pool.type, pool.series
    FROM uo_pool pool LEFT JOIN uo_series series ON (pool.series=series.series_id)
    LEFT JOIN uo_season season ON (series.season=season.season_id)
    $where
    $orderby";
  return DBQuery(trim($query));
}

/**
 * Information of given pool.
 *
 * @param int $poolId uo_pool.pool_id
 * @return php array of pool information.
 */
function PoolInfo($poolId) {
  $query = sprintf("SELECT pool.*, ser.name AS seriesname, ser.season FROM uo_pool pool
        LEFT JOIN uo_series ser ON(pool.series=ser.series_id)
        WHERE pool.pool_id=%d",
  (int)$poolId);
  return DBQueryToRow($query, true);
}

/**
 * Get followers for given pool based on moves (uo_moveteams.frompool).
 * @param int $poolId uo_pool.pool_id
 * @return php array of pool followers.
 */
function PoolFollowersArray($poolId) {
  $ids = array();
  $query = sprintf("SELECT topool FROM uo_moveteams WHERE frompool=%d GROUP BY topool",
  (int)$poolId);
  $followers = DBQueryToArray($query);

  foreach($followers as $follower){
    $ids[] = $follower['topool'];
    $ids = array_merge($ids, PoolFollowersArray($follower['topool']));
  }
  return $ids;
}

/**
 * Get followers for given pool based on follower id (uo_pool.follower).
 * @param int $poolId uo_pool.pool_id
 * @return php array of pool followers.
 */
function PoolPlayoffFollowersArray($poolId) {
  $ids = array();

  $query = sprintf("SELECT follower FROM uo_pool WHERE pool_id=%d AND follower IS NOT NULL",
  (int)$poolId);
  $followers = DBQueryToArray($query);

  foreach($followers as $follower){
    $ids[] = $follower['follower'];
    $ids = array_merge($ids, PoolPlayoffFollowersArray($follower['follower']));
  }
  return $ids;
}

/**
 * Get playoff 1st round pool for given pool based on follower id (uo_pool.follower).
 * @param int $poolId uo_pool.pool_id
 * @return root pool id.
 */
function PoolPlayoffRoot($poolId) {
  $ids = array();

  $query = sprintf("SELECT pool_id FROM uo_pool WHERE follower=%d",
  (int)$poolId);
  $root = DBQueryToValue($query);

  if($root > 0){
    $poolId = PoolPlayoffRoot($root);
  }

  return $poolId;
}

/**
 * Information of given pool template.
 *
 * @param int $poolId uo_pooltemplate.template_id
 * @return php array of pool template information.
 */
function PoolTemplateInfo($poolId) {
  $query = sprintf("SELECT * FROM uo_pooltemplate WHERE template_id=%d",
  (int)$poolId);
  return DBQueryToRow($query);
}

/**
 * Get all pool templates.
 *
 * @return php array of pool template information.
 */
function PoolTemplates() {
  $query = "SELECT * FROM uo_pooltemplate ORDER BY name ASC";
  return DBQueryToArray($query);
}

/**
 * Gives shorter name for given pool.
 * @param int $poolId uo_pool.pool_id
 * @return short name.
 */
function PoolShortName($poolId) {
  $poolname=PoolName($poolId);
  $forbiddenwords=array("Playoff", "Playout", "Swissdraw");
  foreach ($forbiddenwords as $word) {
    $newname=str_replace($word,"",$poolname);
    if (empty($newname)) {$newname=$poolname;} else {$poolname=$newname;}
  }
  $poolname=str_replace("Semifinals","Semis",$poolname);
  $poolname=str_replace("Quarterfinals","Quarters",$poolname);
  return $poolname;
}

/**
 * Get name for given pool.
 * @param int $poolId uo_pool.pool_id
 * @return Pool name.
 */
function PoolName($poolId){
  $query = sprintf("SELECT pool.name FROM uo_pool pool
        WHERE pool_id=%d",
  (int)$poolId);

  return U_(DBQueryToValue($query));
}

/**
 * Get division name for given pool.
 * @param int $poolId uo_pool.pool_id
 * @return Division name.
 */
function PoolSeriesName($poolId){
  $query = sprintf("SELECT ser.name FROM uo_pool pool
            LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
        WHERE pool_id=%d",
  (int)$poolId);

  return U_(DBQueryToValue($query));
}

/**
 * Get all pools in database.
 *
 * @return mysql array of pools
 */
function PoolListAll() {
  $query = sprintf("SELECT pool.pool_id, pool.name, ser.name AS seriesname, season.name AS seasonname
    FROM uo_pool pool
    LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
    LEFT JOIN uo_season season ON (ser.season=season.season_id)
    ORDER BY pool.name, ser.name, season.name");
  return DBQuery($query);
}

/**
 * Get list of pool types.
 * @return Hardcoded PHP array of pool types.
 */
function PoolTypes() {
  return array(
      "roundrobin"=>1,
      "playoff"=>2,
      "swissdraw"=>3,
      "crossmatch"=>4);
}

/**
 * Get all teams in given pool.
 *
 * @param int $poolId uo_pool.pool_id
 * @param string $order Order by "seed", "name", "rank". Default is "rank".
 * @return php array of teams
 */
function PoolTeams($poolId, $order="rank"){
  $query = sprintf("SELECT uo_team.team_id, uo_team.name, uo_team.club, club.name AS clubname,
        uo_team_pool.Rank, uo_team.country, c.name AS countryname, uo_team.rank AS seed,
        c.flagfile, uo_team_pool.activerank
        FROM uo_team
        RIGHT JOIN uo_team_pool ON (uo_team.team_id=uo_team_pool.team)
        LEFT JOIN uo_club club ON (club=club.club_id)
        LEFT JOIN uo_country c ON (uo_team.country=c.country_id)
        WHERE uo_team_pool.pool = '%s'",
          (int)$poolId);

  switch($order) {
    case "seed":
      $query .= " ORDER BY uo_team_pool.Rank ASC";
      break;
    case "name":
      $query .= " ORDER BY uo_team.name, uo_team.team_id";
      break;
    case "rank":
      $query .= " ORDER BY uo_team_pool.activerank ASC, uo_team_pool.Rank ASC, uo_team.team_id";
      break;
    default:
      $query .= " ORDER BY uo_team_pool.activerank ASC, uo_team_pool.Rank ASC, uo_team.team_id ";
      break;
  }

  return DBQueryToArray($query);
}

/**
 * Get placement name for position in pool.
 *
 * @param int $poolId uo_pool.pool_id
 * @param int $pos position to get name
 * @param boolean $ordinal TRUE if ordinal string
 * @return name of position
 */
function PoolPlacementString($poolId, $pos, $ordinal=true){
  $ret = _("Unknown");
  $info = PoolInfo($poolId);
  if(intval($info['placementpool'])){
    $ppools = SeriesPlacementPoolIds($info['series']);
    $placementfrom = 1;
    foreach ($ppools as $ppool){
      $teams = PoolSchedulingTeams($ppool['pool_id']);
      if($info['pool_id']!=$ppool['pool_id']){
        for($i=1;$i<=count($teams);$i++){
          $moved = PoolMoveExist($ppool['pool_id'], $i);
          if(!$moved){
            $placementfrom++;
          }
        }
      }else{
        for($i=1;$i<=count($teams) && $i<$pos;$i++){
          $moved = PoolMoveExist($ppool['pool_id'], $i);
          if(!$moved){
            $placementfrom++;
          }
        }
        break;
      }
    }

    if($ordinal) {
      if($placementfrom==0){
        $ret = _("Unknown");
      }elseif($placementfrom==1){
        $ret = _("Gold");
      }elseif($placementfrom==2){
        $ret = _("Silver");
      }elseif($placementfrom==3){
        $ret = _("Bronze");
      }elseif($placementfrom>3){
        $ret = ordinal($placementfrom);
      }
    } else { $ret=$placementfrom; }
  }
  return $ret;
}

/**
 * Get all scheduling teams in given pool. Scheduling teams are real team replacements for scheduling purpose.
 *
 * @param int $poolId uo_pool.pool_id
 * @return php array of scheduling teams
 */
function PoolSchedulingTeams($poolId){
  $query = sprintf("SELECT s.name, s.scheduling_id
        FROM uo_moveteams m
        LEFT JOIN uo_scheduling_name s ON (s.scheduling_id=m.scheduling_id)
        WHERE m.topool = '%s'
        ORDER BY m.torank ASC, m.scheduling_id ASC",
    (int)$poolId);

  return DBQueryToArray($query);
}

/**
 * Get pool score board.
 *
 * @param int $poolId uo_pool.pool_id
 * @param string $sorting one of: "total", "goal", "pass", "games", "team", "name", "callahan"
 * @param int $limit Numbers of rows returned, 0 if unlimited
 * @return mysql array of players.
 */
function PoolScoreBoard($poolId, $sorting, $limit){
  $query = sprintf("
        SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done,
        COALESCE(t1.callahan,0) AS callahan, COALESCE(s.fedin,0) AS fedin, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, pel.games
        FROM uo_player AS p
        LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m
            LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game)
            LEFT JOIN uo_game AS g1 ON (ps.game=g1.game_id)
            WHERE ps.pool=%d AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer)
        LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1
            LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game)
            LEFT JOIN uo_game AS g2 ON (ps1.game=g2.game_id)
            WHERE ps1.pool=%d AND m1.scorer IS NOT NULL AND g2.isongoing=0 AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1)
        LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin
            FROM uo_goal AS m2
            LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game)
            LEFT JOIN uo_game AS g3 ON (ps2.game=g3.game_id)
            WHERE ps2.pool=%d AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist)
        LEFT JOIN uo_team AS j ON (p.team=j.team_id)
        LEFT JOIN (SELECT up.player, COUNT(*) AS games
            FROM uo_played up
            LEFT JOIN uo_game_pool AS ps4 ON (up.game=ps4.game)
            LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
            WHERE ps4.pool=%d AND g4.isongoing=0 GROUP BY player)
            AS pel ON (p.player_id=pel.player)
        WHERE pel.games > 0",
  (int)$poolId,
  (int)$poolId,
  (int)$poolId,
  (int)$poolId,
  (int)$poolId);

  switch($sorting){
    case "total":
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "goal":
      $query .= " ORDER BY done DESC, total DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "pass":
      $query .= " ORDER BY fedin DESC, total DESC, done DESC, lastname ASC, p.player_id";
      break;

    case "games":
      $query .= " ORDER BY games DESC, total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "team":
      $query .= " ORDER BY teamname ASC, total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "name":
      $query .= " ORDER BY firstname,lastname ASC, total DESC, done DESC, fedin DESC, p.player_id";
      break;

    case "callahan":
      $query .= " ORDER BY callahan DESC, total DESC, lastname ASC, p.player_id";
      break;

    default:
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;
  }

  if($limit > 0){
    $query .= " limit $limit";
  }

  return DBQuery($query);
}

/**
 * Get score board for list of pools.
 *
 * @param string $pools comma separated list of pools
 * @param string $sorting one of: "total", "goal", "pass", "games", "team", "name", "callahan"
 * @param int $limit Numbers of rows returned, 0 if unlimited
 * @return mysql array of players.
 */
function PoolsScoreBoard($pools, $sorting, $limit){

  $poolIds = mysql_real_escape_string(implode(",",$pools));

  $query = " SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done,
        COALESCE(t1.callahan,0) AS callahan, COALESCE(s.fedin,0) AS fedin, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, pel.games
        FROM uo_player AS p
        LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m
            LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game)
            LEFT JOIN uo_game AS g1 ON (ps.game=g1.game_id)
            WHERE ps.pool IN($poolIds) AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer)
        LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1
            LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game)
            LEFT JOIN uo_game AS g2 ON (ps1.game=g2.game_id)
            WHERE ps1.pool IN($poolIds) AND m1.scorer IS NOT NULL AND g2.isongoing=0 AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1)
        LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin
            FROM uo_goal AS m2 LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game)
            LEFT JOIN uo_game AS g3 ON (ps2.game=g3.game_id)
            WHERE ps2.pool IN($poolIds) AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist)
        LEFT JOIN uo_team AS j ON (p.team=j.team_id)
        LEFT JOIN (SELECT up.player, COUNT(*) AS games
            FROM uo_played up
            LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
            WHERE g4.pool IN($poolIds) AND g4.isongoing=0 GROUP BY player)
            AS pel ON (p.player_id=pel.player)
        WHERE pel.games > 0";

  switch($sorting){
    case "total":
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "goal":
      $query .= " ORDER BY done DESC, total DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "pass":
      $query .= " ORDER BY fedin DESC, total DESC, done DESC, lastname ASC, p.player_id";
      break;

    case "games":
      $query .= " ORDER BY games DESC, total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "team":
      $query .= " ORDER BY teamname ASC, total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "name":
      $query .= " ORDER BY firstname,lastname ASC, total DESC, done DESC, fedin DESC, p.player_id";
      break;

    case "callahan":
      $query .= " ORDER BY callahan DESC, total DESC, lastname ASC, p.player_id";
      break;

    default:
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;
  }

  if($limit > 0){
    $query .= " limit $limit";
  }

  return DBQuery($query);
}

/**
 * Get pool score board with defenses
 *
 * @param string $pools comma separated list of pools
 * @param string $sorting one of: "deftotal", "total", "goal", "pass", "games", "team", "name", "callahan"
 * @param int $limit Numbers of rows returned, 0 if unlimited
 * @return mysql array of players.
 */
function PoolsScoreBoardWithDefenses($pools, $sorting, $limit){
  $poolIds = mysql_real_escape_string(implode(",",$pools));

  $query = "
        SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done,
        COALESCE(t1.callahan,0) AS callahan, COALESCE(s.fedin,0) AS fedin, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, COALESCE(t3.deftotal,0) AS deftotal, pel.games
        FROM uo_player AS p
        LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m
            LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game)
            LEFT JOIN uo_game AS g1 ON (ps.game=g1.game_id)
            WHERE ps.pool IN($poolIds) AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer)
        LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1
            LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game)
            LEFT JOIN uo_game AS g2 ON (ps1.game=g2.game_id)
            WHERE ps1.pool IN($poolIds) AND m1.scorer IS NOT NULL AND g2.isongoing=0 AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1)
        LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin
            FROM uo_goal AS m2
            LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game)
            LEFT JOIN uo_game AS g3 ON (ps2.game=g3.game_id)
            WHERE ps2.pool IN($poolIds) AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist)
        LEFT JOIN (SELECT m3.author AS author, COUNT(*) AS deftotal FROM uo_defense AS m3 LEFT JOIN uo_game_pool AS ps3 ON (m3.game=ps3.game)
            LEFT JOIN uo_game AS g5 ON (ps3.game=g5.game_id)
            WHERE ps3.pool IN($poolIds) AND g5.isongoing=0 GROUP BY author) AS t3 ON (p.player_id=t3.author)
        LEFT JOIN uo_team AS j ON (p.team=j.team_id)
        LEFT JOIN (SELECT up.player, COUNT(*) AS games
            FROM uo_played up
            LEFT JOIN uo_game_pool AS ps4 ON (up.game=ps4.game)
            LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
            WHERE ps4.pool IN($poolIds) AND g4.isongoing=0 GROUP BY player)
            AS pel ON (p.player_id=pel.player)
        WHERE pel.games > 0";

  switch($sorting){
    case "deftotal":
      $query .= " ORDER BY deftotal DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "total":
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "goal":
      $query .= " ORDER BY done DESC, total DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "pass":
      $query .= " ORDER BY fedin DESC, total DESC, done DESC, lastname ASC, p.player_id";
      break;

    case "games":
      $query .= " ORDER BY games DESC, total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "team":
      $query .= " ORDER BY teamname ASC, total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "name":
      $query .= " ORDER BY firstname,lastname ASC, total DESC, done DESC, fedin DESC, p.player_id";
      break;

    case "callahan":
      $query .= " ORDER BY callahan DESC, total DESC, lastname ASC, p.player_id";
      break;

    default:
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;
  }

  if($limit > 0){
    $query .= " limit $limit";
  }

  return DBQuery($query);
}

/**
 * Get pool score board with defenses
 *
 * @param int $poolId uo_pool.pool_id
 * @param string $sorting one of: "deftotal", "total", "goal", "pass", "games", "team", "name", "callahan"
 * @param int $limit Numbers of rows returned, 0 if unlimited
 * @return mysql array of players.
 */
function PoolScoreBoardWithDefenses($poolId, $sorting, $limit){

  $query = sprintf("
        SELECT p.player_id, p.firstname, p.lastname, j.name AS teamname, COALESCE(t.done,0) AS done,
        COALESCE(t1.callahan,0) AS callahan, COALESCE(s.fedin,0) AS fedin, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS total, COALESCE(t3.deftotal,0) AS deftotal, pel.games
        FROM uo_player AS p
        LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m
            LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game)
            LEFT JOIN uo_game AS g1 ON (ps.game=g1.game_id)
            WHERE ps.pool=%d AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer)
        LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1
            LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game)
            LEFT JOIN uo_game AS g2 ON (ps1.game=g2.game_id)
            WHERE ps1.pool=%d AND m1.scorer IS NOT NULL AND g2.isongoing=0 AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1)
        LEFT JOIN (SELECT m2.assist AS assist, COUNT(*) AS fedin
            FROM uo_goal AS m2
            LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game)
            LEFT JOIN uo_game AS g3 ON (ps2.game=g3.game_id)
            WHERE ps2.pool=%d AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist)
        LEFT JOIN (SELECT m3.author AS author, COUNT(*) AS deftotal FROM uo_defense AS m3 LEFT JOIN uo_game_pool AS ps3 ON (m3.game=ps3.game)
            LEFT JOIN uo_game AS g5 ON (ps3.game=g5.game_id)
            WHERE ps3.pool=%d AND g5.isongoing=0 GROUP BY author) AS t3 ON (p.player_id=t3.author)
        LEFT JOIN uo_team AS j ON (p.team=j.team_id)
        LEFT JOIN (SELECT up.player, COUNT(*) AS games
            FROM uo_played up
            LEFT JOIN uo_game_pool AS ps4 ON (up.game=ps4.game)
            LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
            WHERE ps4.pool=%d AND g4.isongoing=0 GROUP BY player)
            AS pel ON (p.player_id=pel.player)
        WHERE pel.games > 0",
      (int)$poolId,
      (int)$poolId,
      (int)$poolId,
      (int)$poolId,
      (int)$poolId);

  switch($sorting){
    case "deftotal":
      $query .= " ORDER BY deftotal DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "total":
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC";
      break;

    case "goal":
      $query .= " ORDER BY done DESC, total DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "pass":
      $query .= " ORDER BY fedin DESC, total DESC, done DESC, lastname ASC, p.player_id";
      break;

    case "games":
      $query .= " ORDER BY games DESC, total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "team":
      $query .= " ORDER BY teamname ASC, total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;

    case "name":
      $query .= " ORDER BY firstname,lastname ASC, total DESC, done DESC, fedin DESC, p.player_id";
      break;

    case "callahan":
      $query .= " ORDER BY callahan DESC, total DESC, lastname ASC, p.player_id";
      break;

    default:
      $query .= " ORDER BY total DESC, done DESC, fedin DESC, lastname ASC, p.player_id";
      break;
  }

  if($limit > 0){
    $query .= " limit $limit";
  }

  return DBQuery($query);
}


function PoolDependsOn($topool) {
  $query = sprintf("SELECT pmt.frompool, ps.name
        FROM uo_moveteams pmt
        LEFT JOIN uo_pool ps ON(ps.pool_id=pmt.frompool)
        WHERE pmt.topool = %d
		GROUP BY frompool
        ORDER BY pmt.torank ASC",
      (int)$topool);
  return DBQueryToArray($query);
}

/**
 * Get all movings to given pool.
 *
 * @param int $poolId uo_pool.pool_id
 * @return PHP array of moves
 */
function PoolMovingsToPool($poolId){
  $query = sprintf("SELECT pmt.*, ps.name, sn.name AS sname
        FROM uo_moveteams pmt
        LEFT JOIN uo_pool ps ON(ps.pool_id=pmt.frompool)
        LEFT JOIN uo_scheduling_name sn ON(pmt.scheduling_id=sn.scheduling_id)
        WHERE pmt.topool = %d
        ORDER BY pmt.torank ASC",
      (int)$poolId);
  return DBQueryToArray($query);
}

function PoolGetMoveByTeam($toPool, $team) {
  $query = sprintf("SELECT mv.* FROM uo_moveteams AS mv
    LEFT JOIN uo_team_pool AS tp ON (tp.pool = mv.topool AND tp.activerank = mv.torank)
    LEFT JOIN uo_team AS t ON (tp.team = t.team_id)
    WHERE mv.topool=%d AND t.team_id=%d",
      (int) $toPool, (int) $team);
  return DBQueryToArray($query);
}

/**
 * Get all movings from given pool.
 *
 * @param int $poolId uo_pool.pool_id
 * @return PHP array of moves
 */
function PoolMovingsFromPool($poolId){
  $query = sprintf("SELECT pmt.*, ps.name, sn.name AS sname
        FROM uo_moveteams pmt
        LEFT JOIN uo_pool ps ON(ps.pool_id=pmt.frompool)
        LEFT JOIN uo_scheduling_name sn ON(pmt.scheduling_id=sn.scheduling_id)
        WHERE pmt.frompool = %d
        ORDER BY pmt.topool, pmt.torank ASC",
      (int)$poolId);

  return DBQueryToArray($query);
}

/**
 * Get all movings from given pool with team associated.
 *
 * @param int $poolId uo_pool.pool_id
 * @return PHP array of teams
 */
function PoolMovingsFromPoolWithTeams($poolId){
  $query = sprintf("SELECT pmt.*,tp.team as team_id,t.name as teamname
        FROM uo_moveteams pmt
        LEFT JOIN uo_team_pool tp ON (pmt.frompool=tp.pool AND pmt.fromplacing=tp.activerank)
        LEFT JOIN uo_team t ON (tp.team=t.team_id)
        WHERE pmt.frompool = %d
        ORDER BY pmt.frompool, pmt.fromplacing ASC",
    (int)$poolId);

  return DBQueryToArray($query);
}

/**
 * Get move to given pool and position.
 *
 * @param int $poolId uo_pool.pool_id
 * @param int $fromrank position to move
 * @return PHP array row
 */
function PoolGetMoveToPool($poolId, $fromrank){
  $query = sprintf("SELECT m.topool, m.torank, m.ismoved, m.scheduling_id, pool.name, pool.color, pool.visible
            FROM uo_moveteams m
            LEFT JOIN uo_pool pool ON(m.topool=pool.pool_id)
            WHERE m.frompool=%d AND m.fromplacing=%d",
  (int) $poolId,
  (int) $fromrank);

  return DBQueryToRow($query);
}

/**
 * Get move from given pool and position.
 *
 * @param int $topool uo_pool.pool_id
 * @param int $topos move to position
 * @return PHP array row
 */
function PoolGetMoveFrom($topool, $torank){
  $query = sprintf("SELECT m.frompool, m.fromplacing, m.scheduling_id
            FROM uo_moveteams m
            WHERE m.topool=%d AND m.torank=%d",
  (int) $topool,
  (int) $torank);

  return DBQueryToRow($query);
}

/**
 * Get pool where team is moved by scheduling id.
 *
 * @param int $schedulingId
 * @return uo_moveteams.frompool
 */
function PoolGetFromPoolBySchedulingId($schedulingId){
  $query = sprintf("SELECT m.frompool
            FROM uo_moveteams m
            WHERE m.scheduling_id=%d",
    (int) $schedulingId);

  return DBQueryToValue($query);
}

/**
 * Get pool where team is moved by team id.
 *
 * @param int $poolId
 * @param int $teamId
 * @return uo_moveteams.frompool
 */
function PoolGetFromPoolByTeamId($poolId,$teamId){
  $query = sprintf("SELECT m.frompool
            FROM uo_moveteams m
            LEFT JOIN uo_team_pool tp ON(tp.pool=m.topool)
            WHERE m.topool=%d AND tp.team=%d",
  (int) $poolId,
  (int) $teamId);

  return DBQueryToValue($query);
}

/**
 * Get teem from given position from given pool.
 * returns team ranked $activerank from pool $poolId if $countbye=true
 * if $countbye=false, $activerank is corrected by one if BYE team is ranked ahead in this pool
 *
 * @param int $poolId
 * @param int $activerank
 * @param boolean $countbye
 * @return PHP array row
 */
function PoolTeamFromStandings($poolId, $activerank, $countbye=true) {
  if($countbye) {
    $query = sprintf("SELECT j.team_id, j.name, js.activerank, c.flagfile
              FROM uo_team AS j
              LEFT JOIN uo_team_pool AS js ON (j.team_id = js.team)
              LEFT JOIN uo_country c ON(c.country_id=j.country)
              WHERE js.pool=%d AND js.activerank=%d",
              (int)$poolId,
              (int)$activerank);
  }else{
    $query = sprintf("SELECT j.team_id, j.name, js.activerank, c.flagfile
              FROM uo_team AS j
              LEFT JOIN uo_team_pool AS js ON (j.team_id = js.team)
              LEFT JOIN uo_country c ON(c.country_id=j.country)
              WHERE js.pool=%d AND js.activerank=%d+
                  (SELECT count(j.team_id)
                   FROM uo_team AS j
                   LEFT JOIN uo_team_pool AS js ON (j.team_id = js.team)
                   WHERE js.pool=%d and js.activerank<=%d and j.valid=2)",
              (int)$poolId,
              (int)$activerank,
              (int)$poolId,
              (int)$activerank);
  }
  return DBQueryToRow($query);
}

/**
 * Get team from given initial position from given pool.
 * returns team ranked $rank from pool $poolId if $countbye=true
 * if $countbye=false, $rank is corrected by one if BYE team is ranked ahead in this pool
 *
 * @param int $poolId
 * @param int $activerank
 * @param boolean $countbye
 * @return PHP array row
 */
function PoolTeamFromInitialRank($poolId, $rank,$countbye=true){
  if($countbye) {
    $query = sprintf("
            SELECT j.team_id, j.name, js.activerank, c.flagfile
            FROM uo_team AS j
            LEFT JOIN uo_team_pool AS js ON (j.team_id = js.team)
            LEFT JOIN uo_country c ON(c.country_id=j.country)
            WHERE js.pool=%d AND js.rank=%d",
    (int)$poolId,
    (int)$rank);
  }else{
    $query = sprintf("
            SELECT j.team_id, j.name, js.activerank, c.flagfile
            FROM uo_team AS j
            LEFT JOIN uo_team_pool AS js ON (j.team_id = js.team)
            LEFT JOIN uo_country c ON(c.country_id=j.country)
            WHERE js.pool=%d AND js.rank=%d+
                (SELECT count(j.team_id)
                 FROM uo_team AS j
                 LEFT JOIN uo_team_pool AS js ON (j.team_id = js.team)
                 WHERE js.pool=%d and js.rank<=%d and j.valid=2)",
    (int)$poolId,
    (int)$rank,
    (int)$poolId,
    (int)$rank);
  }

  return DBQueryToRow($query);
}

/**
 * Test if same move exist and already done.
 *
 * @param int $frompool
 * @param int $fromplacing
 * @return 1 if exist, 0 otherwise
 */
function PoolIsMoved($frompool, $fromplacing){
  $query = sprintf("SELECT frompool
        FROM uo_moveteams
        WHERE frompool=%d AND fromplacing=%d AND ismoved=1",
        (int)$frompool,
        (int)$fromplacing);

  return DBQueryRowCount($query);
}

/**
 * Test if all pools to move from are played or not.
 *
 * @param int $topool
 * @return true if played, false otherwise
 */
function PoolIsMoveFromPoolsPlayed($topool){
  $query = sprintf("SELECT m.frompool, p.played
            FROM uo_moveteams m
            LEFT JOIN uo_pool p ON(m.frompool=p.pool_id)
            WHERE m.topool=%d AND p.played=0 GROUP BY m.frompool",
          (int)$topool);

  return DBQueryRowCount($query) ? false:true;
}

/**
 * Tests if move exist.
 *
 * @param int $frompool
 * @param int $fromplacing
 * @return 0 if move doesn't exist. 1 if exist.
 */
function PoolMoveExist($frompool, $fromplacing){
  $query = sprintf("SELECT frompool
        FROM uo_moveteams
        WHERE frompool=%d AND fromplacing=%d",
      (int)$frompool,
      (int)$fromplacing);

  return DBQueryRowCount($query);
}

/**
 * Test if all moves done.
 *
 * @param int $topool
 * @return true if no moves
 */
function PoolIsAllMoved($topool){
  $query = sprintf("SELECT topool
        FROM uo_moveteams
        WHERE topool=%d AND ismoved=0",
      (int)$topool);

  return (DBQueryRowCount($query)==0);
}

/**
 * Gets games to move with teams into continuation pool.
 *
 * @param int $poolId
 * @param int $mvgames: 0 - all, 1 - nothing, 2 - mutual
 * @return PHP array of game ids to move
 */
function PoolGetGamesToMove($poolId, $mvgames){
  $games = array();
  $moves = PoolMovingsToPool($poolId);
  foreach($moves as $row){
    $team = PoolTeamFromStandings($row['frompool'],$row['fromplacing']);
    if($mvgames==0){
      $teamgames = TeamPoolGames($team['team_id'],$row['frompool']);
      if(mysql_num_rows($teamgames)){
        while($game = mysql_fetch_assoc($teamgames)){
          $found = false;
          foreach ($games as $id){
            if($game['game_id'] == $id){
              $found=true;
            }
          }
          if(!$found){
            $games[]=$game['game_id'];
          }
        }
      }
    }else if($mvgames==2){
      $moves2 = PoolMovingsToPool($poolId);
        foreach($moves2 as $row2){
        $team2 = PoolTeamFromStandings($row2['frompool'],$row2['fromplacing']);
        if($row2['frompool'] == $row2['frompool']){
          $teamgames = TeamPoolGamesAgainst($team['team_id'],$team2['team_id'],$row['frompool']);
          if(mysql_num_rows($teamgames)){
            while($game = mysql_fetch_assoc($teamgames)){
              $found = false;
              foreach ($games as $id){
                if($game['game_id'] == $id){
                  $found=true;
                }
              }
              if(!$found){
                $games[]=$game['game_id'];
              }
            }
          }
        }
      }
    }
  }
  sort($games);
  return $games;
}

function PoolCountGames($poolId) {
  $games = DBQuery("SELECT game_id
      FROM uo_game game
      LEFT JOIN uo_pool p ON (p.pool_id=game.pool)
      WHERE p.pool_id=$poolId");
  return mysql_num_rows($games);
}

/**
 * Gets all games played in given pool.
 *
 * @param int $poolId
 * @param int $fieldId - limits games to given field
 * @return PHP array of games
 */
function PoolGames($poolId, $fieldId=null) {

  $query = sprintf("SELECT home.name AS hometeamname, visitor.name AS visitorteamname, CONCAT(home.name, ' - ', visitor.name) AS name,
            p.hometeam, p.visitorteam,
            p.time, p.game_id, p.homescore, p.visitorscore,
            phome.name AS phometeamname, pvisitor.name AS pvisitorteamname
            FROM uo_game p
            LEFT JOIN uo_team AS home ON (p.hometeam=home.team_id)
            LEFT JOIN uo_team AS visitor ON (p.visitorteam=visitor.team_id)
            LEFT JOIN uo_scheduling_name AS phome ON (p.scheduling_name_home=phome.scheduling_id)
            LEFT JOIN uo_scheduling_name AS pvisitor ON (p.scheduling_name_visitor=pvisitor.scheduling_id)
            LEFT JOIN uo_game_pool ps ON (p.game_id=ps.game)
            WHERE ps.pool = %d",
           (int)$poolId);
  if (isset($fieldId)) {
    $query .= sprintf(" AND p.reservation = %d AND ps.timetable=1",
    (int)$fieldId);
  }
  $query .= " ORDER BY time ASC ";

  return DBQueryToArray($query);
}

/**
 * Get all games from given pools which have no schedule.
 *
 * @param int $poolId
 * @return PHP array of games
 */
function PoolGamesNotScheduled($poolId){
  $query = sprintf("SELECT home.name AS hometeamname, visitor.name AS visitorteamname, p.hometeam, p.visitorteam,
        p.time, p.game_id, p.homescore, p.visitorscore,
        phome.name AS phometeamname, pvisitor.name AS pvisitorteamname
        FROM uo_game p
        LEFT JOIN uo_team AS home ON (p.hometeam=home.team_id)
        LEFT JOIN uo_team AS visitor ON (p.visitorteam=visitor.team_id)
        LEFT JOIN uo_scheduling_name AS phome ON (p.scheduling_name_home=phome.scheduling_id)
        LEFT JOIN uo_scheduling_name AS pvisitor ON (p.scheduling_name_visitor=pvisitor.scheduling_id)
        LEFT JOIN uo_game_pool ps ON (p.game_id=ps.game)
        WHERE ps.pool = %d AND (p.time IS NULL OR p.reservation IS NULL)
        ORDER BY game_id",
    (int)$poolId);

  return DBQueryToArray($query);
}


/**
 * Get all moved games in given pool.
 *
 * @param int $poolId
 * @return PHP array of games
 */
function PoolMovedGames($poolId){
  $query = sprintf("SELECT pp.game_id, pp.time, pp.hometeam, pp.visitorteam, pp.homescore,
            pp.visitorscore, pp.pool AS pool, pool.name AS poolname, pool.timeslot,
            ps.series_id, ps.name AS seriesname, ps.season, ps.type, pr.fieldname, pr.reservationgroup,
            pr.id AS reservation_id, pr.starttime, pr.endtime, pl.id AS place_id, COALESCE(pm.goals,0) AS scoresheet,
            pl.name AS placename, pl.address, pp.isongoing, pp.hasstarted, home.name AS hometeamname, visitor.name AS visitorteamname,
            phome.name AS phometeamname, pvisitor.name AS pvisitorteamname, pool.color, pgame.name AS gamename,
            home.abbreviation AS homeshortname, visitor.abbreviation AS visitorshortname, homec.country_id AS homecountryid,
            homec.name AS homecountry, visitorc.country_id AS visitorcountryid, visitorc.name AS visitorcountry
            FROM uo_game_pool gp
            LEFT JOIN uo_game pp ON (gp.game=pp.game_id)
            LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (pp.game_id=pm.game)
            LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool)
            LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
            LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)
            LEFT JOIN uo_location pl ON (pr.location=pl.id)
            LEFT JOIN uo_team AS home ON (pp.hometeam=home.team_id)
            LEFT JOIN uo_team AS visitor ON (pp.visitorteam=visitor.team_id)
            LEFT JOIN uo_country AS homec ON (homec.country_id=home.country)
            LEFT JOIN uo_country AS visitorc ON (visitorc.country_id=visitor.country)
            LEFT JOIN uo_scheduling_name AS pgame ON (pp.name=pgame.scheduling_id)
            LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
            LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
            WHERE pp.valid=true AND gp.pool=%d AND timetable=0
            ORDER BY pr.starttime, pr.reservationgroup, pl.id, pr.fieldname +0, pp.time ASC, pp.game_id ASC",
  (int)$poolId);

  return DBQueryToArray($query);
}

/**
 * Get number of games played in given pool.
 *
 * @param int $poolId
 * @return Number of games played
 */
function PoolTotalPlayedGames($poolId) {
  $query = sprintf("SELECT pp.game_id, pp.hometeam, pp.visitorteam, pp.time
            FROM uo_game pp
            RIGHT JOIN uo_game_pool pps ON(pps.game=pp.game_id)
            WHERE pps.pool=%d AND pp.valid=true
                AND hasstarted>0 AND isongoing=0
            ORDER BY pp.time ASC",
  (int)$poolId);

  return DBQueryRowCount($query);
}

/**
 * Check if all games in pool are played. If yes, then updates uo_pool.played accordingly.
 *
 * @param int $poolId
 */
function PoolResolvePlayed($poolId){
  $poolId = intval($poolId);
  $games = DBQuery("SELECT game_id
            FROM uo_game game
            LEFT JOIN uo_pool p ON (p.pool_id=game.pool)
            WHERE p.pool_id=$poolId");
  $played = DBQuery("SELECT game_id
            FROM uo_game game
            LEFT JOIN uo_pool p ON (p.pool_id=game.pool)
            WHERE p.pool_id=$poolId AND game.hasstarted AND game.isongoing=0");
  if (mysql_num_rows($games) == mysql_num_rows($played)) {
    DBQuery("UPDATE uo_pool SET played=1 WHERE pool_id=$poolId");
  } else {
    DBQuery("UPDATE uo_pool SET played=0 WHERE pool_id=$poolId");
  }
}

/**
 * Test if one or more games already played in given pool.
 *
 * @param int $poolId
 * @return true if pool started.
 */
function IsPoolStarted($poolId){

  $query = sprintf("SELECT game_id
            FROM uo_pool pool
            LEFT JOIN uo_game pp ON (pool.pool_id=pp.pool)
            WHERE pool.pool_id=$poolId AND (pp.hasstarted>0)",
        (int)$poolId);
  return DBQueryRowCount($query)?true:false;
}

/**
 * Adds pool template.
 *
 * @param uo_pooltemplate $params
 */
function AddPoolTemplate($params) {
  if (hasCurrentSeasonsEditRight()) {
    $query = sprintf("INSERT INTO uo_pooltemplate
            (name, timeoutlen, halftime, winningscore, drawsallowed, timecap, scorecap, addscore, halftimescore, timeouts,
            timeoutsper, timeoutsovertime, timeoutstimecap, betweenpointslen, continuingpool, mvgames, type,
            ordering, teams, timeslot, forfeitagainst, forfeitscore)
            VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
    mysql_real_escape_string($params['name']),
    mysql_real_escape_string($params['timeoutlen']),
    mysql_real_escape_string($params['halftime']),
    mysql_real_escape_string($params['winningscore']),
    mysql_real_escape_string($params['drawsallowed']),
    mysql_real_escape_string($params['timecap']),
    mysql_real_escape_string($params['scorecap']),
    mysql_real_escape_string($params['addscore']),
    mysql_real_escape_string($params['halftimescore']),
    mysql_real_escape_string($params['timeouts']),
    mysql_real_escape_string($params['timeoutsper']),
    mysql_real_escape_string($params['timeoutsovertime']),
    mysql_real_escape_string($params['timeoutstimecap']),
    mysql_real_escape_string($params['betweenpointslen']),
    mysql_real_escape_string($params['continuingpool']),
    mysql_real_escape_string($params['mvgames']),
    mysql_real_escape_string($params['type']),
    mysql_real_escape_string($params['ordering']),
    mysql_real_escape_string($params['teams']),
    mysql_real_escape_string($params['timeslot']),
    mysql_real_escape_string($params['forfeitagainst']),
    mysql_real_escape_string($params['forfeitscore']));

    return DBQueryInsert($query);
  } else { die('Insufficient rights to add pool template.'); }
}

/**
 * Updates pool template.
 *
 * @param int $poolId
 * @param uo_pooltemplate $params
 */
function SetPoolTemplate($poolId, $params) {
  if (hasCurrentSeasonsEditRight()) {
    $query = sprintf("UPDATE uo_pooltemplate SET
            name='%s', timeoutlen='%s', halftime='%s', winningscore='%s', drawsallowed='%s', timecap='%s', scorecap='%s',
            addscore='%s', halftimescore='%s', timeouts='%s', timeoutsper='%s', timeoutsovertime='%s',
            timeoutstimecap='%s', betweenpointslen='%s', continuingpool='%s', mvgames='%s', type='%s', ordering='%s',
            teams='%s', timeslot='%s', forfeitagainst='%s', forfeitscore='%s'
            WHERE template_id='%s'",
    mysql_real_escape_string($params['name']),
    mysql_real_escape_string($params['timeoutlen']),
    mysql_real_escape_string($params['halftime']),
    mysql_real_escape_string($params['winningscore']),
    mysql_real_escape_string($params['drawsallowed']),
    mysql_real_escape_string($params['timecap']),
    mysql_real_escape_string($params['scorecap']),
    mysql_real_escape_string($params['addscore']),
    mysql_real_escape_string($params['halftimescore']),
    mysql_real_escape_string($params['timeouts']),
    mysql_real_escape_string($params['timeoutsper']),
    mysql_real_escape_string($params['timeoutsovertime']),
    mysql_real_escape_string($params['timeoutstimecap']),
    mysql_real_escape_string($params['betweenpointslen']),
    mysql_real_escape_string($params['continuingpool']),
    mysql_real_escape_string($params['mvgames']),
    mysql_real_escape_string($params['type']),
    mysql_real_escape_string($params['ordering']),
    mysql_real_escape_string($params['teams']),
    mysql_real_escape_string($params['timeslot']),
    mysql_real_escape_string($params['forfeitagainst']),
    mysql_real_escape_string($params['forfeitscore']),
    mysql_real_escape_string($poolId));

    return DBQuery($query);
  } else { die('Insufficient rights to edit pool template.'); }
}

/**
 * Deletes pool.
 *
 * @param int $poolId
 */
function DeletePool($poolId) {
  $poolInfo = PoolInfo($poolId);
  if (hasEditSeasonSeriesRight($poolInfo['season'])) {
    Log2("pool","delete",PoolName($poolId));
    $query = sprintf("DELETE FROM uo_pool WHERE pool_id=%d",
    (int)$poolId);

    DBQuery($query);

    $query = sprintf("DELETE FROM uo_moveteams WHERE frompool=%d OR topool=%d",
    (int)$poolId,(int)$poolId);

    DBQuery($query);
  } else { die('Insufficient rights to delete pool'); }
}

/**
 * Deletes pool template.
 *
 * @param int $poolId
 */
function DeletePoolTemplate($poolId) {
  $poolInfo = PoolInfo($poolId);
  if (hasEditSeasonSeriesRight($poolInfo['season'])) {
    $query = sprintf("DELETE FROM uo_pooltemplate WHERE template_id=%d",
    (int)$poolId);

    DBQuery($query);

  } else { die('Insufficient rights to delete pool template'); }
}

/**
 * Creates a pool based on given template.
 *
 * @param int $seriesId - Series where pool is created
 * @param string $name - Pool name
 * @param string $ordering - Pool order
 * @param int $poolTemplateId - Pool template
 * @return id to created pool.
 */
function PoolFromPoolTemplate($seriesId, $name, $ordering, $poolTemplateId) {
  $seriesinfo = SeriesInfo($seriesId);
  if (hasEditSeasonSeriesRight($seriesinfo['season'])) {
    $colors = array("F0F8FF","FAEBD7","00FFFF","7FFFD4","F0FFFF","F5F5DC","FFE4C4","0000FF","8A2BE2","DEB887","FFFF00","5F9EA0",
            "7FFF00","D2691E","FF7F50","6495ED","FFF8DC","DC143C","00FFFF","00008B","008B8B","B8860B","A9A9A9","006400",
            "BDB76B","8B008B","FF8C00","9932CC","8B0000","E9967A","8FBC8F","00CED1","9400D3","FF1493","00BFFF","1E90FF",
            "B22222","228B22","FF00FF","DCDCDC","F8F8FF","FFD700","DAA520","008000","ADFF2F","F0FFF0","FF69B4","CD5C5C",
            "FFFFF0","F0E68C","E6E6FA","FFF0F5","7CFC00","FFFACD","ADD8E6","F08080","E0FFFF","FAFAD2","D3D3D3","90EE90",
            "FFB6C1","FFA07A","20B2AA","87CEFA","778899","B0C4DE","FFFFE0","00FF00","32CD32","FAF0E6","FF00FF","800000",
            "66CDAA","0000CD","BA55D3","9370D8","3CB371","7B68EE","00FA9A","48D1CC","C71585","191970","F5FFFA","FFE4E1",
            "FFE4B5","FFDEAD","FDF5E6","808000","6B8E23","FFA500","FF4500","DA70D6","EEE8AA","98FB98","AFEEEE","D87093",
            "FFEFD5","FFDAB9","CD853F","FFC0CB","DDA0DD","B0E0E6","800080","FF0000","BC8F8F","4169E1","FA8072","F4A460",
            "2E8B57","FFF5EE","A0522D","C0C0C0","87CEEB","6A5ACD","708090","FFFAFA","00FF7F","4682B4","D2B48C","D8BFD8",
            "FF6347","40E0D0","EE82EE","F5DEB3","F5F5F5","9ACD32");
    $query = sprintf("INSERT INTO uo_pool
            (type, timeoutlen, halftime, winningscore, drawsallowed, timecap, scorecap, addscore, halftimescore, timeouts,
            timeoutsper, timeoutsovertime, timeoutstimecap,betweenpointslen, continuingpool, forfeitagainst, forfeitscore, visible, played,
            mvgames, ordering, teams, timeslot, name, series)
            SELECT type, timeoutlen, halftime, winningscore, drawsallowed, timecap, scorecap, addscore, halftimescore, timeouts,
            timeoutsper, timeoutsovertime, timeoutstimecap,betweenpointslen, continuingpool, forfeitagainst, forfeitscore, 0, 0,
            mvgames, '%s', teams, timeslot, '%s', %d
            FROM uo_pooltemplate WHERE template_id=%d",
    mysql_real_escape_string($ordering),
    mysql_real_escape_string($name),
    (int)$seriesId,
    (int)$poolTemplateId);

    $newId = DBQueryInsert($query);
    $color = $colors[$newId % count($colors)];
    $query = "UPDATE uo_pool SET color='".$color."' WHERE pool_id=".$newId;

    DBQuery($query);

    Log1("pool","add",$newId);
    return $newId;
  } else { die('Insufficient privileges to add pool'); }
}

/**
 * Creates a pool by copying params from given pool
 *
 * @param int $seriesId - Series where pool is created
 * @param string $name - Pool name
 * @param string $ordering - Pool order
 * @param int $poolId - pool to copy
 * @param boolean $follower - new pool is follower for copied pool (used with playoff pools)
 */
function PoolFromAnotherPool($seriesId, $name, $ordering, $poolId, $follower=false) {
  $seriesinfo = SeriesInfo($seriesId);
  if (hasEditSeasonSeriesRight($seriesinfo['season'])) {
    $colors = array("F0F8FF","FAEBD7","00FFFF","7FFFD4","F0FFFF","F5F5DC","FFE4C4","0000FF","8A2BE2","DEB887","FFFF00","5F9EA0",
            "7FFF00","D2691E","FF7F50","6495ED","FFF8DC","DC143C","00FFFF","00008B","008B8B","B8860B","A9A9A9","006400",
            "BDB76B","8B008B","FF8C00","9932CC","8B0000","E9967A","8FBC8F","00CED1","9400D3","FF1493","00BFFF","1E90FF",
            "B22222","228B22","FF00FF","DCDCDC","F8F8FF","FFD700","DAA520","008000","ADFF2F","F0FFF0","FF69B4","CD5C5C",
            "FFFFF0","F0E68C","E6E6FA","FFF0F5","7CFC00","FFFACD","ADD8E6","F08080","E0FFFF","FAFAD2","D3D3D3","90EE90",
            "FFB6C1","FFA07A","20B2AA","87CEFA","778899","B0C4DE","FFFFE0","00FF00","32CD32","FAF0E6","FF00FF","800000",
            "66CDAA","0000CD","BA55D3","9370D8","3CB371","7B68EE","00FA9A","48D1CC","C71585","191970","F5FFFA","FFE4E1",
            "FFE4B5","FFDEAD","FDF5E6","808000","6B8E23","FFA500","FF4500","DA70D6","EEE8AA","98FB98","AFEEEE","D87093",
            "FFEFD5","FFDAB9","CD853F","FFC0CB","DDA0DD","B0E0E6","800080","FF0000","BC8F8F","4169E1","FA8072","F4A460",
            "2E8B57","FFF5EE","A0522D","C0C0C0","87CEEB","6A5ACD","708090","FFFAFA","00FF7F","4682B4","D2B48C","D8BFD8",
            "FF6347","40E0D0","EE82EE","F5DEB3","F5F5F5","9ACD32");
    $query = sprintf("INSERT INTO uo_pool
            (type, timeoutlen, halftime, winningscore, drawsallowed, timecap, scorecap, addscore, halftimescore, timeouts,
            timeoutsper, timeoutsovertime, timeoutstimecap,betweenpointslen, continuingpool, forfeitagainst, forfeitscore, visible, played,
            mvgames, ordering, teams, timeslot, name, series)
            SELECT type, timeoutlen, halftime, winningscore, drawsallowed, timecap, scorecap, addscore, halftimescore, timeouts,
            timeoutsper, timeoutsovertime, timeoutstimecap,betweenpointslen, continuingpool, forfeitagainst, forfeitscore, 0, 0,
            mvgames, '%s', teams, timeslot, '%s', %d
            FROM uo_pool WHERE pool_id=%d",
    mysql_real_escape_string($ordering),
    mysql_real_escape_string($name),
    (int)$seriesId,
    (int)$poolId);

    $newId = DBQueryInsert($query);

    $color = $colors[$newId % count($colors)];
    $query = "UPDATE uo_pool SET color='".$color."' WHERE pool_id=".$newId;
    DBQuery($query);

    if($follower){
      $query = "UPDATE uo_pool SET follower='".$newId."' WHERE pool_id=".$poolId;
      DBQuery($query);
    }

    Log1("pool","add",$newId);
    return $newId;
  } else { die('Insufficient privileges to add pool'); }
}

/**
 * Update all pool parameters.
 *
 * @param int $poolId
 * @param uo_pool $params
 */
function SetPoolDetails($poolId, $params, $comment=null) {
  $poolinfo = PoolInfo($poolId);
  if(hasEditSeasonSeriesRight($poolinfo['season'])) {
    $result = DBSetRow("uo_pool",$params,"pool_id=$poolId");
    if ($result && isset($comment)) {
      SetComment(3, $poolId, $comment);
    }
  } else { die('Insufficient rights to edit pool'); }
}

/**
 * Update pool key parameters.
 *
 * @param int $poolId
 * @param uo_pool $params
 */
function SetPool($poolId, $params) {
  $poolinfo = PoolInfo($poolId);
  if(hasEditSeasonSeriesRight($poolinfo['season'])) {
    $query = sprintf("UPDATE uo_pool SET name='%s', continuingpool='%s', placementpool='%s',
            visible='%s', type='%s', ordering='%s' WHERE pool_id='%s'",
    mysql_real_escape_string($params['name']),
    mysql_real_escape_string($params['continuingpool']),
    mysql_real_escape_string($params['placementpool']),
    mysql_real_escape_string($params['visible']),
    mysql_real_escape_string($params['type']),
    mysql_real_escape_string($params['ordering']),
    mysql_real_escape_string($poolId));

  DBQuery($query);

  } else { die('Insufficient rights to edit pool'); }
}
/**
 * Sets pool visibility (= shown for public or not)
 *
 * @param int $poolId
 * @param int $visible: 0 hidden, 1 visible
 */
function SetPoolVisibility($poolId, $visible) {
  $poolinfo = PoolInfo($poolId);
  if(hasEditSeasonSeriesRight($poolinfo['season'])) {
    $query = sprintf("UPDATE uo_pool SET visible=%d
            WHERE pool_id=%d",
    (int)$visible,
    (int)$poolId);

    return DBQuery($query);
  }
}

/**
 * Set pool name.
 *
 * @param int $poolId
 * @param string $name
 */
function SetPoolName($poolId, $name) {
  $poolinfo = PoolInfo($poolId);
  if(hasEditSeasonSeriesRight($poolinfo['season'])) {
    $query = sprintf("UPDATE uo_pool SET
            name='%s'
            WHERE pool_id=%d",
      mysql_real_escape_string($name),
      (int)$poolId);

    return DBQuery($query);
  } else { die('Insufficient rights to edit pool'); }
}

/**
 * Removes team from pool.
 *
 * @param int $poolId
 * @param int $teamId - team to remove
 */
function PoolDeleteTeam($poolId, $teamId, $checkrights=true) {
  $poolInfo = PoolInfo($poolId);
  if (!$checkrights || hasEditTeamsRight($poolInfo['series'])) {

    $query = sprintf("DELETE FROM uo_team_pool WHERE pool=%d AND team=%d",
      (int)$poolId,
      (int)$teamId);

    DBQuery($query);

    $teaminfo = TeamInfo($teamId);
    if($teaminfo['pool']==$poolId){
      $query = sprintf("UPDATE uo_team SET pool=NULL WHERE team_id=%d",
        (int)$teamId);
      DBQuery($query);
    }
  } else { die('PDT: Insufficient rights to edit pool teams'); }
}

/**
 * Change team's pool.
 *
 * @param int $curpool - old pool
 * @param int $teamId - team to change
 * @param int $rank - rank in new pool
 * @param int $newpool - new pool
 */
function PoolSetTeam($curpool, $teamId, $rank, $newpool) {
  if($newpool>0){
    $poolInfo = PoolInfo($newpool);
    if (!hasEditTeamsRight($poolInfo['series']))
      die('PST: Insufficient rights to edit pool teams');
  }
  if ($curpool>0){
    $poolInfo = PoolInfo($curpool);
    if (!hasEditTeamsRight($poolInfo['series']))
      die('PST: Insufficient rights to edit pool teams');
  }


  if ($newpool > 0){
    $query = sprintf("UPDATE uo_team_pool
                      SET rank=%d, pool=%d WHERE pool=%d AND team=%d",
                      (int) $rank, (int) $newpool, (int) $curpool, (int) $teamId);
    DBQuery($query);
  } else {
    $query = sprintf("DELETE FROM uo_team_pool
                      WHERE pool=%d AND team=%d",
                      (int) $curpool, (int) $teamId);
    DBQuery($query);
  }

  $teaminfo = TeamInfo($teamId);
  if ($teaminfo['pool'] == $curpool) {
    $query = sprintf("UPDATE uo_team SET pool=%d WHERE team_id=%d", (int) $newpool, (int) $teamId);

    DBQuery($query);
  }
}

/**
 * Adds teams into pool.
 *
 * @param int $poolId
 * @param int $teamId
 * @param int $rank - team rank in pool
 * @param int $updaterank - if activerank is updated
 */
function PoolAddTeam($poolId, $teamId, $rank, $updaterank=false, $checkrights=true) {
  $poolInfo = PoolInfo($poolId);

  if (!$checkrights || hasEditTeamsRight($poolInfo['series'])) {

    if($updaterank){
      $query = sprintf("INSERT IGNORE INTO uo_team_pool
                  (team, pool, rank, activerank)
                  VALUES (%d,%d,%d,%d)",
      (int)$teamId,
      (int)$poolId,
      (int)$rank,
      (int)$rank);
    }else{
      $query = sprintf("INSERT IGNORE INTO uo_team_pool
                  (team, pool, rank)
                  VALUES (%d,%d,%d)",
      (int)$teamId,
      (int)$poolId,
      (int)$rank);
    }
    DBQuery($query);

    //update team pool
    /*
    $query = sprintf("UPDATE uo_team SET
            pool=%d WHERE team_id=%d",
    (int)$poolId,
    (int)$teamId);
    */
    DBQuery($query);

  } else { die('PAT: Insufficient rights to edit pool teams'); }
}

/**
 * Adds special ranking rules.
 *
 * @param int $frompool
 * @param int $fromplacing
 * @param int $torank
 * @param string $pteamname
 */
function AddSpecialRankingRule($frompool, $fromplacing, $torank,$pteamname) {
  $poolInfo = PoolInfo($frompool);
  if (hasEditTeamsRight($poolInfo['series'])) {

    $query = sprintf("INSERT INTO uo_scheduling_name
                (name) VALUES ('%s')",
    mysql_real_escape_string($pteamname));

    $pteam = DBQueryInsert($query);

    $query = sprintf("INSERT INTO uo_specialranking
                (frompool, fromplacing, torank, scheduling_id)
                VALUES ('%s','%s','%s', %d)",
    mysql_real_escape_string($frompool),
    mysql_real_escape_string($fromplacing),
    mysql_real_escape_string($torank),
    (int) $pteam);

    return DBQueryInsert($query);
  } else { die('Insufficient rights to add pool moves'); }
}

/**
 * Add move between pools.
 *
 * @param int $frompool - pool where to move
 * @param int $topool - pool to move
 * @param int $fromplacing - from poistion to move
 * @param int $torank - to position to move
 * @param string $pteamname - team scheduling name in new pool (f.ex. A1)
 */
function PoolAddMove($frompool, $topool, $fromplacing, $torank,$pteamname) {
  $poolInfo = PoolInfo($topool);
  if (hasEditTeamsRight($poolInfo['series'])) {

    $query = sprintf("INSERT INTO uo_scheduling_name
                (name) VALUES ('%s')",
    mysql_real_escape_string($pteamname));

    $pteam = DBQueryInsert($query);

    $query = sprintf("INSERT INTO uo_moveteams
                (frompool, topool, fromplacing, torank, scheduling_id, ismoved)
                VALUES (%d,%d,%d,%d, %d, 0)",
      (int)$frompool,
      (int)$topool,
      (int)$fromplacing,
      (int)$torank,
      (int)$pteam);

    return DBQueryInsert($query);
  } else { die('Insufficient rights to add pool moves'); }
}

/**
 * Updates move parameters.
 *
 * @param int $frompool - move from pool
 * @param int $oldfpos - old from placing value
 * @param int $fromplacing - new from placing value
 * @param int $torank - new to rank value
 */
function PoolSetMove($frompool, $oldfpos, $fromplacing, $torank) {
  $poolInfo = PoolInfo($frompool);
  if (hasEditTeamsRight($poolInfo['series'])) {

    $query = sprintf("UPDATE uo_moveteams SET fromplacing=%d, torank=%d
                WHERE frompool=%d AND fromplacing=%d",
      (int)$fromplacing,
      (int)$torank,
      (int)$frompool,
      (int)$oldfpos);

    return DBQuery($query);
  } else { die('Insufficient rights to add pool moves'); }
}


/**
 * Makes moves to given pool.
 *
 * @param int $poolId - pool to moves are done.
 */
function PoolMakeMoves($poolId) {
  $poolInfo = PoolInfo($poolId);
  if (hasEditTeamsRight($poolInfo['series'])) {
    //move teams
    LogPoolUpdate($poolId, "Teams moved");
    $moves = PoolMovingsToPool($poolId);
    $topool = 0;
    foreach($moves as $row){
      //store target pool
      if(!$topool){
        $topool=$row['topool'];
      }

      //add team to target pool
      $team = PoolTeamFromStandings($row['frompool'],$row['fromplacing'],$poolInfo['type']!=2); // do not count BYE team if we are moving to a playoff pool
      PoolAddTeam($row['topool'],$team['team_id'],$row['torank'],true);

      //replace pseudo team with real team in games
      if(isRespTeamHomeTeam()){
        $query = sprintf("UPDATE uo_game SET
                    hometeam=%d, respteam=%d WHERE scheduling_name_home=%d AND scheduling_name_home!=0",
                    (int)$team['team_id'],
                    (int)$team['team_id'],
                    (int)$row['scheduling_id']);
      }else{
        $query = sprintf("UPDATE uo_game SET
                    hometeam=%d WHERE scheduling_name_home=%d AND scheduling_name_home!=0",
                    (int)$team['team_id'],
                    (int)$row['scheduling_id']);
      }

      DBQuery($query);

      $query = sprintf("UPDATE uo_game SET visitorteam=%d WHERE scheduling_name_visitor=%d AND scheduling_name_visitor!=0",
      (int)$team['team_id'],
      (int)$row['scheduling_id']);

      DBQuery($query);

      //set move done
      $query = sprintf("UPDATE uo_moveteams SET ismoved='1' WHERE frompool=%d AND fromplacing=%d",
        (int)$row['frompool'],
        (int)$row['fromplacing']);

      DBQuery($query);
    }

    //games to move
    $poolinfo = PoolInfo($poolId);
    $mvgames = intval($poolinfo['mvgames']);
    $games = PoolGetGamesToMove($poolId, $mvgames);
    foreach ($games as $id ) {
      $query = sprintf("INSERT IGNORE INTO uo_game_pool
                    (game, pool, timetable)
                    VALUES (%d, %d, 0)",
          (int)$id,
          (int)$topool);

      $result = mysql_query($query);
      if (!$result) { die('Invalid query: ' . mysql_error()); }
    }
  } else { die('Insufficient rights to move teams'); }
}

/**
 * Makes move to given pool.
 *
 * @param int $frompool - pool from move.
 * @param int $fromplacing - position from move.
 * @param boolean $checkrights check edit teams privilege, defaults to true
 */
function PoolMakeMove($frompool, $fromplacing, $checkrights=true) {
  $poolInfo = PoolInfo($frompool);
  if (!$checkrights || hasEditTeamsRight($poolInfo['series'])) {
    //move teams
    $query = sprintf("SELECT pmt.*, ps.name, sn.name AS sname
        FROM uo_moveteams pmt
        LEFT JOIN uo_pool ps ON(ps.pool_id=pmt.frompool)
        LEFT JOIN uo_scheduling_name sn ON(pmt.scheduling_id=sn.scheduling_id)
        WHERE pmt.frompool = %d AND pmt.fromplacing = %d
        ORDER BY pmt.torank ASC",
      (int)$frompool,
      (int)$fromplacing);
    $row = DBQueryToRow($query);
    LogPoolUpdate($row['frompool'], "Teams moved");
    // add team to target pool
    $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing'], $poolInfo['type'] != 2); // do not count BYE team if we are moving to a playoff pool

    // delete previously moved team
    $previous = PoolTeamFromInitialRank($row['topool'], $row['torank']);
    if (!empty($previous) && CanDeleteTeamFromPool($row['topool'], $previous['team_id'])) {
      PoolDeleteTeam($row['topool'], $previous['team_id'], $checkrights);
    }
    
    PoolAddTeam($row['topool'], $team['team_id'], $row['torank'], true, $checkrights);

    // replace pseudo team with real team in games
    if (isRespTeamHomeTeam()) {
      $query = sprintf(
          "UPDATE uo_game SET
                    hometeam=%d, respteam=%d WHERE scheduling_name_home=%d AND scheduling_name_home!=0", (int) $team['team_id'],
          (int) $team['team_id'], (int) $row['scheduling_id']);
    } else {
      $query = sprintf(
          "UPDATE uo_game SET
                    hometeam=%d WHERE scheduling_name_home=%d AND scheduling_name_home!=0", (int) $team['team_id'],
          (int) $row['scheduling_id']);
    }

    DBQuery($query);

    $query = sprintf(
        "UPDATE uo_game SET visitorteam=%d WHERE scheduling_name_visitor=%d AND scheduling_name_visitor!=0",
        (int) $team['team_id'], (int) $row['scheduling_id']);

    DBQuery($query);

    // set move done
    $query = sprintf("UPDATE uo_moveteams SET ismoved='1' WHERE frompool=%d AND fromplacing=%d", (int) $row['frompool'],
        (int) $row['fromplacing']);

    DBQuery($query);

  } else { die('Insufficient rights to move teams'); }
}
/**
 * Delete a move from pool.
 *
 * @param int $frompool - move is from pool
 * @param int $fromplacing - move is from position
 */
function PoolDeleteMove($frompool, $fromplacing) {
  $poolInfo = PoolInfo($frompool);
  if (hasEditTeamsRight($poolInfo['series'])) {
    $query = sprintf("DELETE FROM uo_moveteams WHERE frompool=%d AND fromplacing=%d",
        (int)$frompool,
        (int)$fromplacing);

    DBQuery($query);
  } else { die('Insufficient rights to move teams'); }
}

/**
 * Undo move.
 *
 * @param int $frompool - move is from pool
 * @param int $fromplacing - move is from position
 * @param int $topool - move is to pool
 */
function PoolUndoMove($frompool, $fromplacing, $topool) {
  $poolInfo = PoolInfo($frompool);
  if (hasEditTeamsRight($poolInfo['series'])) {

    // delete moved games
    $query = sprintf("DELETE FROM uo_game_pool WHERE pool=%d AND timetable=0",
          (int)$topool);
    DBQuery($query);

    $query = sprintf("UPDATE uo_moveteams SET ismoved=0 WHERE frompool=%d AND fromplacing=%d",
    (int)$frompool,
    (int)$fromplacing);

    DBQuery($query);

    $query = sprintf("SELECT scheduling_id FROM uo_moveteams WHERE frompool=%d AND fromplacing=%d",
    (int)$frompool,
    (int)$fromplacing);

    $result = DBQueryToRow($query);
    $homesched = $result['scheduling_id'];

    //replace real team with pseudo team in games
    $query = sprintf("UPDATE uo_game SET
            hometeam=NULL WHERE scheduling_name_home=%d",
    (int)$result['scheduling_id']); // FIXME set respteam to scheduling_team

    DBQuery($query);
    $vissched = $result['scheduling_id'];

    $query = sprintf("UPDATE uo_game SET
            visitorteam=NULL WHERE scheduling_name_visitor=%d",
    (int)$result['scheduling_id']);

    DBQuery($query);

    $team = PoolTeamFromStandings($frompool,$fromplacing,$poolInfo['type']!=2); // do not count BYE team if we are moving to a playoff pool

    PoolDeleteTeam($topool, $team['team_id']);


//     $query = sprintf("DELETE FROM uo_team_pool WHERE
//                 team=%d AND pool=%d",
//     (int)$team['team_id'],
//     (int)$topool);
//     DBQuery($query);

//     //update team pool
//     $query = sprintf("UPDATE uo_team SET
//             pool=%d WHERE team_id=%d",
//     (int)$frompool,
//     (int)$team['team_id']);

//     DBQuery($query);

  } else { die('Insufficient rights to move teams'); }
}

function PoolConfirmMoves($poolId, $visible = null) {
  PoolMakeMoves($poolId);
  // Check if a BYE team has been scheduled. If so, fill in standard result
  $changes = CheckBYE($poolId);
  if ($changes > 0) {
    // check if the game with the BYE team is scheduled. If so, exchange it with the game that is not scheduled.
    CheckBYESchedule($poolId);
  }

  ResolvePoolStandings($poolId);

  if (isset($visible)) {
    SetPoolVisibility($poolId, $visible);
  }
}

/**
 * Set team's scheduling name in pool.
 * Enter description here ...
 * @param int $scheduling_id
 * @param string $name
 * @param string $season
 */
function PoolSetSchedulingName($scheduling_id, $name, $season) {
  if (isSeasonAdmin($season)) {
    $query = sprintf("UPDATE uo_scheduling_name SET name='%s' WHERE scheduling_id=%d",
    mysql_real_escape_string($name),
    (int)$scheduling_id);

    DBQuery($query);
  } else { die('Insufficient rights to change scheduling names'); }
}

/**
 * Test if games can be generated to given pool.
 *
 * @param int $poolId
 * @return true if games can be generated.
 */
function CanGenerateGames($poolId) {
  $query = sprintf("SELECT count(*) FROM uo_game WHERE pool=%d",
  (int)$poolId);
  $result = mysql_query($query);
  if (!$result) { die('Invalid query: ' . mysql_error()); }
  if (!$row = mysql_fetch_row($result)) return false;
  return $row[0] == 0;
}

/**
 * Test if there is real teams in pool.
 *
 * @param int $poolId
 * @return true if there is no real teams in pool.
 */
function PseudoTeamsOnly($poolId){
  $query = sprintf("SELECT count(*) FROM uo_moveteams WHERE topool=%d AND ismoved=0 AND scheduling_id IS NOT NULL",
  (int)$poolId);
  $result = DBQueryToValue($query);
  if ($result > 0) {
    return true;
  } else return false;
}

/**
 * Test if pool can be deleted.
 *
 * @param int $poolId
 */
function CanDeletePool($poolId) {
  $query = sprintf("SELECT count(*) FROM uo_team WHERE pool=%d",
  (int)$poolId);
  $result = mysql_query($query);
  if (!$result) { die('Invalid query: ' . mysql_error()); }
  if (!$row = mysql_fetch_row($result)) return false;
  if ($row[0] == 0) {
    $query = sprintf("SELECT count(*) FROM uo_game WHERE pool=%d",
    (int)$poolId);
    $result = mysql_query($query);
    if (!$result) { die('Invalid query: ' . mysql_error()); }
    if (!$row = mysql_fetch_row($result)) return false;
    if ($row[0] == 0) {
      $query = sprintf("SELECT count(*) FROM uo_game_pool WHERE pool=%d",
      (int)$poolId);
      $result = mysql_query($query);
      if (!$result) { die('Invalid query: ' . mysql_error()); }
      if (!$row = mysql_fetch_row($result)) return false;
      return $row[0] == 0;
    } else return false;
  } else return false;
}

/**
 * Test if team can be deleted from pool
 *
 * @param int $poolId
 * @param int $teamId
 */
function CanDeleteTeamFromPool($poolId, $teamId) {
  $query = sprintf("SELECT count(*) FROM uo_game WHERE pool=%d AND ((hometeam='%s' OR visitorteam='%s') AND (homescore>0 OR visitorscore>0 OR hasstarted>0))",
  (int)$poolId,
  (int)$teamId,
  (int)$teamId);
  $result = mysql_query($query);
  if (!$result) { die('Invalid query: ' . mysql_error()); }
  if (!$row = mysql_fetch_row($result)) return false;
  if ($row[0] == 0) {
    $query = sprintf("SELECT count(*) FROM uo_game_pool
                          LEFT JOIN uo_game ON (uo_game_pool.game=uo_game.game_id)
                          WHERE uo_game_pool.pool=%d AND ((hometeam='%s' OR visitorteam='%s')
                            AND (homescore>0 OR visitorscore>0 OR hasstarted>0)) AND uo_game_pool.timetable=1",
    (int)$poolId,
    (int)$teamId,
    (int)$teamId);
    $result = mysql_query($query);
    if (!$result) { die('Invalid query: ' . mysql_error()); }
    if (!$row = mysql_fetch_row($result)) return false;
    if ($row[0] == 0) {
      return true;
    }
  }
  return false;
}

/**
 * Add a game into pool.
 *
 * @param int $poolId - pool to add
 * @param int $home - home team id
 * @param int $away - away team id
 * @param boolean $psudoteams - true if scheduling ids used instead of team ids
 * @param boolean $homeresp - true if home team is responsible of game's score sheet
 */
function PoolAddGame($poolId, $home, $away, $psudoteams=false, $homeresp=false) {
  $poolInfo = PoolInfo($poolId);
  if (hasEditTeamsRight($poolInfo['series'])) {

    if($psudoteams){
      $hometeam = "scheduling_name_home";
      $visitorteam = "scheduling_name_visitor";
    }else{
      $hometeam = "hometeam";
      $visitorteam = "visitorteam";
    }
    if($homeresp){

      $query = sprintf("INSERT INTO uo_game ($hometeam, $visitorteam, pool, valid, respteam)
                    values (%d, %d, %d, 1, %d)",
      (int)$home,
      (int)$away,
      (int)$poolId,
      (int)$home);
    }else{
      $query = sprintf("INSERT INTO uo_game ($hometeam, $visitorteam, pool, valid)
                    values (%d, %d, %d, 1)",
      (int)$home,
      (int)$away,
      (int)$poolId);
    }
    DBQuery($query);
    $query = sprintf("INSERT INTO uo_game_pool (game, pool, timetable) VALUES (%d, %d, 1)",
    mysql_insert_id(),
    (int)$poolId);
    DBQuery($query);
    LogPoolUpdate($poolId, "Game added");

  } else { die('Insufficient rights to add games'); }
}


/**
 * Creates playoff tree according team numbers in pool and moves between sub-pools.
 *
 * @param int $poolId
 * @param boolean $generate - true if pools are actually generated
 */
function GeneratePlayoffPools($poolId, $generate=true){
  $poolInfo = PoolInfo($poolId);
  if (hasEditTeamsRight($poolInfo['series'])) {

    $pools = array();

    $query = sprintf("SELECT team.team_id from uo_team_pool as tp left join uo_team team
                on (tp.team = team.team_id) WHERE tp.pool=%d ORDER BY tp.rank",
    (int)$poolId);
    $result = DBQuery($query);

    if(mysql_num_rows($result)==0){
      $pseudoteams = true;
      $query = sprintf("SELECT pt.scheduling_id AS team_id from uo_scheduling_name pt
                    LEFT JOIN uo_moveteams mt ON(pt.scheduling_id = mt.scheduling_id)
                    WHERE mt.topool=%d ORDER BY mt.torank",
      (int)$poolId);
      $result = DBQuery($query);
    }
    $teams = mysql_num_rows($result);


    $rounds = 0;
    $roundsToWin = ($teams+1)/2;//+1 to support odd team playoffs
    if($teams==6){$roundsToWin = 4;} //hardcoded quick solution
    while($roundsToWin>=1){
      $roundsToWin = $roundsToWin/2;
      $rounds++;
    }

    //read layout templates
    $html = PlayoffTemplate($teams, $rounds, $poolInfo['playoff_template']);

    // try to parse moves
    $specialmoves=false;

     if (substr($html,0,26)=="<!-- corresponding moves:") {
       $movestring=substr($html,28,strpos($html,"-->")-29);
       $movelines=explode("\n",$movestring);
       foreach ($movelines as $move) {
         $moves[]=str_getcsv($move," ");
       }
       if (count($moves)==$rounds) {
         $specialmoves=true; //parsing succesful
       }
     }

    $poolInfo['specialmoves']=$specialmoves;

    //echo "<p>rounds to win $rounds</p>";
    $prevpoolId = $poolId;
    $realteams = $teams; // real number of teams
    if(is_odd($teams)) { // support for odd number of teams in playoff
      $teams--; // pretend that there is an even number of teams
    }
    $offset = $teams;
    $name = "Round 1";
    $prevname = "R1";
    $poolname = $poolInfo['name'];

    //first round is played in master pool
    for($i=1;$i<$rounds;$i++){

      if($rounds-$i==1){
        $name = "Finals";
        $prevname="SF";
      }elseif($rounds-$i==2){
        $name = "Semifinals";
        $prevname="QF";
      }elseif($rounds-$i==3){
        $name = "Quarterfinals";
        $prevname="R1";
      }else{
        $name = "Round ".($i);
        $prevname="R".($i+1);
      }

      if($generate){
        //create pool
        $name = $poolname." ". $name;
        $id = PoolFromAnotherPool($poolInfo['series'],$name,$poolInfo['ordering'].$i,$prevpoolId,true);
        if($rounds-$i==1){
          DBQuery("UPDATE uo_pool SET placementpool=1 WHERE pool_id=$id");
        }

        //add moves

        if ($specialmoves){ // use specialmoves from HTML comment
          for ($j = 0; $j < $realteams; $j++) {
            $frompos = $moves[$i - 1][$j];
            if ($frompos == $realteams && $realteams > $teams) { // in case of odd number of teams
              $movename = $prevname . " Team " . $realteams;
            } elseif (is_odd($frompos)) {
              $movename = $prevname . " Winner " . (($frompos + 1) / 2);
            } else {
              $movename = $prevname . " Loser " . ($frompos / 2);
            }
            PoolAddMove($prevpoolId, $id, $frompos, $j + 1, $movename);

            if ($i == $rounds - 1) { // add also the special ranking moves
              AddSpecialRankingRule($id, $moves[$i][$j], $j + 1, " Rank " . ($j + 1));
            }
          }
        } else { // do standard moves as before
          $totwinners=0;
          $totlosers=0;
          //loop pool in slice according round going
          for($j=0;$j<$teams;$j=$j+$offset){

            $winners=0;
            $losers=0;
            //handle teams in pairs: winner odd and loser in even number
            for($k=0;$k<$offset;$k=$k+2){

              //winners
              $winners++;
              $totwinners++;
              $frompos = $j+$k+1;
              $torank = $j+$winners;
              PoolAddMove($prevpoolId,$id,$frompos,$torank,"$prevname Winner $totwinners");

              //losers
              $losers++;
              $totlosers++;
              $frompos = $j+$k+2;
              $torank = $j+$offset/2+$losers;
              PoolAddMove($prevpoolId,$id,$frompos,$torank,"$prevname Loser $totlosers");
            }
          }
          // in case of odd number of teams:
          for($k=$j;$k<$realteams;$k++) {
            // echo "adding extra move ".($k+1)."<br>";
            $frompos=$k+1;
            $torank=$k+1;
            PoolAddMove($prevpoolId,$id,$frompos,$torank,"$prevname Team $k");
          }
        }

        $pools[] = PoolInfo($id);
        $prevpoolId = $id;
      }else{
        $pools[] = $poolInfo;
        $pools[$i-1]['name'] = $poolname." ". $name;
      }
      $offset = $offset/2;
    }

    return $pools;

  } else { die('Insufficient rights to add games'); }
}

/**
 * Generate games into pool.
 *
 * @param int $poolId
 * @param int $rounds - how many rounds f.ex. in Round Robin pool
 * @param boolean $generate - true if games are created
 * @param boolean $nomutual - true when no mutual games are created in case that teams are moved from same pool.
 * @param boolean $homeresp - true if home team is responsible of score sheet.
 */
function GenerateGames($poolId, $rounds=1, $generate=true, $nomutual=false, $homeresp=false) {
  $poolInfo = PoolInfo($poolId);
  if (hasEditTeamsRight($poolInfo['series'])) {
    if (CanGenerateGames($poolId)) {

      $pseudoteams = false;

      $poolInfo = PoolInfo($poolId);
      $query = sprintf("SELECT team.team_id from uo_team_pool as tp left join uo_team team
                on (tp.team = team.team_id) WHERE tp.pool=%d ORDER BY tp.rank",
      (int)$poolId);
      $result = DBQuery($query);

      if(mysql_num_rows($result)==0){
        $pseudoteams = true;
        $query = sprintf("SELECT pt.scheduling_id AS team_id from uo_scheduling_name pt
                    LEFT JOIN uo_moveteams mt ON(pt.scheduling_id = mt.scheduling_id)
                    WHERE mt.topool=%d ORDER BY mt.torank",
        (int)$poolId);
        $result = DBQuery($query);
      }

      $teams = array();
      while ($row = mysql_fetch_row($result)) {
        $teams[] = $row[0];
      }

      $games = array();

      // Round robin
      if ($poolInfo['type'] == 1) {
        for ($r = 0; $r < $rounds; $r++){
          for ($i = 0; $i < count($teams); $i++) {
            $skipped=0;
            for ($j=count($teams) - 1; $j>$i ; $j--) {
              //do not generate mutual games if teams are moved from same pools
              if($nomutual){
                if($pseudoteams){
                  $homepool = PoolGetFromPoolBySchedulingId($teams[$i]);
                  $awaypool = PoolGetFromPoolBySchedulingId($teams[$j]);
                  if($homepool==$awaypool){
                    $skipped++;
                    continue;
                  }
                }else{
                  $homepool = PoolGetFromPoolByTeamId($poolId,$teams[$i]);
                  $awaypool = PoolGetFromPoolByTeamId($poolId,$teams[$j]);
                  if($homepool==$awaypool){
                    $skipped++;
                    continue;
                  }
                }
              }
              $game = array("home"=>0,"away"=>0);
              $flip = flip($i+$r, $j+$skipped);
              if ($flip) {
                $game['home']= (int)$teams[$j];
                $game['away']= (int)$teams[$i];
              } else {
                $game['home']= (int)$teams[$i];
                $game['away']= (int)$teams[$j];
              }

              $games[] = $game;
            }
          }
        }
      } elseif ($poolInfo['type'] == 2) {
        for ($r = 0; $r < $rounds; $r++){
          for ($i=0; $i<count($teams); $i+=2) {

            //support for odd numbers of team
            if($i+1<count($teams)){
              $game = array("home"=>0,"away"=>0);
              if(is_odd($r)){
                $game['home']= (int)$teams[$i+1];
                $game['away']= (int)$teams[$i];
              }else{
                $game['home']= (int)$teams[$i];
                $game['away']= (int)$teams[$i+1];
              }
              $games[] = $game;
            }
          }
        }
      } elseif ($poolInfo['type'] == 3) {
        // game generation for Swiss draw round

        if(count($teams) % 2 ==1) {
          $games[]=false;
        }else {
          if ($poolInfo['continuingpool']) {
            // if it's a continuation pool, team 1 plays team 2, etc.
            for ($i=0;$i<count($teams);$i+=2) {
              $game = array("home"=>0,"away"=>0);
              $game['home']= (int)$teams[$i];
              $game['away']= (int)$teams[$i+1];
              $games[] = $game;
            }
          }else {
            // for the first round, for 2n teams (has to be even)
            // team 1 plays team n+1, 2 plays n+2 etc.
            $halfnbteams=count($teams)/2;
            for ($i=0; $i<$halfnbteams; $i++) {
              $game = array("home"=>0,"away"=>0);
              $game['home']= (int)$teams[$i];
              $game['away']= (int)$teams[$i+$halfnbteams];
              $games[] = $game;
            }
          }
        }
        //crossmatch
      } elseif ($poolInfo['type'] == 4) {
        for ($r = 0; $r < $rounds; $r++){
          for ($i=0; $i<count($teams); $i+=2) {

            //support for odd numbers of team
            if($i+1<count($teams)){
              $game = array("home"=>0,"away"=>0);
              if(is_odd($r)){
                $game['home']= (int)$teams[$i+1];
                $game['away']= (int)$teams[$i];
              }else{
                $game['home']= (int)$teams[$i];
                $game['away']= (int)$teams[$i+1];
              }
              $games[] = $game;
            }
          }
        }
      }

      if($generate){
        foreach($games as $game){
          if($homeresp){
            if($pseudoteams){
              $query = sprintf("INSERT INTO uo_game (scheduling_name_home, scheduling_name_visitor, pool, valid, respteam)
                                    values (%d, %d, %d, 1, %d)",
              (int)$game['home'],
              (int)$game['away'],
              (int)$poolId,
              (int)$game['home']);
            }else{
              $query = sprintf("INSERT INTO uo_game (hometeam, visitorteam, pool, valid, respteam)
                                    values (%d, %d, %d, 1, %d)",
              (int)$game['home'],
              (int)$game['away'],
              (int)$poolId,
              (int)$game['home']);
            }
          }else{
            if($pseudoteams){
              $query = sprintf("INSERT INTO uo_game (scheduling_name_home, scheduling_name_visitor, pool, valid)
                                    values (%d, %d, %d, 1)",
              (int)$game['home'],
              (int)$game['away'],
              (int)$poolId);
            }else{
              $query = sprintf("INSERT INTO uo_game (hometeam, visitorteam, pool, valid)
                                    values (%d, %d, %d, 1)",
              (int)$game['home'],
              (int)$game['away'],
              (int)$poolId);
            }
          }
          DBQuery($query);
          $query = sprintf("INSERT INTO uo_game_pool (game, pool, timetable) VALUES (%d, %d, 1)",
          mysql_insert_id(),
          (int)$poolId);
          DBQuery($query);
          // check if a game with the BYE team has been added
          // if yes, automatically fill in the score
          CheckBYE($poolId);
          LogPoolUpdate($poolId, "Games generated");
        }
      }
      return $games;

    }
  } else { die('Insufficient rights to add games'); }
}

/**
 * Used to test which team is home.
 *
 * @param int $i
 * @param int $j
 */
function flip($i, $j) {
  is_odd($i) ? $ret = is_odd($j): $ret = !is_odd($j);
  return $ret;
}

/**
 * Export pool parameters to CSV format.
 *
 * @param string $season
 * @param string $separator
 */
function PoolsToCsv($season,$separator){

  $result = array();

  $pools = SeasonPools($season, true, true);
  foreach ($pools as $pool) {
    $poolinfo = PoolInfo($pool['pool_id']);
    $standings = PoolTeams($poolinfo['pool_id'], "rank");


    foreach($standings as $row){

      $stats = TeamStatsByPool($poolinfo['pool_id'], $row['team_id']);
      $points = TeamPointsByPool($poolinfo['pool_id'], $row['team_id']);

      $poolrow = array(
            "Division"=>$poolinfo['seriesname'],
            "Pool"=>$poolinfo['name'],
            "Standing"=>$row['activerank'],
            "Team"=>$row['name'],
            "Games"=>$stats['games'],
            "Wins"=>$stats['wins'],
            "Losses"=>intval($stats['games'])-intval($stats['wins']),
            "GoalsFor"=>$points['scores'],
            "GoalsAgainst"=>$points['against'],
            "GoalsDiff"=>intval($points['scores'])-intval($points['against'])
      //"Spirit"=>number_format(SafeDivide(intval($points['spirit']), intval($stats['games'])),1)
      );

      $result[] = $poolrow;
    }
  }

  return ArrayToCsv($result, $separator);
}

function SeriesRanking($series_id) {
  $ranking = array ();
  $ppools = SeriesPlacementPoolIds($series_id);
  foreach ($ppools as $ppool) {
    $teams = PoolTeams($ppool['pool_id']);
    $steams = PoolSchedulingTeams($ppool['pool_id']);
    if (count($teams) < count($steams)) {
      $totalteams = count($steams);
    } else {
      $totalteams = count($teams);
    }
    
    for ($i = 1; $i <= $totalteams; $i++) {
      $moved = PoolMoveExist($ppool['pool_id'], $i);
      if (!$moved) {
        $team = PoolTeamFromStandings($ppool['pool_id'], $i);
        $gamesleft = TeamPoolGamesLeft($team['team_id'], $ppool['pool_id']);
        if ($ppool['played'] || ($ppool['type'] == 2 && mysql_num_rows($gamesleft) == 0)) {
          $ranking[] = $team;
        } else {
          $ranking[] = null;
        }
      }
    }
  }
  return $ranking;
}

function PlayoffTemplate($teams, $rounds, $id="") {
  global $include_prefix;

  //read layout templates
  if (empty($id)) {
    $id = $teams."_teams_".$rounds."_rounds";
  }
  if (is_file($include_prefix."cust/".CUSTOMIZATIONS."/layouts/".$id.".html")) {
    $ret2 = file_get_contents($include_prefix."cust/".CUSTOMIZATIONS."/layouts/".$id.".html");
  }elseif (is_file($include_prefix."cust/default/layouts/".$id.".html")) {
    $ret2 = file_get_contents($include_prefix."cust/default/layouts/".$id.".html");
  }else{
    $ret2 = "";
  }
  return $ret2;
}
?>
