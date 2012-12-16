<?php
$html = "";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$game_result = GameResult($gameId);
	
if(isset($_POST['save'])) {
	GameSetSpiritPoints($gameId, intval($_POST['homespirit']), intval($_POST['awayspirit']));
	
	header("location:?view=addscoresheet&game=".$gameId);
	}

$html .= "<div data-role='header'>\n";
$html .= "<h1>"._("Spirit points").": ".utf8entities($game_result['hometeamname'])." - ".utf8entities($game_result['visitorteamname'])."</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=addspiritpoints' method='post' data-ajax='false'>\n";

$html .= "<label for='homespirit'>".utf8entities($game_result['hometeamname']).":</label>";
$html .= "<input maxlength='4' size='5' type='number' name='homespirit' id='homespirit' value='". $game_result['homesotg'] ."'/>";

$html .= "<label for='awayspirit'>".utf8entities($game_result['visitorteamname']).":</label>";
$html .= "<input maxlength='4' size='5' type='number' name='awayspirit' id='awayspirit' value='". $game_result['visitorsotg'] ."'/>";


$html .= "<input type='submit' name='save' data-ajax='false' value='"._("Save")."'/>";
$html .= "<a href='?view=addscoresheet&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Back to score sheet")."</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
?>
