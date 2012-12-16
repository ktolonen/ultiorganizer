<?php
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/player.functions.php';

$LAYOUT_ID = ADDPLAYERLISTS;
$title = _("Rosters");
$gameId = intval($_GET["game"]);
$game_result = GameResult($gameId);

$season = GameSeason($gameId);
if(isset($_SERVER['HTTP_REFERER'])){
  $backurl = utf8entities($_SERVER['HTTP_REFERER']);
}else{
  $backurl = "?view=user/respgames&season=$season";
}

$home_playerlist = TeamPlayerList($game_result['hometeam']);
$away_playerlist = TeamPlayerList($game_result['visitorteam']);

$html = "";
$html2 = "";

//process itself if submit was pressed
if(!empty($_POST['save']))	{
  $backurl = $_POST['backurl'];
  LogGameUpdate($gameId, "playerlist saved", "addplayerlist");
  //HOME PLAYERS
  $played_players = GamePlayers($gameId, $game_result['hometeam']);

  //delete unchecked players
  foreach($played_players as $player){
    $found=false;
    if(!empty($_POST["homecheck"]))	 {
      foreach($_POST["homecheck"] as $playerId) {
        if($player['player_id']==$playerId)	{
          $found=true;
          break;
        }
      }
    }
    if(!$found){
      GameRemovePlayer($gameId, $player['player_id']);
    }
  }

  //handle checked players
  if(!empty($_POST["homecheck"])) {
    foreach($_POST["homecheck"] as $playerId) {
      $number = $_POST["p$playerId"];
      //if number
      if(is_numeric($number)) {
        //check if already in list with correct number
        $played_players = GamePlayers($gameId, $game_result['hometeam']);
        $found = false;
        foreach($played_players as $player){
          //$html .= "<p>".$player['player_id']."==".$playerId ."&&". $player['num']."==".$number."</p>";

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
            $html2 .= "<p  class='warning'><i>". utf8entities($playerinfo1['firstname'] ." ". $playerinfo1['lastname']) ."</i> " . _("and")
            ." <i>". utf8entities($playerinfo2['firstname'] ." ". $playerinfo2['lastname']) ."</i> ". _("same number"). " '$number'.</p>";
            $found = true;
            break;
          }
        }
         
        if(!$found){
          GameAddPlayer($gameId, $playerId, $number);
        }
      }else{
        $playerinfo = PlayerInfo($playerId);
        $html2 .= "<p  class='warning'><i>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</i> ". _("erroneous number"). " '$number'.</p>";
      }
    }
  }
  //AWAY PLAYERS
  $played_players = GamePlayers($gameId, $game_result['visitorteam']);

  //delete unchecked players
  foreach($played_players as $player){
    $found=false;
    if(!empty($_POST["awaycheck"])) {
      foreach($_POST["awaycheck"] as $playerId) {
        if($player['player_id']==$playerId) {
          $found=true;
          break;
        }
      }
    }
    if(!$found){
      GameRemovePlayer($gameId, $player['player_id']);
    }
  }

  if(!empty($_POST["awaycheck"])) {
    //handle checked players
    foreach($_POST["awaycheck"] as $playerId) {
      $number = $_POST["p$playerId"];
      //if number
      if(is_numeric($number)) {
        //check if already in list with correct number
        $played_players = GamePlayers($gameId, $game_result['visitorteam']);
        $found = false;
        foreach($played_players as $player){
          //$html .= "<p>".$player['player_id']."==".$playerId ."&&". $player['num']."==".$number."</p>";

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
            $html2 .= "<p  class='warning'><i>". utf8entities($playerinfo1['firstname'] ." ". $playerinfo1['lastname']) ."</i> " . _("and")
            ." <i>". utf8entities($playerinfo2['firstname'] ." ". $playerinfo2['lastname']) ."</i> ". _("same number"). "'$number'.</p>";
            $found = true;
            break;
          }
        }
         
        if(!$found){
          GameAddPlayer($gameId, $playerId, $number);
        }
      }else {
        $playerinfo = PlayerInfo($playerId);
        $html2 .= "<p  class='warning'><i>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</i> ". _("erroneous number"). " '$number'.</p>";
      }
    }
  }
  $html2 .= "<p>"._("Player lists saved!")."</p>";
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

$menutabs[_("Result")]= "?view=user/addresult&game=$gameId";
$menutabs[_("Players")]= "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Score sheet")]= "?view=user/addscoresheet&game=$gameId";
pageMenu($menutabs);



$html .= "<form method='post' action='?view=user/addplayerlists&amp;game=".$gameId."'>";

$html .= "<table width='600px'><tr><td valign='top' style='width:45%'>\n";

$html .= "<table width='100%' cellspacing='0' cellpadding='0' border='0'>\n";
$html .= "<tr style='height=20'><td align='center'><b>";
$html .= utf8entities($game_result['hometeamname']). "</b></td></tr>\n";
$html .= "</table><div id='home'><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
$html .= "<tr><th class='home'>"._("Name")."</th><th class='home right' style='white-space: nowrap' >"._("Played")." <input type='checkbox' onclick='checkAll(\"home\");'/></th><th class='home'>"._("Jersey#")."</th></tr>\n";

$played_players = GamePlayers($gameId, $game_result['hometeam']);

$i=0;
while($player = mysql_fetch_assoc($home_playerlist)){
  $i++;
  $playerinfo = PlayerInfo($player['player_id']);
  $html .= "<tr>";
  $html .= "<td>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</td>";
  $number = PlayerNumber($player['player_id'], $gameId);
  if($number<0){$number="";}

  $found=false;
  foreach($played_players as $playerId) {
    if($player['player_id']==$playerId['player_id']) {
      $found=true;
      break;
    }
  }

  if($found){
    $html .= "<td class='center'>
			<input onchange=\"toggleField(this,'p".$player['player_id']."');\" type='checkbox' name='homecheck[]' value='".$player['player_id']."' checked='checked'/></td>";
    $html .= "<td  class='left'><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number'/></td>";
  }else{
    $html .= "<td class='center'>
			<input onchange=\"toggleField(this,'p".$player['player_id']."');\" type='checkbox' name='homecheck[]' value='".$player['player_id']."'/></td>";
    $html .= "<td class='left'><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number' disabled='disabled'/></td>";
  }
  $html .= "</tr>\n";
}
$html .= "<tr><td colspan='3'>";
$html .= _("Total number of players:")." ". mysql_num_rows($home_playerlist);
$html .= "</td></tr>";

$html .= "</table></div></td>\n<td style='width:10%'>&nbsp;</td><td valign='top' style='width:45%'>";

$html .= "<table width='100%' cellspacing='0' cellpadding='0' border='0'>";
$html .= "<tr><td><b>";
$html .= utf8entities($game_result['visitorteamname']). "</b></td></tr>\n";
$html .= "</table><div id='away'><table width='100%' cellspacing='0' cellpadding='3' border='0'>";
$html .= "<tr><th class='guest'>"._("Name")."</th><th class='guest right' style='white-space: nowrap'>"._("Played")." <input type='checkbox' onclick='checkAll(\"away\");'/></th><th class='guest'>"._("Jersey#")."</th></tr>\n";

$played_players = GamePlayers($gameId, $game_result['visitorteam']);

$i=0;
while($player = mysql_fetch_assoc($away_playerlist)){
  $i++;
  $playerinfo = PlayerInfo($player['player_id']);
  $html .= "<tr>";
  $html .= "<td>". utf8entities($playerinfo['firstname'] ." ". $playerinfo['lastname']) ."</td>";
  $number = PlayerNumber($player['player_id'], $gameId);
  if($number<0){$number="";}

  $found=false;
  foreach($played_players as $playerId){
    if($player['player_id']==$playerId['player_id']){
      $found=true;
      break;
    }
  }

  if($found){
    $html .= "<td class='center'>
			<input onchange=\"toggleField(this,'p".$player['player_id']."');\" type='checkbox' name='awaycheck[]' value='".$player['player_id']."' checked='checked'/></td>";
    $html .= "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number'/></td>";
  }else{
    $html .= "<td class='center'>
			<input onchange=\"toggleField(this,'p".$player['player_id']."');\" type='checkbox' name='awaycheck[]' value='".$player['player_id']."'/></td>";
    $html .= "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' name='p".$player['player_id']."' id='p".$player['player_id']."' style='WIDTH: 20px' maxlength='2' size='2' value='$number' disabled='disabled'/></td>";
  }
  $html .= "</tr>\n";
}
$html .= "<tr><td colspan='3'>";
$html .= _("Total number of players:")." ". mysql_num_rows($away_playerlist);
$html .= "</td></tr>";

$html .= "</table></div></td></tr></table>\n";

$html .= $html2;

$html .= "<div><input type='hidden' name='backurl' value='$backurl'/></div>";
$html .= "<p><input class='button' type='submit' name='save' value='"._("Save")."'/></p></form>";

echo $html;

//common end
contentEnd();
pageEnd();
?>
