<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
$html = "";

$gameId = intval(iget("game"));
$game_result = GameResult($gameId);

if (isset($_POST['save'])) {
	if (!empty($_POST['starting'])) {
		$starting = $_POST['starting'];
		if ($starting == "H") {
			GameSetStartingTeam($gameId, 1);
		} elseif ($starting == "V") {
			GameSetStartingTeam($gameId, 0);
		}
	}
	header("location:?view=mobile/addscoresheet&game=" . $gameId);
}

mobilePageTop(_("Score&nbsp;sheet"));

//starting team
$hoffence = "";
$voffence = "";
$ishome = GameIsFirstOffenceHome($gameId);
if ($ishome == 1) {
	$hoffence = "checked='checked'";
} elseif ($ishome == 0) {
	$voffence = "checked='checked'";
}

$html .= "<form action='?" . utf8entities($_SERVER['QUERY_STRING']) . "' method='post'>\n";
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= _("starting on offence") . ":";
$html .= "</td></tr><tr><td>\n";
$html .= "<input id='hstart' name='starting' type='radio' $hoffence value='H' />";
$html .= " " . utf8entities($game_result['hometeamname']) . " ";
$html .= "<input id='vstart' name='starting' type='radio' $voffence value='V' />";
$html .= " " . utf8entities($game_result['visitorteamname']) . "";
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/addscoresheet&amp;game=" . $gameId . "'>" . _("Back to score sheet") . "</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>";

echo $html;

pageEnd();
