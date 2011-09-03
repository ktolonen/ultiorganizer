<?php
include_once 'lib/pool.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/common.functions.php';

$LAYOUT_ID = GAMEPLAY;

$gameId = intval($_GET["Game"]);

$game_result = GameResult($gameId);
$seasoninfo = SeasonInfo(GameSeason($gameId));
$homecaptain = GameCaptain($gameId, $game_result['hometeam']);
$awaycaptain = GameCaptain($gameId, $game_result['visitorteam']);
//common page
$title = _("Game play").": ".utf8entities($game_result['hometeamname'])." vs. ".utf8entities($game_result['visitorteamname']);

pageTop($title, false);
leftMenu($LAYOUT_ID);
contentStart();
//content

$home_team_score_board = GameTeamScoreBorad($gameId, $game_result['hometeam']);
$guest_team_score_board = GameTeamScoreBorad($gameId, $game_result['visitorteam']);

$poolinfo = PoolInfo($game_result['pool']);

$goals = GameGoals($gameId);
$gameevents = GameEvents($gameId);
$mediaevents = GameMediaEvents($gameId);

if($game_result['homescore'] > 0 || $game_result['visitorscore'] > 0){
		echo "<h1>". utf8entities($game_result['hometeamname']);
		echo " - ";
		echo utf8entities($game_result['visitorteamname']);
		echo "&nbsp;&nbsp;&nbsp;&nbsp;";
		echo intval($game_result['homescore']);
		echo " - ";
		echo intval($game_result['visitorscore']);
		if(intval($game_result['isongoing'])){
			echo " ("._("ongoing").")";
		}
		echo "</h1>\n";
		
	if(mysql_num_rows($goals) <= 0)
		{
		echo "<h2>"._("Not fed in")."</h2>
			  <p>"._("Please check the status again later")."</p>";
		}
	else{

	//score board
		
	echo "<table style='width:100%'><tr><td valign='top' style='width:45%'>\n";

	echo "<table width='100%' cellspacing='0' cellpadding='0' border='0'>\n";
	echo "<tr style='height=20'><td align='center'><b>";
	echo utf8entities($game_result['hometeamname']), "</b></td></tr>\n";
	echo "</table><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
	echo "<tr><th class='home'>#</th><th class='home'>"._("Name")."</th><th class='home center'>"._("Assists")."</th><th class='home center'>"._("Goals")."</th>
		 <th class='home center'>"._("Tot.")."</th></tr>\n";

	while($row = mysql_fetch_assoc($home_team_score_board))
		{
			echo "<tr>";
			echo "<td style='text-align:right'>". $row['num'] ."</td>";
			echo "<td><a href='?view=playercard&amp;Series=0&amp;Player=". $row['player_id'];
			echo "'>". utf8entities($row['firstname']) ."&nbsp;";
			echo utf8entities($row['lastname']) ."</a>";
			if($row['player_id']==$homecaptain){
			echo "&nbsp;"._("(C)");
			}
			echo "</td>";
			echo "<td class='center'>". $row['fedin'] ."</td>";
			echo "<td class='center'>". $row['done'] ."</td>";
			echo"<td class='center'>". $row['total'] ."</td>";
			echo "</tr>";		
		}

		
	echo "</table></td>\n<td style='width:10%'>&nbsp;</td><td valign='top' style='width:45%'>";

	echo "<table width='100%' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td><b>";
	echo utf8entities($game_result['visitorteamname']), "</b></td></tr>\n";
	echo "</table><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
	echo "<tr><th class='guest'>#</th><th class='guest'>"._("Name")."</th><th class='guest center'>";
	echo _("Assists")."</th><th class='guest center'>"._("Goals");
	echo "</th><th class='guest center'>"._("Tot.")."</th></tr>\n";

		
	while($row = mysql_fetch_assoc($guest_team_score_board))
		{
			echo "<tr>";
			echo "<td style='text-align:right'>". $row['num'] ."</td>";
			echo "<td><a href='?view=playercard&amp;Series=0&amp;Player=". $row['player_id'];
			echo "'>". utf8entities($row['firstname']) ."&nbsp;";
			echo utf8entities($row['lastname']) ."</a>";
			if($row['player_id']==$awaycaptain){
			echo "&nbsp;"._("(C)");
			}
			echo "</td>";
			echo "<td class='center'>". $row['fedin'] ."</td>";
			echo "<td class='center'>". $row['done'] ."</td>";
			echo"<td class='center'>". $row['total'] ."</td>";
			echo "</tr>";		
		}

	echo "</table></td></tr></table>\n";

	//timeline
	//$points[50][7];
	$points=array(array());
	$i=0;
	$lprev=0;
	$htAt = intval($poolinfo['winningscore']);
	$htAt = intval(($htAt/ 2) + 0.5);
	$bHt=false;
	$total=0;

	while($goal = mysql_fetch_assoc($goals))
		{
		
		if (!$bHt && $goal['time']>$game_result['halftime']){
			$points[$i][0] = (intval($game_result['halftime']) - $lprev);
			$points[$i][4] = intval($game_result['halftime']);
			$lprev = intval($game_result['halftime']);
			$points[$i][1] = -2;
			$total += $points[$i][0];
			$bHt = 1;
			$i++;
			}
		
		if(intval($goal['time']) > 0)
			$ptLen = intval($goal['time']) - $lprev;
		else
			$ptLen = 1;
			
		$points[$i][0] = $ptLen;
		$points[$i][1] = intval($goal['ishomegoal']);
		$points[$i][2] = utf8entities($goal['scorerlastname'] ." ". $goal['scorerfirstname']);
		$points[$i][3] = utf8entities($goal['assistlastname'] ." ". $goal['assistfirstname']);
		$points[$i][4] = intval($goal['time']);
		$points[$i][5] = $goal['homescore'];
		$points[$i][6] = $goal['visitorscore'];

		$lprev = intval($goal['time']);
		$total += $points[$i][0];
		
		
		$i++;	
		}

	echo "<table border='1' style='height: 15px; color: white; border-width: 1; border-color: white; width: 100%;'><tr>\n";

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
			$title=SecToMin($points[$i][4]). " halftime";
			}
		else
			{
			$title=SecToMin($points[$i][4]). " ".$points[$i][5]."-".$points[$i][6]." ".$points[$i][3]." -> ".$points[$i][2];
			}
		echo "<td style='width:".$width_a."px' class='$color' title='$title'></td>\n";

		}
	echo "</tr></table>\n";

	echo "<table border='1' cellpadding='2' width='100%'>\n";
	echo "<tr><th>"._("Scores")."</th><th>"._("Assist")."</th><th>"._("Goal")."</th><th>"._("Time")."</th><th>"._("Dur.")."</th>";
	if(count($gameevents)||count($mediaevents)){
		echo "<th>"._("Game events ")."</th>";
	}
	echo "</tr>\n";
	
	$bHt=false;
		
	$prevgoal = 0;	
	mysql_data_seek($goals, 0);
	while($goal = mysql_fetch_assoc($goals))
		{
		if (!$bHt && $game_result['halftime']>0 && $goal['time'] > $game_result['halftime']){
			echo "<tr><td colspan='6' class='halftime'>"._("Half-time")."</td></tr>";
			$bHt = 1;
			$prevgoal = intval($game_result['halftime']);
		}
			
		echo "<tr><td style='width:45px;white-space: nowrap'";
		if(intval($goal['ishomegoal'])==1)
			echo " class='home'>";
		else
			echo " class='guest'>";
		
		echo $goal['homescore'] ." - ". $goal['visitorscore'] ."</td>";
		
		if(intval($goal['iscallahan']))
			echo "<td class='callahan'>"._("Callahan-goal")."&nbsp;</td>";
		else
			echo "<td>". utf8entities($goal['assistfirstname']) ." ". utf8entities($goal['assistlastname']) ."&nbsp;</td>";
		echo "<td>". utf8entities($goal['scorerfirstname']) ." ". utf8entities($goal['scorerlastname']) ."&nbsp;</td>";
		echo "<td>". SecToMin($goal['time']) ."</td>";
		$duration = $goal['time']-$prevgoal;
		
		echo "<td>". SecToMin($duration) ."</td>";
		
		if(count($gameevents) || count($mediaevents)){
			echo "<td>";
			//gameevents
			foreach($gameevents as $event){
				if((intval($event['time']) >= $prevgoal) &&
					(intval($event['time']) < intval($goal['time'])))
					{
					if($event['type'] == "timeout")
						$gameevent = _("Timeout");
					elseif($event['type'] == "turnover")
						$gameevent = _("Turnover");
					elseif($event['type'] == "offence")
						$gameevent = _("Offence");
					
					//hack to not show timeouts not correctly marked into scoresheet					
					if($event['type'] == "timeout" && ($event['time']==0 || $event['time']==60)){continue;}
					
					if(intval($event['ishome'])>0)
						echo "<div class='home'>".$gameevent."&nbsp;".SecToMin($event['time'])."</div>";
					else
						echo "<div class='guest'>".$gameevent."&nbsp;".SecToMin($event['time'])."</div>";
					
					}
				}
			//mediaevents
			$tmphtml="";
			foreach($mediaevents as $event){
				if((intval($event['time']) >= $prevgoal) &&
					(intval($event['time']) < intval($goal['time']))){
						$tmphtml .= "<a style='color: #ffffff;' href='". $event['url']."'>"; 
						$tmphtml .= "<img width='12' height='12' src='images/linkicons/".$event['type'].".png' alt='".$event['type']."'/></a>";
						
					}
			}
			if(!empty($tmphtml)){
				echo "<div class='mediaevent'>".$tmphtml."</div>\n";
			}
			echo "</td>";
		}
		echo "</tr>";
		$prevgoal = intval($goal['time']);		
		}
	if(intval($game_result['isongoing'])){
		echo "<tr style='border-style:dashed;border-width:1px;'>";
		echo "<td>&nbsp;</td>";
		echo "<td>&nbsp;</td>";
		echo "<td>&nbsp;</td>";
		echo "<td>&nbsp;</td>";
		echo "<td>&nbsp;</td>";
		if(count($gameevents) || count($mediaevents)){
			echo "<td>&nbsp;</td>";
		}
		echo "</tr>";
	}
	echo "</table>\n";
	
	if(!empty($game_result['official'])){
		echo "<p>"._("Game official").": ". utf8entities($game_result['official']) ."</p>";
	}
	
	$urls = GetMediaUrlList("game", $gameId);
		
	if(count($urls) > count($mediaevents)){
		echo "<h2>"._("Photos and Videos")."</h2>\n";
		echo "<table>";	
			foreach($urls as $url){
				//if time set those are shown as gameevent
				if(!empty($url['time'])){continue;}
								
				echo  "<tr>";
				echo  "<td colspan='2'><img width='16' height='16' src='images/linkicons/".$url['type'].".png' alt='".$url['type']."'/> ";
				echo  "</td><td>";
				if(!empty($url['name'])){
					echo "<a href='". $url['url']."'>". $url['name']."</a>";
				}else{
					echo "<a href='". $url['url']."'>". $url['url']."</a>";
				}
				if(!empty($url['mediaowner'])){
					echo " "._("from")." ". $url['mediaowner'];
				}

				echo "</td>";
				echo "</tr>";
			}
		echo "</table>";	
	}

	if(!intval($game_result['isongoing'])){	
		//statistics
		echo "<h2>"._("Game statistics")."</h2>\n";

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
		$ishome = GameIsFirstOffenceHome($gameId);	
		if($ishome==1){
			$bHStartTheGame = true;
		}elseif($ishome==0){
			$bHStartTheGame = false;
		}else{
			//make some wild guess
			if ($turnover)
				{
				//If turnover before goal
				if (intval($turnover['time']) < intval($goal['time']))
					{
					//If home lose disc Then home was starting the game
					if(intval($turnover['ishome']))
						$bHStartTheGame = true;
					//visitor starts but loses the disc
					else
						$bHStartTheGame = false;
					}
				//no turnovers before goal, the team scored was starting the game
				else
					{
					if (intval($goal['ishomegoal']))
						$bHStartTheGame = true;
					else
						$bHStartTheGame = false;
					}
				}
			//no turnovers in database
			else
				{
				//team scored was starting (just wild guess)
				if (intval($goal['ishomegoal']))
					$bHStartTheGame = true;
				else
					$bHStartTheGame = false;
				}
		}
		//whom start the game, starts offence
		$bHOffence = $bHStartTheGame;

		//return internal pointers to first row
		mysql_data_seek($allgoals, 0);	

		//loop all goals
		while($goal = mysql_fetch_assoc($allgoals))
			{	
			//halftime passed
			if (($nClockTime <= intval($game_result['halftime'])) && (intval($goal['time']) >= intval($game_result['halftime'])))
				{
				$nClockTime = intval($game_result['halftime']);

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
				$turnovers = GameTurnovers($gameId);
				
			while($turnover = mysql_fetch_assoc($turnovers))
				{
				if((intval($turnover['time']) > $nClockTime) &&
					(intval($turnover['time'])<intval($goal['time'])))
					{
					if(intval($turnover['ishome']))
						{
						$nHLosesDisc++;
						//$nDuration = intval($turnover['time']) - $nClockTime;
						//$nClockTime = intval($turnover['time']);
						//$nHTotalTime += $nDuration;
						}
					else
						{
						$nVLosesDisc++;
						//$nDuration = intval($turnover['time']) - $nClockTime;
						//$nClockTime = intval($turnover['time']);
						//$nVTotalTime += $nDuration;
						}
					}
				}
				
				//If a break goal
				if (intval($goal['ishomegoal'])&& $bHOffence==false)
					$nHBreaks++;
				elseif (intval($goal['ishomegoal'])==0 && $bHOffence==true) 
					$nVBreaks++;
				
				//point duration
				$nDuration = intval($goal['time']) - $nClockTime;
				$nClockTime = intval($goal['time']);

				if($bHOffence){
				  $nHTotalTime += $nDuration;
				}else{
				  $nVTotalTime += $nDuration;
				}
				//If home goal
				if (intval($goal['ishomegoal']))
					{
					$nHGoals++;
					$bHOffence = false;
					}
				else
					{
					$nVGoals++;
					$bHOffence = true;
					}
			}
			
			
			//timeouts
			$timeouts = GameTimeouts($gameId);
			
		while($timeout = mysql_fetch_assoc($timeouts))
			{
			if (intval($timeout['ishome']))
				$nHTO++;
			else
				$nVTO++;
			}

			
		$dblHAvg=0.0;
		$dblVAvg=0.0;

		//Build HTML-table	
		echo "<table style='width:60%' border='1' cellpadding='2' cellspacing='0'><tr><th></th><th>". utf8entities($game_result['hometeamname']).
			 "</th><th>". utf8entities($game_result['visitorteamname']) ."</th></tr>";

		echo "<tr><td>"._("Goals").":</td> <td class='home'>$nHGoals</td> <td class='guest'>$nVGoals</td></tr>\n";

		$dblHAvg = SafeDivide($nHTotalTime, ($nHTotalTime+$nVTotalTime)) * 100;
		$dblVAvg = SafeDivide($nVTotalTime, ($nHTotalTime+$nVTotalTime)) * 100;

		echo "<tr><td>"._("Time on offence").":</td> 
			<td class='home'>". SecToMin($nHTotalTime) ." min (".number_format($dblHAvg,1)." %)</td>
			<td class='guest'>". SecToMin($nVTotalTime) ." min (".number_format($dblVAvg,1)." %)</td></tr>\n";

		echo "<tr><td>"._("Time on defence").":</td> 
			<td class='home'>". SecToMin($nVTotalTime) ." min (".number_format($dblVAvg,1)." %)</td>
			<td class='guest'>". SecToMin($nHTotalTime) ." min (".number_format($dblHAvg,1)." %)</td></tr>\n";

		echo "<tr><td>"._("Time on offence")."/"._("goal").":</td> 
			<td class='home'>". SecToMin(SafeDivide($nHTotalTime,$nHGoals)) ." min</td>
			<td class='guest'>". SecToMin(SafeDivide($nVTotalTime,$nVGoals)) ." min</td></tr>\n";

		echo "<tr><td>"._("Time on defence")."/"._("goal").":</td> 
			<td class='home'>". SecToMin(SafeDivide($nVTotalTime,$nVGoals)) ." min</td>
			<td class='guest'>". SecToMin(SafeDivide($nHTotalTime,$nHGoals)) ." min</td></tr>\n";

		$dblHAvg = SafeDivide(abs($nHGoals-$nHBreaks), $nHOffencePoint) * 100;
		$dblVAvg = SafeDivide(abs($nVGoals-$nVBreaks), $nVOffencePoint) * 100;

		echo "<tr><td>"._("Goals from starting on offence").":</td> 
			<td class='home'>". abs($nHGoals-$nHBreaks) ."/". $nHOffencePoint ." (". number_format($dblHAvg,1) ." %)</td>
			<td class='guest'>". abs($nVGoals-$nVBreaks) ."/". $nVOffencePoint ." (". number_format($dblVAvg,1) ." %)</td></tr>";

		$dblHAvg = SafeDivide($nHBreaks, $nVOffencePoint) * 100;
		$dblVAvg = SafeDivide($nVBreaks, $nHOffencePoint) * 100;

		echo "<tr><td>"._("Goals from starting on defence").":</td>
			<td class='home'>". $nHBreaks ."/". $nVOffencePoint ." (". number_format($dblHAvg,1) ." %)</td>
			<td class='guest'>". $nVBreaks ."/". $nHOffencePoint ." (". number_format($dblVAvg,1) ." %)</td></tr>";

		if ($nHLosesDisc+$nVLosesDisc > 0)
			{
			echo "<tr><td>"._("Turnovers").":</td>
				<td class='home'>". $nHLosesDisc ."</td>
				<td class='guest'>". $nVLosesDisc ."</td></tr>";
			}

		echo "<tr><td>"._("Goals from turnovers").":</td>
			<td class='home'>". $nHBreaks ."</td>
			<td class='guest'>". $nVBreaks ."</td></tr>";

		echo "<tr><td>"._("Time-outs").":</td> 
			<td class='home'>". $nHTO ."</td>
			<td class='guest'>". $nVTO ."</td></tr>";
		
		if($seasoninfo['spiritpoints'] && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seasoninfo['season_id']))){		
			echo "<tr><td>"._("Spirit points").":</td> 
				<td class='home'>". $game_result['homesotg'] ."</td>
				<td class='guest'>". $game_result['visitorsotg'] ."</td></tr>";
		}
		echo "</table>";
	}
		echo "<p><a href='?view=gamecard&amp;Team1=". utf8entities($game_result['hometeam']) ."&amp;Team2=". utf8entities($game_result['visitorteam']) . "'>";
		echo  _("Game history")."</a></p>\n";
		if ($_SESSION['uid'] != 'anonymous') {
			echo "<div style='float:left;'><hr/><a href='?view=user/addmedialink&amp;game=$gameId'>"._("Add media")."</a></div>";
		}	
	
	}
}else{
	$gameinfo = GameInfo($gameId);
	
	if($gameinfo['hometeam'] && $gameinfo['visitorteam']){
	    echo "<h1>"; 
    	echo utf8entities($gameinfo['hometeamname']);
    	echo " - ";
    	echo utf8entities($gameinfo['visitorteamname']);
    	echo "&nbsp;&nbsp;&nbsp;&nbsp;";
    	echo "? - ?";
    	echo "</h1>\n";
	}else{
	  
	  echo "<h1>"; 
      echo utf8entities(U_($gameinfo['gamename']));
	  echo "</h1>\n";
	  echo "<h2>";
      echo utf8entities(U_($gameinfo['phometeamname']));
      echo " - ";
      echo utf8entities(U_($gameinfo['pvisitorteamname']));
      echo "&nbsp;&nbsp;&nbsp;&nbsp;";
      echo "? - ?";
      echo "</h2>\n";

	}
	
	echo "<p>";
	echo ShortDate($gameinfo['time']) ." ". DefHourFormat($gameinfo['time']). " ";
	if(!empty($gameinfo['fieldname'])){
		echo _("on field")." ".utf8entities($gameinfo['fieldname']);
	}
	echo "</p>";

}
contentEnd();
pageEnd();
?>
