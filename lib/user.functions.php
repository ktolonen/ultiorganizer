<?php

include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/reservation.functions.php';
include_once $include_prefix.'lib/logging.functions.php';
include_once $include_prefix.'lib/common.functions.php';

//include_once $include_prefix.'lib/configuration.functions.php';

function FailRedirect($user) {
	SetUserSessionData('anonymous');
	header("location:?view=login_failed&user=".urlencode($user));
	exit();
}

function FailRedirectMobile($user) {
	SetUserSessionData('anonymous');
	header("location:?view=mobile/login_failed&user=".urlencode($user));
	exit();
}

function FailUnauthorized($user) {
	header('WWW-Authenticate: Basic realm="ultiorganizer"');
   	if (strpos("Microsoft", $_SERVER["SERVER_SOFTWARE"])) {
   		header("Status: 401 Unauthorized");
   	} else {
   		header("HTTP/1.0 401 Unauthorized");				
   	}
   	echo "<html><head><title>Login failed</title></head><body><h1>Login failed for ".$user."</h1></body></html>\n";
   	exit();
}

function Forbidden($user) {
   	if (strpos("Microsoft", $_SERVER["SERVER_SOFTWARE"])) {
   		header("Status: 403 Forbidden");
   	} else {
   		header("HTTP/1.0 403 Forbidden");				
   	}
   	echo "<html><head><title>Operation not allowed.</title></head><body><h1>Operation not allowed for ".$user."</h1></body></html>\n";
   	exit();
}

function UserAuthenticate($user, $passwd, $failcallback) {
	$query = sprintf("SELECT * FROM uo_users WHERE UserID='%s' AND Password=MD5('%s')",
		mysql_real_escape_string($user),
		mysql_real_escape_string($passwd));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$count=mysql_num_rows($result);
	if($count==1) {
		LogUserAuthentication($user,"success");
		SetUserSessionData($user);
		$row = mysql_fetch_assoc($result);
		DBQuery("UPDATE uo_users SET last_login=NOW() WHERE userid='".mysql_real_escape_string($user)."'");
		
		//first logging
		if(empty($row['last_login']) && $user=="admin"){
    		header("location:?view=admin/serverconf");
    		exit();
		}
		
		if(empty($row['last_login'])){
    		header("location:?view=user/userinfo");
    		exit();
		}
				
	} else {
		LogUserAuthentication($user,"failed");
		if(!empty($failcallback)){
		  $failcallback($user);
		}else{
		  return false;
		}		
	}
}
	
function UserInfo($user_id) {
	if ($user_id == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("SELECT * FROM uo_users WHERE userid='%s'",
			mysql_real_escape_string($user_id));
		return DBQueryToRow($query, true);
	} else { die ('Insufficient rights to get user info'); }
}

function UserIdForMail($mail) {
	$query = sprintf("SELECT userid FROM uo_users WHERE email='%s'",
			mysql_real_escape_string($mail));
		return DBQueryToValue($query);
}

function UserExtraEmails($user_id) {
	if ($user_id == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("SELECT email FROM uo_extraemail WHERE userid='%s'",
			mysql_real_escape_string($user_id));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		$ret = array();
		while ($row = mysql_fetch_row($result)) {
			$ret[] = $row[0];
		}
		if (count($ret) > 0) {
			return $ret;
		} else return false;
	} else { die ('Insufficient rights to get user info'); }
}

function IsRegistered($user_id) {
	if($user_id=="anonymous"){return false;}
	
    $query = sprintf("SELECT userid FROM uo_users WHERE userid='%s'",
		mysql_real_escape_string($user_id));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		return true;
	} else {
		$query = sprintf("SELECT userid FROM uo_registerrequest WHERE userid='%s'",
			mysql_real_escape_string($user_id));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		if ($row = mysql_fetch_assoc($result)) {
			return true;
		}
		return false;
	}
}

function UserUpdateInfo($user_id, $olduser, $user, $name) {
	if ($olduser == $_SESSION['uid'] || hasEditUsersRight()) {

		$query = sprintf("UPDATE uo_users SET UserID='%s', name='%s' WHERE ID=%d",
			mysql_real_escape_string($user),
			mysql_real_escape_string($name),
			(int)$user_id);
	
        DBQuery($query);
	
		if ($olduser != $user) {
			$query = sprintf("UPDATE uo_userproperties SET userid='%s' WHERE userid='%s'",
				mysql_real_escape_string($user),
				mysql_real_escape_string($olduser));
	
			DBQuery($query);
		}
		//update session data only if user is current use
		if($olduser == $_SESSION['uid']){
			SetUserSessionData($user);
		}
		return true;
	} else { die('Insufficient rights to change user info'); }
}

function UserChangePassword($user_id, $passwd) {

	if ($user_id == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("UPDATE uo_users SET password=MD5('%s') WHERE userid='%s'",
			mysql_real_escape_string($passwd),
			mysql_real_escape_string($user_id));
	
		DBQuery($query);
	} else { die('Insufficient rights to change user info'); }
}

function SetUserSessionData($user_id) {
	unset($_SESSION['userproperties']);
	unset($_SESSION['navigation']);
	unset($_SESSION['dbversion']);
	$_SESSION['uid'] = $user_id;

	$query = sprintf("SELECT prop_id, name, value FROM uo_userproperties WHERE userid='%s'",
		mysql_real_escape_string($user_id));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	if (!isset($_SESSION['userproperties'])) {
		$_SESSION['userproperties'] = array();
	}
	
	while ($property = mysql_fetch_assoc($result)) {
		$propname = $property['name'];
		$propvalue = explode(":", $property['value']);
		$propid = $property['prop_id'];
		if (!isset($_SESSION['userproperties'][$propname])) {
			$_SESSION['userproperties'][$propname] = array();		
		}
		if (count($propvalue) == 1) {
			$_SESSION['userproperties'][$propname][$propvalue[0]] = $propid;
		} else {
			if (isset($_SESSION['userproperties'][$propname][$propvalue[0]])) {
				$nextVal = $_SESSION['userproperties'][$propname][$propvalue[0]];
				$nextVal[$propvalue[1]] = $propid;
			} else {
				$nextVal = array($propvalue[1] => $propid);
			}
			$_SESSION['userproperties'][$propname][$propvalue[0]] = $nextVal;
		}
	}
}

function getEditSeasons($userid) {
	$editSeasons = getUserpropertyArray($userid, 'editseason'); 
	return SortEditSeasons($editSeasons);
}
function SortEditSeasons($editSeasons) {
	if (count($editSeasons) == 0) 
		return $editSeasons;
	else {
		$first = true;
		$seasons = "'";
		foreach ($editSeasons as $season => $propId) {
			if ($first) {
				$first = false;
			} else {
				$seasons .= ", '";
			}
			$seasons .= mysql_real_escape_string($season)."'";
		}
		$query = "SELECT season_id FROM uo_season WHERE season_id IN (".$seasons.") ORDER BY starttime ASC";
		$result = DBQuery($query);
		
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		$ret = array();
		while ($row = mysql_fetch_row($result)) {
			$ret[$row[0]] = $editSeasons[$row[0]];
		}
		return $ret;
	}	
}

function getPoolselectors($userid) {
	return getUserpropertyArray($userid, 'poolselector');
}

function getUserroles($userid) {
	return getUserpropertyArray($userid, 'userrole');
}

function getUserLocale($userid) {
	$localearr = getUserpropertyArray($userid, 'locale');
	if (count($localearr) > 0) {
		$tmparr = array_keys($localearr);
		return $tmparr[0];
	} else {
		return GetDefaultLocale();
	}
}

function SetUserLocale($userid, $locale) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
		global $locales;
		if (isset($locales[$locale])) {
			$localearr = getUserpropertyArray($userid, 'locale');
			if (count($localearr) > 0) {
				$query = sprintf("UPDATE uo_userproperties SET value='%s' WHERE userid='%s' AND name='locale'",
					mysql_real_escape_string($locale),
					mysql_real_escape_string($userid));
			} else {
				$query = sprintf("INSERT INTO uo_userproperties (name, value, userid) VALUES ('locale', '%s', '%s')",
					mysql_real_escape_string($locale),
					mysql_real_escape_string($userid));
			}			
			$result = DBQuery($query);
			
			if (!$result) { die('Invalid query: ' . mysql_error()); }
		} else {
			die('Invalid locale: '. $locale);
		}
	} else { die('Insufficient rights to set user locale'); }
}

function getPropId($userid, $name, $value) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("SELECT prop_id FROM uo_userproperties WHERE userid='%s' and name='%s'
							and value='%s'",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($name),
			mysql_real_escape_string($value));
		$result = DBQuery($query);
		
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		$row = mysql_fetch_row($result);
		return $row[0];
		
	} else { die('Insufficient rights to get user info'); }
}

function getUserpropertyArray($userid, $propertyname) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("SELECT prop_id, value FROM uo_userproperties WHERE userid='%s' and name='%s'",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($propertyname));
		$result = DBQuery($query);
		
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		$ret = array();
		while ($property = mysql_fetch_assoc($result)) {
			$propvalue = explode(":", $property['value']);
			$propid = $property['prop_id'];
			if (count($propvalue) == 1) {
				$ret[$propvalue[0]] = $propid;
			} else {
				if (isset($ret[$propvalue[0]])) {
					$nextVal = $ret[$propvalue[0]];
					$nextVal[$propvalue[1]] = $propid;
				} else {
					$nextVal = array($propvalue[1] => $propid);
				}
				$ret[$propvalue[0]] = $nextVal;
			}
		}
		return $ret;
	} else { die('Insufficient rights to get user info'); }
}


function setSelectedSeason() {
	//season selection changed
	if (!empty($_GET["selseason"])){
		$_SESSION['userproperties']['selseason'] = $_GET["selseason"];
	}
}

function getViewPools($selSeasonId) {
	$numselectors = 0;
	$query = "SELECT seas.season_id as season, seas.name as season_name, ser.series_id as series, ser.name as series_name, pool.pool_id as pool, pool.name as pool_name ";
	$query .= "FROM uo_pool pool
		left outer join uo_series ser on (pool.series = ser.series_id)
		left outer join uo_season seas on (ser.season = seas.season_id) ";
	$query .= "WHERE pool.visible=1";
	if (isset($_SESSION['userproperties']['poolselector'])) {
		foreach ($_SESSION['userproperties']['poolselector'] as $selector => $param) {
			if ($numselectors == 0) {
			    $query .= " AND (";
			}
			if ($numselectors > 0) {
				$query .= "OR ";
			}
			if ($selector == 'currentseason') {
				$query .= sprintf("seas.season_id='%s' ", mysql_real_escape_string($selSeasonId));
			} elseif ($selector == 'team') {
				$query .= sprintf("pool.pool_id in (SELECT pool FROM uo_team WHERE team_id=%d) ", (int)key($param));
				$query .= sprintf("OR pool.pool_id in (SELECT pool FROM uo_team_pool WHERE team=%d) ", (int)key($param));
			} elseif ($selector == 'season') {
				$query .= sprintf("seas.season_id='%s' ", mysql_real_escape_string(key($param)));
			} elseif ($selector == 'series') {
				$query .= sprintf("ser.series_id=%d ", (int)key($param));
			} elseif ($selector == 'pool') {
				$query .= sprintf("pool.pool_id=%d ", (int)key($param));
			}
			$numselectors++;
		}
	}


	if ($numselectors > 0) {
		$query .= ")";
	}
	$query .= " ORDER BY seas.endtime > NOW() DESC, seas.starttime DESC, ser.season ASC, ser.ordering ASC, pool.ordering ASC";
	
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }

	return $result;
}


function ClearUserSessionData() {
	if (IsFacebookEnabled()) {
		global $serverConf;
		setcookie ('fbs_' . $serverConf['FacebookAppId'], "", 1, "/");
		unset($_COOKIE['fbs_' . $serverConf['FacebookAppId']]); 
	}
	SetUserSessionData("anonymous");
}

function setSuperAdmin($userid, $value) {
	if (hasEditUsersRight()) {
		if ($value && !isSuperAdminByUserid($userid)) {
			$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'superadmin')",
				mysql_real_escape_string($userid));
			$result = DBQuery($query);
			Log1("security","add",$userid,"","superadmin acceess granted");	
			if (!$result) { die('Invalid query: ' . mysql_error()); }
		} else if (!$value) {
			$query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='superadmin'",
				mysql_real_escape_string($userid));
			$result = DBQuery($query);
			Log1("security","add",$userid,"","superadmin acceess removed");
			if (!$result) { die('Invalid query: ' . mysql_error()); }
		}
	} else { die('Insufficient rights to change superadmin userrole'); }
}

function setTranslationAdmin($userid, $value) {
	if (hasEditUsersRight()) {
		if ($value && !isTranslationAdminByUserid($userid)) {
			$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'translationadmin')",
				mysql_real_escape_string($userid));
			$result = DBQuery($query);
			Log1("security","add",$userid,"","translationadmin acceess granted");	
			if (!$result) { die('Invalid query: ' . mysql_error()); }
		} else if (!$value) {
			$query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='translationadmin'",
				mysql_real_escape_string($userid));
			$result = DBQuery($query);
			Log1("security","add",$userid,"","translationadmin acceess removed");
			if (!$result) { die('Invalid query: ' . mysql_error()); }
		}
	} else { die('Insufficient rights to change superadmin userrole'); }
}

function isSuperAdminByUserid($userid) {
	if (hasEditUsersRight()) {
		$query = sprintf("SELECT * FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='superadmin'",
			mysql_real_escape_string($userid));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	
		if ($row = mysql_fetch_assoc($result)) {
			return true;
		} else {
			return false;
		}
	}
}

function isTranslationAdminByUserid($userid) {
	if (hasEditUsersRight()) {
		$query = sprintf("SELECT * FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='translationadmin'",
			mysql_real_escape_string($userid));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	
		if ($row = mysql_fetch_assoc($result)) {
			return true;
		} else {
			return false;
		}
	}
}

function isSuperAdmin() {
	return isset($_SESSION['userproperties']['userrole']['superadmin']);
}

function isTranslationAdmin() {
	return isset($_SESSION['userproperties']['userrole']['translationadmin']);
}

function isPlayerAdmin($profile_id) {
	return isset($_SESSION['userproperties']['userrole']['playeradmin'][$profile_id]);
}

function hasPlayerAdminRights() {
	return isset($_SESSION['userproperties']['userrole']['playeradmin']);
}

function isSeasonAdmin($season) {
	return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]);
}

function hasScheduleRights() {
	return isset($_SESSION['userproperties']['userrole']['resadmin']);
}

function hasViewUsersRight() {
	return isset($_SESSION['userproperties']['userrole']['superadmin']);
}
function hasEditUsersRight() {
	return isset($_SESSION['userproperties']['userrole']['superadmin']);
}

function hasChangeCurrentSeasonRight() {
	return isset($_SESSION['userproperties']['userrole']['superadmin']);
}

function hasCurrentSeasonsEditRight() {
	$seasons = EnrollSeasons();
	$seasons[] = CurrentSeason();
	$ret = false;
	foreach ($seasons as $season) {
		$ret = $ret || isSeasonAdmin($season);
		if ($ret) return true;
	}
	return false;
}

function hasEditSeasonSeriesRight($season) {
	return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]);
}

function hasEditPlacesRight($season) {
	return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]);
}

function hasEditTeamsRight($series) {
	$season = SeriesSeasonId($series);
	return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
	isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]);
}

function  hasEditGamesRight($series) {
	$season = SeriesSeasonId($series);
	return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
	isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]);
}

function hasEditPlayerProfileRight($playerId) {
	$playerInfo = PlayerInfo($playerId);
	$team = $playerInfo['team'];
	$series = getTeamSeries($team);
	$season = SeriesSeasonId($series);
	return isPlayerAdmin($playerInfo['profile_id']) || 
	isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
	isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]) ||
	isset($_SESSION['userproperties']['userrole']['teamadmin'][$team]);
}

function hasEditPlayersRight($team) {
	$series = getTeamSeries($team);
	$season = SeriesSeasonId($series);
	return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
	isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]) ||
	isset($_SESSION['userproperties']['userrole']['teamadmin'][$team]);
}

function hasEditGamePlayersRight($game) {
	$team = GameRespTeam($game);
	$series = GameSeries($game);
	$season = SeriesSeasonId($series);
	$reservation = GameReservation($game);
	return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
	isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]) ||
	isset($_SESSION['userproperties']['userrole']['teamadmin'][$team]) ||
	isset($_SESSION['userproperties']['userrole']['resgameadmin'][$reservation]) ||
	isset($_SESSION['userproperties']['userrole']['gameadmin'][$game]);
}

function hasEditGameEventsRight($game) {
	$team = GameRespTeam($game);
	$series = GameSeries($game);
	$season = SeriesSeasonId($series);
	$reservation = GameReservation($game);
	return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
	isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]) ||
	isset($_SESSION['userproperties']['userrole']['teamadmin'][$team]) ||
	isset($_SESSION['userproperties']['userrole']['resgameadmin'][$reservation]) ||
	isset($_SESSION['userproperties']['userrole']['gameadmin'][$game]);
}
function hasAccredidationRight($team) {
	return hasEditTeamsRight(getTeamSeries($team)) || 
	isset($_SESSION['userproperties']['userrole']['accradmin'][$team]);
}

function hasTranslationRight() {
	return isSuperAdmin() ||
	isset($_SESSION['userproperties']['userrole']['translationadmin']);
}

function hasAddMediaRight() {
	return isset($_SESSION['uid']) && ($_SESSION['uid'] != 'anonymous');
}
/*
function getSeriesSeason($series) {
	$query = sprintf("SELECT ser.season FROM uo_series ser 
	LEFT JOIN uo_series ser ON (pool.series=ser.series_id) WHERE ser.series_id=%d", (int)$series);
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_row($result)) {
		return $row[0];
	} else return "";
}
*/
function UserListRightsHtml($userId) {
	$query = sprintf("SELECT value FROM uo_userproperties WHERE userid='%s'", mysql_real_escape_string($userId));
	$result = DBQuery($query);
	$rights = "";
	while ($row = mysql_fetch_row($result)) {
	  $value = preg_split('/:/',$row[0]);
	  switch($value[0]){
	    case "superadmin":
	      $rights .= "<span style='color:#ff0000; font-weight:bold'>".$value[0]."</span><br/>";    
	      break;
	    case "seasonadmin":
	      $rights .= "<span style='color:#ff00ff;'>".$value[0].": ";
	      $rights .= utf8entities(SeasonName($value[1]));
	      $rights .= "</span><br/>";
	      break;
	    case "teamadmin":
	      $rights .= "<span'>".$value[0].": ";
	      $rights .= utf8entities(TeamName($value[1]));
	      $rights .= "</span><br/>";    
	      break;	      
	  }
	    
	}
	
	return $rights;
}


function getSeriesName($series) {
	$query = sprintf("SELECT name FROM uo_series WHERE series_id=%d", (int)$series);
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		return $row['name'];
	} else return "";
}

function getTeamSeries($team) {
	$query = sprintf("SELECT series FROM uo_team WHERE team_id=%d", (int)$team);
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		return $row['series'];
	} else return "";
}

function getTeamSeason($team) {
	$query = sprintf("SELECT ser.season as season FROM uo_team as team left join uo_series as ser on (team.series = ser.series_id)  WHERE team_id=%d", (int)$team);
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		return $row['season'];
	} else return "";
}

function getTeamName($team) {
	$query = sprintf("SELECT name FROM uo_team WHERE team_id=%d", (int)$team);
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		return $row['name'];
	} else return "";
}

function RemovePoolSelector($userid, $propid) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("DELETE FROM uo_userproperties WHERE prop_id=%d AND userid='%s' AND name='poolselector'",
			(int)$propid,
			mysql_real_escape_string($userid));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		Log1("security","delete",$userid,$propid,"poolselector");
		if ($userid == $_SESSION['uid']) { SetUserSessionData($userid); }
		return true;
	} else { die('Insufficient rights to change user info'); }
}

function RemoveExtraEmail($userid, $extraEmail) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("DELETE FROM uo_extraemail WHERE userid='%s' AND email='%s'",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($extraEmail));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		Log1("security","delete",$userid,$extraEmail,"extraemail");
		return true;
	} else { die('Insufficient rights to change user info'); }
}

function ToPrimaryEmail($userid, $extraEmail) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("SELECT * FROM uo_extraemail WHERE userid='%s' AND email='%s'",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($extraEmail));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		if ($row = mysql_fetch_row($result)) {
			$userInfo = UserInfo($userid);
			$oldPrimary = $userInfo['email'];
			if ($oldPrimary != $extraEmail) {
				$query = sprintf("UPDATE uo_extraemail SET email='%s' WHERE userid='%s' and email='%s'",
					mysql_real_escape_string($oldPrimary),
					mysql_real_escape_string($userid),
					mysql_real_escape_string($extraEmail)); 
				$result = DBQuery($query);
				if (!$result) { die('Invalid query: ' . mysql_error()); }
				$query = sprintf("UPDATE uo_users SET email='%s' WHERE userid='%s'",
					mysql_real_escape_string($extraEmail),
					mysql_real_escape_string($userid)); 
				$result = DBQuery($query);
				if (!$result) { die('Invalid query: ' . mysql_error()); }
			}
		} 
	} else { die('Insufficient rights to change user info'); }
}

function AddPoolSelector($userid, $selector) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'poolselector', '%s')",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($selector));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		Log1("security","add",$userid,$selector,"poolselector");
		if ($userid == $_SESSION['uid']) { SetUserSessionData($userid); }
		return true;
	} else { die('Insufficient rights to change user info'); } 
}

function RemoveEditSeason($userid, $propid) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
		$query = sprintf("DELETE FROM uo_userproperties WHERE prop_id=%d AND userid='%s' AND name='editseason'",
			(int)$propid,
			mysql_real_escape_string($userid));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		Log1("security","delete",$userid,$propid,"editseason");
		if ($userid == $_SESSION['uid']) { SetUserSessionData($userid); }
		return true;
	} else { die('Insufficient rights to change user info'); }
}


function AddEditSeason($userid, $season) {
	if ($userid == $_SESSION['uid'] || hasEditUsersRight() || isSeasonAdmin($season)) {
		$query = sprintf("SELECT COUNT(*) FROM uo_userproperties 
			WHERE userid='%s' AND name='editseason' AND value='%s'",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($season));
		$exist = DBQueryToValue($query);

		if($exist==0){
			$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'editseason', '%s')",
				mysql_real_escape_string($userid),
				mysql_real_escape_string($season));
			$result = DBQuery($query);
			if (!$result) { die('Invalid query: ' . mysql_error()); }
			Log1("security","add",$userid,$season,"editseason");
		}
		if ($userid == $_SESSION['uid']) { SetUserSessionData($userid); }
		return true;
	} else { die('Insufficient rights to change user info'); } 
}

function RemoveUserRole($userid, $propid) {
	if (hasEditUsersRight() || $_SESSION['uid'] == $userid) {
		$query = sprintf("DELETE FROM uo_userproperties WHERE prop_id=%d AND userid='%s' AND name='userrole'",
			(int)$propid,
			mysql_real_escape_string($userid));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		Log1("security","delete",$userid,$propid,"userrole");
		if ($userid == $_SESSION['uid']) { SetUserSessionData($userid); }
		return true;
	} else { die('Insufficient rights to change user info'); }
}

function AddUserRole($userid, $role) {
	if (hasEditUsersRight()) {
		$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', '%s')",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($role));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		Log1("security","add",$userid,$role,"userrole");
		if ($userid == $_SESSION['uid']) { SetUserSessionData($userid); }
		return true;
	} else { die('Insufficient rights to change user info'); } 
}

function AddSeasonUserRole($userid, $role, $seasonId) {
	if (hasEditUsersRight() || isSeasonAdmin($seasonId)) {
	    
	  $query = sprintf("SELECT COUNT(*) FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='%s'",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($role));
		$result = DBQueryToValue($query);
		
		if($result<=0){
    	    $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', '%s')",
    			mysql_real_escape_string($userid),
    			mysql_real_escape_string($role));
    		$result = DBQuery($query);
    		Log1("security","add",$userid,$seasonId,$role);
    		AddEditSeason($userid, $seasonId);
    		 
    		if ($userid == $_SESSION['uid']) { SetUserSessionData($userid); }
    		return true;
		}else{
		  return false;
		}
	} else { die('Insufficient rights to change user info'); } 
}

function RemoveSeasonUserRole($userid, $role, $seasonId) {
  if (hasEditUsersRight() || isSeasonAdmin($seasonId)) {
    $query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='%s'",
		mysql_real_escape_string($userid),
		mysql_real_escape_string($role));
	$result = DBQuery($query);
  }else{ die('Insufficient rights to change user info'); } 
}

function GetTeamAdmins($teamId) {
	$seasonrights = getEditSeasons($_SESSION['uid']);
	$season = TeamSeason($teamId);

	if (isSuperAdmin() || isset($seasonrights[$season])) {
		$query = sprintf("SELECT pu.userid, pu.name, pu.email FROM uo_userproperties pup
				LEFT JOIN uo_users pu ON(pup.userid=pu.userid)
				WHERE pup.value='%s' ORDER BY pu.name ASC",
				mysql_real_escape_string('teamadmin:'.$teamId));
		return DBQueryToArray($query);
	} else { die('Insufficient rights to access user info'); } 
}

function DeleteUser($userid) {
	if ($userid != "anonymous") {
		if (hasEditUsersRight()) {
			$query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s'",
				mysql_real_escape_string($userid));
			$result = DBQuery($query);
			if (!$result) { die('Invalid query: ' . mysql_error()); }
			$query = sprintf("DELETE FROM uo_users WHERE userid='%s'",
				mysql_real_escape_string($userid));
			$result = DBQuery($query);
			if (!$result) { die('Invalid query: ' . mysql_error()); }
			Log1("security","delete",$userid,"","user");
		} else { die('Insufficient rights to delete user'); }
	} else { die('Can not delete anonymous user'); }
}

function DeleteRegisterRequest($userid) {
	if ($userid != "anonymous") {
		if (hasEditUsersRight()) {
			Log1("security","delete",$userid,"","RegisterRequest");
			$query = sprintf("DELETE FROM uo_registerrequest WHERE userid='%s'",
				mysql_real_escape_string($userid));
			$result = DBQuery($query);
			if (!$result) { die('Invalid query: ' . mysql_error()); }
		} else { die('Insufficient rights to delete user'); }
	} else { die('Can not delete anonymous user'); }
}

function AddRegisterRequest($newUsername, $newPassword, $newName, $newEmail, $message='register.txt') {
	Log1("user","add",$newUsername,"","register request");
	$token = uuidSecure();
	$query = sprintf("INSERT INTO uo_registerrequest (userid, password, name, email, token) VALUES ('%s', MD5('%s'), '%s', '%s', '%s')",
					mysql_real_escape_string($newUsername),
					mysql_real_escape_string($newPassword),
					mysql_real_escape_string($newName),
					mysql_real_escape_string($newEmail),
					mysql_real_escape_string($token));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$message = file_get_contents('locale/'.GetSessionLocale().'/LC_MESSAGES/'.$message);
	
	// for IIS
	if(!isset($_SERVER['REQUEST_URI'])) {
		$url = "http://".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME']."?view=register&token=".$token;
	} else	{
		$url = "http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."&token=".$token;
	}
	
	$message = str_replace(array('$url', '$ultiorganizer'), array($url, _("Ultiorganizer")), $message);
	$headers  = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";
	
	global $serverConf;
	$headers .= "From: ".$serverConf['EmailSource']."\r\n";
	
	if (!mail($newEmail, _("Confirm your account to ultiorganizer"), $message, $headers)) {
		$query = sprintf("DELETE FROM uo_registerrequest WHERE userid='%s'",
					mysql_real_escape_string($newUsername));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		return false;
	} else {
		return true;
	}
}


function emailUsed($email) {
	$query = sprintf("select email from uo_users where LOWER(email)='%s' 
		union all select email from uo_extraemail where LOWER(email)='%s' 
		union all select email from uo_extraemailrequest where LOWER(email)='%s'",
			mysql_real_escape_string(strtolower($email)),
			mysql_real_escape_string(strtolower($email)),
			mysql_real_escape_string(strtolower($email)));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_row($result)) {
		return true;
	} else {
		return false;
	}
}

function AddExtraEmailRequest($userid, $extraEmail, $message='verify_email.txt') {
	Log1("user","add",$userid,"","extra email request");
	$token = uuidSecure();
	$query = sprintf("INSERT INTO uo_extraemailrequest (userid, email, token) VALUES ('%s', '%s', '%s')",
					mysql_real_escape_string($userid),
					mysql_real_escape_string($extraEmail),
					mysql_real_escape_string($token));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$message = file_get_contents('locale/'.GetSessionLocale().'/LC_MESSAGES/'.$message);
	
	// for IIS
	if(!isset($_SERVER['REQUEST_URI'])) {
		$url = "http://".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME']."?view=user/addextraemail&token=".$token;
	} else	{
		$url = "http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."&token=".$token;
	}
	
	$message = str_replace(array('$url', '$ultiorganizer'), array($url, _("Ultiorganizer")), $message);
	$headers  = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";
	
	global $serverConf;
	$headers .= "From: ".$serverConf['EmailSource']."\r\n";
	
	if (!mail($extraEmail, _("Confirm extra email address for ultiorganizer"), $message, $headers)) {
		$query = sprintf("DELETE FROM uo_extraemailrequest WHERE token='%s'",
					mysql_real_escape_string($token));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		return false;
	} else {
		return true;
	}
}

function RegisterUIDByToken($token) {
	$query = sprintf("SELECT userid FROM uo_registerrequest WHERE token='%s'",
		mysql_real_escape_string($token));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		return $row['userid'];
	} return false; 
}

function ConfirmRegister($token) {
	$query = sprintf("SELECT userid, password, name, email FROM uo_registerrequest WHERE token='%s'",
		mysql_real_escape_string($token));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		$query = sprintf("INSERT INTO uo_users (name, userid, password, email) VALUES ('%s', '%s', '%s', '%s')",
			mysql_real_escape_string($row['name']),
			mysql_real_escape_string($row['userid']),
			mysql_real_escape_string($row['password']),
			mysql_real_escape_string($row['email']));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		$query = sprintf("DELETE FROM uo_registerrequest WHERE token='%s'",
			mysql_real_escape_string($token));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		FinalizeNewUser($row['userid'], $row['email']);
		Log1("user","add",$row['userid'],"","confirm register request");
		return true;
	} else return false;
}


function ConfirmRegisterUID($userid) {
    if(isSuperAdmin()){
    	$query = sprintf("SELECT userid, password, name, email FROM uo_registerrequest WHERE userid='%s'",
    		mysql_real_escape_string($userid));
    	$result = DBQuery($query);
    	if (!$result) { die('Invalid query: ' . mysql_error()); }
    	if ($row = mysql_fetch_assoc($result)) {
    		$query = sprintf("INSERT INTO uo_users (name, userid, password, email) VALUES ('%s', '%s', '%s', '%s')",
    			mysql_real_escape_string($row['name']),
    			mysql_real_escape_string($row['userid']),
    			mysql_real_escape_string($row['password']),
    			mysql_real_escape_string($row['email']));
    		$result = DBQuery($query);
    		if (!$result) { die('Invalid query: ' . mysql_error()); }
    		$query = sprintf("DELETE FROM uo_registerrequest WHERE userid='%s'",
    			mysql_real_escape_string($userid));
    		$result = DBQuery($query);
    		if (!$result) { die('Invalid query: ' . mysql_error()); }
    		FinalizeNewUser($row['userid'], $row['email']);
    		Log1("user","add",$row['userid'],"","added by administrator");
    		return true;
    	} else return false;
    }else{
    die("Insufficient user rights."); 
    }
}

function FinalizeNewUser($userid, $email) {
	$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'poolselector', 'currentseason')",
			mysql_real_escape_string($userid));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$query = sprintf("SELECT DISTINCT profile_id FROM uo_player_profile WHERE LOWER(email)='%s'",
		mysql_real_escape_string(strtolower($email)));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	while ($accreditation = mysql_fetch_row($result)) {
		$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'playeradmin:%s')",
			mysql_real_escape_string($userid),
			mysql_real_escape_string($accreditation[0]) );
		$result1 = DBQuery($query);
		if (!$result1) { die('Invalid query: ' . mysql_error()); }
	}
}

function ConfirmEmail($token) {
	$query = sprintf("SELECT userid, email FROM uo_extraemailrequest WHERE token='%s'",
		mysql_real_escape_string($token));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if ($row = mysql_fetch_assoc($result)) {
		$query = sprintf("INSERT INTO uo_extraemail (userid, email) VALUES ('%s', '%s')",
			mysql_real_escape_string($row['userid']),
			mysql_real_escape_string($row['email']));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		$query = sprintf("DELETE FROM uo_extraemailrequest WHERE token='%s'",
			mysql_real_escape_string($token));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		$query = sprintf("SELECT DISTINCT profile_id FROM uo_player_profile WHERE LOWER(email)='%s'",
			mysql_real_escape_string(strtolower($row['email'])));
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		while ($accreditation = mysql_fetch_row($result)) {
			$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'playeradmin:%s')",
				mysql_real_escape_string($row['userid']),
				mysql_real_escape_string($accreditation[0]) );
			$result1 = DBQuery($query);
			if (!$result1) { die('Invalid query: ' . mysql_error()); }
		}
		
		
		Log1("user","add",$row['userid'],"","confirm extra email");
		return true;
	} else return false;
}

function uuidSecure() {

	$pr_bits = null;
	$fp = @fopen('/dev/urandom','rb');
	if ($fp !== false) {
		$pr_bits .= @fread($fp, 16);
		@fclose($fp);
	} else {
		// If /dev/urandom isn't available (eg: in non-unix systems), use mt_rand().
		$pr_bits = "";
		for($cnt=0; $cnt < 16; $cnt++){
			$pr_bits .= chr(mt_rand(0, 255));
		}
	}

	$time_low = bin2hex(substr($pr_bits,0, 4));
	$time_mid = bin2hex(substr($pr_bits,4, 2));
	$time_hi_and_version = bin2hex(substr($pr_bits,6, 2));
	$clock_seq_hi_and_reserved = bin2hex(substr($pr_bits,8, 2));
	$node = bin2hex(substr($pr_bits,10, 6));

	/**
	 * Set the four most significant bits (bits 12 through 15) of the
	 * time_hi_and_version field to the 4-bit version number from
	 * Section 4.1.3.
	 * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
	 */
	$time_hi_and_version = hexdec($time_hi_and_version);
	$time_hi_and_version = $time_hi_and_version >> 4;
	$time_hi_and_version = $time_hi_and_version | 0x4000;

	/**
	 * Set the two most significant bits (bits 6 and 7) of the
	 * clock_seq_hi_and_reserved to zero and one, respectively.
	 */
	$clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
	$clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
	$clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;

	return sprintf('%08s-%04s-%04x-%04x-%012s',
	$time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved, $node);
}

function TeamResponsibilities($userid, $season) {
	$teams = SeasonTeams($season);
	$seasonTeamAdmin = array();
	foreach ($teams as $team) {
		if (isset($_SESSION['userproperties']['userrole']['teamadmin'][$team['team_id']])) {
			$seasonTeamAdmin[] = $team['team_id'];
		}
	}
	return $seasonTeamAdmin;
}

function GameResponsibilities($season) {
	$query = sprintf("SELECT DISTINCT game_id FROM uo_game WHERE ");
	$criteria = "";
	if (isSeasonAdmin($season)) {
		$criteria = sprintf(" pool IN 
		(SELECT pool_id FROM uo_pool WHERE series IN 
			(SELECT series_id FROM uo_series WHERE season='%s'))",
			mysql_real_escape_string($season));
	} else {
		// SeriesAdmin
		$seriesResult = SeasonSeries($season);
		$seasonSeriesAdmin = array();
		foreach($seriesResult as $row) {
			if (isset($_SESSION['userproperties']['userrole']['seriesadmin'][$row['series_id']])) {
				 $seasonSeriesAdmin[] = $row['series_id'];
			}	
		}
		if (count($seasonSeriesAdmin) > 0) {
			$criteria = "(pool IN (SELECT pool_id FROM uo_pool WHERE series IN (".implode(",", $seasonSeriesAdmin).")))";
		}
		
		// TeamAdmin
		$teams = SeasonTeams($season);
		$seasonTeamAdmin = array();
		foreach ($teams as $team) {
			if (isset($_SESSION['userproperties']['userrole']['teamadmin'][$team['team_id']])) {
				$seasonTeamAdmin[] = $team['team_id'];
			}
		}
		if (count($seasonTeamAdmin) > 0) {
			if (strlen($criteria) > 0) {
				$criteria .= " OR ";
			}
			$criteria .= "(respteam IN (".implode(",", $seasonTeamAdmin)."))";
		}
		if (isset($_SESSION['userproperties']['userrole']['gameadmin'])) {
			// GameAdmin
			$respGames = $_SESSION['userproperties']['userrole']['gameadmin'];
			$seasonGames = array();
			foreach ($respGames as $gameId => $propId) {
				if (GameSeason($gameId) == $season) {
					$seasonGames[] = $gameId;
				}
			}
			if (count($seasonGames) > 0) {
				if (strlen($criteria) > 0) {
					$criteria .= " OR ";
				}
				$criteria .= "(game_id IN (".implode(",", $seasonGames)."))";
			}
		}
		if (isset($_SESSION['userproperties']['userrole']['resgameadmin'])) {
			// ResGameAdmin
			$respResvs = $_SESSION['userproperties']['userrole']['resgameadmin'];
			$seasonResvs = array();
			foreach($respResvs as $resId => $propId) {
				foreach (ReservationSeasons($resId) as $resSeason) {
					if ($resSeason == $season) {
						$seasonResvs[] = $resId;
						break;
					}
				}
			}
			if (count($seasonResvs) > 0) {
			if (strlen($criteria) > 0) {
					$criteria .= " OR ";
				}
				$criteria .= "(reservation IN (".implode(",", $seasonResvs)."))";	
			}
		}
	}
	if (strlen($criteria) == 0) {
		return array();
	} else {
		$ret = array();
		$query .= $criteria;
		$result = DBQuery($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		while ($row = mysql_fetch_row($result)) {
			$ret[] = $row[0];
		}
		return $ret;
	}
}

function GameResponsibilityArray($season, $series=null) {
	$gameResponsibilities = GameResponsibilities($season);
	if(!$gameResponsibilities) {
		return array();
	}
	$query = sprintf("SELECT game_id, hometeam, kj.name as hometeamname, visitorteam,
			vj.name as visitorteamname, pp.pool as pool, time, homescore, visitorscore,
			pool.timecap, pool.timeslot, pool.series, res.reservationgroup,
			ser.name, pool.name as poolname, res.id as res_id, res.starttime,
			loc.name AS locationname, res.fieldname AS fieldname, res.location,
			COALESCE(m.goals,0) AS goals, phome.name AS phometeamname, pvisitor.name AS pvisitorteamname,
	        pp.isongoing, pp.hasstarted
		FROM uo_game pp left join uo_reservation res on (pp.reservation=res.id) 
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_series ser on (pool.series=ser.series_id)
			left join uo_location loc on (res.location=loc.id)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
			left join (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS m ON (pp.game_id=m.game)
		WHERE game_id IN (".implode(",",$gameResponsibilities).")" 
		    . ($series?" AND pool.series=%d":"") . "
		ORDER BY res.starttime ASC, res.reservationgroup ASC, res.fieldname+0,pp.time ASC",
	    $series?(int)$series:0);
	
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$ret = array();
	while ($row = mysql_fetch_assoc($result)) {
		if (!isset($ret[$row['reservationgroup']])) {
			$ret[$row['reservationgroup']] = array();
		}
		if (!isset($ret[$row['reservationgroup']][$row['res_id']])) {
			$ret[$row['reservationgroup']][$row['res_id']] = array();
		}
		$gamesArray = $ret[$row['reservationgroup']][$row['res_id']];
		$gamesArray['starttime'] = $row['starttime'];
		$gamesArray['locationname'] = utf8entities($row['locationname']) . " " . _("Field") . " " . utf8entities($row['fieldname']);
		$gamesArray[$row['game_id']] = $row;
		$ret[$row['reservationgroup']][$row['res_id']] = $gamesArray;
	}
	return  $ret;	
}

function UserResetPassword($userId) {
	Log1("user","change",$userId,"","reset password");
	
	$query = sprintf("SELECT email FROM uo_users WHERE userid='%s'",
			mysql_real_escape_string($userId));
	$result = DBQuery($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$row = mysql_fetch_assoc($result);
	
	$email = $row['email'];
	if(!empty($email)){
		$password = CreateRandomPassword();
				
		$url = GetURLBase();
		$locale = getSessionLocale();
		$message = file_get_contents('locale/'.$locale.'/LC_MESSAGES/pwd_reset.txt');
		$message = str_replace('$url', $url, $message);
		$message = str_replace('$username', $userId, $message);
		$message = str_replace('$password', $password, $message);
		
		$headers  = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";
		
		global $serverConf;
		$headers .= "From: ".$serverConf['EmailSource']."\r\n";
			
		if (mail($email, _("New password to ultiorganizer"), $message, $headers)) {
			$query = sprintf("UPDATE uo_users SET password=MD5('%s') WHERE userid='%s'",
						mysql_real_escape_string($password),
						mysql_real_escape_string($userId));
			$result = DBQuery($query);
			if (!$result) { die('Invalid query: ' . mysql_error()); }
			return true;
		} else {
			return false;
		}
	}else{
		return false;
	}
}

function CreateRandomPassword() {
    
	$chars = "abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ23456789";
    $password = '' ;
    for($i=0;$i<8;$i++) {
        $password .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
    }
    return $password;
}

function CreateNewUsername($firstname, $lastname, $email) {
	$firstname = strtolower($firstname);
	$lastname = strtolower($lastname);
	$emailSplitted = explode("@", strtolower($email));
	$emailStart= $emailSplitted[0];
	$try = substr($firstname, 0, 1).$lastname;
	if (!isRegistered($try)) return $try;
	if (!isRegistered($emailStart)) return $emailStart;
	if (!isRegistered($firstname.".".$lastname)) return $firstname.".".$lastname;
	$extra = 0;
	while (true) {
		$extra++;
		if (!isRegistered($try.$extra)) return $try.$extra;
		if (!isRegistered($emailStart.$extra)) return $emailStart.$extra;
		if (!isRegistered($firstname.".".$lastname.$extra)) return $firstname.".".$lastname.$extra;
	}
}

function UserCreateRandomPassword() {

    $chars = "abcdefghijkmnopqrstuvwxyz023456789";
    srand((double)microtime()*1000000);
    $i = 0;
    $pass = '' ;
    while ($i <= 7) {
        $num = rand() % 33;
        $tmp = substr($chars, $num, 1);
        $pass = $pass . $tmp;
        $i++;
    }
    return $pass;
}

?>
