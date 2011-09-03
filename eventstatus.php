<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

$LAYOUT_ID = SERIESTATUS;

$title = _("Final standings")." ";
if (!empty($_GET['Season'])) {
	$seasoninfo = SeasonInfo($_GET['Season']);
	$title.= U_($seasoninfo['name']);	
}

//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();
echo "<h1>"._("Final standings")."</h1>";

$htmlseries = array();
$maxplacements=0;

$series = SeasonSeries($seasoninfo['season_id'], true);
foreach($series as $ser){
	$ppools = SeriesPlacementPoolIds($ser['series_id']);
	$htmlteams = array();
	foreach ($ppools as $ppool){
		$teams = PoolTeams($ppool['pool_id']);
		$steams = PoolSchedulingTeams($ppool['pool_id']);
		if(count($teams) < count($steams)){
		  $totalteams = count($steams);
		}else{
		  $totalteams = count($teams);
		}
		
		for($i=1;$i<=$totalteams;$i++){
			$moved = PoolMoveExist($ppool['pool_id'], $i);
			if(!$moved){
				$team = PoolTeamFromStandings($ppool['pool_id'], $i);
				$gamesleft = TeamPoolGamesLeft($team['team_id'], $ppool['pool_id']);
				if($ppool['played'] || ($ppool['type']==2 && mysql_num_rows($gamesleft)==0)){
					$team = PoolTeamFromStandings($ppool['pool_id'], $i);
					$html = "";
					if(intval($seasoninfo['isinternational'])){
						$html .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/> ";
					}
					$html .= "<a href='?view=teamcard&amp;Team=".$team['team_id']."'>".utf8entities($team['name'])."</a>";
					$htmlteams[] = $html;
				}else{
					$htmlteams[]= "&nbsp;";
				}
			}
		}					
	}
	$htmlseries[] = $htmlteams;
}

echo "<table cellpadding='2' style='width:100%;'>\n";
echo "<tr>";
echo "<th style='width:20%;'>". _("Placement"). "</th>";
foreach($series as $ser){
	echo "<th style='width:".(80/count($series))."%;'>". utf8entities(U_($ser['name'])) ."</th>";
	$maxplacements = max(count(SeriesTeams($ser['series_id'])), $maxplacements);
}
echo "</tr>\n";
for($i=0;$i<$maxplacements;$i++){
	
	if($i<3){
		echo "<tr style='font-weight:bold;border-bottom-style:dashed;border-bottom-width:1px;border-bottom-color:#E0E0E0;'>";
	}else{
		echo "<tr style='border-bottom-style:dashed;border-bottom-width:1px;border-bottom-color:#E0E0E0;'>";
	}
	if($i==0){
		echo "<td>"._("Gold")."</td>";
	}elseif($i==1){
		echo "<td>"._("Silver")."</td>";
	}elseif($i==2){
		echo "<td>"._("Bronze")."</td>";
	}elseif($i>2){
		echo "<td>".ordinal($i+1)."</td>";
	}

	for($j=0;$j<count($series);$j++){
		echo "<td>";
		if(!empty($htmlseries[$j][$i])){
			echo $htmlseries[$j][$i];
		}else{
			echo "&nbsp;";
		}
		echo "</td>";
	}
	echo "</tr>\n";
}
echo "</table>\n";

contentEnd();
pageEnd();
?>
