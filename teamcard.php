<?php
include 'view_ids.inc.php';
include 'lib/database.php';
include 'lib/team.functions.php';
include 'lib/common.functions.php';
include 'lib/season.functions.php';
include 'lib/serie.functions.php';
include 'builder.php';

$LAYOUT_ID = $TEAMCARD;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content

OpenConnection();
$teamId = intval($_GET["Team"]);
$teaminfo = TeamInfo($teamId);

$serie = strtok($teaminfo['snimi'], " ");

echo "<h1>".$teaminfo['nimi']." (".$serie.")</h1>";
echo "<h2>".$teaminfo['seura']."</h2>";

$seasons = TeamPlayedSeasons($teaminfo['nimi'], $serie);

echo "<table border='1' cellspacing='0' width='100%'><tr>
	<th>Kausi</th>
	<th>Sarja</th>
	<th>Sij.</th>
	<th>Pel.</th>
	<th>Voi.</th>
	<th>Tap.</th>
	<th>Voi.-%</th>
	<th>Teh.</th>
	<th>Teh./peli</th>
	<th>P&auml;&auml;s.</th>
	<th>P&auml;&auml;s./peli</th>
	<th>Maaliero</th>
	</tr>";

$nOtherGames=0;
$nOtherGoalsMade=0;
$nOtherGoalsAgainst=0;
$nOtherWins=0;
$nOtherLoses=0;

$nOutdoorGames=0;
$nOutdoorGoalsMade=0;
$nOutdoorGoalsAgainst=0;
$nOutdoorWins=0;
$nOutdoorLoses=0;

$nIndoorGames=0;
$nIndoorGoalsMade=0;
$nIndoorGoalsAgainst=0;
$nIndoorWins=0;
$nIndoorLoses=0;

$nSerGames=0;
$nSerGoalsMade=0;
$nSerGoalsAgainst=0;
$nSerWins=0;
$nSerLoses=0;

$nCurSer=0;
$arraySeason=0;

while($season = mysql_fetch_assoc($seasons))
	{
	$games = TeamGames($season['joukkue_id']);
	$arrayYear = strtok($season['kausi'], "."); 
	$arraySeason = strtok(".");
					
	//echo "<p>$arrayYear ja $arraySeason ja $nCurSer</p>";					
	
	$game = mysql_fetch_assoc($games);
	
	while($game)
		{
		if($arraySeason=="1")
			{
			echo "<tr class='highlight'><td>Kes&auml; $arrayYear </td>";
			}
		elseif($arraySeason=="2")
			{
			echo "<tr><td>Talvi $arrayYear</td>";
			}
		else
			{
			echo "<tr><td>".$season['kausi']."</td>";
			}
		
		echo "<td>".$game['nimi']."</td>";
		echo "<td>".$game['activerank']."</td>";
		
		$nCurSer = $game['sarja'];
		
		while($nCurSer == $game['sarja'])
			{
			if (!is_null($game['kotipisteet']) && !is_null($game['vieraspisteet']))
				{
				$nSerGames++;
			
				if ($season['joukkue_id'] == $game['kotijoukkue'])
					{
					$nSerGoalsMade = $nSerGoalsMade+intval($game['kotipisteet']);
					$nSerGoalsAgainst = $nSerGoalsAgainst+intval($game['vieraspisteet']);
					
					if (intval($game['kotipisteet']) > intval($game['vieraspisteet']))
						$nSerWins++;
					else
						$nSerLoses++;
					}
				else
					{
					$nSerGoalsMade = $nSerGoalsMade+intval($game['vieraspisteet']);
					$nSerGoalsAgainst = $nSerGoalsAgainst+intval($game['kotipisteet']);
					if (intval($game['kotipisteet']) < intval($game['vieraspisteet']))
						$nSerWins++;
					else
						$nSerLoses++;
					}
				}
			$game = mysql_fetch_assoc($games);
			if(!$game) 
				break;
			}

		echo "<td>$nSerGames </td>";
		echo "<td>$nSerWins</td>";
		echo "<td>$nSerLoses</td>";
		echo "<td>", number_format((SafeDivide($nSerWins,$nSerGames)*100),1), "%</td>";
		echo "<td>$nSerGoalsMade</td>";
		echo "<td>", number_format(SafeDivide($nSerGoalsMade,$nSerGames),1), "</td>";
		echo "<td>$nSerGoalsAgainst</td>";
		echo "<td>", number_format(SafeDivide($nSerGoalsAgainst,$nSerGames),1), "</td>";
		echo "<td>", ($nSerGoalsMade-$nSerGoalsAgainst), "</td>";
		echo "</tr>";
		
		if($arraySeason == "1")
			{
			$nOutdoorGames = $nOutdoorGames+$nSerGames;
			$nOutdoorWins = $nOutdoorWins+$nSerWins;
			$nOutdoorLoses = $nOutdoorLoses+$nSerLoses;
			$nOutdoorGoalsMade = $nOutdoorGoalsMade+$nSerGoalsMade;
			$nOutdoorGoalsAgainst = $nOutdoorGoalsAgainst+$nSerGoalsAgainst;
			}
		elseif($arraySeason == "2")
			{
			$nIndoorGames = $nIndoorGames+$nSerGames;
			$nIndoorWins = $nIndoorWins+$nSerWins;
			$nIndoorLoses = $nIndoorLoses+$nSerLoses;
			$nIndoorGoalsMade = $nIndoorGoalsMade+$nSerGoalsMade;
			$nIndoorGoalsAgainst = $nIndoorGoalsAgainst+$nSerGoalsAgainst;
			}
		else
			{
			$nOtherGames = $nOtherGames+$nSerGames;
			$nOtherWins = $nOtherWins+$nSerWins;
			$nOtherLoses = $nOtherLoses+$nSerLoses;
			$nOtherGoalsMade = $nOtherGoalsMade+$nSerGoalsMade;
			$nOtherGoalsAgainst = $nOtherGoalsAgainst+$nSerGoalsAgainst;
			}
		
		$nSerGames = 0;
		$nSerGoalsMade = 0;
		$nSerGoalsAgainst = 0;
		$nSerWins = 0;
		$nSerLoses = 0;
		}
	}
echo "</table><p></p>";

echo "<table border='1' width='100%'><tr>
<th>Kausi</th>
<th>Pelit</th>
<th>Voitot</th>
<th>Tappiot</th>
<th>Voitto-%</th>
<th>Tehdyt</th>
<th>Tehdyt/peli</th>
<th>P&auml;&auml;stetyt</th>
<th>P&auml;&auml;stetyt/peli</th>
<th>Maaliero</th>
</tr>";

if($nOutdoorGames)
{
echo "<tr><td>Kes&auml;</td>
<td>$nOutdoorGames</td>
<td>$nOutdoorWins</td>
<td>$nOutdoorLoses</td>
<td>",number_format((SafeDivide($nOutdoorWins,$nOutdoorGames)*100),1)," %</td>
<td>$nOutdoorGoalsMade</td>
<td>",number_format(SafeDivide($nOutdoorGoalsMade,$nOutdoorGames),1),"</td>
<td>$nOutdoorGoalsAgainst</td>
<td>",number_format(SafeDivide($nOutdoorGoalsAgainst,$nOutdoorGames),1),"</td>
<td>",$nOutdoorGoalsMade-$nOutdoorGoalsAgainst,"</td></tr>";
}

if($nIndoorGames)
{
echo "<tr><td>Talvi</td>
<td>$nIndoorGames</td>
<td>$nIndoorWins</td>
<td>$nIndoorLoses</td>
<td>",number_format(SafeDivide($nIndoorWins,$nIndoorGames)*100,1)," %</td>
<td>$nIndoorGoalsMade</td>
<td>",number_format(SafeDivide($nIndoorGoalsMade,$nIndoorGames),1),"</td>
<td>$nIndoorGoalsAgainst</td>
<td>",number_format(SafeDivide($nIndoorGoalsAgainst,$nIndoorGames),1),"</td>
<td>",$nIndoorGoalsMade-$nIndoorGoalsAgainst,"</td></tr>";
}

if($nOtherGames)
{
echo "<tr><td>Muut</td>
<td>$nOtherGames</td>
<td>$nOtherWins</td>
<td>$nOtherLoses</td>
<td>",number_format(SafeDivide($nOtherWins,$nOtherGames)*100,1)," %</td>
<td>$nOtherGoalsMade</td>
<td>",number_format(SafeDivide($nOtherGoalsMade,$nOtherGames),1),"</td>
<td>$nOtherGoalsAgainst</td>
<td>",number_format(SafeDivide($nOtherGoalsAgainst,$nOtherGames),1),"</td>
<td>",$nOtherGoalsMade-$nOtherGoalsAgainst,"</td></tr>";
}


if($nOutdoorGames+$nIndoorGames+$nOtherGames)
{
echo "<tr class='highlight'><td>Yht.</td>
<td>",$nOutdoorGames+$nIndoorGames+$nOtherGames,"</td>
<td>",$nOutdoorWins+$nIndoorWins+$nOtherWins,"</td>
<td>",$nOutdoorLoses+$nIndoorLoses+$nLoses,"</td>
<td>",number_format(SafeDivide($nOutdoorWins+$nIndoorWins+$nOtherWins,$nOutdoorGames+$nIndoorGames+$nOtherGames)*100,1)," %</td>
<td>",$nOutdoorGoalsMade+$nIndoorGoalsMade+$nOtherGoalsMade,"</td>
<td>",number_format(SafeDivide($nOutdoorGoalsMade+$nIndoorGoalsMade+$nOtherGoalsMade,$nOutdoorGames+$nIndoorGames+$nOtherGames),1),"</td>
<td>",$nOutdoorGoalsAgainst+$nIndoorGoalsAgainst+$nOtherGoalsAgainst,"</td>
<td>",number_format(SafeDivide($nOutdoorGoalsAgainst+$nIndoorGoalsAgainst+$nOtherGoalsAgainst,$nOutdoorGames+$nIndoorGames+$nOtherGames),1),"</td>
<td>",($nOutdoorGoalsMade+$nIndoorGoalsMade+$nOtherGoalsMade)-($nOutdoorGoalsAgainst+$nIndoorGoalsAgainst+$nOtherGoalsAgainst),"</td></tr>";
}

echo "</table>";


//ottelutilastot

echo "<h2>Pelatut pelit</h2>";
echo "<table border='1' cellspacing='2' width='100%'><tr>";
$sort = $_GET["Sort"];

if(is_null($sort))
	$sort="serie";

$played = TeamPlayedGames($teaminfo['nimi'], $serie, $sort);

$sBaseUrl="teamcard.php?Team=$teamId&amp;";

echo "<th><a href=\"".$sBaseUrl."Sort=team\">Ottelu</a></th>";
echo "<th><a href=\"".$sBaseUrl."Sort=result\">Tulos</a></th>";
echo "<th><a href=\"".$sBaseUrl."Sort=serie\">Sarja</a></th></tr>";

while($row = mysql_fetch_assoc($played))
{
if (!is_null($row['kotipisteet']) && !is_null($row['vieraspisteet']))
{
$arrayYear = strtok($row['kausi'], "."); 
$arraySeason = strtok(".");
	
	if($row['kotipisteet'] > $row['vieraspisteet'])
		echo "<tr><td><b>".$row['knimi']."</b>";
	else
		echo "<tr><td>".$row['knimi'];
	
	
	if($row['kotipisteet'] < $row['vieraspisteet'])
		echo " - <b>".$row['vnimi']."</b></td>";
	else
		echo " - ".$row['vnimi']."</td>";
	
	
	echo "<td><a href=\"gameplay.php?Game=" .$row['peli_id']."\">".$row['kotipisteet']." - " .$row['vieraspisteet']. "</a></td>";

	if( $arraySeason == "1")
		{
		echo "<td>Kes&auml; $arrayYear: <a href=\"seriestatus.php?Serie=" .$row['sarja_id']. "\">".$row['nimi']."</a></td></tr>";
		}
	elseif($arraySeason == "2")
		{
		echo "<td>Talvi $arrayYear: <a href=\"seriestatus.php?Serie=" .$row['sarja_id']. "\">".$row['nimi']."</a></td></tr>";
		}
	else
		{
		echo "<td>".$row['kausi'].": <a href=\"seriestatus.php?Serie=" .$row['sarja_id']. "\">".$row['nimi']."</a></td></tr>";
		}
}	
}

echo "</table>";

CloseConnection();
	
?>
<p><a href="javascript:history.go(-1);">Palaa</a></p>

<?php
contentEnd();
pageEnd();
?>
