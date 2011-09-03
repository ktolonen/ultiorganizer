<?php
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/player.functions.php';

$LAYOUT_ID = ADDPLAYERLISTS;
$title = _("Rosters");
$gameId = intval($_GET["Game"]);
$game_result = GameResult($gameId);

$season = GameSeason($gameId);
if(isset($_SERVER['HTTP_REFERER'])){
	$backurl = utf8entities($_SERVER['HTTP_REFERER']);
}else{
	$backurl = "?view=user/respgames&Season=$season";
}

$home_playerlist = TeamPlayerList($game_result['hometeam']);
$away_playerlist = TeamPlayerList($game_result['visitorteam']);

$html = "";

//process itself if submit was pressed
if(!empty($_POST['save']))
	{
	$backurl = $_POST['backurl'];
	LogGameUpdate($gameId, "playerlist saved", "addplayerlist");
	//HOME PLAYERS
	$played_players = GamePlayers($gameId, $game_result['hometeam']);
	
	//delete unchecked players
	while($player = mysql_fetch_assoc($played_players))
		{
		$found=false;
		if(!empty($_POST["homecheck"]))
			{
			foreach($_POST["homecheck"] as $playerId) 
				{
				if($player['player_id']==$playerId)
					{
					$found=true;
					break;
					}
				}
			}
		if(!$found)
			GameRemovePlayer($gameId, $player['player_id']);
		}
	
	//handle checked players
	if(!empty($_POST["homecheck"]))
		{
		foreach($_POST["homecheck"] as $playerId) 
			{
			$number = $_POST["p$playerId"];
			//if number
			if(is_numeric($number))
				{
				//check if already in list with correct number
				$played_players = GamePlayers($gameId, $game_result['hometeam']);
				$found = false;
				while($player = mysql_fetch_assoc($played_players))
					{
					//echo "<p>".$player['player_id']."==".$playerId ."&&". $player['num']."==".$number."</p>";

					//if exist
					if($player['player_id']==$playerId && $player['num']==$number)
						{
						$found = true;
						break;
						}
					//if found, but with different number
					if($player['player_id']==$playerId && $player['num']!=$number)
						{
						GameSetPlayerNumber($gameId, $playerId, $number);
						$found = true;
						break;
						}
					//if two players with same number
					if($player['player_id']!=$playerId && $player['num']==$number)
						{
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
				}
			else
				{
				$playerinfo = PlayerInfo($playerId);
				$html .= "<p  class='warning'><i>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</i> ". _("erroneous number"). " '$number'.</p>";
				}
			}
		}
	//AWAY PLAYERS
	$played_players = GamePlayers($gameId, $game_result['visitorteam']);
	
	//delete unchecked players
	while($player = mysql_fetch_assoc($played_players))
		{
		$found=false;
		if(!empty($_POST["awaycheck"]))
			{
			foreach($_POST["awaycheck"] as $playerId) 
				{
				if($player['player_id']==$playerId)
					{
					$found=true;
					break;
					}
				}
			}
		if(!$found)
			GameRemovePlayer($gameId, $player['player_id']);
		}
	
	if(!empty($_POST["awaycheck"]))
		{
		//handle checked players	
		foreach($_POST["awaycheck"] as $playerId) 
			{
			$number = $_POST["p$playerId"];
			//if number
			if(is_numeric($number))
				{
				//check if already in list with correct number
				$played_players = GamePlayers($gameId, $game_result['visitorteam']);
				$found = false;
				while($player = mysql_fetch_assoc($played_players))
					{
					//echo "<p>".$player['player_id']."==".$playerId ."&&". $player['num']."==".$number."</p>";

					//if exist
					if($player['player_id']==$playerId && $player['num']==$number)
						{
						$found = true;
						break;
						}
					//if found, but with different number
					if($player['player_id']==$playerId && $player['num']!=$number)
						{
						GameSetPlayerNumber($gameId, $playerId, $number);
						$found = true;
						break;
						}
					//if two players with same number
					if($player['player_id']!=$playerId && $player['num']==$number)
						{
						$playerinfo1 = PlayerInfo($playerId);
						$playerinfo2 = PlayerInfo($player['player_id']);
						$html .= "<p  class='warning'><i>". utf8entities($playerinfo1['firstname'] ." ". $playerinfo1['lastname']) ."</i> " . _("and")
							." <i>". utf8entities($playerinfo2['firstname'] ." ". $playerinfo2['lastname']) ."</i> ". _("same number"). "'$number'.</p>";
						$found = true;
						break;
						}
					}
					
				if(!$found)
					GameAddPlayer($gameId, $playerId, $number);
				}
			else
				{
				$playerinfo = PlayerInfo($playerId);
				$html .= "<p  class='warning'><i>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</i> ". _("erroneous number"). " '$number'.</p>";
				}
			}
		}
	$html .= "<p>"._("Player lists saved!")."</p>";
	}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
?>
<script type="text/javascript">
<!--
function toggleField(checkbox, fieldid) {
    var input = document.getElementById(fieldid);
	input.disabled = !checkbox.checked;
}
	
function checkAll(field){
	var div = document.getElementById(field);
		 
	var elems = div.getElementsByTagName( "input" );
	
	for (var i=1; i < elems.length; i++) {
		 switch(elems[i].type) {
			case "checkbox":
				elems[i].checked = !elems[i].checked;
				break;
			case "text":
				elems[i].disabled = !elems[i].disabled;
				break;
			}
	}
}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

echo $html;

echo "<form method='post' action='?view=user/addplayerlists&amp;Game=".$gameId."'>";

echo "<table width='600px'><tr><td valign='top' style='width:45%'>\n";

echo "<table width='100%' cellspacing='0' cellpadding='0' border='0'>\n";
echo "<tr style='height=20'><td align='center'><b>";
echo utf8entities($game_result['hometeamname']), "</b></td></tr>\n";
echo "</table><div id='home'><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
echo "<tr><th class='home'>"._("Name")."</th><th class='home right' style='white-space: nowrap' >"._("Played")." <input type='checkbox' onclick='checkAll(\"home\");'/></th><th class='home'>"._("Jersey#")."</th></tr>\n";

$players = GamePlayers($gameId, $game_result['hometeam']);
$played_players = array();
while ($row = mysql_fetch_assoc($players)) {
		$played_players[] = $row['player_id'];
	}
	
$i=0;
while($player = mysql_fetch_assoc($home_playerlist))
	{
	$i++;
	$playerinfo = PlayerInfo($player['player_id']);
	echo "<tr>";
	echo "<td>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</td>";
	$number = PlayerNumber($player['player_id'], $gameId);
	if($number<0){$number="";}
	
	$found=false;
	foreach($played_players as $playerId) 
		{
		if($player['player_id']==$playerId)
			{
			$found=true;
			break;
			}
		}
		
	if($found)
		{
		echo "<td class='center'>
			<input onchange=\"toggleField(this,'p".$player['player_id']."');\" type='checkbox' name='homecheck[]' value='".$player['player_id']."' checked='checked'/></td>";
		echo "<td  class='left'><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number'/></td>";
		}
	else
		{
		echo "<td class='center'>
			<input onchange=\"toggleField(this,'p".$player['player_id']."');\" type='checkbox' name='homecheck[]' value='".$player['player_id']."'/></td>";
		echo "<td class='left'><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number' disabled='disabled'/></td>";
		}
	echo "</tr>\n";		
	}
echo "<tr><td colspan='3'>";
echo _("Total number of players:")." ". mysql_num_rows($home_playerlist);
echo "</td></tr>";
	
echo "</table></div></td>\n<td style='width:10%'>&nbsp;</td><td valign='top' style='width:45%'>";

echo "<table width='100%' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td><b>";
echo utf8entities($game_result['visitorteamname']), "</b></td></tr>\n";
echo "</table><div id='away'><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
echo "<tr><th class='guest'>"._("Name")."</th><th class='guest right' style='white-space: nowrap'>"._("Played")." <input type='checkbox' onclick='checkAll(\"away\");'/></th><th class='guest'>"._("Jersey#")."</th></tr>\n";

$players = GamePlayers($gameId, $game_result['visitorteam']);
$played_players = array();
while ($row = mysql_fetch_assoc($players)) {
		$played_players[] = $row['player_id'];
	}
	
$i=0;	
while($player = mysql_fetch_assoc($away_playerlist))
	{
	$i++;
	$playerinfo = PlayerInfo($player['player_id']);
	echo "<tr>";
	echo "<td>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</td>";
	$number = PlayerNumber($player['player_id'], $gameId);
	if($number<0){$number="";}
	
	$found=false;
	foreach($played_players as $playerId) 
		{
		if($player['player_id']==$playerId)
			{
			$found=true;
			break;
			}
		}
		
	if($found)
		{
		echo "<td class='center'>
			<input onchange=\"toggleField(this,'p".$player['player_id']."');\" type='checkbox' name='awaycheck[]' value='".$player['player_id']."' checked='checked'/></td>";
		echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number'/></td>";
		}
	else
		{
		echo "<td class='center'>
			<input onchange=\"toggleField(this,'p".$player['player_id']."');\" type='checkbox' name='awaycheck[]' value='".$player['player_id']."'/></td>";
		echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number' disabled='disabled'/></td>";
		}
	echo "</tr>\n";	
	}
echo "<tr><td colspan='3'>";
echo _("Total number of players:")." ". mysql_num_rows($away_playerlist);
echo "</td></tr>";

echo "</table></div></td></tr></table>\n";
echo "<div><input type='hidden' name='backurl' value='$backurl'/></div>";
echo "<p>    
		<input class='button' type='submit' name='save' value='"._("Save")."'/>
		<input class='button' type='button' name='return'  value='"._("Return")."' onclick=\"window.location.href='$backurl'\"/>
	</p></form>";
	
echo "<p></p><p><a href='?view=user/addscoresheet&amp;Game=$gameId'>"._("Feed in score sheet")."</a></p>";

//common end
contentEnd();
pageEnd();
?>
