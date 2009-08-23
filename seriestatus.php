<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'lib/team.functions.php';
include_once 'builder.php';

include_once 'user/lib/serie.functions.php';
$LAYOUT_ID = SERIESTATUS;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

OpenConnection();
$serieId = intval($_GET["Serie"]);
$serieName = SerieName($serieId);

echo "<h2>".htmlentities($serieName)."</h2>";

echo "<table border='1' width='500'>
	  <tr><th>"._("Joukkue")."</th><th>"._("Maaliero")."</th><th>+/-</th><th>"._("Voitot")."</th><th>"._("Tappiot")."</th><th>"._("Pelit")."</th><th>"._("Pisteet")."</th></tr>";
SerieResolveStandings($serieId);
$standings = SerieStandings($serieId);

while($row = mysql_fetch_assoc($standings))
	{
	$stats = TeamStats($serieId, $row['joukkue_id']);
	$points = TeamPoints($serieId, $row['joukkue_id']);
	
	echo "<tr><td>",htmlentities($row['nimi']),"</td>";
	echo "<td>".intval($points['pisteet'])."-".intval($points['vastaan'])."</td>";
	echo "<td>",(intval($points['pisteet'])-intval($points['vastaan'])),"</td>";
	echo"<td>".intval($stats['voitot'])."</td>";
	echo "<td>",intval($stats['ottelut'])-intval($stats['voitot']),"</td>";
	echo "<td>".intval($stats['ottelut'])."</td>";
	echo "<td>",intval($stats['voitot'])*2,"</td></tr>";
	}
echo "</table>";

echo "<p><a href='played.php?Series=$serieId'>"._("Pelatut ottelut")."</a><br/></p>";

echo "<h2>"._("Pistep&ouml;rssin k&auml;rkisijat")."</h2>";
echo "<table cellspacing='0' border='0' width='500px'>";
echo "<tr><th>"._("Pelaaja")."</th><th>"._("Joukkue")."</th><th>"._("Pelej&auml;")."</th><th>"._("Sy&ouml;t&ouml;t")."</th><th>"._("Maalit")."</th><th>"._("Yht.")."</th></tr>";

$scores = SerieScoreBoard($serieId,"total", 5);
while($row = mysql_fetch_assoc($scores))
	{
	echo "<tr><td>". htmlentities($row['enimi']." ".$row['snimi'])."</td>";
	echo "<td>".htmlentities($row['jnimi'])."</td>";
	echo "<td align='center'>".intval($row['peleja'])."</td>";
	echo "<td align='center'>".intval($row['syotetty'])."</td>";
	echo "<td align='center'>".intval($row['tehty'])."</td>";
	echo "<td align='center'>".intval($row['yht'])."</td></tr>";
	}

echo "</table>";
echo "<a href='scorestatus.php?Series=$serieId'>"._("Pistep&ouml;rssi")."</a>";

CloseConnection();

echo "<p><a href='javascript:history.go(-1);'>"._("Palaa")."</a></p>";
     
contentEnd();
pageEnd();
?>
