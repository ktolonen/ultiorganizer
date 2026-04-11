<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/season.functions.php';

function MobileSpiritTimeoutValues($gameId, $home, $maxslots)
{
	$values = array();
	foreach (GameSpiritTimeoutsArray($gameId) as $timeout) {
		if ((int)$timeout['ishome'] === (int)$home && count($values) < $maxslots) {
			$values[] = SecToMin($timeout['time']);
		}
	}
	for ($i = count($values); $i < $maxslots; $i++) {
		$values[] = "";
	}
	return $values;
}

$html = "";
$maxSpiritTimeouts = 4;

$gameId = intval(iget("game"));
$game_result = GameResult($gameId);
$seasoninfo = SeasonInfo(GameSeason($gameId));

if (empty($seasoninfo['spiritmode']) || !empty($seasoninfo['hide_time_on_scoresheet'])) {
	header("location:?view=mobile/addtimeouts&game=" . $gameId);
	exit;
}

if (isset($_POST['save'])) {
	$time_delim = array(",", ";", ":", "#", "*");

	GameRemoveAllSpiritTimeouts($gameId);

	$j = 0;
	for ($i = 0; $i < $maxSpiritTimeouts; $i++) {
		$time = $_POST['hto' . $i];
		$time = str_replace($time_delim, ".", $time);
		if (!empty($time)) {
			$j++;
			GameAddSpiritTimeout($gameId, $j, TimeToSec($time), 1);
		}
	}

	$j = 0;
	for ($i = 0; $i < $maxSpiritTimeouts; $i++) {
		$time = $_POST['ato' . $i];
		$time = str_replace($time_delim, ".", $time);
		if (!empty($time)) {
			$j++;
			GameAddSpiritTimeout($gameId, $j, TimeToSec($time), 0);
		}
	}

	header("location:?view=mobile/addscoresheet&game=" . $gameId);
}

mobilePageTop(_("Spirit stoppages"));

$html .= "<form action='?" . utf8entities($_SERVER['QUERY_STRING']) . "' method='post'>\n";
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= "<b>" . utf8entities($game_result['hometeamname']) . "</b> " . _("spirit stoppages") . ":";
$html .= "</td></tr><tr><td>\n";

foreach (MobileSpiritTimeoutValues($gameId, 1, $maxSpiritTimeouts) as $i => $timeValue) {
	$html .= "<input class='input' type='text' size='5' maxlength='8' id='hto$i' name='hto$i' value='" . utf8entities($timeValue) . "' /> ";
}

$html .= "</td></tr><tr><td>\n";
$html .= "<b>" . utf8entities($game_result['visitorteamname']) . "</b> " . _("spirit stoppages") . ":";
$html .= "</td></tr><tr><td>\n";

foreach (MobileSpiritTimeoutValues($gameId, 0, $maxSpiritTimeouts) as $i => $timeValue) {
	$html .= "<input class='input' type='text' size='5' maxlength='8' id='ato$i' name='ato$i' value='" . utf8entities($timeValue) . "' /> ";
}

$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/addtimeouts&amp;game=" . $gameId . "'>" . _("Back to timeouts") . "</a>";
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/addscoresheet&amp;game=" . $gameId . "'>" . _("Back to scoresheet") . "</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>";

echo $html;

pageEnd();
