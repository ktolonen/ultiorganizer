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

$LAYOUT_ID = $GAMECARD;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content

OpenConnection();
$teamId1 = intval($_GET["Team1"]);
$teamId2 = intval($_GET["Team2"]);
$sorting = $_GET["Sort"];

if(is_null($sorting))
	{
	$sorting="serie";
	}

$team1 = TeamInfo($teamId1);
$team2 = TeamInfo($teamId2);

$serie = strtok($team1['snimi'], " ");

//$seasons = TeamPlayedSeasons($team1['nimi'], $serie);

$nGames=0;
$nT1GoalsMade=0;
$nT1GoalsAgainst=0;
$nT1Wins=0;
$nT1Loses=0;
$nT2GoalsMade=0;
$nT2GoalsAgainst=0;
$nT2Wins=0;
$nT2Loses=0;

$games = GetAllPlayedGames($team1['nimi'],$team2['nimi'], $serie, $sorting);

while($game = mysql_fetch_assoc($games))
	{	
	if(intval($game['kotipisteet']) || intval($game['vieraspisteet']))
		{
		if($game['knimi'] == $team1['nimi'])
			{
			if (intval($game['kotipisteet']) > intval($game['vieraspisteet']))
				{
				$nT1Wins++;
				$nT2Loses++;
				}
			else
				{
				$nT2Wins++;
				$nT1Loses++;
				}
			$nT1GoalsMade += intval($game['kotipisteet']);
			$nT2GoalsAgainst += intval($game['kotipisteet']);
			
			$nT2GoalsMade += intval($game['vieraspisteet']);
			$nT1GoalsAgainst += intval($game['vieraspisteet']);
			}
		else
			{
			if (intval($game['kotipisteet']) < intval($game['vieraspisteet']))
				{
				$nT1Wins++;
				$nT2Loses++;
				}
			else
				{
				$nT2Wins++;
				$nT1Loses++;
				}
			
			$nT1GoalsMade += intval($game['vieraspisteet']);
			$nT2GoalsAgainst += intval($game['vieraspisteet']);
			
			$nT2GoalsMade += intval($game['kotipisteet']);
			$nT1GoalsAgainst += intval($game['kotipisteet']);
			}
						
		$nGames++;
		}
	}

echo "<h2>". htmlentities($team1['nimi']) ." vs. ". htmlentities($team2['nimi']) ."</h2>\n";	

echo "<table border='1' width='100%'><tr>\n
	<th>Joukkue</th><th>Pelit</th><th>Voitot</th><th>Tappiot</th><th>Voitto-%</th><th>Tehdyt</th>
	<th>Tehdyt/peli</th><th>P&auml;&auml;stetyt</th><th>P&auml;&auml;stetyt/peli</th><th>Maaliero</th>
	</tr>\n";
	
echo "<tr>
	 <td><a href='teamcard.php?Team=$teamId1'>". htmlentities($team1['nimi']) ."</a></td>
	 <td>$nGames</td>
	 <td>$nT1Wins</td>
	 <td>$nT1Loses</td>
	 <td>", number_format((SafeDivide($nT1Wins,$nGames)*100),1) ," %</td>
	 <td>$nT1GoalsMade</td>
	 <td>", number_format(SafeDivide($nT1GoalsMade,$nGames),1) ,"</td>
	 <td>$nT1GoalsAgainst</td>
	 <td>", number_format(SafeDivide($nT1GoalsAgainst,$nGames),1) ,"</td>
	 <td>",$nT1GoalsMade-$nT1GoalsAgainst,"</td></tr>\n";

echo "<tr>
	 <td><a href='teamcard.php?Team=$teamId2'>". htmlentities($team2['nimi']) ."</a></td>
	 <td>$nGames</td>
	 <td>$nT2Wins</td>
	 <td>$nT2Loses</td>
	 <td>", number_format((SafeDivide($nT2Wins,$nGames)*100),1) ," %</td>
	 <td>$nT1GoalsMade</td>
	 <td>", number_format(SafeDivide($nT2GoalsMade,$nGames),1) ,"</td>
	 <td>$nT1GoalsAgainst</td>
	 <td>", number_format(SafeDivide($nT2GoalsAgainst,$nGames),1) ,"</td>
	 <td>",$nT2GoalsMade-$nT2GoalsAgainst,"</td></tr>\n";

echo "</table>\n";

echo "<h2>Pelatut pelit</h2>\n";
echo "<table border='1' cellspacing='2' width='100%'><tr>\n";

$sBaseUrl="gamecard.php?Team1=$teamId1&amp;Team2=$teamId2&amp;";
	
echo "<th><a href='".$sBaseUrl."Sort=team'>Ottelu</a></th>";
echo "<th><a href='".$sBaseUrl."Sort=result'>Tulos</a></th>";
echo "<th><a href='".$sBaseUrl."Sort=serie'>Sarja</a></th></tr>";

$points[200][8];
mysql_data_seek($games,0);

while($game = mysql_fetch_assoc($games))
	{	
	if(intval($game['kotipisteet']) || intval($game['vieraspisteet']))
		{
		$arrayYear = strtok($game['kausi'], "."); 
		$arraySeason = strtok(".");
		
		if(intval($game['kotipisteet']) > intval($game['vieraspisteet']))
			echo "<tr><td><b>".htmlentities($game['knimi'])."</b>";
		else
			echo "<tr><td>".htmlentities($game['knimi']);
		
		if(intval($game['kotipisteet']) < intval($game['vieraspisteet']))
			echo " - <b>".htmlentities($game['vnimi'])."</b></td>";
		else
			echo " - ".htmlentities($game['vnimi'])."</td>";
		
		echo "<td><a href='gameplay.php?Game=".$game['peli_id']."'>".$game['kotipisteet']." - ".$game['vieraspisteet']."</a></td>";

		if($arraySeason == "1")
			echo "<td>Kes&auml; $arrayYear: <a href='seriestatus.php?Serie=".$game['sarja_id']."'>".htmlentities($game['nimi'])."</a></td></tr>";
		elseif($arraySeason == "2")
			echo "<td>Talvi $arrayYear: <a href='seriestatus.php?Serie=".$game['sarja_id']."'>".htmlentities($game['nimi'])."</a></td></tr>";
		else
			echo "<td>".htmlentities($game['kausi']).": <a href='seriestatus.php?Serie=".$game['sarja_id']."'>".htmlentities($game['nimi'])."</a></td></tr>";
		
		$scores = GameScoreBorad($game['peli_id']);
		$i=0;
		
		while($row = mysql_fetch_assoc($scores))
			{
			$bFound=false;	
			for ($i=0; ($i < 200) && ($points[$i][0] != ""); $i++) 
				{
				if (($points[$i][0] == $row['jnro']) && ($points[$i][2] == $row['jnimi']))
					{
					$points[$i][3]++;
					$points[$i][4]+= intval($row['tehty']);
					$points[$i][5]+= intval($row['syotetty']);
					$points[$i][6] = $points[$i][4]+$points[$i][5];
					$bFound=true;
					}
				}
				
			if(!$bFound && $i<200)
				{
				$points[$i][0] = $row['jnro'];
				$points[$i][1] = $row['enimi'] ." ". $row['snimi'];
				$points[$i][2] = $row['jnimi'];
				$points[$i][3] = 1;
				$points[$i][4] = intval($row['tehty']);
				$points[$i][5] = intval($row['syotetty']);
				$points[$i][6] = $points[$i][4]+$points[$i][5];
				$points[$i][7] = $row['pelaaja_id'];
				}
			}
		}
	}
echo "</table>\n";

echo "<h2>Pistep&ouml;rssi</h2>\n";
echo "<table border='1' width='75%'><tr><th>#</th>";

$sorted = false;

if($sorting == "pname")
	{
	echo "<th><b>Pelaaja</b></th>";
	usort($points, create_function('$b,$a','return strcmp($b[1],$a[1]);')); 
	$sorted = true;
	}
else
	{
	echo "<th><a href='".$sBaseUrl."Sort=pname'>Pelaaja</a></th>";
	}

if($sorting == "pteam")
	{
	echo "<th><b>Joukkue</b></th>";
	usort($points, create_function('$b,$a','return strcmp($b[2],$a[2]);')); 
	$sorted = true;
	}
else
	{
	echo "<th><a href='".$sBaseUrl."Sort=pteam'>Joukkue</a></th>";
	}

if($sorting == "pgames")
	{
	echo "<th><b>Pelej&auml;</b></th>";
	usort($points, create_function('$a,$b','return $a[3]==$b[3]?0:($a[3]>$b[3]?-1:1);'));
	$sorted = true;
	}
else
	{
	echo "<th><a href='".$sBaseUrl."Sort=pgames'>Pelej&auml;</a></th>";
	}

if($sorting == "ppasses")
	{
	echo "<th><b>Sy&ouml;t&ouml;t</b></th>";
	usort($points, create_function('$a,$b','return $a[4]==$b[4]?0:($a[4]>$b[4]?-1:1);'));
	$sorted = true;
	}
else
	{
	echo "<th><a href='".$sBaseUrl."Sort=ppasses'>Sy&ouml;t&ouml;t</a></th>";
	}

if($sorting == "pgoals")
	{
	echo "<th><b>Maalit</b></th>";
	usort($points, create_function('$a,$b','return $a[5]==$b[5]?0:($a[5]>$b[5]?-1:1);'));
	$sorted = true;
	}
else
	{
	echo "<th><a href='".$sBaseUrl."Sort=pgoals'>Maalit</a></th>";
	}
		
if(($sorting == "ptotal")||(!$sorted))
	{
	echo "<th><b>Yht.</b></th></tr>\n";
	usort($points, create_function('$a,$b','return $a[6]==$b[6]?0:($a[6]>$b[6]?-1:1);'));
	}
else
	{
	echo "<th><a href='".$sBaseUrl."Sort=ptotal'>Yht.</a></th></tr>\n";
	}
		

for ($i=0; $i < 200 && $points[$i][0] != ""; $i++) 
	{
	echo "<tr> <td>",$i+1,"</td>";
		
	if($sorting == "pname")
		echo "<td class='highlight'><a href='playercard.php?Player=".$points[$i][7]."'>".htmlentities($points[$i][1]) ."</a></td>";
	else
		echo "<td><a href='playercard.php?Player=".$points[$i][7]."'>".htmlentities($points[$i][1]) ."</a></td>";
		

	if($sorting == "pteam")
		echo "<td class='highlight'>".htmlentities($points[$i][2]) ."</td>";
	else
		echo "<td>".htmlentities($points[$i][2]) ."</td>";
		
	if($sorting == "pgames")
		echo "<td class='highlight cntr'>".$points[$i][3]."</td>";
	else
		echo "<td class='cntr'>".$points[$i][3]."</td>";

	if($sorting == "ppasses")
		echo "<td class='highlight cntr'>".$points[$i][4]."</td>";
	else
		echo "<td class='cntr'>".$points[$i][4]."</td>";

	if($sorting == "pgoals")
		echo "<td class='highlight cntr'>".$points[$i][5]."</td>";
	else
		echo "<td class='cntr'>".$points[$i][5]."</td>";
		
	if(($sorting == "ptotal")||(!$sorted))
		{
		echo "<td class='highlight cntr'>".$points[$i][6]."</td>";
		}
	else
		{
		echo "<td class='cntr'>".$points[$i][6]."</td>";
		}
	echo "</tr>";
	}
echo "</table>\n";
		 
		 
CloseConnection();
	
?>
<p><a href="javascript:history.go(-1);">Palaa</a></p>

<?php
contentEnd();
pageEnd();
?>
