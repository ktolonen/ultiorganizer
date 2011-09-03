<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
$html = "";

$gameId = intval($_GET["Game"]);
$game_result = GameResult($gameId);
	
if(isset($_POST['save'])) {
	$time = "0.0";
	$time_delim = array(",", ";", ":", "#", "*");
	
	if(isset($_POST['halftime']))
		$time = $_POST['halftime'];

	$time = str_replace($time_delim,".",$time);
	$htime = TimeToSec($time);
	GameSetHalftime($gameId, $htime);
	
	header("location:?view=mobile/addscoresheet&Game=".$gameId);
	}

mobilePageTop(_("Score&nbsp;sheet"));

$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n"; 
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= _("Half time").":";
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='input' maxlength='8' type='text' name='halftime' id='halftime' value='". SecToMin($game_result['halftime']) ."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='save' value='"._("Save")."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/addscoresheet&amp;Game=".$gameId."'>"._("Back to score sheet")."</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>"; 

echo $html;
		
pageEnd();
?>
