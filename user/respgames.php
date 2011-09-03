<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/timetable.functions.php';

$LAYOUT_ID = RESPONSEGAMES;

$title = _("Game responsibilities");

$group = "all";

if(!empty($_GET["group"])) {
	$group  = $_GET["group"];
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
$help = "<p>"._("Feed in the data for your game responsibilities").":</p>
	<ol>
		<li> "._("Result")." "._(" ")." </li>
		<li> "._("Players in the game")." </li>
		<li> "._("Game score sheet")." </li>
	</ol>
	<p>"._("Check the game play after feeding in the score sheet").".</p>";

onPageHelpAvailable($help);
//content

if (isset($_GET['Season'])) {
	$season = $_GET['Season'];
} else {
	$season = CurrentSeason();
}

$groups = TimetableGrouping($season, "season", "all");
if(count($groups>1)){
	echo "<p>\n";	
	foreach($groups as $grouptmp){
		if($group==$grouptmp['reservationgroup']){
			echo "<a class='groupinglink' href='?view=user/respgames&amp;Season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."'><span class='selgroupinglink'>".U_($grouptmp['reservationgroup'])."</span></a>";
		}else{
			echo "<a class='groupinglink' href='?view=user/respgames&amp;Season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."'>".U_($grouptmp['reservationgroup'])."</a>";
		}
		echo "&nbsp;&nbsp;&nbsp; ";
	}
	if($group=="all"){
		echo "<a class='groupinglink' href='?view=user/respgames&amp;Season=$season&amp;group=all'><span class='selgroupinglink'>"._("All")."</span></a>";
	}else{
		echo "<a class='groupinglink' href='?view=user/respgames&amp;Season=$season&amp;group=all'>"._("All")."</a>";
	}
	echo "</p>\n";	
}

$respGameArray = GameResponsibilityArray($season);

if(count($respGameArray) == 0) {
	echo "\n<p>"._("No game responsibilities").".</p>\n";	
}else{
	echo "<noscript> 
	<p><b>"._("Feeding in the score sheet requires JavaScript. Please enable JavaScript to continue!")."</b></p>
	</noscript>";	
}

$first = true;
foreach ($respGameArray as $tournament => $resArray) {
	if($group != "all" && $tournament != $group){
		continue;
	}
	if($first) {
		$first = false;
	} else {
		echo "<hr/>\n";
	}
	if($group == "all"){
		echo "<h1>". utf8entities($tournament) ."</h1>\n";				
	}
	
	foreach($resArray as $resId => $gameArray) {		
		echo "<table cellpadding='2' border='0' style='width:100%'>";
		echo "<tr><th class='left' colspan='8'>";
		echo DefWeekDateFormat($gameArray['starttime']) ." ";
		if($resId)
			echo "<a class='thlink' href='?view=reservationinfo&amp;Reservation=".$resId."'>". $gameArray['locationname'] ."</a>";
		else
			echo _("No location");
		echo "</th>\n<th class='right' colspan='2'>";
		if($resId)
			echo "<a class='thlink' href='?view=user/pdfscoresheet&amp;Reservation=".$resId."&amp;Season=".$season."'>"._("Print scoresheets")."</a>";
		echo "</th></tr>\n";
		
		foreach ($gameArray as $gameId => $game) {
			if (!is_numeric($gameId)) {
				continue;
			} 
			echo "<tr><td style='width:6%'>", DefHourFormat($game['time']) ,"</td>";
			if($game['hometeam'] && $game['visitorteam']){
				echo "<td style='width:20%'>". utf8entities($game['hometeamname']) ."</td><td style='width:2%'>-</td><td style='width:20%'>". utf8entities($game['visitorteamname']) ."</td>";
			}else{
				echo "<td style='width:20%'>". utf8entities($game['phometeamname']) ."</td><td style='width:2%'>-</td><td style='width:20%'>". utf8entities($game['pvisitorteamname']) ."</td>";
			}
			echo "<td style='width:5%'>". intval($game['homescore']) ."</td><td style='width:2%'>-</td><td style='width:5%'>". intval($game['visitorscore']) ."</td>";
			if (intval($game['goals'])>0) {
				echo "<td style='width:15%'><a href='?view=gameplay&amp;Game=". $game['game_id'] ."'>"._("Game play")."</a></td>";
			} else {
				echo "<td style='width:15%'>es</td>";
			}
			if($game['hometeam'] && $game['visitorteam']){
				echo "<td style='white-space: nowrap' class='right'><a href='?view=user/addresult&amp;Game=".$gameId."'>"._("Result")."</a> | ";
				echo "<a href='?view=user/addplayerlists&amp;Game=".$gameId."'>"._("Players")."</a> | ";
				echo "<a href='?view=user/addscoresheet&amp;Game=$gameId'>"._("Score sheet")."</a></td>";
			}
			echo "</tr>";
		}
		echo "</table>";
		
	}
}

//common end
contentEnd();
pageEnd();
?>
