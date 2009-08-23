<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/common.functions.php';
include_once 'builder.php';

$LAYOUT_ID = GAMEPLAY;

//common page
pageTop(false);
leftMenu($LAYOUT_ID);
contentStart();
//content
OpenConnection();
$gameId = intval($_GET["Game"]);

$game_result = GameResult($gameId);

$home_team_score_board = GameTeamScoreBorad($gameId, $game_result['kotijoukkue']);
$guest_team_score_board = GameTeamScoreBorad($gameId, $game_result['vierasjoukkue']);

$rules = SerieRules($game_result['sarja']);

$goals = GameGoals($gameId);
$gameevents = GameEvents($gameId);

if(mysql_num_rows($goals) <= 0)
	{
	echo "<h2>"._("Pelin p&ouml;yt&auml;kirjaa ei ole viel&auml; sy&ouml;tetty pelikoneeseen.")."</h2>
		  <p>"._("Tarkista tilanne sivulta my&ouml;hemmin uudelleen.")."</p>";
	}

//score board
echo "<h2>"._("Pelin pistep&ouml;rssi")."</h2>\n";
	
echo "<table><tr><td valign='top' style='width:45%'>\n";

echo "<table width='100%' cellspacing='0' cellpadding='0' border='0'>\n";
echo "<tr style='height=20'><td align='center'><b>";
echo htmlentities($game_result['KNimi']), "</b></td></tr>\n";
echo "</table><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
echo "<tr><th class='home'>#</th><th class='home'>"._("Nimi")."</th><th class='home'>"._("Sy&ouml;t.")."</th><th class='home'>"._("Maal.").</th>
     <th class='home'>"._("Yht.")."</th></tr>\n";

while($row = mysql_fetch_assoc($home_team_score_board))
	{
		echo "<tr>";
		echo "<td style='text-align:right'>". $row['numero'] ."</td>";
		echo "<td><a href='playercard.php?Series=0&amp;Player=". $row['pelaaja_id'];
		echo "'>". htmlentities($row['enimi']) ."&nbsp;";
		echo htmlentities($row['snimi']) ."</a></td>";
		echo "<td>". $row['syotetty'] ."</td>";
		echo "<td>". $row['tehty'] ."</td>";
		echo"<td>". $row['yht'] ."</td>";
		echo "</tr>";		
	}

	
echo "</table></td>\n<td style='width:10%'>&nbsp;</td><td valign='top' style='width:45%'>";

echo "<table width='100%' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td><b>";
echo htmlentities($game_result['VNimi']), "</b></td></tr>\n";
echo "</table><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
echo "<tr><th class='guest'>#</th><th class='guest'>"_.("Nimi")."</th><th class='guest'>"._("Sy&ouml;t.")."</th><th class='guest'>"._("Maal.")."</th>
     <th class='guest'>"._("Yht.")."</th></tr>\n";

	
while($row = mysql_fetch_assoc($guest_team_score_board))
	{
		echo "<tr>";
		echo "<td style='text-align:right'>". $row['numero'] ."</td>";
		echo "<td><a href='playercard.php?Series=0&amp;Player=". $row['pelaaja_id'];
		echo "'>". htmlentities($row['enimi']) ."&nbsp;";
		echo htmlentities($row['snimi']) ."</a></td>";
		echo "<td>". $row['syotetty'] ."</td>";
		echo "<td>". $row['tehty'] ."</td>";
		echo"<td>". $row['yht'] ."</td>";
		echo "</tr>";		
	}

echo "</table></td></tr></table>\n";

//timeline
//$points[50][7];
$points=array(array());
$i=0;
$lprev=0;
$htAt = intval($rules['PeliPist']);
$htAt = intval(($htAt/ 2) + 0.5);
$bHt=false;
$total=0;

while($goal = mysql_fetch_assoc($goals))
	{
	if(intval($goal['aika']) > 0)
		$ptLen = intval($goal['aika']) - $lprev;
	else
		$ptLen = 1;
		
	$points[$i][0] = $ptLen;
	$points[$i][1] = intval($goal['kotimaali']);
	$points[$i][2] = htmlentities($goal['tsnimi'] ." ". $goal['tenimi']);
	$points[$i][3] = htmlentities($goal['ssnimi'] ." ". $goal['senimi']);
	$points[$i][4] = intval($goal['aika']);
	$points[$i][5] = $goal['ktilanne'];
	$points[$i][6] = $goal['vtilanne'];

	$lprev = intval($goal['aika']);
	$total += $points[$i][0];
	
	if ($htAt != 0 && !$bHt)
		{
		if ((intval($goal['ktilanne'])==$htAt) || (intval($goal['vtilanne'])==$htAt))
			{
			$i++;
			$points[$i][0] = (intval($game_result['puoliaika']) - $lprev);
			$points[$i][4] = intval($game_result['puoliaika']);
			$lprev = intval($game_result['puoliaika']);
			$points[$i][1] = -2;
			$total += $points[$i][0];
			$bHt = 1;
			}
		}
	$i++;	
	}

echo "<table border='1' style='height: 15px; color: white; border-width: 1; border-color: white; width: 97%;'><tr>\n";

$maxlength = 600;
$latestHomeGoalTime = 0;
$latestGuestGoalTime = 0;
$offset = $maxlength/$total;
for ($i=0; $i < 50 && !empty($points[$i][0]); $i++) 
	{
	if($points[$i][1]==1)
		{
		$color="home";
		$latestHomeGoalTime = $points[$i][4];
		}
	elseif($points[$i][1]==-2)
		{
		$color="halftime";
		}
	else
		{
		$color="guest";
		$latestGuestGoalTime = $points[$i][4];
		}
	
	$timeSinceLastGuestGoal = $points[$i][4] - $latestGuestGoalTime;
	$timeSinceLastHomeGoal = $points[$i][4] - $latestHomeGoalTime;
	
	$width_a = $points[$i][0]*$offset;
	
	if($points[$i][1]==-2)
		{
		$title=SecToMin($points[$i][4]). " Puoliaika";
		}
	else
		{
		$title=SecToMin($points[$i][4]). " ".$points[$i][5]."-".$points[$i][6]." ".$points[$i][3]." -> ".$points[$i][2];
		}
	echo "<td style='width:".$width_a."px' class='$color' title='$title'></td>\n";

	}
echo "</tr></table>\n";
echo "<p>"._("Kirjanpit&auml;j&auml;t").": ". htmlentities($game_result['toim']) ."</p>";
echo "<h2>"._("Pelin maalit")."</h2>\n";
echo "<table border='1' cellpadding='2px' width='97%'>\n";
echo "<tr><th>#</th><th>"._("Aika")."</th><th>"._("Sy&ouml;tt&auml;j&auml;")."</th><th>"._("Tekij&auml;")."</th><th>"._("Tilanne")."</th><th>&nbsp;</th></tr>\n";



$bHt=false;
	
$i=1;
$prevgoal = 0;

mysql_data_seek($goals, 0);
while($goal = mysql_fetch_assoc($goals))
	{
	echo "<tr><td";
	if(intval($goal['kotimaali'])==1)
		echo " class='home'>";
	else
		echo " class='guest'>";
	
	echo "$i</td>";
	$i++;
	echo "<td>". SecToMin($goal['aika']) ."</td>";
	if(intval($goal['callahan']))
		echo "<td class='callahan'>"._("Callahan-maali")."&nbsp;</td>";
	else
		echo "<td>". htmlentities($goal['senimi']) ." ". htmlentities($goal['ssnimi']) ."&nbsp;</td>";
	echo "<td>". htmlentities($goal['tenimi']) ." ". htmlentities($goal['tsnimi']) ."&nbsp;</td>";
	echo "<td>". $goal['ktilanne'] ." - ". $goal['vtilanne'] ."</td>";
	echo "<td>";
	
	//gameevents
	mysql_data_seek($gameevents, 0);
	while($event = mysql_fetch_assoc($gameevents))
		{
		if((intval($event['aika']) >= $prevgoal) &&
			(intval($event['aika']) < intval($goal['aika'])))
			{
			if ($event['tyyppi'] == "timeout")
				$gameevent = _("Aikalis&auml;");
			else
				$gameevent = _("Kiekonmenetys");

			if(intval($event['koti'])>0)
				echo "<div class='home'>".$gameevent."&nbsp;".SecToMin($event['aika'])."</div>";
			else
				echo "<div class='guest'>".$gameevent."&nbsp;".SecToMin($event['aika'])."</div>";
			
			}
		}
	
	echo "</td>";
	echo "</tr>";
	
	if ($htAt != 0 && !$bHt)
		{
		if ((intval($goal['ktilanne'])==$htAt) || (intval($goal['vtilanne'])==$htAt))
			{
			echo "<tr><td colspan='6' class='halftime'>_("Puoliaika")."</td></tr>";
			$bHt = 1;
			}
		}
	
	$prevgoal = intval($goal['aika']);
	}
echo "</table>\n";

//statistics
echo "<h2>"._("Pelin tilastot")."</h2>\n";

$allgoals = GameAllGoals($gameId);

$bHOffence = 0;
$nHOffencePoint = 0;
$nVOffencePoint = 0;
$nHBreaks = 0;
$nVBreaks = 0;
$nHTotalTime = 0;
$nVTotalTime = 0;
$nHGoals = 0;
$nVGoals = 0;
$nClockTime = 0;
$nDuration = 0;
$bHStartTheGame = 0;
$nHTO = 0;
$nVTO = 0;
$nHLosesDisc = 0;
$nVLosesDisc = 0;

$turnovers = GameTurnovers($gameId);

$goal = mysql_fetch_assoc($allgoals);
$turnover = mysql_fetch_assoc($turnovers);

//who start the game?
if ($turnover)
	{
	//If turnover before goal
	if (intval($turnover['aika']) < intval($goal['aika']))
		{
		//If home lose disc Then home was starting the game
		if(intval($turnover['koti']))
			$bHStartTheGame = true;
		//visitor starts but loses the disc
		else
			$bHStartTheGame = false;
		}
	//no turnovers before goal, the team scored was starting the game
	else
		{
		if (intval($goal['kotimaali']))
			$bHStartTheGame = true;
		else
			$bHStartTheGame = false;
		}
	}
//no turnovers in database
else
	{
	//team scored was starting (just wild guess)
	if (intval($goal['kotimaali']))
		$bHStartTheGame = true;
	else
		$bHStartTheGame = false;
	}
	
//whom start the game, starts offence
$bHOffence = $bHStartTheGame;

//return internal pointers to first row
mysql_data_seek($allgoals, 0);	

//loop all goals
while($goal = mysql_fetch_assoc($allgoals))
	{	
	//halftime passed
	if (($nClockTime <= intval($game_result['puoliaika'])) && (intval($goal['aika']) >= intval($game_result['puoliaika'])))
		{
		$nClockTime = intval($game_result['puoliaika']);

		if($bHStartTheGame)
			$bHOffence = false;
		else
			$bHOffence = true;
		}

	//track offence turns
	if($bHOffence)
		$nHOffencePoint++;
	else
		$nVOffencePoint++;
	
	//If turnovers before goal
	if(mysql_num_rows($turnovers))
		mysql_data_seek($turnovers, 0);
		
	while($turnover = mysql_fetch_assoc($turnovers))
		{
		if((intval($turnover['aika']) > $nClockTime) &&
			(intval($turnover['aika'])<intval($goal['aika'])))
			{
			if(intval($turnover['koti']))
				{
				$nHLosesDisc++;
				$nDuration = intval($turnover['aika']) - $nClockTime;
				$nClockTime = intval($turnover['aika']);
				$nHTotalTime += $nDuration;
				}
			else
				{
				$nVLosesDisc++;
				$nDuration = intval($turnover['aika']) - $nClockTime;
				$nClockTime = intval($turnover['aika']);
				$nVTotalTime += $nDuration;
				}
			}
		}
		
		//If a break goal
		if (intval($goal['kotimaali'])&& $bHOffence==false)
			$nHBreaks++;
		elseif (intval($goal['kotimaali'])==0 && $bHOffence==true) 
			$nVBreaks++;
		
		//point duration (after turnover or last goal)
		$nDuration = intval($goal['aika']) - $nClockTime;
		$nClockTime = intval($goal['aika']);
			
		//If home goal
		if (intval($goal['kotimaali']))
			{
			$nHTotalTime += $nDuration;
			$nHGoals++;
			$bHOffence = false;
			}
		else
			{
			$nVTotalTime += $nDuration;
			$nVGoals++;
			$bHOffence = true;
			}
	}
	
	
	//timeouts
	$timeouts = GameTimeouts($gameId);
	
while($timeout = mysql_fetch_assoc($timeouts))
	{
	if (intval($timeout['koti']))
		$nHTO++;
	else
		$nVTO++;
	}

	
$dblHAvg=0.0;
$dblVAvg=0.0;

//Build HTML-table	
echo "<table border='1' cellpadding='2px' cellspacing='0'><tr><th></th><th>". htmlentities($game_result['KNimi']).
	 "</th><th>". htmlentities($game_result['VNimi']) ."</th></tr>";

echo "<tr><td>"._("Maalit").":</td> <td class='home'>$nHGoals</td> <td class='guest'>$nVGoals</td></tr>\n";

$dblHAvg = SafeDivide($nHTotalTime, ($nHTotalTime+$nVTotalTime)) * 100;
$dblVAvg = SafeDivide($nVTotalTime, ($nHTotalTime+$nVTotalTime)) * 100;

echo "<tr><td>"._("Hy&ouml;kk&auml;ysaika").":</td> 
	<td class='home'>". SecToMin($nHTotalTime) ." min (".number_format($dblHAvg,1)." %)</td>
	<td class='guest'>". SecToMin($nVTotalTime) ." min (".number_format($dblVAvg,1)." %)</td></tr>\n";

echo "<tr><td>"._("Puolustusaika").":</td> 
	<td class='home'>". SecToMin($nVTotalTime) ." min (".number_format($dblVAvg,1)." %)</td>
	<td class='guest'>". SecToMin($nHTotalTime) ." min (".number_format($dblHAvg,1)." %)</td></tr>\n";

echo "<tr><td>"._("Hy&ouml;kk&auml;ysaika")."/"._("maali").":</td> 
	<td class='home'>". SecToMin(SafeDivide($nHTotalTime,$nHGoals)) ." min</td>
	<td class='guest'>". SecToMin(SafeDivide($nVTotalTime,$nVGoals)) ." min</td></tr>\n";

echo "<tr><td>"._("Puolustusaika")."/"._("maali").":</td> 
	<td class='home'>". SecToMin(SafeDivide($nVTotalTime,$nVGoals)) ." min</td>
	<td class='guest'>". SecToMin(SafeDivide($nHTotalTime,$nHGoals)) ." min</td></tr>\n";

$dblHAvg = SafeDivide(abs($nHGoals-$nHBreaks), $nHOffencePoint) * 100;
$dblVAvg = SafeDivide(abs($nVGoals-$nVBreaks), $nVOffencePoint) * 100;

echo "<tr><td>"._("Hy&ouml;kk&auml;ysvuorosta maali").":</td> 
	<td class='home'>". abs($nHGoals-$nHBreaks) ."/". $nHOffencePoint ." (". number_format($dblHAvg,1) ." %)</td>
	<td class='guest'>". abs($nVGoals-$nVBreaks) ."/". $nVOffencePoint ." (". number_format($dblVAvg,1) ." %)</td></tr>";

$dblHAvg = SafeDivide($nHBreaks, $nVOffencePoint) * 100;
$dblVAvg = SafeDivide($nVBreaks, $nHOffencePoint) * 100;

echo "<tr><td>"._("Puolustusvuorosta maali").":</td>
	<td class='home'>". $nHBreaks ."/". $nVOffencePoint ." (". number_format($dblHAvg,1) ." %)</td>
	<td class='guest'>". $nVBreaks ."/". $nHOffencePoint ." (". number_format($dblVAvg,1) ." %)</td></tr>";

if ($nHLosesDisc+$nVLosesDisc > 0)
	{
	echo "<tr><td>"._("Kiekonmenetykset").":</td>
		<td class='home'>". $nHLosesDisc ."</td>
		<td class='guest'>". $nVLosesDisc ."</td></tr>";
	}
else
	{
	echo "<tr><td>"._("Kiekonmenetykset").":</td> 
		<td class='home'>-</td>
		<td class='guest'>-</td></tr>";
	}


echo "<tr><td>"._("Katkomaalit").":</td>
	<td class='home'>". $nHBreaks ."</td>
	<td class='guest'>". $nVBreaks ."</td></tr>";

echo "<tr><td>"._("Aikalis&auml;t").":</td> 
	<td class='home'>". $nHTO ."</td>
	<td class='guest'>". $nVTO ."</td></tr>";

echo "</table>";

CloseConnection();
?>
<p><a href="javascript:history.go(-1);">"._("Palaa")."</a></p>
<?php
contentEnd();
pageEnd();
?>
