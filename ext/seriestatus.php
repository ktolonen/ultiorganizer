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
$serieId = intval($_GET["Serie"]);

echo "<table class='pk_table'>
	  <tr>
	  <th class='pk_ser_th'>Joukkue</th>
	  <th class='pk_ser_th'>Maaliero</th>
	  <th class='pk_ser_th'>+/-</th>
	  <th class='pk_ser_th'>Voitot</th>
	  <th class='pk_ser_th'>Tappiot</th>
	  <th class='pk_ser_th'>Pelit</th>
	  <th class='pk_ser_th'>Pisteet</th>
	  </tr>";

$standings = SerieStandings($serieId);

while($row = mysql_fetch_assoc($standings))
	{
	$stats = TeamStats($serieId, $row['joukkue_id']);
	$points = TeamPoints($serieId, $row['joukkue_id']);
	
	echo "<tr><td class='pk_ser_td1'>",htmlentities($row['nimi']),"</td>\n";
	echo "<td class='pk_ser_td2'>".intval($points['pisteet'])."-".intval($points['vastaan'])."</td>\n";
	echo "<td class='pk_ser_td2'>",(intval($points['pisteet'])-intval($points['vastaan'])),"</td>\n";
	echo"<td class='pk_ser_td2'>".intval($stats['voitot'])."</td>\n";
	echo "<td class='pk_ser_td2'>",intval($stats['ottelut'])-intval($stats['voitot']),"</td>\n";
	echo "<td class='pk_ser_td2'>".intval($stats['ottelut'])."</td>\n";
	echo "<td class='pk_ser_td2'>",intval($stats['voitot'])*2,"</td></tr>\n";
	}
echo "</table>\n";
CloseConnection();
?>
</body>
</html>