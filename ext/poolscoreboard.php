<?php
include_once 'localization.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="Pragma" content="no-cache"/>
<meta http-equiv="Expires" content="-1"/>
<?php
$style = iget("style");
if(empty($style))
	$style='pelikone.css';
	
echo "<link rel='stylesheet' href='$style' type='text/css' />";
echo "<title>"._("Ultiorganizer")."</title>";
?>
</head>
<body>
<?php

include_once '../lib/season.functions.php';
include_once '../lib/series.functions.php';
include_once '../lib/team.functions.php';

$poolId = intval(iget("pool"));
$season = iget("season");
$sort="total";

echo "<table class='pk_table'>";

echo "<tr><th class='pk_scoreboard_th'>"._("Player")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Team")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Games")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Assists")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Goals")."</th>";
echo "<th class='pk_scoreboard_th'>"._("Tot.")."</th>";
echo "</tr>";

$scores = PoolScoreBoard($poolId, $sort, 10);

while($row = mysqli_fetch_assoc($scores))
	{
	echo "<tr><td class='pk_scoreboard_td1'>". utf8entities($row['firstname']." ".$row['lastname'])."</td>";
	echo "<td class='pk_scoreboard_td1'>".utf8entities($row['teamname'])."</td>";
	echo "<td  class='pk_scoreboard_td2'>".intval($row['games'])."</td>";
	echo "<td  class='pk_scoreboard_td2'>".intval($row['fedin'])."</td>";
	echo "<td  class='pk_scoreboard_td2'>".intval($row['done'])."</td>";
	echo "<td  class='pk_scoreboard_td2'>".intval($row['total'])."</td></tr>";
	}

echo "</table>";

CloseConnection();
?>
</body>
</html>
