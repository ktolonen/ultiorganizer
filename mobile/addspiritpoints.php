<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
$html = "";

$gameId = intval(iget("game"));
$game_result = GameResult($gameId);
	
if(isset($_POST['save'])) {
	GameSetSpiritPoints($gameId, intval($_POST['homespirit']), intval($_POST['awayspirit']));
	
	header("location:?view=mobile/addscoresheet&game=".$gameId);
	}

mobilePageTop(_("Score&nbsp;sheet"));

$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n"; 
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= "<b>".utf8entities($game_result['hometeamname'])."</b> "._("spirit points").":";
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='input' maxlength='3' size='5' type='text' name='homespirit' id='homespirit' value='". $game_result['homesotg'] ."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<b>".utf8entities($game_result['visitorteamname'])."</b> "._("spirit points").":";
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='input' maxlength='3' size='5' type='text' name='awayspirit' id='awayspirit' value='". $game_result['visitorsotg'] ."'/>";
$html .= "</td></tr><tr><td>\n";

$html .= "<input class='button' type='submit' name='save' value='"._("Save")."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/addscoresheet&amp;game=".$gameId."'>"._("Back to score sheet")."</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>"; 

echo $html;
		
pageEnd();
?>
