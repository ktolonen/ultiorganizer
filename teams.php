<?php
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/statistical.functions.php';

$LAYOUT_ID = TEAMS;
$title = _("Teams");
$html = "";
$list = "allteams";

if(!empty($_GET["list"])) {
$list = $_GET["list"];
}

if(!empty($_GET["Season"])){
	$season = mysql_real_escape_string($_GET["Season"]);
}else{	
	$season = CurrentSeason();
}
$seasonInfo = SeasonInfo($season);
$series = SeasonSeries($season, true);

//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();
$html .= "[<a href='?view=teams&amp;Season=$season&amp;list=allteams'>"._("By division")."</a>]";
$html .= "&nbsp;&nbsp;";	
$html .= "[<a href='?view=teams&amp;Season=$season&amp;list=bypool'>"._("By pool")."</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=teams&amp;Season=$season&amp;list=byseeding'>"._("By seeding")."</a>]";
$html .= "&nbsp;&nbsp;";
$html .= "[<a href='?view=eventstatus&amp;Season=$season'>"._("Final standings")."</a>]";
$html .= "&nbsp;&nbsp;";

if(!empty($season)){
	$html .= "<h1>$title</h1>\n";
}else{
	$html .= "<p>"._("No teams")."</p>\n";
}

$cols = 2;
if (!intval($seasonInfo['isnationalteams'])){
	$cols++;
}
if(intval($seasonInfo['isinternational'])){
	$cols++;
}
if($list=="byseeding"){
	$cols++;
}
$isstatdata = IsStatsDataAvailable();

if($list=="allteams" || $list=="byseeding"){

	foreach($series as $row){
	
		$html .= "<table border='0' cellspacing='0' cellpadding='2' width='100%'>\n";
		$html .= "<tr>";
		$html .= "<th colspan='$cols'>";
		$html .=utf8entities(U_($row['name']))."</th>\n";
		$html .= "</tr>\n";
		if($list=="byseeding"){
			$teams = SeriesTeams($row['series_id'], true);
		}else{
			$teams = SeriesTeams($row['series_id']);
		}
		$i=0;	
		foreach ($teams as $team) {
			$i++;
			$html .= "<tr>";
			if($list=="byseeding"){
			  if(!empty($team['rank'])){
				$html .= "<td style='width:2px'>".$team['rank'].".</td>";
			  }else{
			    $html .= "<td style='width:2px'>-</td>";
			  }
			}
			if(intval($seasonInfo['isnationalteams'])){
				$html .= "<td style='width:200px'><a href='?view=teamcard&amp;Team=".$team['team_id']."'>".utf8entities(U_($team['name']))."</a></td>";
			} else {
				$html .= "<td style='width:150px'><a href='?view=teamcard&amp;Team=".$team['team_id']."'>".utf8entities($team['name'])."</a></td>";
				$html .= "<td style='width:150px'><a href='?view=clubcard&amp;Club=". $team['club']."'>".utf8entities($team['clubname'])."</a></td>";
			}
			if(intval($seasonInfo['isinternational'])){
				$html .= "<td style='width:150px'>";

				if(!empty($team['flagfile'])){
				  $html .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/>&nbsp;";
				}
				if(!empty($team['countryname'])){ 
				  $html .= "<a href='?view=countrycard&amp;Country=". $team['country']."'>".utf8entities(_($team['countryname']))."</a>";
				}
				$html .= "</td>";
			}
			
			$html .= "<td class='right' style='white-space: nowrap;width:15%'>\n";
			if($isstatdata){
				$html .= "<a href='?view=playerlist&amp;Team=".$team['team_id']."'>"._("Roster")."</a>";
				$html .= "&nbsp;&nbsp;";
			}
			$html .= "<a href='?view=scorestatus&amp;Team=".$team['team_id']."'>"._("Scoreboard")."</a>";
			
			$html .= "&nbsp;&nbsp;";
			$html .= "<a href='?view=games&amp;Team=".$team['team_id']."'>"._("Games")."</a>";
			if (mysql_num_rows( mysql_query("SHOW TABLES LIKE 'uo_defense'")))
			{
			  $html .= "&nbsp;&nbsp;";
			  $html .= "<a href='?view=defensestatus&amp;Team=".$team['team_id']."'>"._("Defenseboard")."</a>";
			}
			
			$html .= "</td>";
			$html .= "</tr>\n";
		}
		$html .= "</table>\n";
	}
} elseif ($list=="bypool") {

	foreach($series as $row){
		$html .= "<h2>".utf8entities(U_($row['name']))."</h2>\n";
		
		$pools = SeriesPools($row['series_id'], true);
		if(!count($pools)){
			$html .= "<p>"._("Pools not yet created")."</p>";
			continue;
		}
		foreach ($pools as $pool) {
			$html .= "<table border='0' cellspacing='0' cellpadding='2' width='100%'>\n";
			$html .= "<tr>";
			$html .= "<th colspan='".($cols-1)."'>".utf8entities(U_(PoolSeriesName($pool['pool_id'])).", ". U_($pool['name']))."</th><th class='right'>"._("Scoreboard")."</th>\n";
			$html .= "</tr>\n";
			if($pool['type']==2){
				//find out sub pools
				$pools = array();
				$pools[] = $pool['pool_id'];
				$followers = PoolFollowersArray($pool['pool_id']);
				$pools = array_merge($pools,$followers);
				$playoffpools = implode(",",$pools);
			}
			$teams = PoolTeams($pool['pool_id']);

			foreach($teams as $team){
				$html .= "<tr>";
				if(intval($seasonInfo['isnationalteams'])){
					$html .= "<td style='width:150px'><a href='?view=teamcard&amp;Team=".$team['team_id']."'>".utf8entities(U_($team['name']))."</a></td>";
				} else {
					$html .= "<td style='width:150px'><a href='?view=teamcard&amp;Team=".$team['team_id']."'>".utf8entities($team['name'])."</a></td>";
					$html .= "<td style='width:150px'><a href='?view=clubcard&amp;Club=". $team['club']."'>".utf8entities($team['clubname'])."</a></td>";
				}
				if(intval($seasonInfo['isinternational'])){
					$html .= "<td style='width:150px'>";
					if(!empty($team['flagfile'])){
					  $html .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/>&nbsp;";
					}
					if(!empty($team['countryname'])){ 
					  $html .= "<a href='?view=countrycard&amp;Country=". $team['country']."'>".utf8entities(_($team['countryname']))."</a>";
					}
					$html .= "</td>";
				}
				
				$html .= "<td class='right' style='white-space: nowrap;width:15%'>\n";
				$html .= "<a href='?view=games&amp;Team=".$team['team_id']."'>"._("Games")."</a>";
				$html .= "&nbsp;&nbsp;";
		
				if($pool['type']==2){
					$html .= "<a href='?view=scorestatus&amp;Team=".$team['team_id']."&amp;Pools=".$playoffpools."'>"._("Pool")."</a>";
				}else{
					$html .= "<a href='?view=scorestatus&amp;Team=".$team['team_id']."&amp;Pool=". $pool['pool_id'] ."'>"._("Pool")."</a>";
				}
				$html .= "&nbsp;&nbsp;";	
				
				$html .= "<a href='?view=scorestatus&amp;Team=".$team['team_id']."'>"._("Division")."</a></td>";
				$html .= "</tr>\n";
			}
			$html .= "</table>\n";
		}
	}
}

echo $html;
contentEnd();
pageEnd();
?>
