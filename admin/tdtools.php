<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/timetable.functions.php';


// function to search for missing spirit scores in played games (search by pool)
// returns an array of game id's
function SearchMissingSpiritByPool($poolId) {
  $query = sprintf("SELECT game_id, th.name AS home, tv.name AS visitor, g.homescore, g.homesotg, g.visitorscore, g.visitorsotg, g.time AS time FROM uo_game AS g
    JOIN uo_team AS th ON (g.hometeam=th.team_id)
    JOIN uo_team AS tv ON (g.visitorteam=tv.team_id)
    WHERE g.pool=%d 
      AND g.isongoing=0 
      AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0 
      AND (g.homesotg IS NULL OR g.homesotg=0 OR g.visitorsotg IS NULL OR g.visitorsotg=0)
      ORDER BY g.time ASC",
    (int)$poolId);

    return DBQueryToArray($query);
}

// function to search for missing spirit scores in played games (search by division)
// returns an array of game id's
function SearchMissingSpiritBySeries($seriesId) {
  $query = sprintf("SELECT game_id, th.name AS home, tv.name AS visitor, g.homescore, g.homesotg, g.visitorscore, g.visitorsotg, g.time AS time, p.name AS poolname FROM uo_game AS g
    JOIN uo_team AS th ON (g.hometeam=th.team_id)
    JOIN uo_team AS tv ON (g.visitorteam=tv.team_id)
    JOIN uo_pool AS p ON g.pool=p.pool_id
    WHERE p.series=%d 
      AND g.isongoing=0 
      AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0 
      AND (g.homesotg IS NULL OR g.homesotg=0 OR g.visitorsotg IS NULL OR g.visitorsotg=0)
      ORDER BY g.time ASC",
    (int)$seriesId);

    return DBQueryToArray($query);
}

// function to search for spirit scores of '$catval' in at least one item
// returns html code for table of matches
function TableSpiritSearchCat($season,$catval) {

  $ret = "";

  $query = sprintf("SELECT 	sp.game_id AS game_id, 
		IF(g.visitorteam = sp.team_id, tv.name, th.name) AS givenfor, 
    	IF(g.visitorteam = sp.team_id, th.name, tv.name) AS givenby, 
    	CONCAT(cat1,' ',cat2,' ',cat3,' ',cat4,' ',cat5) AS scores, 
        (cat1+cat2+cat3+cat4+cat5) AS total,
        p.name AS pool,
        s.name AS division
	FROM uo_spirit as sp
    JOIN uo_game g ON g.game_id=sp.game_id
    JOIN uo_team th ON th.team_id=g.hometeam
	JOIN uo_team tv ON tv.team_id=g.visitorteam
    JOIN uo_pool p ON p.pool_id=g.pool
    JOIN uo_series s ON s.series_id=p.series
    WHERE s.season='%s'
      AND g.isongoing=0 
      AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0 
      AND (cat1+cat2+cat3+cat4+cat5)>0
      AND (sp.cat1=%d OR sp.cat2=%d OR sp.cat3=%d OR sp.cat4=%d OR sp.cat5=%d)
    ORDER BY p.series ASC, givenfor ASC",
    $season,
    (int)$catval,
    (int)$catval,
    (int)$catval,
    (int)$catval,
    (int)$catval);

  $resultsTable = DBQuery($query);

  if (DBNumRows($resultsTable)>0) {	

    $ret .= "<p>"._("List of teams that received a '").$catval._("' in at least one category:")."</p>";
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th class='center'>"._("Division")."</th>";
    $ret .= "<th class='center'>"._("Pool")."</th>";
    $ret .= "<th class='center'>"._("Scores")."</th>";
    $ret .= "<th class='center'>"._("Total")."</th>";
    $ret .= "<th>"._("Given for")."</th>";
    $ret .= "<th>"._("Given by")."</th>";
    $ret .= "<th class='center'>"._("Link")."</th>";
    $ret .= "</tr>";
    
    while ($row = DBFetchAssoc($resultsTable)) {
      $ret .= "<tr>";
      $ret .= "<td>".$row['division']."</td>";
      $ret .= "<td>".$row['pool']."</td>";
      $ret .= "<td class='center'>".$row['scores']."</td>";
      $ret .= "<td class='center'>".$row['total']."</td>";
      $ret .= "<td>".$row['givenfor']."</td>";
      $ret .= "<td>".$row['givenby']."</td>";
      $ret .= "<td><a href='?view=user/addspirit&amp;game=".$row['game_id']."'>"._("Edit Spirit")."</a></td>";
      $ret .= "</tr>";
    }
    
    $ret .= "</table>";

  } else {
    $ret = "<p>"._("Nothing found.")."</p>";
  }

  return $ret;

}

// function to search for multiple spirit scores of '$catval' in category number "$catnum"
// returns html code for table of matches
function TableSpiritSearchCatReps($season,$catnum,$catval) {

  $ret = "";

  $query = sprintf("SELECT 	sp.game_id AS game_id, 
		IF(g.visitorteam = sp.team_id, tv.name, th.name) AS givenfor, 
    	IF(g.visitorteam = sp.team_id, th.name, tv.name) AS givenby, 
    	CONCAT(cat1,' ',cat2,' ',cat3,' ',cat4,' ',cat5) AS scores, 
        (cat1+cat2+cat3+cat4+cat5) AS total,
        p.name AS pool,
        s.name AS division,
        g.time AS time
	FROM uo_spirit as sp
    JOIN (SELECT team_id, cat%d AS c, COUNT(*) FROM uo_spirit GROUP BY team_id,c HAVING COUNT(*)>1)reps ON sp.team_id = reps.team_id AND reps.c=%d
    JOIN uo_game g ON g.game_id=sp.game_id
    JOIN uo_team th ON th.team_id=g.hometeam
	JOIN uo_team tv ON tv.team_id=g.visitorteam
    JOIN uo_pool p ON p.pool_id=g.pool
    JOIN uo_series s ON s.series_id=p.series
    WHERE s.season='%s'
      AND g.isongoing=0 
      AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0 
      AND (cat1+cat2+cat3+cat4+cat5)>0
      AND sp.cat%d=%d
    ORDER BY p.series ASC, givenfor ASC, time ASC",
    (int)$catnum,
    (int)$catval,
    DBEscapeString($season),
    (int)$catnum,
    (int)$catval);

  $resultsTable = DBQuery($query);

  if (DBNumRows($resultsTable)>0) {	

    $ret .= "<table width='100%'><tr>";
    $ret .= "<th class='center'>"._("Division")."</th>";
    $ret .= "<th class='center'>"._("Pool")."</th>";
    $ret .= "<th class='center'>"._("Scores")."</th>";
    $ret .= "<th class='center'>"._("Total")."</th>";
    $ret .= "<th>"._("Given for")."</th>";
    $ret .= "<th>"._("Given by")."</th>";
    $ret .= "<th class='center'>"._("Link")."</th>";
    $ret .= "</tr>";
    
    while ($row = DBFetchAssoc($resultsTable)) {
      $ret .= "<tr>";
      $ret .= "<td>".$row['division']."</td>";
      $ret .= "<td>".$row['pool']."</td>";
      $ret .= "<td class='center'>".$row['scores']."</td>";
      $ret .= "<td class='center'>".$row['total']."</td>";
      $ret .= "<td>".$row['givenfor']."</td>";
      $ret .= "<td>".$row['givenby']."</td>";
      $ret .= "<td><a href='?view=user/addspirit&amp;game=".$row['game_id']."'>"._("Edit Spirit")."</a></td>";
      $ret .= "</tr>";
    }
    
    $ret .= "</table>";

  } else {
    $ret = "<p>"._("Nothing found.")."</p>";
  }

  return $ret;

}

// function to search for spirit scores (total) higher or lower than a threshold
// returns html code for table of matches
function TableSpiritSearchTotal($season,$th,$higher=true) {
  
  $ret = "";

  $op = $higher ? '>' : '<';

  $query = sprintf("SELECT 	sp.game_id AS game_id, 
		IF(g.visitorteam = sp.team_id, tv.name, th.name) AS givenfor, 
    	IF(g.visitorteam = sp.team_id, th.name, tv.name) AS givenby, 
    	CONCAT(cat1,' ',cat2,' ',cat3,' ',cat4,' ',cat5) AS scores, 
        (cat1+cat2+cat3+cat4+cat5) AS total,
        p.name AS pool,
        s.name AS division
	FROM uo_spirit as sp
    JOIN uo_game g ON g.game_id=sp.game_id
    JOIN uo_team th ON th.team_id=g.hometeam
	JOIN uo_team tv ON tv.team_id=g.visitorteam
    JOIN uo_pool p ON p.pool_id=g.pool
    JOIN uo_series s ON s.series_id=p.series
    WHERE s.season='%s'
      AND g.isongoing=0 
      AND (COALESCE(g.homescore,0)+COALESCE(g.visitorscore,0))>0 
      AND (cat1+cat2+cat3+cat4+cat5)>0
      AND (sp.cat1+sp.cat2+sp.cat3+sp.cat4+sp.cat5)%s%d
    ORDER BY p.series ASC, givenfor ASC",
    $season,
    $op,
    (int)$th);

  $resultsTable = DBQuery($query);

  if (DBNumRows($resultsTable)>0) {	

    $ret .= "<p>"._("List of teams that received a score '").$op.$th."'</p>";
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th class='center'>"._("Division")."</th>";
    $ret .= "<th class='center'>"._("Pool")."</th>";
    $ret .= "<th class='center'>"._("Scores")."</th>";
    $ret .= "<th class='center'>"._("Total")."</th>";
    $ret .= "<th>"._("Given for")."</th>";
    $ret .= "<th>"._("Given by")."</th>";
    $ret .= "<th class='center'>"._("Link")."</th>";
    $ret .= "</tr>";
    
    while ($row = DBFetchAssoc($resultsTable)) {
      $ret .= "<tr>";
      $ret .= "<td>".$row['division']."</td>";
      $ret .= "<td>".$row['pool']."</td>";
      $ret .= "<td class='center'>".$row['scores']."</td>";
      $ret .= "<td class='center'>".$row['total']."</td>";
      $ret .= "<td>".$row['givenfor']."</td>";
      $ret .= "<td>".$row['givenby']."</td>";
      $ret .= "<td><a href='?view=user/addspirit&amp;game=".$row['game_id']."'>"._("Edit Spirit")."</a></td>";
      $ret .= "</tr>";
    }
    
    $ret .= "</table>";

  } else {
    $ret = "<p>"._("Nothing found for '").$op.$th."'.</p>";
  }

  return $ret;


}

// function to search for spirit scores (average) higher or lower than a threshold in any category
// returns html code for table of matches
function TableSpiritSearchCatAvg($season,$th,$higher=true) {
  
  $ret = "";

  $op = $higher ? '>' : '<';

  $query = sprintf("SELECT s.name AS seriesname, t.name AS teamname, 
      ROUND(AVG(sp.cat1),2) AS cat1, ROUND(AVG(sp.cat2),2) AS cat2, 
      ROUND(AVG(sp.cat3),2) AS cat3, ROUND(AVG(sp.cat4),2) AS cat4, 
      ROUND(AVG(sp.cat5),2) AS cat5, 
      ROUND(AVG(sp.cat1+sp.cat2+sp.cat3+sp.cat4+sp.cat5),2) AS total, 
      COUNT(*) AS games
    FROM uo_spirit AS sp
    JOIN uo_team AS t ON sp.team_id=t.team_id
    JOIN uo_game AS g ON sp.game_id=g.game_id
    JOIN uo_pool AS p ON g.pool=p.pool_id
    JOIN uo_series AS s ON p.series=s.series_id
    WHERE s.season='%s' AND (sp.cat1+sp.cat2+sp.cat3+sp.cat4+sp.cat5)>0
    GROUP BY sp.team_id
    HAVING cat1%s%f OR cat2%s%f OR cat3%s%f OR cat4%s%f OR cat5%s%f
    ORDER BY s.series_id
  
    ",
    $season,
    $op,(float)$th,
    $op,(float)$th,
    $op,(float)$th,
    $op,(float)$th,
    $op,(float)$th);

  $resultsTable = DBQuery($query);

  if (DBNumRows($resultsTable)>0) {	

    $ret .= "<p>"._("List of teams that received an average '").$op.$th._("' in at least one category:")."</p>";
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th>"._("Division")."</th>";
    $ret .= "<th>"._("Team")."</th>";
    $ret .= "<th class='center'>"._("Rules")."</th>";
    $ret .= "<th class='center'>"._("Fouls")."</th>";
    $ret .= "<th class='center'>"._("Fair")."</th>";
    $ret .= "<th class='center'>"._("Attitude")."</th>";
    $ret .= "<th class='center'>"._("Comm")."</th>";
    $ret .= "<th class='center'>"._("Total")."</th>";
    $ret .= "</tr>";
    
    while ($row = DBFetchAssoc($resultsTable)) {
      $ret .= "<tr>";
      $ret .= "<td>".$row['seriesname']."</td>";
      $ret .= "<td>".$row['teamname']."</td>";
      $ret .= "<td class='center'>".$row['cat1']."</td>";
      $ret .= "<td class='center'>".$row['cat2']."</td>";
      $ret .= "<td class='center'>".$row['cat3']."</td>";
      $ret .= "<td class='center'>".$row['cat4']."</td>";
      $ret .= "<td class='center'>".$row['cat5']."</td>";
      $ret .= "<td class='center'>".$row['total']."</td>";
      //$ret .= "<td><a href='?view=user/addspirit&amp;game=".$row['game_id']."'>"._("Edit Spirit")."</a></td>";
      $ret .= "</tr>";
    }
    
    $ret .= "</table>";

  } else {
    $ret = "<p>"._("Nothing found for '").$op.$th."'.</p>";
  }

  return $ret;


}


function TableSpiritSearchComments($season) {

  $ret = "";

  $query = "SELECT sp.*, 
    (sp.cat1 + sp.cat2 + sp.cat3 + sp.cat4 + sp.cat5) AS total, 
    CONCAT(cat1,' ',cat2,' ',cat3,' ',cat4,' ',cat5) AS scores,
    IF(sp.team_id = g.hometeam, tv.name, th.name) AS givenby, 
    IF(sp.team_id = g.hometeam, th.name, tv.name) AS givenfor,
    s.name AS division,
    p.name AS pool,
    sp.game_id
    FROM uo_spirit AS sp
    JOIN uo_game AS g ON sp.game_id = g.game_id
    JOIN uo_team AS th ON g.hometeam = th.team_id
    JOIN uo_team AS tv ON g.visitorteam = tv.team_id
    JOIN uo_pool AS p ON g.pool = p.pool_id
    JOIN uo_series AS s ON p.series = s.series_id
    WHERE LENGTH(trim(sp.comments)) > 0
    ORDER BY g.`time` DESC, g.reservation";

  $resultsTable = DBQuery($query);

  if (DBNumRows($resultsTable)>0) {	

    $ret .= "<p>"._("List of teams that received a comment in a game")."</p>";
    $ret .= "<table width='100%' border=1><tr>";
    $ret .= "<th class='center'>"._("Division")."</th>";
    $ret .= "<th class='center'>"._("Pool")."</th>";
    $ret .= "<th class='center'>"._("Scores")."</th>";
    $ret .= "<th class='center'>"._("Total")."</th>";
    $ret .= "<th class='center'>"._("Comment")."</th>";
    $ret .= "<th>"._("Given for")."</th>";
    $ret .= "<th>"._("Given by")."</th>";
    $ret .= "<th class='center'>"._("Link")."</th>";
    $ret .= "</tr>";
    
    while ($row = DBFetchAssoc($resultsTable)) {
      $ret .= "<tr>";
      $ret .= "<td>".$row['division']."</td>";
      $ret .= "<td>".$row['pool']."</td>";
      $ret .= "<td class='center' style='white-space: nowrap;'>".$row['scores']."</td>";
      $ret .= "<td class='center'>".$row['total']."</td>";
      $ret .= "<td class='center'>".$row['comments']."</td>";
      $ret .= "<td>".$row['givenfor']."</td>";
      $ret .= "<td>".$row['givenby']."</td>";
      $ret .= "<td><a href='?view=user/addspirit&amp;game=".$row['game_id']."'>"._("Edit Spirit")."</a></td>";
      $ret .= "</tr>";
    }
    
    $ret .= "</table>";

  } else {
    $ret = "<p>"._("Nothing found.")."</p>";
  }

  return $ret;
}


// function to search missing jersey numbers on the rosters
function TableMissingNumbers($season) {

$ret = "";

$query = sprintf("SELECT p.num, p.firstname, p.lastname, t.name AS team, t.team_id AS team_id, s.name AS division
    FROM uo_player p
    JOIN uo_team t ON p.team = t.team_id
    JOIN uo_series s ON t.series = s.series_id
    WHERE s.season = '%s' AND p.num IS NULL
    ORDER BY division, team, p.num",$season);

  $resultsTable = DBQuery($query);

  if (DBNumRows($resultsTable)>0) {	

    $ret .= "<p>"._("Missing shirt numbers found! (click the team name to edit the roster)")."</p>";
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th class='center'>"._("Number")."</th>";
    $ret .= "<th class='center'>"._("First Name")."</th>";
    $ret .= "<th class='center'>"._("Last Name")."</th>";
    $ret .= "<th class='center'>"._("Team")."</th>";
    $ret .= "<th class='center'>"._("Division")."</th>";
    $ret .= "<th class='center'>"._("Link")."</th>";
    $ret .= "</tr>";
    
    while ($row = DBFetchAssoc($resultsTable)) {
      $ret .= "<tr>";
      $ret .= "<td class='center'>".$row['num']."</td>";
      $ret .= "<td>".$row['firstname']."</td>";
      $ret .= "<td>".$row['lastname']."</td>";
      $ret .= "<td><a href='?view=user/teamplayers&team=".$row['team_id']."'>".$row['team']."</a></td>";
      $ret .= "<td>".$row['division']."</td>";
      $ret .= "</tr>";
    }
    
    $ret .= "</table>";

  } else {
    $ret = "<p>"._("No missing shirt numbers found.")."</p>";
  }

  return $ret;
  
}

// function to search for duplicate jersey numbers on the rosters and print a table with the results
function TableDuplicateNumbers($season) {

$ret = "";

  $query = sprintf("SELECT p.num, p.firstname, p.lastname, t.name AS team, t.team_id AS team_id, s.name AS division
    FROM uo_player p
    JOIN (
    SELECT num, team, COUNT( * ) 
    FROM uo_player
    GROUP BY num, team
    HAVING COUNT( * ) >1
    )dups ON p.num = dups.num
    AND p.team = dups.team
    JOIN uo_team t ON p.team = t.team_id
    JOIN uo_series s ON t.series = s.series_id
    WHERE s.season = '%s'
    ORDER BY division, team, p.num",$season);

  $resultsTable = DBQuery($query);

  if (DBNumRows($resultsTable)>0) {	

    $ret .= "<p>"._("Duplicates found! (click the team name to edit the roster)")."</p>";
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th class='center'>"._("Number")."</th>";
    $ret .= "<th class='center'>"._("First Name")."</th>";
    $ret .= "<th class='center'>"._("Last Name")."</th>";
    $ret .= "<th class='center'>"._("Team")."</th>";
    $ret .= "<th class='center'>"._("Division")."</th>";
    $ret .= "<th class='center'>"._("Link")."</th>";
    $ret .= "</tr>";
    
    while ($row = DBFetchAssoc($resultsTable)) {
      $ret .= "<tr>";
      $ret .= "<td class='center'>".$row['num']."</td>";
      $ret .= "<td>".$row['firstname']."</td>";
      $ret .= "<td>".$row['lastname']."</td>";
      $ret .= "<td><a href='?view=user/teamplayers&team=".$row['team_id']."'>".$row['team']."</a></td>";
      $ret .= "<td>".$row['division']."</td>";
      $ret .= "</tr>";
    }
    
    $ret .= "</table>";

  } else {
    $ret = "<p>"._("No duplicates found.")."</p>";
  }

  return $ret;

}


//
function TableTimeoutStats() {

  $ret = "";

  $query = "SELECT DISTINCT reservationgroup FROM uo_reservation";
  $reservations = DBQueryToArray($query);

  $query = "SELECT r.reservationgroup AS day, COUNT(*) AS timeouts FROM `uo_timeout` AS t
    JOIN uo_game AS g ON t.game=g.game_id
    JOIN uo_reservation AS r ON g.reservation=r.id
    GROUP BY r.reservationgroup
    ORDER BY r.date";
  $timeouts = DBQueryToArray($query);

  $query = "SELECT r.reservationgroup AS day, COUNT(*) AS games FROM uo_game AS g
    JOIN uo_reservation AS r ON g.reservation=r.id
    GROUP BY r.reservationgroup
    ORDER BY r.date";
  $games = DBQueryToArray($query);

  $ret .= "<table width=50% border=1>";
  
  $ret .= "<tr>";
  $ret .= "<th class='center'>Day</th>";
  $ret .= "<th class='center'>Games</th>";
  $ret .= "<th class='center'>Timeouts</th>";
  $ret .= "<th class='center'>Average</th>";
  $ret .= "<tr>";

  $games = 13;
  $timeouts = 3;

  foreach ($reservations as $r) {
    $group = $r['reservationgroup'];

    $query = "SELECT COUNT(*) FROM uo_game AS g
      JOIN uo_reservation AS r ON g.reservation=r.id
      WHERE r.reservationgroup='$group'";
    $games = DBQueryToValue($query);

    $query = "SELECT COUNT(*) FROM `uo_timeout` AS t
      JOIN uo_game AS g ON t.game=g.game_id
      JOIN uo_reservation AS r ON g.reservation=r.id
      WHERE r.reservationgroup='$group'";
    $timeouts = DBQueryToValue($query);

    $ret .= "<tr>";
    $ret .= "<td>".$group."</td>";
    $ret .= "<td class='right'>".$games."</td>";
    $ret .= "<td class='right'>".$timeouts."</td>";
    $ret .= "<td class='right'>".sprintf("%.2f",(($games>0) ? $timeouts/$games : 0))."</td>";
    $ret .= "</tr>";
  }
  
  $ret .= "</table>";

  return $ret;
}


// 
function TableSOTGURLs($ss) {
  $query = sprintf("SELECT s.name AS series, t.name AS team, t.sotg_token AS token FROM uo_team AS t
    JOIN uo_series AS s on t.series=s.series_id
    WHERE s.season='%s'
    ORDER BY s.name, t.name",$ss);
  $tokens = DBQuery($query);

  $baseURL = rtrim(BASEURL,"/");

  $ret = "<table class='tdtools-table'>";
  $ret .= "<tr>";
  $ret .= "<th class='center'>Division</th>";
  $ret .= "<th class='center'>Team</th>";
  $ret .= "<th class='center'>SOTG URL</th>";
  $ret .= "<tr>";

  foreach ($tokens as $token) {
    $fullURL = empty($token['token']) ? "" : $baseURL."/sotg/?token=".$token['token'];
    $ret .= "<tr>";
    $ret .= "<td>".$token['series']."</td>";
    $ret .= "<td>".$token['team']."</td>";
    $ret .= "<td><a href='".$fullURL."'>".$fullURL."</a></td>";
    $ret .= "</tr>";
  }

  $ret .= "</table>";

  return $ret;
}


//
function GenerateSOTGTokens($ss,$filter="onlymissing") {
  
  if ($filter=="onlymissing") {
    $query = sprintf("UPDATE uo_team AS t
      JOIN uo_series AS s on t.series=s.series_id
      SET t.sotg_token=MD5(t.team_id+RAND())
      WHERE s.season='%s' AND t.sotg_token IS NULL",$ss);
    DBQuery($query);
    
    return "<p>Total number of new tokens generated: ".(int) DBAffectedRows()."</p>";

  } else {
    return "<p>Invalid filter.</p>";
  }
}


// ===========================
// Page code starts here
// ===========================

$season = GetString("season");

$title = _("TD Tools");
$html = "";

$html .= "<h1>".$title."</h1>\n";

  if (!empty($season) && isSeasonAdmin($season)) {

    // Tool 1: direct links to game edit pages based on game ref. #
    
    if(isset($_POST['game'])){

      $gameId = intval($_POST['game']);
      $linktype = $_POST['directlink'];
      if (!$gameId || !GamePool($gameId)) {
        $html .= "<p class='warning'>". _("Invalid game number.")."</p>";
      } else {
        switch($linktype) {
          case 'result':
            $linkto = "?view=user/addresult&game=$gameId";
            break;
          case 'players':
            $linkto = "?view=user/addplayerlists&game=$gameId";
            break;
          case 'scoresheet':
            $linkto = "?view=user/addscoresheet&game=$gameId";
            break;
          case 'spirit':
            $linkto = "?view=user/addspirit&game=$gameId";
            break;
          default:
            $linkto = "?view=admin/tdtools&season=$season";
            break;

        }
        header("Location: $linkto");
      }

    }

    $html .= "<hr />";

    $html .= "<h2>"._("Direct Links")."</h2>";
    $html .= "<div class='tdtools-box bg-td1'>";
    $html .= "<p>"._("Enter the game ref. # on the paper spirit/scoresheet for a direct link: ")."</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<input class='input' type='text' size='10' maxlength='10' name='game'/>";
    $html .= "<button class='button' type='submit' name='directlink' value='result'>"._("Result")."</button> ";
    $html .= "<button class='button' type='submit' name='directlink' value='players'>"._("Players")."</button> ";
    $html .= "<button class='button' type='submit' name='directlink' value='scoresheet'>"._("Scoresheet")."</button> ";
    $html .= "<button class='button' type='submit' name='directlink' value='spirit'>"._("Spirit")."</button> ";
    $html .= "</form></p>";

    $html .= "</div>";
    
    // Tool 2: Set of SOTG Tools
    
    $html .= "<hr/><h2>"._("SOTG Tools")."</h2>";

    // Tool 2aa (NEW!) generate and display tokens and URLs for online SOTG
    $html .= "<div class='tdtools-box bg-td2'>";
    $html .= "<p><strong>"._("URLs for teams (for entering SOTG scores online)")."</strong></p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='getsotgurls' value='all'>"._("Show URLs for all teams")."</button> ";
    $html .= "<button class='button' type='submit' name='generatesotgtokens' value='onlymissing'>"._("Generate Tokens")." ("._("only missing").")"."</button> ";
    $html .= "</form></p>";

    if(isset($_POST['getsotgurls'])){
      $html .= TableSOTGURLs($season);
    }

    if(isset($_POST['generatesotgtokens'])){
      $html .= GenerateSOTGTokens($season,$_POST['generatesotgtokens']);
    }

    $html .= "</div>";

    // Tool 2a: search for games missing spirit scores
    $html .= "<div class='tdtools-box bg-td2'>";
    $html .= "<p>"._("To search for played games that are <b>missing SOTG scores</b>, press one of these buttons: ")."</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='missingsotgpool' value='search'>"._("Search Missing by Pool")."</button> ";
    $html .= "<button class='button' type='submit' name='missingsotgdiv' value='search'>"._("Search Missing by Division")."</button> ";
    $html .= "</form></p>";

    // if search by pool
    if(isset($_POST['missingsotgpool'])){
      $allpools = array();
      $allpools = SeasonPools($season);
      $nonemissing = true;
      foreach ($allpools as $pool) {
        $games = SearchMissingSpiritByPool($pool['pool_id']);
        if(!empty($games)) {
          $nonemissing = false;
          $html .= "<p>"._("Pool").": <b>".$pool['poolname']."</b> (".$pool['seriesname'].")</p><table width='100%'>";
          foreach ($games as $game) {
            $visitorStyle = empty($game['homesotg']) ? 'color: red; font-weight: bold;' : '';
            $homeStyle = empty($game['visitorsotg']) ? 'color: red; font-weight: bold;' : '';
            $html .= "<tr><td class='right' style='width: 10%; $homeStyle'>".$game['home']."</td><td class='center' style='width: 2%'>".$game['homescore']."</td><td class='center' style='width: 1%'>-</td><td class='center' style='width: 2%'>".$game['visitorscore']."</td><td style='width: 10%; $visitorStyle'>".$game['visitor']."</td><td class='center' style='width: 20%'>".$game['time']."</td><td style='width: 10%'><a href='?view=user/addspirit&amp;game=".$game['game_id']."'>"._("Edit Spirit")."</a></td></tr>";
          }
          $html .= "</table>";
        }
      }
      if ($nonemissing) {
        $html .= "<p>"._("No played games found missing SOTG scores!")."</p>";
      }
    }

    // if search by division
    if(isset($_POST['missingsotgdiv'])){
      $allseries = array();
      $allseries = SeasonSeries($season);
      $nonemissing = true;
      foreach ($allseries as $series) {
        $games = SearchMissingSpiritBySeries(intval($series['series_id']));
        if(!empty($games)) {
          $nonemissing = false;
          $html .= "<p>"._("Division").": <b>".$series['name']."</b></p><table width='100%'>";
          foreach ($games as $game) {
            $visitorStyle = empty($game['homesotg']) ? 'color: red; font-weight: bold;' : '';
            $homeStyle = empty($game['visitorsotg']) ? 'color: red; font-weight: bold;' : '';
            $html .= "<tr><td style='width: 10%'>".$game['poolname']."</td><td class='right' style='width: 10%; $homeStyle'>".$game['home']."</td><td class='center' style='width: 2%'>".$game['homescore']."</td><td class='center' style='width: 1%'>-</td><td class='center' style='width: 2%'>".$game['visitorscore']."</td><td style='width: 10%; $visitorStyle'>".$game['visitor']."</td><td class='center' style='width: 20%'>".$game['time']."</td><td style='width: 10%'><a href='?view=user/addspirit&amp;game=".$game['game_id']."'>"._("Edit Spirit")."</a></td></tr>";
          }
          $html .= "</table>";
        }
      }
      if ($nonemissing) {
        $html .= "<p>"._("No played games found missing SOTG scores!")."</p>";
      }
    }
    
    $html .= "</div>";

    // Tool 2b: search for spirit scores of '0' or '4' in at least one category
    $html .= "<div class='tdtools-box bg-td2'>";
    $html .= "<p>"._("To search for teams that <b>received a '0' or '4'</b> in at least one category, use these buttons: ")."</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='sotgzeros' value='search'>"._("Search 0's")."</button> ";
    $html .= "<button class='button' type='submit' name='sotgfours' value='search'>"._("Search 4's")."</button> ";
    $html .= "</form></p>";

    if(isset($_POST['sotgzeros'])){
      $html .= TableSpiritSearchCat($season,0);
    }

    if(isset($_POST['sotgfours'])){
      $html .= TableSpiritSearchCat($season,4);
    }

    $html .= "</div>";

    // Tool 2c: search for spirit scores of 1 in the specified category given to the same team more than once
    $html .= "<div class='tdtools-box bg-td2'>";
    $html .= "<form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<p>"._("Search for teams that received a")."&nbsp;";
    $html .= "<input type='radio' name='sotgrepsval' value='0'>0&nbsp;</button>";
    $html .= "<input type='radio' name='sotgrepsval' value='1' checked>1&nbsp;</button>";
    $html .= "<input type='radio' name='sotgrepsval' value='2'>2&nbsp;</button>";
    $html .= "<input type='radio' name='sotgrepsval' value='3'>3&nbsp;</button>";
    $html .= "<input type='radio' name='sotgrepsval' value='4'>4&nbsp;</button>";
    $html .= "</p><p>"._("<b>more than once</b>, in this category").":&nbsp;";
    $html .= "<button class='button' type='submit' name='sotgreps' value='1'>"._("Rules")."</button> ";
    $html .= "<button class='button' type='submit' name='sotgreps' value='2'>"._("Fouls")."</button> ";
    $html .= "<button class='button' type='submit' name='sotgreps' value='3'>"._("Fair")."</button> ";
    $html .= "<button class='button' type='submit' name='sotgreps' value='4'>"._("Atittude")."</button> ";
    $html .= "<button class='button' type='submit' name='sotgreps' value='5'>"._("Comm")."</button> ";
    $html .= "</p></form>";

    if(isset($_POST['sotgreps'])){
      $html .= "<p>"._("Showing results for ");
      switch(intval($_POST['sotgreps'])) {
        case 1:
          $html .= _("Rules");
          break;
        case 2:
          $html .= _("Fouls");
          break;
        case 3:
          $html .= _("Fair");
          break;
        case 4:
          $html .= _("Attitude");
          break;
        case 5:
          $html .= _("Comm");
          break;
        default:
          break;
      }
      $html .= " = ".intval($_POST['sotgrepsval'])."</p>";
      $html .= TableSpiritSearchCatReps($season,intval($_POST['sotgreps']),intval($_POST['sotgrepsval']));
    }
    
    $html .= "</div>";

    // Tool 2d: search for spirit scores (total) higher or lower than a certain value
    $html .= "<div class='tdtools-box bg-td2'>";
    $html .= "<p>"._("To search for teams that received high/low <b>total scores</b> enter a threshold and press the appropriate button:")."</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='sotgop' value='lower'>"._("Lower than")."</button> ";
    $html .= "<input class='input' type='text' size='10' maxlength='10' name='sotgth'/>";
    $html .= "<button class='button' type='submit' name='sotgop' value='higher'>"._("Higher than")."</button> ";
    $html .= "</form></p>";

    if(isset($_POST['sotgth'])){
      $sotgth = intval($_POST['sotgth']);
      $sotgop = $_POST['sotgop'];
      if (!$sotgth || ($sotgth<1) || ($sotgth>20)) {
        $html .= "<p class='warning'>". _("Invalid threshold! Please use a number between 1 and 20.")."</p>";
      } else {
        switch($sotgop) {
          case 'higher':
            $html .= TableSpiritSearchTotal($season,$sotgth,true);
            break;
          case 'lower':
            $html .= TableSpiritSearchTotal($season,$sotgth,false);
            break;
          default:
            break;

        }
      }
    }
    $html .= "</div>";

    // Tool 2e: search for spirit scores (average) higher or lower than a certain value in any category
    $html .= "<div class='tdtools-box bg-td2'>";
    $html .= "<p>"._("To search for teams that have a low/high <b>average score</b> in any category enter a threshold and press the appropriate button:")."</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='sotgAvgOp' value='lower'>"._("Lower than")."</button> ";
    $html .= "<input class='input' type='text' size='10' maxlength='10' name='sotgAvgTh'/>";
    $html .= "<button class='button' type='submit' name='sotgAvgOp' value='higher'>"._("Higher than")."</button> ";
    $html .= "</form></p>";

    if(isset($_POST['sotgAvgTh'])){
      $sotgAvgTh = floatval(strtr($_POST['sotgAvgTh'],',','.'));
      $sotgAvgOp = $_POST['sotgAvgOp'];
      if (!$sotgAvgTh || ($sotgAvgTh<0.1) || ($sotgAvgTh>3.9)) {
        $html .= "<p class='warning'>". _("Invalid threshold! Please use a number between 0.1 and 3.9.")."</p>";
      } else {
        switch($sotgAvgOp) {
          case 'higher':
            $html .= TableSpiritSearchCatAvg($season,$sotgAvgTh,true);
            break;
          case 'lower':
            $html .= TableSpiritSearchCatAvg($season,$sotgAvgTh,false);
            break;
          default:
            break;

        }
      }
    }
    $html .= "</div>";

    // Tool 2f: search for spirit comments
    $html .= "<div class='tdtools-box bg-td2' id='sotgComments'>";
    $html .= "<p>"._("Press the button to search for spirit comments")."</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season#sotgComments'>";
    $html .= "<button class='button' type='submit' name='sotgComments' value='sotgComments'>"._("Show Comments")."</button> ";
    $html .= "</form></p>";

    if (isset($_POST['sotgComments'])) {
      $html .= TableSpiritSearchComments($season);
    }
    $html .= "</div>";


    $html .= "<hr />";
    
    // Tool 3: timeout stats
	  $html .= "<h2>"._("Timeout Stats")."</h2>";
    $html .= "<div class='tdtools-box bg-td3'>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='timeouts' value='search'>"._("Show Timeout Stats")."</button> ";
    $html .= "</form></p>";

    if(isset($_POST['timeouts'])){
      $html .= TableTimeoutStats();
    }

    $html .= "</div>";

    $html .= "<hr />";

    // Tool 4: search for missing and duplicate numbers on team rosters
	  $html .= "<h2>"._("Missing and duplicate shirt numbers")."</h2>";
    $html .= "<div class='tdtools-box bg-td4'>";
    $html .= "<p>"._("To search for players with the same shirt number on the same team, or who don't have a shirt number assigned press the appropriate button: ")."</p>";
    $html .= "<p><form method='POST' action='?view=admin/tdtools&amp;season=$season'>";
    $html .= "<button class='button' type='submit' name='dups' value='search'>"._("Duplicates")."</button> ";
    $html .= "<button class='button' type='submit' name='missing' value='search'>"._("Missing")."</button> ";
    $html .= "</form></p>";

    if(isset($_POST['dups'])){
      $html .= TableDuplicateNumbers($season);
    }

    if(isset($_POST['missing'])){
      $html .= TableMissingNumbers($season);
    }

    $html .= "</div>";

  } else {
    $html .= "<p>"._("Insufficient user rights")."</p>";
  }

showPage($title, $html);
?>
