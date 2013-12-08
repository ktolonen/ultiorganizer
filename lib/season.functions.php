<?php
/**
 * @file
 * This file contains all event handling functions. For historical reasons event (tournament/season) is referred as a season.
 *
 */

/**
 * Returns all series (aka. division) played on given season.
 *
 * @param string $seasonId uo_season.season_id
 * @param boolean $onlyvalid true if only uo_series.valid=1 rows selected.
 * @return 
 */
function SeasonSeries($seasonId, $onlyvalid=false){
  $query = sprintf("SELECT ser.* 
  	FROM uo_series ser
	WHERE ser.season = '%s'",
    mysql_real_escape_string($seasonId));

  if ($onlyvalid) {
    $query .= " AND ser.valid=1";
  }
  
  $query .= " ORDER BY ser.ordering ASC, ser.series_id ASC";
  return DBQueryToArray($query);
}

/**
 * Returns all pools with series (aka. division) played on given season.
 *
 * @param string $seasonId uo_season.season_id
 * @param boolean $onlyvisible true if only uo_pool.visible=1 rows selected.
 * @param boolean $onlyvalid true if only uo_series.valid=1 rows selected.
 * @return php array of pools
 */
function SeasonPools($seasonId, $onlyvisible=false, $onlyvalid=true){
  
  $query = sprintf("SELECT pool.pool_id, pool.name AS poolname, pool.continuingpool, ser.series_id, ser.name AS seriesname 
  	FROM uo_pool pool
	LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
	WHERE ser.season = '%s'",
    mysql_real_escape_string($seasonId));

  if ($onlyvisible) {
    $query .= " AND pool.visible=1";
  }
  
  if ($onlyvalid) {
    $query .= " AND ser.valid=1";
  }
  
  $query .= " ORDER BY ser.ordering ASC, pool.ordering ASC, pool.pool_id ASC";
  return DBQueryToArray($query);
}

/**
 * Returns array of event types like indoor, outdoor, beach.
 *
 * @return array of Season types 
 */
function SeasonTypes() {
  return array("indoor","outdoor","beach");
}

/**
 * Returns current season, which can be user selected if multiple seasons set as current (uo_season.iscurrent=1).
 * User selected season is stored into $_SESSION['userproperties']['selseason']
 * @return uo_season.season_id 
 */
function CurrentSeason() {
  if (isset($_SESSION['userproperties']['selseason'])) {
    return $_SESSION['userproperties']['selseason'];
  }
  $query = sprintf("SELECT season_id FROM uo_season WHERE iscurrent=1 ORDER BY starttime DESC");
  return DBQueryToValue($query);
}

/**
 * Returns all current seasons (uo_season.iscurrent=1).
 * 
 * @return mysql array 
 */
function CurrentSeasons() {
  $query = sprintf("SELECT season_id AS season_id, name FROM uo_season WHERE iscurrent=1 ORDER BY starttime DESC");
  return DBQuery($query);
}

/**
 * Returns current season name.
 * @see CurrentSeason()
 * @return String uo_season.name 
 */
function CurrentSeasonName(){
  if (isset($_SESSION['userproperties']['selseason'])) {
    $query = sprintf("SELECT name FROM uo_season WHERE season_id='%s'",
    mysql_real_escape_string($_SESSION['userproperties']['selseason']));
    return U_(DBQueryToValue($query));
  }
  $query = sprintf("SELECT name FROM uo_season WHERE iscurrent=1 ORDER BY starttime DESC");
  return U_(DBQueryToValue($query));
}

/**
 * Returns name for given season.
 * @param string $seasonId uo_season.season_id
 * @return String uo_season.name 
 */
function SeasonName($seasonId){
  $query = sprintf("SELECT name FROM uo_season WHERE season_id='%s'",
    mysql_real_escape_string($seasonId));
  $name = U_(DBQueryToValue($query));
  return ($name==-1) ? "" : $name;
}

/**
 * Returns type for given season.
 * @param string $seasonId uo_season.season_id
 * @return String uo_season.type
 */
function Seasontype($seasonId){
  $query = sprintf("SELECT type FROM uo_season WHERE season_id='%s'",
    mysql_real_escape_string($seasonId));
  $type = DBQueryToValue($query);
  return ($type==-1) ? "" : $type;
}

/**
 * Returns information about season.
 * @param string $seasonId uo_season.season_id 
 * @return uo_season.*
 */
function SeasonInfo($seasonId){
  $query = sprintf("SELECT * FROM uo_season WHERE season_id='%s'",
    mysql_real_escape_string($seasonId));
  return DBQueryToRow($query, true);
}

/**
 * Returns all seasons.
 * 
 * @param array $filter sql conditions
 * @param array $ordering sql ordering  
 * @return mysql array of seasons
 */
function Seasons($filter=null, $ordering=null){
  if (!isset($ordering)) {
    $ordering = array("season.starttime" => "DESC");
  }
  $orderby = CreateOrdering(array("uo_season" => "season"), $ordering);
  $where = CreateFilter(array("uo_season" => "season"), $filter);
  $query = sprintf("SELECT season_id, name FROM uo_season season $where $orderby");
  return DBQuery(trim($query));
}

/**
 * Returns all seasons.
 * 
 * @return array of seasons
 */
function SeasonsArray(){
  $query = sprintf("SELECT season_id, name FROM uo_season season ORDER BY starttime DESC");
  return DBQueryToArray($query);
}

/**
 * Returns all seasons for given type.
 * @see SeasonTypes()
 * 
 * @param string $seasonId uo_season.season_id 
 * @return php array of seasons
 */
function SeasonsByType($seasontype){
  $query = sprintf("SELECT season_id AS season_id, name FROM uo_season WHERE type='%s'
		ORDER BY starttime DESC", mysql_real_escape_string($seasontype));
  return DBQueryToArray($query);
}

/**
 * Returns all seasons having enrollment open.
 * 
 * @return php-array with uo_season.season_id as key and name as value.
 */
function EnrollSeasons() {
  $query = sprintf("SELECT season_id AS season_id, name FROM uo_season WHERE enrollopen=1 ORDER BY starttime DESC");
  $result = mysql_query($query);
  if (!$result) { die('Invalid query: ' . mysql_error()); }
  $ret = array();
  while ($row = mysql_fetch_assoc($result)) {
    $ret[$row['season_id']] = $row['name'];
  }
  return $ret;
}

/**
 * Returns all players playing on given season.
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of players
 */
function SeasonAllPlayers($seasonId) {
  $query = sprintf("SELECT p.player_id FROM uo_player p
			LEFT JOIN uo_team t ON (p.team=t.team_id)
			LEFT JOIN uo_series ser ON (t.series=ser.series_id)
			WHERE ser.season='%s' ORDER BY ser.name, t.name,p.lastname, p.firstname",
  mysql_real_escape_string($seasonId));
  return DBQueryToArray($query);
}



/**
 * Returns all teams playing on given season.
 *
 * @param string $seasonId uo_season.season_id
 * @param boolean $onlyvalid true if only uo_team.valid=1 rows selected.
 * @return php array of teams
 */
function SeasonTeams($season, $onlyvalid=true) {
  $query = sprintf("SELECT team.*, ser.name AS seriesname
		FROM uo_team team
		LEFT JOIN uo_series ser ON(team.series=ser.series_id)
		WHERE ser.season='%s'",
        mysql_real_escape_string($season));
		
  if ($onlyvalid) {
    $query .= " AND team.valid>=0";
  }
  $query .= " ORDER BY ser.ordering, team.name";
  
  return DBQueryToArray($query);
}

/**
 * Returns all field reservations for given season.
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of reservations
 */
function SeasonReservations($seasonId, $group="all"){
  $query = sprintf("SELECT  pr.*, pl.name FROM uo_reservation pr 
		LEFT JOIN uo_location pl ON (pr.location=pl.id)
		WHERE pr.season='%s'",
        mysql_real_escape_string($seasonId));
  
  if($group != "all"){
    $query .= sprintf(" AND pr.reservationgroup = '%s'",  mysql_real_escape_string($group));
  }
  
  $query .= " ORDER BY pr.starttime, pr.reservationgroup ASC, pl.name, pr.fieldname+0";
  
  return DBQueryToArray($query);
}

/**
 * Returns all reservation groups for given season.
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of reservations
 */
function SeasonReservationgroups($seasonId) {
  $query = sprintf("
		SELECT DISTINCT pr.reservationgroup
		FROM uo_reservation pr
		WHERE pr.season='%s'
		ORDER BY pr.starttime, pr.reservationgroup ASC, pr.fieldname+0",
  mysql_real_escape_string($seasonId));

  return DBQueryToArray($query);
}

/**
 * Returns all games played on given season without scheduled starting time.
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of games
 */
function SeasonGamesNotScheduled($seasonId){
  $query = sprintf("
		SELECT p.hometeam, Kj.name AS hometeamname, p.visitorteam, Vj.name As visitorteamname, p.time, p.game_id, p.homescore, p.visitorscore, 
			p.game_id IN (SELECT DISTINCT game FROM uo_goal) As goals,
			Kj.team_id AS kId, Vj.team_id AS vId,phome.name AS phometeamname, pvisitor.name AS pvisitorteamname,
			ps.name AS poolname, ser.name AS seriesname
		FROM uo_game p 
		LEFT JOIN uo_team AS Kj ON (p.hometeam=Kj.team_id)
		LEFT JOIN uo_team AS Vj ON (p.visitorteam=Vj.team_id)
		LEFT JOIN uo_game_pool pss ON (p.game_id=pss.game) 
		LEFT JOIN uo_pool ps ON (p.pool=ps.pool_id)
		LEFT JOIN uo_series ser ON (ps.series=ser.series_id)
		LEFT JOIN uo_scheduling_name AS phome ON (p.scheduling_name_home=phome.scheduling_id)
		LEFT JOIN uo_scheduling_name AS pvisitor ON (p.scheduling_name_visitor=pvisitor.scheduling_id)
		WHERE ser.season='%s' AND (p.time IS NULL OR p.reservation IS NULL OR p.reservation='0')
		ORDER BY time ASC ",
    mysql_real_escape_string($seasonId));
  return DBQueryToArray($query);
}

/**
 * Returns all games played on given season.
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of games
 */
function SeasonAllGames($season){
  $query = sprintf("
		SELECT game.*
		FROM uo_game game 
		LEFT JOIN uo_pool pool ON (pool.pool_id=game.pool) 
		LEFT JOIN uo_series ser ON (ser.series_id=pool.series)
		WHERE ser.season='%s'
		ORDER BY game.game_id",
  mysql_real_escape_string($season));

  return DBQueryToArray($query);
}

/**
 * Returns all teamadmins on given season.
 *
 * Access level: editseason
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of users
 */
function SeasonTeamAdmins($seasonId, $group=false) {
  $seasonrights = getEditSeasons($_SESSION['uid']);
  if (isset($seasonrights[$seasonId])) {
    if($group){
      $query = sprintf("SELECT u.userid, u.name, u.email, j.team_id, GROUP_CONCAT(j.name SEPARATOR ',') as teamname FROM uo_users u
  			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
  			LEFT JOIN uo_team j ON (SUBSTRING_INDEX(up.value, ':', -1)=j.team_id)
  			WHERE j.series IN (SELECT series_id FROM uo_series WHERE season='%s') AND up.value LIKE 'teamadmin:%%'
  			GROUP BY u.email
  			ORDER BY j.series, j.name",
      mysql_real_escape_string($seasonId));
    }else{
      $query = sprintf("SELECT u.userid, u.name, u.email, j.team_id, j.name as teamname FROM uo_users u
  			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
  			LEFT JOIN uo_team j ON (SUBSTRING_INDEX(up.value, ':', -1)=j.team_id)
  			WHERE j.series IN (SELECT series_id FROM uo_series WHERE season='%s') AND up.value LIKE 'teamadmin:%%'
  			ORDER BY j.series, j.name",
      mysql_real_escape_string($seasonId));
    }
    return DBQueryToArray($query);
  } else { die('Insufficient rights'); }
}

/**
 * Returns all accreditation admins on given season.
 *
 * Access level: editseason
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of users
 */
function SeasonAccreditationAdmins($seasonId, $group=false) {
  $seasonrights = getEditSeasons($_SESSION['uid']);
  if (isset($seasonrights[$seasonId])) {
    if($group){
      $query = sprintf("SELECT u.userid, u.name, u.email, j.team_id, GROUP_CONCAT(j.name SEPARATOR ',') as teamname FROM uo_users u
  			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
  			LEFT JOIN uo_team j ON (SUBSTRING_INDEX(up.value, ':', -1)=j.team_id)
  			WHERE j.series IN (SELECT series_id FROM uo_series WHERE season='%s') AND up.value LIKE 'accradmin:%%'
  			GROUP BY u.email
  			ORDER BY j.series, j.name",
      mysql_real_escape_string($seasonId));
    }else{
      $query = sprintf("SELECT u.userid, u.name, u.email, j.team_id, j.name as teamname FROM uo_users u
  			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
  			LEFT JOIN uo_team j ON (SUBSTRING_INDEX(up.value, ':', -1)=j.team_id)
  			WHERE j.series IN (SELECT series_id FROM uo_series WHERE season='%s') AND up.value LIKE 'accradmin:%%'
  			ORDER BY j.series, j.name",
      mysql_real_escape_string($seasonId));
    }
    return DBQueryToArray($query);
  } else { die('Insufficient rights'); }
}
/**
 * Returns all game admins (scorekeepers) on given season.
 *
 * Access level: editseason
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of users
 */
function SeasonGameAdmins($seasonId) {
  $seasonrights = getEditSeasons($_SESSION['uid']);
  if (isset($seasonrights[$seasonId])) {
      $query = sprintf("SELECT u.userid, u.name, u.email, COUNT(*) AS games FROM uo_users u
  			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
  			LEFT JOIN uo_game g ON (SUBSTRING_INDEX(up.value, ':', -1)=g.game_id)
  			WHERE g.game_id IN (SELECT gp.game FROM uo_game_pool gp 
				LEFT JOIN uo_pool pool ON (pool.pool_id=gp.pool) 
				LEFT JOIN uo_series ser ON (ser.series_id=pool.series)
				WHERE ser.season='%s' AND gp.timetable=1)
  			GROUP BY u.userid
			ORDER BY u.name",
      mysql_real_escape_string($seasonId));
    return DBQueryToArray($query);
  } else { die('Insufficient rights'); }
}

/**
 * Returns all users having admin rights for given season.
 *
 * Access level: editseason
 *
 * @param string $seasonId uo_season.season_id
 * @return php array of users
 */
function SeasonAdmins($seasonId) {
  $seasonrights = getEditSeasons($_SESSION['uid']);
  if (isset($seasonrights[$seasonId])) {
    $query = sprintf("SELECT u.userid, u.name, u.email
			FROM uo_users u
			LEFT JOIN uo_userproperties up ON (u.userid=up.userid)
			WHERE SUBSTRING_INDEX(up.value,':',1)='seasonadmin' AND SUBSTRING_INDEX(up.value, ':', -1)='%s'
			GROUP BY u.userid, u.name, u.email",
    mysql_real_escape_string($seasonId));

    return DBQueryToArray($query);
  } else { die('Insufficient rights'); }
}

/**
 * Deletes a given season.
 *
 * Access level: superadmin
 *
 * @param string $seasonId uo_season.season_id
 * @return boolean TRUE on success or FALSE on error. 
 */
function DeleteSeason($seasonId) {
  if (isSuperAdmin()) {
    Log2("season","delete",SeasonName($seasonId));
    $query = sprintf("DELETE FROM uo_season WHERE season_id='%s'",
      mysql_real_escape_string($seasonId));
     return DBQuery($query);
  } else { die('Insufficient rights to delete season'); }
}

/**
 * Adds a season.
 *
 * Access level: superadmin
 *
 * @param string $seasonId uo_season.season_id
 * @param string $params uo_season fields
 * @return boolean TRUE on success or FALSE on error. 
 */
function AddSeason($seasonId, $params) {
  if (isSuperAdmin()) {
    $query = sprintf("
			INSERT INTO uo_season 
			(season_id, name, type, istournament, isinternational, organizer, category, isnationalteams,
			starttime, endtime, iscurrent, enrollopen, enroll_deadline,spiritpoints,showspiritpoints,
			timezone) 
			VALUES ('%s', '%s', '%s', %d, %d, '%s', '%s', '%d', '%s', '%s', %d, %d, '%s', %d, %d, '%s')",
    mysql_real_escape_string($seasonId),
    mysql_real_escape_string($params['name']),
    mysql_real_escape_string($params['type']),
    (int)$params['istournament'],
    (int)$params['isinternational'],
    mysql_real_escape_string($params['organizer']),
    mysql_real_escape_string($params['category']),
    (int)$params['isnationalteams'],
    mysql_real_escape_string($params['starttime']),
    mysql_real_escape_string($params['endtime']),
    (int)$params['iscurrent'], (int)$params['enrollopen'],
    mysql_real_escape_string($params['enroll_deadline']),
    (int)$params['spiritpoints'],
    (int)$params['showspiritpoints'],
    mysql_real_escape_string($params['timezone']));
    	
    Log1("season","add",$seasonId);

    return DBQuery($query);
  } else { die('Insufficient rights to add season'); }
}

/**
 * Change season properties a season.
 *
 * Access level: seasonadmin
 *
 * @param string $seasonId uo_season.season_id
 * @param string $params uo_season fields
 * @return boolean TRUE on success or FALSE on error. 
 */
function SetSeason($seasonId, $params) {
  if (isSeasonAdmin($seasonId)) {
    $query = sprintf("
			UPDATE uo_season SET
			season_id='%s', name='%s', type='%s', istournament='%d', isinternational='%d', 
			organizer='%s', category='%s', isnationalteams='%d',
			starttime='%s', endtime='%s', iscurrent=%d, enrollopen=%d, enroll_deadline='%s',
			spiritpoints=%d, showspiritpoints=%d, timezone='%s'
			WHERE season_id='%s'",
    mysql_real_escape_string($seasonId),
    mysql_real_escape_string($params['name']),
    mysql_real_escape_string($params['type']),
    (int)$params['istournament'],
    (int)$params['isinternational'],
    mysql_real_escape_string($params['organizer']),
    mysql_real_escape_string($params['category']),
    (int)$params['isnationalteams'],
    mysql_real_escape_string($params['starttime']),
    mysql_real_escape_string($params['endtime']),
    (int)$params['iscurrent'], (int)$params['enrollopen'],
    mysql_real_escape_string($params['enroll_deadline']),
    (int)$params['spiritpoints'],
    (int)$params['showspiritpoints'],
    mysql_real_escape_string($params['timezone']),
    mysql_real_escape_string($seasonId));

    return DBQuery($query);
  } else { die('Insufficient rights to edit season'); }

}

/**
 * Tests if season can be safely removed from database.
 *
 * @param string $seasonId uo_season.season_id
 * @return boolean true if season can be deleted, false otherwise. 
 */
function CanDeleteSeason($seasonId) {
  $query = sprintf("SELECT count(*) FROM uo_series WHERE season='%s'",
  mysql_real_escape_string($seasonId));
  $result = mysql_query($query);
  if (!$result) { die('Invalid query: ' . mysql_error()); }
  if (!$row = mysql_fetch_row($result)) return false;
  if ($row[0] == 0) {
    $query = sprintf("SELECT season_id FROM uo_season WHERE iscurrent=1 AND season_id='%s'",
    mysql_real_escape_string($seasonId));
    $result = mysql_query($query);
    if (!$result) { die('Invalid query: ' . mysql_error()); }
    if (!$row = mysql_fetch_row($result)) return true;
    return !($row[0] == $seasonId);
  } else return false;
}
?>
