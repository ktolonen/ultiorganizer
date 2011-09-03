<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';

$LAYOUT_ID = SCOREBOARD;

$poolId = 0;
$poolIds = array();
$seriesId = 0;
$teamId = 0;
$sort="total";

$title = _("Scoreboard");

if(!empty($_GET["Pool"])) {
	$poolId = intval($_GET["Pool"]);
	$title = $title.": ".utf8entities(U_(PoolName($poolId)));
}
if(!empty($_GET["Pools"])) {
	$poolIds = explode(",",$_GET["Pools"]);
	$title = $title.": ".utf8entities(U_(PoolName($poolId)));
}
if(!empty($_GET["Series"])) {
	$seriesId = intval($_GET["Series"]);
	$title = $title.": ".utf8entities(U_(SeriesName($seriesId)));
}
if(!empty($_GET["Team"])) {
	$teamId = intval($_GET["Team"]);
	$title = $title.": ".utf8entities(TeamName($teamId));
}
if(!empty($_GET["Sort"])){
	$sort = $_GET["Sort"];
}
//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();

echo "<h1>"._("Scoreboard")."</h1>\n";

echo "<table style='width:100%' cellpadding='1' border='1'>";

$viewUrl="?view=scorestatus&amp;";
if($teamId){$viewUrl.= "Team=$teamId&amp;";}
if($poolId){$viewUrl.= "Pool=$poolId&amp;";}
if(count($poolIds)){$viewUrl.= "Pools=".implode(",",$poolIds)."&amp;";}
if($seriesId){$viewUrl.= "Series=$seriesId&amp;";}

echo "<tr>\n";
	echo "<th style='width:5%'>#</th>";
if($sort == "name") 
	echo "<th style='width:30%'>"._("Player")."</th>";
else
	echo "<th style='width:30%'><a class='thsort' href='".$viewUrl."Sort=name'>"._("Player")."</a></th>";
	
if($sort == "team") 
	echo "<th style='width:25%'><b>"._("Team")."</b></th>";
else
	echo "<th style='width:25%'><a class='thsort' href='".$viewUrl."Sort=team'>"._("Team")."</a></th>";
	
if($sort == "games") 
	echo "<th class='center' style='width:8%'><b>"._("Games")."</b></th>";
else
	echo "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."Sort=games'>"._("Games")."</a></th>";
				
if($sort == "pass") 
	echo "<th class='center' style='width:8%'><b>"._("Assists")."</b></th>";
else
	echo "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."Sort=pass'>"._("Assists")."</a></th>";
		
if($sort == "goal") 
	echo "<th class='center' style='width:8%'><b>"._("Goals")."</b></th>";
else
	echo "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."Sort=goal'>"._("Goals")."</a></th>";


if($sort == "callahan") 
	echo "<th class='center' style='width:8%'><b>"._("Cal.")."</b></th>";
else
	echo "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."Sort=callahan'>"._("Cal.")."</a></th>";

	
if($sort == "total") 
	echo "<th class='center' style='width:8%'><b>"._("Tot.")."</b></th>";
else
	echo "<th class='center' style='width:8%'><a class='thsort' href='".$viewUrl."Sort=total'>"._("Tot.")."</a></th>";
	
echo "</tr>";

if($teamId){
	if(count($poolIds)){
		$scores = TeamScoreBoard($teamId, $poolIds, $sort, 0);
	}else{
		$scores = TeamScoreBoard($teamId, $poolId, $sort, 0);
	}
}elseif($poolId){
	$scores = PoolScoreBoard($poolId, $sort, 0);
}elseif(count($poolIds)){
	$scores = PoolsScoreBoard($poolIds, $sort, 0);
}elseif($seriesId){
	$scores = SeriesScoreBoard($seriesId, $sort, 0);
}
$i=1;
while($row = mysql_fetch_assoc($scores))
	{
	echo "<tr>";
	echo "<td>".$i++."</td>";
	if($sort == "name") {
		echo "<td class='highlight'><a href='?view=playercard&amp;Series=$poolId&amp;Player=". $row['player_id']."'>";
		echo utf8entities($row['firstname']." ".$row['lastname']);
		echo "</a></td>";
	}else{
		echo "<td><a href='?view=playercard&amp;Series=$poolId&amp;Player=". $row['player_id']."'>";
		echo utf8entities($row['firstname']." ".$row['lastname']);
		echo "</a></td>";
	}
	if($sort == "team") 
		echo "<td class='highlight'>".utf8entities($row['teamname'])."</td>";
	else
		echo "<td>".utf8entities($row['teamname'])."</td>";
		
	if($sort == "games") 
		echo "<td class='center highlight'>".intval($row['games'])."</td>";
	else
		echo "<td class='center'>".intval($row['games'])."</td>";
					
	if($sort == "pass") 
		echo "<td class='center highlight'>".intval($row['fedin'])."</td>";
	else
		echo "<td class='center'>".intval($row['fedin'])."</td>";
	
	if($sort == "goal"){ 
		echo "<td class='center highlight'>".intval($row['done'])."</td>";
	}else{
		echo "<td class='center'>".intval($row['done'])."</td>";
	}

	if($sort == "callahan") 
		echo "<td class='center highlight'>".intval($row['callahan'])."</td>";
	else
		echo "<td class='center'>".intval($row['callahan'])."</td>";

		
	if($sort == "total") 
		echo "<td class='center highlight'>".intval($row['total'])."</td></tr>";
	else
		echo "<td class='center'>".intval($row['total'])."</td></tr>";
	}

echo "</table>";
contentEnd();
pageEnd();
?>
