<?php
include_once $include_prefix.'lib/image.functions.php';
include_once $include_prefix.'lib/url.functions.php';
include_once $include_prefix.'lib/common.functions.php';

/**
 * Set player details.
 *
 * @param int $playerId
 * @param int $number
 * @param string $fname
 * @param string $lname
 * @param string $accrId
 */
function SetPlayer($playerId, $number, $fname, $lname, $accrId, $profileId) {
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayersRight($playerInfo['team'])) {
    if ($number<0) $number = "null";
    else $number = (int)$number;
    //echo "<p>".$profileId."</p>";
    $query = sprintf("UPDATE uo_player SET num=%s, firstname='%s', lastname='%s', accreditation_id='%s',
    		profile_id='%s'
			WHERE player_id=%d",
    $number,
    mysql_real_escape_string($fname),
    mysql_real_escape_string($lname),
    mysql_real_escape_string($accrId),
    mysql_real_escape_string($profileId),
    (int)$playerId);
    return DBQuery($query);
  } else { die("Insufficient rights to edit player"); }
}

/**
 * Create profile for player.
 *
 * @param unknown_type $playerId
 */
function CreatePlayerProfile($playerId) {
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayersRight($playerInfo['team'])) {

    $query = sprintf("INSERT INTO uo_player_profile (firstname,lastname,accreditation_id,num) VALUES
				('%s','%s','%s','%s')",
    mysql_real_escape_string($playerInfo['firstname']),
    mysql_real_escape_string($playerInfo['lastname']),
    mysql_real_escape_string($playerInfo['accreditation_id']),
    mysql_real_escape_string($playerInfo['num']));
    $profileId = DBQueryInsert($query);

    $query = sprintf("UPDATE uo_player SET profile_id=%d
			WHERE player_id=%d",
    (int)$profileId,
    (int)$playerId);
    $result = DBQuery($query);
  } else { die("Insufficient rights to edit player"); }
}

/**
 * Gets players.
 *
 * @param unknown_type $filter
 * @param unknown_type $ordering
 */
function Players($filter=null, $ordering=null) {
  if (!isset($ordering)) {
    $ordering = array("season.starttime" => "ASC", "series.ordering" => "ASC", "pool.ordering" => "ASC");
  }
  $tables = array("uo_player" => "player", "uo_team" => "team", "uo_pool" => "pool", "uo_series" => "series", "uo_season" => "season");
  $orderby = CreateOrdering($tables, $ordering);
  $where = CreateFilter($tables, $filter);
  $query = "SELECT player_id, CONCAT(player.firstname, ' ', player.lastname) as name, num,
		player.firstname, player.lastname, player.accredited, player.accreditation_id, player.profile_id
		FROM uo_player player
		LEFT JOIN uo_team team ON (player.team=team.team_id)
		LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id)
		LEFT JOIN uo_series series ON (team.series=series.series_id)
		LEFT JOIN uo_season season ON (series.season=season.season_id)
		$where $orderby";
		return DBQuery(trim($query));
}

/**
 * Player info.
 * 
 * @param int $playerId
 */
function PlayerInfo($playerId){
  $query = sprintf("SELECT p.player_id, p.profile_id, CONCAT(p.firstname, ' ', p.lastname) as name, p.firstname,
		p.lastname, p.num, p.accreditation_id, p.team, t.name AS teamname, p.accredited, 
		p.team, t.series, ser.type, ser.name AS seriesname, pp.profile_image, pp.email, pp.gender,
		pp.birthdate
		FROM uo_player p 
		LEFT JOIN uo_team t ON (p.team=t.team_id) 
		LEFT JOIN uo_series ser ON (ser.series_id=t.series)
		LEFT JOIN uo_player_profile pp ON (p.profile_id=pp.profile_id)
		WHERE player_id='%s'",
  mysql_real_escape_string($playerId));

  return DBQueryToRow($query);
}

/**
 * Gets latest playerId for given profile id.
 * @param int $profileId
 */
function PlayerLatestId($profileId){
  if(!empty($profileId)){
    $query = sprintf("SELECT MAX(p.player_id) FROM uo_player p
			LEFT JOIN uo_team t ON (p.team=t.team_id) 
			LEFT JOIN uo_series ser ON (ser.series_id=t.series)
			WHERE p.profile_id=%d",
    (int)$profileId);
    	
    return DBQueryToValue($query);
  }
  return -1;
}

function PlayerListAll($lastname=""){
  $query = "SELECT MAX(player_id) AS player_id, firstname, lastname, num, accreditation_id, profile_id, team, uo_team.name AS teamname
		FROM uo_player p 
		LEFT JOIN uo_team ON p.team=team_id
		WHERE accredited=1";
  if(!empty($lastname) && $lastname!="ALL"){
    $query .= " AND UPPER(lastname) LIKE '". mysql_real_escape_string($lastname)."%'";
  }

  $query .= " GROUP BY profile_id ORDER BY lastname, firstname";

  return DBQuery($query);
}

/**
 * Returns player name. 
 * 
 * @param int $playerId
 */
function PlayerName($playerId) {
  $query = sprintf("SELECT firstname, lastname 
		FROM uo_player p 
		WHERE player_id='%s'",
  mysql_real_escape_string($playerId));

  $row = DBQueryToRow($query);
  return $row['firstname'] ." ". $row['lastname'];
}

/**
 * Get Player's profile.
 * 
 * @param int $profileId
 */
function PlayerProfile($profileId){
  $query = sprintf(" SELECT pp.*
		FROM uo_player_profile pp 
		WHERE pp.profile_id=%d",
      (int)$profileId);

  return DBQueryToRow($query);
}

/**
 * Get player info by accreditation id.
 * 
 * @param int $accrId
 * @param int $series
 */
function PlayerInfoByAccrId($accrId, $series) {
  $query = sprintf("SELECT player_id, firstname, lastname, num, accreditation_id, profile_id, team, team.name AS teamname, accredited
		FROM uo_player p 
		LEFT JOIN uo_team team ON (p.team=team.team_id)
		LEFT JOIN uo_series ser ON (team.series=ser.series_id)
		WHERE p.accreditation_id='%s' AND ser.series_id=%d",
  mysql_real_escape_string($accrId),
  (int)$series);

  return DBQueryToRow($query);

}

/**
 * Get player jersey number in game.
 * 
 * @param int $playerId
 * @param int $gameId
 */
function PlayerNumber($playerId, $gameId) {
  $query = sprintf("SELECT p.num as defnum, pel.num as game 
		FROM uo_player AS p 
		LEFT JOIN (SELECT player, num FROM uo_played  WHERE game=%d)
			AS pel ON (p.player_id=pel.player) 
		WHERE p.player_id=%d",
  (int)$gameId,
  (int)$playerId);

  $result = mysql_query($query);
  if (!$result) { die('Invalid query: ' . mysql_error()); }

  if(!mysql_num_rows($result))
  return -1;

  $row = mysql_fetch_assoc($result);

  if(is_numeric($row['game'])) {
    return intval($row['game']);
  } else if (is_numeric($row['defnum']) && $row['defnum'] >= 0) {
    return intval($row['defnum']);
  } else {
    return -1;
  }
}

/**
 * Get games where given player has made points on given event.
 * 
 * @param int $playerId
 * @param string $seasonId
 */

function PlayerSeasonGames($playerId, $seasonId){
  $query = sprintf("SELECT game_id,hometeam,visitorteam 
		FROM uo_game p 
		WHERE p.pool IN
			(SELECT pool.pool_id 
			FROM uo_pool pool 
				LEFT JOIN uo_series ser ON (pool.series=ser.series_id) 
			WHERE ser.season='%s') 
		AND p.game_id IN (SELECT uo_goal.game FROM uo_goal 
			WHERE scorer=%d OR assist=%d)
		AND p.isongoing=0
		ORDER BY p.time, p.game_id",
    mysql_real_escape_string($seasonId),
  (int)$playerId,
  (int)$playerId);

  return DBQueryToArray($query);
}

/**
 * Total number of played games on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonPlayedGames($playerId, $seasonId){
  $query = sprintf("
		SELECT COUNT(*) AS games 
		FROM uo_played 
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp 
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game) 
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND player='%s'",
  mysql_real_escape_string($seasonId),
  mysql_real_escape_string($playerId),
  mysql_real_escape_string($playerId));

  return DBQueryToValue($query);
}

/**
 * Total number of passes on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonPasses($playerId, $seasonId) {
  $query = sprintf("SELECT COUNT(*) AS passes
		FROM uo_goal 
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 			
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND assist='%s'",
  mysql_real_escape_string($seasonId),
  mysql_real_escape_string($playerId),
  mysql_real_escape_string($playerId));

  return DBQueryToValue($query);
}

/**
 * Total number of goals on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonGoals($playerId, $seasonId) {
  $query = sprintf("SELECT COUNT(*) AS goals
		FROM uo_goal 
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)			
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND scorer='%s'",
  mysql_real_escape_string($seasonId),
  mysql_real_escape_string($playerId),
  mysql_real_escape_string($playerId));

  return DBQueryToValue($query);
}

/**
 * Total number of callahan goals on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonCallahanGoals($playerId, $seasonId) {
  $query = sprintf("SELECT COUNT(*) AS goals
		FROM uo_goal 
		WHERE game IN (SELECT gp.game FROM uo_game_pool gp
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS ug ON (pp.game=ug.game_id) 
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)			
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1 AND ug.isongoing=0) 
		AND scorer='%s' AND iscallahan=1",
  mysql_real_escape_string($seasonId),
  mysql_real_escape_string($playerId),
  mysql_real_escape_string($playerId));

  return DBQueryToValue($query);
}

/**
 * Total number of wins on given season by given player.
 * 
 * @param int $playerId
 * @param string $seasonId
 */
function PlayerSeasonWins($playerId, $teamId, $seasonId){
  $query = sprintf("SELECT COUNT(*) AS wins
		FROM uo_played p
		WHERE p.game IN (SELECT gp.game FROM uo_game_pool gp 
			LEFT JOIN uo_played AS pp ON (pp.game=gp.game)
			LEFT JOIN uo_game AS g ON (g.game_id=gp.game)
			LEFT JOIN uo_pool AS pool ON (pool.pool_id=gp.pool)
			LEFT JOIN uo_series AS ser ON (pool.series=ser.series_id)	
			WHERE ser.season='%s' AND pp.player='%s' AND timetable=1
			AND g.isongoing=0
			AND ((g.homescore>g.visitorscore AND g.hometeam='%s')
			OR (g.homescore<g.visitorscore AND g.visitorteam='%s'))) 
		AND p.player='%s'",
  mysql_real_escape_string($seasonId),
  mysql_real_escape_string($playerId),
  mysql_real_escape_string($teamId),
  mysql_real_escape_string($teamId),
  mysql_real_escape_string($playerId));

  return DBQueryToValue($query);
}

/**
 * Player's game events in given game.
 * 
 * @param int $playerId
 * @param int $gameId
 */
function PlayerGameEvents($playerId, $gameId){
  $query = sprintf(" SELECT time,homescore,visitorscore,assist,scorer,iscallahan 
		FROM uo_goal 
		WHERE game=%d AND (scorer=%d OR assist=%d) 
		ORDER BY time",
    (int)$gameId,
    (int)$playerId,
    (int)$playerId);

  return DBQueryToArray($query);
}

/**
 * Add or update player profile.
 * 
 * @param int $teamId
 * @param int $playerId
 * @param int $profile
 */
function SetPlayerProfile($teamId, $playerId, $profile) {
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId) && $playerInfo['team']==$teamId) {

    $query = sprintf("SELECT pp.profile_id
				FROM uo_player_profile pp 
				WHERE pp.profile_id=%d",
            (int)$playerInfo['profile_id']);

    $exist = DBQueryRowCount($query);
    
    //SetPlayer($playerId, $profile['num'], $profile['firstname'], $profile['lastname'], $profile['accreditation_id']);
    if (empty($profile['num'])||$profile['num']<0){
      $number = "null";
    }else{
      $number = (int)$profile['num'];
    }

    //update player data according profile data
    $query = sprintf("UPDATE uo_player SET num=%s, firstname='%s', lastname='%s', accreditation_id='%s'
			WHERE player_id=%d",
        $number,
        mysql_real_escape_string($profile['firstname']),
        mysql_real_escape_string($profile['lastname']),
        mysql_real_escape_string($profile['accreditation_id']),
        (int)$playerId);
    	
    DBQuery($query);

    //add
    if(!$exist){
      $query = sprintf("INSERT INTO uo_player_profile (accreditation_id, firstname,
			lastname, num, email, nickname, gender, info, national_id, birthdate, birthplace, nationality, 
			throwing_hand, height, weight, position, story, achievements, public) VALUES 
			('%s', '%s','%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
			'%s', '%s', '%s', '%s', '%s', '%s')",
      mysql_real_escape_string($profile['accreditation_id']),
      mysql_real_escape_string($profile['firstname']),
      mysql_real_escape_string($profile['lastname']),
      mysql_real_escape_string($profile['num']),
      mysql_real_escape_string($profile['email']),
      mysql_real_escape_string($profile['nickname']),
      mysql_real_escape_string($profile['gender']),
      mysql_real_escape_string($profile['info']),
      mysql_real_escape_string($profile['national_id']),
      mysql_real_escape_string($profile['birthdate']),
      mysql_real_escape_string($profile['birthplace']),
      mysql_real_escape_string($profile['nationality']),
      mysql_real_escape_string($profile['throwing_hand']),
      mysql_real_escape_string($profile['height']),
      mysql_real_escape_string($profile['weight']),
      mysql_real_escape_string($profile['position']),
      mysql_real_escape_string($profile['story']),
      mysql_real_escape_string($profile['achievements']),
      mysql_real_escape_string($profile['public']));
      
      $profileId = DBQueryInsert($query);
      $query = sprintf("UPDATE uo_player SET profile_id=%d WHERE player_id=%d",
        $profileId,
        (int)$playerId);
    	
    DBQuery($query);

    }else{
      $query = sprintf("UPDATE uo_player_profile SET accreditation_id='%s', email='%s', firstname='%s', lastname='%s', num='%s',
			nickname='%s', gender='%s', info='%s', national_id='%s', birthdate='%s', birthplace='%s', nationality='%s', throwing_hand='%s', 
			height='%s', weight='%s', position='%s', story='%s', achievements='%s', public='%s' WHERE profile_id='%s'",
      mysql_real_escape_string($profile['accreditation_id']),
      mysql_real_escape_string($profile['email']),
      mysql_real_escape_string($profile['firstname']),
      mysql_real_escape_string($profile['lastname']),
      mysql_real_escape_string($profile['num']),
      mysql_real_escape_string($profile['nickname']),
      mysql_real_escape_string($profile['gender']),
      mysql_real_escape_string($profile['info']),
      mysql_real_escape_string($profile['national_id']),
      mysql_real_escape_string($profile['birthdate']),
      mysql_real_escape_string($profile['birthplace']),
      mysql_real_escape_string($profile['nationality']),
      mysql_real_escape_string($profile['throwing_hand']),
      mysql_real_escape_string($profile['height']),
      mysql_real_escape_string($profile['weight']),
      mysql_real_escape_string($profile['position']),
      mysql_real_escape_string($profile['story']),
      mysql_real_escape_string($profile['achievements']),
      mysql_real_escape_string($profile['public']),
      mysql_real_escape_string($profile['profile_id']));
      
      DBQuery($query);
    }

    LogPlayerProfileUpdate($playerId);
  } else { die('Insufficient rights to edit player profile'); }
}

/**
 * Add image on player profile.
 * 
 * @param int $playerId
 */
function UploadPlayerImage($playerId){
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {
    $max_file_size = 5 * 1024 * 1024; //5 MB
    	
    if($_FILES['picture']['size'] > $max_file_size){
      return "<p class='warning'>"._("File is too large")."</p>";
    }

    $imgType = $_FILES['picture']['type'];
    $type = explode("/", $imgType);
    $type1 = $type[0];
    $type2 = $type[1];
    if ($type1 != "image") {
      return "<p class='warning'>"._("File is not supported image format")."</p>";
    }

    if(!extension_loaded("gd")){
      return "<p class='warning'>"._("Missing gd extensinon for image handiling.")."</p>";
    }

    $file_tmp_name = $_FILES['picture']['tmp_name'];
    $imgname = time().$playerInfo['profile_id'].".jpg";
    $basedir = "".UPLOAD_DIR."players/".$playerInfo['profile_id']."/";
    if(!is_dir($basedir)){
      recur_mkdirs($basedir,0775);
      recur_mkdirs($basedir."thumbs/",0775);
    }

    ConvertToJpeg($file_tmp_name, $basedir.$imgname);
    CreateThumb($basedir.$imgname, $basedir."thumbs/".$imgname, 120, 160);

    //currently removes old image, in future there might be a gallery of images
    RemovePlayerProfileImage($playerId);
    SetPlayerProfileImage($playerId, $imgname);

    return "";
    	
  } else { die('Insufficient rights to upload image'); }
}

/**
 * Set profile image for player.
 * 
 * @param int $playerId
 * @param string $filename
 */
function SetPlayerProfileImage($playerId, $filename) {
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {

    $query = sprintf("UPDATE uo_player_profile SET profile_image='%s' WHERE profile_id='%s'",
    mysql_real_escape_string($filename),
    mysql_real_escape_string($playerInfo['profile_id']));
    	
    DBQuery($query);

  } else { die('Insufficient rights to edit player profile'); }
}

function RemovePlayerProfileImage($playerId) {
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {

    $profile = PlayerProfile($playerInfo['profile_id']);

    if(!empty($profile['profile_image'])){
      	
      //thumbnail
      $file = "".UPLOAD_DIR."players/".$playerInfo['profile_id']."/thumbs/".$profile['profile_image'];
      if(is_file($file)){
        unlink($file);//  remove old images if present
      }

      //image
      $file = "".UPLOAD_DIR."players/".$playerInfo['profile_id']."/".$profile['profile_image'];

      if(is_file($file)){
        unlink($file);//  remove old images if present
      }

      $query = sprintf("UPDATE uo_player_profile SET profile_image=NULL WHERE profile_id='%s'",
      mysql_real_escape_string($playerInfo['profile_id']));
      	
      DBQuery($query);
    }
  } else { die('Insufficient rights to edit palyer profile'); }
}

/**
 * Add url into player profile.
 * 
 * @param int $playerId
 * @param string $type
 * @param string $url
 * @param string $name
 */
function AddPlayerProfileUrl($playerId, $type, $url, $name) {
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {
    $url = SafeUrl($url);
    $query = sprintf("INSERT INTO uo_urls (owner,owner_id,type,name,url)
				VALUES('player',%d,'%s','%s','%s')",
    (int)$playerInfo['profile_id'],
    mysql_real_escape_string($type),
    mysql_real_escape_string($name),
    mysql_real_escape_string($url));
    return DBQuery($query);
  } else { die('Insufficient rights to add url'); }
}

/**
 * Remove URL form plater profile.
 *
 * @param int $playerId
 * @param int $urlId
 */
function RemovePlayerProfileUrl($playerId, $urlId) {
  $playerInfo = PlayerInfo($playerId);
  if (hasEditPlayerProfileRight($playerId)) {
    $query = sprintf("DELETE FROM uo_urls WHERE url_id=%d",
    (int)$urlId);
    return DBQuery($query);
  } else { die('Insufficient rights to remove url'); }
}

/**
 * Returns all event player in CVS format.
 * 
 * @param string $season
 * @param string $separator
 */
function PlayersToCsv($season, $separator){
   
  $query = sprintf("
		SELECT p.firstname AS FirstName, p.lastname AS LastName, p.num AS Jersey, j.name AS TeamName, 
		j.abbreviation AS TeamAbbreviation, club.name AS Club, divi.name AS Division, c.name AS Country,
		pel.games AS Games, 
		COALESCE(s.fedin,0) AS Assists, COALESCE(t.done,0) AS Goals, 
		COALESCE(t1.callahan,0) AS Callahans, (COALESCE(t.done,0) + COALESCE(s.fedin,0)) AS Total
		FROM uo_player AS p 
		LEFT JOIN (SELECT m.scorer AS scorer, COUNT(*) AS done FROM uo_goal AS m 
			LEFT JOIN uo_game_pool AS ps ON (m.game=ps.game)
			LEFT JOIN uo_pool pool ON(ps.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
			LEFT JOIN uo_game AS g1 ON (ps.game=g1.game_id)
			WHERE ser.season='%s' AND ps.timetable=1 AND scorer IS NOT NULL AND g1.isongoing=0 GROUP BY scorer) AS t ON (p.player_id=t.scorer)
		LEFT JOIN (SELECT m1.scorer AS scorer1, COUNT(*) AS callahan FROM uo_goal AS m1 
			LEFT JOIN uo_game_pool AS ps1 ON (m1.game=ps1.game)
			LEFT JOIN uo_pool pool ON(ps1.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
			LEFT JOIN uo_game AS g2 ON (ps1.game=g2.game_id)
			WHERE ser.season='%s' AND ps1.timetable=1 AND m1.scorer IS NOT NULL AND g2.isongoing=0  AND iscallahan=1 GROUP BY m1.scorer) AS t1 ON (p.player_id=t1.scorer1)
		LEFT JOIN  (SELECT m2.assist AS assist, COUNT(*) AS fedin 
			FROM uo_goal AS m2 LEFT JOIN uo_game_pool AS ps2 ON (m2.game=ps2.game) 
			LEFT JOIN uo_game AS g3 ON (ps2.game=g3.game_id)
			LEFT JOIN uo_pool pool ON(ps2.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
			WHERE ser.season='%s' AND ps2.timetable=1 AND g3.isongoing=0 GROUP BY assist) AS s ON (p.player_id=s.assist) 
		LEFT JOIN uo_team AS j ON(p.team=j.team_id) 
		LEFT JOIN uo_series AS divi ON(divi.series_id=j.series)
		LEFT JOIN uo_country AS c ON(c.country_id=j.country)
		LEFT JOIN uo_club AS club ON(club.club_id=j.club)
		LEFT JOIN (SELECT up.player, COUNT(*) AS games 
			FROM uo_played up
			LEFT JOIN uo_game AS g4 ON (up.game=g4.game_id)
			LEFT JOIN uo_pool pool ON(g4.pool=pool.pool_id)
			LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
			WHERE ser.season='%s' AND g4.isongoing=0 
			GROUP BY player) AS pel ON (p.player_id=pel.player)
		WHERE divi.season='%s'
		ORDER BY j.name, p.lastname, p.firstname",
  mysql_real_escape_string($season),
  mysql_real_escape_string($season),
  mysql_real_escape_string($season),
  mysql_real_escape_string($season),
  mysql_real_escape_string($season),
  mysql_real_escape_string($season));

  // Gets the data from the database
  $result = DBQuery($query);
  return ResultsetToCsv($result, $separator);
}
?>
