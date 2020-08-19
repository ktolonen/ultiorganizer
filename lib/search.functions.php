<?php

include_once $include_prefix.'lib/season.functions.php';

function SearchSeason($resultTarget, $hiddenProperties, $submitbuttons) {
	$ret = "<form method='post' action='?".$resultTarget."'>\n";
	$ret .= "<table><tr><td>"._("Event").":</td><td>";
	$ret .= SeasonControl();
	$ret .= "</td></tr>\n";
	$ret .= "</table>\n";
	$ret .= "<p>";
	foreach ($hiddenProperties as $name => $value) {
		$ret .= "<input type='hidden' name='".urlencode($name)."' value='".urlencode($value)."'/>\n";
	}
	foreach ($submitbuttons as $name => $value) {
		$ret .= "<input type='submit' name='".$name."' value='".utf8entities($value)."'/>\n";
	}
	$ret .= "</p>";
	$ret .= "</form>";
	return $ret;
}

function SearchSeries($resultTarget, $hiddenProperties, $submitbuttons) {
	$querystring = $_SERVER['QUERY_STRING'];
	$ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
	$ret .= "<table><tr><td>"._("Event").":</td><td>";
	$ret .= SeasonControl();
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Division")."</td><td>";
	$ret .= "<input type='text' name='seriesname' value='";
	if (isset($_POST['seriesname'])) $ret .= $_POST['seriesname'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= "<input type='submit' name='searchser' value='"._("Search")."'/>";
	$ret .= "</td></tr>\n";
	$ret .= "</table>\n";
	$ret .= "</form>";
	$ret .= "<form method='post' id='series' action='?".$resultTarget."'>\n";
	$ret .= "<p>";
	$ret .= SeriesResults();
	foreach ($hiddenProperties as $name => $value) {
		$ret .= "<input type='hidden' name='".urlencode($name)."' value='".urlencode($value)."'/>\n";
	}
	foreach ($submitbuttons as $name => $value) {
		$ret .= "<input type='submit' name='".$name."' value='".utf8entities($value)."'/>\n";
	}
	$ret .= "</p>";
	$ret .= "</form>";
	return $ret;
}

function SearchPool($resultTarget, $hiddenProperties, $submitbuttons) {
	$querystring = $_SERVER['QUERY_STRING'];
	$ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
	$ret .= "<table><tr><td>"._("Event").":</td><td>";
	$ret .= SeasonControl();
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Division")."</td><td>";
	$ret .= "<input type='text' name='seriesname' value='";
	if (isset($_POST['seriesname'])) $ret .= $_POST['seriesname'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Division")."</td><td>";
	$ret .= "<input type='text' name='poolname' value='";
	if (isset($_POST['poolname'])) $ret .= $_POST['poolname'];
	$ret .="'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= "<input type='submit' name='searchpool' value='"._("Search")."'/>";
	$ret .= "</td></tr>\n";
	$ret .= "</table>\n";
	$ret .= "</form>";
	$ret .= "<form method='post' id='pools' action='?".$resultTarget."'>\n";
	$ret .= "<p>";
	$ret .= PoolResults();
	foreach ($hiddenProperties as $name => $value) {
		$ret .= "<input type='hidden' name='".urlencode($name)."' value='".urlencode($value)."'/>\n";
	}
	foreach ($submitbuttons as $name => $value) {
		$ret .= "<input type='submit' name='".$name."' value='".utf8entities($value)."'/>\n";
	}
	$ret .= "</p>";
	$ret .= "</form>";
	return $ret;
}

function SearchTeam($resultTarget, $hiddenProperties, $submitbuttons) {
	$querystring = $_SERVER['QUERY_STRING'];
	$ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
	$ret .= "<table><tr><td>"._("Event").":</td><td>";
	$ret .= SeasonControl();
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Division")."</td><td>";
	$ret .= "<input type='text' name='seriesname' value='";
	if (isset($_POST['seriesname'])) $ret .= $_POST['seriesname'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Team")."</td><td>";
	$ret .= "<input type='text' name='teamname' value='";
	if (isset($_POST['teamname'])) $ret .= $_POST['teamname'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= "<input type='submit' name='searchteam' value='"._("Search")."'/>";
	$ret .= "</td></tr>\n";
	$ret .= "</table>\n";
	$ret .= "</form>";
	$ret .= "<form method='post' id='teams' action='?".$resultTarget."'>\n";
	$ret .= "<p>";
	$ret .= TeamResults();
	foreach ($hiddenProperties as $name => $value) {
		$ret .= "<input type='hidden' name='".urlencode($name)."' value='".urlencode($value)."'/>\n";
	}
	foreach ($submitbuttons as $name => $value) {
		$ret .= "<input type='submit' name='".$name."' value='".utf8entities($value)."'/>\n";
	}
	$ret .= "</p>";
	$ret .= "</form>";
	return $ret;
}

function SearchUser($resultTarget, $hiddenProperties, $submitbuttons) {
	$querystring = $_SERVER['QUERY_STRING'];
	$ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
	$ret .= "<table><tr><td>"._("Event").":</td><td>";
	$ret .= "<input type='checkbox'";
	if (!empty($_POST['useseasons'])) {
		$ret .= " checked='checked'";
	}
	$ret .= " name='useseasons' value='true' />";
	$ret .= SeasonControl();
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Name")."</td><td>";
	$ret .= "<input type='text' name='username' value='";
	if (isset($_POST['username'])) $ret .= $_POST['username'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Team")."</td><td>";
	$ret .= "<input type='text' name='teamname' value='";
	if (isset($_POST['teamname'])) $ret .= $_POST['teamname'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Email")."</td><td>";
	$ret .= "<input type='text' name='email' value='";
	if (isset($_POST['email'])) $ret .= $_POST['email'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Unconfirmed").":</td><td>";
	$ret .= "<input type='checkbox'";
	if (!empty($_POST['registerrequest'])) {
		$ret .= " checked='checked'";
	}
	$ret .= " name='registerrequest' value='true' />";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= "<input type='submit' name='searchuser' value='"._("Search")."'/>";
	$ret .= "</td></tr>\n";
	$ret .= "</table>\n";
	$ret .= "</form>";
	$ret .= "<form method='post' id='users' action='?".$resultTarget."'>\n";
	$ret .= "<div>";
	$ret .= UserResults();
	foreach ($hiddenProperties as $name => $value) {
		$ret .= "<input type='hidden' name='".urlencode($name)."' value='".urlencode($value)."'/>\n";
	}
	if (!empty($_POST['registerrequest'])) {
		$ret .= "<input type='hidden' name='registerrequest' value='registerrequest'/>\n";
	}
	foreach ($submitbuttons as $name => $value) {
		$ret .= "<input type='submit' name='".$name."' value='".utf8entities($value)."'/>\n";
	}
	$ret .= "</div>";
	$ret .= "</form>";
	return $ret;
}

function SearchPlayer($resultTarget, $hiddenProperties, $submitbuttons) {
	$querystring = $_SERVER['QUERY_STRING'];
	$ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
	$ret .= "<table><tr><td>"._("Event").":</td><td>";
	$ret .= "<input type='checkbox'";
	if (!empty($_POST['useseasons'])) {
		$ret .= " checked='checked'";
	}
	$ret .= " name='useseasons' value='true' />";
	$ret .= SeasonControl();
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Name")."</td><td>";
	$ret .= "<input type='text' name='username' value='";
	if (isset($_POST['username'])) $ret .= $_POST['username'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Team")."</td><td>";
	$ret .= "<input type='text' name='teamname' value='";
	if (isset($_POST['teamname'])) $ret .= $_POST['teamname'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= _("Email")."</td><td>";
	$ret .= "<input type='text' name='email' value='";
	if (isset($_POST['email'])) $ret .= $_POST['email'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= "<input type='submit' name='searchplayer' value='"._("Search")."'/>";
	$ret .= "</td></tr>\n";
	$ret .= "</table>\n";
	$ret .= "</form>";
	$ret .= "<form method='post' id='users' action='?".$resultTarget."'>\n";
	$ret .= "<div>";
	$ret .= PlayerResults();
	foreach ($hiddenProperties as $name => $value) {
		$ret .= "<input type='hidden' name='".urlencode($name)."' value='".urlencode($value)."'/>\n";
	}
	if (!empty($_POST['registerrequest'])) {
		$ret .= "<input type='hidden' name='registerrequest' value='registerrequest'/>\n";
	}
	foreach ($submitbuttons as $name => $value) {
		$ret .= "<input type='submit' name='".$name."' value='".utf8entities($value)."'/>\n";
	}
	$ret .= "</div>";
	$ret .= "</form>";
	return $ret;
}

function SearchReservation($resultTarget, $hiddenProperties, $submitbuttons) {
	$querystring = $_SERVER['QUERY_STRING'];
	$ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
	$ret .= "<table style='width:100%'>";
	$ret .= "<tr><td>"._("Start time")." ("._("dd.mm.yyyy")."):</td><td>";
	$ret .= "<input type='text' id='searchstart' name='searchstart' value='";

	if (isset($_POST['searchstart'])) {
		$ret .= $_POST['searchstart'];
	}else {
		$ret .= date('d.m.Y');
	}
	$ret .= "'/>\n";
	$ret .= "&nbsp;<button type='button' class='button' id='showcal1'><img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td></td><td><div id='calContainer1'></div></td></tr>\n";
	$ret .= "<tr><td>"._("End time")." ("._("dd.mm.yyyy")."):</td><td>";
	$ret .= "<input type='text' id='searchend' name='searchend' value='";
	if (isset($_POST['searchend'])) {
		$ret .= $_POST['searchend'];
	} 
	$ret .= "'/>\n";
	$ret .= "&nbsp;<button type='button' class='button' id='showcal2'><img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td></td><td><div id='calContainer2'></div></td></tr>\n";
	$ret .= "<tr><td>"._("Grouping name").":</td><td>";
	$ret .= "<input type='text' name='searchgroup' value='";
	if (isset($_POST['searchgroup'])) $ret .= $_POST['searchgroup'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>"._("Field").":</td><td>";
	$ret .= "<input type='text' name='searchfield' value='";
	if (isset($_POST['searchfield'])) $ret .= $_POST['searchfield'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>"._("Location").":</td><td>";
	$ret .= "<input type='text' name='searchlocation' value='";
	if (isset($_POST['searchlocation'])) $ret .= $_POST['searchlocation'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= "<input type='submit' name='searchreservation' value='"._("Search")."'/>";
	$ret .= "</td></tr>\n";
	$ret .= "</table>\n";
	$ret .= "</form>";
	
	$ret .= "<form method='post' id='reservations' action='?".$resultTarget."'>\n";
	$ret .= ReservationResults();
	$ret .= "<p>";
	foreach ($hiddenProperties as $name => $value) {
		$ret .= "<input type='hidden' name='".urlencode($name)."' value='".urlencode($value)."'/>\n";
	}
	$ret .= "<input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/>\n";
	if (!empty($_POST['searchreservation']) || !empty($_GET['season'])) {
		foreach ($submitbuttons as $name => $value) {
			$ret .= "<input type='submit' name='".$name."' value='".utf8entities($value)."'/>\n";
		}
	}
	$ret .= "</p>";
	$ret .= "</form>";
	
	return $ret;
}

function SearchGame($resultTarget, $hiddenProperties, $submitbuttons) {
	$querystring = $_SERVER['QUERY_STRING'];
	//leads to styles included on middle of page
	$ret = "<form method='post' action='?".utf8entities($querystring)."'>\n";
	$ret .= "<table>";
	$ret .= "<tr><td>"._("Start time")." ("._("dd.mm.yyyy")."):</td><td>";
	$ret .= "<input type='text' id='searchstart' name='searchstart' value='";

	if (isset($_POST['searchstart'])) {
		$ret .= $_POST['searchstart'];
	} else {
		$ret .= date('d.m.Y');;
	}
	$ret .= "'/>\n";
	$ret .= "&nbsp;<button type='button' class='button' id='showcal1'><img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td></td><td><div id='calContainer1'></div></td></tr>\n";
	$ret .= "<tr><td>"._("End time")." ("._("dd.mm.yyyy")."):</td><td>";
	$ret .= "<input type='text' id='searchend' name='searchend' value='";
	if (isset($_POST['searchend'])) {
		$ret .= $_POST['searchend'];
	}
	
	$ret .= "'/>\n";
	$ret .= "&nbsp;<button type='button' class='button' id='showcal2'><img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td></td><td><div id='calContainer2'></div></td></tr>\n";
	$ret .= "<tr><td>"._("Tournament").":</td><td>";
	$ret .= "<input type='text' name='searchgroup' value='";
	if (isset($_POST['searchgroup'])) $ret .= $_POST['searchgroup'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>"._("Field").":</td><td>";
	$ret .= "<input type='text' name='searchfield' value='";
	if (isset($_POST['searchfield'])) $ret .= $_POST['searchfield'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>"._("Location").":</td><td>";
	$ret .= "<input type='text' name='searchlocation' value='";
	if (isset($_POST['searchlocation'])) $ret .= $_POST['searchlocation'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>"._("Team")." ("._("separate with a comma")."):</td><td>";
	$ret .= "<input type='text' name='searchteams' value='";
	if (isset($_POST['searchteams'])) $ret .= $_POST['searchteams'];
	$ret .= "'/>\n";
	$ret .= "</td></tr>\n";
	$ret .= "<tr><td>";
	$ret .= "<input type='submit' name='searchgame' value='"._("Search")."'/>";
	$ret .= "</td></tr>\n";
	$ret .= "</table>\n";
	$ret .= "</form>";
	
	$ret .= "<form method='post' id='games' action='?".$resultTarget."'>\n";
	$ret .= GameResults();
	$ret .= "<p>";
	foreach ($hiddenProperties as $name => $value) {
		$ret .= "<input type='hidden' name='".urlencode($name)."' value='".urlencode($value)."'/>\n";
	}
	foreach ($submitbuttons as $name => $value) {
		$ret .= "<input type='submit' name='".$name."' value='".utf8entities($value)."'/>\n";
	}
	$ret .= "</p>";
	$ret .= "</form>";
	
	return $ret;
}

function SeasonControl() {
	if (!empty($_POST['searchseasons'])) {
		$selected = array_flip($_POST['searchseasons']);
	} elseif (!empty($GET['Season'])) {
		$selected = array($GET['Season'] => 'selected');
	} elseif(!empty($_SESSION['userproperties']['editseason'])) {
		$selected = $_SESSION['userproperties']['editseason'];
	}else{
		$selected = array();
	}

	$ret = "<select multiple='multiple' name='searchseasons[]' id='searchseasons' style='height:200px'>\n";
	
	$seasons = Seasons();
	while($season = mysqli_fetch_assoc($seasons)){
		$ret .= "<option value='".urlencode($season['season_id'])."'";
		if (isset($selected[$season['season_id']])) {
			$ret .=	" selected='selected'";
		}
		$ret .= ">".utf8entities($season['name'])."</option>\n";
	}
	$ret .= "</select>\n";
	return $ret;
}

function SeriesResults() {
	if (empty($_POST['searchser'])) {
		return "";
	} else {
		$query = "SELECT seas.name as season_name, ser.series_id as series, ser.name as series_name ";
		$query .= "FROM uo_series as ser left join uo_season as seas on (ser.season = seas.season_id) ";
		$query .= "WHERE ser.season IN (";
		if (!empty($_POST['searchseasons'])) {
			$selected = array_flip($_POST['searchseasons']);
		} elseif (!empty($GET['Season'])) {
			$selected = array($GET['Season'] => 'selected');
		} else {
			$selected = $_SESSION['userproperties']['editseason'];
		}
		foreach ($selected as $seasonid => $value) {
			$query .= "'".DBEscapeString($seasonid)."', ";
		}
		$query = substr($query, 0, strlen($query) - 2);
		$query .= ")";
		if (!empty($_POST['seriesname']) && strlen(trim($_POST['seriesname'])) > 0) {
			$query .= " AND ser.name like '%".DBEscapeString(trim($_POST['seriesname']))."%'";
		} 
		
		$result = DBQuery($query);
	
		$ret = "<table><tr><th><input type='checkbox' onclick='checkAll(\"series\");' /></th>";
		$ret .= "<th>"._("Event")."</th><th>"._("Division")."</th></tr>\n";
		while ($row = mysqli_fetch_assoc($result)) {
			$ret .= "<tr><td><input type='checkbox' name='series[]' value='".utf8entities($row['series'])."' /></td>";
			$ret .= "<td>".utf8entities($row['season_name'])."</td><td>";
			$ret .= utf8entities($row['series_name'])."</td></tr>\n";
		}
		$ret .= "</table>\n";
		return $ret;
	}
}

function PoolResults() {
	if (empty($_POST['searchpool'])) {
		return "";
	} else {
		$query = "SELECT seas.name as season_name, ser.name as series_name, pool.pool_id as pool, pool.name as pool_name ";
		$query .= "FROM uo_pool as pool left join uo_series as ser on (pool.series = ser.series_id) left join uo_season as seas on (ser.season = seas.season_id) ";
		$query .= "WHERE ser.season IN (";
		if (!empty($_POST['searchseasons'])) {
			$selected = array_flip($_POST['searchseasons']);
		} elseif (!empty($GET['Season'])) {
			$selected = array($GET['Season'] => 'selected');
		} else {
			$selected = $_SESSION['userproperties']['editseason'];
		}
		foreach ($selected as $seasonid => $value) {
			$query .= "'".DBEscapeString($seasonid)."', ";
		}
		$query = substr($query, 0, strlen($query) - 2);
		$query .= ")";
		if (!empty($_POST['seriesname']) && strlen(trim($_POST['seriesname'])) > 0) {
			$query .= " AND ser.name like '%".DBEscapeString(trim($_POST['seriesname']))."%'";
		} 
		if (!empty($_POST['poolname']) && strlen(trim($_POST['poolname'])) > 0) {
			$query .= " AND pool.name like '%".DBEscapeString(trim($_POST['poolname']))."%'";
		} 

		$result = DBQuery($query);
	
		$ret = "<table><tr><th><input type='checkbox' onclick='checkAll(\"pools\");' /></th>";
		$ret .= "<th>"._("Event")."</th><th>"._("Division")."</th><th>"._("Division")."</th></tr>\n";
		while ($row = mysqli_fetch_assoc($result)) {
			$ret .= "<tr><td><input type='checkbox' name='pools[]' value='".utf8entities($row['pool'])."' /></td>";
			$ret .= "<td>".utf8entities($row['season_name'])."</td>";
			$ret .= "<td>".utf8entities($row['series_name'])."</td>";
			$ret .= "<td>".utf8entities($row['pool_name'])."</td>";
			$ret .= "</tr>\n";
		}
		$ret .= "</table>\n";
		return $ret;
	}
}

function TeamResults() {
	if (empty($_POST['searchteam'])) {
		return "";
	} else {
		$query = "SELECT seas.name as season_name, ser.name as series_name, team.team_id as team, team.name as team_name ";
		$query .= "FROM uo_team as team left join uo_series as ser on (team.series = ser.series_id) left join uo_season as seas on (ser.season = seas.season_id) ";
		$query .= "WHERE ser.season IN (";
		if (!empty($_POST['searchseasons'])) {
			$selected = array_flip($_POST['searchseasons']);
		} elseif (!empty($GET['Season'])) {
			$selected = array($GET['Season'] => 'selected');
		} else {
			$selected = $_SESSION['userproperties']['editseason'];
		}
		foreach ($selected as $seasonid => $value) {
			$query .= "'".DBEscapeString($seasonid)."', ";
		}
		$query = substr($query, 0, strlen($query) - 2);
		$query .= ")";
		if (!empty($_POST['seriesname']) && strlen(trim($_POST['seriesname'])) > 0) {
			$query .= " AND ser.name like '%".DBEscapeString(trim($_POST['seriesname']))."%'";
		} 
		if (!empty($_POST['teamname']) && strlen(trim($_POST['teamname'])) > 0) {
			$query .= " AND team.name like '%".DBEscapeString(trim($_POST['teamname']))."%'";
		} 

		$result = DBQuery($query);
		$ret = "<table><tr><th><input type='checkbox' onclick='checkAll(\"teams\")' /></th>";
		$ret .= "<th>"._("Event")."</th><th>"._("Division")."</th><th>"._("Team")."</th></tr>\n";
		while ($row = mysqli_fetch_assoc($result)) {
			$ret .= "<tr><td><input type='checkbox' name='teams[]' value='".utf8entities($row['team'])."' /></td>";
			$ret .= "<td>".utf8entities($row['season_name'])."</td>";
			$ret .= "<td>".utf8entities($row['series_name'])."</td>";
			$ret .= "<td>".utf8entities($row['team_name'])."</td>";
			$ret .= "</tr>\n";
		}
		$ret .= "</table>\n";
		return $ret;
	}
}

function UserResults() {
	if (empty($_POST['searchuser'])) {
		return "";
	} else {
		if (!empty($_POST['registerrequest'])) {
			$query = "SELECT name as user_name, userid, last_login, email FROM uo_registerrequest ";
		}else{
			$query = "SELECT name as user_name, userid, last_login, email FROM uo_users ";
		}
		
		if (!empty($_POST['searchseasons'])) {
			$selected = array_flip($_POST['searchseasons']);
		} elseif (!empty($GET['Season'])) {
			$selected = array($GET['Season'] => 'selected');
		} else {
			$selected = $_SESSION['userproperties']['editseason'];
		}
		$criteria = "";
		if (!empty($_POST['useseasons'])) {
			$criteria = "(userid in (select userid from uo_userproperties where name='editseason' and value in (";
			foreach ($selected as $seasonid => $prop) {
				$criteria .= "'".DBEscapeString($seasonid)."', ";
			}
			$criteria = substr($criteria, 0, strlen($criteria) - 2);
			$criteria .= ")))";
		}
		
		if (!empty($_POST['teamname'])) {
			if (strlen($criteria) > 0) {
				$criteria .= " and ";
			}
			$criteria .= "(userid in (select userid from uo_userproperties where name='userrole' ";
			$criteria .= "and value like 'teamadmin:%' and substring_index(value, ':', -1) in ";
			$criteria .= "(select team_id from uo_team where series in ";
			$criteria .= "(select series_id from uo_series where season in ("; 
			foreach ($selected as $seasonid => $value) {
				$criteria .= "'".DBEscapeString($seasonid)."', ";
			}
			$criteria = substr($criteria, 0, strlen($criteria) - 2);
			$criteria .= ")) and name like '%".DBEscapeString($_POST['teamname'])."%')))";
		}
		if (!empty($_POST['username'])) {
			if (strlen($criteria) > 0) {
				$criteria .= " and ";
			}
			$criteria .= "(name like '%".DBEscapeString($_POST['username'])."%')";
		}
		
		if (!empty($_POST['email'])) {
			if (strlen($criteria) > 0) {
				$criteria .= " and ";
			}
			$criteria .= "(email like '%".DBEscapeString($_POST['email'])."%')";
		}
		
		if (strlen($criteria) > 0) {
			$query .= " WHERE ".$criteria;	
		}
		$query .= " ORDER BY userid, name";

		$result = DBQuery($query);
		
		$ret = "<table style='white-space: nowrap;'><tr><th><input type='checkbox' onclick='checkAll(\"users\");'/></th>";
		$ret .= "<th>"._("Name")."</th><th>"._("Username")."</th><th>"._("Email")."</th><th>"._("Rights")."</th><th>"._("Last login")."</th></tr>\n";
		while ($row = mysqli_fetch_assoc($result)) {
			$ret .= "<tr><td style='vertical-align:text-top;'>";
			if (urlencode($row['userid']) != 'anonymous') {
				$ret .= "<input type='checkbox' name='users[]' value='".urlencode($row['userid'])."'/>";
			} else {
				$ret .= "&nbsp;";
			}
			$ret .= "</td>";
			$ret .= "<td style='vertical-align:text-top;'><a href='?view=user/userinfo&amp;user=".urlencode($row['userid'])."'>".utf8entities($row['user_name'])."</a></td>";
			$ret .= "<td style='vertical-align:text-top;'>".utf8entities($row['userid'])."</td>";
			$ret .= "<td style='vertical-align:text-top;'>".utf8entities($row['email'])."</td>";
			
			$ret .= "<td style='vertical-align:text-top;'>".UserListRightsHtml($row['userid'])."</td>";
			
			$ret .= "<td style='vertical-align:text-top;'>".LongTimeFormat($row['last_login'])."</td>";			
			$ret .= "</tr>\n";
		}
		$ret .= "</table>\n";
		return $ret;
	}
}

function PlayerResults() {
	if (empty($_POST['searchplayer'])) {
		return "";
	} else {
		$query = "SELECT DISTINCT MAX(player_id) as player_id, CONCAT(firstname, ' ', lastname) as user_name, ";
		$query .= "GROUP_CONCAT(DISTINCT email SEPARATOR ', ') as email, accreditation_id, GROUP_CONCAT(DISTINCT t.name ORDER BY t.team_id DESC SEPARATOR ', ') as teamname ";
		$query .= "FROM uo_player p join uo_team t ON (p.team = t.team_id) ";
		
		if (!empty($_POST['searchseasons'])) {
			$selected = array_flip($_POST['searchseasons']);
		} elseif (!empty($GET['Season'])) {
			$selected = array($GET['Season'] => 'selected');
		} else {
			$selected = $_SESSION['userproperties']['editseason'];
		}
		$criteria = "";
		if (!empty($_POST['useseasons'])) {
			$criteria = "(team in ";
			$criteria .= "(select team_id from uo_team where series in ";
			$criteria .= "(select series_id from uo_series where season in (";
			foreach ($selected as $seasonid => $prop) {
				$criteria .= "'".DBEscapeString($seasonid)."', ";
			}
			$criteria = substr($criteria, 0, strlen($criteria) - 2);
			$criteria .= "))))";
		}
		
		if (!empty($_POST['teamname'])) {
			if (strlen($criteria) > 0) {
				$criteria .= " and ";
			}
			$criteria .= "(team in ";
			$criteria .= "(select team_id from uo_team where series in ";
			$criteria .= "(select series_id from uo_series where season in ("; 
			foreach ($selected as $seasonid => $value) {
				$criteria .= "'".DBEscapeString($seasonid)."', ";
			}
			$criteria = substr($criteria, 0, strlen($criteria) - 2);
			$criteria .= ")) and name like '%".DBEscapeString($_POST['teamname'])."%'))";
		}
		if (!empty($_POST['username'])) {
			if (strlen($criteria) > 0) {
				$criteria .= " and ";
			}
			$criteria .= "(firstname like '%".DBEscapeString($_POST['username'])."%'";
			$criteria .= " or lastname like '%".DBEscapeString($_POST['username'])."%'";
			$criteria .= " or CONCAT(firstname, ' ', lastname) like '%".DBEscapeString($_POST['username'])."%')";
		}
		
		if (!empty($_POST['email'])) {
			if (strlen($criteria) > 0) {
				$criteria .= " and ";
			}
			$criteria .= "(email like '%".DBEscapeString($_POST['email'])."%')";
		}
		
		if (strlen($criteria) > 0) {
			$query .= " WHERE ".$criteria;	
		}
		$query .= " GROUP BY CONCAT(firstname, ' ', lastname), accreditation_id";
		$query .= " ORDER BY lastname, firstname, t.name";
		$result = DBQuery($query);
		
		$ret = "<table><tr><th><input type='checkbox' onclick='checkAll(\"players[]\");'/></th>";
		$ret .= "<th>"._("Name")."</th><th>"._("Team")."</th><th>"._("Email")."</th></tr>\n";
		while ($row = mysqli_fetch_assoc($result)) {
			$ret .= "<tr><td>";
			$ret .= "<input type='checkbox' name='players[]' value='".urlencode($row['accreditation_id'])."'/>";
			$ret .= "</td>";
			$ret .= "<td><a href='?view=playercard&amp;player=".urlencode($row['player_id'])."'>".utf8entities($row['user_name'])."</a></td>";
			$ret .= "<td>".utf8entities($row['teamname'])."</td>";
			$ret .= "<td>".utf8entities($row['email'])."</td>";
			$ret .= "</tr>\n";
		}
		$ret .= "</table>\n";
		return $ret;
	}
}

function ReservationResults() {
	if (empty($_POST['searchreservation']) && empty($_GET['season'])) {
		return "";
	} else {
		$query = "SELECT res.id as reservation_id, res.season, res.location, res.fieldname, res.reservationgroup, res.starttime, res.endtime, loc.name, loc.fields, loc.indoor, loc.address, count(game_id) as games ";
		$query .= "FROM uo_reservation res left join uo_location as loc on (res.location = loc.id) left join uo_game as game on (res.id = game.reservation) ";

		$start = "";
		if (isset($_POST['searchstart'])) {
			$start = $_POST['searchstart'];
		}
		
		//else {
		//	$start = date('d.m.Y');
		//}
		if (isset($_POST['searchend'])) {
			$end = $_POST['searchend'];
		} 
		
		if(empty($end)){
			$query .= "WHERE res.starttime>'".ToInternalTimeFormat($start." 00:00")."'";
		}else{
			$query .= "WHERE res.starttime>'".ToInternalTimeFormat($start." 00:00")."' AND ";
			$query .= "res.endtime<'".ToInternalTimeFormat($end." 23:59")."' ";
		}
		if (isset($_POST['searchgroup']) && strlen($_POST['searchgroup']) > 0) {
			$query .= "AND res.reservationgroup like '%".DBEscapeString($_POST['searchgroup'])."%' ";
		}
		if (isset($_POST['searchfield']) && strlen($_POST['searchfield']) > 0) {
			$query .= "AND res.fieldname like '".DBEscapeString($_POST['searchfield'])."' ";
		}
		if (isset($_POST['searchlocation']) && strlen($_POST['searchlocation']) > 0) {
			$query .= "AND (loc.name like '%".DBEscapeString($_POST['searchlocation'])."%' OR ";
			$query .= "loc.address like '%".DBEscapeString($_POST['searchlocation'])."%') ";
		}
		
		if (isset($_GET['season']) && strlen($_GET['season']) > 0) {
			$query .= "AND res.season='".DBEscapeString($_GET['season'])."' ";
		}
		$query .= "GROUP BY res.starttime, res.id, res.location, res.fieldname, res.reservationgroup, res.endtime, loc.name, loc.fields, loc.indoor, loc.address";

		$result = DBQuery($query);
		
		$ret = "<table class='admintable'><tr><th><input type='checkbox' onclick='checkAll(\"reservations\");'/></th>";
		$ret .= "<th>"._("Group")."</th><th>"._("Location")."</th><th>"._("Date")."</th>";
		$ret .= "<th>"._("Starts")."</th><th>"._("Ends")."</th><th>"._("Games")."</th>";
		$ret .= "<th>"._("Scoresheets")."</th><th></th></tr>\n";
		while ($row = mysqli_fetch_assoc($result)) {
			$ret .= "<tr class='admintablerow'><td><input type='checkbox' name='reservations[]' value='".utf8entities($row['reservation_id'])."'/></td>";
			$ret .= "<td>".utf8entities(U_($row['reservationgroup']))."</td>";
			$ret .= "<td><a href='?view=admin/addreservation&amp;reservation=".$row['reservation_id']."&amp;season=".$row['season']."'>".utf8entities(U_($row['name']))." "._("Field")." ".utf8entities(U_($row['fieldname']))."</a></td>";
			$ret .= "<td>".DefWeekDateFormat($row['starttime'])."</td>";
			$ret .= "<td>".DefHourFormat($row['starttime'])."</td>";
			$ret .= "<td>".DefHourFormat($row['endtime'])."</td>";
			$ret .= "<td class='center'>".$row['games']."</td>";
			$ret .= "<td class='center'><a href='?view=user/pdfscoresheet&amp;reservation=".$row['reservation_id']."'>"._("PDF")."</a></td>";
			if(intval($row['games'])==0){
				$ret .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' name='remove' alt='"._("X")."' onclick=\"setId(".$row['reservation_id'].");\"/></td>";
			}
			
			$ret .= "</tr>\n";
		}
		$ret .= "</table>\n";
		return $ret;
		
	}
}

function GameResults() {
	
	if (empty($_POST['searchgame'])) {
		return "";
	} else {
		$query = "SELECT game_id, hometeam, kj.name as hometeamname, visitorteam, vj.name as visitorteamname, pp.pool as pool,
			time, homescore, visitorscore, pool.timecap, pool.timeslot, pool.series,
			CONCAT(loc.name, ' "._("Field")." ', res.fieldname) AS locationname,
			res.reservationgroup,phome.name AS phometeamname, pvisitor.name AS pvisitorteamname
		FROM uo_game pp left join uo_reservation res on (pp.reservation=res.id) 
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
			left join uo_location loc on (res.location=loc.id)";

		if (isset($_POST['searchstart'])) {
			$start = $_POST['searchstart'];
		} else {
			$start = date('d.m.Y');
		}
		
		if (isset($_POST['searchend'])) {
			$end = $_POST['searchend'];
		} 
		
		if(empty($end)){
			$query .= "WHERE res.starttime>'".ToInternalTimeFormat($start." 00:00")."'";
		}else{
			$query .= "WHERE res.starttime>'".ToInternalTimeFormat($start." 00:00")."' AND ";
			$query .= "res.endtime<'".ToInternalTimeFormat($end." 23:59")."' ";
		}
		
		if (isset($_POST['searchgroup']) && strlen($_POST['searchgroup']) > 0) {
			$query .= "AND res.reservationgroup like '%".DBEscapeString($_POST['searchgroup'])."%' ";
		}
		if (isset($_POST['searchfield']) && strlen($_POST['searchfield']) > 0) {
			$query .= "AND res.fieldname like '".DBEscapeString($_POST['searchfield'])."' ";
		}
		if (isset($_POST['searchlocation']) && strlen($_POST['searchlocation']) > 0) {
			$query .= "AND (loc.name like '%".DBEscapeString($_POST['searchlocation'])."%' OR ";
			$query .= "loc.address like '%".DBEscapeString($_POST['searchlocation'])."%') ";
		}
		if (isset($_POST['searchteams']) && strlen($_POST['searchteams'])) {
			foreach (explode(',',$_POST['searchteams']) as $team) {
				$query .= "AND (vj.name LIKE '%".DBEscapeString($team)."%' OR kj.name LIKE '%".DBEscapeString($team)."%') ";
			}
		}
		$result = DBQuery($query);
		
		$ret = "<table><tr><th><input type='checkbox' onclick='checkAll(\"games\");'/></th>";
		$ret .= "<th>"._("Tournament")."</th><th>"._("Location")."</th><th>"._("Game")."</th></tr>\n";
		while ($row = mysqli_fetch_assoc($result)) {
			$ret .= "<tr><td><input type='checkbox' name='games[]' value='".utf8entities($row['game_id'])."'/></td>";
			$ret .= "<td>".utf8entities($row['reservationgroup'])."</td>";
			$ret .= "<td>".utf8entities($row['locationname'])."</td>";
			$ret .= "<td>".utf8entities(GameName($row))."</td>";
			$ret .= "</tr>\n";
		}
		$ret .= "</table>\n";
		return $ret;
	}
}
?>