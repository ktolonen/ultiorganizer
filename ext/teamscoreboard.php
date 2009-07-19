<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="pelikone.css" type="text/css" />
<title>Liitokiekkoliiton Pelikone</title>
</head>
<body>

<?php
include '../lib/database.php';
include '../lib/season.functions.php';
include '../lib/serie.functions.php';
include '../lib/team.functions.php';

OpenConnection();
$seriesId = intval($_GET["Series"]);
$teamId = intval($_GET["Team"]);
$sort="total";

echo "<table class='pk_table'>";

echo "<tr><th class='pk_scoreboard_th'>Pelaaja</th>";
echo "<th class='pk_scoreboard_th'>Joukkue</th>";
echo "<th class='pk_scoreboard_th'>Pelej&auml;</th>";
echo "<th class='pk_scoreboard_th'>Sy&ouml;t&ouml;t</th>";
echo "<th class='pk_scoreboard_th'>Maalit</th>";
echo "<th class='pk_scoreboard_th'>Yht.</th>";
echo "</tr>";

if($teamId)
	$scores = TeamScoreBoard($teamId, $seriesId, $sort, 0);
else		
	$scores = SerieScoreBoard($seriesId, $sort, 0);

while($row = mysql_fetch_assoc($scores))
	{
	echo "<tr><td class='pk_scoreboard_td1'>". htmlentities($row['enimi']." ".$row['snimi'])."</td>";
	echo "<td class='pk_scoreboard_td1'>".htmlentities($row['jnimi'])."</td>";
	echo "<td  class='pk_scoreboard_td2'>".intval($row['peleja'])."</td>";
	echo "<td  class='pk_scoreboard_td2'>".intval($row['syotetty'])."</td>";
	echo "<td  class='pk_scoreboard_td2'>".intval($row['tehty'])."</td>";
	echo "<td  class='pk_scoreboard_td2'>".intval($row['yht'])."</td></tr>";
	}

echo "</table>";

CloseConnection();
?>
</body>
</html>
