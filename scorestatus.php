<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'lib/team.functions.php';
include_once 'builder.php';

$LAYOUT_ID = SCOREBOARD;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

echo "<h1>"._("Pistep&ouml;rssi")."</h1>\n";

$seriesId=0;
$teamId = 0;
$sort="total";

OpenConnection();
if(!empty($_GET["Series"]))
	$seriesId = intval($_GET["Series"]);

if(!empty($_GET["Team"]))
	$teamId = intval($_GET["Team"]);

if(!empty($_GET["Sort"]))
	$sort = $_GET["Sort"];

echo "<table cellspacing='1px' cellpadding='1px' border='1'>";

$baseUrl="scorestatus.php?";
$baseUrl.= "Team=$teamId&amp;";
$baseUrl.= "Series=$seriesId&amp;";
	
echo "<tr><th>"._("Pelaaja")."</th>";

if($sort == "team") 
	echo "<th><b>"._("Joukkue")."</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=team'>"._("Joukkue")."</a></th>";
	
if($sort == "games") 
	echo "<th><b>"._("Pelej&auml;")."</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=games'>"._("Pelej&auml;")."</a></th>";
				
if($sort == "pass") 
	echo "<th><b>"._("Sy&ouml;t&ouml;t")."</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=pass'>"._("Sy&ouml;t&ouml;t")."</a></th>";
		
if($sort == "goal") 
	echo "<th><b>"._("Maalit")."</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=goal'>"._("Maalit")."</a></th>";
		
if($sort == "total") 
	echo "<th><b>"._("Yht.")."</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=total'>"._("Yht.")."</a></th>";
	
echo "</tr>";

if($teamId)
	$scores = TeamScoreBoard($teamId, $seriesId, $sort, 0);
else		
	$scores = SerieScoreBoard($seriesId, $sort, 0);

while($row = mysql_fetch_assoc($scores))
	{
	echo "<tr><td><a href='playercard.php?Series=$seriesId&amp;Player=". $row['pelaaja_id']."'>";
	echo htmlentities($row['enimi']." ".$row['snimi']);
	echo "</a></td>";
	if($sort == "team") 
		echo "<td class='highlight'>".htmlentities($row['jnimi'])."</td>";
	else
		echo "<td>".htmlentities($row['jnimi'])."</td>";
		
	if($sort == "games") 
		echo "<td align='center' class='highlight'>".intval($row['peleja'])."</td>";
	else
		echo "<td align='center'>".intval($row['peleja'])."</td>";
					
	if($sort == "pass") 
		echo "<td align='center' class='highlight'>".intval($row['syotetty'])."</td>";
	else
		echo "<td align='center'>".intval($row['syotetty'])."</td>";
			
	if($sort == "goal") 
		echo "<td align='center' class='highlight'>".intval($row['tehty'])."</td>";
	else
		echo "<td align='center'>".intval($row['tehty'])."</td>";
			
	if($sort == "total") 
		echo "<td align='center' class='highlight'>".intval($row['yht'])."</td></tr>";
	else
		echo "<td align='center'>".intval($row['yht'])."</td></tr>";
	}

echo "</table>";

CloseConnection();

echo "<p><a href='javascript:history.go(-1);'>"._("Palaa")."</a></p>\n";

contentEnd();
pageEnd();
?>
