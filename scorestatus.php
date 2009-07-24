<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'lib/team.functions.php';
include_once 'builder.php';

$LAYOUT_ID = $SCOREBOARD;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();
?>
<h1>Pistep&ouml;rssi</h1>
<?php

OpenConnection();
$seriesId = intval($_GET["Series"]);
$teamId = intval($_GET["Team"]);

echo "<table cellspacing='1px' cellpadding='1px' border='1'>";

$sort = $_GET["Sort"];
if(is_null($sort))
	$sort="total";

$baseUrl="scorestatus.php?";
$baseUrl.= "Team=$teamId&amp;";
$baseUrl.= "Series=$seriesId&amp;";
	
echo "<tr><th>Pelaaja</th>";

if($sort == "team") 
	echo "<th><b>Joukkue</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=team'>Joukkue</a></th>";
	
if($sort == "games") 
	echo "<th><b>Pelej&auml;</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=games'>Pelej&auml;</a></th>";
				
if($sort == "pass") 
	echo "<th><b>Sy&ouml;t&ouml;t</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=pass'>Sy&ouml;t&ouml;t</a></th>";
		
if($sort == "goal") 
	echo "<th><b>Maalit</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=goal'>Maalit</a></th>";
		
if($sort == "total") 
	echo "<th><b>Yht.</b></th>";
else
	echo "<th><a href='".$baseUrl."Sort=total'>Yht.</a></th>";
	
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
?>
<p><a href="javascript:history.go(-1);">Palaa</a></p>
<?php
contentEnd();
pageEnd();
?>
