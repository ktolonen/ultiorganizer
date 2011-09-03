<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

$LAYOUT_ID = SERIESTATUS;

$title = _("Statistics")." ";
$viewUrl="?view=seriesstatus";
$sort="winavg";

if (!empty($_GET['Series'])) {
	$viewUrl .= "&amp;Series=".$_GET['Series'];
	$seriesinfo = SeriesInfo($_GET['Series']);
	$seasoninfo = SeasonInfo($seriesinfo['season']);
	$title.= U_($seriesinfo['name']);
}

if(!empty($_GET["Sort"])){
	$sort = $_GET["Sort"];
}

//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();

$teamstats = array();
$allteams = array();
$teams = SeriesTeams($seriesinfo['series_id']);

foreach ($teams as $team) {
	$stats = TeamStats($team['team_id']);
	$spiritstats = TeamSpiritStats($team['team_id']);
	$points = TeamPoints($team['team_id']);

	$teamstats['name']=$team['name'];
	$teamstats['team_id']=$team['team_id'];
	$teamstats['seed']=$team['rank'];
	$teamstats['flagfile']=$team['flagfile'];
	$teamstats['pool']=$team['poolname'];
	$teamstats['wins']=$stats['wins'];
	$teamstats['games']=$stats['games'];
	$teamstats['for']=$points['scores'];
	$teamstats['against']=$points['against'];
	$teamstats['losses']=$teamstats['games']-$teamstats['wins'];
	$teamstats['diff']=$teamstats['for']-$teamstats['against'];
	$teamstats['spirit']=$points['spirit'];
	$teamstats['spiritavg']=number_format(SafeDivide(intval($points['spirit']), intval($spiritstats['games'])),1);
	$teamstats['winavg']=number_format(SafeDivide(intval($stats['wins']), intval($stats['games']))*100,1);

	$allteams[] = $teamstats;
}

echo "<h2>"._("Division statistics:")." ".utf8entities($seriesinfo['name'])."</h2>";	
$style = "";

echo "<table border='1' style='width:100%'>\n";
echo "<tr>";

if($sort == "name" || $sort == "pool" || $sort == "against" || $sort == "seed") {
	usort($allteams, create_function('$a,$b','return $a[\''.$sort.'\']==$b[\''.$sort.'\']?0:($a[\''.$sort.'\']<$b[\''.$sort.'\']?-1:1);'));
}else{
	usort($allteams, create_function('$a,$b','return $a[\''.$sort.'\']==$b[\''.$sort.'\']?0:($a[\''.$sort.'\']>$b[\''.$sort.'\']?-1:1);'));
}

if($sort == "name") {
	echo "<th style='width:180px'>"._("Team")."</th>";
}else{
	echo "<th style='width:180px'><a class='thsort' href='".$viewUrl."&amp;Sort=name'>"._("Team")."</a></th>";
}	

/*
if($sort == "pool") {
	echo "<th style='width:200px'>"._("Pool")."</th>";
}else{
	echo "<th style='width:200px'><a href='".$viewUrl."&amp;Sort=pool'>"._("Pool")."</a></th>";
}	
*/

if($sort == "seed") {
	echo "<th class='center'>"._("Seeding")."</th>";
}else{
	echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=seed'>"._("Seeding")."</a></th>";
}	

if($sort == "games") {
	echo "<th class='center'>"._("Games")."</th>";
}else{
	echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=games'>"._("Games")."</a></th>";
}		

if($sort == "wins") {
	echo "<th class='center'>"._("Wins")."</th>";
}else{
	echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=wins'>"._("Wins")."</a></th>";
}		

if($sort == "losses") {
	echo "<th class='center'>"._("Losses")."</th>";
}else{
	echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=losses'>"._("Losses")."</a></th>";
}		

if($sort == "for") {
	echo "<th class='center'>"._("Goals for")."</th>";
}else{
	echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=for'>"._("Goals for")."</a></th>";
}		

if($sort == "against") {
	echo "<th class='center'>"._("Goals against")."</th>";
}else{
	echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=against'>"._("Goals against")."</a></th>";
}		

if($sort == "diff") {
	echo "<th class='center'>"._("Goals diff")."</th>";
}else{
	echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=diff'>"._("Goals diff")."</a></th>";
}	

if($sort == "winavg") {
	echo "<th class='center'>"._("Win-%")."</th>";
}else{
	echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=winavg'>"._("Win-%")."</a></th>";
}	
if($seasoninfo['spiritpoints'] && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seriesinfo['season']))){
	if($sort == "spirit") {
		echo "<th class='center'>"._("Spirit points")."</th>";
	}else{
		echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=spirit'>"._("Spirit points")."</a></th>";
	}	
	
	if($sort == "spiritavg") {
		echo "<th class='center'>"._("Spirit points")."</th>";
	}else{
		echo "<th class='center'><a class='thsort' href='".$viewUrl."&amp;Sort=spiritavg'>"._("Spirit / Game")."</a></th>";
	}	
}

echo "</tr>\n";

foreach($allteams as $stats){
	echo "<tr>";
	$flag="";
	if(intval($seasoninfo['isinternational'])){
		$flag = "<img height='10' src='images/flags/tiny/".$stats['flagfile']."' alt=''/> ";
	}
	if($sort == "name") {
		echo "<td class='highlight'>$flag<a href='?view=teamcard&amp;Team=".$stats['team_id']."'>",utf8entities(U_($stats['name'])),"</a></td>";
	}else{
		echo "<td>$flag<a href='?view=teamcard&amp;Team=".$stats['team_id']."'>",utf8entities(U_($stats['name'])),"</a></td>";
	}
/*
	if($sort == "pool") {
		echo "<td class='highlight'>",utf8entities(U_($stats['pool'])),"</td>";
	}else{
		echo "<td>",utf8entities(U_($stats['pool'])),"</td>";
	}
*/
	if($sort == "seed") {
		echo "<td class='center highlight'>".intval($stats['seed']).".</td>";
	}else{
		echo "<td class='center'>".intval($stats['seed']).".</td>";
	}
	
	if($sort == "games") {
		echo "<td class='center highlight'>".intval($stats['games'])."</td>";
	}else{
		echo "<td class='center'>".intval($stats['games'])."</td>";
	}
	if($sort == "wins") {
		echo"<td class='center highlight'>".intval($stats['wins'])."</td>";
	}else{
		echo"<td class='center'>".intval($stats['wins'])."</td>";
	}
	if($sort == "losses") {
		echo "<td class='center highlight'>",intval($stats['losses']),"</td>";
	}else{
		echo "<td class='center'>",intval($stats['losses']),"</td>";
	}
	if($sort == "for") {
		echo "<td class='center highlight'>".intval($stats['for'])."</td>";
	}else{
		echo "<td class='center'>".intval($stats['for'])."</td>";
	}
	if($sort == "against") {
		echo "<td class='center highlight'>".intval($stats['against'])."</td>";
	}else{
		echo "<td class='center'>".intval($stats['against'])."</td>";
	}
	if($sort == "diff") {
		echo "<td class='center highlight'>",intval($stats['diff']),"</td>";
	}else{
		echo "<td class='center'>",intval($stats['diff']),"</td>";
	}

	if($sort == "winavg") {
		echo "<td class='center highlight'>".$stats['winavg']."%</td>";
	}else{
		echo "<td class='center'>".$stats['winavg']."%</td>";
	}
	
	if($seasoninfo['spiritpoints'] && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seriesinfo['season']))){
		if($sort == "spirit") {
			echo "<td class='center highlight'>".$stats['spirit']."</td>";
		}else{
			echo "<td class='center'>".$stats['spirit']."</td>";
		}
		if($sort == "spiritavg") {
			echo "<td class='center highlight'>".$stats['spiritavg']."</td>";
		}else{
			echo "<td class='center'>".$stats['spiritavg']."</td>";
		}
	}
	
	echo "</tr>\n";
	}
echo "</table>\n";
echo "<a href='?view=poolstatus&amp;Series=".$seriesinfo['series_id']."'>"._("Show all pools")."</a>";
echo "<h2>"._("Scoreboard leaders")."</h2>\n";
echo "<table cellspacing='0' border='0' width='100%'>\n";
echo "<tr><th style='width:200px'>"._("Player")."</th><th style='width:200px'>"._("Team")."</th><th class='center'>"._("Games")."</th>
<th class='center'>"._("Assists")."</th><th class='center'>"._("Goals")."</th><th class='center'>"._("Tot.")."</th></tr>\n";

$scores = SeriesScoreBoard($seriesinfo['series_id'],"total", 10);
while($row = mysql_fetch_assoc($scores))
	{
	echo "<tr><td>". utf8entities($row['firstname']." ".$row['lastname'])."</td>";
	echo "<td>".utf8entities($row['teamname'])."</td>";
	echo "<td class='center'>".intval($row['games'])."</td>";
	echo "<td class='center'>".intval($row['fedin'])."</td>";
	echo "<td class='center'>".intval($row['done'])."</td>";
	echo "<td class='center'>".intval($row['total'])."</td></tr>\n";
	}

echo "</table>";
echo "<a href='?view=scorestatus&amp;Series=".$seriesinfo['series_id']."'>"._("Scoreboard")."</a>";

contentEnd();
pageEnd();
?>
