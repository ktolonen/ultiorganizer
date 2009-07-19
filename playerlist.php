<?php
include 'view_ids.inc.php';
include 'lib/database.php';
include 'lib/team.functions.php';
include 'lib/common.functions.php';
include 'lib/season.functions.php';
include 'lib/serie.functions.php';
include 'lib/player.functions.php';
include 'lib/game.functions.php';
include 'builder.php';

$LAYOUT_ID = $PLAYERLIST;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content

OpenConnection();
$teamId = intval($_GET["Team"]);
$teaminfo = TeamInfo($teamId);
$serie = strtok($teaminfo['snimi'], " ");

$players = TeamPlayerList($teamId );

echo "<h1>Pelaajalista</h1>\n";
echo "<h2>".$teaminfo['nimi']." (".$serie.")</h2>\n";

echo "<table border='0' cellpadding='2'>\n";
echo "<tr><th>Nimi</th><th>Kaudet</th><th>Pelej&auml;</th><th>Sy&ouml;t&ouml;t</th><th>Maalit</th><th>Yht.</th></tr>\n";

while($player = mysql_fetch_assoc($players))
	{
	$playerinfo = PlayerInfo($player['pelaaja_id']);
	$goals = 0;
	$passes = 0;
	$played = 0;
	$years=0;
	$prevyear=0;
	$playedSeasons = PlayerPlayedSeasons($playerinfo['jnro']);

	while($season = mysql_fetch_assoc($playedSeasons))
		{
		$playedseries = PlayerPlayedSeries($season['pelaaja_id']);
	
		while($series = mysql_fetch_assoc($playedseries))
			{
			$goals += PlayerGoals($season['pelaaja_id'], $series['sarja']);
			$passes += PlayerPasses($season['pelaaja_id'], $series['sarja']);
			$played += PlayerPlayedGames($season['pelaaja_id'], $series['sarja']);
			$year = intval(strtok($series['kausi'], "."));
			if($year != $prevyear)
				{
				$years++;
				$prevyear = $year;
				}
			}
		}
	echo "<tr><td><a href='playercard.php?Series=0&amp;Player=". $player['pelaaja_id']."'>". 
		htmlentities($playerinfo['enimi'] ." ". $playerinfo['snimi']) ."</a></td>
		<td>$years</td>
		<td>$played</td><td>$passes</td><td>$goals</td>
		<td>", $passes+$goals ,"</td></tr>";
	}
echo "</table>\n";	
CloseConnection();
	
?>
<p><a href="javascript:history.go(-1);">Palaa</a></p>

<?php
contentEnd();
pageEnd();
?>
