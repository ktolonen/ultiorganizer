<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/timetable.functions.php';

$LAYOUT_ID = SERIESTATUS;

$title = _("Standings")." ";
$seriesScoreboard = false;
$print=0;

if (!empty($_GET['Season'])) {
	$pools = SeasonPools($_GET['Season'], true,true);
	$title.= U_(SeasonName($_GET['Season']));
	$seriesScoreboard = true;
	$seasoninfo = SeasonInfo($_GET['Season']);
} else if (!empty($_GET['Series'])) {
	$pools = SeriesPools($_GET['Series'], true);
	$title.= U_(SeriesName($_GET['Series']));
	$seriesScoreboard = true;
	$seriesinfo = SeriesInfo($_GET['Series']);
	$seasoninfo = SeasonInfo($seriesinfo['season']);
} else if (!empty($_GET['Pool'])) {
	$games=PoolGames($_GET['Pool']);
	$poolinfo = PoolInfo($_GET['Pool']);
	
	//if pool has only one game, show game's schoresheet if exist
	if(count($games)==1 && $poolinfo['type']==1){
		$game = $games[0];
		header("location:?view=gameplay&Game=".$game['game_id']);
		exit();
	}
	$pools[] = array(
		"pool_id"=>intval($_GET['Pool']),
		"name"=>PoolName(intval($_GET['Pool']))
		);
	
	
	$seasoninfo = SeasonInfo($poolinfo['season']);
	$title.= utf8entities(U_(PoolSeriesName($_GET['Pool'])).", ". U_(PoolName($_GET['Pool'])));
}
if(!empty($_GET["Print"])) {
	$print = intval($_GET["Print"]);
	$format = "paper";
}

//common page
pageTop($title, $print);
leftMenu($LAYOUT_ID, $print);
contentStart();
$prevseries = 0;

foreach ($pools as $pool) {
	
	$poolinfo = PoolInfo($pool['pool_id']);

	if($prevseries && $prevseries != $poolinfo['series']){
		scoreboard($prevseries, true);
		if( mysql_num_rows( mysql_query("SHOW TABLES LIKE 'uo_defense'")))
		{
		defenseboard($prevseries, true);
		}
	}
	$prevseries = $poolinfo['series'];
	$seriesName = U_($poolinfo['seriesname']).", ". U_($poolinfo['name']);

	echo "<h2>".utf8entities($seriesName)."</h2>";

	if($poolinfo['type']==1){
		// round robin
		printRoundRobinPool($seasoninfo, $poolinfo);
	}elseif($poolinfo['type']==2){
		// playoff
		printPlayoffTree($seasoninfo, $poolinfo);
	}elseif($poolinfo['type']==3){
		// Swissdraw
		printSwissdraw($seasoninfo, $poolinfo);
	}elseif($poolinfo['type']==4){
		// Cross matches
		printCrossmatchPool($seasoninfo, $poolinfo);
	}

	if(!$seriesScoreboard && !$print){
		scoreboard($pool['pool_id'], false);
		if( mysql_num_rows( mysql_query("SHOW TABLES LIKE 'uo_defense'")))
		{
		defenseboard($pool['pool_id'], false);
		}
	}
}
if($seriesScoreboard && !$print){
	scoreboard($prevseries, true);
}

$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace("/&Print=[0-1]/","",$querystring);
if($print){
  echo "<hr/><div style='text-align:right'><a href='?".utf8entities($querystring)."'>"._("Return")."</a></div>";
}else{
  echo "<hr/><div style='text-align:right'>";
  echo "<div style='text-align:right'><a href='?".utf8entities($querystring)."&amp;Print=1'>"._("Printable version")."</a></div>";
}
contentEnd();
pageEnd();

function scoreboard($id, $seriesScoreboard){
	if($seriesScoreboard){
		echo "<h2>"._("Scoreboard leaders")."</h2>\n";
		echo "<table cellspacing='0' border='0' width='100%'>\n";
		echo "<tr><th style='width:200px'>"._("Player")."</th><th style='width:200px'>"._("Team")."</th><th class='center'>"._("Games")."</th>
		<th class='center'>"._("Assists")."</th><th class='center'>"._("Goals")."</th><th class='center'>"._("Tot.")."</th></tr>\n";

		$scores = SeriesScoreBoard($id,"total", 10);
		while($row = mysql_fetch_assoc($scores))
			{
			echo "<tr><td>". utf8entities($row['firstname']." ".$row['lastname'])."</td>";
			echo "<td>".utf8entities($row['teamname'])."</td>";
			echo "<td class='center'>".intval($row['games'])."</td>";
			echo "<td class='center'>".intval($row['fedin'])."</td>";
			echo "<td class='center'>".intval($row['done'])."</td>";
			echo "<td class='center'>".intval($row['total'])."</td></tr>\n";
			}

		echo "</table>";
		echo "<a href='?view=scorestatus&amp;Series=".$id."'>"._("Scoreboard")."</a>";
	}else{	
		echo "<h2>"._("Scoreboard leaders")."</h2>\n";
		echo "<table cellspacing='0' border='0' width='100%'>\n";
		echo "<tr><th style='width:200px'>"._("Player")."</th><th style='width:200px'>"._("Team")."</th><th class='center'>"._("Games")."</th>
		<th class='center'>"._("Assists")."</th><th class='center'>"._("Goals")."</th><th class='center'>"._("Tot.")."</th></tr>\n";

		$poolinfo = PoolInfo($id);
		$pools = array();
		if($poolinfo['type']==2){
			//find out sub pools
			$pools[] = $id;
			$followers = PoolFollowersArray($poolinfo['pool_id']);
			$pools = array_merge($pools,$followers);
			$scores = PoolsScoreBoard($pools,"total", 5);
		}else{
			$scores = PoolScoreBoard($id,"total", 5);
		}
		
		while($row = mysql_fetch_assoc($scores))
			{
			echo "<tr><td>". utf8entities($row['firstname']." ".$row['lastname'])."</td>";
			echo "<td>".utf8entities($row['teamname'])."</td>";
			echo "<td class='center'>".intval($row['games'])."</td>";
			echo "<td class='center'>".intval($row['fedin'])."</td>";
			echo "<td class='center'>".intval($row['done'])."</td>";
			echo "<td class='center'>".intval($row['total'])."</td></tr>\n";
			}

		echo "</table>";
		if($poolinfo['type']==2){
			echo "<a href='?view=scorestatus&amp;Pools=".implode(",",$pools)."'>"._("Scoreboard")."</a>";
		}else{
			echo "<a href='?view=scorestatus&amp;Pool=".$id."'>"._("Scoreboard")."</a>";
		}
	}
}


function defenseboard($id, $seriesDefenseboard){
	if($seriesDefenseboard){
		echo "<h2>"._("Defenseboard leaders")."</h2>\n";
		echo "<table cellspacing='0' border='0' width='100%'>\n";
		echo "<tr><th style='width:200px'>"._("Player")."</th><th style='width:200px'>"._("Team")."</th><th class='center'>"._("Games")."</th>
		<th class='center'>"._("Total defenses")."</th></tr>\n";

		$defenses = SeriesDefenseBoard($seriesinfo['series_id'],"deftotal", 10);
		while($row = mysql_fetch_assoc($defenses))
			{
			echo "<tr><td>". utf8entities($row['firstname']." ".$row['lastname'])."</td>";
			echo "<td>".utf8entities($row['teamname'])."</td>";
			echo "<td class='center'>".intval($row['games'])."</td>";
			echo "<td class='center'>".intval($row['deftotal'])."</td></tr>\n";
			}

		echo "</table>";
		echo "<a href='?view=defensestatus&amp;Series=".$seriesinfo['series_id']."'>"._("Defenseboard")."</a>";

	}else{	
		echo "<h2>"._("Defenseboard leaders")."</h2>\n";
		echo "<table cellspacing='0' border='0' width='100%'>\n";
		echo "<tr><th style='width:200px'>"._("Player")."</th><th style='width:200px'>"._("Team")."</th><th class='center'>"._("Games")."</th>
		<th class='center'>"._("Total defenses")."</th></tr>\n";

		$poolinfo = PoolInfo($id);
		$pools = array();
		if($poolinfo['type']==2){
			//find out sub pools
			$pools[] = $id;
			$followers = PoolFollowersArray($poolinfo['pool_id']);
			$pools = array_merge($pools,$followers);
			$scores = PoolsScoreBoardWithDefenses($pools,"deftotal", 5);
		}else{
			$scores = PoolScoreBoardWithDefenses($id,"deftotal", 5);
		}
		
		while($row = mysql_fetch_assoc($scores))
			{
			echo "<tr><td>". utf8entities($row['firstname']." ".$row['lastname'])."</td>";
			echo "<td>".utf8entities($row['teamname'])."</td>";
			echo "<td class='center'>".intval($row['games'])."</td>";
			echo "<td class='center'>".intval($row['deftotal'])."</td></tr>\n";
			}

		echo "</table>";
		if($poolinfo['type']==2){
			echo "<a href='?view=defensestatus&amp;Pools=".implode(",",$pools)."'>"._("Defenseboard")."</a>";
		}else{
			echo "<a href='?view=defensestatus&amp;Pool=".$id."'>"._("Defenseboard")."</a>";
		}
	}
}


function printSwissdraw($seasoninfo, $poolinfo){
// prints Swiss draw standing

	$style = "";

	if($poolinfo['played']){
		$style = "class='playedpool'";
	}
	echo "<table $style border='2' width='100%'>\n";
	echo "<tr><th>#</th><th style='width:200px'>"._("Team")."</th>";
	echo "<th class='center'>"._("Games")."</th>";
	echo "<th class='center'>"._("Victory Points")."</th>";
	echo "<th class='center'>"._("Opponent VPs")."</th>";
	echo "<th class='center'>"._("Margin")."</th>";
	echo "<th class='center'>"._("Goals scored")."</th>";
	//if($seasoninfo['spiritpoints'] && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seasoninfo['season_id']))){
	//	echo "<th class='center'>"._("Spirit points")."</th>";
	//}
	echo "</tr>\n";

	$standings = PoolTeams($poolinfo['pool_id'], "rank");
	
	if(count($standings)){
	  foreach($standings as $row){
//			$stats = TeamStatsByPool($poolinfo['pool_id'], $row['team_id']);
			$vp = TeamVictoryPointsByPool($poolinfo['pool_id'], $row['team_id']);
//			$points=TeamPointsByPool($poolinfo['pool_id'], $row['team_id']);
			$flag="";
			if(intval($seasoninfo['isinternational'])){
				$flag = "<img height='10' src='images/flags/tiny/".$row['flagfile']."' alt=''/> ";
			}
			echo "<tr><td>".$row['activerank']."</td>";
			echo "<td>&nbsp;$flag<a href='?view=teamcard&amp;Team=".$row['team_id']."'>",utf8entities(U_($row['name'])),"</a></td>";
			echo "<td class='center'>".intval($vp['games'])."</td>";
			echo "<td class='center'>".intval($vp['victorypoints'])."</td>";
			echo "<td class='center'>",intval($vp['oppvp'])."</td>";
			echo "<td class='center'>",intval($vp['margin'])."</td>";
			echo "<td class='center'>".intval($vp['score'])."</td>";
			// might give too details idea which team has given and how many points
			//if($seasoninfo['spiritpoints'] && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seasoninfo['season_id']))){
			//	echo "<td class='center'>",intval($points['spirit']),"</td>";
			//}
			echo "</tr>\n";
		}
	}else{
		$teams = PoolSchedulingTeams($poolinfo['pool_id']);
		foreach($teams as $row){
			echo "<tr><td>-</td>";
			echo "<td>".utf8entities(U_($row['name']))."</td>";
			echo "<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo "</tr>\n";
			}
	}
	echo "</table>\n";

	echo "<table width='100%'>\n";	
	if($poolinfo['mvgames']==0 || $poolinfo['mvgames']==2){
		$mvgames = PoolMovedGames($poolinfo['pool_id']);
		foreach($mvgames as $game){
			echo GameRow($game, false, false, false, false, false, true);
		}
	}
	$games = TimetableGames($poolinfo['pool_id'], "pool", "all", "series");
	while($game = mysql_fetch_assoc($games)){
		//function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
		echo GameRow($game, false, false, false, false, false, true);
	}
	echo "</table>\n";
	
	echo "<p><a href='?view=games&amp;Pool=".$poolinfo['pool_id']."'>"._("Schedule")."</a><br/></p>";
}


function printRoundRobinPool($seasoninfo, $poolinfo){

	$style = "";

	if($poolinfo['played']){
		$style = "style='font-weight: bold;'";
	}
	echo "<table $style border='2' width='100%'>\n";
	echo "<tr><th>#</th><th style='width:200px'>"._("Team")."</th>";
	echo "<th class='center'>"._("Games")."</th>";
	echo "<th class='center'>"._("Wins")."</th>";
	echo "<th class='center'>"._("Losses")."</th>";
	echo "<th class='center'>"._("Goals for")."</th>";
	echo "<th class='center'>"._("Goals against")."</th>";
	echo "<th class='center'>"._("Goal diff")."</th>";
	echo "</tr>\n";

	$standings = PoolTeams($poolinfo['pool_id'], "rank");
	$teams = PoolSchedulingTeams($poolinfo['pool_id']);
	$continuationpools = array();
	$gamesplayed = PoolTotalPlayedGames($poolinfo['pool_id']);
			
	if(!$poolinfo['continuingpool'] || count($standings)>=count($teams)){
        $i=1;
        foreach($standings as $row){
			$stats = TeamStatsByPool($poolinfo['pool_id'], $row['team_id']);
			$points = TeamPointsByPool($poolinfo['pool_id'], $row['team_id']);
			$movetopool = PoolGetMoveToPool($poolinfo['pool_id'],$i);
			$flag="";
			if(intval($seasoninfo['isinternational'])){
				$flag = "<img height='10' src='images/flags/tiny/".$row['flagfile']."' alt=''/> ";
			}
			$colorcoding="";
			if($movetopool){
				//$iebackground="filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#FFFFFF,endColorstr=#".$movetopool['color'].");";
				$colorcoding = "background-color:#".$movetopool['color'].";background-color:".RGBtoRGBa($movetopool['color'],0.3).";color:#".textColor($movetopool['color']);
				echo "<tr>";
				$continuationpools[]=$movetopool;
			}else{
				echo "<tr>";
			}
			if($gamesplayed>0){
			  echo "<td><div style='$colorcoding'>".$row['activerank']."</div></td>";
			}else{
			  echo "<td><div style='$colorcoding'>-</div></td>";
			}
			echo "<td><div>&nbsp;$flag<a href='?view=teamcard&amp;Team=".$row['team_id']."'>",utf8entities($row['name']),"</a></div></td>";
			echo "<td class='center'><div>".intval($stats['games'])."</div></td>";
			echo"<td class='center'><div>".intval($stats['wins'])."</div></td>";
			echo "<td class='center'><div>",intval($stats['games'])-intval($stats['wins']),"</div></td>";
			echo "<td class='center'><div>".intval($points['scores'])."</div></td>";
			echo "<td class='center'><div>".intval($points['against'])."</div></td>";
			echo "<td class='center'><div>",(intval($points['scores'])-intval($points['against'])),"</div></td>";
			echo "</tr>\n";
			$i++;
		}
	}else{
		
		$i=1;
		foreach($teams as $row){
		    $realteam = PoolTeamFromStandings($poolinfo['pool_id'],$i);
			$movetopool = PoolGetMoveToPool($poolinfo['pool_id'],$i);
			$colorcoding="";
			if($movetopool){
				//$iebackground="background:transparent;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#FFFFFF,endColorstr=#".$movetopool['color'].");zoom: 1;";
				$colorcoding = "background-color:#".$movetopool['color'].";background-color:".RGBtoRGBa($movetopool['color'],0.3).";color:#".textColor($movetopool['color']);
				echo "<tr>";
				$continuationpools[]=$movetopool;
			}else{
				echo "<tr>";
			}
			echo "<td style='$colorcoding'>-</td>";
			if($realteam){
			  $flag="";
			  if(intval($seasoninfo['isinternational'])){
				$flag = "<img height='10' src='images/flags/tiny/".$realteam['flagfile']."' alt=''/> ";
			  }
			  echo "<td><div>&nbsp;$flag<a href='?view=teamcard&amp;Team=".$realteam['team_id']."'>",utf8entities($realteam['name']),"</a></div></td>";
			  
			}else{
			  echo "<td>".utf8entities(U_($row['name']))."</td>";  
			}
			
			echo "<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo"<td class='center'>-</td>";
			echo "</tr>\n";
			$i++;
			}
	}
	echo "</table>\n";
	
	if(count($continuationpools)){
		echo "<table width='100%'><tr>\n";	
		$prev="";
		$width = 100 / count($continuationpools);
		foreach($continuationpools as $cpool){
			if($cpool['topool']!=$prev){
				echo "<td style='background-color:#".$cpool['color'].";background-color:".RGBtoRGBa($cpool['color'],0.3).";color:#".textColor($cpool['color']).";width:".$width."%'>";
				if($cpool['visible']){
					echo "<a href='?view=poolstatus&amp;Pool=".$cpool['topool']."'>".utf8entities(U_($cpool['name']))."</a>";
				}else{
					echo utf8entities(U_($cpool['name']));
				}
				echo "</td>";
				$prev=$cpool['topool'];
			}
		}
		echo "</tr></table>\n";	
	}
	echo "<table width='100%'>\n";	
	if($poolinfo['mvgames']==0 || $poolinfo['mvgames']==2){
		$mvgames = PoolMovedGames($poolinfo['pool_id']);
		foreach($mvgames as $game){
			echo GameRow($game, false, false, false, false, false, true);
		}
	}
	$games = TimetableGames($poolinfo['pool_id'], "pool", "all", "series");
	while($game = mysql_fetch_assoc($games)){
		//function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
		echo GameRow($game, false, false, false, false, false, true);
	}
	echo "</table>\n";
	
	echo "<p><a href='?view=games&amp;Pool=".$poolinfo['pool_id']."'>"._("Schedule")."</a><br/></p>";
}

function printPlayoffTree($seasoninfo, $poolinfo){
	
	$pools = array();
	$pools[] = $poolinfo['pool_id'];
	
	//find out total rounds played
	$followers = PoolPlayoffFollowersArray($poolinfo['pool_id']);

	if(count($followers)==0){
		$followers = PoolFollowersArray($poolinfo['pool_id']);
	}
	$pools = array_merge($pools,$followers);
	$rounds = count($pools);
	
	//find out total teams in pool
	$teams = PoolTeams($poolinfo['pool_id']);
	$steams = PoolSchedulingTeams($poolinfo['pool_id']);
	if(count($teams)<count($steams)){
		$teams = $steams;
		$totalteams = count($steams);
	}else{
	  $totalteams = count($teams);  
	}
	
	
	global $include_prefix;
	
	//read layout templates
	if (is_file($include_prefix."cust/".CUSTOMIZATIONS."/layouts/".$totalteams."_teams_".$rounds."_rounds.html")) {
		$html = file_get_contents($include_prefix."cust/".CUSTOMIZATIONS."/layouts/".$totalteams."_teams_".$rounds."_rounds.html");
	}elseif (is_file($include_prefix."cust/default/layouts/".$totalteams."_teams_".$rounds."_rounds.html")) {
		$html = file_get_contents($include_prefix."cust/default/layouts/".$totalteams."_teams_".$rounds."_rounds.html");
	}else{
		$html = "<p>"._("No playoff tree template found.")."</p>";
	}
	
	$round=0;
	foreach($pools as $poolId){
		$pool = PoolInfo($poolId);
		
		//find out round name
		switch(count($pools)-$round){
			case 1:
			$roundname = U_("Finals");
			break;
			case 2:
			$roundname = U_("Semifinals");
			break;
			case 3:
			$roundname = U_("Quarterfinals");
			break;
			default:
			$roundname = U_("Round") ." ". ($round+1);
			break;
		}
		$html=str_replace("[round ".($round+1)."]",$roundname,$html);
		
		$winners=0;
		$losers=0;
		$games=0;
		for($i=1;$i<=$totalteams;$i++){

			$team = PoolTeamFromInitialRank($pool['pool_id'],$i);
			$movefrom = PoolGetMoveFrom($pool['pool_id'],$i);
			
			$name = "";
            $byeName = "";
			//find out team name
			if($team['team_id']){
				if(intval($seasoninfo['isinternational']) && !empty($team['flagfile'])){
					$name .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/> ";
				}
				$name .= "<a href='?view=teamcard&amp;Team=".$team['team_id']."'>".utf8entities($team['name'])."</a>";
			}else{
			    $realteam = PoolTeamFromStandings($movefrom['frompool'],$movefrom['fromplacing']);
				$gamesleft = TeamPoolGamesLeft($realteam['team_id'], $movefrom['frompool']);
				$frompoolinfo = PoolInfo($movefrom['frompool']);
				$isodd = is_odd($totalteams) && $i==$totalteams;
				if($realteam['team_id'] && $frompoolinfo['played'] && mysql_num_rows($gamesleft)==0 && !$isodd){
					if(intval($seasoninfo['isinternational']) && !empty($realteam['flagfile'])){
						$name .= "<img height='10' src='images/flags/tiny/".$realteam['flagfile']."' alt=''/> ";
					}
					$name .= "<i>".utf8entities($realteam['name'])."</i>";
			    }else{
			      $sname = SchedulingNameByMoveTo($pool['pool_id'],$i);
				  $name .= utf8entities(U_($sname['name']));
				}
				
			}
			
			if($team['team_id']){
    			$gamesinpool =TeamPoolGames($team['team_id'], $pool['pool_id']);
    			if (mysql_num_rows($gamesinpool)==0) { // that's the BYE team
    				$byeName = $name; // save its name 	
    				//echo $round." ".$name." ".$pool['pool_id']." ".$team['team_id']."<br>";				
    			}
			}
			//update team name to template
			if($round==0){
				$html=str_replace("[team $i]",$name,$html);
			}else{
				if($movefrom['fromplacing']==$totalteams && $totalteams%2==1){ // Assuming the BYE team is always last in a pool
					$winners=ceil($movefrom['fromplacing']/2);
					
					$html=str_replace("[winner $round/$winners]",$previousRoundByeName,$html);
				} elseif($movefrom['fromplacing']%2==1){		
					$winners=ceil($movefrom['fromplacing']/2);
					$html=str_replace("[winner $round/$winners]",$name,$html);
				}else{
					$losers=ceil($movefrom['fromplacing']/2);
					$html=str_replace("[loser $round/$losers]",$name,$html);
				}
			}
			
			//update game results
			if($i%2==1){
				$games++;
				$game = "";
				if($team['team_id']){
					$results = GameHomeTeamResults($team['team_id'], $pool['pool_id']);
					foreach($results as $res){
						if($res['scoresheet'] && !$res['isongoing']){
						  $game .= "<a href='?view=gameplay&amp;Game=". $res['game_id'] ."'>";
						  $game .= $res['homescore']."-".$res['visitorscore']."</a> ";
						}elseif($res['homescore'] + $res['visitorscore']>0 && !$res['isongoing']){
						  $game .= $res['homescore']."-".$res['visitorscore'];
						}elseif(!empty($res['gamename'])){
						  $game .= "<span class='lowlight'>".utf8entities(U_($res['gamename']))."</span>";
						}
					}
				}
				if(empty($game) && isset($sname['scheduling_id'])){
				  $results = GameHomePseudoTeamResults($sname['scheduling_id'], $pool['pool_id']);
				  foreach($results as $res){
					if(!empty($res['gamename'])){
					  $game .= "<span class='lowlight'>".utf8entities(U_($res['gamename']))."</span>";
					}
				  }		  
				}
				
				if(empty($game)){
				  //$game = "&nbsp;";
				  $game .= "<span class='lowlight'>"._("Game")." ".$games."</span>";
				}
				$html=str_replace("[game ".($round+1)."/$games]",$game,$html);
			}
			
			
		}
		if ($totalteams%2 == 1) {
			$previousRoundByeName=$byeName; // save previous pool Bye name
		}
		$round++;	
	}
	
	//placements
	$html=str_replace("[placement]",_("Placement"),$html);
	for($i=1;$i<=$totalteams;$i++){
		
		$placementname = "";
		$team = PoolTeamFromStandings($pool['pool_id'],$i);
		$gamesleft = TeamPoolGamesLeft($team['team_id'], $pool['pool_id']);
		
		if(!PoolMoveExist($pool['pool_id'],$i)){
			$placement = PoolPlacementString($pool['pool_id'],$i);
	        $placementname = "<b>".U_($placement) ."</b> ";
			if(mysql_num_rows($gamesleft)==0){
				if(intval($seasoninfo['isinternational']) && !empty($team['flagfile'])){
					$placementname .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/> ";
				}
				$placementname .= utf8entities($team['name'])."";
			}		
		}else{
			$movetopool = PoolGetMoveToPool($pool['pool_id'],$i);
			$placementname .= "<a href='?view=poolstatus&amp;Pool=".$movetopool['topool']."'>&raquo; ".utf8entities(U_($movetopool['name']))."</a>&nbsp; ";
			
			$gamesleft = TeamPoolGamesLeft($team['team_id'], $pool['pool_id']);
			
			if(mysql_num_rows($gamesleft)==0){
    			if(intval($seasoninfo['isinternational']) && !empty($team['flagfile'])){
    					$placementname .= "<img height='10' src='images/flags/tiny/".$team['flagfile']."' alt=''/> ";
    			}
    			
    			$placementname .= utf8entities($team['name']);
			}
		}
			
		$html=str_replace("[placement $i]",$placementname,$html);	
		
				
	}
	
	echo $html;
	echo "<p><a href='?view=games&amp;Pools=".implode(",",$pools)."'>"._("Schedule")."</a><br/></p>";

}

function printCrossmatchPool($seasoninfo, $poolinfo){

    $style = "";

	if($poolinfo['played']){
		$style = "style='font-weight: bold;'";
	}
	
  	echo "<table $style width='100%'>\n";
  	
  	
	$games = TimetableGames($poolinfo['pool_id'], "pool", "all", "crossmatch");
	$i=0;
	$pos=1;
	$winnerpools = array();
	$loserpools = array();
	
	while($game = mysql_fetch_assoc($games)){
	  $i++;
	  $winnerspool = PoolGetMoveToPool($poolinfo['pool_id'],$pos);
  	  $winnerpoolstyle = "background-color:#".$winnerspool['color'].";background-color:".RGBtoRGBa($winnerspool['color'],0.3).";color:#".textColor($winnerspool['color']);
  	  $winnerpools[$winnerspool['topool']]=$winnerspool['color'];
  	  
  	  $loserspool = PoolGetMoveToPool($poolinfo['pool_id'],$pos+1);
  	  $loserpoolstyle = "background-color:#".$loserspool['color'].";background-color:".RGBtoRGBa($loserspool['color'],0.3).";color:#".textColor($loserspool['color']);
  	  $loserspools[$loserspool['topool']]=$loserspool['color'];
  	  
	  echo "<tr>";
	  echo "<td class='center' style='".$winnerpoolstyle."'></td>";
      echo "<td class='center' style='".$loserpoolstyle."'></td>";
      echo "<td style='width:10%'>"._("Game")." $i "."</td>";
      echo "<td></td>";
      
	  $goals = intval($game['homescore'])+intval($game['visitorscore']);
	  
      if($goals && !intval($game['isongoing']) && $game['hometeam'] && $game['visitorteam']){
        if(intval($game['homescore'])>intval($game['visitorscore'])){
  		  echo "<td style='".$winnerpoolstyle."'><a href='?view=teamcard&amp;Team=".$game['hometeam']."'>". utf8entities($game['hometeamname']) ."</a></td>\n";
  		  echo "<td class='center'>-</td>\n";
  		  echo "<td style='".$loserpoolstyle."'><a href='?view=teamcard&amp;Team=".$game['visitorteam']."'>". utf8entities($game['visitorteamname']) ."</a></td>\n";
        }else{
  		  echo "<td style='".$loserpoolstyle."'><a href='?view=teamcard&amp;Team=".$game['hometeam']."'>". utf8entities($game['hometeamname']) ."</a></td>\n";
  		  echo "<td class='center'>-</td>\n";
  		  echo "<td style='".$winnerpoolstyle."'><a href='?view=teamcard&amp;Team=".$game['visitorteam']."'>". utf8entities($game['visitorteamname']) ."</a></td>\n";
        }
	  }else{
        if($game['hometeam']){
  		  echo "<td><a href='?view=teamcard&amp;Team=".$game['hometeam']."'>". utf8entities($game['hometeamname']) ."</a></td>\n";
        }else{
          echo "<td>". utf8entities($game['phometeamname']) ."</td>\n";
        }
  		echo "<td class='center'>-</td>\n";
  		if($game['visitorteam']){
  		  echo "<td><a href='?view=teamcard&amp;Team=".$game['visitorteam']."'>". utf8entities($game['visitorteamname']) ."</a></td>\n";
  		}else{
  		  echo "<td>". utf8entities($game['pvisitorteamname']) ."</td>\n";
  		}
	  }
	  
	  if(!$goals){
		echo "<td>?</td>\n";
		echo "<td>-</td>\n";	
		echo "<td>?</td>\n";
	  }else{
		echo "<td>".intval($game['homescore'])."</td>\n";	
		echo "<td>-</td>\n";		
		echo "<td>".intval($game['visitorscore'])."</td>\n";
	  }
	  
	  if(!intval($game['isongoing'])){
		if(intval($game['scoresheet'])){
		  echo "<td class='right'>&nbsp;<a href='?view=gameplay&amp;Game=". $game['game_id'] ."'>";
		  echo _("Game play") ."</a></td>\n";
	    }else{
		  echo "<td class='left'></td>\n";
	    }
	  }else{
		if(intval($game['scoresheet'])){
		  echo "<td class='right'>&nbsp;&nbsp;<a href='?view=gameplay&amp;Game=". $game['game_id'] ."'>";
		  echo _("Ongoing") ."</a></td>\n";
	    }else{
		  echo "<td class='right'>&nbsp;&nbsp;"._("Ongoing")."</td>\n";
	    }
      }
	  echo "</tr>\n";
	  $pos +=2;
	}
	echo "</table>\n";
	
	echo "<table style='white-space: nowrap' cellpadding='2' width='100%'><tr>\n";
    
	echo "<td>"._("Winners continues in:")."</td>";
	foreach ($winnerpools as $winnerId => $color) {
      echo "<td style='background-color:#".$color.";background-color:".RGBtoRGBa($color,0.3).";color:#".textColor($color).";width:".(50/count($winnerpools))."%'>";
  	  if($winnerspool['visible']){
  	    echo "<a href='?view=poolstatus&amp;Pool=".$winnerId."'>".utf8entities(U_(PoolName($winnerId)))."</a>";
  	  }else{
    	echo utf8entities(U_(PoolName($winnerId)));
  	  }
  	  echo "</td>";
      }
    
    echo "<td>"._("Losers continues in:")."</td>";      
    foreach ($loserspools as $loserId => $color) {
      echo "<td style='background-color:#".$color.";background-color:".RGBtoRGBa($color,0.3).";color:#".textColor($color).";width:".(50/count($loserspools))."%'>";
      if($loserspool['visible']){
    	echo "<a href='?view=poolstatus&amp;Pool=".$loserId."'>".utf8entities(PoolName($loserId))."</a>";
      }else{
    	echo utf8entities(PoolName($loserId));
      }
	  echo "</td>";
    }	
	echo "</tr></table>\n";	
		
	echo "<p><a href='?view=games&amp;Pool=".$poolinfo['pool_id']."'>"._("Schedule")."</a><br/></p>";
}
?>
