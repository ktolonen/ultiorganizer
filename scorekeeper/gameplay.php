<?php
$html = "";

$gameId = intval(iget("game"));
$game_result = GameResult($gameId);
$goals = GameGoals($gameId);
$gameevents = GameEvents($gameId);

$html .= "<div data-role='header'>\n";
$html .= "<h1>"._("Game play").": ".utf8entities($game_result['hometeamname'])." - ".utf8entities($game_result['visitorteamname'])."</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
    
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= "<b>". utf8entities($game_result['hometeamname']);
$html .= " - ";
$html .= utf8entities($game_result['visitorteamname']);
$html .= " ". intval($game_result['homescore']) ." - ". intval($game_result['visitorscore']) ."</b>";
$html .= "</td></tr><tr><td>\n";
if(mysqli_num_rows($goals) <= 0){
	$html .= _("Not fed in");
	$html .= "</td></tr><tr><td>\n";
	$html .=  "<a href='?view=addplayerlists&amp;game=".$gameId."&amp;team=".$game_result['hometeam']."'>"._("Feed in score sheet")."</a>";
}else{		
	$prevgoal = 0;
	while($goal = mysqli_fetch_assoc($goals)){

		if((intval($game_result['halftime']) >= $prevgoal) &&
						(intval($game_result['halftime']) < intval($goal['time']))){
			$html .= "<tr><td>";
			$html .= _("Half-time");
			$html .= "</td></tr>\n";
		}
		if(count($gameevents)){
			foreach($gameevents as $event){
				if((intval($event['time']) >= $prevgoal) &&
					(intval($event['time']) < intval($goal['time']))){
					if($event['type'] == "timeout")
						$gameevent = _("time-out");
					elseif($event['type'] == "turnover")
						$gameevent = _("turnover");
					elseif($event['type'] == "offence")
						$gameevent = _("offence");
					
					if(intval($event['ishome'])>0){
						$team = utf8entities($game_result['hometeamname']);
						$style = "class='homefontcolor'";
					}else{
						$team = utf8entities($game_result['visitorteamname']);
						$style = "class='guestfontcolor'";
					}
					
					$html .= "<tr><td $style>\n";
					$html .= SecToMin($event['time']) ." ". $team ." ". $gameevent;
					$html .= "</td></tr>\n";
				}
			}
		}
		if(intval($goal['ishomegoal'])==1)
			$style = "class='homefontcolor'";
		else
			$style = "class='guestfontcolor'";
		
		
		
		$html .= "<tr><td  $style>\n";
		$html .= SecToMin($goal['time']) ." ";
		$html .= $goal['homescore'] ." - ". $goal['visitorscore'] ." ";
		if(intval($goal['iscallahan'])){
			$html .= _("Callahan-goal")."&nbsp;";
		}else{
			$html .= utf8entities($goal['assistfirstname']) ." ". utf8entities($goal['assistlastname']) ." --> ";
		}			
		$html .= utf8entities($goal['scorerfirstname']) ." ". utf8entities($goal['scorerlastname']) ."&nbsp;";
		
		$html .= "</td></tr>\n";
		
		$prevgoal = intval($goal['time']);
	}
	
	$html .= "</td></tr><tr><td>\n";
	$html .= _("Game official").": ". utf8entities($game_result['official']);
}
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "<a href='?view=scoreboard&amp;game=$gameId&amp;team=".$game_result['hometeam']."' data-role='button' data-ajax='false'>".utf8entities($game_result['hometeamname'])." "._("scoreboard")."</a>";
$html .= "<a href='?view=scoreboard&amp;game=$gameId&amp;team=".$game_result['visitorteam']."' data-role='button' data-ajax='false'>".utf8entities($game_result['visitorteamname'])." "._("scoreboard")."</a>";
$html .= "<a href='?view=respgames' data-role='button' data-ajax='false'>"._("Back to game responsibilities")."</a>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
	

?>
