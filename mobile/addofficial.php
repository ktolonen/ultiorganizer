<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
$html = "";

$gameId = intval(iget("game"));
$game_result = GameResult($gameId);

if (isset($_POST['save'])) {
	GameSetScoreSheetKeeper($gameId, $_POST['official']);
	header("location:?view=mobile/addscoresheet&game=" . $gameId);
}

mobilePageTop(_("Score&nbsp;sheet"));

$html .= "<form action='?" . utf8entities($_SERVER['QUERY_STRING']) . "' method='post'>\n";
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= _("Game official") . ":";
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='input' style='width: 90%' type='text' name='official' id='official' value='" . utf8entities($game_result['official']) . "'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/addscoresheet&amp;game=" . $gameId . "'>" . _("Back to score sheet") . "</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>";

echo $html;

pageEnd();
