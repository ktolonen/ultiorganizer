<?php
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/player.functions.php';
include_once $include_prefix.'lib/location.functions.php';
include_once $include_prefix.'lib/configuration.functions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	include_once 'lib/twitter.functions.php';
}

$LAYOUT_ID = ADDDEFENSESHEET;
$title = _("Feed in defense sheet");
$maxtimeouts = 6;
$maxdefenses = 31;
ob_start();
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'lib/yui.functions.php';
echo yuiLoad(array("yahoo-dom-event"));
?>
<script type="text/javascript">
<!--
function validTime(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '.');
	}

function validNumber(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '');
	}

function validNumberX(field) 
	{
	field.value=field.value.replace(/[^0-9|^xX]/g, '');
	}

function highlightError(id) 
	{
	var errorDiv = YAHOO.util.Dom.get(id);
	YAHOO.util.Dom.setStyle(errorDiv,"background-color","#FF0000");
	}
</script>

<?php
$scrolling = "onkeypress='chgFocus(event);'";
pageTopHeadClose($title,false, $scrolling);
leftMenu($LAYOUT_ID);
contentStart();

//echo "First line"
$gameId = intval($_GET["game"]);
$season = GameSeason($gameId);
$seasoninfo = SeasonInfo($season);

//content
$menutabs[_("Result")]= "?view=user/addresult&game=$gameId";
$menutabs[_("Players")]= "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Score sheet")]= "?view=user/addscoresheet&game=$gameId";
if($seasoninfo['spiritmode']>0 && isSeasonAdmin($seasoninfo['season_id'])){
  $menutabs[_("Spirit points")]= "?view=user/addspirit&game=$gameId";
}
if(ShowDefenseStats())
{
  $menutabs[_("Defense sheet")]= "?view=user/adddefensesheet&game=$gameId";
}
pageMenu($menutabs);



$game_result = GameResult($gameId);
$homecaptain = -1;
$awaycaptain = -1;

$errIds=array();
//process itself if submit was pressed

if(!empty($_POST['save']))
	{
	LogDefenseUpdate($gameId, "defensesheet saved", "adddefensesheet");
	$time_delim = array(",", ";", ":");
	//GameAddDefense($gameId, $player, $home, $caught, $time, $iscallahan, $number)
	//set halftime
	$htime = $_POST['halftime'];
	$htime = str_replace($time_delim,".",$htime);
	$htime = TimeToSec($htime);
	
	//remove all old defenses (if any)
	GameRemoveAllDefenses($gameId);

	//insert defenses
	$h=0;
	$a=0;
	$prevtime=0;
	for($i=0;$i<$maxdefenses; $i++)
		{
		$team="";
		$defense=-1;
		$goal=-1;
		$caught="";
		$iscaught=-1;
		$callahan="";
		$iscallahan=-1;
		$time="";
		if(!empty($_POST['team'.$i]))
			$team = $_POST['team'.$i];
		if(!empty($_POST['defense'.$i]) || $_POST['defense'.$i]=="0")
			$defense = $_POST['defense'.$i];
		if(!empty($_POST['caught'.$i]))
			$caught = $_POST['caught'.$i];
		if(!empty($_POST['callahan'.$i]))
			$callahan = $_POST['callahan'.$i];
		if(!empty($_POST['time'.$i]))
			$time = $_POST['time'.$i];
		
		$time = str_replace($time_delim,".",$time);
		$time = TimeToSec($time);
		if(!empty($team) && $time == $htime){
			echo "<p class='warning'>"._("Defense")." ",$i+1,": "._("time can not be the same as half-time ending")."!</p>";
			$errIds[]="time$i";
		}
			
		if(!empty($team) && $time <= $prevtime){
			echo "<p class='warning'>"._("Defense")." ",$i+1,": "._("time can not be the same or earlier than the previous point")."!</p>";
			$errIds[]="time$i";
		}
		
		//if(strcasecmp($pass,'xx')==0 || strcasecmp($pass,'x')==0)
		//	$iscallahan = 1;
			
		$prevtime = $time;
		if(!empty($caught) && $caught=='C')
			{
			$iscaught=1;
			}
		else if(!empty($caught) && $caught=='T')
			{
			$iscaught=0;
			}
		if(!empty($callahan) && $callahan=='L')
			{
			$iscallahan=1;
			}
		else if(!empty($callahan) && $callahan=='N')
			{
			$iscallahan=0;
			}
			
			
		if(!empty($team) && $team=='H')
			{
			$h++;
			
			$defense = GamePlayerFromNumber($gameId, $game_result['hometeam'], $defense);
			if($defense==-1){
				echo "<p class='warning'>"._("Defense")." ",$i+1,": "._("player's number")." '".$_POST['defense'.$i]."' "._("Not on the roster")."!</p>";
				$errIds[]="defense$i";
			}
			GameAddDefense($gameId, $defense, 1, $iscaught, $time, $iscallahan, $i+1);
			//GameAddScore($gameId,$pass,$goal,$time,$i+1,$h,$a,1,$iscallahan);
			}
		elseif(!empty($team) && $team=='A')
			{
			$a++;
			$defense = GamePlayerFromNumber($gameId, $game_result['visitorteam'], $defense);
			if($defense==-1){
				echo "<p class='warning'>"._("Defense")." ",$i+1,": "._("player's number")." '".$_POST['defense'.$i]."' "._("Not on the roster")."!</p>";
				$errIds[]="defense$i";
			}

			GameAddDefense($gameId, $defense, 0, $iscaught, $time, $iscallahan, $i+1);
			}
		}
		GameSetDefenses($gameId, $h, $a);
	echo "<p>"._("Defense sheet saved")." (". _("Time").": ".DefTimestamp().")!</p>";
	// The defenseplay.php needs to be created
	//echo "<a href='?view=gameplay&amp;game=$gameId'>"._("Game play")."</a>";
	}
$game_result = GameResult($gameId);
$place = ReservationInfo($game_result['reservation']);
$homecaptain = GameCaptain($gameId, $game_result['hometeam']);
$awaycaptain = GameCaptain($gameId, $game_result['visitorteam']);
$home_playerlist = GamePlayers($gameId, $game_result['hometeam']);
$away_playerlist = GamePlayers($gameId, $game_result['visitorteam']);

if(count($home_playerlist)==0){
  echo "<p class='warning'>".utf8entities(sprintf(_("No players given for team %s."), $game_result['hometeamname']))."<a href='?view=user/addplayerlists&amp;game=".$gameId."'>"._("Feed in the players in the game.")."</a></p>";
}
if(count($away_playerlist)==0){
  echo "<p class='warning'>".utf8entities(sprintf(_("No players given for team %s."), $game_result['visitorteamname']))."<a href='?view=user/addplayerlists&amp;game=".$gameId."'>"._("Feed in the players in the game.")."</a></p>";
}


echo "<form id='defensesheet' action='?view=user/adddefensesheet&amp;game=$gameId' method='post'>";
echo "<table cellspacing='5' cellpadding='5'>";

echo "<tr><td colspan='2'><h1>"._("Defense score sheet")." #$gameId</h1></td></tr>";
echo "<tr><td valign='top'>\n";

//team, place, time info and scoresheet keeper's name
echo "<table cellspacing='0' width='100%' border='1'>";
echo "<tr><th>"._("Home team")."</th></tr>";
echo "<tr><td>". utf8entities($game_result['hometeamname']) ."</td></tr>";
echo "<tr><th>"._("Away team")."</th></tr>";
echo "<tr><td>". utf8entities($game_result['visitorteamname']) ."</td></tr>";
echo "<tr><th>"._("Field")."</th></tr>";
echo "<tr><td>". utf8entities($place['name']) ." ". _("field")." ".utf8entities($place['fieldname']) ."</td></tr>";
echo "<tr><th>"._("Scheduled start date and time")."</th></tr>";
echo "<tr><td>". ShortDate($game_result['time']) ." ". DefHourFormat($game_result['time']) ."</td></tr>";
echo "<tr><th>"._("Game official(s)")."</th></tr>";
echo "<tr><td><input class='input' style='width: 90%' type='text' name='secretary' id='secretary' value='".utf8entities($game_result['official'])."'/></td></tr>";
echo "</table>\n";

//starting team
$hoffence="";
$voffence="";
$ishome = GameIsFirstOffenceHome($gameId);
if($ishome==1){
$hoffence="checked='checked'";
}elseif($ishome==0){
$voffence="checked='checked'";
}

echo "<table cellspacing='0' width='100%' border='1'>\n";
echo "<tr><th colspan='2'>"._("Starting offensive team")."</th></tr>";
echo "<tr><td style='width: 40px' class='center'><input id='hstart' name='starting' type='radio' $hoffence value='H' /></td>";

echo "<td>". utf8entities($game_result['hometeamname']) ."</td></tr>";
echo "<tr><td style='width: 40px' class='center'><input id='vstart' name='starting' type='radio' $voffence value='V' /></td>";
echo "<td>". utf8entities($game_result['visitorteamname']) ."</td></tr>";
echo "</table>\n";

//timeouts
echo "<table cellspacing='0' width='100%' border='1'>";
echo "<tr><th colspan='",$maxtimeouts+1,"'>"._("Time-outs")."</th></tr>\n";

echo "<tr><th>"._("Home")."</th>\n";

//home team used timeouts
$i=0;
$timeouts = GameTimeouts($gameId);
while($timeout = mysqli_fetch_assoc($timeouts))
	{
	if (intval($timeout['ishome']))
		{
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='5' maxlength='8' id='hto$i' name='hto$i' value='". SecToMin($timeout['time']) ."' /></td>\n";
		$i++;
		}
	}

//empty slots
for($i;$i<$maxtimeouts; $i++)
	{
	//two last slot are smaller for visual reasons
	if($i>($maxtimeouts-3))
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='1' maxlength='8' id='hto$i' name='hto$i' value='' /></td>\n";	
	else
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='5' maxlength='8' id='hto$i' name='hto$i' value='' /></td>\n";		
	}
echo "</tr>\n";

echo "<tr><th>"._("Away")."</th>\n";

//away team used timeouts
$i=0;
$timeouts = GameTimeouts($gameId);
while($timeout = mysqli_fetch_assoc($timeouts))
	{
	if (!intval($timeout['ishome']))
		{
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='5' maxlength='8' id='ato$i' name='ato$i' value='". SecToMin($timeout['time']) ."' /></td>\n";
		$i++;
		}
	}

//empty slots
for($i;$i<$maxtimeouts; $i++)
	{
	//two last slot are smaller for visual reasons
	if($i>($maxtimeouts-3))
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='1' maxlength='8' id='ato$i' name='ato$i' value='' /></td>\n";	
	else
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='5' maxlength='8' id='ato$i' name='ato$i' value='' /></td>\n";	
	}
	
echo "</tr>";
echo "</table>";

//halftime
echo "<table cellspacing='0' width='100%' border='1'>\n";
echo "<tr><th>"._("Half-time ended at")."</th></tr>";
echo "<tr><td><input class='input' onkeyup=\"validTime(this);\"
	maxlength='8' type='text' name='halftime' id='halftime' value='". SecToMin($game_result['halftime']) ."'/></td></tr>";
echo "</table>\n";

//result		
echo "<table cellspacing='0' width='100%' border='1'>\n";
if ($game_result['isongoing']) {
	echo "<tr><th>"._("Current score")."</th></tr>";
}else{
	echo "<tr><th>"._("Final score")."</th></tr>";
}
echo "<tr><td>". $game_result['homescore'] ." - ". $game_result['visitorscore'] ."</td></tr>";
echo "</table>\n";

echo "<table cellspacing='0' width='100%' border='1'>\n";
echo "<tr><th colspan='2'>"._("Captains")."</th></tr>";
echo "<tr><td>". utf8entities($game_result['hometeamname']) ."</td>";
echo "<td><select style='width:100%' class='dropdown' name='homecaptain'>\n";
echo "<option class='dropdown' value=''></option>\n";
foreach($home_playerlist as $player){
	$playerInfo = PlayerInfo($player['player_id']);
	if($homecaptain==$player['player_id'])
		echo "<option class='dropdown' selected='selected' value='".utf8entities($player['player_id'])."'>".utf8entities($playerInfo['firstname'] ." ". $playerInfo['lastname'])."</option>\n";
	else
		echo "<option class='dropdown' value='".utf8entities($player['player_id'])."'>".utf8entities($playerInfo['firstname'] ." ". $playerInfo['lastname'])."</option>\n";
}
echo  "</select></td>\n";
echo "</tr><tr>";
echo "<td>". utf8entities($game_result['visitorteamname']) ."</td>";
echo "<td><select style='width:100%' class='dropdown' name='awaycaptain'>\n";
echo "<option class='dropdown' value=''></option>\n";
foreach($away_playerlist as $player){
	$playerInfo = PlayerInfo($player['player_id']);
	if($awaycaptain==$player['player_id'])
		echo "<option class='dropdown' selected='selected' value='".utf8entities($player['player_id'])."'>".utf8entities($playerInfo['firstname'] ." ". $playerInfo['lastname'])."</option>\n";
	else
		echo "<option class='dropdown' value='".utf8entities($player['player_id'])."'>".utf8entities($playerInfo['firstname'] ." ". $playerInfo['lastname'])."</option>\n";
}
echo "</select></td>\n";
echo "</tr>";
echo "</table>\n";
		
//buttons
echo "<table cellspacing='0' cellpadding='10' width='100%'>\n";
// echo "<tr><td><input class='input' type='checkbox' name='isongoing' ";
// if ($game_result['isongoing']) {
// 	echo "checked='checked'";
// }
// echo "/> "._("Game ongoing")."</td><td></td></tr>";
// echo "<tr>";
echo "<td><input class='button' type='submit' value='"._("Save defenses")."' name='save'/></td>";
echo "<td><input class='button' type='reset' value='"._("Cancel")."' name='reset'/></td>";
echo "</tr>";
/*echo "<tr><td colspan='2'>
		<a href='javascript://' onclick=\"eraseLast()\">"._("Delete the last defense")."</a></td></tr>";*/

echo "<tr><td colspan='2'>
<p>"._("Feed in the scoresheet").":</p>
<ul>
<li>"._("In addition to the tab-key you can use the + key to change fields and the enter key to select a radio button.")."</li>
<li>"._("As separator in the time field you can use any of the '.', ',', ':', or ';' characters").".</li>
<li>"._("Input XX as the assist in Callahan goals").".</li>
<li>"._("You can save the score sheet at any time while feeding it in")."</li></ul></td></tr>";
echo "<tr><td colspan='2'><p><a href='?view=user/respgames'>"._("Back to game responsibilities")."</a></p></td></tr>";
echo "</table>\n";

//scores
$style_left = "border-left-style:solid;border-left-width:1px;border-left-color:#000000;";
$style_left .= "border-right-style:dashed;border-right-width:1px;border-right-color:#E0E0E0;";
$style_left .= "border-top-style:solid;border-top-width:1px;border-top-color:#000000;";
$style_left .= "border-bottom-style:solid;border-bottom-width:1px;border-bottom-color:#000000;";

$style_mid = "border-top-style:solid;border-top-width:1px;border-top-color:#000000;";
$style_mid .= "border-bottom-style:solid;border-bottom-width:1px;border-bottom-color:#000000;";
$style_mid .= "border-left-style:dashed;border-left-width:1px;border-left-color:#E0E0E0;";
$style_mid .= "border-right-style:dashed;border-right-width:1px;border-right-color:#E0E0E0;";

$style_right = "border-right-style:solid;border-right-width:1px;border-right-color:#000000;";
$style_right .= "border-top-style:solid;border-top-width:1px;border-top-color:#000000;";
$style_right .= "border-bottom-style:solid;border-bottom-width:1px;border-bottom-color:#000000;";
$style_right .= "border-left-style:dashed;border-left-width:1px;border-left-color:#E0E0E0;";

echo "</td><td>";
echo "<table style='border-collapse:collapse' cellspacing='0' cellpadding='2' border='0'>\n";
echo "<tr><th style='background-color:#FFFFFF;border-style:none;border-width:0;border-color:#FFFFFF'></th>";

echo "<th style='$style_left'>"._("Home")."</th><th style='$style_mid'>"._("Away")."</th>";
echo "<th style='$style_mid'>"._("Player")."</th><th style='$style_mid'>"._("Caught")."</th>";
echo "<th style='$style_mid'>"._("Touched")."</th><th style='$style_mid'>"._("Callahan")."</th>";
echo "<th style='$style_mid'>"._("Not callahan")."</th><th style='$style_right'>"._("Time")."</th>";
//echo "<th style='$style_right'>"._("Score")."</th></tr>\n";

$scores = GameDefenses($gameId);

$i=0;
while($row = mysqli_fetch_assoc($scores))
	{
	
	echo "<tr>"; 
	echo "<td class='center' style='width: 25px;color:#B0B0B0;'>",$i+1,"</td>\n";
	
	if (intval($row['ishomedefense']))
		{
		echo "<td style='width:40px;$style_left' class='center'><input id='hteam$i' name='team$i' type='radio' checked='checked' value='H' /></td>";
		echo "<td style='width:40px;$style_mid' class='center'><input id='ateam$i' name='team$i' type='radio' value='A' /></td>";			
		}
	else
		{
		echo "<td style='width:40px;$style_left' class='center'><input id='hteam$i' name='team$i' type='radio' value='H' /></td>";
		echo "<td style='width:40px;$style_mid' class='center'><input id='ateam$i' name='team$i' type='radio' checked='checked' value='A' /></td>";			
		}
	$n = PlayerNumber($row['author'],$gameId);
	if($n < 0)
		$n="";
		
	echo "<td class='center' style='width:50px;$style_mid'><input class='input' onkeyup=\"validNumber(this);\" id='defense$i' name='defense$i' maxlength='3' size='3' value='$n'/></td>";
	if (intval($row['iscaught']))
		{
		echo "<td style='width:40px;$style_left' class='center'><input id='caught$i' name='caught$i' type='radio' checked='checked' value='C' /></td>";
		echo "<td style='width:40px;$style_mid' class='center'><input id='touched$i' name='caught$i' type='radio' value='T' /></td>";			
		}
	else
		{
		echo "<td style='width:40px;$style_left' class='center'><input id='caught$i' name='caught$i' type='radio' value='C' /></td>";
		echo "<td style='width:40px;$style_mid' class='center'><input id='touched$i' name='caught$i' type='radio' checked='checked' value='T' /></td>";			
		}
	if (intval($row['iscallahan']))
		{
		echo "<td style='width:40px;$style_left' class='center'><input id='callahan$i' name='callahan$i' type='radio' checked='checked' value='L' /></td>";
		echo "<td style='width:40px;$style_mid' class='center'><input id='notCallahan$i' name='callahan$i' type='radio' value='N' /></td>";
		}
	else
		{
		echo "<td style='width:40px;$style_left' class='center'><input id='callahan$i' name='callahan$i' type='radio' value='L' /></td>";
		echo "<td style='width:40px;$style_mid' class='center'><input id='notCallahan$i' name='callahan$i' type='radio' checked='checked' value='N' /></td>";
		}
	
	
	echo "<td style='width:60px;$style_mid'><input class='input' onkeyup=\"validTime(this);\" id='time$i' name='time$i' maxlength='8' size='8' value='". SecToMin($row['time']) ."'/></td>";
	
	echo "</tr>\n";
	$i++;	
	}


for($i;$i<$maxdefenses; $i++)
	{
	echo "<tr>"; 
	echo "<td class='center' style='width:25px;color:#B0B0B0;'>",$i+1,"</td>\n";
	echo "<td class='center' style='width:40px;$style_left'><input id='hteam$i' name='team$i' type='radio' value='H' /></td>";
	echo "<td class='center' style='width:40px;$style_mid'><input id='ateam$i' name='team$i' type='radio' value='A' /></td>";			
	echo "<td  class='center' style='width:50px;$style_mid'><input class='input' onkeyup=\"validNumber(this);\" id='defense$i' name='defense$i' size='3' maxlength='3'/></td>";
	echo "<td style='width:40px;$style_left' class='center'><input id='caught$i' name='caught$i' type='radio' value='C' /></td>";
	echo "<td style='width:40px;$style_mid' class='center'><input id='touched$i' name='caught$i' type='radio' value='T' /></td>";
	echo "<td style='width:40px;$style_left' class='center'><input id='callahan$i' name='callahan$i' type='radio'  value='L' /></td>";
	echo "<td style='width:40px;$style_mid' class='center'><input id='notCallahan$i' name='callahan$i' type='radio' value='N' /></td>";
	echo "<td style='width:60px;$style_mid'><input class='input' onkeyup=\"validTime(this);\" id='time$i' name='time$i' maxlength='8' size='8'/></td>";
	
	echo "</tr>\n";
	}
echo "</table>\n";		
echo "</td></tr></table></form>\n";		

foreach($errIds as $id){
	echo "<script type=\"text/javascript\">highlightError(\"$id\");</script>";
	}

//common end
contentEnd();
pageEnd();
ob_end_flush();
?>
