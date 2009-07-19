<?php
include 'view_ids.inc.php';
include 'lib/database.php';
include 'lib/season.functions.php';
include 'lib/serie.functions.php';
include 'lib/team.functions.php';
include 'builder.php';

$LAYOUT_ID = $SERIESTATUS;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

OpenConnection();
$serieId = intval($_GET["Serie"]);
$serieName = SerieName($serieId);

echo "<h2>$serieName</h2>";

echo "<table border='1' width='500'>
	  <tr><th>Joukkue</th><th>Maaliero</th><th>+/-</th><th>Voitot</th><th>Tappiot</th><th>Pelit</th><th>Pisteet</th></tr>";

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

echo "<p><a href='played.php?Series=$serieId'>Pelatut ottelut</a><br/></p>";

echo "<h2>Pistep&ouml;rssin k&auml;rkisijat</h2>";
echo "<table cellspacing='0' border='0' width='500px'>";
echo "<tr><th>Pelaaja</th><th>Joukkue</th><th>Pelej&auml;</th><th>Sy&ouml;t&ouml;t</th><th>Maalit</th><th>Yht</th></tr>";

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
echo "<a href='scorestatus.php?Series=$serieId'>Pistep&ouml;rssi</a>";

CloseConnection();
?>

<p><a href="javascript:history.go(-1);">Palaa</a></p>

<?php
contentEnd();
pageEnd();
?>
