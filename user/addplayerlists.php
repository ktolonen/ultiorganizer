<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
include_once '../lib/database.php';
include_once '../lib/common.functions.php';
include_once '../lib/game.functions.php';
include_once '../lib/team.functions.php';
include_once '../lib/player.functions.php';

include_once 'lib/game.functions.php';
$LAYOUT_ID = ADDPLAYERLISTS;

//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
?>
<script type="text/javascript">
<!--
function toggleField(checkbox, fieldid) 
	{
    var input = document.getElementById(fieldid);
	input.disabled = !checkbox.checked;
	}
//-->
</script>
<?php
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
//content
OpenConnection();
$gameId = intval($_GET["Game"]);
$game_result = GameResult($gameId);

$home_playerlist = TeamPlayerList($game_result['kotijoukkue']);
$away_playerlist = TeamPlayerList($game_result['vierasjoukkue']);

//process itself if submit was pressed
$save = $_POST['save'];
if(isset($save))
	{
	//HOME PLAYERS
	$played_players = GamePlayers($gameId, $game_result['kotijoukkue']);
	
	//delete unchecked players
	while($player = mysql_fetch_assoc($played_players))
		{
		$found=false;
		foreach($_POST["homecheck"] as $playerId) 
			{
			if($player['pelaaja_id']==$playerId)
				{
				$found=true;
				break;
				}
			}
		if(!$found)
			GameRemovePlayer($gameId, $player['pelaaja_id']);
		}
	
	//handle checked players
	foreach($_POST["homecheck"] as $playerId) 
		{
		$number = $_POST["p$playerId"];
		//if number
		if(is_numeric($number))
			{
			//check if already in list with correct number
			$played_players = GamePlayers($gameId, $game_result['kotijoukkue']);
			$found = false;
			while($player = mysql_fetch_assoc($played_players))
				{
				//echo "<p>".$player['pelaaja_id']."==".$playerId ."&&". $player['Numero']."==".$number."</p>";

				//if exist
				if($player['pelaaja_id']==$playerId && $player['Numero']==$number)
					{
					$found = true;
					break;
					}
				//if found, but with different number
				if($player['pelaaja_id']==$playerId && $player['Numero']!=$number)
					{
					GameSetPlayerNumber($gameId, $playerId, $number);
					$found = true;
					break;
					}
				//if two players with same number
				if($player['pelaaja_id']!=$playerId && $player['Numero']==$number)
					{
					$playerinfo1 = PlayerInfo($playerId);
					$playerinfo2 = PlayerInfo($player['pelaaja_id']);
					echo "<p  class='warning'><i>". htmlentities($playerinfo1['enimi'] ." ". $playerinfo1['snimi']) ."</i> ja 
					<i>". htmlentities($playerinfo2['enimi'] ." ". $playerinfo2['snimi']) ."</i> same numero '$number'.</p>";
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
			echo "<p  class='warning'><i>". htmlentities($playerinfo['enimi'] ." ". $playerinfo['snimi']) ."</i> virheellinen numero '$number'.</p>";
			}
		}
		
	//AWAY PLAYERS
	$played_players = GamePlayers($gameId, $game_result['vierasjoukkue']);
	
	//delete unchecked players
	while($player = mysql_fetch_assoc($played_players))
		{
		$found=false;
		foreach($_POST["awaycheck"] as $playerId) 
			{
			if($player['pelaaja_id']==$playerId)
				{
				$found=true;
				break;
				}
			}
		if(!$found)
			GameRemovePlayer($gameId, $player['pelaaja_id']);
		}
	
	//handle checked players	
	foreach($_POST["awaycheck"] as $playerId) 
		{
		$number = $_POST["p$playerId"];
		//if number
		if(is_numeric($number))
			{
			//check if already in list with correct number
			$played_players = GamePlayers($gameId, $game_result['vierasjoukkue']);
			$found = false;
			while($player = mysql_fetch_assoc($played_players))
				{
				//echo "<p>".$player['pelaaja_id']."==".$playerId ."&&". $player['Numero']."==".$number."</p>";

				//if exist
				if($player['pelaaja_id']==$playerId && $player['Numero']==$number)
					{
					$found = true;
					break;
					}
				//if found, but with different number
				if($player['pelaaja_id']==$playerId && $player['Numero']!=$number)
					{
					GameSetPlayerNumber($gameId, $playerId, $number);
					$found = true;
					break;
					}
				//if two players with same number
				if($player['pelaaja_id']!=$playerId && $player['Numero']==$number)
					{
					$playerinfo1 = PlayerInfo($playerId);
					$playerinfo2 = PlayerInfo($player['pelaaja_id']);
					echo "<p><i>". htmlentities($playerinfo1['enimi'] ." ". $playerinfo1['snimi']) ."</i> ja 
					<i>". htmlentities($playerinfo2['enimi'] ." ". $playerinfo2['snimi']) ."</i> same numero '$number'.</p>";
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
			echo "<p><i>". htmlentities($playerinfo['enimi'] ." ". $playerinfo['snimi']) ."</i> virheellinen numero '$number'.</p>";
			}
		}
	}

echo "<form method='post' action='addplayerlists.php?Game=".$gameId."'>";

echo "<table width='600px'><tr><td valign='top' style='width:45%'>\n";

echo "<table width='100%' cellspacing='0' cellpadding='0' border='0'>\n";
echo "<tr style='height=20'><td align='center'><b>";
echo htmlentities($game_result['KNimi']), "</b></td></tr>\n";
echo "</table><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
echo "<tr><th class='home'>#</th><th class='home'>Nimi</th><th class='home'>Paikalla</th><th class='home'>Numero</th></tr>\n";

$i=0;
while($player = mysql_fetch_assoc($home_playerlist))
	{
	$i++;
	$playerinfo = PlayerInfo($player['pelaaja_id']);
	echo "<tr>";
	echo "<td style='text-align:right'>$i</td>";
	echo "<td>". htmlentities($playerinfo['enimi'] ." ". $playerinfo['snimi']) ."</td>";
	$number = PlayerNumber($player['pelaaja_id'], $gameId);
	if($number >= 0)
		{
		echo "<td style='text-align: center;'>
			<input onchange=\"toggleField(this,'p".$player['pelaaja_id']."');\" type='checkbox' name='homecheck[]' value='".$player['pelaaja_id']."' checked='checked'/></td>";
		echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['pelaaja_id']."' id='p".$player['pelaaja_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number'/></td>";
		}
	else
		{
		echo "<td style='text-align: center;'>
			<input onchange=\"toggleField(this,'p".$player['pelaaja_id']."');\" type='checkbox' name='homecheck[]' value='".$player['pelaaja_id']."'/></td>";
		echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['pelaaja_id']."' id='p".$player['pelaaja_id']."' style='WIDTH: 20px' maxlength='2' size='2' disabled='disabled'/></td>";
		}
	echo "</tr>\n";		
	}
echo "<tr><td colspan='4'><a href='teamplayers.php?Team=".$game_result['kotijoukkue']."&amp;Game=$gameId'>Lis&auml;&auml; pelaaja</a></td></tr>";
	
echo "</table></td>\n<td style='width:10%'>&nbsp;</td><td valign='top' style='width:45%'>";

echo "<table width='100%' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td><b>";
echo htmlentities($game_result['VNimi']), "</b></td></tr>\n";
echo "</table><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
echo "<tr><th class='guest'>#</th><th class='guest'>Nimi</th><th class='guest'>Paikalla</th><th class='guest'>Numero</th></tr>\n";

$i=0;	
while($player = mysql_fetch_assoc($away_playerlist))
	{
	$i++;
	$playerinfo = PlayerInfo($player['pelaaja_id']);
	echo "<tr>";
	echo "<td style='text-align:right'>$i</td>";
	echo "<td>". htmlentities($playerinfo['enimi'] ." ". $playerinfo['snimi']) ."</td>";
	$number = PlayerNumber($player['pelaaja_id'], $gameId);
	if($number >= 0)
		{
		echo "<td style='text-align: center;'>
			<input onchange=\"toggleField(this,'p".$player['pelaaja_id']."');\" type='checkbox' name='awaycheck[]' value='".$player['pelaaja_id']."' checked='checked'/></td>";
		echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['pelaaja_id']."' id='p".$player['pelaaja_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number'/></td>";
		}
	else
		{
		echo "<td style='text-align: center;'>
			<input onchange=\"toggleField(this,'p".$player['pelaaja_id']."');\" type='checkbox' name='awaycheck[]' value='".$player['pelaaja_id']."'/></td>";
		echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['pelaaja_id']."' id='p".$player['pelaaja_id']."' style='WIDTH: 20px' maxlength='2' size='2' disabled='disabled'/></td>";
		}
	echo "</tr>\n";	
	}
echo "<tr><td colspan='4'><a href='teamplayers.php?Team=".$game_result['vierasjoukkue']."&amp;Game=$gameId'>Lis&auml;&auml; pelaaja</a></td></tr>";
	
echo "</table></td></tr></table>\n";
echo "<p>    
		<input class='button' type='submit' name='save' value='Tallenna'/>
	</p></form>";
	
echo "<p></p><p><a href='addscoresheet.php?Game=$gameId'>Sy&ouml;t&auml; p&ouml;yt&auml;kirja</a></p>";
echo "<p><a href='respgames.php'>Takaisin vastuupeleihin</a></p>";

CloseConnection();
//common end
contentEnd();
pageEnd();
?>