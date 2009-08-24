<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
$style = urldecode($_GET["Style"]);
if(empty($style))
	$style='pelikone.css';
	
echo "<link rel='stylesheet' href='$style' type='text/css' />";
echo "<title>"._("Liitokiekkoliiton Pelikone")."</title>
	</head>
	<body>\n";

include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once '../lib/team.functions.php';

OpenConnection();
$seriesId = intval($_GET["Serie"]);
$season = $_GET["Season"];
$sort="total";

echo "<table class='pk_table'>";

echo "<tr><th class='pk_scoreboard_th'>"._("Pelaaja")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Joukkue")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Pelej&auml;")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Sy&ouml;t&ouml;t")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Maalit")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Yht.")."</th>";
echo "</tr>";

$scores = SerieScoreBoard($seriesId, $sort, 10);

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
