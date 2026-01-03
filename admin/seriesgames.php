<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/database.php';
include_once 'lib/pool.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/reservation.functions.php';

$LAYOUT_ID = POOLGAMES;

if (!empty($_GET["season"]))
	$season = $_GET["season"];

if (!empty($_GET["series"])) {
	$seriesId = $_GET["series"];
	if (empty($season)) {
		$season = SeriesSeasonId($seriesId);
	}
}

$seriesinfo = SeriesInfo($seriesId);
$rounds = 1;
$nomutual = 0;
$matches = 1;
$homeresp = isset($_POST["homeresp"]);

$title = utf8entities(U_($seriesinfo['name'])) . ": " . _("Games");
$html = "";

if (!empty($_POST['generate'])) {
	if (!empty($_POST['rounds'])) {
		$rounds = $_POST['rounds'];
	}
	if (!empty($_POST['matches'])) {
		$matches = $_POST['matches'];
	}
	$nomutual = isset($_POST["nomutual"]);

	$pools = SeriesPools($seriesId);

	foreach ($pools as $pool) {
		if (!CanGenerateGames($pool['pool_id'])) {
			continue;
		}
		$info = PoolInfo($pool['pool_id']);
		if ($info['type'] == 1) {

			if ($info['mvgames'] == 2) {
				GenerateGames($pool['pool_id'], $rounds, true, $nomutual, $homeresp);
			} else {
				GenerateGames($pool['pool_id'], $rounds, true, false, $homeresp);
			}
		} elseif ($info['type'] == 2) {
			GenerateGames($pool['pool_id'], $matches, true);
			//generate pools needed to solve standings
			$generatedpools = GeneratePlayoffPools($pool['pool_id'], true);

			//generate games into generated pools
			foreach ($generatedpools as $gpool) {
				if (CanGenerateGames($gpool['pool_id'])) {
					GenerateGames($gpool['pool_id'], $matches, true);
				}
			}
		}
	}
	session_write_close();
	header("location:?view=admin/seasonpools&season=$season");
}

//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<form method='post' action='?view=admin/seriesgames&amp;season=$season&amp;series=$seriesId'>";

$html .= "<h2>" . _("Creation of games") . "</h2>\n";
$html .= "<p><b>" . _("Round Robin pool") . "</b></p>\n";
$html .= "<p>" . _("Game rounds") . ": <input class='input' size='2' name='rounds' value='$rounds'/></p>\n";
$html .= "<p><input class='input' type='checkbox' name='nomutual'";
if ($nomutual) {
	$html .= "checked='checked'";
}
$html .= "/> " . _("Do not generate mutual games for teams moved from same pool, if pool format includes mutual games") . ".</p>";
$html .= "<p><b>" . _("Play-off pool") . "</b></p>\n";
$html .= "<p>" . _("best of") . " <input class='input' size='2' name='matches' value='$matches'/></p>\n";
$html .= "<p>" . _("Home team has rights to edit game score sheet") . ":<input class='input' type='checkbox' name='homeresp'";
if (isRespTeamHomeTeam()) {
	$html .= "checked='checked'";
}
$html .= "/></p>";
$html .= "<p><input type='submit' name='generate' value='" . _("Generate all games") . "'/></p>";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
