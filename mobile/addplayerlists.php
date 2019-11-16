<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
$html = "";

$gameId = intval(iget("game"));

if(iget("team")){
	$teamId = intval(iget("team"));
}else{
	$game_result = GameResult($gameId);
	$teamId = $game_result['hometeam'];
}

if(isset($_POST['save'])) {

	$played_players = GamePlayers($gameId, $teamId);
	
	//delete unchecked players
	foreach($played_players as $player){
		$found=false;
		if(!empty($_POST["check"]))	{
			foreach($_POST["check"] as $playerId) {
				if($player['player_id']==$playerId)	{
					$found=true;
					break;
				}
			}
		}
		if(!$found)
			GameRemovePlayer($gameId, $player['player_id']);
	}
	
	//handle checked players
	if(!empty($_POST["check"])) {
		foreach($_POST["check"] as $playerId) {
			$number = $_POST["p$playerId"];
			//if number
			if(is_numeric($number))	{
				//check if already in list with correct number
				$played_players = GamePlayers($gameId, $teamId);
				$found = false;
				foreach($played_players as $player){

					//if exist
					if($player['player_id']==$playerId && $player['num']==$number) {
						$found = true;
						break;
					}
					//if found, but with different number
					if($player['player_id']==$playerId && $player['num']!=$number) {
						GameSetPlayerNumber($gameId, $playerId, $number);
						$found = true;
						break;
					}
					//if two players with same number
					if($player['player_id']!=$playerId && $player['num']==$number) {
						$playerinfo1 = PlayerInfo($playerId);
						$playerinfo2 = PlayerInfo($player['player_id']);
						$html .= "<p  class='warning'><i>". utf8entities($playerinfo1['firstname'] ." ". $playerinfo1['lastname']) ."</i> " . _("and")
							." <i>". utf8entities($playerinfo2['firstname'] ." ". $playerinfo2['lastname']) ."</i> ". _("same number"). " '$number'.</p>";
						$found = true;
						break;
						}
				}
					
				if(!$found)
					GameAddPlayer($gameId, $playerId, $number);
			}else {
				$playerinfo = PlayerInfo($playerId);
				$html .= "<p  class='warning'><i>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</i> ". _("erroneous number"). " '$number'.</p>";
			}
		}
	}

	if(empty($html)){
		$game_result = GameResult($gameId);
		if($teamId==$game_result['hometeam']){
			header("location:?view=mobile/addplayerlists&game=".$gameId."&team=".$game_result['visitorteam']);
		}elseif($teamId==$game_result['visitorteam']){
			header("location:?view=mobile/addscoresheet&game=".$gameId);
		}
	}
}

mobilePageTop(_("Rosters"));

$playerlist = TeamPlayerList($teamId);

$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n"; 
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= "<b>".utf8entities(TeamName($teamId)) ."</b> "._("roster");

$played_players = GamePlayers($gameId, $teamId);
	
$i=0;
while($player = mysqli_fetch_assoc($playerlist))	{
	$i++;
	$playerinfo = PlayerInfo($player['player_id']);
	$number = PlayerNumber($player['player_id'], $gameId);
	if($number<0){$number="";}
	
	$found=false;
	foreach($played_players as $playerId) {
		if($player['player_id']==$playerId['player_id'])	{
			$found=true;
			break;
		}
	}

	$html .= "</td></tr><tr><td>\n";
	$html .= "<input class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' maxlength='3' size='2' value='$number'/> ";
	
	if($found || count($played_players)==0){
		$html .= "<input class='center' type='checkbox' name='check[]' value='".utf8entities($player['player_id'])."' checked='checked'/>";
	}else{
		$html .= "<input class='center' type='checkbox' name='check[]' value='".utf8entities($player['player_id'])."'/>";
	}
	$html .= utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']);
}
	
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='save' value='"._("Save")."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/respgames'>"._("Back to game responsibilities")."</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>"; 

echo $html;
		
pageEnd();
?>
