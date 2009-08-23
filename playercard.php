<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/team.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/game.functions.php';
include_once 'builder.php';

$LAYOUT_ID = PLAYERCARD;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content

OpenConnection();
$playerId = intval($_GET["Player"]);
$serieId = intval($_GET["Series"]);

$player = PlayerInfo($playerId);

if ($player['numero'])
	echo "<h2>#". $player['numero'] ." ". htmlentities($player['enimi'] ." ". $player['snimi']) ."</h2>";
else
	echo "<h2>". htmlentities($player['enimi'] ." ". $player['snimi']) ."</h2>";
	
echo "<h3>". htmlentities($player['jnimi']) ."</h3>";

echo "<table border='1' cellspacing='0' width='100%'>\n
	<tr><th>Kausi</th><th>Sarja</th><th>Joukkue</th><th>Pelej&auml;</th><th>Sy&ouml;t&ouml;t</th><th>Maalit</th><th>Yht.</th>
	<th>Sy&ouml;tt&ouml; ka.</th><th>Maali ka.</th><th>Piste ka.</th></tr>\n";

$nOutdoorGoals=0;
$nOutdoorPasses=0;
$nOutdoorPlayed=0;

$nIndoorGoals=0;
$nIndoorPasses=0;
$nIndoorPlayed=0;

$nOtherGoals=0;
$nOtherPasses=0;
$nOtherPlayed=0;

$total=0; 
$dblPassAvg=0; 
$dblGoalAvg=0; 
$dblScoreAvg=0;
	
$playedSeasons = PlayerPlayedSeasons($player['jnro']);

while($season = mysql_fetch_assoc($playedSeasons))
	{
	$playedseries = PlayerPlayedSeries($season['pelaaja_id']);
	
	while($serie = mysql_fetch_assoc($playedseries))
		{
		$goals = PlayerGoals($season['pelaaja_id'], $serie['sarja']);
		$passes = PlayerPasses($season['pelaaja_id'], $serie['sarja']);
		$games = PlayerPlayedGames($season['pelaaja_id'], $serie['sarja']);
		
		$arrayYear = strtok($serie['kausi'], "."); 
		$arraySeason = strtok(".");
		
		$total = $goals + $passes;

		$dblPassAvg = $passes / $games;
		$dblGoalAvg = $goals / $games;
		$dblScoreAvg = $total / $games;

		if ($arraySeason == "1")
			{
			$nOutdoorGoals = $nOutdoorGoals + $goals;
			$nOutdoorPasses = $nOutdoorPasses + $passes;
			$nOutdoorPlayed = $nOutdoorPlayed + $games;

			echo "<tr class='highlight'><td>Kes&auml; $arrayYear</td>
				<td>". htmlentities($serie['nimi']) ."</td>
				<td>". htmlentities($season['nimi']) ."</td>
				<td>". $games ."</td>
				<td>". $passes ."</td>
				<td>". $goals ."</td>
				<td>". $total ."</td>
				<td>". number_format($dblPassAvg,2) ."</td>
				<td>". number_format($dblGoalAvg,2) ."</td>
				<td>". number_format($dblScoreAvg,2) ."</td></tr>\n";
			}
			
		elseif ($arraySeason == "2")
			{
			$nIndoorGoals += $goals;
			$nIndoorPasses += $passes;
			$nIndoorPlayed += $games;

			echo "<tr><td>Talvi $arrayYear</td>
				<td>". htmlentities($serie['nimi']) ."</td>
				<td>". htmlentities($season['nimi']) ."</td>
				<td>". $games ."</td>
				<td>". $passes ."</td>
				<td>". $goals ."</td>
				<td>". $total ."</td>
				<td>". number_format($dblPassAvg,2) ."</td>
				<td>". number_format($dblGoalAvg,2) ."</td>
				<td>". number_format($dblScoreAvg,2) ."</td></tr>\n";
			}
		else
			{
			$nOtherGoals += $goals;
			$nOtherPasses += $passes;
			$nOtherPlayed += $games;

			echo "<tr><td>".$serie['kausi']."</td>
				<td>". htmlentities($serie['nimi']) ."</td>
				<td>". htmlentities($season['nimi']) ."</td>
				<td>". $games ."</td>
				<td>". $passes ."</td>
				<td>". $goals ."</td>
				<td>". $total ."</td>
				<td>". number_format($dblPassAvg,2) ."</td>
				<td>". number_format($dblGoalAvg,2) ."</td>
				<td>". number_format($dblScoreAvg,2) ."</td></tr>\n";
			}		
		}
	}
echo "</table>\n";

//seasons total
echo "<table border='1' width='100%'><tr>
	<th>Kausi</th><th>Pelej&auml;</th><th>Sy&ouml;t&ouml;t</th><th>Maalit</th><th>Yht.</th><th>Sy&ouml;tt&ouml; ka.</th>
	<th>Maali ka.</th><th>Piste ka.</th></tr>\n";

if ($nOutdoorPlayed)
	{
	$total = $nOutdoorGoals+$nOutdoorPasses;
	$dblPassAvg = $nOutdoorPasses / $nOutdoorPlayed;
	$dblGoalAvg = $nOutdoorGoals / $nOutdoorPlayed;
	$dblScoreAvg = $total / $nOutdoorPlayed;
	echo "<tr>
		<td>Kes&auml;</td>
		<td>".$nOutdoorPlayed."</td>
		<td>".$nOutdoorPasses."</td>
		<td>".$nOutdoorGoals."</td>
		<td>".$total."</td>
		<td>".number_format($dblPassAvg,2)."</td>
		<td>".number_format($dblGoalAvg,2)."</td>
		<td>".number_format($dblScoreAvg,2)."</td></tr>\n";
	}

if ($nIndoorPlayed)
	{
	$total = $nIndoorGoals+$nIndoorPasses;
	$dblPassAvg = $nIndoorPasses / $nIndoorPlayed;
	$dblGoalAvg = $nIndoorGoals / $nIndoorPlayed;
	$dblScoreAvg = $total / $nIndoorPlayed;
	echo "<tr>
		<td>Talvi</td>
		<td>".$nIndoorPlayed."</td>
		<td>".$nIndoorPasses."</td>
		<td>".$nIndoorGoals."</td>
		<td>".$total."</td>
		<td>".number_format($dblPassAvg,2)."</td>
		<td>".number_format($dblGoalAvg,2)."</td>
		<td>".number_format($dblScoreAvg,2)."</td></tr>\n";
	}

if ($nOtherPlayed)
	{
	$total = $nOtherGoals+$nOtherPasses;
	$dblPassAvg = $nOtherPasses / $nOtherPlayed;
	$dblGoalAvg = $nOtherGoals / $nOtherPlayed;
	$dblScoreAvg = $total / $nOtherPlayed;
	echo "<tr>
		<td>Muut</td>
		<td>".$nOtherPlayed."</td>
		<td>".$nOtherPasses."</td>
		<td>".$nOtherGoals."</td>
		<td>".$total."</td>
		<td>".number_format($dblPassAvg,2)."</td>
		<td>".number_format($dblGoalAvg,2)."</td>
		<td>".number_format($dblScoreAvg,2)."</td></tr>\n";
	}

$total = $nOutdoorGoals+$nOutdoorPasses+$nIndoorGoals+$nIndoorPasses+$nOtherGoals+$nOtherPasses;

if ($nOutdoorPlayed+$nIndoorPlayed+$nOtherPlayed)
	{
	$dblPassAvg = ($nOutdoorPasses+$nIndoorPasses+$nOtherPasses) / ($nOutdoorPlayed+$nIndoorPlayed+$nOtherPlayed);
	$dblGoalAvg = ($nOutdoorGoals+$nIndoorGoals+$nOtherGoals) / ($nOutdoorPlayed+$nIndoorPlayed+$nOtherPlayed);
	$dblScoreAvg = $total / ($nOutdoorPlayed+$nIndoorPlayed+$nOtherPlayed);
	}
else
	{
	$dblPassAvg = 0;
	$dblGoalAvg = 0;
	$dblScoreAvg = 0;
	}

echo "<tr class='highlight'>
	<td>Yht.</td>
	<td>",$nOutdoorPlayed+$nIndoorPlayed+$nOtherPlayed,"</td>
	<td>",$nOutdoorPasses+$nIndoorPasses+$nOtherPasses,"</td>
	<td>",$nOutdoorGoals+$nIndoorGoals+$nOtherGoals,"</td>
	<td>".$total."</td>
	<td>".number_format($dblPassAvg,2)."</td>
	<td>".number_format($dblGoalAvg,2)."</td>
	<td>".number_format($dblScoreAvg,2)."</td></tr>\n";

echo "</table>\n<p></p>\n";


//Current season stats

$games = PlayerSeasonGames($playerId, CurrenSeason());

if(mysql_num_rows($games))
	{
	echo "<h2>Kauden pelitapahtumat:</h2>\n";

	while($game = mysql_fetch_assoc($games))
		{
		
		$result = GameResult($game['peli_id']);
		
		echo "<table border='1' width='75%'>";
		echo "<tr><th colspan='4'><b>", ShortDate($result['aika']) ,"&nbsp;&nbsp;". htmlentities($result['KNimi']) ." - ". htmlentities($result['VNimi']) ."&nbsp;
			&nbsp;".$result['kotipisteet']. " - ".$result['vieraspisteet']."</b></th></tr>\n";
			
		$events = PlayerGameEvents($playerId,$game['peli_id']);
			
	   	while($event = mysql_fetch_assoc($events))
			{		
			echo "<tr><td>". SecToMin($event['aika']) ."</td><td>". $event['ktilanne'] ." - ". $event['vtilanne'] ."</td>";
				
			if($event['syottaja'] == $playerId)
				{
				echo"<td class='highlight'>". htmlentities($player['enimi'] ." ". $player['snimi']) ."</td>\n";
				}
			else
				{
				$p = PlayerInfo($event['syottaja']);
				if ($p)
					echo "<td>". htmlentities($p['enimi'] ." ". $p['snimi']) ."</td>";
				else
					echo "<td>&nbsp;</td>";
				}
			
			
			if($event['tekija'] == $playerId)
				{
				echo"<td class='highlight'>". htmlentities($player['enimi'] ." ". $player['snimi']) ."</td>\n";
				}
			else
				{
				$p = PlayerInfo($event['tekija']);
				if ($p)
					echo "<td>". htmlentities($p['enimi'] ." ". $p['snimi']) ."</td>";
				else
					echo "<td>&nbsp;</td>";
				}
							
			echo "</tr>";
			}
		echo "</table>";
		}
	}
CloseConnection();
	
?>
<p><a href="javascript:history.go(-1);">Palaa</a></p>

<?php
contentEnd();
pageEnd();
?>
